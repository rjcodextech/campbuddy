<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDiscoveryRequest;
use App\Http\Requests\UpdateDiscoveryRequest;
use App\Models\DiscoveryProfile;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The anonymous-discovery matching API (§3.4, §8.3, §8.6, §10). The
 * public discovery_id authorizes nothing by itself — every mutating call
 * requires the one-time-shown owner token as a bearer credential.
 */
class DiscoveryController extends Controller
{
    /**
     * GET — every active profile's public fields. Never owner_token_hash
     * (hidden on the model) — client-side tag-overlap matching runs on
     * this list (§3.4 M2).
     */
    public function index(Event $event): JsonResponse
    {
        $profiles = DiscoveryProfile::where('event_id', $event->id)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->get(['discovery_id', 'fields']);

        return response()->json(['data' => $profiles]);
    }

    /**
     * POST — "Join attendee discovery" (§3.4 M2). Returns the owner
     * token exactly once; the client must store it locally, since it is
     * never recoverable from the server again (§8.6, §3.4 M8).
     */
    public function store(StoreDiscoveryRequest $request, Event $event): JsonResponse
    {
        $credentials = DiscoveryProfile::generateCredentials();

        $profile = DiscoveryProfile::create([
            'discovery_id' => $credentials['discovery_id'],
            'owner_token_hash' => $credentials['owner_token_hash'],
            'event_id' => $event->id,
            'fields' => $request->only(['tags', 'profession', 'who_to_meet']),
            'expires_at' => $event->ends_on?->endOfDay(),
        ]);

        return response()->json([
            'discovery_id' => $profile->discovery_id,
            'owner_token' => $credentials['owner_token'],
            'fields' => $profile->fields,
        ], 201);
    }

    /**
     * PATCH — update the exposed fields. Requires the owner token; a
     * missing or wrong token gets a generic 403 (§10 — no hint about
     * which part was wrong).
     */
    public function update(UpdateDiscoveryRequest $request, Event $event, string $discoveryId): JsonResponse
    {
        $profile = $this->authorizedProfile($event, $discoveryId, $request);

        $profile->update(['fields' => $request->only(['tags', 'profession', 'who_to_meet'])]);

        return response()->json(['discovery_id' => $profile->discovery_id, 'fields' => $profile->fields]);
    }

    /**
     * DELETE — "Leave attendee discovery" (§3.4 M6). Same owner-token
     * requirement as PATCH.
     */
    public function destroy(Request $request, Event $event, string $discoveryId): JsonResponse
    {
        $profile = $this->authorizedProfile($event, $discoveryId, $request);
        $profile->delete();

        return response()->json(null, 204);
    }

    private function authorizedProfile(Event $event, string $discoveryId, Request $request): DiscoveryProfile
    {
        $profile = DiscoveryProfile::where('event_id', $event->id)
            ->where('discovery_id', $discoveryId)
            ->first();

        $token = $request->bearerToken();

        // Deliberately the same generic 403 whether the profile is
        // missing or the token is wrong — §10 says never hint which.
        abort_if(! $profile || ! $token || ! $profile->ownerTokenMatches($token), 403);

        return $profile;
    }
}
