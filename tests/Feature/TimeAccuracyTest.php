<?php

namespace Tests\Feature;

use App\Http\Controllers\HomeController;
use App\Jobs\EvaluateEventLifecycleJob;
use App\Jobs\FetchEventInfoJob;
use App\Jobs\FetchSpeakersSponsorsSessionsJob;
use App\Models\Event;
use App\Models\FetchLog;
use App\Services\SchedulePageProbe;
use App\Services\WordCampDiscoveryScraper;
use App\Support\EventData;
use App\Support\EventTime;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Every time CampBuddy shows or acts on must be the time at the venue: read
 * the way WordCamp stores it, in the event's own time zone, and checked
 * against what the site's own schedule page prints ("History Walk 6:30 AM IST").
 */
class TimeAccuracyTest extends TestCase
{
    use RefreshDatabase;

    private const SITE = 'https://rajasthan.wordcamp.org/2026';

    /** The WordCamp schedule block's markup, as a schedule page renders it. */
    private function schedulePageHtml(string $zone = 'IST'): string
    {
        return <<<HTML
            <html><body><header><nav>Home Schedule 10:00</nav></header><main>
            <div class="wp-block-wordcamp-schedule">
              <h2 class="wordcamp-schedule__date">Saturday, October 10</h2>
              <div class="wordcamp-schedule__time-slot">
                <h3 class="wordcamp-schedule__time-slot-header">6:30 am {$zone}</h3>
                <div class="wordcamp-schedule__session"><h4 class="wordcamp-schedule__session-title"><a href="#">History Walk</a></h4><span>Amber Fort</span></div>
              </div>
              <div class="wordcamp-schedule__time-slot">
                <h3 class="wordcamp-schedule__time-slot-header">10:00 am {$zone}</h3>
                <div class="wordcamp-schedule__session"><h4><a href="#">Keynote: Building the Open Web</a></h4><span>Main Hall</span></div>
                <div class="wordcamp-schedule__session"><h4><a href="#">Beginner’s Guide to Blocks</a></h4><span>Hall B</span></div>
              </div>
              <div class="wordcamp-schedule__time-slot">
                <h3 class="wordcamp-schedule__time-slot-header">1:00 pm {$zone}</h3>
                <div class="wordcamp-schedule__session"><h4>Lunch</h4><p>Veg thali, ₹ 10.50 extra for drinks — WordPress 6.4 stickers too</p></div>
              </div>
            </div></main><footer>© 2026 · 10:00</footer></body></html>
            HTML;
    }

    /** A WordCamp REST session: the venue's clock time stored as if it were UTC. */
    private function wpSession(int $id, string $title, int $hour, int $minute, ?string $shownTime = null): array
    {
        $session = [
            'id' => $id,
            'title' => ['rendered' => $title],
            'meta' => ['_wcpt_session_time' => gmmktime($hour, $minute, 0, 10, 10, 2026), '_wcpt_session_duration' => 1800],
        ];

        if ($shownTime !== null) {
            $session['session_date_time'] = ['date' => 'October 10, 2026', 'time' => $shownTime];
        }

        return $session;
    }

    private function fakeSite(array $sessions, ?array $index, ?string $scheduleHtml): void
    {
        $ok = fn ($body) => Http::response($body, 200, ['X-WP-TotalPages' => '1']);

        Http::fake([
            self::SITE.'/wp-json/' => $index === null ? Http::response('', 404) : Http::response($index),
            self::SITE.'/schedule/' => $scheduleHtml === null ? Http::response('', 404) : Http::response($scheduleHtml, 200, ['Content-Type' => 'text/html; charset=UTF-8']),
            self::SITE.'/wp-json/wp/v2/sessions*' => $ok($sessions),
            self::SITE.'/wp-json/wp/v2/*' => $ok([]),
            '*' => Http::response('', 404),
        ]);
    }

    private function event(array $attributes = []): Event
    {
        return Event::withoutEvents(fn () => Event::create($attributes + [
            'slug' => 'wc-rajasthan-'.uniqid(),
            'display_name' => 'WordCamp Rajasthan 2026',
            'source_site_url' => self::SITE,
            'status' => 'active',
            'is_visible' => true,
        ]));
    }

    private function sessionsAtRajasthan(): array
    {
        return [
            $this->wpSession(1, 'History Walk', 6, 30),
            $this->wpSession(2, 'Keynote: Building the Open Web', 10, 0),
            $this->wpSession(3, 'Beginner&#8217;s Guide to Blocks', 10, 0),
            $this->wpSession(4, 'Lunch', 13, 0),
        ];
    }

    // ---- Reading the schedule page -----------------------------------------------

    public function test_the_schedule_page_gives_the_zone_and_each_sessions_time(): void
    {
        $page = SchedulePageProbe::parse($this->schedulePageHtml());

        $this->assertSame('IST', $page['zone_token']);

        $times = SchedulePageProbe::timesFor($page, ['History Walk', 'Keynote: Building the Open Web', 'Beginner&#8217;s Guide to Blocks', 'Lunch', 'Not on the page']);

        $this->assertSame([390], $times['history walk']);      // 6:30
        $this->assertSame([600], $times['keynote: building the open web']);
        $this->assertSame([600], $times["beginner's guide to blocks"]);
        $this->assertSame([780], $times['lunch']);             // 1:00 pm
        $this->assertArrayNotHasKey('not on the page', $times);
    }

    public function test_prices_and_version_numbers_are_not_mistaken_for_times(): void
    {
        $page = SchedulePageProbe::parse('<p>Tickets ₹ 10.50 · WordPress 6.4 · 3.5 hours</p><p>9:15 AM CEST Doors open</p>');

        $this->assertSame([555], array_column($page['tokens'], 'minutes'));
        $this->assertSame('CEST', $page['zone_token']);
    }

    public function test_abbreviations_become_zones(): void
    {
        $this->assertSame('Asia/Kolkata', EventTime::fromAbbreviation('IST'));
        $this->assertSame('Europe/Berlin', EventTime::fromAbbreviation('cest'));
        $this->assertSame('+05:45', EventTime::fromAbbreviation('UTC+5:45'));
        $this->assertSame('-03:00', EventTime::fromAbbreviation('GMT-3'));
        $this->assertNull(EventTime::fromAbbreviation('XYZ'));
    }

    // ---- The fetch uses it --------------------------------------------------------

    public function test_the_zone_comes_from_the_schedule_page_when_the_site_settings_say_nothing(): void
    {
        $this->fakeSite($this->sessionsAtRajasthan(), null, $this->schedulePageHtml());
        $event = $this->event();

        FetchSpeakersSponsorsSessionsJob::dispatchSync($event);

        $this->assertSame('Asia/Kolkata', $event->fresh()->timezone);
        $walk = collect(EventData::get($event->id, 'sessions'))->firstWhere('id', 1);
        $this->assertSame('2026-10-10T06:30:00+05:30', $walk['starts_at']);

        $log = FetchLog::latest('id')->first();
        $this->assertSame('ok', $log->status);
        $this->assertStringContainsString('time zone taken from the schedule page (IST → Asia/Kolkata)', $log->message);
        $this->assertStringContainsString('times match the schedule page (IST) for all 4 sessions checked', $log->message);
    }

    public function test_the_sites_own_setting_wins_and_times_are_verified(): void
    {
        $this->fakeSite($this->sessionsAtRajasthan(), ['timezone_string' => 'Asia/Kolkata', 'gmt_offset' => 5.5], $this->schedulePageHtml());
        $event = $this->event();

        FetchSpeakersSponsorsSessionsJob::dispatchSync($event);

        $this->assertSame('Asia/Kolkata', $event->fresh()->timezone);
        $this->assertStringContainsString('times match the schedule page', FetchLog::latest('id')->value('message'));
        $this->assertStringNotContainsString('taken from the schedule page', FetchLog::latest('id')->value('message'));
    }

    public function test_a_site_storing_true_utc_is_detected_from_its_schedule_page(): void
    {
        // This site's timestamps are real UTC moments: 6:30 IST stored as 01:00 UTC.
        $sessions = [
            $this->wpSession(1, 'History Walk', 1, 0),
            $this->wpSession(2, 'Keynote: Building the Open Web', 4, 30),
            $this->wpSession(4, 'Lunch', 7, 30),
        ];
        $this->fakeSite($sessions, ['timezone_string' => 'Asia/Kolkata', 'gmt_offset' => 5.5], $this->schedulePageHtml());
        $event = $this->event();

        FetchSpeakersSponsorsSessionsJob::dispatchSync($event);

        $walk = collect(EventData::get($event->id, 'sessions'))->firstWhere('id', 1);
        $this->assertSame('2026-10-10T06:30:00+05:30', $walk['starts_at']);
        $this->assertStringContainsString('times match the schedule page', FetchLog::latest('id')->value('message'));
    }

    public function test_times_that_disagree_with_the_schedule_page_are_flagged(): void
    {
        // The page's times (in CEST) don't match what we read — e.g. a wrongly set zone.
        $page = str_replace(['6:30 am', '10:00 am', '1:00 pm'], ['3:00 am', '6:30 am', '9:30 am'], $this->schedulePageHtml('CEST'));
        $this->fakeSite($this->sessionsAtRajasthan(), ['timezone_string' => 'Europe/Berlin'], $page);
        $event = $this->event(['timezone' => 'Europe/Berlin', 'timezone_locked' => true]);

        FetchSpeakersSponsorsSessionsJob::dispatchSync($event);

        $log = FetchLog::latest('id')->first();
        $this->assertSame('partial', $log->status);
        $this->assertStringContainsString('session times differ from the schedule page (CEST) for 4 of 4', $log->message);
        $this->assertStringContainsString('“History Walk”: 3:00 on the page, 6:30 here', $log->message);
    }

    public function test_no_schedule_page_changes_nothing(): void
    {
        $this->fakeSite($this->sessionsAtRajasthan(), ['timezone_string' => 'Asia/Kolkata'], null);
        $event = $this->event();

        FetchSpeakersSponsorsSessionsJob::dispatchSync($event);

        $this->assertSame('ok', FetchLog::latest('id')->value('status'));
        $this->assertSame('2026-10-10T06:30:00+05:30', collect(EventData::get($event->id, 'sessions'))->firstWhere('id', 1)['starts_at']);
    }

    // ---- Dates and the zone from central.wordcamp.org --------------------------------

    public function test_central_fills_a_missing_zone_and_dates_but_never_overwrites(): void
    {
        Http::fake([
            'central.wordcamp.org/*' => Http::response([[
                'URL' => self::SITE.'/',
                'Venue Name' => 'JECRC',
                'Event Timezone' => 'Asia/Kolkata',
                'Start Date (YYYY-mm-dd)' => gmmktime(0, 0, 0, 10, 10, 2026),
                'End Date (YYYY-mm-dd)' => gmmktime(0, 0, 0, 10, 11, 2026),
            ]]),
            '*' => Http::response('', 404),
        ]);

        $bare = $this->event();
        FetchEventInfoJob::dispatchSync($bare);
        $bare->refresh();
        $this->assertSame(['Asia/Kolkata', '2026-10-10', '2026-10-11'], [$bare->timezone, $bare->starts_on->toDateString(), $bare->ends_on->toDateString()]);

        $set = $this->event(['timezone' => 'Europe/London', 'timezone_locked' => true, 'starts_on' => '2026-10-09']);
        FetchEventInfoJob::dispatchSync($set);
        $set->refresh();
        $this->assertSame(['Europe/London', '2026-10-09'], [$set->timezone, $set->starts_on->toDateString()]);
    }

    public function test_discovery_takes_the_events_own_date_not_the_utc_one(): void
    {
        // 09:00 on 10 Oct in India is 03:30 on 10 Oct UTC — but 23:00 on the 9th for a 04:30 IST start.
        $payload = ['events' => [[
            'title' => 'WordCamp Rajasthan 2026', 'url' => self::SITE.'/', 'location' => 'Jaipur, India',
            'date' => '2026-10-10 04:30:00', 'end_date' => '2026-10-11 18:00:00',
            'timestamp' => CarbonImmutable::parse('2026-10-10 04:30', 'Asia/Kolkata')->getTimestamp(),
        ]]];
        Http::fake(['events.wordpress.org/*' => Http::response('<script>globalEventsPayload["events0"] = '.json_encode($payload).';</script>')]);

        $found = (new WordCampDiscoveryScraper)->discoverUpcomingWordCamps();

        $this->assertSame('2026-10-10', $found[0]['starts_on']);
        $this->assertSame('2026-10-11', $found[0]['ends_on']);
    }

    // ---- "Is it over yet?" is decided at the venue --------------------------------

    public function test_an_event_stays_live_through_its_last_day_and_the_retention_days_after_at_the_venue(): void
    {
        $la = $this->event(['starts_on' => '2026-10-10', 'ends_on' => '2026-10-10', 'timezone' => 'America/Los_Angeles']);

        // 02:00 UTC on the 11th is still 19:00 on the 10th in Los Angeles.
        $this->travelTo(CarbonImmutable::parse('2026-10-11T02:00:00Z'));
        EvaluateEventLifecycleJob::dispatchSync();
        $this->assertSame('active', $la->fresh()->status);
        $this->withoutVite()->get('/')->assertSee('WordCamp Rajasthan 2026');

        // 08:00 UTC on the 11th is 01:00 on the 11th there: the last day is over
        // (so the picker stops listing it) — but nothing is archived yet.
        $this->travelTo(CarbonImmutable::parse('2026-10-11T08:00:00Z'));
        EvaluateEventLifecycleJob::dispatchSync();
        $this->assertSame('active', $la->fresh()->status);
        $this->withoutVite()->get('/')->assertDontSee('WordCamp Rajasthan 2026');

        // Retention runs to the end of the 13th in Los Angeles = 06:59:59 UTC on the 14th.
        $this->travelTo(CarbonImmutable::parse('2026-10-14T06:00:00Z'));
        EvaluateEventLifecycleJob::dispatchSync();
        $this->assertSame('active', $la->fresh()->status);

        $this->travelTo(CarbonImmutable::parse('2026-10-14T08:00:00Z'));
        EvaluateEventLifecycleJob::dispatchSync();
        $this->assertSame('archived', $la->fresh()->status);
    }

    public function test_an_event_east_of_utc_is_retained_by_its_local_days(): void
    {
        $nz = $this->event(['starts_on' => '2026-10-10', 'ends_on' => '2026-10-10', 'timezone' => 'Pacific/Auckland']);

        // 12:00 UTC on the 10th is already 01:00 on the 11th in Auckland: over, but retained.
        $this->travelTo(CarbonImmutable::parse('2026-10-10T12:00:00Z'));
        EvaluateEventLifecycleJob::dispatchSync();
        $this->assertSame('active', $nz->fresh()->status);
        $this->assertTrue(EventTime::isOver($nz));

        // Retention runs to the end of the 13th in Auckland (UTC+13) = 10:59:59 UTC that day.
        $this->travelTo(CarbonImmutable::parse('2026-10-13T12:00:00Z'));
        EvaluateEventLifecycleJob::dispatchSync();
        $this->assertSame('archived', $nz->fresh()->status);
    }

    public function test_discovery_profiles_are_kept_for_the_retention_days_after_the_last_day_at_the_venue(): void
    {
        $event = $this->event(['starts_on' => '2026-10-10', 'ends_on' => '2026-10-11', 'timezone' => 'Asia/Kolkata']);

        $this->postJson(route('api.discovery.store', $event), ['tags' => ['developer']])->assertCreated();

        $this->assertSame(
            '2026-10-14T18:29:59+00:00', // 23:59:59 IST on the 14th: the 11th plus three days
            CarbonImmutable::parse(\App\Models\DiscoveryProfile::first()->expires_at)->utc()->toIso8601String()
        );
    }

    public function test_pages_say_during_by_the_venues_date(): void
    {
        $event = $this->event(['starts_on' => '2026-10-10', 'ends_on' => '2026-10-10', 'timezone' => 'Pacific/Auckland']);

        // 20:00 UTC on the 9th = 09:00 on the 10th in Auckland.
        $this->travelTo(CarbonImmutable::parse('2026-10-09T20:00:00Z'));

        $this->withoutVite()->get(route('event.home', $event))->assertSee('data-event-phase="during"', false)
            ->assertSee('data-event-timezone="Pacific/Auckland"', false);
    }
}
