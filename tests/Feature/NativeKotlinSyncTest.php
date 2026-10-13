<?php

namespace Tests\Feature;

use App\Console\Commands\ExportAppConfigDefaultsCommand;
use App\Support\MobileAppDefaults;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * The native (Kotlin) app and the server must agree: its bundled default config, the config fields
 * it reads, the API paths it calls and the icons it can draw. These fail the moment one side moves.
 */
class NativeKotlinSyncTest extends TestCase
{
    private function kotlin(string $path): string
    {
        return file_get_contents(base_path('native-app/shared/src/commonMain/kotlin/club/campbuddy/shared/'.$path));
    }

    public function test_the_bundled_default_config_is_what_the_server_would_serve(): void
    {
        $file = str_replace("\r\n", "\n", file_get_contents(base_path(ExportAppConfigDefaultsCommand::PATH)));

        $this->assertSame(ExportAppConfigDefaultsCommand::source(), $file, 'Run: php artisan campbuddy:app-config-defaults');
    }

    public function test_the_app_reads_every_top_level_config_field_the_schema_requires(): void
    {
        $schema = json_decode(file_get_contents(base_path('schema/app-config.schema.json')), true);
        $models = $this->kotlin('model/ConfigModels.kt');

        foreach (array_keys($schema['properties']) as $field) {
            if ($field === 'onboarding') {
                continue; // read later, with the onboarding screens
            }
            $camel = lcfirst(str_replace('_', '', ucwords($field, '_')));
            $this->assertTrue(
                str_contains($models, "val {$camel}:") || str_contains($models, "@SerialName(\"{$field}\")"),
                "AppConfig in Kotlin has no field for \"{$field}\"."
            );
        }
    }

    public function test_every_api_path_the_app_calls_is_a_real_route(): void
    {
        preg_match_all('#"\$baseUrl(/app/[^"]+)"|"(/app/[^"]+)"#', $this->kotlin('data/Api.kt'), $found);
        $paths = array_values(array_filter(array_merge($found[1], $found[2])));
        $this->assertGreaterThan(8, count($paths));

        foreach (array_unique($paths) as $path) {
            // Kotlin string templates become sample values.
            $url = '/api/v1'.preg_replace(['#\$\{?slug\}?#', '#\$\{?eventId\}?#', '#\$\{?discoveryId\}?#'], ['wordcamp-test', '7', str_repeat('a', 64)], $path);
            $matched = false;
            foreach (['GET', 'POST'] as $method) {
                try {
                    Route::getRoutes()->match(Request::create($url, $method));
                    $matched = true;
                    break;
                } catch (NotFoundHttpException|MethodNotAllowedHttpException) {
                    // try the next method
                }
            }
            $this->assertTrue($matched, "The Kotlin app calls {$url}, which the server does not have.");
        }
    }

    public function test_the_app_can_draw_every_icon_the_default_config_names(): void
    {
        $components = $this->kotlin('ui/Components.kt');
        $config = MobileAppDefaults::config();

        foreach (array_merge($config['tabs'], $config['organizer']['tabs']) as $tab) {
            $this->assertStringContainsString("\"{$tab['icon']}\" to", $components, "The app has no drawing for the icon \"{$tab['icon']}\".");
        }
    }

    public function test_the_app_knows_the_home_sections_it_draws_are_server_choices(): void
    {
        $screens = $this->kotlin('ui/EventScreens.kt');
        preg_match_all('/"([a-z_]+)" -> (?:HeroCard|HappeningNow|UpNext)/', $screens, $drawn);

        foreach ($drawn[1] as $type) {
            $this->assertContains($type, MobileAppDefaults::SECTION_TYPES, "The app draws a \"{$type}\" block the server does not offer.");
        }
        $this->assertContains('hero', $drawn[1]);
    }

    public function test_the_apps_copies_of_the_websites_artwork_are_current(): void
    {
        $map = [
            'logo-wordmark-sm.png' => 'wordmark.png', 'icons/icon-192.png' => 'app_icon.png',
        ];

        foreach ($map as $web => $app) {
            $copy = base_path('native-app/shared/src/commonMain/composeResources/drawable/'.$app);
            $this->assertFileExists($copy);
            $this->assertSame(md5_file(public_path('media/'.$web)), md5_file($copy), "{$app} is out of date: run python native-app/scripts/sync-web-assets.py");
        }

        // The tab drawings are rendered to PNG (Android cannot draw SVG); the hash of each source SVG is recorded.
        $recorded = json_decode(file_get_contents(base_path('native-app/web-art.json')), true);
        foreach (['home', 'my-day', 'quest', 'contribute', 'explore', 'camp-card'] as $name) {
            $this->assertSame(md5_file(public_path("media/{$name}.svg")), $recorded[$name] ?? null, "The website's {$name}.svg changed: run python native-app/scripts/sync-web-assets.py");
            $this->assertFileExists(base_path('native-app/shared/src/commonMain/composeResources/drawable/nav_'.str_replace('-', '_', $name).'.png'));
        }
    }

    public function test_the_apps_copy_of_the_website_icons_is_current(): void
    {
        $file = str_replace("
", "
", file_get_contents(base_path(\App\Console\Commands\ExportAppIconsCommand::PATH)));

        $this->assertSame(\App\Console\Commands\ExportAppIconsCommand::source(), $file, 'Run: php artisan campbuddy:app-icons');
    }

    public function test_the_apps_contribute_teams_are_the_websites_teams(): void
    {
        $js = file_get_contents(base_path('resources/js/attendee/contrib-teams.js'));
        $kotlin = $this->kotlin('domain/ContribTeams.kt');

        preg_match_all("/^\s{4}id: '([a-z-]+)',\n\s+name: '([^']+)'/m", $js, $teams, PREG_SET_ORDER);
        $this->assertGreaterThanOrEqual(10, count($teams));
        foreach ($teams as [$all, $id, $name]) {
            $this->assertStringContainsString("ContribTeam(\"{$id}\", \"{$name}\"", $kotlin, "Team {$id} is missing: run node native-app/scripts/gen-contrib-teams.mjs");
        }

        preg_match_all("/key: '([a-z]+)'/", $js, $tags);
        foreach ($tags[1] as $key) {
            $this->assertStringContainsString("ContribTag(\"{$key}\"", $kotlin, "Tag {$key} is missing: run node native-app/scripts/gen-contrib-teams.mjs");
        }
    }
}
