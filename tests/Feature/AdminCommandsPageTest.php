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
