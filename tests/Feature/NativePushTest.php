<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\NativePushToken;
use App\Support\EventPush;
use App\Support\NativePush;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Pushes to the native app through FCM (App\Support\NativePush): off until
 * Firebase credentials exist, and the website push paths are not disturbed.
 */
class NativePushTest extends TestCase
{
    use RefreshDatabase;

    private array $history = [];

    private ?string $credentialsFile = null;

    protected function tearDown(): void
    {
        if ($this->credentialsFile) {
            @unlink($this->credentialsFile);
        }
        parent::tearDown();
    }

    private function fcm(array $fcmResponses): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        $this->credentialsFile = tempnam(sys_get_temp_dir(), 'fcm');
        file_put_contents($this->credentialsFile, json_encode(['project_id' => 'campbuddy-test', 'client_email' => 'push@campbuddy-test.iam.gserviceaccount.com', 'private_key' => $pem]));

        $mock = new MockHandler([new Response(200, [], json_encode(['access_token' => 'ya29.test', 'expires_in' => 3599])), ...$fcmResponses]);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));
        $this->app->instance(Client::class, new Client(['handler' => $stack]));

        Cache::forget('fcm.access_token');
        config(['services.fcm' => ['credentials_path' => $this->credentialsFile, 'project_id' => null]]);
    }

    private function event(): Event
    {
        return Event::withoutEvents(fn () => Event::create([
            'slug' => 'wordcamp-test',
            'display_name' => 'WordCamp Test',
            'source_site_url' => 'https://wordcamp-test.wordcamp.org/2026',
            'status' => 'active',
            'is_visible' => true,
            'starts_on' => '2026-10-04',
            'ends_on' => '2026-10-04',
            'timezone' => 'Asia/Kolkata',
            'country_code' => 'IN',
        ]));
    }

    private function token(Event $event, string $suffix): NativePushToken
    {
        return NativePushToken::create(['event_id' => $event->id, 'device_id' => 'dev-'.$suffix, 'platform' => 'android', 'token' => 'fcm-token-'.str_repeat($suffix, 30)]);
    }

    private const PAYLOAD = ['title' => 'Thank you', 'body' => 'How was it?', 'url' => 'https://campbuddy.club/event/wordcamp-test?thanks=1'];

    public function test_nothing_is_sent_and_nothing_breaks_without_credentials(): void
    {
        $event = $this->event();
        $this->token($event, 'a');
        config(['services.fcm' => ['credentials_path' => null, 'project_id' => null]]);

        $this->assertFalse(NativePush::enabled());
        $this->assertSame(0, NativePush::toEvent($event, self::PAYLOAD, 'thank-you', 3600));
        $this->assertSame(1, NativePushToken::count());
    }

    public function test_each_install_gets_the_message_and_the_gone_ones_are_removed(): void
    {
        $event = $this->event();
        $this->token($event, 'a');
        $gone = $this->token($event, 'b');
        $this->token($event, 'c');
        $this->fcm([new Response(200, [], '{}'), new Response(404, [], '{"error":{"status":"NOT_FOUND"}}'), new Response(200, [], '{}')]);

        $sent = NativePush::toEvent($event, self::PAYLOAD, 'thank-you', 3600);

        $this->assertSame(2, $sent);
        $this->assertNull(NativePushToken::find($gone->id));
        $this->assertSame(2, NativePushToken::count());

        // One call for the access token, three for the installs.
        $this->assertCount(4, $this->history);
        $this->assertSame('https://oauth2.googleapis.com/token', (string) $this->history[0]['request']->getUri());
        $send = $this->history[1]['request'];
        $this->assertSame('https://fcm.googleapis.com/v1/projects/campbuddy-test/messages:send', (string) $send->getUri());
        $this->assertSame('Bearer ya29.test', $send->getHeaderLine('Authorization'));

        $message = json_decode((string) $send->getBody(), true)['message'];
        $this->assertSame('Thank you', $message['notification']['title']);
        $this->assertSame(self::PAYLOAD['url'], $message['data']['url']);
        $this->assertSame('thank-you', $message['data']['kind']);
    }

    public function test_the_access_token_is_asked_for_once_and_reused(): void
    {
        $event = $this->event();
        $this->token($event, 'a');
        $this->fcm([new Response(200, [], '{}'), new Response(200, [], '{}')]);

        NativePush::toEvent($event, self::PAYLOAD, 'one', 3600);
        NativePush::toEvent($event, self::PAYLOAD, 'two', 3600);

        $tokenCalls = collect($this->history)->filter(fn ($h) => str_contains((string) $h['request']->getUri(), 'oauth2'))->count();
        $this->assertSame(1, $tokenCalls);
    }

    public function test_pushes_that_need_something_on_the_phone_stay_web_only(): void
    {
        $event = $this->event();
        $this->token($event, 'a');
        $this->fcm([new Response(200, [], '{}')]);

        $this->assertSame(0, NativePush::toEvent($event, self::PAYLOAD + ['needs' => 'bring'], 'move-data', 3600));
        $this->assertCount(0, $this->history);
    }

    public function test_the_feature_switch_turns_native_push_off(): void
    {
        $event = $this->event();
        $this->token($event, 'a');
        $this->fcm([new Response(200, [], '{}')]);
        config(['mobile_app.features.native_push' => false]);

        $this->assertSame(0, NativePush::toEvent($event, self::PAYLOAD, 'thank-you', 3600));
        $this->assertCount(0, $this->history);
    }

    public function test_event_push_counts_app_installs_with_web_phones(): void
    {
        $event = $this->event();
        $this->token($event, 'a');
        $this->fcm([new Response(200, [], '{}')]);
        config(['services.vapid' => ['subject' => 'mailto:test@example.com', 'public_key' => 'BM_hD8Ox0zWMrOtvvXHVl2PpzSL_jvcW7c5ixy1SXlwKASmhCr0ayk6GQmfqDcWyKx9YYWkih-SM7mC9kTYt_fo', 'private_key' => 'AULfVYPjtSQFR4LPspAb4868ogRmAGiZc7asCBe5LvU']]);

        $this->assertSame(1, EventPush::send($event, self::PAYLOAD, 'save-data', 3600));
    }

    public function test_the_upcoming_command_tells_app_installs_about_the_next_wordcamp_in_their_country(): void
    {
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-10T06:30:00Z'));
        $past = $this->event();
        $next = Event::withoutEvents(fn () => Event::create([
            'slug' => 'wordcamp-delhi', 'display_name' => 'WordCamp Delhi', 'source_site_url' => 'https://delhi.wordcamp.org/2026',
            'status' => 'active', 'is_visible' => true, 'starts_on' => '2026-11-14', 'ends_on' => '2026-11-15',
            'timezone' => 'Asia/Kolkata', 'country_code' => 'IN',
        ]));
        $this->token($past, 'a');
        $this->fcm([new Response(200, [], '{}')]);

        $this->artisan('campbuddy:upcoming-push', ['--yes' => true])->assertSuccessful();

        $fcmCalls = collect($this->history)->filter(fn ($h) => str_contains((string) $h['request']->getUri(), 'messages:send'));
        $this->assertCount(1, $fcmCalls);
        $message = json_decode((string) $fcmCalls->first()['request']->getBody(), true)['message'];
        $this->assertStringContainsString('WordCamp Delhi', $message['notification']['title']);
        $this->assertSame('/event/'.$next->slug, $message['data']['url']);
    }

    public function test_the_export_command_reaches_app_installs_too(): void
    {
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-10T06:30:00Z'));
        $event = $this->event();
        $this->token($event, 'a');
        $this->fcm([new Response(200, [], '{}')]);

        $this->artisan('campbuddy:export-push', ['--yes' => true])->assertSuccessful();

        $fcmCalls = collect($this->history)->filter(fn ($h) => str_contains((string) $h['request']->getUri(), 'messages:send'));
        $this->assertCount(1, $fcmCalls);
    }
}
