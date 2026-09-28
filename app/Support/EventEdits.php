<?php

namespace App\Support;

use App\Models\Event;

/**
 * How a hand-edited form becomes what is stored on an event — used by the
 * event manager's pages, and written to match what the admin's own event
 * pages do with the same input (a test posts one payload to both and compares
 * the result).
 */
class EventEdits
{
    /**
     * A time zone typed by someone is theirs to keep (timezone_locked): the
     * WordCamp site's own setting never overwrites it. Left blank, the zone
     * is read from the site again on the next fetch.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function details(array $data, Event $event): array
    {
        $data = self::dates($data, $event);

        if (! array_key_exists('timezone', $data)) {
            return $data;
        }

        $zone = EventTime::normalize($data['timezone']);

        if ($zone === null) {
            // Unlocking keeps the last known zone until the next fetch reads the site's.
            $data['timezone'] = $event->timezone_locked ? null : $event->timezone;
            $data['timezone_locked'] = false;
        } else {
            $data['timezone'] = $zone;
            $data['timezone_locked'] = true;
        }

        return $data;
    }

    /**
     * Dates typed by someone are theirs to keep (dates_locked): the daily fetch
     * from central.wordcamp.org no longer changes them. Both left blank, the
     * dates are read from central again. Saving the form without touching the
     * dates changes nothing, so an untouched form never locks them.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function dates(array $data, ?Event $event): array
    {
        if (! array_key_exists('starts_on', $data) && ! array_key_exists('ends_on', $data)) {
            return $data;
        }

        $day = fn (mixed $value): ?string => blank($value) ? null : \Carbon\CarbonImmutable::parse($value)->toDateString();
        $typed = [$day($data['starts_on'] ?? null), $day($data['ends_on'] ?? null)];

        if ($typed === [null, null]) {
            $data['dates_locked'] = false;
        } elseif ($event === null || $typed !== [$event->starts_on?->toDateString(), $event->ends_on?->toDateString()]) {
            $data['dates_locked'] = true;
        }

        return $data;
    }

    /**
     * Blank fields are dropped entirely, not stored as empty strings — the
     * attendee view only checks whether a key exists. Line endings are
     * normalised so an untouched textarea (browsers send CRLF) still equals
     * what the auto-fetch stored, and isn't mistaken for an edit.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    public static function info(array $fields): array
    {
        return array_filter(
            array_map(fn ($v) => is_string($v) ? trim(str_replace("\r\n", "\n", $v)) : $v, $fields),
            fn ($v) => filled($v)
        );
    }
}
