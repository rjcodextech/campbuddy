<?php

namespace App\Support;

use App\Models\Event;
use Carbon\CarbonImmutable;

/**
 * "Running late" — a delay an admin or the event's managers set while the
 * event is on: N minutes, for the whole event or one track, for the sessions
 * starting from a given time until the end of that day (at the venue). The
 * next day starts on time again without anyone having to clear it.
 *
 * Applied wherever session times are served (EventPageController::cached(),
 * SendSessionRemindersJob), so the app's Home, My Day, calendar export and
 * reminders all use the new times; each moved session also carries its
 * original time and the minutes, so the app can show both. With no delay set
 * nothing changes.
 *
 * Stored on the event (`schedule_delays`): a list of
 * {track: ?string, minutes: int, from: ISO UTC, until: ISO UTC, note: ?string, set_by, set_at}.
 */
class ScheduleDelay
{
    public const MAX_MINUTES = 240;

    /** @return list<array<string, mixed>> the delays that still apply (their day isn't over) */
    public static function active(Event $event, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();

        return array_values(array_filter(
            is_array($event->schedule_delays ?? null) ? $event->schedule_delays : [],
            fn ($delay) => is_array($delay) && ($delay['minutes'] ?? 0) > 0 && ! empty($delay['until']) && CarbonImmutable::parse($delay['until'])->greaterThan($now)
        ));
    }

    /**
     * The sessions with every applicable delay added: the largest one that
     * covers a session (whole event or its track, starting at or after the
     * delay's "from", on that day) moves it.
     *
     * @param  array<int, mixed>  $sessions
     * @return array<int, mixed>
     */
    public static function apply(Event $event, array $sessions): array
    {
        $delays = self::active($event);
        if ($delays === []) {
            return $sessions;
        }

        return array_map(function ($session) use ($delays) {
            if (! is_array($session) || empty($session['starts_at'])) {
                return $session;
            }

            $starts = CarbonImmutable::parse($session['starts_at']);
            $minutes = 0;
            foreach ($delays as $delay) {
                $track = $delay['track'] ?? null;
                if ($track !== null && ! in_array($track, $session['track_names'] ?? [], true)) {
                    continue;
                }
                if ($starts->lessThan(CarbonImmutable::parse($delay['from'])) || $starts->greaterThanOrEqualTo(CarbonImmutable::parse($delay['until']))) {
                    continue;
                }
                $minutes = max($minutes, (int) $delay['minutes']);
            }

            if ($minutes === 0) {
                return $session;
            }

            return [
                ...$session,
                'starts_at' => $starts->addMinutes($minutes)->toIso8601String(),
                'original_starts_at' => $session['starts_at'],
                'delay_minutes' => $minutes,
            ];
        }, $sessions);
    }

    /**
     * Sets (or, with 0 minutes, clears) the delay for one track — null is the
     * whole event — from $from (a time at the venue) to the end of that day.
     */
    public static function set(Event $event, ?string $track, int $minutes, CarbonImmutable $from, ?string $note, string $by): void
    {
        $zone = EventTime::zone($event);
        $from = $from->setTimezone($zone);
        $kept = array_values(array_filter(
            self::active($event),
            fn ($delay) => ($delay['track'] ?? null) !== $track
        ));

        if ($minutes > 0) {
            $kept[] = [
                'track' => $track,
                'minutes' => min($minutes, self::MAX_MINUTES),
                'from' => $from->utc()->toIso8601String(),
                'until' => $from->endOfDay()->utc()->toIso8601String(),
                'note' => $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 140) : null,
                'set_by' => mb_substr($by, 0, 120),
                'set_at' => CarbonImmutable::now()->utc()->toIso8601String(),
            ];
        }

        $event->forceFill(['schedule_delays' => $kept ?: null])->save();
        DataVersion::forget($event->id);
    }

    public static function clear(Event $event): void
    {
        $event->forceFill(['schedule_delays' => null])->save();
        DataVersion::forget($event->id);
    }

    /** "20 min late · Track 1 · from 11:30" — for the banner and the admin pages. */
    public static function describe(Event $event, array $delay): string
    {
        $from = CarbonImmutable::parse($delay['from'])->setTimezone(EventTime::zone($event));

        return implode(' · ', array_filter([
            "{$delay['minutes']} min late",
            $delay['track'] ?? 'Whole event',
            'from '.$from->format('g:i A'),
        ]));
    }

    /** @return list<string> the track names in this event's schedule, for the form */
    public static function tracks(Event $event): array
    {
        $names = [];
        foreach (EventData::get($event->id, 'sessions') ?? [] as $session) {
            foreach ($session['track_names'] ?? [] as $name) {
                $names[$name] = true;
            }
        }
        $names = array_keys($names);
        sort($names);

        return $names;
    }
}
