<?php

namespace App\Support;

use App\Models\Event;
use DateTimeZone;
use Throwable;

/**
 * Time zones for events. A WordCamp happens somewhere: its session times,
 * what counts as "day 2", and when the discovery chat opens are all in the
 * event's own zone — never the server's and never the visitor's phone's.
 */
class EventTime
{
    /** The event's zone; the app's own (UTC by default) until one is known. */
    public static function zone(Event $event): DateTimeZone
    {
        return self::parse($event->timezone) ?? new DateTimeZone(config('app.timezone', 'UTC'));
    }

    public static function known(Event $event): bool
    {
        return self::parse($event->timezone) !== null;
    }

    /**
     * A usable zone from what a WordPress site reports: its timezone_string
     * ("Asia/Kolkata") if set, otherwise its gmt_offset in hours (5.5 →
     * "+05:30"). Null when neither makes sense.
     */
    public static function fromWordPress(mixed $timezoneString, mixed $gmtOffset): ?string
    {
        if (is_string($timezoneString) && self::isIana($timezoneString)) {
            return $timezoneString;
        }

        if (is_numeric($gmtOffset)) {
            return self::offsetName((float) $gmtOffset);
        }

        return null;
    }

    /** A valid zone name as typed or stored: an IANA name or "+HH:MM" / "-HH:MM". */
    public static function normalize(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (self::isIana($value)) {
            return $value;
        }

        if (preg_match('/^(?:UTC|GMT)?\s*([+-])(\d{1,2})(?::?(\d{2}))?$/i', $value, $m)) {
            $hours = (int) $m[2];
            $minutes = (int) ($m[3] ?? 0);

            if ($hours <= 14 && in_array($minutes, [0, 15, 30, 45], true)) {
                return sprintf('%s%02d:%02d', $m[1], $hours, $minutes);
            }
        }

        return null;
    }

    public static function parse(?string $value): ?DateTimeZone
    {
        $name = self::normalize($value);

        if ($name === null) {
            return null;
        }

        try {
            return new DateTimeZone($name);
        } catch (Throwable) {
            return null;
        }
    }

    private static function isIana(string $name): bool
    {
        return in_array($name, DateTimeZone::listIdentifiers(), true) || $name === 'UTC';
    }

    private static function offsetName(float $hours): ?string
    {
        if (abs($hours) > 14) {
            return null;
        }

        $minutes = (int) round(abs($hours) * 60);

        return sprintf('%s%02d:%02d', $hours < 0 ? '-' : '+', intdiv($minutes, 60), $minutes % 60);
    }
}
