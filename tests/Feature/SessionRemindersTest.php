<?php

namespace Tests\Feature;

use App\Jobs\SendSessionRemindersJob;
use App\Models\Event;
use App\Models\PushSubscription;
use App\Models\SessionBookmark;
use App\Support\EventData;
use Carbon\CarbonImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The "starting soon" push. It must reach a whole crowd inside the 5–10 minute
 * window (many requests in flight, batches saved as they finish), never arrive
 * late (short TTL), and clean up subscriptions the push service says are gone.
 * Runs the real library — encryption, VAPID signing — against a fake push service.
 */
class SessionRemindersTest extends TestCase
{
    use RefreshDatabase;

    // Throwaway keys made for these tests only (a P-256 key pair, and what a browser's subscription carries).
    private const VAPID_PUBLIC = 'BM_hD8Ox0zWMrOtvvXHVl2PpzSL_jvcW7c5ixy1SXlwKASmhCr0ayk6GQmfqDcWyKx9YYWkih-SM7mC9kTYt_fo';

    private const VAPID_PRIVATE = 'AULfVYPjtSQFR4LPspAb4868ogRmAGiZc7asCBe5LvU';

    private const P256DH = 'BB7uFWhMjz8fkzCOi2BiE0LeMo_tsF561P9bUp-9FYMrRzkCrLWKHkpP5J0JDDx588DToAnhGE5AXduw_EeVH0c';

    private const AUTH = 'aYKRF_SWLrZhFgJMZ5Gixw';

    private Event $event;

    /** @var array<int, array{request: \Psr\Http\Message\RequestInterface}> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.vapid' => ['subject' => 'mailto:test@example.com', 'public_key' => self::VAPID_PUBLIC, 'private_key' => self::VAPID_PRIVATE]]);

        $this->travelTo(CarbonImmutable::parse('2026-10-10T09:00:00Z'));

        $this->event = Event::withoutEvents(fn () => Event::create([
            'slug' => 'wc-reminders', 'display_name' => 'WordCamp Reminders', 'source_site_url' => 'https://reminders.wordcamp.org/2026',
            'status' => 'active', 'is_visible' => true, 'starts_on' => '2026-10-10', 'ends_on' => '2026-10-10', 'timezone' => 'UTC',
        ]));

        EventData::put($this->event->id, 'sessions', [
            // Starts in 7 minutes: inside the 5–10 minute window.
            ['id' => 1, 'title' => 'Keynote', 'starts_at' => '2026-10-10T09:07:00+00:00', 'duration_seconds' => 3600, 'track_names' => ['Main Hall']],
            // Starts in 40 minutes: not yet.
            ['id' => 2, 'title' => 'Later talk', 'starts_at' => '2026-10-10T09:40:00+00:00', 'duration_seconds' => 3600],
        ]);
    }

    /** A fake push service: answers every push with the given status (or asks $answer per request). */
    private function pushService(int|callable $answer = 201): void
    {
        $history = [];
        $stack = HandlerStack::create(fn ($request) => new FulfilledPromise(new Response(is_callable($answer) ? $answer($request) : $answer)));
        $stack->push(Middleware::history($history));
        $this->sent = &$history;

        $this->app->instance(Client::class, new Client(['handler' => $stack]));
    }

    private function subscribe(string $device, string $endpoint = null, array $keys = []): PushSubscription
    {
        return PushSubscription::create([
            'event_id' => $this->event->id,
            'device_id' => $device,
            'endpoint' => $endpoint ?? 'https://push.example.test/send/'.$device,
            'p256dh_key' => $keys['p256dh'] ?? self::P256DH,
            'auth_key' => $keys['auth'] ?? self::AUTH,
        ]);
    }

    private function bookmark(string $device, int $session = 1, bool $reminder = true): SessionBookmark
    {
        return SessionBookmark::create(['event_id' => $this->event->id, 'device_id' => $device, 'session_id' => $session, 'reminder_enabled' => $reminder]);
    }

    private function run_(): void
    {
        (new SendSessionRemindersJob)->handle();
    }

    public function test_a_reminder_goes_out_with_a_short_ttl_and_high_urgency(): void
    {
        $this->pushService();
        $this->subscribe('phone-a');
        $bookmark = $this->bookmark('phone-a');

        $this->run_();

        $this->assertCount(1, $this->sent);
        $request = $this->sent[0]['request'];
        $this->assertSame('https://push.example.test/send/phone-a', (string) $request->getUri());
        $this->assertSame('600', $request->getHeaderLine('TTL'), 'a late "starting soon" is worse than none');
        $this->assertSame('high', $request->getHeaderLine('Urgency'));
        $this->assertNotSame('', $request->getHeaderLine('Topic'));
        $this->assertMatchesRegularExpression('/^(vapid|webpush) /i', $request->getHeaderLine('Authorization'), 'signed with our VAPID key');
        $this->assertNotNull($bookmark->fresh()->reminder_sent_at);
    }

    public function test_only_bookmarks_in_the_window_with_reminders_on_are_sent(): void
    {
        $this->pushService();
        foreach (['a', 'b', 'c'] as $device) {
            $this->subscribe($device);
        }
        $inWindow = $this->bookmark('a', 1);
        $later = $this->bookmark('b', 2);
        $off = $this->bookmark('c', 1, reminder: false);

        $this->run_();

        $this->assertCount(1, $this->sent);
        $this->assertNotNull($inWindow->fresh()->reminder_sent_at);
        $this->assertNull($later->fresh()->reminder_sent_at);
        $this->assertNull($off->fresh()->reminder_sent_at);
    }

    public function test_a_reminder_is_sent_once_however_often_the_job_runs(): void
    {
        $this->pushService();
        $this->subscribe('phone-a');
        $this->bookmark('phone-a');

        $this->run_();
        $this->run_();

        $this->assertCount(1, $this->sent);
    }

    public function test_a_bookmark_with_no_subscription_is_left_alone(): void
    {
        $this->pushService();
        $bookmark = $this->bookmark('no-push');

        $this->run_();

        $this->assertCount(0, $this->sent);
        $this->assertNull($bookmark->fresh()->reminder_sent_at);
    }

    public function test_a_gone_subscription_is_removed_and_the_others_still_get_theirs(): void
    {
        $this->pushService(fn ($request) => str_contains((string) $request->getUri(), 'gone') ? 410 : 201);
        $this->subscribe('phone-a', 'https://push.example.test/send/ok');
        $this->subscribe('phone-b', 'https://push.example.test/send/gone');
        $this->bookmark('phone-a');
        $this->bookmark('phone-b');

        $this->run_();

        $this->assertCount(2, $this->sent);
        $this->assertSame(['https://push.example.test/send/ok'], PushSubscription::pluck('endpoint')->all());
        $this->assertSame(2, SessionBookmark::whereNotNull('reminder_sent_at')->count());
    }

    public function test_a_push_service_error_keeps_the_subscription(): void
    {
        $this->pushService(503);
        $this->subscribe('phone-a');
        $this->bookmark('phone-a');

        $this->run_();

        $this->assertSame(1, PushSubscription::count(), 'a hiccup is not a reason to forget the phone');
    }

    public function test_a_subscription_with_broken_keys_cannot_stop_the_others(): void
    {
        $this->pushService();
        $this->subscribe('broken', 'https://push.example.test/send/broken', ['p256dh' => 'not-a-key', 'auth' => 'x']);
        $this->subscribe('fine');
        $this->bookmark('broken');
        $this->bookmark('fine');

        $this->run_();

        $this->assertCount(1, $this->sent);
        $this->assertSame('https://push.example.test/send/fine', (string) $this->sent[0]['request']->getUri());
        $this->assertSame(2, SessionBookmark::whereNotNull('reminder_sent_at')->count());
    }

    public function test_a_crowd_is_sent_in_batches_and_all_marked(): void
    {
        $this->pushService();

        // 450 phones bookmarked the keynote — more than two batches.
        for ($i = 0; $i < 450; $i++) {
            $this->subscribe("phone-{$i}");
            $this->bookmark("phone-{$i}");
        }

        $this->run_();

        $this->assertCount(450, $this->sent);
        $this->assertSame(450, SessionBookmark::whereNotNull('reminder_sent_at')->count());
        $this->assertCount(450, array_unique(array_map(fn ($h) => (string) $h['request']->getUri(), $this->sent)));
    }

    public function test_one_phone_with_two_bookmarks_in_the_window_gets_two_distinct_reminders(): void
    {
        $this->pushService();
        EventData::put($this->event->id, 'sessions', [
            ['id' => 1, 'title' => 'Keynote', 'starts_at' => '2026-10-10T09:07:00+00:00', 'duration_seconds' => 3600],
            ['id' => 3, 'title' => 'Workshop', 'starts_at' => '2026-10-10T09:08:00+00:00', 'duration_seconds' => 3600],
        ]);
        $this->subscribe('phone-a');
        $this->bookmark('phone-a', 1);
        $this->bookmark('phone-a', 3);

        $this->run_();

        $this->assertCount(2, $this->sent);
        $topics = array_map(fn ($h) => $h['request']->getHeaderLine('Topic'), $this->sent);
        $this->assertCount(2, array_unique($topics), 'a topic per session, so one reminder never replaces the other');
    }
}
