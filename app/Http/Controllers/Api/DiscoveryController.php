<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDiscoveryRequest;
use App\Http\Requests\UpdateDiscoveryRequest;
use App\Models\AttendeeRoster;
use App\Models\DiscoveryProfile;
use App\Models\Event;
use App\Support\EventTime;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The anonymous-discovery matching API. The
 * public discovery_id authorizes nothing by itself — every mutating call
 * requires the one-time-shown owner token as a bearer credential.
 */
class DiscoveryController extends Controller
{
    /** Shown when someone picks a name another profile already claimed. */
    private const CLAIMED = 'Someone has already linked this name to their discovery profile. If that wasn\'t you, ask an organizer — meanwhile you can type your name instead.';

    /**
     * GET — every active profile's public card (DiscoveryProfile::publicCard):
     * what each attendee chose to share. Never owner_token_hash. Client-side
     * tag-overlap matching runs on this list.
     */
    public function index(Event $event): JsonResponse
    {
        $profiles = DiscoveryProfile::where('event_id', $event->id)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->with('rosterEntry:id,name,gravatar_url,links,is_suppressed')
            // Bounded, so one response can't grow without limit. Far above any
            // WordCamp's opted-in attendee count; newest first if ever reached.
            ->latest('id')
            ->limit(2000)
            ->get();

        return response()->json(['data' => $profiles->map->publicCard()->values()]);
    }

    /**
     * POST — "Join attendee discovery". Returns the owner
     * token exactly once; the client must store it locally, since it is
     * never recoverable from the server again.
     */
    public function store(StoreDiscoveryRequest $request, Event $event): JsonResponse
    {
        $credentials = DiscoveryProfile::generateCredentials();
        $entry = $this->claimableEntry($event, $request->input('attendee_roster_id'));

        try {
            $profile = DiscoveryProfile::create([
                'discovery_id' => $credentials['discovery_id'],
                'owner_token_hash' => $credentials['owner_token_hash'],
                'event_id' => $event->id,
                'attendee_roster_id' => $entry?->id,
                'fields' => $this->fields($request, $entry),
                // The end of the event's last day at the venue, not on the server's clock.
                'expires_at' => EventTime::endOfLastDay($event),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Two people picked the same name at the same moment.
            throw ValidationException::withMessages(['attendee_roster_id' => self::CLAIMED]);
        }

        return response()->json([
            ...$profile->load('rosterEntry')->publicCard(),
            'owner_token' => $credentials['owner_token'],
        ], 201);
    }

    /**
     * PATCH — update the exposed fields. Requires the owner token; a
     * missing or wrong token gets a generic 403.
     */
    public function update(UpdateDiscoveryRequest $request, Event $event, string $discoveryId): JsonResponse
    {
        $profile = $this->authorizedProfile($event, $discoveryId, $request);
        $entry = $this->claimableEntry($event, $request->input('attendee_roster_id'), $profile);

        try {
            $profile->update([
                'attendee_roster_id' => $entry?->id,
                'fields' => $this->fields($request, $entry),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['attendee_roster_id' => self::CLAIMED]);
        }

        return response()->json($profile->load('rosterEntry')->publicCard());
    }

    /**
     * DELETE — "Leave attendee discovery". Same owner-token
     * requirement as PATCH. Leaving also frees the attendee-list name.
     */
    public function destroy(Request $request, Event $event, string $discoveryId): JsonResponse
    {
        $profile = $this->authorizedProfile($event, $discoveryId, $request);
        $profile->delete();

        return response()->json(null, 204);
    }

    /**
     * What's stored as the profile's shared fields. A typed name is only
     * kept when no attendee-list entry was picked — the list's own name wins,
     * so a claimed profile can't show a different name than the list does.
     *
     * @return array<string, mixed>
     */
    private function fields(StoreDiscoveryRequest $request, ?AttendeeRoster $entry): array
    {
        return array_filter([
            'tags' => array_values(array_unique($request->input('tags'))),
            'profession' => $request->input('profession'),
            'who_to_meet' => $request->input('who_to_meet'),
            'display_name' => $entry ? null : $request->input('display_name'),
            'wporg_username' => $request->input('wporg_username'),
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * The attendee-list entry being picked, if it may be: it must be this
     * event's, still public, and not already claimed by another profile.
     */
    private function claimableEntry(Event $event, mixed $id, ?DiscoveryProfile $self = null): ?AttendeeRoster
    {
        if ($id === null) {
            return null;
        }

        $entry = AttendeeRoster::where('event_id', $event->id)
            ->where('is_suppressed', false)
            ->find($id);

        if (! $entry) {
            throw ValidationException::withMessages(['attendee_roster_id' => 'That name isn\'t on this event\'s attendee list any more — refresh and try again.']);
        }

        $claimedByOther = DiscoveryProfile::where('attendee_roster_id', $entry->id)
            ->when($self, fn ($q) => $q->whereKeyNot($self->getKey()))
            ->exists();

        if ($claimedByOther) {
            throw ValidationException::withMessages(['attendee_roster_id' => self::CLAIMED]);
        }

        return $entry;
    }

    private function authorizedProfile(Event $event, string $discoveryId, Request $request): DiscoveryProfile
    {
        $profile = DiscoveryProfile::where('event_id', $event->id)
            ->where('discovery_id', $discoveryId)
            ->first();

        $token = $request->bearerToken();

        // Deliberately the same generic 403 whether the profile is
        // missing or the token is wrong — never hint which.
        abort_if(! $profile || ! $token || ! $profile->ownerTokenMatches($token), 403);

        return $profile;
    }
}
