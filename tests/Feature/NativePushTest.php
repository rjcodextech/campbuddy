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
