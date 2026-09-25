<?php

namespace App\Support;

use App\Models\Event;
use DateTimeZone;
use Illuminate\Support\Facades\Cache;
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

    /**
     * How long after its last day an event stays live: nothing is archived and
     * no attendee-entered data (discovery profiles, waves, messages) expires
     * until this many days have passed. Scraped dates are sometimes missing or
     * a day off, and an attendee's own plans and connections must never vanish
     * while the event might still be on — or the morning after.
     */
    public const RETENTION_DAYS = 3;

    /** No WordCamp runs longer; a stray session date further out must not keep an event live. */
    public const MAX_EVENT_SPAN_DAYS = 7;

    /**
     * The event's last day at the venue ("Y-m-d"): the latest of its end date,
     * its start date and the day its last scheduled session ends. Scraped
     * events often have no end date at all (or one that is wrong), while the
     * schedule shows the real days.
     */
    public static function lastDay(Event $event): ?string
    {
        $dates = array_filter([
            $event->ends_on?->toDateString(),
            $event->starts_on?->toDateString(),
            self::lastSessionDay($event),
        ]);

        return $dates === [] ? null : max($dates);
    }

    /** Whether the event's last day is over at the venue. */
    public static function isOver(Event $event, ?\DateTimeInterface $now = null): bool
    {
        $last = self::lastDay($event);

        return $last !== null && $last < self::today($event, $now);
    }

    /**
     * The moment nothing of the event needs to be kept any more: the end of its
     * last day plus RETENTION_DAYS. While the venue's zone is unknown it is
     * measured in the latest zone on Earth (UTC−12), so no event is ever cut
     * short by a guess. Null when the event has no dates at all.
     */
    public static function retentionEnd(Event $event): ?\Carbon\CarbonImmutable
    {
        $last = self::lastDay($event);

        if ($last === null) {
            return null;
        }

        $zone = self::parse($event->timezone) ?? new DateTimeZone('Etc/GMT+12');

        return \Carbon\CarbonImmutable::parse($last.' 23:59:59', $zone)->addDays(self::RETENTION_DAYS)->utc();
    }

    /** Whether the event is still inside its retention window (an event with no dates always is). */
    public static function retained(Event $event, ?\DateTimeInterface $now = null): bool
    {
        $end = self::retentionEnd($event);

        return $end === null || \Carbon\CarbonImmutable::instance($now ?? now())->lte($end);
    }

    /**
     * Reads the last-session day of many events in one cache round trip, for
     * lists (the WordCamp picker) that would otherwise ask once per event.
     *
     * @param  iterable<Event>  $events
     */
    public static function primeSessionDays(iterable $events): void
    {
        $keys = [];

        foreach ($events as $event) {
            $keys[] = self::sessionDayKey($event->id);
        }

        $request = Cache::store('array');

        foreach (Cache::many($keys) as $key => $value) {
            if ($value !== null) {
                $request->put($key, $value, 60);
            }
        }
    }

    /** Called whenever an event or its data is written (see DataVersion::forget). */
    public static function forgetSessionDay(int $eventId): void
    {
        Cache::store('array')->forget(self::sessionDayKey($eventId));
        Cache::forget(self::sessionDayKey($eventId));
    }

    private static function sessionDayKey(int $eventId): string
    {
        return "event:{$eventId}:last-session-day";
    }

    private static function lastSessionDay(Event $event): ?string
    {
        $key = self::sessionDayKey($event->id);
        // The array store lives only as long as this request: repeat questions
        // in one request (a list of events) don't go back to the database.
        $request = Cache::store('array');
        $day = $request->get($key);

        if ($day === null) {
            // '' stands for "no sessions" — Cache::remember doesn't keep a null.
            $day = Cache::remember($key, 900, fn () => self::sessionDayFromSchedule($event) ?? '');
            $request->put($key, $day, 60);
        }

        return $day === '' ? null : $day;
    }

    private static function sessionDayFromSchedule(Event $event): ?string
    {
        $zone = self::zone($event);
        $latest = null;
        $limit = $event->starts_on?->addDays(self::MAX_EVENT_SPAN_DAYS)->toDateString();

        foreach (EventData::get($event->id, 'sessions') ?? [] as $session) {
            if (! is_array($session) || empty($session['starts_at'])) {
                continue;
            }

            try {
                $start = \Carbon\CarbonImmutable::parse($session['starts_at']);
            } catch (Throwable) {
                continue;
            }

            $seconds = is_numeric($session['duration_seconds'] ?? null) && $session['duration_seconds'] > 0
                ? (int) $session['duration_seconds']
                : 30 * 60;
            $day = $start->addSeconds($seconds)->setTimezone($zone)->toDateString();

            if ($limit !== null && $day > $limit) {
                continue;
            }

            $latest = $latest === null ? $day : max($latest, $day);
        }

        return $latest;
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
