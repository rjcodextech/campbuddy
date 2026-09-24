<?php

namespace Tests\Feature;

use App\Jobs\FetchSpeakersSponsorsSessionsJob;
use App\Models\Event;
use App\Models\FetchLog;
use App\Models\User;
use App\Services\DataRefresher;
use App\Support\CacheVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The admin's two separate buttons. "Clear cache" clears server caches,
 * optionally purges Cloudflare, and bumps the version that tells installed
 * apps to drop saved copies — fetching nothing. "Refresh event data" fetches
 * every live event's data now — clearing nothing, and never blanking a live
 * event.
 */
class CachePurgeTest extends TestCase
{
    use RefreshDatabase;

    private const SITE = 'https://test.wordcamp.org/2026';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('local');
        Storage::fake('public');
        Http::preventStrayRequests();

        // Nothing here should try to create a real symlink in the repo's public/ folder.
        config(['filesystems.links' => [Storage::disk('public')->path('.') => Storage::disk('public')->path('.')]]);
    }

    private function event(array $overrides = []): Event
    {
        return Event::create($overrides + [
            'slug' => 'wc-test',
            'display_name' => 'WordCamp Test 2026',
            'source_site_url' => self::SITE,
            'status' => 'active',
            'is_visible' => true,
        ]);
    }

    /** A WordCamp site whose REST API returns one session / speaker / sponsor. */
    private function fakeWordCampSite(): void
    {
        $json = fn (array $items) => Http::response($items, 200, ['X-WP-TotalPages' => '1']);

        Http::fake([
            self::SITE.'/wp-json/wp/v2/sessions*' => $json([[
                'id' => 7, 'title' => ['rendered' => 'Fresh keynote'], 'link' => 'https://test.wordcamp.org/2026/keynote',
                'meta' => ['_wcpt_session_time' => 1790000000, '_wcpt_session_duration' => 3600],
            ]]),
            self::SITE.'/wp-json/wp/v2/speakers*' => $json([]),
            self::SITE.'/wp-json/wp/v2/sponsors*' => $json([]),
            self::SITE.'/wp-json/wp/v2/organizers*' => $json([]),
            self::SITE.'/wp-json/wp/v2/session_track*' => $json([]),
            self::SITE.'/wp-json/wp/v2/sponsor_level*' => $json([]),
            // Event information: central.wordcamp.org knows this event.
            'central.wordcamp.org/*' => Http::response([[
                'URL' => self::SITE.'/', 'Venue Name' => 'Grand Hall', 'Physical Address' => '1 Main St', 'Location' => 'Testville',
            ]]),
            '*' => Http::response('', 404),
        ]);
    }

    private function purge(): TestResponse
    {
        $admin = User::firstWhere('email', 'admin@campbuddy.test') ?? User::factory()->create(['email' => 'admin@campbuddy.test']);

        return $this->actingAs($admin)->post(route('admin.cache.purge'));
    }

    private function refresh(): TestResponse
    {
        $admin = User::firstWhere('email', 'admin@campbuddy.test') ?? User::factory()->create(['email' => 'admin@campbuddy.test']);

        return $this->actingAs($admin)->post(route('admin.data.refresh'));
    }

    // ---- Access -------------------------------------------------------------

    public function test_guests_cannot_purge_or_refresh(): void
    {
        $this->post(route('admin.cache.purge'))->assertRedirect(route('login'));
        $this->post(route('admin.data.refresh'))->assertRedirect(route('login'));

        $this->assertSame('0', CacheVersion::current());
    }

    public function test_the_dashboard_offers_both_buttons_and_says_when_they_were_last_used(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Clear cache')
            ->assertSee(route('admin.cache.purge'), false)
            ->assertSee('Refresh event data now')
            ->assertSee(route('admin.data.refresh'), false)
            ->assertSee('Not cleared yet.')
            ->assertSee('Not fetched yet.');

        CacheVersion::bump('someone@example.com', 'Server caches cleared.');

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertSee('Last cleared')
            ->assertSee('by someone@example.com')
            ->assertSee('Server caches cleared.');
    }

    // ---- What a purge does -----------------------------------------------------

    public function test_a_purge_clears_caches_and_bumps_the_version(): void
    {
        $this->fakeWordCampSite();
        Cache::put('some:stale:thing', 'old', 3600);
        $before = CacheVersion::current();

        $this->purge()
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('status', fn ($status) => str_contains($status, 'Server caches cleared'));

        $this->assertNull(Cache::get('some:stale:thing'));
        $this->assertGreaterThan((int) $before, (int) CacheVersion::current());

        $last = CacheVersion::last();
        $this->assertSame('admin@campbuddy.test', $last['by']);
        $this->assertNotEmpty($last['summary']);
    }

    public function test_each_purge_produces_a_strictly_newer_version(): void
    {
        $this->fakeWordCampSite();

        $this->purge();
        $first = (int) CacheVersion::current();

        $this->travel(1)->minutes(); // past the cooldown
        $this->purge();

        $this->assertGreaterThan($first, (int) CacheVersion::current());
    }

    public function test_clearing_the_cache_fetches_nothing(): void
    {
        $this->event();
        Http::fake(['*' => Http::response('', 404)]);

        $this->purge()->assertSessionHas('status', fn ($s) => str_contains($s, 'Server caches cleared'));

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'wordcamp.org'));
    }

    public function test_refreshing_data_clears_no_cache_and_leaves_the_version_alone(): void
    {
        $this->event();
        $this->fakeWordCampSite();
        Cache::put('some:other:thing', 'kept', 3600);

        $this->refresh()->assertSessionHas('status', fn ($s) => str_contains($s, 'Fresh data fetched for 1 event'));

        $this->assertSame('kept', Cache::get('some:other:thing'));
        $this->assertSame('0', CacheVersion::current());
    }

    public function test_a_refresh_merges_fresh_data_into_what_is_stored(): void
    {
        $event = $this->event();
        Cache::put("event:{$event->id}:sessions", [['id' => 1, 'title' => 'Dropped session'], ['id' => 7, 'title' => 'Old title']], 3600);
        $this->fakeWordCampSite();

        $this->refresh()->assertSessionHas('status', fn ($s) => str_contains($s, 'Fresh data fetched for 1 event'));

        $sessions = Cache::get("event:{$event->id}:sessions");
        $this->assertCount(1, $sessions);
        $this->assertSame('Fresh keynote', $sessions[0]['title']);
    }

    public function test_a_second_refresh_straight_after_the_first_is_refused(): void
    {
        $this->fakeWordCampSite();

        $this->refresh()->assertSessionHas('status');
        $this->refresh()->assertSessionHasErrors('refresh');

        $this->travel(31)->seconds();
        $this->refresh()->assertSessionHas('status');
    }

    public function test_a_live_event_is_never_blanked_when_its_site_is_down(): void
    {
        $event = $this->event();
        Cache::put("event:{$event->id}:sessions", [['id' => 1, 'title' => 'Last good session']], 3600);
        Cache::put("event:{$event->id}:sponsors", [['id' => 2, 'name' => 'Last good sponsor']], 3600);
        Http::fake(['*' => fn () => throw new ConnectionException('site down')]);

        $this->purge();
        $this->refresh()->assertSessionHas('status', fn ($s) => str_contains($s, 'couldn\'t fully refresh WordCamp Test 2026'));

        // Neither the flush nor the failed fetch took the last good data with it.
        $this->assertSame('Last good session', Cache::get("event:{$event->id}:sessions")[0]['title']);
        $this->assertSame('Last good sponsor', Cache::get("event:{$event->id}:sponsors")[0]['name']);
    }

    public function test_draft_and_archived_events_are_left_alone(): void
    {
        $this->event(['slug' => 'draft', 'status' => 'draft']);
        $this->event(['slug' => 'old', 'status' => 'archived']);
        Http::fake(); // any request at all would be a failure below

        $this->refresh()->assertSessionHas('status', fn ($s) => str_contains($s, 'No live events to refresh'));

        Http::assertNothingSent();
    }

    public function test_the_attendee_roster_is_not_scraped_by_a_refresh(): void
    {
        $this->event();
        $this->fakeWordCampSite();

        $this->refresh();

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/attendees'));
    }

    public function test_events_beyond_the_time_budget_are_queued_instead_of_holding_up_the_request(): void
    {
        $this->event(['slug' => 'a', 'display_name' => 'A']);
        $this->event(['slug' => 'b', 'display_name' => 'B']);
        Queue::fake();

        // A budget of zero: everything is over it before it starts.
        $refresher = new DataRefresher;
        $method = (new \ReflectionClass($refresher))->getMethod('refreshEvents');
        $result = $method->invoke($refresher, microtime(true) - 3600, now());

        $this->assertSame(['A', 'B'], $result['queued']);
        $this->assertSame([], $result['refreshed']);
        Queue::assertPushed(FetchSpeakersSponsorsSessionsJob::class, 2);
    }

    // ---- Cloudflare -----------------------------------------------------------------

    public function test_cloudflare_is_purged_when_credentials_are_configured(): void
    {
        config(['services.cloudflare.zone_id' => 'zone123', 'services.cloudflare.api_token' => 'secret-token']);
        Http::fake([
            'api.cloudflare.com/*' => Http::response(['success' => true, 'errors' => []]),
            '*' => Http::response('', 404),
        ]);

        $this->purge()->assertSessionHas('status', fn ($s) => str_contains($s, 'Cloudflare purged'));

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.cloudflare.com/client/v4/zones/zone123/purge_cache'
            && $request->method() === 'POST'
            && $request['purge_everything'] === true
            && $request->hasHeader('Authorization', 'Bearer secret-token'));
    }

    public function test_cloudflare_is_left_alone_when_not_configured(): void
    {
        config(['services.cloudflare.zone_id' => null, 'services.cloudflare.api_token' => null]);

        $this->purge()->assertSessionHas('status', fn ($s) => str_contains($s, 'Cloudflare not configured'));

        Http::assertNothingSent();
    }

    public function test_a_cloudflare_failure_is_reported_but_does_not_undo_the_purge(): void
    {
        config(['services.cloudflare.zone_id' => 'zone123', 'services.cloudflare.api_token' => 'bad-token']);
        Http::fake([
            'api.cloudflare.com/*' => Http::response(['success' => false, 'errors' => [['message' => 'Invalid API token']]], 403),
        ]);
        $before = (int) CacheVersion::current();

        $this->purge()->assertSessionHas('status', fn ($s) => str_contains($s, 'Cloudflare failed: Invalid API token'));

        $this->assertGreaterThan($before, (int) CacheVersion::current());
    }

    // ---- How phones find out ---------------------------------------------------------------

    public function test_the_version_endpoint_is_public_current_and_never_cached(): void
    {
        $this->getJson('/api/v1/cache-version')
            ->assertOk()
            ->assertExactJson(['version' => '0'])
            ->assertHeader('Cache-Control');

        $this->assertStringContainsString('no-store', $this->get('/api/v1/cache-version')->headers->get('Cache-Control'));

        $version = CacheVersion::bump();

        $this->getJson('/api/v1/cache-version')->assertExactJson(['version' => $version]);
    }

    public function test_attendee_pages_carry_the_current_version_for_the_app_to_compare(): void
    {
        $event = $this->event();
        $version = CacheVersion::bump();

        $meta = '<meta name="campbuddy-cache-version" content="'.$version.'">';

        $this->get('/')->assertSee($meta, false);
        $this->get(route('event.home', $event))->assertSee($meta, false);
    }

    public function test_an_unreadable_version_file_never_breaks_a_page(): void
    {
        Storage::disk('local')->put('cache-purge.json', '{ this is not json');

        $this->assertSame('0', CacheVersion::current());
        $this->get('/')->assertOk()->assertSee('name="campbuddy-cache-version" content="0"', false);
    }

    public function test_a_second_purge_straight_after_the_first_is_refused(): void
    {
        $this->fakeWordCampSite();

        $this->purge()->assertSessionHas('status');
        $version = CacheVersion::current();

        // Not the `throttle` middleware — its counters live in the cache a purge empties.
        $this->purge()
            ->assertRedirect(route('dashboard'))
            ->assertSessionHasErrors('purge')
            ->assertSessionMissing('status');

        $this->assertSame($version, CacheVersion::current(), 'a refused purge must not bump the version');

        $this->travel(11)->seconds();
        $this->purge()->assertSessionHas('status');
    }

    public function test_a_purge_already_in_progress_blocks_a_second_one(): void
    {
        $this->fakeWordCampSite();
        $lock = Cache::lock('admin:cache-purge', 60);
        $this->assertTrue($lock->get());

        $this->purge()->assertSessionHasErrors('purge');

        $this->assertSame('0', CacheVersion::current());
        $lock->release();
    }

    public function test_fetch_failures_are_only_counted_from_this_run(): void
    {
        $event = $this->event();
        // An old failure from last week must not make this run look failed.
        FetchLog::create([
            'event_id' => $event->id, 'source' => 'wordcamp_rest', 'job_type' => 'sessions_speakers_sponsors',
            'status' => 'error', 'message' => 'old', 'fetched_at' => now()->subDays(7),
        ]);
        $this->fakeWordCampSite();

        $this->refresh()->assertSessionHas('status', fn ($s) => str_contains($s, 'Fresh data fetched for 1 event')
            && ! str_contains($s, 'couldn\'t fully refresh'));
    }
}
