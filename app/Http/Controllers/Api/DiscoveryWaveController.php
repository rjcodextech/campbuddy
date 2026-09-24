<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DiscoveryProfile;
use App\Models\DiscoveryWave;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Waves between discovery matches — the way two people can find each other
 * even when one or both joined anonymously.
 *
 *   - Waving tells the other person only that *a match* would like to meet
 *     (they see which card, which is already public — never a name).
 *   - Once both have waved, each sees the name and "where to meet" message
 *     the other gave with their wave. Everyone else still sees the same
 *     anonymous card as before.
 *
 * Every call acts as {discoveryId} and needs its owner token, like editing
 * the profile itself; a wrong or missing token gets the same generic 403.
 */
class DiscoveryWaveController extends Controller
{
    /** Plenty for one event; stops a profile waving at everyone. */
    private const MAX_WAVES = 40;

    public function index(Request $request, Event $event, string $discoveryId): JsonResponse
    {
        return response()->json($this->state($this->me($event, $discoveryId, $request)));
    }

    public function store(Request $request, Event $event, string $discoveryId): JsonResponse
    {
        $me = $this->me($event, $discoveryId, $request);

        $data = $request->validate([
            'to' => ['required', 'string', 'size:64'],
            'name' => ['nullable', 'string', 'max:60'],
            'message' => ['nullable', 'string', 'max:140'],
        ]);

        $target = $this->active($event)->where('discovery_id', $data['to'])->first();

        if (! $target || $target->is($me)) {
            throw ValidationException::withMessages(['to' => 'That attendee has left discovery.']);
        }

        // An anonymous profile has no name to show — ask for the one to reveal.
        $name = trim((string) ($data['name'] ?? '')) ?: null;
        if ($name === null && $me->load('rosterEntry')->publicCard()['name'] === null) {
            throw ValidationException::withMessages(['name' => 'Add your first name — they only see it if they wave back.']);
        }

        $exists = DiscoveryWave::where('from_profile_id', $me->id)->where('to_profile_id', $target->id)->exists();
        if (! $exists && DiscoveryWave::where('from_profile_id', $me->id)->count() >= self::MAX_WAVES) {
            throw ValidationException::withMessages(['to' => 'That\'s a lot of waves already — wait for a few to wave back.']);
        }

        DiscoveryWave::updateOrCreate(
            ['from_profile_id' => $me->id, 'to_profile_id' => $target->id],
            [
                'event_id' => $event->id,
                'reveal_name' => $name,
                'message' => trim((string) ($data['message'] ?? '')) ?: null,
            ]
        );

        return response()->json($this->state($me), 201);
    }

    public function destroy(Request $request, Event $event, string $discoveryId, string $targetId): JsonResponse
    {
        $me = $this->me($event, $discoveryId, $request);
        $target = DiscoveryProfile::where('event_id', $event->id)->where('discovery_id', $targetId)->first();

        if ($target) {
            DiscoveryWave::where('from_profile_id', $me->id)->where('to_profile_id', $target->id)->delete();
        }

        return response()->json($this->state($me));
    }

    /**
     * What this profile may know: whom it waved at, which matches waved at it,
     * and — for mutual waves only — the other person's name and message.
     *
     * @return array{sent: array<int, string>, received: array<int, string>, mutual: array<int, array<string, ?string>>}
     */
    private function state(DiscoveryProfile $me): array
    {
        $activeIds = $this->active($me->event)->pluck('id');

        $sent = DiscoveryWave::where('from_profile_id', $me->id)->whereIn('to_profile_id', $activeIds)
            ->with('to:id,discovery_id')->get();
        $received = DiscoveryWave::where('to_profile_id', $me->id)->whereIn('from_profile_id', $activeIds)
            ->with('from.rosterEntry')->get();

        $sentTo = $sent->pluck('to.discovery_id')->filter()->values();

        $mutual = $received
            ->filter(fn (DiscoveryWave $w) => $sentTo->contains($w->from?->discovery_id))
            ->map(fn (DiscoveryWave $w) => [
                'discovery_id' => $w->from->discovery_id,
                'name' => $w->reveal_name ?: $w->from->publicCard()['name'],
                'message' => $w->message,
                'my_message' => $sent->firstWhere('to.discovery_id', $w->from->discovery_id)?->message,
            ])
            ->values();

        return [
            'sent' => $sentTo->all(),
            'received' => $received->pluck('from.discovery_id')->filter()
                ->reject(fn ($id) => $sentTo->contains($id))->values()->all(),
            'mutual' => $mutual->all(),
        ];
    }

    private function active(Event $event)
    {
        return DiscoveryProfile::where('event_id', $event->id)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    private function me(Event $event, string $discoveryId, Request $request): DiscoveryProfile
    {
        $profile = DiscoveryProfile::where('event_id', $event->id)->where('discovery_id', $discoveryId)->first();
        $token = $request->bearerToken();

        abort_if(! $profile || ! $token || ! $profile->ownerTokenMatches($token), 403);

        return $profile;
    }
}
