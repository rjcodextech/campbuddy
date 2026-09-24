<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * API limits are counted per phone, so a venue's shared wifi — or a mobile
 * network that puts many phones behind one address — doesn't throttle
 * everyone together, while one runaway client still is. And whatever else
 * is going on, one person can always add and wave at at least ten people.
 */
class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::withoutEvents(fn () => Event::create([
            'slug' => 'wc-test', 'display_name' => 'WordCamp Test 2026', 'source_site_url' => 'https://test.wordcamp.org/2026',
            'status' => 'active', 'is_visible' => true,
        ]));
    }

    private function phone(?string $id = null): array
    {
        return ['X-CampBuddy-Device' => $id ?? (string) Str::uuid()];
    }

    private function join(array $headers = [], array $body = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson(route('api.discovery.store', $this->event), $body + ['tags' => ['business owner']], $headers);
    }

    public function test_a_whole_venue_on_one_wifi_can_join_at_once(): void
    {
        // 150 different phones behind one address, all joining within a minute.
        for ($i = 0; $i < 150; $i++) {
            $this->join($this->phone())->assertCreated();
        }
    }

    public function test_one_runaway_phone_is_limited_without_affecting_its_neighbours(): void
    {
        $noisy = $this->phone();

        for ($i = 0; $i < 120; $i++) {
            $this->getJson(route('api.discovery.index', $this->event), $noisy)->assertOk();
        }
        $this->getJson(route('api.discovery.index', $this->event), $noisy)->assertStatus(429)->assertHeader('Retry-After');

        $this->getJson(route('api.discovery.index', $this->event), $this->phone())->assertOk();
    }

    public function test_one_person_can_add_and_wave_at_ten_friends_while_the_room_is_busy(): void
    {
        // A busy moment on the same wifi: lots of other phones joining and browsing.
        $friends = [];
        for ($i = 0; $i < 10; $i++) {
            $friends[] = $this->join($this->phone())->json('discovery_id');
        }
        for ($i = 0; $i < 200; $i++) {
            $this->getJson(route('api.discovery.index', $this->event), $this->phone())->assertOk();
        }

        $me = $this->phone();
        $card = $this->join($me)->assertCreated()->json();
        $auth = $me + ['Authorization' => 'Bearer '.$card['owner_token']];

        foreach ($friends as $friend) {
            $this->postJson(route('api.discovery.waves.store', [$this->event, $card['discovery_id']]), ['to' => $friend, 'name' => 'Asha'], $auth)->assertCreated();
            $this->getJson(route('api.discovery.waves.index', [$this->event, $card['discovery_id']]), $auth)->assertOk();
            $this->getJson(route('api.discovery.index', $this->event), $me)->assertOk();
        }

        $this->getJson(route('api.discovery.waves.index', [$this->event, $card['discovery_id']]), $auth)
            ->assertJsonCount(10, 'sent');
    }

    public function test_requests_that_say_nothing_about_the_phone_get_the_smaller_allowance(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->join()->assertCreated();
        }
        $this->join()->assertStatus(429);

        // A real app on the same address is unaffected.
        $this->join($this->phone())->assertCreated();
    }

    public function test_the_per_address_backstop_still_stops_rotating_ids(): void
    {
        config(['campbuddy.rate_limits.address_reads' => 30]);

        for ($i = 0; $i < 30; $i++) {
            $this->getJson('/api/v1/health', $this->phone())->assertOk();
        }
        $this->getJson('/api/v1/health', $this->phone())->assertStatus(429);
    }

    public function test_a_malformed_device_id_counts_as_anonymous(): void
    {
        config(['campbuddy.rate_limits.anonymous_reads' => 5]);

        for ($i = 0; $i < 5; $i++) {
            $this->getJson('/api/v1/health', ['X-CampBuddy-Device' => "fake-{$i}"])->assertOk();
        }
        $this->getJson('/api/v1/health', ['X-CampBuddy-Device' => 'fake-99'])->assertStatus(429);
    }
}
