<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Every analytics parameter the app sends must be reportable in GA: it has
 * to be registered as a custom dimension or metric (config/analytics.php),
 * which campbuddy:ga-setup then creates in the GA property.
 */
class AnalyticsRegistryTest extends TestCase
{
    use RefreshDatabase;

    /** Parameters set on every hit by the tag itself (analytics.blade.php), not by track(). */
    private const PAGE_LEVEL = ['event_slug', 'display_mode'];

    /** GA4's own ecommerce params (items and the list they came from): reported without a custom dimension. */
    private const GA_BUILT_IN = ['items', 'item_list_id', 'item_list_name'];

    /** @return array<string, array<int, string>> event => params, read from analytics.js's EVENTS */
    private function allowlist(): array
    {
        $js = file_get_contents(resource_path('js/attendee/analytics.js'));
        preg_match('/const EVENTS = \{(.*?)\n\};/s', $js, $block);
        preg_match_all("/^\s+([a-z_]+): \[([^\]]*)\]/m", $block[1], $rows, PREG_SET_ORDER);

        $events = [];
        foreach ($rows as [, $event, $params]) {
            preg_match_all("/'([a-z_]+)'/", $params, $names);
            $events[$event] = $names[1];
        }

        return $events;
    }

    public function test_every_parameter_the_app_sends_is_registered_for_reporting(): void
    {
        $events = $this->allowlist();
        $this->assertGreaterThan(60, count($events), 'the allowlist was read');

        $sent = array_values(array_diff(array_unique([...self::PAGE_LEVEL, ...array_merge(...array_values($events))]), self::GA_BUILT_IN));
        $registered = [...array_keys(config('analytics.dimensions')), ...array_keys(config('analytics.metrics'))];

        $this->assertSame([], array_values(array_diff($sent, $registered)), 'params sent but not registered in config/analytics.php');
        $this->assertSame([], array_values(array_diff($registered, $sent)), 'params registered but never sent');
    }

    public function test_no_event_or_parameter_uses_a_name_ga4_reserves(): void
    {
        // GA4 refuses these as custom dimensions ("session_id is a reserved
        // parameter_name"), or treats them as its own — a param with one of
        // these names is never reportable, and can corrupt GA's own data.
        $reservedParams = [
            'session_id', 'user_id', 'client_id', 'page_location', 'page_referrer', 'page_title',
            'language', 'screen_resolution', 'engagement_time_msec', 'session_engaged', 'firebase_conversion',
        ];
        $reservedEvents = [
            'ad_click', 'ad_exposure', 'ad_impression', 'ad_query', 'adunit_exposure', 'app_clear_data',
            'app_install', 'app_remove', 'app_update', 'error', 'first_open', 'first_visit', 'in_app_purchase',
            'notification_dismiss', 'notification_foreground', 'notification_open', 'notification_receive',
            'os_update', 'screen_view', 'session_start', 'user_engagement',
        ];
        $reservedPrefixes = '/^(google_|ga_|firebase_)/';

        $events = $this->allowlist();
        $params = array_unique([...self::PAGE_LEVEL, ...array_merge(...array_values($events))]);

        $this->assertSame([], array_values(array_intersect($params, $reservedParams)), 'reserved parameter names');
        $this->assertSame([], array_values(array_intersect(array_keys($events), $reservedEvents)), 'reserved event names');
        $this->assertSame([], array_values(preg_grep($reservedPrefixes, [...$params, ...array_keys($events)])), 'reserved prefixes');
    }

    public function test_the_registry_fits_ga4_limits_and_key_events_exist(): void
    {
        $this->assertLessThanOrEqual(50, count(config('analytics.dimensions')));
        $this->assertLessThanOrEqual(50, count(config('analytics.metrics')));
        $this->assertSame([], array_values(array_diff(config('analytics.key_events'), array_keys($this->allowlist()))));
    }

    public function test_event_pages_tell_analytics_which_screen_and_event_phase(): void
    {
        $this->withoutVite();
        $event = Event::withoutEvents(fn () => Event::create([
            'slug' => 'wc-test', 'display_name' => 'WordCamp Test', 'source_site_url' => 'https://test.wordcamp.org',
            'status' => 'active', 'is_visible' => true, 'starts_on' => today()->addDays(3), 'ends_on' => today()->addDays(4),
        ]));

        $this->get(route('event.my-day', $event))->assertSee('data-page-type="my_day" data-event-phase="before"', false);
        $this->get(route('event.guide', $event))->assertSee('data-page-type="guide"', false);
        $this->get(route('home'))->assertSee('data-page-type="picker"', false);
    }

    private const FALLBACK_TEST_KEY = "-----BEGIN PRIVATE KEY-----\n"
        ."MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQDC7879UmKgzX2a\n"
        ."EqSRzm0UIptXh26VIbzuAXiMnCFeXfBo3q1Nc3DC+rQz46zd6f5Vz91k4BRzHo/t\n"
        ."GhO6IrXlzSejo9fmvNs+lCDBRSMefZV1M4kFKp2m8DIyBeFAQbZ10hh2Gi/ExD0n\n"
        ."5SuO8ZRZMB5gO1l9TuD30o1RI7CJqY+U8LLPAs2yM9Bu9jTYmTRCqO1Qh7zZpY/0\n"
        ."0e0sbw8EGyrYdrSeMW7xsVYnboN8p2KSX/wkDV6GnpKhazjQjXqH2sr2glJCW3LS\n"
        ."tTiHQ+CISSdDVJOy7cd0eo6M24ZoaWJKzgPEnSYLGkQEWVwDdLWzTKKcp66h4JMM\n"
        ."4UWp6zi1AgMBAAECggEAAL3LPnzl+aEWcXPRMunDUfCIwTY3ZjNM5eXVCMneOZ7n\n"
        ."V2rDY3hFLUSpU0oyIdzuNfLWr3fHtCFhYEA5Y3uW0AMt+6oXm93LgqsrDbke11KY\n"
        ."cyvpQT8GfvhKZJTPpMArOrr8E1Tpqhip9+Ef/4jtLMKpVuna3wZiBidDmNOnK/ra\n"
        ."qMNF2W9pE0qeljzzlFkav3t2F5DEu/Jfm0bfrWRCcK4xbJDBNiBsJejx/8NJ3pL5\n"
        ."CbCQlFO4jgFWr6xVNLANTrJXOqBpN9HdwWkiulLwbEEXYriyB+nUfENaqJmZDQr2\n"
        ."SB7M/5WtU7/k4cvUx0PlMovjwDiQ23yr5w7tBS7oYQKBgQDpgIrfB22PmklEKjF/\n"
        ."j4g1eFMEBdqFMVISDZ+vXIVRLsqyOi2NqXC4Tf8w3OZHxyalq8Bc2woEuxuMmnjZ\n"
        ."cf6RCfWkFM/lZX5fCreSFppgaFO9pc5wWJGmNcNNi9o6QBMRvtDxSioq2OYfnE4L\n"
        ."zGbVYVuN1KSFCisRnBIq/GzD1QKBgQDVuAe78W0s8G4jpxJwrUZOCxVit28HKeJ1\n"
        ."Rnpw5Ma8nG5n62PUJB4nBSw0e8sdS1ys0G+WJxFWe0CUhcWP4LuYWpDANQDmVbdB\n"
        ."M7B6D25yUT5yy2cRAq/w4GOgkdEgXLR5CC5AC1gcZRLiOJRaIU6e50rm9mtUrP3F\n"
        ."gWsjzzRxYQKBgQDJHJt/aKhmrN45xZWIZplMZGwr5NbHLSmgvvcwVKHGwGR4iT8+\n"
        ."6hdZxmPrM/drDw4sVveBgm7okDc0LL+1mUdGqKtXPU2qKHZlaZeeTbV9H54wk6Rw\n"
        ."gvg/jcK/AZtd3F4QRKc1i0kySko6I6fc5ZoUYpw2VlW0+IML0b6cI3mSWQKBgHJd\n"
        ."XlYmZWPM7UNK3kVJk/ujVrhSrKlKspJLOq463laD51jYz7zv7uq2i+cx74knkNox\n"
        ."V+Uj1tVOc0qHIydQ/VIT9K3GL0sSx1Ou01sd8t5dt2s6iVbC91+Q0/9SyHPm5/R0\n"
        ."ejtvVqErPSWtXUW12RqTbrOVXe4b/Fi6Xpj1An1BAoGAHq7a/5Vk65aAQfOFPzPQ\n"
        ."7bwypjVzPeib9ezylV6YpWq1aaxw4sadiYKQCHi8YDfu5f/dpUzZme2XcUHx3ECI\n"
        ."RKNDKduQyBYteiBRyTlsFqUe47R/L3k/qzmxzlUR2fmMwhWT/6Bx2toXofgTPiOO\n"
        ."Q6MOS1dxY9GPFRk9BwhevDo=\n"
        ."-----END PRIVATE KEY-----\n";

    private function credentials(): string
    {
        $cnf = dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf';
        $configArgs = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        if (file_exists($cnf)) {
            $configArgs['config'] = $cnf;
        }

        $pem = null;
        $key = @openssl_pkey_new($configArgs);
        if ($key) {
            @openssl_pkey_export($key, $pem, null, file_exists($cnf) ? ['config' => $cnf] : []);
        }

        if (! $pem) {
            $pem = self::FALLBACK_TEST_KEY;
        }

        $path = tempnam(sys_get_temp_dir(), 'ga');
        file_put_contents($path, json_encode(['client_email' => 'setup@example.iam.gserviceaccount.com', 'private_key' => $pem]));

        return $path;
    }

    private function fakeGoogle(array $existingDimensions = []): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'tok']),
            '*/customDimensions?*' => Http::response(['customDimensions' => array_map(fn ($p) => ['parameterName' => $p], $existingDimensions)]),
            '*/customMetrics?*' => Http::response(['customMetrics' => []]),
            '*/keyEvents?*' => Http::response(['keyEvents' => [['eventName' => 'generate_lead']]]),
            '*' => Http::response(['name' => 'created']),
        ]);
    }

    public function test_setup_creates_only_what_is_missing(): void
    {
        $this->fakeGoogle(['event_slug', 'session_title']);

        $this->artisan('campbuddy:ga-setup', ['--property' => '123', '--credentials' => $this->credentials()])
            ->assertSuccessful();

        $posts = collect(Http::recorded())->map(fn ($pair) => $pair[0])->filter(fn (Request $r) => $r->method() === 'POST' && str_contains($r->url(), 'analyticsadmin'));

        $dimensions = $posts->filter(fn (Request $r) => str_ends_with($r->url(), '/customDimensions'))->map(fn (Request $r) => $r['parameterName']);
        $this->assertCount(count(config('analytics.dimensions')) - 2, $dimensions);
        $this->assertNotContains('event_slug', $dimensions);
        $this->assertTrue($posts->contains(fn (Request $r) => str_ends_with($r->url(), '/customMetrics') && $r['parameterName'] === 'metric_value' && $r['scope'] === 'EVENT'));
        $this->assertFalse($posts->contains(fn (Request $r) => str_ends_with($r->url(), '/keyEvents') && $r['eventName'] === 'generate_lead'), 'already a key event');
        $this->assertTrue($posts->contains(fn (Request $r) => str_ends_with($r->url(), '/keyEvents') && $r['eventName'] === 'discovery_join'));

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'properties/123/') && $r->hasHeader('Authorization', 'Bearer tok'));
    }

    public function test_a_dry_run_changes_nothing(): void
    {
        $this->fakeGoogle();

        $this->artisan('campbuddy:ga-setup', ['--property' => '123', '--credentials' => $this->credentials(), '--dry-run' => true])
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        Http::assertNotSent(fn (Request $r) => $r->method() === 'POST' && str_contains($r->url(), 'analyticsadmin'));
    }

    public function test_setup_explains_missing_configuration(): void
    {
        config(['analytics.property_id' => '']);
        $this->artisan('campbuddy:ga-setup')->expectsOutputToContain('GA_PROPERTY_ID')->assertFailed();
        $this->artisan('campbuddy:ga-setup', ['--property' => '123', '--credentials' => '/nope.json'])->expectsOutputToContain('GA_CREDENTIALS_PATH')->assertFailed();
    }
}
