<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureEventManager;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The website, the PWA and the store app share one code base. These checks
 * fail the moment one of them is changed without the others.
 */
class AppSyncTest extends TestCase
{
    /** @return list<string> the paths public/sw.js refuses to cache */
    private function privatePaths(): array
    {
        $js = file_get_contents(public_path('sw.js'));
        preg_match("/const PRIVATE_PATHS = \\[(.*?)\\];/s", $js, $match);
        preg_match_all("/'([^']+)'/", $match[1] ?? '', $paths);

        return $paths[1];
    }

    public function test_every_signed_in_page_is_kept_out_of_the_service_worker_cache(): void
    {
        $private = $this->privatePaths();
        $this->assertNotEmpty($private, 'sw.js must list its PRIVATE_PATHS');

        $uncovered = [];
        $checked = 0;
        foreach (Route::getRoutes() as $route) {
            /** @var LaravelRoute $route */
            $middleware = $route->gatherMiddleware();
            $signedIn = in_array('auth', $middleware, true)
                || in_array('auth:manager', $middleware, true)
                || in_array(EnsureEventManager::class, $middleware, true)
                || collect($middleware)->contains(fn ($m) => is_string($m) && str_starts_with($m, 'auth'));

            if (! $signedIn || ! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $checked++;
            $uri = '/'.ltrim($route->uri(), '/');
            $covered = collect($private)->contains(fn ($base) => $uri === $base || str_starts_with($uri, $base.'/'));

            if (! $covered) {
                $uncovered[] = $uri;
            }
        }

        $this->assertGreaterThan(20, $checked, 'the check must really look at the signed-in routes');
        $this->assertSame([], $uncovered, 'These signed-in pages could be saved on a shared phone: add their prefix to PRIVATE_PATHS in public/sw.js.');
    }

    public function test_the_signing_in_pages_are_kept_out_of_the_cache_too(): void
    {
        $private = $this->privatePaths();

        foreach (['/admin/login', '/manager/login', '/forgot-password', '/profile'] as $path) {
            $this->assertTrue(
                collect($private)->contains(fn ($base) => $path === $base || str_starts_with($path, $base.'/')),
                "{$path} should not be cached"
            );
        }
    }

    public function test_the_app_start_up_config_lists_every_switch_the_page_reads(): void
    {
        $bridge = file_get_contents(resource_path('js/attendee/native-bridge.js'));
        preg_match_all('/features\??\.(\w+)/', $bridge, $read);

        $served = array_keys($this->getJson('/api/v1/app-config')->assertOk()->json('features'));
        foreach (array_unique($read[1]) as $feature) {
            $this->assertContains($feature, $served, "native-bridge.js reads features.{$feature}, which /api/v1/app-config does not serve");
        }
    }

    public function test_every_bottom_tab_of_the_panel_in_the_app_is_a_real_page(): void
    {
        $node = trim((string) shell_exec(PHP_OS_FAMILY === 'Windows' ? 'where node 2>NUL' : 'command -v node'));
        if ($node === '') {
            $this->markTestSkipped('node is not installed');
        }

        $json = shell_exec('node '.escapeshellarg(base_path('tools/organizer-tabs.mjs')));
        $pages = json_decode((string) $json, true);
        $this->assertIsArray($pages, 'tools/organizer-tabs.mjs must print JSON');

        $hrefs = collect($pages)->flatten()->unique()->values();
        $this->assertGreaterThan(8, $hrefs->count());

        foreach ($hrefs as $href) {
            try {
                Route::getRoutes()->match(\Illuminate\Http\Request::create($href, 'GET'));
            } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                $this->fail("The app shows a tab to {$href}, but the website has no such page.");
            }
        }
    }

    public function test_the_attendee_bottom_navigation_only_names_real_pages(): void
    {
        preg_match_all("/'route' => '([a-z.-]+)'/", file_get_contents(resource_path('views/attendee/partials/nav.blade.php')), $names);

        $this->assertNotEmpty($names[1]);
        foreach ($names[1] as $name) {
            $this->assertTrue(Route::has($name), "The attendee bottom bar links to route {$name}, which does not exist.");
        }
    }
}
