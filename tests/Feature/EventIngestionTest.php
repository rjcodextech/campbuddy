<?php

namespace Tests\Feature;

use App\Jobs\FetchBrandingAssetsJob;
use App\Jobs\FetchEventInfoJob;
use App\Jobs\FetchSpeakersSponsorsSessionsJob;
use App\Jobs\ParseAttendeeRosterJob;
use App\Models\AttendeeRoster;
use App\Models\Event;
use App\Models\FetchLog;
use App\Models\User;
use App\Services\AttendeeRosterScraper;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Getting an event's data onto its pages: that going live by ANY route queues
 * the fetches (the lifecycle sweep skips "approved", which is why auto-published
 * events had no logo or info), that the scheduler actually works the queue off
 * on a host with no worker, the nightly roster refresh, and the manual buttons.
 */
class EventIngestionTest extends TestCase
{
    use RefreshDatabase;

    private const SITE = 'https://test.wordcamp.org/2026';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');
        Http::preventStrayRequests();
    }

    private function event(array $overrides = []): Event
    {
        return Event::create($overrides + [
            'slug' => 'wc-test',
            'display_name' => 'WordCamp Test 2026',
            'source_site_url' => self::SITE,
            'status' => 'draft',
            'is_visible' => true,
        ]);
    }

    private function scheduled(string $name): ScheduledEvent
    {
        app(Kernel::class)->bootstrap();

        $event = collect(app(Schedule::class)->events())->first(fn (ScheduledEvent $e) => $e->description === $name);
        $this->assertNotNull($event, "No scheduled task named {$name}");

        return $event;
    }

    // ---- Going live queues the fetches ------------------------------------------

    public function test_approving_a_draft_queues_branding_and_event_info_but_not_the_schedule(): void
    {
        $event = $this->event();
        Queue::fake();

        $event->update(['status' => 'approved']);

        Queue::assertPushed(FetchBrandingAssetsJob::class, fn ($job) => $job->onlyMissing && $job->event->is($event));
        Queue::assertPushed(FetchEventInfoJob::class);
        Queue::assertNotPushed(FetchSpeakersSponsorsSessionsJob::class);
        Queue::assertNotPushed(ParseAttendeeRosterJob::class);
    }

    /** The lifecycle sweep does exactly this: draft → active, never touching "approved". */
    public function test_going_straight_from_draft_to_active_queues_everything(): void
    {
        $event = $this->event();
        Queue::fake();

        $event->update(['status' => 'active']);

        Queue::assertPushed(FetchBrandingAssetsJob::class);
        Queue::assertPushed(FetchEventInfoJob::class);
        Queue::assertPushed(FetchSpeakersSponsorsSessionsJob::class);
        Queue::assertPushed(ParseAttendeeRosterJob::class);
    }

    public function test_an_event_created_already_active_is_ingested_straight_away(): void
    {
        Queue::fake();

        $this->event(['status' => 'active']);

        Queue::assertPushed(FetchBrandingAssetsJob::class);
        Queue::assertPushed(FetchEventInfoJob::class);
        Queue::assertPushed(FetchSpeakersSponsorsSessionsJob::class);
    }

    public function test_moving_from_approved_to_active_starts_the_schedule_fetch(): void
    {
        $event = $this->event(['status' => 'approved']);
        Queue::fake();

        $event->update(['status' => 'active']);

        Queue::assertPushed(FetchSpeakersSponsorsSessionsJob::class);
    }

    public function test_a_plain_draft_or_an_unrelated_edit_queues_nothing(): void
    {
        Queue::fake();
        $draft = $this->event();
        $live = $this->event(['slug' => 'live', 'status' => 'active']);
        Queue::fake(); // forget what creating "live" queued

        $draft->update(['display_name' => 'Renamed draft']);
        $live->update(['display_name' => 'Renamed live', 'short_name' => '#WC']);
        $live->update(['status' => 'archived']);

        Queue::assertNothingPushed();
    }

    public function test_an_event_with_both_branding_assets_does_not_refetch_them(): void
    {
        Storage::disk('public')->put('branding/1/logo.png', 'x');
        Storage::disk('public')->put('branding/1/favicon.png', 'x');
        $event = $this->event(['logo_path' => 'branding/1/logo.png', 'favicon_path' => 'branding/1/favicon.png']);
        Queue::fake();

        $event->update(['status' => 'active']);

        Queue::assertNotPushed(FetchBrandingAssetsJob::class);
        Queue::assertPushed(FetchEventInfoJob::class);
    }

    public function test_a_queueing_failure_never_breaks_the_admins_save(): void
    {
        $event = $this->event();
        Queue::shouldReceive('push', 'later', 'pushOn')->andThrow(new \RuntimeException('queue backend down'));
        Bus::shouldReceive('dispatch')->andThrow(new \RuntimeException('queue backend down'));

        $event->update(['status' => 'active']);

        $this->assertSame('active', $event->fresh()->status);
    }

    // ---- The scheduler ------------------------------------------------------------------

    public function test_the_attendee_roster_refreshes_for_active_events_every_day_at_midnight(): void
    {
        $roster = $this->scheduled('ingest-attendee-roster');

        $this->assertSame('0 0 * * *', $roster->expression);

        $active = $this->event(['slug' => 'a', 'status' => 'active']);
        $this->event(['slug' => 'b', 'status' => 'approved']);
        $this->event(['slug' => 'c', 'status' => 'draft']);
        $this->event(['slug' => 'd', 'status' => 'archived']);
        Queue::fake();

        $roster->run(app());

        Queue::assertPushed(ParseAttendeeRosterJob::class, 1);
        Queue::assertPushed(ParseAttendeeRosterJob::class, fn ($job) => $job->event->is($active));
    }

    public function test_the_scheduler_drains_the_queue_itself_so_no_worker_process_is_needed(): void
    {
        $worker = $this->scheduled('work-queue');

        $this->assertSame('* * * * *', $worker->expression);
        $this->assertTrue($worker->withoutOverlapping, 'a slow run must not stack up under the next minute\'s');
        $this->assertTrue($worker->filtersPass(app()), 'runs on the database queue');

        config(['queue.default' => 'sync']);
        $this->assertFalse($worker->filtersPass(app()), 'nothing to drain on the sync queue');
    }

    public function test_the_queue_worker_is_scheduled_after_every_task_that_feeds_it(): void
    {
        app(Kernel::class)->bootstrap();
        $names = collect(app(Schedule::class)->events())->map->description->values();

        $this->assertSame('work-queue', $names->last(), 'it has to run last to see what this minute queued');
    }

    /** End to end: a queued job really gets run by the scheduler's worker. */
    public function test_queued_work_is_actually_processed_by_the_scheduled_worker(): void
    {
        $event = $this->event(['status' => 'active']);
        $this->assertGreaterThan(0, DB::table('jobs')->count(), 'going live queued work');

        Http::fake([
            self::SITE.'/wp-json/wp/v2/*' => Http::response([], 200, ['X-WP-TotalPages' => '1']),
            self::SITE.'/wp-json/' => Http::response([]),
            '*' => Http::response('', 404),
        ]);

        $this->scheduled('work-queue')->run(app());

        $this->assertSame(0, DB::table('jobs')->count(), 'the worker emptied the queue');
        $this->assertTrue(
            FetchLog::where('event_id', $event->id)->where('job_type', 'sessions_speakers_sponsors')->exists(),
            'and the schedule fetch actually ran'
        );
    }

    public function test_branding_is_backfilled_daily_for_live_events_still_missing_it(): void
    {
        $missing = $this->event(['slug' => 'a', 'status' => 'active']);
        Storage::disk('public')->put('branding/9/logo.png', 'x');
        Storage::disk('public')->put('branding/9/favicon.png', 'x');
        $this->event(['slug' => 'complete', 'status' => 'active', 'logo_path' => 'branding/9/logo.png', 'favicon_path' => 'branding/9/favicon.png']);
        $this->event(['slug' => 'draft', 'status' => 'draft']);
        Queue::fake();

        $backfill = $this->scheduled('backfill-event-branding');
        $this->assertSame('30 2 * * *', $backfill->expression);
        $backfill->run(app());

        Queue::assertPushed(FetchBrandingAssetsJob::class, 1);
        Queue::assertPushed(FetchBrandingAssetsJob::class, fn ($job) => $job->onlyMissing && $job->event->is($missing));
    }

    // ---- Roster: mirror the source page --------------------------------------------------------

    private function attendeesPage(array $names): string
    {
        $items = collect($names)->map(fn ($name) => '<li><span class="tix-attendee-name"><span class="tix-first">'
            .explode(' ', $name)[0].'</span> <span class="tix-last">'.(explode(' ', $name)[1] ?? '').'</span></span></li>')->implode('');

        return '<ul class="tix-attendee-list">'.$items.'</ul>';
    }

    private function rosterRow(Event $event, string $name, bool $suppressed = false): AttendeeRoster
    {
        return AttendeeRoster::create([
            'event_id' => $event->id, 'name' => $name, 'links' => [], 'is_suppressed' => $suppressed,
            'content_hash' => (new AttendeeRosterScraper)->contentHash($name, []),
        ]);
    }

    public function test_the_roster_refresh_adds_new_attendees_and_drops_ones_who_left_the_source_page(): void
    {
        $event = $this->event(['status' => 'active']);
        $stays = $this->rosterRow($event, 'Ada Lovelace');
        $left = $this->rosterRow($event, 'Grace Hopper');
        Http::fake([self::SITE.'/attendees/' => Http::response($this->attendeesPage(['Ada Lovelace', 'Alan Turing']))]);

        ParseAttendeeRosterJob::dispatchSync($event);

        $names = $event->attendeeRoster()->orderBy('name')->pluck('name')->all();
        $this->assertSame(['Ada Lovelace', 'Alan Turing'], $names);
        $this->assertNotNull($stays->fresh());
        $this->assertNull($left->fresh(), 'Grace left the Attendees page, so she leaves CampBuddy too');
        $this->assertStringContainsString('1 no longer listed and removed', FetchLog::where('job_type', 'roster')->latest('id')->value('message'));
    }

    public function test_a_suppressed_attendee_stays_suppressed_even_when_absent_from_the_source(): void
    {
        $event = $this->event(['status' => 'active']);
        $suppressed = $this->rosterRow($event, 'Removed Person', suppressed: true);
        Http::fake([self::SITE.'/attendees/' => Http::response($this->attendeesPage(['Ada Lovelace']))]);

        ParseAttendeeRosterJob::dispatchSync($event);

        $this->assertNotNull($suppressed->fresh(), 'the suppression list is what stops them coming back (IN5)');
        $this->assertTrue($suppressed->fresh()->is_suppressed);
    }

    public function test_an_empty_scrape_never_wipes_the_roster(): void
    {
        $event = $this->event(['status' => 'active']);
        $this->rosterRow($event, 'Ada Lovelace');
        // The page structure is intact but lists nobody (or the list was momentarily empty).
        Http::fake([self::SITE.'/attendees/' => Http::response('<ul class="tix-attendee-list"></ul>')]);

        ParseAttendeeRosterJob::dispatchSync($event);

        $this->assertSame(1, $event->attendeeRoster()->count());
    }

    // ---- Pages heal themselves when the cache is cold ----------------------------------------------

    public function test_a_page_view_on_a_cold_cache_queues_a_refresh_once_not_per_visitor(): void
    {
        $event = $this->event(['status' => 'active']);
        Queue::fake();

        $this->get(route('event.my-day', $event))->assertOk();
        $this->get(route('event.my-day', $event))->assertOk();
        $this->get(route('event.home', $event))->assertOk();

        Queue::assertPushed(FetchSpeakersSponsorsSessionsJob::class, 1);
    }

    public function test_a_warm_cache_queues_nothing(): void
    {
        $event = $this->event(['status' => 'active']);
        foreach (['sessions', 'speakers', 'sponsors'] as $key) {
            Cache::put("event:{$event->id}:{$key}", [], 3600); // an empty list is still "fetched"
        }
        Queue::fake();

        $this->get(route('event.my-day', $event))->assertOk();
        $this->get(route('event.explore', $event))->assertOk();

        Queue::assertNothingPushed();
    }

    public function test_ingested_data_is_kept_for_two_weeks_not_two_days(): void
    {
        $event = $this->event(['status' => 'active']);
        Http::fake([
            self::SITE.'/wp-json/wp/v2/*' => Http::response([], 200, ['X-WP-TotalPages' => '1']),
            '*' => Http::response('', 404),
        ]);

        FetchSpeakersSponsorsSessionsJob::dispatchSync($event);

        // Three days on, a stopped cron must not have blanked the event.
        $this->travel(3)->days();
        $this->assertNotNull(Cache::get("event:{$event->id}:sessions"));
    }

    // ---- Admin's manual buttons work with no worker -------------------------------------------------

    private function admin(): User
    {
        return User::factory()->create();
    }

    public function test_refresh_now_runs_immediately_and_reports_what_it_found(): void
    {
        $event = $this->event(['status' => 'active']);
        Http::fake([
            self::SITE.'/wp-json/wp/v2/sessions*' => Http::response([[
                'id' => 1, 'title' => ['rendered' => 'Opening'], 'meta' => ['_wcpt_session_time' => 1790000000],
            ]], 200, ['X-WP-TotalPages' => '1']),
            self::SITE.'/wp-json/wp/v2/*' => Http::response([], 200, ['X-WP-TotalPages' => '1']),
            '*' => Http::response('', 404),
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.events.refresh', $event))
            ->assertRedirect(route('admin.events.edit', $event))
            ->assertSessionHas('status', fn ($s) => str_starts_with($s, 'Refreshed — 1 sessions'));

        $this->assertCount(1, Cache::get("event:{$event->id}:sessions"));
    }

    public function test_refresh_now_shows_a_failure_instead_of_an_error_page(): void
    {
        $event = $this->event(['status' => 'active']);
        Http::fake(['*' => fn () => throw new ConnectionException('site down')]);

        $this->actingAs($this->admin())
            ->post(route('admin.events.refresh', $event))
            ->assertRedirect(route('admin.events.edit', $event))
            ->assertSessionHas('error', fn ($s) => str_starts_with($s, "Couldn't refresh sessions, speakers and sponsors"));
    }

    public function test_refetching_branding_runs_immediately_too(): void
    {
        $event = $this->event(['status' => 'active']);
        Http::fake([
            self::SITE.'/wp-json/' => Http::response(['site_icon_url' => self::SITE.'/files/icon.png']),
            self::SITE.'/files/icon.png' => Http::response('ICON', 200, ['Content-Type' => 'image/png']),
            self::SITE.'/' => Http::response('<html></html>'),
            'https://test.wordcamp.org/favicon.ico' => Http::response('', 404),
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.events.refresh-branding', $event))
            ->assertSessionHas('status', fn ($s) => str_starts_with($s, 'Branding re-fetched — Fetched: favicon'));

        $this->assertNotNull($event->fresh()->favicon_path);
    }
}
