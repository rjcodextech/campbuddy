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

    /** Today's date at the venue ("Y-m-d"). */
    public static function today(Event $event, ?\DateTimeInterface $now = null): string
    {
        return \Carbon\CarbonImmutable::instance($now ?? now())->setTimezone(self::zone($event))->toDateString();
    }

    /** Whether the event's last day (end date, or start date) is over at the venue. */
    public static function isOver(Event $event, ?\DateTimeInterface $now = null): bool
    {
        $last = ($event->ends_on ?? $event->starts_on)?->toDateString();

        return $last !== null && $last < self::today($event, $now);
    }

    /** The last moment of the event's final day at the venue (for expiring discovery profiles). */
    public static function endOfLastDay(Event $event): ?\Carbon\CarbonImmutable
    {
        $last = ($event->ends_on ?? $event->starts_on)?->toDateString();

        return $last === null ? null : \Carbon\CarbonImmutable::parse($last.' 23:59:59', self::zone($event))->utc();
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

    /**
     * Abbreviations WordCamp schedule pages print after times, and the zone
     * each means at WordCamps. Only a last resort — the site's own setting
     * and central.wordcamp.org come first — because some are ambiguous
     * worldwide (IST is India here; CST is US Central).
     */
    private const ABBREVIATIONS = [
        'IST' => 'Asia/Kolkata', 'PKT' => 'Asia/Karachi', 'NPT' => 'Asia/Kathmandu', 'BDT' => 'Asia/Dhaka',
        'SLST' => 'Asia/Colombo', 'ICT' => 'Asia/Bangkok', 'WIB' => 'Asia/Jakarta', 'WITA' => 'Asia/Makassar',
        'SGT' => 'Asia/Singapore', 'MYT' => 'Asia/Kuala_Lumpur', 'PHT' => 'Asia/Manila', 'HKT' => 'Asia/Hong_Kong',
        'JST' => 'Asia/Tokyo', 'KST' => 'Asia/Seoul', 'GST' => 'Asia/Dubai', 'IRST' => 'Asia/Tehran', 'TRT' => 'Europe/Istanbul',
        'WET' => 'Europe/Lisbon', 'WEST' => 'Europe/Lisbon', 'BST' => 'Europe/London', 'GMT' => 'Europe/London',
        'CET' => 'Europe/Berlin', 'CEST' => 'Europe/Berlin', 'EET' => 'Europe/Athens', 'EEST' => 'Europe/Athens', 'MSK' => 'Europe/Moscow',
        'SAST' => 'Africa/Johannesburg', 'EAT' => 'Africa/Nairobi', 'WAT' => 'Africa/Lagos', 'CAT' => 'Africa/Harare',
        'EST' => 'America/New_York', 'EDT' => 'America/New_York', 'CST' => 'America/Chicago', 'CDT' => 'America/Chicago',
        'MST' => 'America/Denver', 'MDT' => 'America/Denver', 'PST' => 'America/Los_Angeles', 'PDT' => 'America/Los_Angeles',
        'AKST' => 'America/Anchorage', 'HST' => 'Pacific/Honolulu', 'AST' => 'America/Halifax', 'ADT' => 'America/Halifax',
        'BRT' => 'America/Sao_Paulo', 'ART' => 'America/Argentina/Buenos_Aires', 'CLT' => 'America/Santiago', 'COT' => 'America/Bogota',
        'PET' => 'America/Lima',
        'AEST' => 'Australia/Sydney', 'AEDT' => 'Australia/Sydney', 'ACST' => 'Australia/Adelaide', 'ACDT' => 'Australia/Adelaide',
        'AWST' => 'Australia/Perth', 'NZST' => 'Pacific/Auckland', 'NZDT' => 'Pacific/Auckland',
        'UTC' => 'UTC',
    ];

    /** A zone from what a schedule page prints: "IST", "CEST", "UTC+5:30", "GMT-3". */
    public static function fromAbbreviation(?string $token): ?string
    {
        $token = strtoupper(trim((string) $token));

        if ($token === '') {
            return null;
        }

        if (preg_match('/^(?:UTC|GMT)\s*([+\-−])\s*(\d{1,2})(?:\s*:?\s*(\d{2}))?$/u', $token, $m)) {
            return self::normalize(($m[1] === '-' || $m[1] === '−' ? '-' : '+').$m[2].':'.($m[3] ?? '00'));
        }

        return self::ABBREVIATIONS[$token] ?? null;
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
