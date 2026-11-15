<?php

namespace Tests\Feature\Adversarial;

use App\Models\Event;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Rate limits (OWASP API4): they must apply, apply to the right party, and not
 * be a lever for one person to lock out others. At a WordCamp the organizers sit
 * on the same venue wifi — the same address — as a few thousand attendees, so a
 * limit counted per address on the organizer side, or one counter shared by
 * unrelated limits, lets anyone in the room stop the organizers working.
 */
class ApiRateLimitTest extends TestCase
{
    use ApiFixtures;
    use RefreshDatabase;

    private Event $event;

    private const VENUE_WIFI = '203.0.113.50';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->travelTo(CarbonImmutable::parse('2026-11-20T06:00:00Z'));
        config(['app.debug' => false]);
        Http::fake();
        Queue::fake();

        $this->event = $this->makeEvent();
    }

    private function fromVenue(): static
    {
        return $this->withServerVariables(['REMOTE_ADDR' => self::VENUE_WIFI]);
    }

    private function questUrl(): string
    {
        return "/api/v1/app/organizer/events/{$this->event->id}/quests";
    }

    public function test_a_throttled_answer_is_json_with_retry_after_and_no_debug_detail(): void
    {
        config(['campbuddy.rate_limits.anonymous_reads' => 3]);

        for ($i = 0; $i < 3; $i++) {
            $this->getJson('/api/v1/health')->assertOk();
        }
        $refused = $this->getJson('/api/v1/health')->assertStatus(429);

        $refused->assertHeader('Retry-After');
        $this->assertStringContainsString('application/json', (string) $refused->headers->get('Content-Type'));
        $this->assertIsString($refused->json('message'));
        $this->assertNoDebugLeak($refused);
        $this->assertNotSame('', (string) $refused->headers->get('X-Content-Type-Options'));

        // Without Accept: application/json the API still answers in JSON.
        $plain = $this->get('/api/v1/health')->assertStatus(429);
        $this->assertStringContainsString('application/json', (string) $plain->headers->get('Content-Type'));
    }

    public function test_attendee_writes_are_limited_per_phone_and_the_address_backstop_still_applies(): void
    {
        config(['campbuddy.rate_limits.device_writes' => 5, 'campbuddy.rate_limits.address_writes' => 8]);
        $phone = $this->phone();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson(route('api.events.seen', $this->event), ['device_id' => 'device-0001'], $phone)->assertNoContent();
        }
        $this->postJson(route('api.events.seen', $this->event), ['device_id' => 'device-0001'], $phone)->assertStatus(429);

        // Another phone on the same address is fine — until the address backstop.
        for ($i = 0; $i < 3; $i++) {
            $this->postJson(route('api.events.seen', $this->event), ['device_id' => 'device-0002'], $this->phone())->assertNoContent();
        }
        $this->postJson(route('api.events.seen', $this->event), ['device_id' => 'device-0003'], $this->phone())->assertStatus(429);
    }

    public function test_bookmarks_get_their_own_roomier_counter_and_do_not_eat_the_write_allowance(): void
    {
        config(['campbuddy.rate_limits.device_writes' => 3]);
        $phone = $this->phone();

        // Six reminders (twice the write allowance) in a quick burst…
        for ($i = 1; $i <= 6; $i++) {
            $this->postJson(route('api.bookmarks.store', $this->event), ['device_id' => 'device-1', 'session_id' => $i], $phone)->assertCreated();
        }
        $this->postJson(route('api.bookmarks.store', $this->event), ['device_id' => 'device-1', 'session_id' => 99], $phone)->assertStatus(429);

        // …and the phone can still do its ordinary writes.
        $this->postJson(route('api.events.seen', $this->event), ['device_id' => 'device-0001'], $phone)->assertNoContent();
    }

    public function test_anyone_on_the_venue_wifi_cannot_use_up_the_organizers_announcement_limit(): void
    {
        $admin = User::factory()->create();
        $token = $this->organizerToken('admin', $admin->id);

        // An attendee on the same wifi hammers the announcement endpoint without a sign-in.
        for ($i = 0; $i < 15; $i++) {
            // Refused — and, past ten, throttled themselves.
            $this->assertContains($this->fromVenue()->postJson("/api/v1/app/organizer/events/{$this->event->id}/broadcasts", ['kind' => 'custom'], $this->phone())->getStatusCode(), [401, 429]);
        }

        $status = $this->fromVenue()->postJson("/api/v1/app/organizer/events/{$this->event->id}/broadcasts", ['kind' => 'custom'], $this->bearer($token))->getStatusCode();
        $this->assertNotSame(429, $status, 'the organizer was throttled by someone else\'s requests');
    }

    public function test_anyone_on_the_venue_wifi_cannot_use_up_the_organizers_save_limit(): void
    {
        $manager = $this->managerOf($this->event);
        $token = $this->organizerToken('manager', $manager->id);

        for ($i = 0; $i < 65; $i++) {
            $this->fromVenue()->postJson($this->questUrl(), ['title' => 'x'], $this->phone());
        }

        $this->fromVenue()->postJson($this->questUrl(), ['title' => 'Find the photo booth'], $this->bearer($token))->assertCreated();
    }

    public function test_one_organizers_saves_do_not_throttle_another_organizer_on_the_same_wifi(): void
    {
        $busy = $this->managerOf($this->event);
        $other = $this->managerOf($this->event);
        $busyToken = $this->organizerToken('manager', $busy->id);

        for ($i = 0; $i < 60; $i++) {
            $this->fromVenue()->postJson($this->questUrl(), ['title' => "Item {$i}"], $this->bearer($busyToken))->assertCreated();
        }
        $this->fromVenue()->postJson($this->questUrl(), ['title' => 'One too many'], $this->bearer($busyToken))->assertStatus(429);

        $this->fromVenue()->postJson($this->questUrl(), ['title' => 'Mine'], $this->bearer($this->organizerToken('manager', $other->id)))->assertCreated();
    }

    public function test_ordinary_saves_do_not_count_against_the_tighter_per_action_limits(): void
    {
        $admin = User::factory()->create();
        $auth = $this->bearer($this->organizerToken('admin', $admin->id));

        for ($i = 0; $i < 12; $i++) {
            $this->postJson($this->questUrl(), ['title' => "Item {$i}"], $auth)->assertCreated();
        }

        // 10 a minute for "re-fetch" is its own budget, not what's left of the saves.
        $status = $this->postJson("/api/v1/app/organizer/events/{$this->event->id}/fetch/schedule", [], $auth)->getStatusCode();
        $this->assertNotSame(429, $status);
    }

    public function test_the_tighter_per_action_limits_still_apply(): void
    {
        $admin = User::factory()->create();
        $auth = $this->bearer($this->organizerToken('admin', $admin->id));

        for ($i = 0; $i < 10; $i++) {
            $this->assertNotSame(429, $this->postJson("/api/v1/app/organizer/events/{$this->event->id}/fetch/schedule", [], $auth)->getStatusCode());
        }
        $this->postJson("/api/v1/app/organizer/events/{$this->event->id}/fetch/schedule", [], $auth)->assertStatus(429);
    }

    public function test_attendees_adding_sessions_to_their_calendars_do_not_lock_the_organizers_out_of_signing_in(): void
    {
        $ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nEND:VCALENDAR";

        for ($i = 0; $i < 25; $i++) {
            $this->fromVenue()->post('/calendar.ics', ['ics' => $ics]);
        }

        User::factory()->create(['email' => 'lead@example.com']);
        $status = $this->fromVenue()->postJson('/api/v1/app/auth/login', ['email' => 'lead@example.com', 'password' => 'password', 'role' => 'admin'])->getStatusCode();
        $this->assertSame(200, $status, 'calendar downloads used up the sign-in limit');
    }

    public function test_organizer_sign_in_is_still_limited_per_address_and_per_account(): void
    {
        User::factory()->create(['email' => 'lead@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->fromVenue()->postJson('/api/v1/app/auth/login', ['email' => 'lead@example.com', 'password' => 'wrong-password', 'role' => 'admin'])->assertUnprocessable();
        }
        $this->fromVenue()->postJson('/api/v1/app/auth/login', ['email' => 'lead@example.com', 'password' => 'password', 'role' => 'admin'])->assertStatus(429);

        for ($i = 0; $i < 14; $i++) {
            $this->fromVenue()->postJson('/api/v1/app/auth/login', ['email' => "guess{$i}@example.com", 'password' => 'x', 'role' => 'admin']);
        }
        $this->fromVenue()->postJson('/api/v1/app/auth/login', ['email' => 'other@example.com', 'password' => 'x', 'role' => 'admin'])->assertStatus(429);
    }
}
