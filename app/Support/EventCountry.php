<?php

namespace App\Support;

use App\Models\Event;
use DateTimeZone;
use Throwable;

/**
 * Which country a WordCamp is in, for the picker's country filter.
 *
 * Read in this order, first answer wins:
 *  1. events.country_code — stored (central.wordcamp.org's venue country, or
 *     filled from the time zone by the migration / `campbuddy:countries`);
 *  2. the event's own time zone ("Asia/Kolkata" → IN), which every live
 *     event has once its site has been read;
 *  3. the last part of the venue line ("…, Sofia, Bulgaria" → BG).
 * No answer means null: the event is still listed, just under no country.
 *
 * Codes are ISO 3166-1 alpha-2, upper case ("IN", "GB").
 */
class EventCountry
{
    /** @var array<string, string>|null lower-case English country name → code */
    private static ?array $byName = null;

    public static function code(Event $event): ?string
    {
        return self::valid($event->country_code)
            ?? self::fromTimezone($event->timezone)
            ?? self::fromText($event->info['venue'] ?? null);
    }

    /** "IN" from "Asia/Kolkata"; null for an offset ("+05:30"), "UTC" or junk. */
    public static function fromTimezone(?string $timezone): ?string
    {
        if (! $timezone || ! str_contains($timezone, '/')) {
            return null;
        }

        try {
            $location = (new DateTimeZone($timezone))->getLocation();
        } catch (Throwable) {
            return null;
        }

        return self::valid(is_array($location) ? ($location['country_code'] ?? null) : null);
    }

    /** "IN" from "…, Bengaluru, Karnataka, India"; matched on the last comma part only. */
    public static function fromText(?string $text): ?string
    {
        if (! $text) {
            return null;
        }

        $parts = array_map('trim', explode(',', $text));
        $last = mb_strtolower(preg_replace('/^the\s+/iu', '', (string) end($parts)) ?? '');

        return self::names()[$last] ?? null;
    }

    /** "India" for "IN" (English); the code itself if the name is unknown. */
    public static function name(string $code): string
    {
        if (class_exists(\Locale::class)) {
            $name = \Locale::getDisplayRegion('-'.$code, 'en');
            if (is_string($name) && $name !== '' && $name !== $code) {
                return $name;
            }
        }

        return $code;
    }

    /**
     * Every time zone of these countries → its country, so the browser can
     * tell which listed country it is in from its own zone. Includes old
     * names still in use ("Asia/Calcutta") where PHP knows them.
     *
     * @param  iterable<string>  $codes
     * @return array<string, string>
     */
    public static function timezones(iterable $codes): array
    {
        $map = [];

        foreach ($codes as $code) {
            foreach (DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, $code) as $zone) {
                $map[$zone] = $code;
            }
        }

        $wanted = array_flip(array_values($map));
        $current = array_flip(DateTimeZone::listIdentifiers());

        foreach (DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC) as $zone) {
            if (! isset($current[$zone]) && ($code = self::fromTimezone($zone)) && isset($wanted[$code])) {
                $map[$zone] = $code;
            }
        }

        ksort($map);

        return $map;
    }

    private static function valid(mixed $code): ?string
    {
        return is_string($code) && preg_match('/^[A-Za-z]{2}$/', $code) && strtoupper($code) !== '??'
            ? strtoupper($code)
            : null;
    }

    /** @return array<string, string> */
    private static function names(): array
    {
        if (self::$byName !== null) {
            return self::$byName;
        }

        $names = [];

        // Every country that has a time zone, by its English name.
        foreach (DateTimeZone::listIdentifiers() as $zone) {
            if (($code = self::fromTimezone($zone)) && ($name = self::name($code)) !== $code) {
                $names[mb_strtolower($name)] = $code;
            }
        }

        // What organisers type that the standard name doesn't cover.
        return self::$byName = $names + [
            'usa' => 'US', 'united states of america' => 'US', 'u.s.a.' => 'US',
            'uk' => 'GB', 'england' => 'GB', 'scotland' => 'GB', 'wales' => 'GB', 'northern ireland' => 'GB',
            'netherlands' => 'NL', 'holland' => 'NL', 'czech republic' => 'CZ', 'türkiye' => 'TR', 'turkey' => 'TR',
            'uae' => 'AE', 'korea' => 'KR', 'south korea' => 'KR', 'russia' => 'RU', 'vietnam' => 'VN',
        ];
    }
}
