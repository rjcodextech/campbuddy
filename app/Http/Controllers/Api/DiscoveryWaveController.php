<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DiscoveryMessage;
use App\Models\DiscoveryProfile;
use App\Models\DiscoveryWave;
use App\Models\Event;
use App\Support\ChatWindow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Waves and short messages between discovery matches — how two people find
 * each other even when one or both joined anonymously.
 *
 *   - Waving tells the other person only that *a match* would like to meet
 *     (they see which card, which is already public — never a name). A wave
 *     can carry a first message; they can read it once they wave back.
 *   - Once both have waved, each sees the name the other gave, and they can
 *     exchange a few messages to agree where to meet:
 *       · only while the event is on — each day from an hour before its first
 *         session to an hour after its last, in the event's time zone
 *         (ChatWindow). Outside that, nobody can send, whatever their count;
 *       · at most three messages each per event day (CampBuddy is a guide,
 *         not a chat app — after that they swap Camp Cards);
 *       · taking turns within the day: you can't send again until the other
 *         person replies.
 *     Everyone else still sees the same anonymous cards as before.
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
            'message' => ['nullable', 'string', 'max:'.DiscoveryMessage::MAX_LENGTH],
        ]);

        $target = $this->active($event)->where('discovery_id', $data['to'])->first();

        if (! $target || $target->is($me)) {
            throw ValidationException::withMessages(['to' => 'That attendee has left discovery.']);
        }

        // An anonymous profile has no name to show — ask for the one to reveal.
        $name = $this->clean($data['name'] ?? null);
        if ($name === null && $me->load('rosterEntry')->publicCard()['name'] === null) {
            throw ValidationException::withMessages(['name' => 'Add your first name — they only see it if they wave back.']);
        }

        $message = $this->clean($data['message'] ?? null);
        $window = ChatWindow::for($event);

        // A wave is fine any time; words only while the chat is open.
        if ($message !== null && $window->current() === null) {
            throw ValidationException::withMessages(['message' => $this->closedMessage($window)]);
        }

        DB::transaction(function () use ($me, $target, $event, $name, $message, $window) {
            if (DiscoveryWave::where('from_profile_id', $me->id)->where('to_profile_id', $target->id)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['to' => 'You\'ve already waved — wait for them to wave back.']);
            }

            if (DiscoveryWave::where('from_profile_id', $me->id)->count() >= self::MAX_WAVES) {
                throw ValidationException::withMessages(['to' => 'That\'s a lot of waves already — wait for a few to wave back.']);
            }

            DiscoveryWave::create([
                'event_id' => $event->id,
                'from_profile_id' => $me->id,
                'to_profile_id' => $target->id,
                'reveal_name' => $name,
            ]);

            if ($message !== null) {
                $this->sendChecked($me, $target, $event, $message, $window);
            }
        });

        return response()->json($this->state($me), 201);
    }

    /** POST — one more message in a mutual conversation (turn and limit checked). */
    public function message(Request $request, Event $event, string $discoveryId): JsonResponse
    {
        $me = $this->me($event, $discoveryId, $request);

        $data = $request->validate([
            'to' => ['required', 'string', 'size:64'],
            'body' => ['required', 'string', 'max:'.DiscoveryMessage::MAX_LENGTH],
        ]);

        $body = $this->clean($data['body']);
        if ($body === null) {
            throw ValidationException::withMessages(['body' => 'Write a short message first.']);
        }

        $target = $this->active($event)->where('discovery_id', $data['to'])->first();
        if (! $target || $target->is($me)) {
            throw ValidationException::withMessages(['to' => 'That attendee has left discovery.']);
        }

        $window = ChatWindow::for($event);

        DB::transaction(function () use ($me, $target, $event, $body, $window) {
            // Locking both waves serialises two sends in the same conversation,
            // so a double tap can't slip past the turn or the limit.
            $waves = DiscoveryWave::where(fn ($q) => $q->where('from_profile_id', $me->id)->where('to_profile_id', $target->id))
                ->orWhere(fn ($q) => $q->where('from_profile_id', $target->id)->where('to_profile_id', $me->id))
                ->lockForUpdate()
                ->count();

            if ($waves < 2) {
                throw ValidationException::withMessages(['to' => 'You can message each other once you\'ve both waved.']);
            }

            $this->sendChecked($me, $target, $event, $body, $window);
        });

        return response()->json($this->state($me), 201);
    }

    public function destroy(Request $request, Event $event, string $discoveryId, string $targetId): JsonResponse
    {
        $me = $this->me($event, $discoveryId, $request);
        $target = DiscoveryProfile::where('event_id', $event->id)->where('discovery_id', $targetId)->first();

        if ($target) {
            DB::transaction(function () use ($me, $target) {
                DiscoveryWave::where('from_profile_id', $me->id)->where('to_profile_id', $target->id)->delete();
                // Taking a wave back ends the conversation for both.
                $this->between($me->id, $target->id)->delete();
            });
        }

        return response()->json($this->state($me));
    }

    /**
     * Stores a message if the rules allow it right now; otherwise says why
     * not. Checked against the clock at the moment of sending, so a phone
     * left open past closing time can't slip one in.
     */
    private function sendChecked(DiscoveryProfile $me, DiscoveryProfile $target, Event $event, string $body, ChatWindow $window): void
    {
        $open = $window->current();

        if ($open === null) {
            throw ValidationException::withMessages(['body' => $this->closedMessage($window)]);
        }

        $today = $this->inWindow($this->between($me->id, $target->id)->orderBy('id')->get(['from_profile_id', 'created_at']), $open);
        $rules = $this->rules($today, $me->id, true, true);

        if (! $rules['can_send']) {
            throw ValidationException::withMessages(['body' => match ($rules['reason']) {
                'limit' => 'You\'ve sent all '.DiscoveryMessage::MAX_PER_PERSON.' of today\'s messages — swap Camp Cards to keep talking.',
                default => 'Wait for their reply before sending another message.',
            }]);
        }

        DiscoveryMessage::create([
            'event_id' => $event->id,
            'from_profile_id' => $me->id,
            'to_profile_id' => $target->id,
            'body' => $body,
        ]);
    }

    private function closedMessage(ChatWindow $window): string
    {
        $status = $window->status();

        return match (true) {
            $status['opens_label'] !== null => "Messages open during the event — next at {$status['opens_label']} (event time).",
            $status['ended'] => 'The event is over, so messages are closed. Use Camp Cards to stay in touch.',
            default => 'Messages open during the event, once its schedule is published.',
        };
    }

    /**
     * The messages sent inside one day's window — the day's count and turn
     * only look at these. Compared as absolute moments.
     */
    private function inWindow(Collection $messages, ?array $window): Collection
    {
        if ($window === null) {
            return collect();
        }

        return $messages->filter(fn ($m) => $m->created_at !== null
            && $m->created_at->gte($window['opens'])
            && $m->created_at->lt($window['closes']))->values();
    }

    /**
     * Whose turn it is. $messages: today's part of the conversation, oldest first.
     *
     * @return array{mine: int, theirs: int, can_send: bool, reason: ?string}
     */
    private function rules(Collection $messages, int $meId, bool $mutual, bool $open): array
    {
        $mine = $messages->where('from_profile_id', $meId)->count();
        $lastIsMine = $messages->isNotEmpty() && $messages->last()->from_profile_id === $meId;

        $reason = match (true) {
            ! $open => 'closed',
            $mine >= DiscoveryMessage::MAX_PER_PERSON => 'limit',
            ! $mutual => 'not_mutual',
            $lastIsMine => 'waiting',
            default => null,
        };

        return [
            'mine' => $mine,
            'theirs' => $messages->count() - $mine,
            'can_send' => $reason === null,
            'reason' => $reason,
        ];
    }

    /**
     * What this profile may know: whom it waved at (with the messages it sent
     * them), which matches waved at it (and whether they wrote — not what),
     * and, for mutual waves, the other's name and the whole short thread.
     *
     * Four queries however many matches: waves out, waves in, one batch of
     * messages, and the active-profile filter.
     */
    private function state(DiscoveryProfile $me): array
    {
        $window = ChatWindow::for($me->event);
        $open = $window->current();
        $activeIds = $this->active($me->event)->pluck('id')->flip();

        $sent = DiscoveryWave::where('from_profile_id', $me->id)->with('to:id,discovery_id')->get()
            ->filter(fn ($w) => $activeIds->has($w->to_profile_id));
        $received = DiscoveryWave::where('to_profile_id', $me->id)->with('from.rosterEntry')->get()
            ->filter(fn ($w) => $activeIds->has($w->from_profile_id));

        $messages = DiscoveryMessage::where('from_profile_id', $me->id)->orWhere('to_profile_id', $me->id)
            ->orderBy('id')
            ->get(['id', 'from_profile_id', 'to_profile_id', 'body', 'created_at'])
            ->groupBy(fn ($m) => $m->from_profile_id === $me->id ? $m->to_profile_id : $m->from_profile_id);

        $sentTo = $sent->pluck('to_profile_id')->flip();
        $thread = fn (int $otherId) => ($messages[$otherId] ?? collect())->values();
        $publicMessages = fn (Collection $list) => $list->map(fn ($m) => [
            'mine' => $m->from_profile_id === $me->id,
            'body' => $m->body,
            'at' => $m->created_at?->toIso8601String(),
            // The event day it was sent on, in the event's own time zone.
            'day' => $m->created_at ? $window->localDay($m->created_at) : null,
        ])->all();

        $mutual = $received->filter(fn ($w) => $sentTo->has($w->from_profile_id))->map(function ($w) use ($me, $thread, $publicMessages, $open) {
            $list = $thread($w->from_profile_id);

            return [
                'discovery_id' => $w->from->discovery_id,
                'name' => $w->reveal_name ?: $w->from->publicCard()['name'],
                'messages' => $publicMessages($list),
                // Counts and turn are today's (the open window's) only.
                ...$this->rules($this->inWindow($list, $open), $me->id, true, $open !== null),
            ];
        })->values();

        $mutualIds = $received->pluck('from_profile_id')->flip();
        $pending = $sent->reject(fn ($w) => $mutualIds->has($w->to_profile_id))->map(fn ($w) => [
            // Only ever my own messages here — theirs wait for the wave back.
            'discovery_id' => $w->to->discovery_id,
            'messages' => $publicMessages($thread($w->to_profile_id)),
        ])->values();

        $waiting = $received->reject(fn ($w) => $sentTo->has($w->from_profile_id));

        return [
            'max_messages' => DiscoveryMessage::MAX_PER_PERSON,
            'chat' => $window->status(),
            'sent' => $sent->pluck('to.discovery_id')->values()->all(),
            'received' => $waiting->pluck('from.discovery_id')->values()->all(),
            // They wrote something: "wave back to read it" — never the words.
            'received_with_message' => $waiting->filter(fn ($w) => $thread($w->from_profile_id)->isNotEmpty())
                ->pluck('from.discovery_id')->values()->all(),
            'pending' => $pending->all(),
            'mutual' => $mutual->all(),
        ];
    }

    /** Messages either way between two profiles. */
    private function between(int $a, int $b): Builder
    {
        return DiscoveryMessage::query()->where(fn ($q) => $q
            ->where(fn ($q) => $q->where('from_profile_id', $a)->where('to_profile_id', $b))
            ->orWhere(fn ($q) => $q->where('from_profile_id', $b)->where('to_profile_id', $a)));
    }

    /** Plain single-line text: control characters out, spaces collapsed. */
    private function clean(?string $text): ?string
    {
        $text = preg_replace('/[\p{Cc}\p{Cf}]+/u', ' ', (string) $text) ?? '';
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        return $text === '' ? null : mb_substr($text, 0, DiscoveryMessage::MAX_LENGTH);
    }

    private function active(Event $event): Builder
    {
        return DiscoveryProfile::where('event_id', $event->id)->alive($event);
    }

    private function me(Event $event, string $discoveryId, Request $request): DiscoveryProfile
    {
        $profile = DiscoveryProfile::where('event_id', $event->id)->where('discovery_id', $discoveryId)->first();
        $token = $request->bearerToken();

        abort_if(! $profile || ! $token || ! $profile->ownerTokenMatches($token), 403);

        return $profile;
    }
}
