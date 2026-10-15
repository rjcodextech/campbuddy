<?php

namespace Tests\Feature;

use App\Models\DiscoveryProfile;
use App\Models\DiscoveryReport;
use App\Models\Event;
use App\Models\EventManager;
use App\Models\User;
use App\Support\AppOrganizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * The native app organizer API: bearer-token sign-in, events, reports, running late.
 */
class OrganizerApiTest extends TestCase
{
    use RefreshDatabase;

    private Event $mine;

    private Event $theirs;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        RateLimiter::clear('app-login:');
        $this->mine = $this->makeEvent('wordcamp-mine');
        $this->theirs = $this->makeEvent('wordcamp-theirs');
    }

    private function makeEvent(string $slug): Event
    {
        return Event::withoutEvents(fn () => Event::create([
            'slug' => $slug, 'display_name' => ucwords(str_replace('-', ' ', $slug)), 'source_site_url' => "https://{$slug}.wordcamp.org/2026",
            'status' => 'active', 'is_visible' => true, 'starts_on' => now()->addDays(3)->toDateString(), 'ends_on' => now()->addDays(4)->toDateString(), 'timezone' => 'Asia/Kolkata',
        ]));
    }

    private function manager(array $attributes = []): EventManager
    {
        $manager = EventManager::factory()->create(['email' => 'mgr@example.com'] + $attributes);
        $manager->events()->attach($this->mine->id);

        return $manager;
    }

    private function bearer(string $role, int $id): array
    {
        return ['Authorization' => 'Bearer '.AppOrganizer::issue($role, $id, 'test phone')['token']];
    }

    public function test_an_admin_signs_in_and_gets_a_token_that_works(): void
    {
        $admin = User::factory()->create(['email' => 'boss@example.com']);

        $login = $this->postJson('/api/v1/app/auth/login', ['email' => 'boss@example.com', 'password' => 'password', 'role' => 'admin', 'device_name' => 'Pixel'])
            ->assertOk()
            ->assertJsonStructure(['token', 'expires_at', 'role', 'user' => ['id', 'name', 'email']]);

        $this->getJson('/api/v1/app/organizer/me', ['Authorization' => 'Bearer '.$login->json('token')])
            ->assertOk()->assertJsonPath('role', 'admin')->assertJsonPath('user.id', $admin->id);
        $this->assertNotSame($login->json('token'), DB::table('app_api_tokens')->value('token_hash'), 'only a hash is stored');
    }

    public function test_wrong_credentials_are_refused_and_throttled(): void
    {
        User::factory()->create(['email' => 'boss@example.com']);
        $bad = ['email' => 'boss@example.com', 'password' => 'nope', 'role' => 'admin'];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/app/auth/login', $bad)->assertStatus(422)->assertJsonPath('error.code', 'invalid_credentials');
        }
        $this->postJson('/api/v1/app/auth/login', $bad)->assertStatus(429);
        $this->assertSame(0, DB::table('app_api_tokens')->count());
    }

    public function test_an_inactive_manager_cannot_sign_in(): void
    {
        $this->manager(['is_active' => false]);

        $this->postJson('/api/v1/app/auth/login', ['email' => 'mgr@example.com', 'password' => 'password', 'role' => 'manager'])->assertStatus(422);
    }

    public function test_every_organizer_endpoint_needs_a_token(): void
    {
        $this->getJson('/api/v1/app/organizer/me')->assertUnauthorized();
        $this->getJson('/api/v1/app/organizer/events')->assertUnauthorized();
        $this->getJson("/api/v1/app/organizer/events/{$this->mine->id}/reports", ['Authorization' => 'Bearer made-up'])->assertUnauthorized();
        $this->postJson("/api/v1/app/organizer/events/{$this->mine->id}/delay/clear")->assertUnauthorized();
    }

    public function test_a_token_stops_working_when_it_expires_or_is_revoked(): void
    {
        $manager = $this->manager();
        $headers = $this->bearer('manager', $manager->id);
        $this->getJson('/api/v1/app/organizer/me', $headers)->assertOk();

        $this->postJson('/api/v1/app/auth/logout', [], $headers)->assertNoContent();
        $this->getJson('/api/v1/app/organizer/me', $headers)->assertUnauthorized();

        $headers = $this->bearer('manager', $manager->id);
        $this->travel(8)->days();
        $this->getJson('/api/v1/app/organizer/me', $headers)->assertUnauthorized();
    }

    public function test_a_manager_sees_only_their_own_events_and_an_admin_sees_all(): void
    {
        $manager = $this->manager();
        $admin = User::factory()->create();

        $this->assertSame(['wordcamp-mine'], collect($this->getJson('/api/v1/app/organizer/events', $this->bearer('manager', $manager->id))->json('data'))->pluck('slug')->all());
        $this->assertEqualsCanonicalizing(['wordcamp-mine', 'wordcamp-theirs'], collect($this->getJson('/api/v1/app/organizer/events', $this->bearer('admin', $admin->id))->json('data'))->pluck('slug')->all());

        $this->getJson("/api/v1/app/organizer/events/{$this->theirs->id}/reports", $this->bearer('manager', $manager->id))->assertNotFound();
        $this->postJson("/api/v1/app/organizer/events/{$this->theirs->id}/delay/clear", [], $this->bearer('manager', $manager->id))->assertNotFound();
    }

    public function test_reports_can_be_read_and_the_profile_removed(): void
    {
        $manager = $this->manager();
        $c = DiscoveryProfile::generateCredentials();
        $profile = DiscoveryProfile::create(['discovery_id' => $c['discovery_id'], 'owner_token_hash' => $c['owner_token_hash'], 'event_id' => $this->mine->id, 'fields' => ['tags' => ['seo'], 'display_name' => 'Spammy Sam'], 'expires_at' => now()->addWeek()]);
        DiscoveryReport::create(['event_id' => $this->mine->id, 'discovery_id' => $profile->discovery_id, 'reporter_device_id' => 'd1', 'reason' => 'spam', 'snapshot' => ['name' => 'Spammy Sam'], 'status' => 'open']);
        $headers = $this->bearer('manager', $manager->id);

        $open = $this->getJson("/api/v1/app/organizer/events/{$this->mine->id}/reports", $headers)->assertOk()->json('data.open');
        $this->assertSame('Spammy Sam', $open[0]['profile']['name']);
        $this->assertSame('Spam or selling', $open[0]['reports'][0]['label']);
        $this->assertTrue($open[0]['profile_still_there']);

        $this->postJson("/api/v1/app/organizer/events/{$this->mine->id}/reports/{$profile->discovery_id}/remove", [], $headers)->assertNoContent();
        $this->assertNull(DiscoveryProfile::find($profile->id));
        $this->assertSame('removed', DiscoveryReport::first()->status);
    }

    public function test_running_late_can_be_set_read_and_cleared(): void
    {
        $manager = $this->manager();
        $headers = $this->bearer('manager', $manager->id);
        $base = "/api/v1/app/organizer/events/{$this->mine->id}/delay";

        $this->postJson($base, ['minutes' => 15, 'track' => '', 'from' => now()->setTimezone('Asia/Kolkata')->format('Y-m-d\TH:i'), 'note' => 'Lunch ran over'], $headers)
            ->assertOk()->assertJsonPath('data.active.0.minutes', 15);
        $this->getJson($base, $headers)->assertOk()->assertJsonPath('data.active.0.minutes', 15)->assertJsonPath('data.timezone', 'Asia/Kolkata');

        $this->postJson($base, ['minutes' => 999, 'from' => 'soon'], $headers)->assertUnprocessable();

        $this->postJson("{$base}/clear", [], $headers)->assertNoContent();
        $this->getJson($base, $headers)->assertJsonPath('data.active', []);
    }
}
