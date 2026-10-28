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

    public function test_upload_valid_png_logo_under_100kb_succeeds(): void
    {
        Storage::fake('public');
        Mail::fake();

        $event = $this->createEvent();
        $token = Crypt::encryptString(json_encode([
            'time' => now()->subSeconds(10)->timestamp,
            'ip' => '127.0.0.1',
        ]));

        // Generate a 40 KB PNG image
        $pngFile = UploadedFile::fake()->image('sponsor-logo.png', 200, 200)->size(40);

        $response = $this->post(route('sponsor-deals.store'), [
            'event_id' => $event->id,
            'sponsor_name' => 'Kinsta',
            'title' => '2 months free WordPress hosting',
            'description' => 'Fast managed WordPress hosting with premium Google C2 machines.',
            'url' => 'https://kinsta.com',
            'submitter_name' => 'Mark',
            'submitter_email' => 'mark@kinsta.com',
            'submission_token' => $token,
            'logo_image' => $pngFile,
        ]);

        $response->assertRedirect(route('sponsor-deals.success'));

        $submission = SponsorDealSubmission::where('sponsor_name', 'Kinsta')->first();
        $this->assertNotNull($submission);
        $this->assertNotNull($submission->logo_path);
        $this->assertStringEndsWith('.png', $submission->logo_path);
        Storage::disk('public')->assertExists($submission->logo_path);
    }

    public function test_reject_non_png_logo_upload(): void
    {
        Storage::fake('public');

        $event = $this->createEvent();
        $token = Crypt::encryptString(json_encode([
            'time' => now()->subSeconds(10)->timestamp,
            'ip' => '127.0.0.1',
        ]));

        // JPG file
        $jpgFile = UploadedFile::fake()->image('logo.jpg', 200, 200);

        $response = $this->from(route('sponsor-deals.create'))->post(route('sponsor-deals.store'), [
            'event_id' => $event->id,
            'sponsor_name' => 'Invalid Logo Co',
            'title' => 'Valid Title',
            'description' => 'Valid description details here.',
            'url' => 'https://example.com',
            'submitter_name' => 'Test',
            'submitter_email' => 'test@example.com',
            'submission_token' => $token,
            'logo_image' => $jpgFile,
        ]);

        $response->assertRedirect(route('sponsor-deals.create'));
        $response->assertSessionHasErrors('logo_image');
        $this->assertDatabaseMissing('sponsor_deal_submissions', ['sponsor_name' => 'Invalid Logo Co']);
    }

    public function test_reject_logo_image_larger_than_100kb(): void
    {
        Storage::fake('public');

        $event = $this->createEvent();
        $token = Crypt::encryptString(json_encode([
            'time' => now()->subSeconds(10)->timestamp,
            'ip' => '127.0.0.1',
        ]));

        // 150 KB PNG (exceeds 100 KB limit)
        $largePng = UploadedFile::fake()->image('huge-logo.png', 400, 400)->size(150);

        $response = $this->from(route('sponsor-deals.create'))->post(route('sponsor-deals.store'), [
            'event_id' => $event->id,
            'sponsor_name' => 'Heavy Logo Co',
            'title' => 'Valid Title',
            'description' => 'Valid description details here.',
            'url' => 'https://example.com',
            'submitter_name' => 'Test',
            'submitter_email' => 'test@example.com',
            'submission_token' => $token,
            'logo_image' => $largePng,
        ]);

        $response->assertRedirect(route('sponsor-deals.create'));
        $response->assertSessionHasErrors('logo_image');
        $this->assertDatabaseMissing('sponsor_deal_submissions', ['sponsor_name' => 'Heavy Logo Co']);
    }

    public function test_approving_submission_with_uploaded_logo_creates_media_asset(): void
    {
        Storage::fake('public');
        $admin = $this->adminUser();
        $event = $this->createEvent();

        // Create a stored file
        $file = UploadedFile::fake()->image('uploaded-logo.png', 100, 100)->size(30);
        $storedPath = $file->store('sponsor-deals/logos', 'public');

        $submission = SponsorDealSubmission::create([
            'event_id' => $event->id,
            'sponsor_name' => 'Elementor',
            'title' => '50% off Elementor Pro',
            'description' => 'Build websites faster with WordPress Elementor.',
            'url' => 'https://elementor.com',
            'submitter_name' => 'Yoni',
            'submitter_email' => 'yoni@elementor.com',
            'logo_path' => $storedPath,
            'logo_url' => Storage::disk('public')->url($storedPath),
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.deal-submissions.approve', $submission), [
            'publish' => 1,
        ]);

        $response->assertRedirect(route('admin.deal-submissions.index'));

        $submission->refresh();
        $offer = Offer::find($submission->approved_offer_id);
        $this->assertNotNull($offer);
        $this->assertNotNull($offer->media_asset_id);

        $mediaAsset = MediaAsset::find($offer->media_asset_id);
        $this->assertNotNull($mediaAsset);
        $this->assertEquals($storedPath, $mediaAsset->path);
        $this->assertEquals('image/png', $mediaAsset->mime_type);
    }

    public function test_rejecting_submission_purges_uploaded_logo_from_storage(): void
    {
        Storage::fake('public');
        $admin = $this->adminUser();
        $event = $this->createEvent();

        $file = UploadedFile::fake()->image('spam-logo.png', 100, 100)->size(20);
        $storedPath = $file->store('sponsor-deals/logos', 'public');
        Storage::disk('public')->assertExists($storedPath);

        $submission = SponsorDealSubmission::create([
            'event_id' => $event->id,
            'sponsor_name' => 'Malicious Corp',
            'title' => 'Bad deal',
            'description' => 'Bad deal details.',
            'url' => 'https://badsite.com',
            'submitter_name' => 'Malicious',
            'submitter_email' => 'bad@badsite.com',
            'logo_path' => $storedPath,
            'logo_url' => Storage::disk('public')->url($storedPath),
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.deal-submissions.destroy', $submission));

        $response->assertRedirect(route('admin.deal-submissions.index'));
        $this->assertDatabaseMissing('sponsor_deal_submissions', ['id' => $submission->id]);
        Storage::disk('public')->assertMissing($storedPath);
    }

    public function test_xss_payloads_in_all_fields_are_safely_escaped(): void
    {
        $admin = $this->adminUser();
        $event = $this->createEvent();

        $xssTitle = '<script>alert("xss-title")</script>';
        $xssBrand = '<img src=x onerror="alert(\'xss-brand\')">';
        $xssDesc = '<svg onload="alert(\'xss-desc\')">';
        $xssTerms = '<b onmouseover="alert(\'xss-terms\')">Hover</b>';
        $xssSubmitter = '<span onclick="alert(\'xss-name\')">Hacker</span>';
        $xssNotes = '<iframe src="javascript:alert(1)"></iframe>';

        $submission = SponsorDealSubmission::create([
            'event_id' => $event->id,
            'sponsor_name' => 'Attack Co',
            'brand' => $xssBrand,
            'title' => $xssTitle,
            'description' => $xssDesc,
            'terms' => $xssTerms,
            'url' => 'https://safe-domain.com/perk',
            'submitter_name' => $xssSubmitter,
            'submitter_email' => 'attacker@attackco.com',
            'notes' => $xssNotes,
            'status' => 'pending',
        ]);

        // 1. Index page escape test
        $indexResponse = $this->actingAs($admin)->get(route('admin.deal-submissions.index'));
        $indexResponse->assertOk();
        // Raw unescaped executable script tags must NOT be present
        $indexResponse->assertDontSee($xssTitle, false);
        $indexResponse->assertDontSee($xssBrand, false);
        // HTML entities must be escaped
        $indexResponse->assertSee(e($xssTitle), false);
        $indexResponse->assertSee(e($xssBrand), false);

        // 2. Show review page escape test
        $showResponse = $this->actingAs($admin)->get(route('admin.deal-submissions.show', $submission));
        $showResponse->assertOk();
        $showResponse->assertDontSee($xssTitle, false);
        $showResponse->assertDontSee($xssDesc, false);
        $showResponse->assertDontSee($xssNotes, false);
        $showResponse->assertSee(e($xssTitle), false);
        $showResponse->assertSee(e($xssDesc), false);
        $showResponse->assertSee(e($xssNotes), false);
    }

    public function test_javascript_url_scheme_is_strictly_rejected(): void
    {
        $event = $this->createEvent();
        $token = Crypt::encryptString(json_encode([
            'time' => now()->subSeconds(10)->timestamp,
            'ip' => '127.0.0.1',
        ]));

        $response = $this->from(route('sponsor-deals.create'))->post(route('sponsor-deals.store'), [
            'event_id' => $event->id,
            'sponsor_name' => 'XSS Vector Corp',
            'title' => 'Malicious URL Deal',
            'description' => 'Trying to inject javascript: pseudo-protocol.',
            'url' => 'javascript:alert(document.cookie)',
            'submitter_name' => 'Attacker',
            'submitter_email' => 'attacker@example.com',
            'submission_token' => $token,
        ]);

        $response->assertRedirect(route('sponsor-deals.create'));
        $response->assertSessionHasErrors('url');
        $this->assertDatabaseMissing('sponsor_deal_submissions', ['sponsor_name' => 'XSS Vector Corp']);
    }

    public function test_performance_queries_are_bounded_on_admin_index(): void
    {
        $admin = $this->adminUser();
        $event = $this->createEvent();

        // Seed 15 submissions
        for ($i = 1; $i <= 15; $i++) {
            SponsorDealSubmission::create([
                'event_id' => $event->id,
                'sponsor_name' => "Sponsor {$i}",
                'title' => "Offer {$i}",
                'description' => "Description {$i}",
                'url' => "https://sponsor{$i}.com",
                'submitter_name' => "Person {$i}",
                'submitter_email' => "person{$i}@sponsor{$i}.com",
                'status' => 'pending',
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $start = microtime(true);
        $response = $this->actingAs($admin)->get(route('admin.deal-submissions.index'));
        $duration = microtime(true) - $start;

        $response->assertOk();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Page should render in well under 500ms
        $this->assertLessThan(1.0, $duration, "Admin index render took too long: {$duration}s");

        // Queries should be bounded (no N+1 for events or approved offers)
        $this->assertLessThan(15, count($queries), "Too many queries executed: ".count($queries));
    }
}
