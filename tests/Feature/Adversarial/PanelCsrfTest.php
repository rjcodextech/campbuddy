<?php

namespace Tests\Feature\Adversarial;

use App\Models\Event;
use App\Models\EventManager;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route as Router;
use Tests\TestCase;

/**
 * Forged requests, read straight from the router: every state-changing
 * address that runs with a session (admin, event manager, sign-in, password
 * reset) refuses a request without the CSRF token — for a guest, a signed-in
 * admin and a signed-in manager alike — and none of them can be reached with
 * a GET. The only session-less writes are the few public ones that are
 * stateless by design.
 */
class PanelCsrfTest extends TestCase
{
    use RefreshDatabase;

    /** Writes that deliberately run without a session or CSRF check (stateless, no account behind them). */
    private const STATELESS_WRITES = ['calendar.file'];

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Http::fake();
    }

    /** @return list<Route> */
    private function writeRoutes(): array
    {
        return collect(Router::getRoutes()->getRoutes())
            ->filter(fn (Route $r) => array_diff($r->methods(), ['GET', 'HEAD', 'OPTIONS']) !== [])
            ->reject(fn (Route $r) => str_starts_with($r->uri(), 'api/'))
            ->values()->all();
    }

    private static function checksCsrf(Route $route): bool
    {
        $middleware = $route->gatherMiddleware();
        $excluded = $route->excludedMiddleware();

        return in_array('web', $middleware, true)
            && ! in_array(ValidateCsrfToken::class, $excluded, true)
            && ! in_array(VerifyCsrfToken::class, $excluded, true);
    }

    private function url(Route $route): string
    {
        $values = ['event' => 1, 'eventId' => 1, 'offer' => 1, 'deal' => 1, 'quest' => 1, 'questId' => 1, 'entry' => 1, 'entryId' => 1,
            'table' => 1, 'tableId' => 1, 'free_steal' => 1, 'suggestion' => 1, 'submission' => 1, 'media' => 1, 'event_manager' => 1,
            'release' => 1, 'version' => 1, 'section' => 'text', 'slug' => 'privacy', 'name' => 'campbuddy:prune-fetch-log',
            'kind' => 'photos', 'discoveryId' => str_repeat('a', 64), 'token' => 'x', 'id' => 1, 'hash' => 'x'];

        $url = '/'.ltrim($route->uri(), '/');
        foreach ($route->parameterNames() as $name) {
            $url = str_replace(['{'.$name.'}', '{'.$name.'?}'], (string) ($values[$name] ?? 1), $url);
        }

        return $url;
    }

    private static function method(Route $route): string
    {
        return collect($route->methods())->reject(fn ($m) => in_array($m, ['GET', 'HEAD'], true))->first();
    }

    public function test_only_the_known_stateless_public_writes_skip_the_csrf_check(): void
    {
        $unchecked = collect($this->writeRoutes())->reject(fn (Route $r) => self::checksCsrf($r))
            ->map(fn (Route $r) => $r->getName() ?? $r->uri())->values()->all();

        $this->assertSame(self::STATELESS_WRITES, $unchecked);
    }

    public function test_every_admin_and_sign_in_write_refuses_a_forged_request_from_a_signed_in_admin(): void
    {
        $event = Event::create(['slug' => 'wc-alpha', 'display_name' => 'Alpha', 'source_site_url' => 'https://alpha.wordcamp.org/2026', 'status' => 'draft', 'is_visible' => false]);
        $admin = User::factory()->create(['name' => 'Original']);
        $this->app['env'] = 'production';
        $this->actingAs($admin);

        $routes = collect($this->writeRoutes())->filter(fn (Route $r) => str_starts_with($r->uri(), 'admin') && self::checksCsrf($r));
        $this->assertGreaterThan(70, $routes->count(), 'the admin write routes were found');

        foreach ($routes as $route) {
            $status = $this->call(self::method($route), $this->url($route), ['name' => 'Forged', 'display_name' => 'Forged', 'title' => 'Forged'])->status();
            $this->assertSame(419, $status, self::method($route)." {$route->uri()} answered {$status}");
        }

        $this->assertSame('Original', $admin->fresh()->name);
        $this->assertSame('Alpha', $event->fresh()->display_name);
        $this->assertAuthenticatedAs($admin);
    }

    public function test_every_manager_write_refuses_a_forged_request_from_a_signed_in_manager(): void
    {
        $event = Event::create(['slug' => 'wc-alpha', 'display_name' => 'Alpha', 'source_site_url' => 'https://alpha.wordcamp.org/2026', 'status' => 'active', 'is_visible' => true]);
        $manager = EventManager::factory()->create();
        $manager->events()->attach($event->id);
        $this->app['env'] = 'production';
        Auth::guard('manager')->setUser($manager);

        $routes = collect($this->writeRoutes())->filter(fn (Route $r) => str_starts_with($r->uri(), 'manager'));
        $this->assertGreaterThan(25, $routes->count(), 'the manager write routes were found');

        foreach ($routes as $route) {
            $status = $this->call(self::method($route), $this->url($route), ['display_name' => 'Forged', 'title' => 'Forged', 'venue' => 'Forged'])->status();
            $this->assertSame(419, $status, self::method($route)." {$route->uri()} answered {$status}");
        }

        $this->assertSame('Alpha', $event->fresh()->display_name);
        $this->assertSame(0, $event->quests()->where('title', 'Forged')->count());
    }

    public function test_the_sign_in_and_password_reset_forms_refuse_a_forged_request(): void
    {
        $admin = User::factory()->create(['password' => 'secret-password-1']);
        $this->app['env'] = 'production';

        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'secret-password-1'])->assertStatus(419);
        $this->assertGuest();
        $this->post('/admin/forgot-password', ['email' => $admin->email])->assertStatus(419);
        $this->post('/admin/reset-password', ['token' => 'x', 'email' => $admin->email, 'password' => 'secret-password-2', 'password_confirmation' => 'secret-password-2'])->assertStatus(419);
        $this->post('/manager/login', ['email' => 'someone@example.com', 'password' => 'secret-password-1'])->assertStatus(419);
    }

    public function test_no_admin_or_manager_action_that_changes_something_answers_a_get(): void
    {
        $verbs = '/(destroy|delete|remove|suppress|unsuppress|publish|withdraw|rollback|discard|purge|refresh|approve|dismiss|reset|store|update|run|notify|clear|logout|release-claim|roles|upload|chunk|art|webhook|colors|site|broadcasts)$/';
        // Downloads, and the password reset form (the page the e-mailed link opens; the reset itself is a POST).
        $reads = ['admin.events.deal-leads.export', 'admin.deals.leads.export', 'password.reset'];

        $gets = collect(Router::getRoutes()->getRoutes())
            ->filter(fn (Route $r) => in_array('GET', $r->methods(), true) && preg_match('#^(admin|manager)#', $r->uri()))
            ->filter(fn (Route $r) => preg_match($verbs, (string) $r->getName()) && ! in_array($r->getName(), $reads, true))
            ->map(fn (Route $r) => $r->getName())->values()->all();

        $this->assertSame([], $gets, 'actions reachable with a GET (a link or an <img> could trigger them)');
    }
}
