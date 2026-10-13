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

    public function test_the_server_and_the_shell_agree_on_the_app_identity(): void
    {
        $shell = json_decode(file_get_contents(base_path('mobile-app/app/capacitor.config.json')), true);

        $this->assertSame($shell['appId'], config('mobile_app.app_id'));
        $this->assertStringContainsString('CampBuddyApp/', $shell['appendUserAgent']);
    }

    public function test_the_notification_look_is_the_same_on_the_phone_and_from_the_server(): void
    {
        $shell = json_decode(file_get_contents(base_path('mobile-app/app/capacitor.config.json')), true)['plugins']['LocalNotifications'];
        $push = file_get_contents(app_path('Support/NativePush.php'));

        $this->assertStringContainsString("'color' => '{$shell['iconColor']}'", $push);
        $this->assertStringContainsString("'icon' => '{$shell['smallIcon']}'", $push);
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

    public function test_the_pwa_manifest_and_the_app_use_the_same_name_and_icon_source(): void
    {
        $pwa = config('campbuddy.pwa');
        $shell = json_decode(file_get_contents(base_path('mobile-app/app/capacitor.config.json')), true);

        $this->assertSame($pwa['short_name'], $shell['appName']);
        $this->assertFileExists(public_path('media/favicon.png'), 'the app icons are made from this file (scripts/make-assets.py)');
    }
}
