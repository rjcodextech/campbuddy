<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin → Commands: the app's own artisan commands, described and runnable
 * one at a time (campbuddy:doctor only from the terminal).
 */
class AdminCommandsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guests_are_sent_to_the_login_screen(): void
    {
        $this->get('/admin/commands')->assertRedirect(route('login'));
        $this->post('/admin/commands/campbuddy:prune-fetch-log')->assertRedirect(route('login'));
    }

    public function test_it_lists_every_campbuddy_command_with_its_details(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/commands')
            ->assertOk()
            ->assertSee('Commands')
            ->assertSee('campbuddy:upcoming-push')
            ->assertSee('Push every phone about the next WordCamp in its own country')
            ->assertSee('--within')
            ->assertSee('campbuddy:export-push')
            ->assertSee('campbuddy:doctor')
            ->assertSee('Terminal only')
            // Laravel's own: the safe ones runnable, every other one listed.
            ->assertSee('Laravel maintenance')
            ->assertSee(route('admin.commands.run', 'optimize:clear'), false)
            ->assertSee(route('admin.commands.run', 'queue:failed'), false)
            ->assertSee('migrate:fresh')
            ->assertDontSee(route('admin.commands.run', 'migrate:fresh'), false)
            ->assertSee('db:wipe');
    }

    public function test_it_shows_what_runs_by_itself_from_the_live_schedule(): void
    {
        $html = $this->actingAs(User::factory()->create())->get('/admin/commands')->assertOk()
            ->assertSee('Runs by itself')
            ->assertSee('data-task="send-after-event-pushes"', false)
            ->assertSee('Day 7 &quot;Keep your WordCamp&quot; and day 15 &quot;Take it with you&quot; pushes', false)
            ->assertSee('data-task="clear-social-cache"', false)
            ->assertSee('Every hour at :10')
            ->assertSee('Every 15 minutes')
            ->getContent();

        // Every scheduled task is listed with a line saying what it does.
        foreach (\App\Support\ScheduledTasks::all() as $task) {
            $this->assertStringContainsString('data-task="'.$task['name'].'"', $html);
            $this->assertNotSame('', $task['what'], "{$task['name']} has no description");
        }
        $this->assertCount(count(\App\Support\ScheduledTasks::WHAT), \App\Support\ScheduledTasks::all(), 'every described task is scheduled, and the other way round');

        // A command the scheduler also runs says so on its card, and the task links to it.
        $this->assertStringContainsString('href="#cmd-campbuddy-clear-social-cache"', $html);
        $this->assertMatchesRegularExpression('/id="cmd-campbuddy-clear-social-cache".*?Runs by itself.*?Every day at 00:15/s', $html);
    }

    public function test_cron_expressions_read_as_words(): void
    {
        $this->assertSame('every minute', \App\Support\ScheduledTasks::cadence('* * * * *'));
        $this->assertSame('every 5 minutes', \App\Support\ScheduledTasks::cadence('*/5 * * * *'));
        $this->assertSame('every hour at :05', \App\Support\ScheduledTasks::cadence('05 * * * *'));
        $this->assertSame('every day at 03:20 UTC', \App\Support\ScheduledTasks::cadence('20 3 * * *'));
        $this->assertSame('every other day (even dates) at 03:00 UTC', \App\Support\ScheduledTasks::cadence('0 3 */2 * *'));
        $this->assertSame('0 9 * * 1', \App\Support\ScheduledTasks::cadence('0 9 * * 1'));
    }

    public function test_a_run_shows_its_output(): void
    {
        $this->actingAs(User::factory()->create())
            ->followingRedirects()
            ->post('/admin/commands/campbuddy:prune-fetch-log', ['opt_dry-run' => '1'])
            ->assertOk()
            ->assertSee('Done')
            ->assertSee('php artisan campbuddy:prune-fetch-log --dry-run');
    }

    public function test_push_commands_run_without_a_prompt(): void
    {
        // No prompt can be answered from a browser: --yes is added for it.
        $this->actingAs(User::factory()->create())
            ->followingRedirects()
            ->post('/admin/commands/campbuddy:upcoming-push', ['opt_country' => 'IN'])
            ->assertOk()
            ->assertSee('php artisan campbuddy:upcoming-push --country=IN --yes')
            ->assertSee('No upcoming WordCamp found.');
    }

    public function test_safe_laravel_commands_run_too(): void
    {
        $this->actingAs(User::factory()->create());

        $this->followingRedirects()
            ->post('/admin/commands/queue:failed')
            ->assertOk()
            ->assertSee('Done')
            ->assertSee('php artisan queue:failed');

        // An argument that takes several values (queue:retry {id*}).
        $this->followingRedirects()
            ->post('/admin/commands/queue:retry', ['arg_id' => 'all'])
            ->assertOk()
            ->assertSee('php artisan queue:retry all');
    }

    public function test_doctor_and_unknown_commands_cannot_be_run_from_here(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post('/admin/commands/campbuddy:doctor')->assertForbidden();
        $this->post('/admin/commands/migrate:fresh')->assertForbidden();
        $this->post('/admin/commands/db:wipe')->assertForbidden();
        $this->post('/admin/commands/no-such:command')->assertNotFound();
    }

    public function test_the_sidebar_links_to_the_commands_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin')
            ->assertSee(route('admin.commands.index'));
    }
}
