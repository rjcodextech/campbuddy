<?php

namespace Tests\Feature;

use App\Jobs\EvaluateEventLifecycleJob;
use App\Jobs\SendThankYouPushJob;
use App\Models\Event;
use App\Models\EventFeedback;
use App\Models\PushSubscription;
use App\Models\User;
use App\Support\EventTime;
use Carbon\CarbonImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * After a WordCamp (6 Oct 2026): it stays a week as "Completed" on the picker
 * and in the app (retention 7 days), a thank-you card asks for a rating from
 * day 3 (push once, to phones with reminders on), and feedback lands in admin.
 */
class AfterEventTest extends TestCase
{
    use RefreshDatabase;

    // Throwaway keys (same as SessionRemindersTest).
    private const VAPID_PUBLIC = 'BM_hD8Ox0zWMrOtvvXHVl2PpzSL_jvcW7c5ixy1SXlwKASmhCr0ayk6GQmfqDcWyKx9YYWkih-SM7mC9kTYt_fo';

    private const VAPID_PRIVATE = 'AULfVYPjtSQFR4LPspAb4868ogRmAGiZc7asCBe5LvU';

    private const P256DH = 'BB7uFWhMjz8fkzCOi2BiE0LeMo_tsF561P9bUp-9FYMrRzkCrLWKHkpP5J0JDDx588DToAnhGE5AXduw_EeVH0c';

    private const AUTH = 'aYKRF_SWLrZhFgJMZ5Gixw';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        // Noon in India on 10 Oct 2026.
        $this->travelTo(CarbonImmutable::parse('2026-10-10T06:30:00Z'));
    }

    private function event(string $slug, string $endsOn, array $attributes = []): Event
    {
        return Event::withoutEvents(fn () => Event::create($attributes + [
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

    public function test_the_retention_week_is_seven_days_and_the_card_comes_on_day_three(): void
    {
        $this->assertSame(7, EventTime::RETENTION_DAYS);
        $this->assertSame(3, EventTime::THANK_YOU_AFTER_DAYS);

        $event = $this->event('wordcamp-done', '2026-10-07');
        $this->assertSame(3, EventTime::daysSinceEnd($event));
        $this->assertNull(EventTime::daysSinceEnd($this->event('wordcamp-today', '2026-10-10')));
    }

    public function test_the_picker_lists_finished_wordcamps_for_a_week_as_completed_after_the_upcoming_ones(): void
    {
        $this->event('wordcamp-next', '2026-11-01');
        $this->event('wordcamp-done', '2026-10-05');       // 5 days ago: still listed
        $this->event('wordcamp-just-done', '2026-10-08');  // 2 days ago: listed first among the finished
        $this->event('wordcamp-long-gone', '2026-10-01');  // 9 days ago: gone

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('Wordcamp Long Gone', $html);
        $next = strpos($html, 'Wordcamp Next');
        $justDone = strpos($html, 'Wordcamp Just Done');
        $done = strpos($html, 'Wordcamp Done');
        $this->assertTrue($next < $justDone && $justDone < $done, 'upcoming first, then the most recently finished');
        $this->assertSame(2, substr_count($html, 'event-card--completed'));
        $this->assertStringContainsString('Completed', $html);
        // Each completed card brings its thank-you card along.
        $this->assertSame(2, substr_count($html, 'data-thank-you'."\n") + substr_count($html, 'data-thank-you '));
    }

    public function test_a_finished_wordcamp_still_opens_and_carries_the_thank_you_card(): void
    {
        $this->event('wordcamp-done', '2026-10-05');
        $this->event('wordcamp-next', '2026-11-01');

        $this->get('/event/wordcamp-done')
            ->assertOk()
            ->assertSee('Thank you for being at Wordcamp Done')
            ->assertSee('Save my day as PDF')
            ->assertSee('1 more coming up in India');

        // Not before it's over.
        $this->get('/event/wordcamp-next')->assertOk()->assertDontSee('Thank you for being at');
    }

    public function test_it_is_archived_only_after_the_week(): void
    {
        $kept = $this->event('wordcamp-kept', '2026-10-04');   // 6 days ago
        $gone = $this->event('wordcamp-gone', '2026-10-02');   // 8 days ago

        (new EvaluateEventLifecycleJob)->handle();

        $this->assertSame('active', $kept->fresh()->status);
        $this->assertSame('archived', $gone->fresh()->status);
    }

    public function test_feedback_is_taken_once_it_is_over_one_per_phone(): void
    {
        $this->event('wordcamp-done', '2026-10-07');
        $this->event('wordcamp-next', '2026-11-01');

        $this->postJson('/api/v1/events/wordcamp-next/feedback', ['device_id' => 'phone-1', 'rating' => 5])->assertStatus(422);

        $this->postJson('/api/v1/events/wordcamp-done/feedback', ['device_id' => 'phone-1', 'rating' => 4, 'comment' => '  Loved My Day  '])->assertCreated();
        $this->postJson('/api/v1/events/wordcamp-done/feedback', ['device_id' => 'phone-1', 'rating' => 5])->assertCreated();
        $this->postJson('/api/v1/events/wordcamp-done/feedback', ['device_id' => 'phone-2', 'rating' => 2, 'website' => 'spam.example'])->assertCreated();
        $this->postJson('/api/v1/events/wordcamp-done/feedback', ['device_id' => 'phone-3', 'rating' => 9])->assertStatus(422);

        $this->assertSame(1, EventFeedback::count());
        $answer = EventFeedback::first();
        $this->assertSame(5, $answer->rating);
        $this->assertNull($answer->comment);
        $this->assertNotSame('phone-1', $answer->device_hash);
        $this->assertSame(64, strlen($answer->device_hash));
    }

    public function test_admin_sees_feedback_by_wordcamp(): void
    {
        $event = $this->event('wordcamp-done', '2026-10-07');
        EventFeedback::create(['event_id' => $event->id, 'device_hash' => str_repeat('a', 64), 'rating' => 5, 'comment' => 'Great app']);
        EventFeedback::create(['event_id' => $event->id, 'device_hash' => str_repeat('b', 64), 'rating' => 4]);

        $this->get('/admin/feedback')->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())
            ->get('/admin/feedback')
            ->assertOk()
            ->assertSee('Wordcamp Done')
            ->assertSee('4.5')
            ->assertSee('Great app');
    }

    private function pushService(array &$history): void
    {
        $stack = HandlerStack::create(fn () => new FulfilledPromise(new Response(201)));
        $stack->push(Middleware::history($history));
        $this->app->instance(Client::class, new Client(['handler' => $stack]));
        config(['services.vapid' => ['subject' => 'mailto:test@example.com', 'public_key' => self::VAPID_PUBLIC, 'private_key' => self::VAPID_PRIVATE]]);
    }

    private function subscribe(Event $event, string $device): void
    {
        PushSubscription::create(['event_id' => $event->id, 'device_id' => $device, 'endpoint' => 'https://push.example.test/send/'.$device, 'p256dh_key' => self::P256DH, 'auth_key' => self::AUTH]);
    }

    public function test_the_thank_you_push_goes_once_on_day_three_in_the_venues_daytime(): void
    {
        $history = [];
        $this->pushService($history);

        $day3 = $this->event('wordcamp-day3', '2026-10-07');
        $day2 = $this->event('wordcamp-day2', '2026-10-08');
        $this->subscribe($day3, 'a');
        $this->subscribe($day3, 'b');
        $this->subscribe($day2, 'c');

        // 3 a.m. at the venue: nothing yet.
        $this->travelTo(CarbonImmutable::parse('2026-10-09T21:30:00Z'));
        (new SendThankYouPushJob)->handle();
        $this->assertCount(0, $history);

        // Noon at the venue on day 3: both phones, once.
        $this->travelTo(CarbonImmutable::parse('2026-10-10T06:30:00Z'));
        (new SendThankYouPushJob)->handle();
        (new SendThankYouPushJob)->handle();

        $this->assertCount(2, $history);
        $this->assertNotNull($day3->fresh()->thank_you_sent_at);
        $this->assertNull($day2->fresh()->thank_you_sent_at);
        $this->assertSame(['https://push.example.test/send/a', 'https://push.example.test/send/b'], collect($history)->map(fn ($h) => (string) $h['request']->getUri())->sort()->values()->all());
    }

    public function test_a_wordcamp_past_its_week_gets_no_push(): void
    {
        $history = [];
        $this->pushService($history);

        $old = $this->event('wordcamp-old', '2026-10-01');
        $this->subscribe($old, 'a');

        (new SendThankYouPushJob)->handle();

        $this->assertCount(0, $history);
        $this->assertNull($old->fresh()->thank_you_sent_at);
    }
}
