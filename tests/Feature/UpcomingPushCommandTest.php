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
 * campbuddy:upcoming-push — each phone hears about the next WordCamp in its
 * own country (the country of the WordCamp it last followed).
 */
class UpcomingPushCommandTest extends TestCase
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

        // Noon in India on 10 Oct 2026.
        $this->travelTo(CarbonImmutable::parse('2026-10-10T06:30:00Z'));

        $stack = HandlerStack::create(fn () => new FulfilledPromise(new Response(201)));
        $stack->push(Middleware::history($this->history));
        $this->app->instance(Client::class, new Client(['handler' => $stack]));
        config(['services.vapid' => ['subject' => 'mailto:test@example.com', 'public_key' => self::VAPID_PUBLIC, 'private_key' => self::VAPID_PRIVATE]]);
    }

    private function event(string $slug, string $startsOn, string $country, array $attributes = []): Event
    {
        return Event::withoutEvents(fn () => Event::create($attributes + [
            'slug' => $slug,
            'display_name' => ucwords(str_replace('-', ' ', $slug)),
            'source_site_url' => "https://{$slug}.wordcamp.org/2026",
            'status' => 'active',
            'is_visible' => true,
            'starts_on' => $startsOn,
            'ends_on' => $startsOn,
            'timezone' => $country === 'IN' ? 'Asia/Kolkata' : 'Europe/Berlin',
            'country_code' => $country,
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

    public function test_each_phone_hears_about_the_next_wordcamp_in_its_own_country(): void
    {
        $pastIn = $this->event('wordcamp-past-in', '2026-10-04', 'IN');
        $pastDe = $this->event('wordcamp-past-de', '2026-10-04', 'DE');
        $nextIn = $this->event('wordcamp-next-in', '2026-11-01', 'IN');
        $this->event('wordcamp-later-in', '2026-11-20', 'IN');
        $this->event('wordcamp-today-in', '2026-10-10', 'IN');
        $this->event('wordcamp-hidden-in', '2026-10-20', 'IN', ['is_visible' => false]);
        $this->event('wordcamp-far-in', '2027-06-01', 'IN');

        $this->subscribe($pastIn, 'a');          // India → next-in
        $this->subscribe($pastIn, 'b');
        $this->subscribe($nextIn, 'b', 'b-next'); // already follows next-in → later-in
        $this->subscribe($pastDe, 'c');          // Germany: nothing upcoming there

        $this->artisan('campbuddy:upcoming-push', ['--yes' => true])->assertSuccessful();

        $this->assertSame([
            'https://push.example.test/send/a',
            'https://push.example.test/send/b',
            'https://push.example.test/send/b-next',
        ], $this->sentTo());
    }

    public function test_country_filter_dry_run_and_confirmation(): void
    {
        $pastIn = $this->event('wordcamp-past-in', '2026-10-04', 'IN');
        $pastDe = $this->event('wordcamp-past-de', '2026-10-04', 'DE');
        $this->event('wordcamp-next-in', '2026-11-01', 'IN');
        $this->event('wordcamp-next-de', '2026-11-01', 'DE');
        $this->subscribe($pastIn, 'a');
        $this->subscribe($pastDe, 'c');

        $this->artisan('campbuddy:upcoming-push', ['--dry-run' => true])->assertSuccessful();
        $this->assertCount(0, $this->history);

        $this->artisan('campbuddy:upcoming-push')
            ->expectsConfirmation('Send 2 push(es) now?', 'no')
            ->assertFailed();
        $this->assertCount(0, $this->history);

        $this->artisan('campbuddy:upcoming-push', ['--country' => 'de', '--yes' => true])->assertSuccessful();
        $this->assertSame(['https://push.example.test/send/c'], $this->sentTo());
    }

    public function test_a_named_wordcamp_goes_only_to_its_country(): void
    {
        $pastIn = $this->event('wordcamp-past-in', '2026-10-04', 'IN');
        $pastDe = $this->event('wordcamp-past-de', '2026-10-04', 'DE');
        $this->event('wordcamp-far-in', '2027-06-01', 'IN');
        $this->subscribe($pastIn, 'a');
        $this->subscribe($pastDe, 'c');

        // Beyond --within, but named: still sent, to India only.
        $this->artisan('campbuddy:upcoming-push', ['--event' => 'wordcamp-far-in', '--yes' => true])->assertSuccessful();
        $this->assertSame(['https://push.example.test/send/a'], $this->sentTo());
    }
}
