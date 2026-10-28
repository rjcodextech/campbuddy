<?php

namespace Tests\Feature;

use App\Mail\SponsorDealSubmittedMail;
use App\Models\Event;
use App\Models\MediaAsset;
use App\Models\Offer;
use App\Models\SponsorDealSubmission;
use App\Models\User;
use App\Support\EventData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SponsorDealSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Queue::fake();
    }

    private function createEvent(): Event
    {
        return Event::create([
            'slug' => 'delhi-2026',
            'display_name' => 'WordCamp Delhi 2026',
            'source_site_url' => 'https://delhi.wordcamp.org/2026',
            'status' => 'active',
            'is_visible' => true,
            'country_code' => 'IN',
            'starts_on' => now()->addDays(20),
            'ends_on' => now()->addDays(21),
        ]);
    }

    private function adminUser(): User
    {
        return User::factory()->create([
            'email_verified_at' => now(),
        ]);
    }

    public function test_sponsor_submission_page_renders_cleanly(): void
    {
        $event = $this->createEvent();

        $response = $this->get(route('sponsor-deals.create'));

        $response->assertOk();
        $response->assertSee('Submit Your WordCamp Deal');
        $response->assertSee($event->display_name);
    }

    public function test_sponsors_json_endpoint_returns_event_sponsors(): void
    {
        $event = $this->createEvent();

        // Seed event sponsor feed
        EventData::put($event->id, 'sponsors', [
            ['name' => 'Hostinger', 'website' => 'https://hostinger.com', 'logo_url' => 'https://hostinger.com/logo.png'],
            ['name' => 'Bluehost', 'website' => 'https://bluehost.com', 'logo_url' => 'https://bluehost.com/logo.png'],
        ]);

        $response = $this->getJson(route('sponsor-deals.sponsors', $event));

        $response->assertOk();
        $response->assertJsonPath('sponsors.0.name', 'Hostinger');
        $response->assertJsonPath('sponsors.1.name', 'Bluehost');
    }

    public function test_sponsor_can_submit_valid_deal_and_triggers_mail(): void
    {
        Mail::fake();

        $event = $this->createEvent();
        $token = Crypt::encryptString(json_encode([
            'time' => now()->subSeconds(10)->timestamp,
            'ip' => '127.0.0.1',
        ]));

        $response = $this->post(route('sponsor-deals.store'), [
            'event_id' => $event->id,
            'sponsor_name' => 'Hostinger',
            'brand' => 'Hostinger Cloud',
            'website' => 'hostinger.com',
            'title' => '30% off all hosting plans',
            'highlight' => '30% OFF',
            'description' => 'Special promotion for WordCamp attendees. Get 30% off any cloud or shared hosting plan.',
            'terms' => 'Valid until end of year. For new accounts only.',
            'coupon_code' => 'WCDELHI30',
            'url' => 'https://hostinger.com/wordcamp',
            'cta_label' => 'Claim 30% Off',
            'icon' => '⚡',
            'submitter_name' => 'Sarah Connor',
            'submitter_email' => 'sarah@hostinger.com',
            'submitter_phone' => '+91 99999 88888',
            'notes' => 'Gold sponsor table 2',
            'submission_token' => $token,
        ]);

        $response->assertRedirect(route('sponsor-deals.success'));

        $this->assertDatabaseHas('sponsor_deal_submissions', [
            'event_id' => $event->id,
            'sponsor_name' => 'Hostinger',
            'title' => '30% off all hosting plans',
            'status' => 'pending',
            'submitter_email' => 'sarah@hostinger.com',
        ]);

        Mail::assertSent(SponsorDealSubmittedMail::class, function ($mail) {
            return $mail->hasTo('hello@wpsimplified.in') &&
                   $mail->submission->sponsor_name === 'Hostinger';
        });
    }

    public function test_honeypot_silently_discards_bot_submissions(): void
    {
        Mail::fake();

        $event = $this->createEvent();
        $token = Crypt::encryptString(json_encode([
            'time' => now()->subSeconds(10)->timestamp,
            'ip' => '127.0.0.1',
        ]));

        $response = $this->post(route('sponsor-deals.store'), [
            'event_id' => $event->id,
            'sponsor_name' => 'Bot Spammer',
            'title' => 'Cheap Ray-Ban Sunglasses',
            'description' => 'Visit our spam website now for sunglasses discount.',
            'url' => 'https://spamsite.xyz',
            'submitter_name' => 'Spambot',
            'submitter_email' => 'bot@spamsite.xyz',
            'hp_organization_token' => 'I am a spam bot', // honeypot filled
            'submission_token' => $token,
        ]);

        $response->assertRedirect(route('sponsor-deals.success'));

        // Nothing in database
        $this->assertDatabaseMissing('sponsor_deal_submissions', [
            'sponsor_name' => 'Bot Spammer',
        ]);

        Mail::assertNothingSent();
    }

    public function test_submission_faster_than_3_seconds_is_rejected(): void
    {
        $event = $this->createEvent();
        // Just generated token (0 seconds elapsed)
        $token = Crypt::encryptString(json_encode([
            'time' => now()->timestamp,
            'ip' => '127.0.0.1',
        ]));

        $response = $this->from(route('sponsor-deals.create'))->post(route('sponsor-deals.store'), [
            'event_id' => $event->id,
            'sponsor_name' => 'Super Fast Bot',
            'title' => 'Speedy Offer',
            'description' => 'Submitted in 100 milliseconds.',
            'url' => 'https://example.com',
            'submitter_name' => 'Fast Person',
            'submitter_email' => 'fast@example.com',
            'submission_token' => $token,
        ]);

        $response->assertRedirect(route('sponsor-deals.create'));
        $response->assertSessionHasErrors('deal_form');
        $this->assertDatabaseCount('sponsor_deal_submissions', 0);
    }

    public function test_admin_can_view_pending_submissions(): void
    {
        $admin = $this->adminUser();
        $event = $this->createEvent();

        $submission = SponsorDealSubmission::create([
            'event_id' => $event->id,
            'sponsor_name' => 'Yoast',
            'brand' => 'Yoast SEO',
            'title' => '20% off Yoast Premium',
            'description' => 'Get the best SEO plugin for WordPress.',
            'url' => 'https://yoast.com/deal',
            'submitter_name' => 'Joost',
            'submitter_email' => 'joost@yoast.com',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.deal-submissions.index'));

        $response->assertOk();
        $response->assertSee('20% off Yoast Premium');
        $response->assertSee('Yoast SEO');
        $response->assertSee('WordCamp Delhi 2026');
    }

    public function test_admin_can_approve_deal_into_draft_status(): void
    {
        $admin = $this->adminUser();
        $event = $this->createEvent();

        $submission = SponsorDealSubmission::create([
            'event_id' => $event->id,
            'sponsor_name' => 'Yoast',
            'brand' => 'Yoast SEO',
            'website' => 'yoast.com',
            'title' => '20% off Yoast Premium',
            'highlight' => '20% OFF',
            'description' => 'Get the best SEO plugin for WordPress.',
            'terms' => 'Valid for WordCamp attendees.',
            'coupon_code' => 'YOAST20',
            'url' => 'https://yoast.com/deal',
            'cta_label' => 'Get Yoast',
            'icon' => '🚀',
            'submitter_name' => 'Joost',
            'submitter_email' => 'joost@yoast.com',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.deal-submissions.approve', $submission), [
            'publish' => 0, // draft
        ]);

        $response->assertRedirect(route('admin.deal-submissions.index'));

        $submission->refresh();
        $this->assertEquals('approved', $submission->status);
        $this->assertNotNull($submission->approved_offer_id);

        $offer = Offer::find($submission->approved_offer_id);
        $this->assertNotNull($offer);
        $this->assertEquals($event->id, $offer->event_id);
        $this->assertEquals('Yoast SEO', $offer->brand);
        $this->assertEquals('20% off Yoast Premium', $offer->title);
        $this->assertEquals('20% OFF', $offer->highlight);
        $this->assertEquals('YOAST20', $offer->coupon_code);
        $this->assertEquals('https://yoast.com/deal', $offer->url);
        $this->assertFalse($offer->is_active); // Saved as Draft!
    }

    public function test_admin_can_approve_deal_into_active_status(): void
    {
        $admin = $this->adminUser();
        $event = $this->createEvent();

        $submission = SponsorDealSubmission::create([
            'event_id' => $event->id,
            'sponsor_name' => 'Weglot',
            'brand' => 'Weglot Translation',
            'title' => 'Translate your site free',
            'description' => 'Turn your WordPress website multilingual in minutes.',
            'url' => 'https://weglot.com',
            'submitter_name' => 'Remy',
            'submitter_email' => 'remy@weglot.com',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.deal-submissions.approve', $submission), [
            'publish' => 1, // active immediately
        ]);

        $response->assertRedirect(route('admin.deal-submissions.index'));

        $submission->refresh();
        $offer = Offer::find($submission->approved_offer_id);
        $this->assertTrue($offer->is_active);
    }

    public function test_admin_can_reject_and_delete_deal_submission(): void
    {
        $admin = $this->adminUser();
        $event = $this->createEvent();

        $submission = SponsorDealSubmission::create([
            'event_id' => $event->id,
            'sponsor_name' => 'Spam Company',
            'title' => 'Not Authentic',
            'description' => 'Spam offer description.',
            'url' => 'https://example.com/bad',
            'submitter_name' => 'Fake Person',
            'submitter_email' => 'fake@bad.com',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.deal-submissions.destroy', $submission));

        $response->assertRedirect(route('admin.deal-submissions.index'));
        $this->assertDatabaseMissing('sponsor_deal_submissions', ['id' => $submission->id]);
    }

    public function test_domain_matching_security_heuristic(): void
    {
        $event = $this->createEvent();

        $matching = new SponsorDealSubmission([
            'website' => 'wpengine.com',
            'url' => 'https://wpengine.com/deal',
            'submitter_email' => 'alex@wpengine.com',
        ]);
        $this->assertTrue($matching->emailDomainMatches());

        $subdomain = new SponsorDealSubmission([
            'website' => 'cloud.hostinger.com',
            'url' => 'https://hostinger.com/signup',
            'submitter_email' => 'rep@hostinger.com',
        ]);
        $this->assertTrue($subdomain->emailDomainMatches());

        $genericGmail = new SponsorDealSubmission([
            'website' => 'wpengine.com',
            'url' => 'https://wpengine.com/deal',
            'submitter_email' => 'someone@gmail.com',
        ]);
        $this->assertFalse($genericGmail->emailDomainMatches());

        $differentDomain = new SponsorDealSubmission([
            'website' => 'bluehost.com',
            'url' => 'https://bluehost.com',
            'submitter_email' => 'attacker@otherbrand.com',
        ]);
        $this->assertFalse($differentDomain->emailDomainMatches());
    }
}
