<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceTransfer;
use App\Models\DiscoveryProfile;
use App\Models\Event;
use App\Support\ApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * "Move my CampBuddy to this device". Someone who set everything up on one
 * device (their laptop, say) and then picks their own name on another (their
 * phone) finds the name already linked. Instead of a dead end, the new device
 * asks the old one:
 *
 *   1. New device → POST /device-transfers with the attendee-list entry and a
 *      fresh ECDH public key. It gets a secret back and shows a 6-digit code
 *      made from its key (device-transfer.js).
 *   2. Old device, which holds the profile's owner token, sees the request
 *      (GET /discovery/{id}/transfer) and approves only once the person types
 *      that code — so a request from someone else can't be approved by a
 *      stray tap, and a key swapped on the way wouldn't match.
 *   3. On approval the old device seals everything to the new device's key
 *      (ECDH + AES-GCM) and uploads the sealed data. The server can't open it.
 *   4. The new device fetches it, opens it, and calls /complete: the owner
 *      token is replaced (the old device's copy stops working), the sealed
 *      data is deleted, and the old device — told the move is done — clears
 *      its own copy.
 *
 * Nothing that names the attendee is stored here, and every request is
 * deleted when its time is up. The admin's "Release claim" is untouched.
 */
class DeviceTransferController extends Controller
{
    private const KEY = ['required', 'string', 'min:60', 'max:200', 'regex:/^[A-Za-z0-9_-]+$/'];

    /** Requests one profile may receive in an hour, and one device may send. */
    private const PER_PROFILE_PER_HOUR = 6;

    private const PER_DEVICE_PER_HOUR = 10;

    // ---- The new device -----------------------------------------------------

    /** POST — ask the device that holds this attendee-list name for its data. */
    public function store(Request $request, Event $event): JsonResponse
    {
        DeviceTransfer::prune();

        $data = $request->validate([
            'attendee_roster_id' => ['required', 'integer'],
            'public_key' => self::KEY,
        ]);

        $profile = DiscoveryProfile::where('event_id', $event->id)
            ->where('attendee_roster_id', $data['attendee_roster_id'])
            ->alive($event)
            ->first();

        if (! $profile) {
            throw ValidationException::withMessages(['attendee_roster_id' => 'This name isn\'t linked on any device any more — you can pick it now.']);
        }

        $this->limit('device-transfer:device:'.(ApiClient::key($request) ?? $request->ip()), self::PER_DEVICE_PER_HOUR);
        $this->limit('device-transfer:profile:'.$profile->id, self::PER_PROFILE_PER_HOUR);

        [$secret, $secretHash] = DeviceTransfer::secret();

        $transfer = DB::transaction(function () use ($event, $profile, $data, $secretHash, $request) {
            $open = DeviceTransfer::where('discovery_profile_id', $profile->id)->live()->lockForUpdate()->get();

            if ($open->contains('status', 'approved')) {
                throw ValidationException::withMessages(['attendee_roster_id' => 'A move to another device is already under way. Try again in a few minutes.']);
            }

            // A newer request replaces one still waiting: the old device only ever sees the latest.
            $open->where('status', 'pending')->each->update(['status' => 'cancelled']);

            return DeviceTransfer::create([
                'event_id' => $event->id,
                'discovery_profile_id' => $profile->id,
                'transfer_id' => Str::random(40),
                'status' => 'pending',
                'requester_key' => $data['public_key'],
                'requester_secret_hash' => $secretHash,
                'requester_label' => DeviceTransfer::labelFor($request->userAgent()),
                'expires_at' => now()->addMinutes(DeviceTransfer::WAIT_MINUTES),
            ]);
        });

        return $this->json([
            'transfer_id' => $transfer->transfer_id,
            'secret' => $secret,
            'expires_at' => $transfer->expires_at->toIso8601String(),
        ], 201);
    }

    /** GET — the new device waiting: the sealed data appears here once approved. */
    public function show(Request $request, Event $event, string $transferId): JsonResponse
    {
        $transfer = $this->asRequester($request, $event, $transferId);

        return $this->json([
            'status' => $transfer->status,
            'expires_at' => $transfer->expires_at->toIso8601String(),
            ...($transfer->status === 'approved' ? [
                'sender_key' => $transfer->sender_key,
                'iv' => $transfer->iv,
                'payload' => $transfer->payload,
            ] : []),
        ]);
    }

    /**
     * POST — the new device has opened the data. The profile gets a new owner
     * token (only this device learns it), the sealed data is deleted, and the
     * old device's token stops working.
     */
    public function complete(Request $request, Event $event, string $transferId): JsonResponse
    {
        $result = DB::transaction(function () use ($request, $event, $transferId) {
            $transfer = $this->asRequester($request, $event, $transferId, lock: true);

            if ($transfer->status !== 'approved') {
                abort(409);
            }

            $credentials = DiscoveryProfile::generateCredentials();
            $profile = $transfer->profile()->lockForUpdate()->firstOrFail();
            $profile->update(['owner_token_hash' => $credentials['owner_token_hash']]);

            $transfer->update([
                'status' => 'completed',
                'payload' => null,
                'iv' => null,
                'expires_at' => now()->addMinutes(DeviceTransfer::KEEP_DONE_MINUTES),
            ]);

            return ['discovery_id' => $profile->discovery_id, 'owner_token' => $credentials['owner_token']];
        });

        return $this->json($result);
    }

    /** DELETE — the new device gave up waiting. */
    public function destroy(Request $request, Event $event, string $transferId): JsonResponse
    {
        $transfer = $this->asRequester($request, $event, $transferId);

        if (in_array($transfer->status, ['pending', 'approved'], true)) {
            $transfer->delete();
        }

        return $this->json(null, 204);
    }

    // ---- The old device (holds the profile's owner token) -------------------

    /** GET — is another device asking for this profile? The latest waiting request, if any. */
    public function pending(Request $request, Event $event, string $discoveryId): JsonResponse
    {
        $profile = $this->owned($request, $event, $discoveryId);

        $transfer = DeviceTransfer::where('discovery_profile_id', $profile->id)
            ->where('status', 'pending')
            ->live()
            ->latest('id')
            ->first();

        return $this->json(['transfer' => $transfer ? [
            'transfer_id' => $transfer->transfer_id,
            'requester_key' => $transfer->requester_key,
            'requester_label' => $transfer->requester_label,
            'created_at' => $transfer->created_at->toIso8601String(),
            'expires_at' => $transfer->expires_at->toIso8601String(),
        ] : null]);
    }

    /** POST — approved: the sealed data for the new device. */
    public function approve(Request $request, Event $event, string $discoveryId, string $transferId): JsonResponse
    {
        $profile = $this->owned($request, $event, $discoveryId);

        $data = $request->validate([
            'sender_key' => self::KEY,
            'iv' => ['required', 'string', 'size:16', 'regex:/^[A-Za-z0-9_-]+$/'],
            'payload' => ['required', 'string', 'max:'.DeviceTransfer::MAX_PAYLOAD, 'regex:/^[A-Za-z0-9_-]+$/'],
        ]);

        [$secret, $secretHash] = DeviceTransfer::secret();

        DB::transaction(function () use ($profile, $transferId, $data, $secretHash) {
            $transfer = $this->pendingFor($profile, $transferId);

            $transfer->update([
                'status' => 'approved',
                'sender_key' => $data['sender_key'],
                'sender_secret_hash' => $secretHash,
                'iv' => $data['iv'],
                'payload' => $data['payload'],
                'expires_at' => now()->addMinutes(DeviceTransfer::WAIT_MINUTES),
            ]);
        });

        return $this->json(['secret' => $secret]);
    }

    /** POST — not me: the request is turned down. */
    public function decline(Request $request, Event $event, string $discoveryId, string $transferId): JsonResponse
    {
        $profile = $this->owned($request, $event, $discoveryId);

        DB::transaction(function () use ($profile, $transferId) {
            $this->pendingFor($profile, $transferId)->update([
                'status' => 'declined',
                'expires_at' => now()->addMinutes(DeviceTransfer::WAIT_MINUTES),
            ]);
        });

        return $this->json(null, 204);
    }

    /**
     * GET — the old device, after approving, waiting to hear the move is done
     * (its owner token no longer works by then, so it asks with its own secret).
     */
    public function senderStatus(Request $request, Event $event, string $transferId): JsonResponse
    {
        $transfer = DeviceTransfer::where('event_id', $event->id)->where('transfer_id', $transferId)->live()->first();

        abort_unless($transfer && DeviceTransfer::secretMatches($transfer->sender_secret_hash, $request->bearerToken()), 404);

        return $this->json(['status' => $transfer->status]);
    }

    // ---- Helpers -------------------------------------------------------------

    private function asRequester(Request $request, Event $event, string $transferId, bool $lock = false): DeviceTransfer
    {
        $transfer = DeviceTransfer::where('event_id', $event->id)
            ->where('transfer_id', $transferId)
            ->live()
            ->when($lock, fn ($q) => $q->lockForUpdate())
            ->first();

        // The same 404 whether it never existed, has expired or the secret is wrong.
        abort_unless($transfer && DeviceTransfer::secretMatches($transfer->requester_secret_hash, $request->bearerToken()), 404);

        return $transfer;
    }

    private function owned(Request $request, Event $event, string $discoveryId): DiscoveryProfile
    {
        $profile = DiscoveryProfile::where('event_id', $event->id)->where('discovery_id', $discoveryId)->first();
        $token = $request->bearerToken();

        abort_if(! $profile || ! $token || ! $profile->ownerTokenMatches($token), 403);

        return $profile;
    }

    private function pendingFor(DiscoveryProfile $profile, string $transferId): DeviceTransfer
    {
        $transfer = DeviceTransfer::where('discovery_profile_id', $profile->id)
            ->where('transfer_id', $transferId)
            ->live()
            ->lockForUpdate()
            ->first();

        if (! $transfer || $transfer->status !== 'pending') {
            throw ValidationException::withMessages(['transfer' => 'That request has expired or was replaced by a newer one.']);
        }

        return $transfer;
    }

    private function limit(string $key, int $perHour): void
    {
        if (RateLimiter::tooManyAttempts($key, $perHour)) {
            throw ValidationException::withMessages(['attendee_roster_id' => 'Too many requests for now. Try again in a little while.']);
        }

        RateLimiter::hit($key, 3600);
    }

    /** Never cached anywhere: each answer is for one device, right now. */
    private function json(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status)->header('Cache-Control', 'no-store, max-age=0');
    }
}
