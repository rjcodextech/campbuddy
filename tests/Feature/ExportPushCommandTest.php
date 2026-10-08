<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\PushSubscription;
use Carbon\CarbonImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * campbuddy:export-push — one "save your data" push per phone, about the
 * latest WordCamp it was at that ended in the past week.
 */
class ExportPushCommandTest extends TestCase
{
    use RefreshDatabase;

    // Throwaway keys (same as SessionRemindersTest).
    private const VAPID_PUBLIC = 'BM_hD8Ox0zWMrOtvvXHVl2PpzSL_jvcW7c5ixy1SXlwKASmhCr0ayk6GQmfqDcWyKx9YYWkih-SM7mC9kTYt_fo';

    private const VAPID_PRIVATE = 'AULfVYPjtSQFR4LPspAb4868ogRmAGiZc7asCBe5LvU';

    private const P256DH = 'BB7uFWhMjz8fkzCOi2BiE0LeMo_tsF561P9bUp-9FYMrRzkCrLWKHkpP5J0JDDx588DToAnhGE5AXduw_EeVH0c';

    private const AUTH = 'aYKRF_SWLrZhFgJMZ5Gixw';

    private array $history = [];

    protected function setUp(): void
    {
        parent::setUp();

        // 3 a.m. in India on 10 Oct 2026: the command ignores venue hours.
        $this->travelTo(CarbonImmutable::parse('2026-10-09T21:30:00Z'));

        $stack = HandlerStack::create(fn () => new FulfilledPromise(new Response(201)));
        $stack->push(Middleware::history($this->history));
        $this->app->instance(Client::class, new Client(['handler' => $stack]));
        config(['services.vapid' => ['subject' => 'mailto:test@example.com', 'public_key' => self::VAPID_PUBLIC, 'private_key' => self::VAPID_PRIVATE]]);
    }

    private function event(string $slug, string $endsOn): Event
    {
        return Event::withoutEvents(fn () => Event::create([
            'slug' => $slug,
            'display_name' => ucwords(str_replace('-', ' ', $slug)),
            'source_site_url' => "https://{$slug}.wordcamp.org/2026",
            'status' => 'active',
            'is_visible' => true,
            'starts_on' => $endsOn,
            'ends_on' => $endsOn,
            'timezone' => 'Asia/Kolkata',
            'country_code' => 'IN',
        ]));
    }

    private function subscribe(Event $event, string $device, ?string $endpoint = null): void
    {
        PushSubscription::create(['event_id' => $event->id, 'device_id' => $device, 'endpoint' => 'https://push.example.test/send/'.($endpoint ?? $device), 'p256dh_key' => self::P256DH, 'auth_key' => self::AUTH]);
    }

    private function sentTo(): array
    {
        return collect($this->history)->map(fn ($h) => (string) $h['request']->getUri())->sort()->values()->all();
    }

    public function test_each_phone_gets_one_push_about_its_latest_wordcamp_in_the_past_week(): void
    {
        $older = $this->event('wordcamp-older', '2026-10-04');
        $newer = $this->event('wordcamp-newer', '2026-10-08');
        $old = $this->event('wordcamp-too-old', '2026-10-01');
        $live = $this->event('wordcamp-today', '2026-10-10');

        $this->subscribe($older, 'a', 'a-older');
        $this->subscribe($newer, 'a', 'a-newer');
        $this->subscribe($older, 'b');
        $this->subscribe($old, 'c');
        $this->subscribe($live, 'd');

        $this->artisan('campbuddy:export-push', ['--yes' => true])->assertSuccessful();

        $this->assertSame([
            'https://push.example.test/send/a-newer',
            'https://push.example.test/send/b',
        ], $this->sentTo());
        $this->assertNull($older->fresh()->thank_you_sent_at);

        // Forced: a second run sends again.
        $this->artisan('campbuddy:export-push', ['--yes' => true])->assertSuccessful();
        $this->assertCount(4, $this->history);
    }

    public function test_dry_run_and_event_filter(): void
    {
        $one = $this->event('wordcamp-one', '2026-10-08');
        $two = $this->event('wordcamp-two', '2026-10-07');
        $this->subscribe($one, 'a');
        $this->subscribe($two, 'b');

        $this->artisan('campbuddy:export-push', ['--dry-run' => true])->assertSuccessful();
        $this->assertCount(0, $this->history);

        $this->artisan('campbuddy:export-push', ['--event' => 'wordcamp-two', '--yes' => true])->assertSuccessful();
        $this->assertSame(['https://push.example.test/send/b'], $this->sentTo());
    }

    public function test_it_asks_before_sending(): void
    {
        $this->subscribe($this->event('wordcamp-one', '2026-10-08'), 'a');

        $this->artisan('campbuddy:export-push')
            ->expectsConfirmation('Send 1 push(es) now?', 'no')
            ->assertFailed();

        $this->assertCount(0, $this->history);
    }
}
