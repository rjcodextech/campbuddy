<?php

namespace Tests\Feature;

use App\Models\AttendeeRoster;
use App\Models\Event;
use App\Models\Offer;
use App\Models\OfferLead;
use App\Models\Quest;
use App\Models\User;
use App\Services\AttendeeRosterScraper;
use Database\Seeders\DefaultQuestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Security and reliability fixes from the full review: what an anonymous
 * visitor can make the server do, what an admin export can do to an admin,
 * and a few things that quietly didn't work.
 */
class HardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function event(array $overrides = []): Event
    {
        return Event::create($overrides + [
            'slug' => 'wc-test',
            'display_name' => 'WordCamp Test',
            'source_site_url' => 'https://test.wordcamp.org/2026',
            'status' => 'active',
            'is_visible' => true,
        ]);
    }

    private function discoveryBody(): array
    {
        return ['tags' => ['developer'], 'profession' => 'Dev', 'who_to_meet' => 'Everyone'];
    }

    // ---- Rate limits -------------------------------------------------------

    // Rate limits: tests/Feature/RateLimitTest.php

    // ---- Push subscriptions (the server later POSTs to the endpoint) -------

    private function pushBody(string $endpoint): array
    {
        return ['device_id' => 'device-1', 'endpoint' => $endpoint, 'keys' => ['p256dh' => 'abc', 'auth' => 'def']];
    }

    public function test_a_push_endpoint_must_be_https(): void
    {
        $event = $this->event();

        $this->postJson("/api/v1/events/{$event->slug}/push/subscribe", $this->pushBody('http://93.184.216.34/push/abc'))
            ->assertStatus(422)->assertJsonValidationErrors('endpoint');
    }

    public function test_a_push_endpoint_cannot_point_at_a_private_or_internal_address(): void
    {
        $event = $this->event();

        foreach (['https://127.0.0.1/x', 'https://169.254.169.254/latest/meta-data', 'https://10.0.0.5/x', 'https://192.168.1.10/x', 'https://localhost/x'] as $endpoint) {
            $this->postJson("/api/v1/events/{$event->slug}/push/subscribe", $this->pushBody($endpoint))
                ->assertStatus(422, "accepted {$endpoint}")->assertJsonValidationErrors('endpoint');
        }

        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_a_real_https_push_endpoint_is_accepted(): void
    {
        $event = $this->event();

        // An IP literal, so the test needs no DNS.
        $this->postJson("/api/v1/events/{$event->slug}/push/subscribe", $this->pushBody('https://93.184.216.34/push/abc'))
            ->assertCreated();

        $this->assertDatabaseCount('push_subscriptions', 1);
    }

    // ---- Lead export: spreadsheet formula injection -------------------------

    public function test_the_lead_export_neutralises_spreadsheet_formulas(): void
    {
        $event = $this->event();
        $offer = Offer::create([
            'event_id' => $event->id, 'title' => '=1+1', 'description' => 'd', 'url' => 'https://deal.example.com',
            'sort_order' => 0, 'is_active' => true, 'capture_leads' => true,
        ]);

        OfferLead::create(['event_id' => $event->id, 'offer_id' => $offer->id, 'name' => '=HYPERLINK("http://evil.example","click")', 'email' => '+cmd@example.com', 'mobile' => '@SUM(1+1)']);
        OfferLead::create(['event_id' => $event->id, 'offer_id' => $offer->id, 'name' => 'Priya Sharma', 'email' => 'priya@example.com', 'mobile' => '+91 98765 43210']);

        $csv = $this->actingAs(User::factory()->create())
            ->get(route('admin.events.deal-leads.export', $event))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString("'+cmd@example.com", $csv);
        $this->assertStringContainsString("'@SUM(1+1)", $csv);
        $this->assertStringContainsString("'=1+1", $csv, 'the deal title is escaped too');
        $this->assertStringNotContainsString(',=HYPERLINK', $csv);
        // Ordinary values — including a phone number that starts with "+" — are untouched.
        $this->assertStringContainsString('Priya Sharma', $csv);
        $this->assertStringContainsString(',"+91 98765 43210",', $csv);
    }

    // ---- Only web addresses ------------------------------------------------

    public function test_a_deal_link_must_be_http_or_https(): void
    {
        $event = $this->event();

        foreach (['javascript:alert(1)', 'data:text/html,<script>alert(1)</script>', 'ftp://files.example.com/x', 'file:///etc/passwd'] as $url) {
            $this->actingAs(User::factory()->create())
                ->post(route('admin.events.offers.store', $event), [
                    'title' => 'Deal', 'description' => 'Something', 'url' => $url, 'sort_order' => 0,
                ])
                ->assertSessionHasErrors('url');
        }

        $this->assertDatabaseCount('offers', 0);
    }

    // ---- Response headers --------------------------------------------------

    public function test_pages_and_the_api_carry_basic_security_headers(): void
    {
        $this->event();

        foreach (['/', '/event/wc-test', '/api/v1/health', '/admin/login'] as $path) {
            $this->get($path)
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        }

        $this->get('/')->assertHeader('Permissions-Policy');
    }

    // ---- Contribute → Quest tie-in (CD4) -----------------------------------

    public function test_the_contribute_page_hands_the_seeded_contribution_quest_to_the_browser(): void
    {
        $event = $this->event();
        $this->seed(DefaultQuestSeeder::class);

        $questId = Quest::where('title', Quest::CONTRIBUTION_CURIOUS)->value('id');
        $this->assertNotNull($questId);

        $this->get(route('event.contribute', $event))
            ->assertOk()
            ->assertSee('"contributorDayQuestId":'.$questId, false);
    }

    // ---- Third-party links -------------------------------------------------

    public function test_the_roster_api_only_hands_out_web_links(): void
    {
        $event = $this->event();
        AttendeeRoster::create([
            'event_id' => $event->id, 'name' => 'Priya Sharma', 'content_hash' => str_repeat('a', 64),
            'gravatar_url' => 'javascript:alert(1)',
            'links' => [
                ['type' => 'website', 'url' => 'javascript:alert(document.cookie)'],
                ['type' => 'website', 'url' => 'data:text/html,<script>alert(1)</script>'],
                ['type' => 'linkedin', 'url' => 'https://linkedin.com/in/priya'],
            ],
        ]);

        $entry = $this->getJson("/api/v1/events/{$event->slug}/roster")->assertOk()->json('data.0');

        $this->assertNull($entry['gravatar_url']);
        $this->assertSame([['type' => 'linkedin', 'url' => 'https://linkedin.com/in/priya']], $entry['links']);
    }

    public function test_the_roster_scraper_drops_links_that_are_not_web_addresses(): void
    {
        $html = '<ul class="tix-attendee-list"><li><span class="tix-attendee-name"><span class="tix-first">Priya</span> <span class="tix-last">Sharma</span></span>'
            .'<img class="avatar" src="javascript:alert(1)">'
            .'<a class="tix-field website" href="javascript:alert(1)">site</a>'
            .'<a class="tix-field linkedin" href="https://linkedin.com/in/priya">in</a></li></ul>';

        $entries = (new AttendeeRosterScraper)->parse($html);

        $this->assertNotNull($entries, 'the fixture should look like an Attendees page');
        $this->assertNull($entries[0]['gravatar_url']);
        $this->assertSame([['type' => 'linkedin', 'url' => 'https://linkedin.com/in/priya']], $entries[0]['links']);
    }

    public function test_the_media_library_refuses_an_svg_with_a_script(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();

        $evil = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->actingAs($admin)->post(route('admin.media.store'), ['file' => $evil])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('media_assets', 0);

        $fine = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"><rect width="1" height="1"/></svg>');
        $this->actingAs($admin)->post(route('admin.media.store'), ['file' => $fine])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('media_assets', 1);
    }

    // ---- My Day payload ----------------------------------------------------

    public function test_my_day_sends_plain_text_bios_not_the_raw_wordpress_markup(): void
    {
        $event = $this->event();
        Cache::put("event:{$event->id}:sessions", []);
        Cache::put("event:{$event->id}:speakers", [
            [
                'id' => 1, 'name' => 'Ada', 'avatar_url' => null, 'link' => null, 'social_links' => [],
                'bio_html' => "\n<div class=\"wp-block-group\"><style>.x{color:red}</style><h2>Ada</h2><p>Ada &amp; <strong>friends</strong> build things.</p>\n\n<script>alert(1)</script><p>Second paragraph</p><a>WordPress</a>\n<a>LinkedIn</a>\n<a>X</a></div>",
            ],
            ['id' => 2, 'name' => 'No Bio', 'avatar_url' => null, 'link' => null, 'social_links' => [], 'bio_html' => ''],
        ]);

        $html = $this->get(route('event.my-day', $event))->assertOk()->getContent();

        $this->assertSame(1, preg_match('#<script type="application/json" id="my-day-data">(.*?)</script>#s', $html, $match));
        $speakers = json_decode($match[1], true)['speakers'];

        // Tags, <style>/<script> contents, the name heading and the social-button labels are gone.
        $this->assertSame("Ada & friends build things.\nSecond paragraph", $speakers[0]['bio_text']);
        $this->assertNull($speakers[1]['bio_text']);
        $this->assertArrayNotHasKey('bio_html', $speakers[0]);
        $this->assertStringNotContainsString('wp-block', $html);
    }

    // ---- Anonymous pages don't start sessions ------------------------------

    public function test_attendee_pages_do_not_set_cookies_or_write_a_session(): void
    {
        $event = $this->event();

        foreach (['/', '/event/wc-test', '/event/wc-test/my-day', '/event/wc-test/explore', '/event/wc-test/camp-card', '/event/wc-test/manifest.json'] as $path) {
            $response = $this->get($path)->assertOk();

            $this->assertEmpty($response->headers->getCookies(), "{$path} set a cookie");
        }
    }

    public function test_the_roster_takedown_still_works_with_its_csrf_token(): void
    {
        $event = $this->event();
        $entry = AttendeeRoster::create([
            'event_id' => $event->id, 'name' => 'Priya Sharma', 'gravatar_url' => null, 'links' => [], 'content_hash' => str_repeat('a', 64), 'is_suppressed' => false,
        ]);

        // The form is the one attendee page that needs a session (CSRF + flash).
        $this->get(route('event.roster-removal.search', [$event, 'name' => 'Priya Sharma']))
            ->assertOk()
            ->assertSee('name="_token"', false);

        // (CSRF checking itself is switched off while tests run; what matters
        // here is that the flow still has a session to carry its message.)
        $this->post(route('event.roster-removal.remove', [$event, $entry]))
            ->assertRedirect(route('event.roster-removal.show', $event))
            ->assertSessionHas('status');

        $this->assertTrue($entry->fresh()->is_suppressed);
    }
}
