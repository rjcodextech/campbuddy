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

        $sent = array_unique([...self::PAGE_LEVEL, ...array_merge(...array_values($events))]);
        $registered = [...array_keys(config('analytics.dimensions')), ...array_keys(config('analytics.metrics'))];

        $this->assertSame([], array_values(array_diff($sent, $registered)), 'params sent but not registered in config/analytics.php');
        $this->assertSame([], array_values(array_diff($registered, $sent)), 'params registered but never sent');
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

    private function credentials(): string
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
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
        $this->artisan('campbuddy:ga-setup')->expectsOutputToContain('GA_PROPERTY_ID')->assertFailed();
        $this->artisan('campbuddy:ga-setup', ['--property' => '123', '--credentials' => '/nope.json'])->expectsOutputToContain('GA_CREDENTIALS_PATH')->assertFailed();
    }
}
