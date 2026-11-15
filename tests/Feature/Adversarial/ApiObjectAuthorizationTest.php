<?php

namespace Tests\Feature\Adversarial;

use App\Models\ContributorTable;
use App\Models\DeviceTransfer;
use App\Models\DiscoveryProfile;
use App\Models\DiscoveryWave;
use App\Models\Event;
use App\Models\Quest;
use App\Models\SharedCampCard;
use App\Models\User;
use App\Support\AppOrganizer;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Broken object-level authorization (OWASP API1): every object an attendee or
 * organizer can touch is reached only with the credential made for it — a
 * profile's owner token for that profile on that event, a transfer's own secret
 * for that side of the transfer, a manager's token for their own events. A
 * public id authorizes nothing, and a hidden event doesn't exist.
 */
class ApiObjectAuthorizationTest extends TestCase
{
    use ApiFixtures;
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-20T06:00:00Z'));
        config(['app.debug' => false, 'campbuddy.rate_limits.address_writes' => 100000, 'campbuddy.rate_limits.address_reads' => 100000]);

        $this->event = $this->makeEvent();
    }

    /** Every route under the given URI prefix, with its parameters filled in. */
    private function routesUnder(string $prefix): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RouteDefinition $route) => str_starts_with($route->uri(), $prefix))
            ->all();
    }

    private function fill(RouteDefinition $route, array $values): string
    {
        $uri = $route->uri();
        foreach ($route->parameterNames() as $name) {
            $uri = str_replace(['{'.$name.'}', '{'.$name.'?}'], (string) ($values[$name] ?? '1'), $uri);
        }
        $uri = preg_replace('/\{(\w+):\w+\}/', (string) ($values['event'] ?? ''), $uri);

        return '/'.ltrim($uri, '/');
    }

    // ---- Hidden, draft and archived events ----------------------------------------------------------

    public function test_a_hidden_draft_or_unapproved_event_is_a_404_on_every_attendee_api_route(): void
    {
        $hidden = [
            $this->makeEvent('wc-hidden', ['is_visible' => false]),
            $this->makeEvent('wc-draft', ['status' => 'draft']),
            $this->makeEvent('wc-approved', ['status' => 'approved']),
            $this->makeEvent('wc-archived', ['status' => 'archived']),
        ];
        $routes = [...$this->routesUnder('api/v1/events/{event'), ...$this->routesUnder('api/v1/app/events/{event')];
        $this->assertGreaterThan(30, count($routes), 'the route walk found the attendee API');

        foreach ($hidden as $event) {
            foreach ($routes as $route) {
                $method = collect($route->methods())->reject(fn ($m) => $m === 'HEAD')->first();
                $url = $this->fill($route, ['event' => $event->slug, 'discoveryId' => str_repeat('a', 64), 'transferId' => 'x', 'targetId' => str_repeat('b', 64), 'shareId' => 'y', 'offer' => 1]);

                // The memories page outlives archiving on purpose (EventMemories::pageIsPublic).
                if ($event->status === 'archived' && str_ends_with($url, '/memories')) {
                    continue;
                }

                $response = $this->json($method, $url, ['tags' => ['developer'], 'device_id' => 'phone-1'], $this->phone());
                $this->assertSame(404, $response->getStatusCode(), "{$method} {$url} answered {$response->getStatusCode()} for a {$event->slug} event");
            }
        }
    }

    public function test_hidden_events_are_left_out_of_every_public_listing(): void
    {
        $this->makeEvent('wc-secret-hidden', ['is_visible' => false]);
        $this->makeEvent('wc-secret-draft', ['status' => 'draft']);

        foreach (['/api/v1/app/events', '/sitemap.xml', '/llms.txt', '/'] as $url) {
            $body = (string) $this->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('wc-secret', $body, "{$url} lists an event that is not public");
        }
    }

    // ---- Discovery profiles: owner tokens ------------------------------------------------------------

    public function test_one_profiles_owner_token_opens_nothing_of_another_profile(): void
    {
        $mallory = $this->joinDiscovery($this->event, ['display_name' => 'Mallory']);
        $victim = $this->joinDiscovery($this->event, ['display_name' => 'Victim', 'profession' => 'Original']);
        $friend = $this->joinDiscovery($this->event, ['display_name' => 'Friend']);
        $as = $this->bearer($mallory['owner_token']);
        $id = $victim['discovery_id'];

        $this->patchJson(route('api.discovery.update', [$this->event, $id]), ['tags' => ['student'], 'profession' => 'Hacked'], $as + $this->phone())->assertForbidden();
        $this->getJson(route('api.discovery.waves.index', [$this->event, $id]), $as)->assertForbidden();
        $this->postJson(route('api.discovery.waves.store', [$this->event, $id]), ['to' => $friend['discovery_id'], 'name' => 'x'], $as + $this->phone())->assertForbidden();
        $this->postJson(route('api.discovery.messages.store', [$this->event, $id]), ['to' => $friend['discovery_id'], 'body' => 'x'], $as + $this->phone())->assertForbidden();
        $this->deleteJson(route('api.discovery.waves.destroy', [$this->event, $id, $friend['discovery_id']]), [], $as + $this->phone())->assertForbidden();
        $this->getJson(route('api.device-transfers.pending', [$this->event, $id]), $as)->assertForbidden();
        $this->postJson(route('api.device-transfers.approve', [$this->event, $id, 'x']), [], $as + $this->phone())->assertForbidden();
        $this->postJson(route('api.device-transfers.decline', [$this->event, $id, 'x']), [], $as + $this->phone())->assertForbidden();
        $this->deleteJson(route('api.discovery.destroy', [$this->event, $id]), [], $as + $this->phone())->assertForbidden();

        $profile = DiscoveryProfile::where('discovery_id', $id)->sole();
        $this->assertSame('Original', $profile->fields['profession']);
        $this->assertSame(0, DiscoveryWave::count());
    }

    public function test_an_unknown_profile_and_a_wrong_token_get_the_same_answer(): void
    {
        $victim = $this->joinDiscovery($this->event);

        $wrongToken = $this->patchJson(route('api.discovery.update', [$this->event, $victim['discovery_id']]), ['tags' => ['student']], $this->bearer('not-the-token') + $this->phone());
        $noProfile = $this->patchJson(route('api.discovery.update', [$this->event, str_repeat('f', 64)]), ['tags' => ['student']], $this->bearer('not-the-token') + $this->phone());

        $wrongToken->assertForbidden();
        $noProfile->assertForbidden();
        $this->assertSame($wrongToken->getContent(), $noProfile->getContent(), 'no hint which of the two it was');
    }

    public function test_a_profile_and_its_token_work_only_on_their_own_event(): void
    {
        $other = $this->makeEvent('wc-other');
        $me = $this->joinDiscovery($this->event);
        $as = $this->bearer($me['owner_token']);

        $this->patchJson(route('api.discovery.update', [$other, $me['discovery_id']]), ['tags' => ['student']], $as + $this->phone())->assertForbidden();
        $this->getJson(route('api.discovery.waves.index', [$other, $me['discovery_id']]), $as)->assertForbidden();
        $this->deleteJson(route('api.discovery.destroy', [$other, $me['discovery_id']]), [], $as + $this->phone())->assertForbidden();
        $this->postJson(route('api.discovery.report', [$other, $me['discovery_id']]), ['device_id' => 'phone-1', 'reason' => 'spam'], $this->phone())->assertNotFound();

        // Waving across events: the other event's profile is not someone you can reach from here.
        $stranger = $this->joinDiscovery($other, ['display_name' => 'Stranger']);
        $this->postJson(route('api.discovery.waves.store', [$this->event, $me['discovery_id']]), ['to' => $stranger['discovery_id'], 'name' => 'Asha'], $as + $this->phone())
            ->assertUnprocessable()->assertJsonValidationErrors('to');

        $this->assertSame(2, DiscoveryProfile::count());
    }

    public function test_owner_tokens_are_read_only_from_the_authorization_header(): void
    {
        $me = $this->joinDiscovery($this->event);
        $token = $me['owner_token'];
        $url = route('api.discovery.update', [$this->event, $me['discovery_id']]);

        $this->patchJson($url.'?token='.$token, ['tags' => ['student']], $this->phone())->assertForbidden();
        $this->patchJson($url.'?access_token='.$token, ['tags' => ['student']], $this->phone())->assertForbidden();
        $this->patchJson($url, ['tags' => ['student'], 'owner_token' => $token, 'token' => $token], $this->phone())->assertForbidden();
        $this->patchJson($url, ['tags' => ['student']], ['X-Owner-Token' => $token] + $this->phone())->assertForbidden();

        $this->patchJson($url, ['tags' => ['student']], $this->bearer($token) + $this->phone())->assertOk();
    }

    public function test_malformed_authorization_headers_are_refused_without_a_crash(): void
    {
        $me = $this->joinDiscovery($this->event);
        $url = route('api.discovery.update', [$this->event, $me['discovery_id']]);

        foreach (['Bearer', 'Bearer ', 'Bearer    ', 'Basic '.base64_encode('user:pass'), 'Bearer '.str_repeat('x', 10000), "Bearer {$me['owner_token']}\x00", 'Token '.$me['owner_token'], 'Bearer '.substr($me['owner_token'], 0, -1)] as $header) {
            $response = $this->patchJson($url, ['tags' => ['student']], ['Authorization' => $header] + $this->phone());
            $this->assertSame(403, $response->getStatusCode(), 'Authorization: '.mb_substr($header, 0, 40));
            $this->assertNoDebugLeak($response);
        }

        foreach (['Bearer', 'Bearer ', 'Basic '.base64_encode('user:pass'), 'Bearer '.str_repeat('x', 10000), "Bearer \x00"] as $header) {
            $response = $this->getJson('/api/v1/app/organizer/me', ['Authorization' => $header]);
            $this->assertSame(401, $response->getStatusCode(), 'organizer, Authorization: '.mb_substr($header, 0, 40));
            $this->assertNoDebugLeak($response);
        }
    }

    // ---- Shared Camp Cards ----------------------------------------------------------------------------

    public function test_a_camp_card_token_and_a_discovery_token_are_not_interchangeable(): void
    {
        $asha = $this->rosterEntry($this->event, 'Asha Rao');
        $profile = $this->joinDiscovery($this->event, ['attendee_roster_id' => $asha->id]);
        $share = $this->postJson(route('api.camp-card-share.store', $this->event), [
            'attendee_roster_id' => $asha->id, 'card' => ['role' => 'Dev'], 'discovery_id' => $profile['discovery_id'], 'discovery_token' => $profile['owner_token'],
        ], $this->phone())->assertCreated()->json();

        $this->putJson(route('api.camp-card-share.update', [$this->event, $share['share_id']]), ['attendee_roster_id' => $asha->id, 'card' => ['role' => 'Hacked']], $this->bearer($profile['owner_token']) + $this->phone())->assertForbidden();
        $this->deleteJson(route('api.camp-card-share.destroy', [$this->event, $share['share_id']]), [], $this->bearer($profile['owner_token']) + $this->phone())->assertForbidden();
        $this->patchJson(route('api.discovery.update', [$this->event, $profile['discovery_id']]), ['tags' => ['student']], $this->bearer($share['owner_token']) + $this->phone())->assertForbidden();

        // A card id used as a discovery id, and the other way round.
        $this->deleteJson(route('api.discovery.destroy', [$this->event, $share['share_id']]), [], $this->bearer($share['owner_token']) + $this->phone())->assertForbidden();
        $this->deleteJson(route('api.camp-card-share.destroy', [$this->event, $profile['discovery_id']]), [], $this->bearer($profile['owner_token']) + $this->phone())->assertForbidden();

        $this->assertSame(['role' => 'Dev'], SharedCampCard::sole()->fields);
        $this->assertSame(1, DiscoveryProfile::count());
    }

    public function test_a_name_linked_to_someone_elses_discovery_profile_cannot_be_claimed_with_borrowed_proof(): void
    {
        $asha = $this->rosterEntry($this->event, 'Asha Rao');
        $victim = $this->joinDiscovery($this->event, ['attendee_roster_id' => $asha->id]);
        $mallory = $this->joinDiscovery($this->event);

        $attempts = [
            'no proof' => [],
            'right id, wrong token' => ['discovery_id' => $victim['discovery_id'], 'discovery_token' => 'not-the-token'],
            'own profile and token' => ['discovery_id' => $mallory['discovery_id'], 'discovery_token' => $mallory['owner_token']],
            'right id, own token' => ['discovery_id' => $victim['discovery_id'], 'discovery_token' => $mallory['owner_token']],
        ];

        foreach ($attempts as $label => $proof) {
            $this->postJson(route('api.camp-card-share.store', $this->event), ['attendee_roster_id' => $asha->id, 'card' => ['role' => 'Fake']] + $proof, $this->phone())
                ->assertUnprocessable()
                ->assertJsonValidationErrors('attendee_roster_id', 'errors', $label);
        }

        $this->assertSame(0, SharedCampCard::count());
    }

    public function test_a_camp_card_cannot_claim_a_suppressed_or_another_events_name(): void
    {
        $other = $this->makeEvent('wc-other');
        $elsewhere = $this->rosterEntry($other, 'Ben Lee');
        $suppressed = $this->rosterEntry($this->event, 'Chen Wei', ['is_suppressed' => true]);

        foreach ([$elsewhere, $suppressed] as $entry) {
            $this->postJson(route('api.camp-card-share.store', $this->event), ['attendee_roster_id' => $entry->id, 'card' => ['role' => 'Dev']], $this->phone())
                ->assertUnprocessable()->assertJsonValidationErrors('attendee_roster_id');
            $this->postJson(route('api.discovery.store', $this->event), ['tags' => ['developer'], 'attendee_roster_id' => $entry->id], $this->phone())
                ->assertUnprocessable()->assertJsonValidationErrors('attendee_roster_id');
        }

        $this->assertSame(0, SharedCampCard::count());
        $this->assertSame(0, DiscoveryProfile::count());
    }

    // ---- Device transfers -------------------------------------------------------------------------------

    public function test_each_side_of_a_device_transfer_needs_its_own_secret_and_its_own_event(): void
    {
        $other = $this->makeEvent('wc-other');
        $asha = $this->rosterEntry($this->event, 'Asha Rao');
        $owner = $this->joinDiscovery($this->event, ['attendee_roster_id' => $asha->id]);

        $request = $this->postJson(route('api.device-transfers.store', $this->event), ['attendee_roster_id' => $asha->id, 'public_key' => $this->base64url(65)], $this->phone())->assertCreated()->json();
        $tid = $request['transfer_id'];
        $approved = $this->postJson(route('api.device-transfers.approve', [$this->event, $owner['discovery_id'], $tid]), [
            'sender_key' => $this->base64url(65), 'iv' => $this->base64url(12), 'payload' => $this->base64url(300),
        ], $this->bearer($owner['owner_token']) + $this->phone())->assertOk()->json();
        $senderSecret = $approved['secret'];

        // The sender's secret doesn't open the new device's side, nor the other way round.
        $this->getJson(route('api.device-transfers.show', [$this->event, $tid]), $this->bearer($senderSecret))->assertNotFound();
        $this->postJson(route('api.device-transfers.complete', [$this->event, $tid]), [], $this->bearer($senderSecret) + $this->phone())->assertNotFound();
        $this->getJson(route('api.device-transfers.sender', [$this->event, $tid]), $this->bearer($request['secret']))->assertNotFound();
        // The owner token isn't a transfer secret either.
        $this->getJson(route('api.device-transfers.show', [$this->event, $tid]), $this->bearer($owner['owner_token']))->assertNotFound();
        // No secret at all.
        $this->getJson(route('api.device-transfers.show', [$this->event, $tid]))->assertNotFound();
        // The right secrets, the wrong event.
        $this->getJson(route('api.device-transfers.show', [$other, $tid]), $this->bearer($request['secret']))->assertNotFound();
        $this->postJson(route('api.device-transfers.complete', [$other, $tid]), [], $this->bearer($request['secret']) + $this->phone())->assertNotFound();
        $this->getJson(route('api.device-transfers.sender', [$other, $tid]), $this->bearer($senderSecret))->assertNotFound();

        $this->assertSame('approved', DeviceTransfer::sole()->status, 'none of the above moved it on');
        $this->assertTrue(DiscoveryProfile::sole()->ownerTokenMatches($owner['owner_token']), 'and the owner token was not rotated');

        // The real new device completes once; a replay of the same call gets nothing more.
        $done = $this->postJson(route('api.device-transfers.complete', [$this->event, $tid]), [], $this->bearer($request['secret']) + $this->phone())->assertOk();
        $this->postJson(route('api.device-transfers.complete', [$this->event, $tid]), [], $this->bearer($request['secret']) + $this->phone())->assertStatus(409);
        $this->assertTrue(DiscoveryProfile::sole()->ownerTokenMatches($done->json('owner_token')));
        $this->assertFalse(DiscoveryProfile::sole()->ownerTokenMatches($owner['owner_token']));
    }

    public function test_one_owner_cannot_approve_or_decline_a_transfer_meant_for_another_profile(): void
    {
        $asha = $this->rosterEntry($this->event, 'Asha Rao');
        $ben = $this->rosterEntry($this->event, 'Ben Lee');
        $this->joinDiscovery($this->event, ['attendee_roster_id' => $asha->id]);
        $mallory = $this->joinDiscovery($this->event, ['attendee_roster_id' => $ben->id]);

        $forAsha = $this->postJson(route('api.device-transfers.store', $this->event), ['attendee_roster_id' => $asha->id, 'public_key' => $this->base64url(65)], $this->phone())->assertCreated()->json('transfer_id');

        $this->postJson(route('api.device-transfers.approve', [$this->event, $mallory['discovery_id'], $forAsha]), [
            'sender_key' => $this->base64url(65), 'iv' => $this->base64url(12), 'payload' => $this->base64url(300),
        ], $this->bearer($mallory['owner_token']) + $this->phone())->assertUnprocessable();
        $this->postJson(route('api.device-transfers.decline', [$this->event, $mallory['discovery_id'], $forAsha]), [], $this->bearer($mallory['owner_token']) + $this->phone())->assertUnprocessable();

        $this->assertSame('pending', DeviceTransfer::where('transfer_id', $forAsha)->sole()->status);
    }

    public function test_device_transfer_answers_are_never_cached(): void
    {
        $asha = $this->rosterEntry($this->event, 'Asha Rao');
        $owner = $this->joinDiscovery($this->event, ['attendee_roster_id' => $asha->id]);
        $request = $this->postJson(route('api.device-transfers.store', $this->event), ['attendee_roster_id' => $asha->id, 'public_key' => $this->base64url(65)], $this->phone())->assertCreated();

        foreach ([
            $request,
            $this->getJson(route('api.device-transfers.show', [$this->event, $request->json('transfer_id')]), $this->bearer($request->json('secret'))),
            $this->getJson(route('api.device-transfers.pending', [$this->event, $owner['discovery_id']]), $this->bearer($owner['owner_token'])),
        ] as $response) {
            $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
            $this->assertStringNotContainsString('public', (string) $response->headers->get('Cache-Control'));
        }
    }

    // ---- Organizer app tokens -----------------------------------------------------------------------------

    public function test_a_manager_reaches_nothing_of_an_event_that_is_not_theirs_on_any_organizer_route(): void
    {
        Http::fake();
        Queue::fake();
        $theirs = $this->makeEvent('wc-theirs');
        $manager = $this->managerOf($this->event);
        $auth = $this->bearer($this->organizerToken('manager', $manager->id));
        $entry = $this->rosterEntry($theirs, 'Asha Rao');

        $routes = collect($this->routesUnder('api/v1/app/organizer/events/{id}'));
        $this->assertGreaterThan(40, $routes->count());

        foreach ($routes as $route) {
            $method = collect($route->methods())->reject(fn ($m) => $m === 'HEAD')->first();
            $url = $this->fill($route, [
                'id' => $theirs->id, 'entryId' => $entry->id, 'itemId' => 1, 'discoveryId' => str_repeat('a', 64),
                'kind' => 'photos', 'action' => collect($route->wheres['action'] ?? 'use')->first() ?: 'use', 'job' => 'schedule',
            ]);
            if (isset($route->wheres['action'])) {
                $url = str_replace('/'.$route->wheres['action'], '/'.explode('|', $route->wheres['action'])[0], $url);
            }

            $status = $this->json($method, $url, ['confirm' => $theirs->slug, 'roles' => []], $auth)->getStatusCode();
            $this->assertContains($status, [403, 404], "{$method} {$url} answered {$status} to a manager of another event");
        }

        $this->assertNotNull($theirs->fresh(), 'their event still exists');
        $this->assertSame('WordCamp Wc Theirs', $theirs->fresh()->display_name);
    }

    public function test_a_manager_cannot_reach_another_events_quests_tables_or_entries_through_their_own_event(): void
    {
        $theirs = $this->makeEvent('wc-theirs');
        $manager = $this->managerOf($this->event);
        $auth = $this->bearer($this->organizerToken('manager', $manager->id));
        $base = "/api/v1/app/organizer/events/{$this->event->id}";

        $quest = Quest::create(['event_id' => $theirs->id, 'title' => 'Their quest', 'source' => 'event', 'sort_order' => 1]);
        $table = ContributorTable::create(['event_id' => $theirs->id, 'team' => 'core', 'title' => 'Their table', 'sort_order' => 1]);
        $entry = $this->rosterEntry($theirs, 'Asha Rao');

        $this->patchJson("{$base}/quests/{$quest->id}", ['title' => 'Hacked'], $auth)->assertNotFound();
        $this->deleteJson("{$base}/quests/{$quest->id}", [], $auth)->assertNotFound();
        $this->patchJson("{$base}/tables/{$table->id}", ['team' => 'core', 'title' => 'Hacked'], $auth)->assertNotFound();
        $this->deleteJson("{$base}/tables/{$table->id}", [], $auth)->assertNotFound();
        $this->postJson("{$base}/attendees/{$entry->id}/roles", ['roles' => ['organizer']], $auth)->assertNotFound();

        $this->assertSame('Their quest', $quest->fresh()->title);
        $this->assertSame('Their table', $table->fresh()->title);
    }

    public function test_admin_only_organizer_routes_turn_a_manager_away(): void
    {
        Http::fake();
        Queue::fake();
        $manager = $this->managerOf($this->event);
        $auth = $this->bearer($this->organizerToken('manager', $manager->id));
        $base = "/api/v1/app/organizer/events/{$this->event->id}";

        foreach ([
            ['GET', "{$base}/activity"], ['GET', "{$base}/deals"], ['GET', "{$base}/leads"], ['GET', "{$base}/leads/export"],
            ['POST', "{$base}/deals"], ['POST', "{$base}/fetch/schedule"], ['DELETE', $base],
            ['POST', "{$base}/attendees/1/suppress"],
        ] as [$method, $url]) {
            $this->json($method, $url, ['confirm' => $this->event->slug], $auth)->assertForbidden();
        }

        foreach ($this->routesUnder('api/v1/app/organizer/admin') as $route) {
            $method = collect($route->methods())->reject(fn ($m) => $m === 'HEAD')->first();
            $this->json($method, $this->fill($route, ['itemId' => $manager->id]), [], $auth)->assertForbidden();
        }

        $this->assertNotNull($this->event->fresh());
        $this->assertTrue($manager->fresh()->is_active);
    }

    public function test_a_manager_cannot_change_an_events_status_or_protected_columns_from_the_app(): void
    {
        Queue::fake();
        $manager = $this->managerOf($this->event);
        $auth = $this->bearer($this->organizerToken('manager', $manager->id));

        $this->postJson("/api/v1/app/organizer/events/{$this->event->id}/details", [
            'slug' => $this->event->slug, 'display_name' => 'WordCamp Attack', 'source_site_url' => $this->event->source_site_url,
            'status' => 'archived', 'logo_path' => '../../.env', 'social_webhook' => 'https://example.com/hook', 'id' => 999,
            'timezone_locked' => true, 'info_fetched' => ['x' => 1], 'created_at' => '2000-01-01',
        ], $auth)->assertOk();

        $event = $this->event->fresh();
        $this->assertSame('active', $event->status);
        $this->assertNull($event->logo_path);
        $this->assertNull($event->getAttributes()['social_webhook'] ?? null);
        $this->assertSame($this->event->id, $event->id);
        $this->assertSame('WordCamp Attack', $event->display_name);
    }

    public function test_an_organizer_token_stops_working_when_it_expires_its_person_goes_or_is_switched_off(): void
    {
        $admin = User::factory()->create();
        $manager = $this->managerOf($this->event);

        $adminToken = $this->organizerToken('admin', $admin->id);
        $managerToken = $this->organizerToken('manager', $manager->id);
        $this->getJson('/api/v1/app/organizer/me', $this->bearer($adminToken))->assertOk();
        $this->getJson('/api/v1/app/organizer/me', $this->bearer($managerToken))->assertOk();

        // A manager taken off the event loses it at once (no cached permission).
        $manager->events()->detach($this->event->id);
        $this->getJson("/api/v1/app/organizer/events/{$this->event->id}/overview", $this->bearer($managerToken))->assertNotFound();

        $manager->update(['is_active' => false]);
        $this->getJson('/api/v1/app/organizer/me', $this->bearer($managerToken))->assertUnauthorized();

        $admin->delete();
        $this->getJson('/api/v1/app/organizer/me', $this->bearer($adminToken))->assertUnauthorized();

        $fresh = User::factory()->create();
        $expiring = $this->organizerToken('admin', $fresh->id);
        $this->travel(AppOrganizer::ADMIN_HOURS)->hours();
        $this->travel(1)->seconds();
        $this->getJson('/api/v1/app/organizer/me', $this->bearer($expiring))->assertUnauthorized();
    }

    public function test_a_manager_token_is_not_an_admin_token_even_when_the_ids_match(): void
    {
        $admin = User::factory()->create();
        $manager = $this->managerOf($this->event);
        // Same numeric id in both tables: the role on the token row decides, not the id.
        DB::table('event_managers')->where('id', $manager->id)->update(['id' => $admin->id]);
        DB::table('event_event_manager')->where('event_manager_id', $manager->id)->update(['event_manager_id' => $admin->id]);

        $token = $this->organizerToken('manager', $admin->id);

        $this->getJson('/api/v1/app/organizer/admin/dashboard', $this->bearer($token))->assertForbidden();
        $this->getJson('/api/v1/app/organizer/me', $this->bearer($token))->assertOk()->assertJsonPath('role', 'manager');
    }

    public function test_only_a_hash_of_an_organizer_token_is_stored_and_logout_revokes_only_that_one(): void
    {
        $admin = User::factory()->create();
        $phone = $this->organizerToken('admin', $admin->id);
        $tablet = $this->organizerToken('admin', $admin->id);

        $this->assertSame(0, DB::table('app_api_tokens')->where('token_hash', $phone)->count());
        $this->assertSame(1, DB::table('app_api_tokens')->where('token_hash', hash('sha256', $phone))->count());

        $this->postJson('/api/v1/app/auth/logout', [], $this->bearer($phone))->assertNoContent();
        $this->getJson('/api/v1/app/organizer/me', $this->bearer($phone))->assertUnauthorized();
        $this->getJson('/api/v1/app/organizer/me', $this->bearer($tablet))->assertOk();
    }
}
