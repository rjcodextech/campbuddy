<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use App\Support\EventData;
use App\Support\ScheduledTasks;
use App\Support\SystemHealth;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * php artisan campbuddy:run-all — caches cleared, migrations run, every
 * scheduled task run once now, and the queue worked off; terminal only.
 */
class RunAllCommandTest extends TestCase
{
    use RefreshDatabase;

    private const SITE = 'https://test.wordcamp.org/2026';

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            self::SITE.'/wp-json/wp/v2/sessions*' => Http::response([['id' => 1, 'title' => ['rendered' => 'Keynote'], 'meta' => []]], 200, ['X-WP-TotalPages' => '1']),
            // Every other fetch (the site's pages, discovery, photos) finds nothing, without failing.
            '*' => Http::response([], 200, ['X-WP-TotalPages' => '1']),
        ]);
    }

    public function test_it_runs_every_scheduled_task_now_and_works_the_queue_off(): void
    {
        $event = Event::withoutEvents(fn () => Event::create([
            'slug' => 'wc-test', 'display_name' => 'WordCamp Test 2026', 'source_site_url' => self::SITE, 'status' => 'active', 'is_visible' => true,
        ]));
        Cache::forget(SystemHealth::HEARTBEAT_KEY);

        $run = $this->artisan('campbuddy:run-all', ['--wait' => 0])
            ->expectsOutputToContain('Clearing caches')
            ->expectsOutputToContain('Database migrations')
            ->expectsOutputToContain('Every scheduled task');
        foreach (ScheduledTasks::all() as $task) {
            $run->expectsOutputToContain($task['name']);
        }
        $run->expectsOutputToContain('Jobs still waiting')->expectsOutputToContain('All done')->assertSuccessful()->run();

        // The heartbeat ran, and the fetch the 15-minute task queued was worked off in the same run.
        $this->assertNotNull(Cache::get(SystemHealth::HEARTBEAT_KEY));
        $this->assertSame('Keynote', EventData::get($event->id, 'sessions')[0]['title']);
        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function test_it_waits_for_jobs_queued_a_little_later_up_to_wait_seconds(): void
    {
        // Further off than --wait: left for the cron, and said so.
        dispatch(fn () => Cache::put('run-all-test-much-later', true))->delay(now()->addMinutes(30));
        $this->artisan('campbuddy:run-all', ['--wait' => 0])->expectsOutputToContain('the cron works them off')->assertSuccessful()->run();
        $this->assertNull(Cache::get('run-all-test-much-later'));
        $this->assertSame(1, DB::table('jobs')->count());

        // A few seconds off: waited for and worked.
        dispatch(fn () => Cache::put('run-all-test-late-job', true))->delay(now()->addSeconds(6));
        $this->artisan('campbuddy:run-all', ['--wait' => 30])->assertSuccessful()->run();
        $this->assertTrue(Cache::get('run-all-test-late-job'));
        $this->assertSame(1, DB::table('jobs')->count(), 'only the 30-minute job is still waiting');
    }

    public function test_a_failing_task_is_reported_and_the_others_still_run(): void
    {
        app(Schedule::class)->call(fn () => throw new RuntimeException('upstream down'))->everyMinute()->name('test-failing-task');
        Cache::forget(SystemHealth::HEARTBEAT_KEY);

        $this->artisan('campbuddy:run-all', ['--wait' => 0])
            ->expectsOutputToContain('test-failing-task')
            ->expectsOutputToContain('upstream down')
            ->expectsOutputToContain('Done, with the problems above')
            ->assertFailed();

        $this->assertNotNull(Cache::get(SystemHealth::HEARTBEAT_KEY), 'the other tasks still ran');
    }

    public function test_admin_commands_lists_it_but_runs_it_only_from_the_terminal(): void
    {
        $this->actingAs(User::factory()->create());

        $html = $this->get('/admin/commands')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/campbuddy:run-all.*?Terminal only/s', $html);
        $this->post('/admin/commands/campbuddy:run-all')->assertForbidden();
    }
}
