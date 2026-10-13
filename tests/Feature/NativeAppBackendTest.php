<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\NativePushToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Server side of the native app: token registration, start-up config and the
 * .well-known files for App Links / Universal Links.
 */
class NativeAppBackendTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'fcm-token-AAAAAAAAAAAAAAAAAAAA:APA91bExample_token-1234567890';

    private function event(string $slug = 'wordcamp-test'): Event
    {
        return Event::withoutEvents(fn () => Event::create([
            'slug' => $slug,
            'display_name' => 'Test',
            'source_site_url' => "https://{$slug}.wordcamp.org/2026",
            'status' => 'active',
            'is_visible' => true,
            'starts_on' => '2026-11-01',
            'ends_on' => '2026-11-02',
            'timezone' => 'Asia/Kolkata',
            'country_code' => 'IN',
        ]));
    }

    private function payload(array $over = []): array
    {
        return $over + ['device_id' => 'dev-1', 'platform' => 'android', 'token' => self::TOKEN, 'app_version' => '1.0.0'];
    }

    public function test_a_token_is_stored_once_and_refreshed(): void
    {
        $event = $this->event();

        $this->postJson("/api/v1/events/{$event->slug}/push/native-subscribe", $this->payload())->assertCreated();
        $this->postJson("/api/v1/events/{$event->slug}/push/native-subscribe", $this->payload(['app_version' => '1.0.1']))->assertCreated();

        $this->assertSame(1, NativePushToken::count());
        $this->assertSame('1.0.1', NativePushToken::first()->app_version);
    }

    public function test_bad_tokens_and_platforms_are_refused(): void
    {
        $event = $this->event();
        $url = "/api/v1/events/{$event->slug}/push/native-subscribe";

        $this->postJson($url, $this->payload(['platform' => 'windows']))->assertUnprocessable();
        $this->postJson($url, $this->payload(['token' => 'short']))->assertUnprocessable();
        $this->postJson($url, $this->payload(['token' => str_repeat('a', 30).' <script>']))->assertUnprocessable();
        $this->postJson($url, $this->payload(['app_version' => 'one']))->assertUnprocessable();
        $this->assertSame(0, NativePushToken::count());
    }

    public function test_one_device_cannot_hoard_tokens(): void
    {
        $event = $this->event();
        $url = "/api/v1/events/{$event->slug}/push/native-subscribe";

        foreach (['a', 'b', 'c'] as $suffix) {
            $this->postJson($url, $this->payload(['token' => self::TOKEN.$suffix]))->assertCreated();
        }
        $this->postJson($url, $this->payload(['token' => self::TOKEN.'d']))->assertUnprocessable();
        $this->assertSame(3, NativePushToken::count());
    }

    public function test_unsubscribing_removes_only_that_device(): void
    {
        $event = $this->event();
        $url = "/api/v1/events/{$event->slug}/push/native-subscribe";
        $this->postJson($url, $this->payload())->assertCreated();
        $this->postJson($url, $this->payload(['device_id' => 'dev-2', 'token' => self::TOKEN.'x']))->assertCreated();

        $this->deleteJson($url, ['device_id' => 'dev-1'])->assertNoContent();

        $this->assertSame(['dev-2'], NativePushToken::pluck('device_id')->all());
    }

    public function test_tokens_go_when_the_event_goes(): void
    {
        $event = $this->event();
        $this->postJson("/api/v1/events/{$event->slug}/push/native-subscribe", $this->payload())->assertCreated();

        $event->delete();

        $this->assertSame(0, NativePushToken::count());
    }

    public function test_app_config_reports_minimum_version_and_switches(): void
    {
        config(['mobile_app.min_version.ios' => '1.2.0']);

        $this->getJson('/api/v1/app-config')
            ->assertOk()
            ->assertJsonPath('min_version.ios', '1.2.0')
            ->assertJsonPath('features.local_reminders', true)
            ->assertHeader('Cache-Control', 'max-age=60, public');
    }

    public function test_asset_links_are_empty_until_a_fingerprint_is_set(): void
    {
        config(['mobile_app.android_sha256' => []]);
        $this->getJson('/.well-known/assetlinks.json')->assertOk()->assertExactJson([]);

        config(['mobile_app.android_sha256' => ['AA:BB:CC']]);
        $this->getJson('/.well-known/assetlinks.json')
            ->assertOk()
            ->assertJsonPath('0.target.package_name', 'club.campbuddy.app')
            ->assertJsonPath('0.target.sha256_cert_fingerprints.0', 'AA:BB:CC');
    }

    public function test_apple_association_names_the_app_once_the_team_is_set(): void
    {
        config(['mobile_app.apple_team_id' => null]);
        $this->getJson('/.well-known/apple-app-site-association')->assertOk()->assertJsonPath('applinks.details', []);

        config(['mobile_app.apple_team_id' => 'ABCDE12345']);
        $this->getJson('/.well-known/apple-app-site-association')
            ->assertOk()
            ->assertJsonPath('applinks.details.0.appIDs.0', 'ABCDE12345.club.campbuddy.app');
    }
}
