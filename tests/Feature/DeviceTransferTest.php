<?php

namespace Tests\Feature;

use App\Models\AttendeeRoster;
use App\Models\DeviceTransfer;
use App\Models\DiscoveryProfile;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * "Move my CampBuddy to this device": the device that picks an already-linked
 * name asks the device holding it; that device approves with the code and
 * uploads data sealed for the new device; the new device completes, gets a new
 * owner token, and the old token stops working. The server never holds the
 * data longer than the hand-over, and never in a form it can read.
 */
class DeviceTransferTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private AttendeeRoster $ada;

    private string $ownerToken;

    private string $discoveryId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::withoutEvents(fn () => Event::create([
            'slug' => 'wc-test',
            'display_name' => 'WordCamp Test',
            'source_site_url' => 'https://test.wordcamp.org/2026',
            'status' => 'active',
            'is_visible' => true,
        ]));
        $this->ada = AttendeeRoster::create([
            'event_id' => $this->event->id,
            'name' => 'Ada Lovelace',
            'content_hash' => hash('sha256', 'ada'),
            'is_suppressed' => false,
        ]);

        // The laptop: joined discovery with Ada's name.
        $joined = $this->postJson(route('api.discovery.store', $this->event), ['tags' => ['developer'], 'attendee_roster_id' => $this->ada->id])->assertCreated();
        $this->ownerToken = $joined->json('owner_token');
        $this->discoveryId = $joined->json('discovery_id');
    }

    private function key(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(65)), '+/', '-_'), '=');
    }

    private function request(?int $rosterId = null): TestResponse
    {
        return $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (Linux; Android 14; Pixel 8) Mobile Safari'])
            ->postJson(route('api.device-transfers.store', $this->event), ['attendee_roster_id' => $rosterId ?? $this->ada->id, 'public_key' => $this->key()]);
    }

    private function as(string $token): static
    {
        return $this->withHeader('Authorization', "Bearer {$token}");
    }

    private function sealed(): array
    {
        return ['sender_key' => $this->key(), 'iv' => rtrim(strtr(base64_encode(random_bytes(12)), '+/', '-_'), '='), 'payload' => rtrim(strtr(base64_encode(random_bytes(900)), '+/', '-_'), '=')];
    }

    private function pending(?string $token = null): TestResponse
    {
        return $this->as($token ?? $this->ownerToken)->getJson(route('api.device-transfers.pending', [$this->event, $this->discoveryId]));
    }

    private function approve(string $transferId, ?array $body = null): TestResponse
    {
        return $this->as($this->ownerToken)->postJson(route('api.device-transfers.approve', [$this->event, $this->discoveryId, $transferId]), $body ?? $this->sealed());
    }

    public function test_the_whole_move_from_one_device_to_another(): void
    {
        $asked = $this->request()->assertCreated();
        $transferId = $asked->json('transfer_id');
        $secret = $asked->json('secret');

        // The laptop sees who's asking — the new device's key and a coarse device name, nothing else.
        $this->pending()->assertOk()
            ->assertJsonPath('transfer.transfer_id', $transferId)
            ->assertJsonPath('transfer.requester_label', 'an Android phone')
            ->assertJsonMissingPath('transfer.requester_secret_hash');

        // Before approval the new device only sees "pending".
        $this->as($secret)->getJson(route('api.device-transfers.show', [$this->event, $transferId]))
            ->assertOk()->assertJsonPath('status', 'pending')->assertJsonMissingPath('payload');

        $sealed = $this->sealed();
        $senderSecret = $this->approve($transferId, $sealed)->assertOk()->json('secret');

        $this->as($secret)->getJson(route('api.device-transfers.show', [$this->event, $transferId]))
            ->assertOk()
            ->assertJsonPath('status', 'approved')
            ->assertJsonPath('payload', $sealed['payload'])
            ->assertJsonPath('sender_key', $sealed['sender_key'])
            ->assertJsonPath('iv', $sealed['iv']);

        $done = $this->as($secret)->postJson(route('api.device-transfers.complete', [$this->event, $transferId]))->assertOk();
        $this->assertSame($this->discoveryId, $done->json('discovery_id'));
        $newToken = $done->json('owner_token');
        $this->assertNotSame($this->ownerToken, $newToken);

        // The sealed data is gone from the server.
        $this->assertNull(DeviceTransfer::firstWhere('transfer_id', $transferId)->payload);

        // The laptop's token no longer works; the phone's does.
        $this->pending()->assertForbidden();
        $this->as($newToken)->patchJson(route('api.discovery.update', [$this->event, $this->discoveryId]), ['tags' => ['designer'], 'attendee_roster_id' => $this->ada->id])->assertOk();

        // The laptop learns it's done (so it can clear its copy) with its own secret.
        $this->as($senderSecret)->getJson(route('api.device-transfers.sender', [$this->event, $transferId]))
            ->assertOk()->assertJsonPath('status', 'completed');

        // And the same name is still linked just once.
        $this->assertSame(1, DiscoveryProfile::where('attendee_roster_id', $this->ada->id)->count());
    }

    public function test_a_name_nobody_linked_has_nothing_to_move(): void
    {
        $bob = AttendeeRoster::create(['event_id' => $this->event->id, 'name' => 'Bob', 'content_hash' => 'b', 'is_suppressed' => false]);

        $this->request($bob->id)->assertUnprocessable()->assertJsonValidationErrors('attendee_roster_id');
    }

    public function test_the_keys_are_checked(): void
    {
        $this->postJson(route('api.device-transfers.store', $this->event), ['attendee_roster_id' => $this->ada->id, 'public_key' => 'short'])->assertUnprocessable();
        $this->postJson(route('api.device-transfers.store', $this->event), ['attendee_roster_id' => $this->ada->id, 'public_key' => str_repeat('a', 80).'<script>'])->assertUnprocessable();

        $transferId = $this->request()->json('transfer_id');
        $this->approve($transferId, ['sender_key' => $this->key(), 'iv' => 'not base64!!!!!!', 'payload' => 'x'])->assertUnprocessable();
        $this->approve($transferId, ['sender_key' => $this->key(), 'iv' => str_repeat('A', 16), 'payload' => str_repeat('A', DeviceTransfer::MAX_PAYLOAD + 1)])->assertUnprocessable();
    }

    public function test_only_the_owner_sees_or_answers_a_request(): void
    {
        $transferId = $this->request()->json('transfer_id');

        $this->pending('wrong-token')->assertForbidden();
        $this->getJson(route('api.device-transfers.pending', [$this->event, $this->discoveryId]))->assertForbidden();
        $this->as('wrong-token')->postJson(route('api.device-transfers.approve', [$this->event, $this->discoveryId, $transferId]), $this->sealed())->assertForbidden();
        $this->as('wrong-token')->postJson(route('api.device-transfers.decline', [$this->event, $this->discoveryId, $transferId]))->assertForbidden();
    }

    public function test_only_the_new_device_can_read_or_finish_its_request(): void
    {
        $transferId = $this->request()->json('transfer_id');
        $this->approve($transferId)->assertOk();

        $this->as('guess')->getJson(route('api.device-transfers.show', [$this->event, $transferId]))->assertNotFound();
        $this->getJson(route('api.device-transfers.show', [$this->event, $transferId]))->assertNotFound();
        $this->as('guess')->postJson(route('api.device-transfers.complete', [$this->event, $transferId]))->assertNotFound();
        $this->as('guess')->getJson(route('api.device-transfers.sender', [$this->event, $transferId]))->assertNotFound();
    }

    public function test_a_request_from_another_event_does_not_reach_this_one(): void
    {
        $asked = $this->request();
        $other = Event::withoutEvents(fn () => Event::create(['slug' => 'wc-other', 'display_name' => 'Other', 'source_site_url' => 'https://o.wordcamp.org/2026', 'status' => 'active', 'is_visible' => true]));

        $this->as($asked->json('secret'))->getJson(route('api.device-transfers.show', [$other, $asked->json('transfer_id')]))->assertNotFound();
    }

    public function test_declining_tells_the_new_device_and_moves_nothing(): void
    {
        $asked = $this->request();
        $transferId = $asked->json('transfer_id');

        $this->as($this->ownerToken)->postJson(route('api.device-transfers.decline', [$this->event, $this->discoveryId, $transferId]))->assertNoContent();

        $this->as($asked->json('secret'))->getJson(route('api.device-transfers.show', [$this->event, $transferId]))
            ->assertOk()->assertJsonPath('status', 'declined')->assertJsonMissingPath('payload');
        $this->as($asked->json('secret'))->postJson(route('api.device-transfers.complete', [$this->event, $transferId]))->assertStatus(409);
        $this->pending()->assertOk()->assertJsonPath('transfer', null);
        $this->approve($transferId)->assertUnprocessable();
    }

    public function test_nothing_can_be_finished_before_it_is_approved_or_twice(): void
    {
        $asked = $this->request();
        $url = route('api.device-transfers.complete', [$this->event, $asked->json('transfer_id')]);

        $this->as($asked->json('secret'))->postJson($url)->assertStatus(409);

        $this->approve($asked->json('transfer_id'))->assertOk();
        $this->as($asked->json('secret'))->postJson($url)->assertOk();
        $this->as($asked->json('secret'))->postJson($url)->assertStatus(409);
    }

    public function test_a_newer_request_replaces_one_still_waiting_but_not_one_under_way(): void
    {
        $first = $this->request()->json('transfer_id');
        $second = $this->request()->json('transfer_id');

        $this->pending()->assertJsonPath('transfer.transfer_id', $second);
        $this->approve($first)->assertUnprocessable();

        $this->approve($second)->assertOk();
        $this->request()->assertUnprocessable();
    }

    public function test_requests_are_limited_per_name(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->request()->assertCreated();
        }

        $this->request()->assertUnprocessable();
    }

    public function test_expired_requests_vanish_with_their_sealed_data(): void
    {
        $asked = $this->request();
        $this->approve($asked->json('transfer_id'))->assertOk();

        $this->travel(DeviceTransfer::WAIT_MINUTES + 1)->minutes();

        $this->as($asked->json('secret'))->getJson(route('api.device-transfers.show', [$this->event, $asked->json('transfer_id')]))->assertNotFound();

        DeviceTransfer::prune();
        $this->assertSame(0, DeviceTransfer::count());
    }

    public function test_the_new_device_can_give_up(): void
    {
        $asked = $this->request();

        $this->as($asked->json('secret'))->deleteJson(route('api.device-transfers.destroy', [$this->event, $asked->json('transfer_id')]))->assertNoContent();

        $this->pending()->assertJsonPath('transfer', null);
    }

    public function test_leaving_discovery_takes_its_requests_with_it(): void
    {
        $this->request();

        $this->as($this->ownerToken)->deleteJson(route('api.discovery.destroy', [$this->event, $this->discoveryId]))->assertNoContent();

        $this->assertSame(0, DeviceTransfer::count());
    }

    public function test_no_step_is_cached(): void
    {
        $asked = $this->request();

        foreach ([
            $this->pending(),
            $this->as($asked->json('secret'))->getJson(route('api.device-transfers.show', [$this->event, $asked->json('transfer_id')])),
        ] as $response) {
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        }
    }

    public function test_the_device_label_is_coarse(): void
    {
        $this->assertSame('an iPhone', DeviceTransfer::labelFor('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)'));
        $this->assertSame('a Mac', DeviceTransfer::labelFor('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)'));
        $this->assertSame('a Windows computer', DeviceTransfer::labelFor('Mozilla/5.0 (Windows NT 10.0; Win64; x64)'));
        $this->assertNull(DeviceTransfer::labelFor(null));
        $this->assertSame(40, strlen(Str::random(40)));
    }
}
