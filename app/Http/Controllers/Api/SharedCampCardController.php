<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ShareCampCardRequest;
use App\Models\AttendeeRoster;
use App\Models\DiscoveryProfile;
use App\Models\Event;
use App\Models\SharedCampCard;
use App\Support\EventTime;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * "Show my Camp Card on the attendee list" (Camp Card page, card-share.js).
 * Works like discovery's join: POST returns an owner token exactly once, and
 * only that token (as a bearer credential) can change or remove the card.
 *
 * One card per attendee-list name, first come. A name someone already linked
 * to their discovery profile can only be used from that same phone — it
 * sends the discovery profile's own id and token along as proof.
 */
class SharedCampCardController extends Controller
{
    private const CLAIMED = 'Someone has already linked this name. If that wasn\'t you, ask an organizer.';

    public function store(ShareCampCardRequest $request, Event $event): JsonResponse
    {
        $entry = $this->claimableEntry($event, $request);
        $credentials = SharedCampCard::generateCredentials();

        try {
            $card = SharedCampCard::create([
                'share_id' => $credentials['share_id'],
                'owner_token_hash' => $credentials['owner_token_hash'],
                'event_id' => $event->id,
                'attendee_roster_id' => $entry->id,
                'fields' => $request->cardFields(),
                'expires_at' => EventTime::retentionEnd($event),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['attendee_roster_id' => self::CLAIMED]);
        }

        RosterController::forget($event);

        return response()->json([...$this->reply($card), 'owner_token' => $credentials['owner_token']], 201);
    }

    public function update(ShareCampCardRequest $request, Event $event, string $shareId): JsonResponse
    {
        $card = $this->authorizedCard($event, $shareId, $request);
        $entry = $this->claimableEntry($event, $request, $card);

        try {
            $card->update(['attendee_roster_id' => $entry->id, 'fields' => $request->cardFields()]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['attendee_roster_id' => self::CLAIMED]);
        }

        RosterController::forget($event);

        return response()->json($this->reply($card));
    }

    public function destroy(Request $request, Event $event, string $shareId): JsonResponse
    {
        $this->authorizedCard($event, $shareId, $request)->delete();
        RosterController::forget($event);

        return response()->json(null, 204);
    }

    /** @return array<string, mixed> */
    private function reply(SharedCampCard $card): array
    {
        return ['share_id' => $card->share_id, 'attendee_roster_id' => $card->attendee_roster_id, 'card' => $card->publicCard()];
    }

    /**
     * The list entry being claimed: this event's, still public, not someone
     * else's card, and not someone else's discovery name.
     */
    private function claimableEntry(Event $event, ShareCampCardRequest $request, ?SharedCampCard $self = null): AttendeeRoster
    {
        $entry = AttendeeRoster::where('event_id', $event->id)
            ->where('is_suppressed', false)
            ->find($request->integer('attendee_roster_id'));

        if (! $entry) {
            throw ValidationException::withMessages(['attendee_roster_id' => 'That name isn\'t on this event\'s attendee list any more. Refresh and try again.']);
        }

        $otherCard = SharedCampCard::where('attendee_roster_id', $entry->id)
            ->when($self, fn ($q) => $q->whereKeyNot($self->getKey()))
            ->exists();

        $profile = DiscoveryProfile::where('attendee_roster_id', $entry->id)->alive($event)->first();
        $profileIsMine = $profile
            && $profile->discovery_id === $request->input('discovery_id')
            && $request->filled('discovery_token')
            && $profile->ownerTokenMatches((string) $request->input('discovery_token'));

        if ($otherCard || ($profile && ! $profileIsMine)) {
            throw ValidationException::withMessages(['attendee_roster_id' => self::CLAIMED]);
        }

        return $entry;
    }

    private function authorizedCard(Event $event, string $shareId, Request $request): SharedCampCard
    {
        $card = SharedCampCard::where('event_id', $event->id)->where('share_id', $shareId)->first();
        $token = $request->bearerToken();

        // The same 403 whether the card is missing or the token is wrong — never hint which.
        abort_if(! $card || ! $token || ! $card->ownerTokenMatches($token), 403);

        return $card;
    }
}
