<?php

namespace Tests\Feature;

use App\Jobs\FetchSpeakersSponsorsSessionsJob;
use App\Jobs\ParseAttendeeRosterJob;
use App\Models\Event;
use App\Models\FetchLog;
use App\Rules\NotPrivateNetworkUrl;
use App\Services\WordCampNormalizer;
use App\Services\WordCampRestClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Ingestion that degrades gracefully: one broken list never costs the
 * others, a suspicious empty result never wipes a live schedule, a site that
 * answers with something other than data is reported as such, and odd or
 * hostile values in the payload never reach the page.
 */
class DataResilienceTest extends TestCase
{
    use RefreshDatabase;

    private const SITE = 'https://test.wordcamp.org/2026';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    private function event(): Event
    {
        return Event::withoutEvents(fn () => Event::create([
            'slug' => 'wc-test',
            'display_name' => 'WordCamp Test 2026',
            'source_site_url' => self::SITE,
            'status' => 'active',
            'is_visible' => true,
        ]));
    }

    private function wpSession(int $id, string $title = 'A talk'): array
    {
        return [
            'id' => $id,
            'title' => ['rendered' => $title],
            'content' => ['rendered' => '<p>About it.</p>'],
            'session_track' => [],
            'meta' => ['_wcpt_session_time' => 1790000000, '_wcpt_session_duration' => 1800, '_wcpt_session_type' => 'session', '_wcpt_speaker_id' => []],
        ];
    }

    /** @param array<string, mixed> $overrides endpoint => response */
    private function fakeSite(array $overrides = []): void
    {
        $ok = fn ($body) => Http::response($body, 200, ['X-WP-TotalPages' => '1']);

        $routes = $overrides + [
            'sessions' => $ok([$this->wpSession(1), $this->wpSession(2)]),
            'speakers' => $ok([['id' => 10, 'title' => ['rendered' => 'Ada'], 'content' => ['rendered' => '']]]),
            'sponsors' => $ok([['id' => 20, 'title' => ['rendered' => 'Acme'], 'content' => ['rendered' => '']]]),
            'organizers' => $ok([]),
            'session_track' => $ok([]),
            'sponsor_level' => $ok([]),
            'session_category' => $ok([]),
        ];

        $fakes = [];
        foreach ($routes as $endpoint => $response) {
            $fakes[self::SITE."/wp-json/wp/v2/{$endpoint}*"] = $response;
        }

        Http::fake($fakes);
    }

    private function lastLog(): FetchLog
    {
        return FetchLog::latest('id')->firstOrFail();
    }

    public function test_a_clean_fetch_caches_every_list_and_logs_ok(): void
    {
        $event = $this->event();
        $this->fakeSite();

        FetchSpeakersSponsorsSessionsJob::dispatchSync($event);

        $this->assertCount(2, Cache::get("event:{$event->id}:sessions"));
        $this->assertSame('ok', $this->lastLog()->status);
        $this->assertSame('2 sessions, 1 speakers, 1 sponsors, 0 organizers', $this->lastLog()->message);
    }

    public function test_every_request_identifies_itself(): void
    {
        $event = $this->event();
        $this->fakeSite();

        FetchSpeakersSponsorsSessionsJob::dispatchSync($event);

        Http::assertSent(fn ($request) => str_starts_with($request->header('User-Agent')[0] ?? '', 'CampBuddy/'));
    }

    public function test_a_failing_optional_list_keeps_its_last_good_copy_and_the_rest_still_update(): void
    {
        $event = $this->event();
        Cache::put("event:{$event->id}:sponsors", [['id' => 99, 'name' => 'Old sponsor']], 3600);
        $this->fakeSite(['sponsors' => Http::response('Server error', 500)]);

        FetchSpeakersSponsorsSessionsJob::dispatchSync($event);

        $this->assertCount(2, Cache::get("event:{$event->id}:sessions"), 'sessions still refreshed');
        $this->assertSame('Old sponsor', Cache::get("event:{$event->id}:sponsors")[0]['name'], 'sponsors kept');
        $this->assertSame('partial', $this->lastLog()->status);
        $this->assertStringContainsString('sponsors not updated (the site answered HTTP 500)', $this->lastLog()->message);
    }

    public function test_a_taxonomy_the_site_does_not_use_is_not_reported_as_a_problem(): void
    {
        $event = $this->event();
        $this->fakeSite(['session_category' => Http::response(['code' => 'rest_no_route'], 404)]);

        FetchSpeakersSponsorsSessionsJob::dispatchSync($event);

        $this->assertSame('ok', $this->lastLog()->status);
    }

    public function test_an_empty_schedule_never_replaces_a_live_one(): void
    {
        $event = $this->event();
        Cache::put("event:{$event->id}:sessions", [['id' => 1, 'title' => 'Keynote']], 3600);
        $this->fakeSite(['sessions' => Http::response([], 200, ['X-WP-TotalPages' => '1'])]);

        FetchSpeakersSponsorsSessionsJob::dispatchSync($event);

        $this->assertSame('Keynote', Cache::get("event:{$event->id}:sessions")[0]['title']);
        $this->assertSame('partial', $this->lastLog()->status);
        $this->assertStringContainsString('sessions: the site returned none, kept the previous 1', $this->lastLog()->message);
    }

    public function test_an_html_page_instead_of_json_fails_the_run_without_touching_the_cache(): void
    {
        $event = $this->event();
        Cache::put("event:{$event->id}:sessions", [['id' => 1, 'title' => 'Keynote']], 3600);
        $this->fakeSite(['sessions' => Http::response('<html>Maintenance</html>', 200)]);

        try {
            FetchSpeakersSponsorsSessionsJob::dispatchSync($event);
            $this->fail('A non-JSON answer must fail the run (so the queue retries it).');
        } catch (\RuntimeException) {
        }

        $this->assertSame('Keynote', Cache::get("event:{$event->id}:sessions")[0]['title']);
        $this->assertSame('error', $this->lastLog()->status);
        $this->assertStringContainsString('not with a list of items', $this->lastLog()->message);
    }

    public function test_a_temporary_server_error_is_retried_before_it_counts(): void
    {
        $event = $this->event();
        $ok = Http::response([$this->wpSession(1)], 200, ['X-WP-TotalPages' => '1']);
        $this->fakeSite(['sessions' => Http::sequence()->push('busy', 503)->pushResponse($ok)]);

        FetchSpeakersSponsorsSessionsJob::dispatchSync($event);

        $this->assertCount(1, Cache::get("event:{$event->id}:sessions"));
        $this->assertSame('ok', $this->lastLog()->status);
    }

    public function test_malformed_items_are_skipped_not_fatal(): void
    {
        $event = $this->event();
        $this->fakeSite(['sessions' => Http::response(
            [$this->wpSession(1), ['title' => ['rendered' => 'No id']], 'not-an-object', $this->wpSession(3)],
            200,
            ['X-WP-TotalPages' => '1']
        )]);

        FetchSpeakersSponsorsSessionsJob::dispatchSync($event);

        $this->assertSame([1, 3], array_column(Cache::get("event:{$event->id}:sessions"), 'id'));
    }

    public function test_the_normalizer_turns_odd_and_hostile_values_into_safe_ones(): void
    {
        $normalizer = new WordCampNormalizer(new WordCampRestClient(self::SITE));

        [$session] = $normalizer->normalizeSessions([[
            'id' => 5,
            'title' => ['rendered' => 'Design &amp; Code'],
            'content' => ['rendered' => '<p>Learn <strong>blocks</strong>.</p><script>alert(1)</script>'],
            'session_track' => '7',
            'session_category' => [8, 'x', -1],
            'meta' => ['_wcpt_session_time' => '1790000000', '_wcpt_speaker_id' => '42', '_wcpt_session_slides' => 'javascript:alert(1)'],
        ]], [7 => 'Main Hall'], [8 => 'Beginner friendly']);

        $this->assertSame('Design & Code', $session['title']);
        $this->assertSame('Learn blocks.', $session['description']);
        $this->assertSame([7], $session['track_ids']);
        $this->assertSame(['Main Hall'], $session['track_names']);
        $this->assertSame(['Beginner friendly'], $session['category_names']);
        $this->assertSame([42], $session['speaker_ids']);
        $this->assertNotNull($session['starts_at']);
        $this->assertNull($session['slides_url']);
        $this->assertNull($session['duration_seconds']);

        [$speaker] = $normalizer->normalizeSpeakers([['id' => 1, 'title' => ['rendered' => 'Ada'], 'avatar_urls' => ['96' => 'javascript:alert(1)']]]);
        $this->assertNull($speaker['avatar_url']);

        [$sponsor] = $normalizer->normalizeSponsors([['id' => 1, 'title' => ['rendered' => 'Acme'], 'content' => ['rendered' => '<img src="data:image/svg+xml,<svg onload=alert(1)>">']]], []);
        $this->assertNull($sponsor['logo_url']);
    }

    public function test_a_long_description_is_trimmed_at_a_word(): void
    {
        $normalizer = new WordCampNormalizer(new WordCampRestClient(self::SITE));
        $long = '<p>'.str_repeat('word ', 300).'</p>';

        [$session] = $normalizer->normalizeSessions([['id' => 1, 'title' => ['rendered' => 'T'], 'content' => ['rendered' => $long]]], []);

        $this->assertLessThanOrEqual(601, mb_strlen($session['description']));
        $this->assertStringEndsWith('word…', $session['description']);
    }

    public function test_private_and_metadata_addresses_are_recognised_for_the_redirect_guard(): void
    {
        foreach (['127.0.0.1', '10.0.0.5', '192.168.1.1', '169.254.169.254', '[::1]', 'localhost'] as $host) {
            $this->assertTrue(NotPrivateNetworkUrl::isPrivateHost($host), $host);
        }

        $this->assertFalse(NotPrivateNetworkUrl::isPrivateHost('8.8.8.8'));
    }

    public function test_the_roster_import_is_a_handful_of_queries_whatever_the_size(): void
    {
        $event = $this->event();
        $items = collect(range(1, 120))->map(fn ($i) => "<li><span class=\"tix-attendee-name\"><span class=\"tix-first\">Person</span> <span class=\"tix-last\">{$i}</span></span></li>")->implode('');
        // Someone listed twice is stored once.
        $items .= '<li><span class="tix-attendee-name"><span class="tix-first">Person</span> <span class="tix-last">1</span></span></li>';
        Http::fake([self::SITE.'/attendees/' => Http::response("<ul class=\"tix-attendee-list\">{$items}</ul>")]);

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        ParseAttendeeRosterJob::dispatchSync($event);

        $this->assertSame(120, $event->attendeeRoster()->count());
        $this->assertLessThan(20, $queries, 'no per-attendee queries');
        $this->assertStringContainsString('120 attendees parsed', FetchLog::where('job_type', 'roster')->latest('id')->value('message'));
    }

    public function test_a_second_roster_run_updates_in_place_and_keeps_suppressions(): void
    {
        $event = $this->event();
        $page = fn (array $names) => '<ul class="tix-attendee-list">'.collect($names)->map(fn ($n) => "<li><span class=\"tix-attendee-name\"><span class=\"tix-first\">{$n}</span></span></li>")->implode('').'</ul>';

        Http::fake([self::SITE.'/attendees/' => Http::sequence()
            ->push($page(['Ada', 'Grace']))
            ->push($page(['Ada', 'Grace', 'Alan']))]);

        ParseAttendeeRosterJob::dispatchSync($event);
        $event->attendeeRoster()->where('name', 'Grace')->update(['is_suppressed' => true]);
        ParseAttendeeRosterJob::dispatchSync($event);

        $this->assertSame(3, $event->attendeeRoster()->count());
        $this->assertTrue($event->attendeeRoster()->where('name', 'Grace')->value('is_suppressed'));
    }
}
