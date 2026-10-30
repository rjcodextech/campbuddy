<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The API handshake (VerifyApiHandshake): client hello, freshness with the server's time, and the
 * server's proof on every answer — without ever locking out the app versions and cached web pages
 * already on phones (they send none of it).
 */
class ApiHandshakeTest extends TestCase
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

    private function hello(array $extra = []): array
    {
        return $extra + ['X-CampBuddy-Client' => 'pwa', 'X-CampBuddy-Device' => (string) Str::uuid(), 'X-CampBuddy-Timestamp' => (string) time()];
    }

    private function join(array $headers): \Illuminate\Testing\TestResponse
    {
        return $this->postJson(route('api.discovery.store', $this->event), ['tags' => ['developer'], 'consent' => true], $headers);
    }

    public function test_every_api_answer_reads_and_writes_alike_and_is_signed_by_the_server(): void
    {
        // The bug this guards: the first version called isMethod() with an array — every /api/v1 call was a 500.
        $this->getJson('/api/v1/health', $this->hello())->assertOk()->assertHeader('X-CampBuddy-Handshake', 'v1')->assertHeader('X-CampBuddy-Server-Time');
        $this->getJson(route('api.discovery.index', $this->event), $this->hello())->assertOk();
        $this->join($this->hello())->assertCreated()->assertHeader('X-CampBuddy-Handshake', 'v1');
    }

    public function test_old_apps_and_cached_pages_without_any_handshake_still_work(): void
    {
        $this->getJson('/api/v1/health')->assertOk();
        $this->postJson(route('api.discovery.store', $this->event), ['tags' => ['developer']])->assertCreated();
    }

    public function test_a_wrong_clock_on_a_write_gets_the_server_time_to_retry_with(): void
    {
        $reply = $this->join($this->hello(['X-CampBuddy-Timestamp' => (string) (time() - 3600)]))
            ->assertStatus(401)
            ->assertHeader('X-CampBuddy-Handshake-Error', 'time-skew')
            ->assertJsonPath('error.code', 'HANDSHAKE_TIME_SKEW');

        $this->assertStringContainsString('no-store', (string) $reply->headers->get('Cache-Control'));
        $server = (int) $reply->json('error.server_time');
        $this->assertEqualsWithDelta(time(), $server, 2);
        $this->assertEqualsWithDelta(time(), (int) $reply->headers->get('X-CampBuddy-Server-Time'), 2);

        // The retry with the server's time goes through.
        $this->join($this->hello(['X-CampBuddy-Timestamp' => (string) $server]))->assertCreated();
    }

    public function test_reads_are_never_refused_for_the_clock(): void
    {
        // A phone a day behind can still read its schedule; only writes need a fresh time.
        $this->getJson('/api/v1/health', $this->hello(['X-CampBuddy-Timestamp' => (string) (time() - 86400)]))->assertOk();
    }

    public function test_malformed_handshake_values_are_refused(): void
    {
        $this->getJson('/api/v1/health', $this->hello(['X-CampBuddy-Client' => 'evil<script>']))->assertStatus(400)->assertJsonPath('error.code', 'INVALID_HANDSHAKE_CLIENT');
        $this->join($this->hello(['X-CampBuddy-Timestamp' => 'yesterday']))->assertStatus(400)->assertJsonPath('error.code', 'INVALID_HANDSHAKE_TIMESTAMP');
        $this->join($this->hello(['X-CampBuddy-Timestamp' => '1e10']))->assertStatus(400);
    }

    public function test_a_malformed_device_id_is_not_refused_it_only_counts_as_anonymous(): void
    {
        $this->getJson('/api/v1/health', $this->hello(['X-CampBuddy-Device' => 'not a uuid']))->assertOk();
    }

    public function test_strict_mode_requires_the_handshake_on_writes_only(): void
    {
        config(['campbuddy.handshake.strict' => true]);

        $this->postJson(route('api.discovery.store', $this->event), ['tags' => ['developer'], 'consent' => true])
            ->assertStatus(400)->assertJsonPath('error.code', 'HANDSHAKE_REQUIRED');
        $this->getJson('/api/v1/health')->assertOk();
        $this->join($this->hello())->assertCreated();
    }

    public function test_the_native_app_hello_is_accepted(): void
    {
        $this->join($this->hello(['X-CampBuddy-Client' => 'native']))->assertCreated();
    }
}
