<?php

namespace Tests\Feature;

use App\Jobs\FetchSpeakersSponsorsSessionsJob;
use App\Models\Event;
use App\Models\User;
use App\Support\EventData;
use App\Support\SystemHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * A live event must not go blank because the cron stopped, a cache was
 * flushed or a deploy skipped its migrations — and when something is wrong,
 * the admin dashboard has to say what and how to fix it.
 */
class StabilityTest extends TestCase
{
    use RefreshDatabase;

    private const SITE = 'https://test.wordcamp.org/2026';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Http::preventStrayRequests();
    }

    private function event(): Event
    {
        return Event::withoutEvents(fn () => Event::create([
            'slug' => 'wc-test', 'display_name' => 'WordCamp Test 2026', 'source_site_url' => self::SITE,
            'status' => 'active', 'is_visible' => true,
        ]));
    }

    public function test_fetched_data_survives_a_flushed_cache(): void
    {
        $event = $this->event();
        Http::fake([
            self::SITE.'/wp-json/wp/v2/sessions*' => Http::response([['id' => 7, 'title' => ['rendered' => 'Opening Keynote'], 'meta' => []]], 200, ['X-WP-TotalPages' => '1']),
            self::SITE.'/wp-json/wp/v2/sponsors*' => Http::response([['id' => 9, 'title' => ['rendered' => 'Acme Hosting'], 'content' => ['rendered' => '']]], 200, ['X-WP-TotalPages' => '1']),
            self::SITE.'/wp-json/wp/v2/*' => Http::response([], 200, ['X-WP-TotalPages' => '1']),
        ]);
        FetchSpeakersSponsorsSessionsJob::dispatchSync($event);

        Cache::flush();

        $this->assertSame('Opening Keynote', EventData::get($event->id, 'sessions')[0]['title']);
        $this->get(route('event.explore', $event))->assertOk()->assertSee('Acme Hosting');
        $this->assertIsArray(Cache::get("event:{$event->id}:sessions"), 'and it is back in the cache');
    }

    public function test_sessions_without_a_time_yet_still_reach_the_page(): void
    {
        $event = $this->event();
        EventData::put($event->id, 'sessions', [['id' => 1, 'title' => 'Building Block Themes', 'starts_at' => null]]);
        Cache::put("event:{$event->id}:fetched-at", now(), 3600);

        $this->get(route('event.my-day', $event))->assertOk()->assertSee('Building Block Themes');
    }

    public function test_the_errors_page_says_when_the_scheduler_is_not_running(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.errors.index'))
            ->assertOk()
            ->assertSee('The scheduler has never run')
            ->assertSee('schedule:run');

        // The dashboard doesn't repeat it: one line that points there.
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('need attention')
            ->assertSee('View errors')
            ->assertDontSee('The scheduler has never run');

        Cache::forever(SystemHealth::HEARTBEAT_KEY, now()->toIso8601String());

        $this->get(route('admin.errors.index'))
            ->assertDontSee('The scheduler has never run');
    }

    public function test_the_scheduler_writes_its_heartbeat(): void
    {
        $this->artisan('schedule:run')->assertSuccessful();

        $this->assertNotNull(Cache::get(SystemHealth::HEARTBEAT_KEY));
    }

    public function test_pending_migrations_are_flagged_with_the_command_to_run(): void
    {
        DB::table('migrations')->where('migration', 'like', '%create_event_feeds_table')->delete();

        $problem = collect(SystemHealth::problems())->firstWhere('fix', 'php artisan migrate --force');

        $this->assertNotNull($problem);
        $this->assertStringContainsString('create_event_feeds_table', $problem['detail']);
    }

    public function test_a_live_event_without_data_is_flagged(): void
    {
        $this->event();

        $titles = collect(SystemHealth::problems())->pluck('title');

        $this->assertTrue($titles->contains('WordCamp Test 2026 has no schedule data yet'));
    }

    public function test_the_health_endpoint_reports_checks_without_details(): void
    {
        Cache::forever(SystemHealth::HEARTBEAT_KEY, now()->toIso8601String());

        $this->getJson(route('api.health'))
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.scheduler', true)
            ->assertJsonPath('checks.migrations', true)
            ->assertJsonMissingPath('problems');
    }

    public function test_doctor_migrates_fetches_every_live_event_and_reports(): void
    {
        $event = $this->event();
        Http::fake([
            self::SITE.'/wp-json/wp/v2/sessions*' => Http::response([['id' => 1, 'title' => ['rendered' => 'Keynote'], 'meta' => []]], 200, ['X-WP-TotalPages' => '1']),
            self::SITE.'/wp-json/wp/v2/*' => Http::response([], 200, ['X-WP-TotalPages' => '1']),
            '*' => Http::response('', 404),
        ]);

        Process::fake();
        $log = storage_path('logs/doctor-test.log');
        file_put_contents($log, str_repeat('old error line'.PHP_EOL, 100));

        $this->artisan('campbuddy:doctor')
            ->expectsOutputToContain('Clearing caches')
            ->expectsOutputToContain('Logs')
            ->expectsOutputToContain('Front-end build')
            ->expectsOutputToContain('Database migrations')
            ->expectsOutputToContain('WordCamp Test 2026')
            ->expectsOutputToContain('Health check');

        $this->assertSame('Keynote', EventData::get($event->id, 'sessions')[0]['title']);
        $this->assertSame('', file_get_contents($log), 'logs emptied');
        Process::assertRan(fn ($process) => $process->command === ['npm', 'run', 'build']);
        @unlink($log);
    }
}
