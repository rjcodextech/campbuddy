<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\FetchLog;
use App\Models\User;
use App\Support\SystemHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Errors live on their own page; the dashboard only summarises them.
 */
class AdminErrorsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Carbon::setTestNow('2026-09-26 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function event(string $slug = 'wc-test', string $name = 'WordCamp Test'): Event
    {
        return Event::create([
            'slug' => $slug,
            'display_name' => $name,
            'source_site_url' => "https://{$slug}.wordcamp.org/2026",
            'status' => 'draft',
            'is_visible' => true,
        ]);
    }

    private function log(?Event $event, string $status, string $message, string $when = 'now', string $job = 'sessions_speakers_sponsors'): FetchLog
    {
        return FetchLog::create([
            'event_id' => $event?->id,
            'source' => 'wordcamp',
            'job_type' => $job,
            'status' => $status,
            'message' => $message,
            'fetched_at' => Carbon::parse($when),
        ]);
    }

    private function signIn(): void
    {
        $this->actingAs(User::factory()->create());
    }

    public function test_guests_are_sent_to_the_login_screen(): void
    {
        $this->get(route('admin.errors.index'))->assertRedirect(route('login'));
    }

    public function test_the_sidebar_links_to_the_errors_page(): void
    {
        $this->signIn();

        $this->get(route('admin.events.index'))->assertSee(route('admin.errors.index'), false)->assertSee('Errors');
    }

    public function test_a_failure_that_repeats_is_one_row_with_a_count(): void
    {
        $this->signIn();
        $event = $this->event();

        foreach (['-3 hours', '-2 hours', '-1 hour'] as $when) {
            $this->log($event, 'error', 'Upstream timed out', $when);
        }
        $this->log($event, 'partial', 'Speakers kept their last copy', '-30 minutes');

        $response = $this->get(route('admin.errors.index'))->assertOk();

        $this->assertCount(2, $response->viewData('fetchProblems'));
        $response->assertSee('Upstream timed out')->assertSee('×3')->assertSee('Speakers kept their last copy')
            ->assertSee('WordCamp Test')->assertSee('Schedule');
    }

    public function test_successful_fetches_are_never_listed(): void
    {
        $this->signIn();
        $this->log($this->event(), 'ok', 'Fetched 40 sessions');

        $this->get(route('admin.errors.index'))->assertOk()->assertDontSee('Fetched 40 sessions')->assertSee('No fetch problems');
    }

    public function test_the_period_defaults_to_seven_days_and_can_be_widened(): void
    {
        $this->signIn();
        $event = $this->event();
        $this->log($event, 'error', 'Yesterday problem', '-1 day');
        $this->log($event, 'error', 'Two weeks ago problem', '-14 days');

        $this->get(route('admin.errors.index'))->assertSee('Yesterday problem')->assertDontSee('Two weeks ago problem');
        $this->get(route('admin.errors.index', ['period' => 30]))->assertSee('Two weeks ago problem');
        $this->get(route('admin.errors.index', ['period' => 1]))->assertSee('Yesterday problem');
        // A made-up period falls back to the default.
        $this->get(route('admin.errors.index', ['period' => 9999]))->assertDontSee('Two weeks ago problem');
    }

    public function test_fetch_problems_can_be_filtered_by_event_job_and_result(): void
    {
        $this->signIn();
        $a = $this->event('wc-a', 'WordCamp Alpha');
        $b = $this->event('wc-b', 'WordCamp Beta');
        $this->log($a, 'error', 'Alpha schedule broke');
        $this->log($b, 'error', 'Beta schedule broke');
        $this->log($b, 'partial', 'Beta branding partly', 'now', 'branding');

        $messages = fn (array $query) => $this->get(route('admin.errors.index', $query))->viewData('fetchProblems')->pluck('message')->sort()->values()->all();

        $this->assertSame(['Alpha schedule broke'], $messages(['event' => $a->id]));
        $this->assertSame(['Beta branding partly'], $messages(['job' => 'branding']));
        $this->assertSame(['Alpha schedule broke', 'Beta schedule broke'], $messages(['result' => 'error']));
        $this->assertSame(['Beta schedule broke'], $messages(['event' => $b->id, 'result' => 'error']));
    }

    public function test_a_problem_that_belongs_to_no_event_is_labelled_all_events(): void
    {
        $this->signIn();
        $this->log(null, 'error', 'Discovery could not reach wordcamp.org', 'now', 'discovery');

        $this->get(route('admin.errors.index'))->assertSee('All events')->assertSee('Discovery could not reach wordcamp.org');
    }

    public function test_the_system_checks_are_shown_with_their_fix(): void
    {
        $this->signIn();
        Cache::forget(SystemHealth::HEARTBEAT_KEY);

        $this->get(route('admin.errors.index'))
            ->assertSee('The scheduler has never run')
            ->assertSee('Fix:');
    }

    public function test_the_dashboard_only_summarises_and_links_to_the_errors_page(): void
    {
        $this->signIn();
        $this->log($this->event(), 'error', 'Upstream timed out');

        $response = $this->get(route('dashboard'))->assertOk();

        // The detail moved; the dashboard just counts and points.
        $response->assertDontSee('Upstream timed out')
            ->assertDontSee('The scheduler has never run')
            ->assertSee('need attention')
            ->assertSee('View errors')
            ->assertSee(route('admin.errors.index'), false);
    }

    public function test_the_dashboard_says_so_when_nothing_is_wrong(): void
    {
        $this->signIn();
        Cache::put(SystemHealth::HEARTBEAT_KEY, now()->toIso8601String());

        $this->get(route('dashboard'))->assertOk()->assertSee('No problems')->assertDontSee('View errors');
    }
}
