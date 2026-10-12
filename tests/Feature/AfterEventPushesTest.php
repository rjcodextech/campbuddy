<?php

namespace Tests\Feature;

use App\Jobs\SendAfterEventPushesJob;
use App\Jobs\SendThankYouPushJob;
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
 * After a WordCamp (12 Oct 2026): the day-3 thank-you push (as before), a
 * day-7 "save your data" push and a day-15 "take it with you" push — each
 * once per event, in the venue's daytime, within the month of retention —
 * and the "Take it with you" sheet the day-15 push opens.
 */
class AfterEventPushesTest extends TestCase
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
        $this->withoutVite();
        $stack = HandlerStack::create(fn () => new FulfilledPromise(new Response(201)));
        $stack->push(Middleware::history($this->history));
        $this->app->instance(Client::class, new Client(['handler' => $stack]));
        config(['services.vapid' => ['subject' => 'mailto:test@example.com', 'public_key' => self::VAPID_PUBLIC, 'private_key' => self::VAPID_PRIVATE]]);
    }

    private function event(string $slug, string $endsOn): Event
    {
        $event = Event::withoutEvents(fn () => Event::create([
            'slug' => $slug, 'display_name' => ucwords(str_replace('-', ' ', $slug)), 'source_site_url' => "https://{$slug}.wordcamp.org/2026",
            'status' => 'active', 'is_visible' => true, 'starts_on' => $endsOn, 'ends_on' => $endsOn, 'timezone' => 'Asia/Kolkata',
        ]));
        PushSubscription::create(['event_id' => $event->id, 'device_id' => $slug, 'endpoint' => "https://push.example.test/send/{$slug}", 'p256dh_key' => self::P256DH, 'auth_key' => self::AUTH]);

        return $event;
    }

    /** Noon in India on the given day. */
    private function noonOn(string $day): void
    {
        $this->travelTo(CarbonImmutable::parse("{$day} 12:00", 'Asia/Kolkata'));
    }

    public function test_day_seven_asks_to_save_the_data_once_and_day_fifteen_to_take_it_along(): void
    {
        $event = $this->event('wordcamp-done', '2026-10-01');

        // Day 6: nothing yet.
        $this->noonOn('2026-10-07');
        (new SendAfterEventPushesJob)->handle();
        $this->assertCount(0, $this->history);

        // Day 7, 3 a.m. at the venue: still nothing.
        $this->travelTo(CarbonImmutable::parse('2026-10-08 03:00', 'Asia/Kolkata'));
        (new SendAfterEventPushesJob)->handle();
        $this->assertCount(0, $this->history);

        // Day 7 at noon: "save your data", once.
        $this->noonOn('2026-10-08');
        (new SendAfterEventPushesJob)->handle();
        (new SendAfterEventPushesJob)->handle();
        $this->assertCount(1, $this->history);
        $this->assertNotNull($event->fresh()->save_data_sent_at);
        $this->assertNull($event->fresh()->move_data_sent_at);
        $this->assertSame(1, $event->fresh()->push_log['save-data']['phones']);

        // Day 15: "take it with you", once.
        $this->noonOn('2026-10-16');
        (new SendAfterEventPushesJob)->handle();
        (new SendAfterEventPushesJob)->handle();
        $this->assertCount(2, $this->history);
        $this->assertNotNull($event->fresh()->move_data_sent_at);
        $this->assertSame(1, $event->fresh()->push_log['move-data']['phones']);
    }

    public function test_an_event_first_seen_late_gets_only_the_latest_push_and_none_after_the_month(): void
    {
        $late = $this->event('wordcamp-late', '2026-09-20');   // day 20 on 10 Oct
        $gone = $this->event('wordcamp-gone', '2026-09-01');   // day 39
        $this->noonOn('2026-10-10');

        (new SendAfterEventPushesJob)->handle();

        $this->assertCount(1, $this->history, 'only the day-15 push, not both at once');
        $this->assertNotNull($late->fresh()->move_data_sent_at);
        $this->assertNotNull($late->fresh()->save_data_sent_at, 'the day-7 one is not sent afterwards either');
        $this->assertNull($gone->fresh()->move_data_sent_at);

        (new SendAfterEventPushesJob)->handle();
        $this->assertCount(1, $this->history);
    }

    public function test_the_day_three_thank_you_push_is_logged_too(): void
    {
        $event = $this->event('wordcamp-three', '2026-10-07');
        $this->noonOn('2026-10-10');

        (new SendThankYouPushJob)->handle();

        $this->assertSame(1, $event->fresh()->push_log['thank-you']['phones']);
    }

    public function test_a_finished_events_pages_carry_the_take_it_with_you_sheet(): void
    {
        $event = $this->event('wordcamp-done', '2026-10-01');
        $this->noonOn('2026-10-16');

        $this->get('/event/wordcamp-done?move=1')->assertOk()
            ->assertSee('data-move-data', false)
            ->assertSee('Going to another WordCamp?')
            ->assertDontSee('Got a new phone?')
            ->assertSee('Save my day as PDF')
            ->assertSee('data-open-move', false);

        // Before it's over there's nothing to take along.
        $this->event('wordcamp-soon', '2026-11-20');
        $this->get('/event/wordcamp-soon')->assertOk()->assertDontSee('data-move-data', false);
    }

    public function test_it_runs_every_hour(): void
    {
        $event = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->first(fn ($e) => $e->description === 'send-after-event-pushes');
        $this->assertSame('10 * * * *', $event?->expression);
    }
}
