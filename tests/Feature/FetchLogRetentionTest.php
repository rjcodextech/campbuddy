<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\FetchLog;
use App\Models\User;
use App\Support\ErrorReport;
use App\Support\FetchLogRetention;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The fetch log keeps 7 days plus the newest row of every (event, kind of
 * fetch), so it stops growing but "the last fetch" never disappears.
 */
class FetchLogRetentionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Queue::fake();
        Carbon::setTestNow('2026-10-10 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function event(string $slug = 'wc-log'): Event
    {
        return Event::create([
            'slug' => $slug, 'display_name' => 'WordCamp '.$slug, 'source_site_url' => "https://{$slug}.wordcamp.org/2026",
            'status' => 'active', 'is_visible' => true,
        ]);
    }

    /** Rows are written oldest first — as the app writes them, so a higher id is a newer fetch. */
    private function log(?Event $event, string $daysAgo, string $status = 'ok', string $job = 'sessions_speakers_sponsors', string $message = 'Fetched'): FetchLog
    {
        return FetchLog::create([
            'event_id' => $event?->id, 'source' => 'wordcamp', 'job_type' => $job, 'status' => $status,
            'message' => $message, 'fetched_at' => now()->subDays((float) $daysAgo),
        ]);
    }

    /** @return array<int, int> */
    private function ids(): array
    {
        return FetchLog::orderBy('id')->pluck('id')->all();
    }

    public function test_rows_older_than_seven_days_go_and_newer_ones_stay(): void
    {
        $event = $this->event();
        $old = [$this->log($event, '200'), $this->log($event, '20'), $this->log($event, '7.1')];
        $recent = [$this->log($event, '6.9'), $this->log($event, '3'), $this->log($event, '1')];
        $keepNewest = $this->log($event, '0.01');

        $removed = FetchLogRetention::prune();

        $this->assertSame(3, $removed);
        $this->assertEqualsCanonicalizing(collect([$keepNewest, ...$recent])->map->id->all(), $this->ids());
        foreach ($old as $row) {
            $this->assertNull(FetchLog::find($row->id));
        }
    }

    public function test_the_newest_row_of_each_event_and_kind_stays_however_old_it_is(): void
    {
        $a = $this->event('wc-a');
        $b = $this->event('wc-b');

        // Event A: a schedule fetch and a branding fetch, all old; event B: only a branding fetch, old.
        $bBranding = $this->log($b, '90', 'partial', 'branding');
        $aScheduleOld = $this->log($a, '40');
        $aBrandingOld = $this->log($a, '35', 'error', 'branding');
        $aScheduleNewest = $this->log($a, '30');
        $aBrandingNewest = $this->log($a, '31', 'ok', 'branding', 'Logo saved');
        // …and a fresh one for A's schedule that makes the 30-day-old one just "old".
        $aScheduleFresh = $this->log($a, '0.1');

        FetchLogRetention::prune();

        $this->assertEqualsCanonicalizing(
            [$aScheduleFresh->id, $aBrandingNewest->id, $bBranding->id],
            $this->ids(),
            'Kept: the newest of each (event, kind) — the 30-day-old schedule row is not the newest any more.'
        );
        $this->assertNull(FetchLog::find($aScheduleOld->id));
        $this->assertNull(FetchLog::find($aScheduleNewest->id));
        $this->assertNull(FetchLog::find($aBrandingOld->id));
    }

    public function test_rows_that_belong_to_no_event_keep_their_newest_of_each_kind_too(): void
    {
        $lifecycle = $this->log(null, '60', 'ok', 'lifecycle');
        $oldDiscovery = $this->log(null, '50', 'ok', 'discovery');
        $newestDiscovery = $this->log(null, '45', 'ok', 'discovery', 'Found 3');

        FetchLogRetention::prune();

        $this->assertEqualsCanonicalizing([$newestDiscovery->id, $lifecycle->id], $this->ids());
        $this->assertNull(FetchLog::find($oldDiscovery->id));
    }

    public function test_a_row_exactly_at_the_limit_is_kept(): void
    {
        $event = $this->event();
        $justOver = FetchLog::create(['event_id' => $event->id, 'source' => 'wordcamp', 'job_type' => 'sessions_speakers_sponsors', 'status' => 'ok', 'message' => 'x', 'fetched_at' => now()->subDays(7)->subSecond()]);
        $atLimit = $this->log($event, '7');
        $fresh = $this->log($event, '0.1');

        FetchLogRetention::prune();

        $this->assertEqualsCanonicalizing([$fresh->id, $atLimit->id], $this->ids());
        $this->assertNull(FetchLog::find($justOver->id));
    }

    public function test_pruning_twice_removes_nothing_the_second_time(): void
    {
        $event = $this->event();
        $this->log($event, '12');
        $this->log($event, '9');
        $this->log($event, '0.1');

        $this->assertSame(2, FetchLogRetention::prune());
        $this->assertSame(0, FetchLogRetention::prune());
        $this->assertCount(1, $this->ids());
    }

    public function test_a_big_backlog_is_removed_in_chunks(): void
    {
        $event = $this->event();
        $backlog = fn (int $count) => DB::table('fetch_log')->insert(array_map(fn ($i) => [
            'event_id' => $event->id, 'source' => 'wordcamp', 'job_type' => 'sessions_speakers_sponsors', 'status' => 'ok',
            'message' => "Old {$i}", 'fetched_at' => now()->subDays(60 - ($i % 50))->toDateTimeString(),
        ], range(1, $count)));

        // 47 old rows, five at a time: ten rounds, the last one short. The newest row is written last.
        $backlog(47);
        $keep = $this->log($event, '0.1');
        $this->assertSame(47, FetchLogRetention::prune(null, 5));
        $this->assertSame([$keep->id], $this->ids());

        // A backlog that is an exact multiple of the chunk size ends cleanly too.
        FetchLog::query()->delete();
        $backlog(10);
        $keep = $this->log($event, '0.1');
        $this->assertSame(10, FetchLogRetention::prune(null, 5));
        $this->assertSame([$keep->id], $this->ids());
    }

    public function test_an_empty_log_is_fine(): void
    {
        $this->assertSame(0, FetchLogRetention::prune());
    }

    // ---- The command ------------------------------------------------------

    public function test_the_command_prunes_and_says_what_it_did(): void
    {
        $event = $this->event();
        $this->log($event, '11');
        $this->log($event, '10');
        $this->log($event, '0.1');

        $this->artisan('campbuddy:prune-fetch-log')
            ->expectsOutputToContain('3 row(s), 2 older than 7 days')
            ->expectsOutputToContain('Removed 2 row(s); 1 left.')
            ->assertSuccessful();

        $this->assertCount(1, $this->ids());
    }

    public function test_a_dry_run_only_counts(): void
    {
        $event = $this->event();
        $this->log($event, '11');
        $this->log($event, '10');
        $this->log($event, '0.1');

        $this->artisan('campbuddy:prune-fetch-log', ['--dry-run' => true])
            ->expectsOutputToContain('2 older than 7 days')
            ->expectsOutputToContain('Dry run — nothing removed.')
            ->assertSuccessful();

        $this->assertCount(3, $this->ids());
    }

    public function test_the_scheduler_runs_it_every_night(): void
    {
        $task = collect($this->app->make(Schedule::class)->events())->first(fn ($e) => $e->description === 'prune-fetch-log');

        $this->assertNotNull($task, 'The nightly prune is scheduled.');
        $this->assertSame('20 3 * * *', $task->expression);
    }

    // ---- What people read from the log keeps working ---------------------

    public function test_the_last_fetch_shown_in_the_admin_survives_the_prune(): void
    {
        $this->actingAs(User::factory()->create());
        $event = $this->event();

        // An event whose fetches all ran weeks ago: a schedule fetch that failed, a logo that was saved, event info that worked.
        $this->log($event, '40', 'ok', 'sessions_speakers_sponsors', 'Old ok run');
        $this->log($event, '35', 'ok', 'branding', 'Logo saved 35 days ago');
        $this->log($event, '32', 'ok', 'event_info', 'Event info 9 of 9');
        $this->log($event, '30', 'error', 'sessions_speakers_sponsors', 'Upstream timed out on the last run');

        FetchLogRetention::prune();

        $this->assertSame(3, FetchLog::count(), 'One row per kind is left.');

        $edit = $this->get(route('admin.events.edit', $event))->assertOk();
        $edit->assertSee('Last fetch:')->assertSee('Upstream timed out on the last run')
            ->assertSee('Logo saved 35 days ago')
            ->assertSee('Event info 9 of 9')
            ->assertDontSee('No schedule fetch has run yet')
            ->assertDontSee("Branding hasn't been auto-fetched yet");

        $list = $this->get(route('admin.events.index'))->assertOk()->getContent();
        $this->assertStringContainsString('error', $list);
        $this->assertStringNotContainsString('Not fetched', $list);

        $this->get(route('dashboard'))->assertOk()->assertSee('Last fetched');
    }

    public function test_what_the_errors_page_can_ask_for_is_what_is_kept(): void
    {
        $this->assertSame([1, 3, 7], array_keys(ErrorReport::PERIODS));  // (numeric keys)
        $this->assertLessThanOrEqual(FetchLogRetention::KEEP_DAYS, (int) max(array_keys(ErrorReport::PERIODS)));
        $this->assertSame((string) FetchLogRetention::KEEP_DAYS, ErrorReport::DEFAULT_PERIOD);

        $this->actingAs(User::factory()->create());
        $event = $this->event();
        $this->log($event, '5', 'error', 'sessions_speakers_sponsors', 'Failed five days ago');

        // Still there after the nightly prune, and an old bookmark for 30 days falls back to the default.
        FetchLogRetention::prune();
        $page = $this->get(route('admin.errors.index', ['period' => 30]))->assertOk();
        $page->assertSee('Failed five days ago')->assertSee('Last 7 days')->assertDontSee('Last 30 days')->assertSee('The log is kept for 7 days');

        $this->get(route('admin.errors.index', ['period' => 1]))->assertDontSee('Failed five days ago');
        $this->get(route('admin.errors.index', ['period' => 3]))->assertDontSee('Failed five days ago');
    }
}
