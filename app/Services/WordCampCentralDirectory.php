<?php

namespace App\Services;

use App\Support\EventTime;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * central.wordcamp.org's own record of a WordCamp — a public REST endpoint
 * (the `wordcamp` post type) that carries the structured facts organizers
 * fill in when they register an event: venue name, address, the venue's
 * website, coordinates. It's the one reliable source for "where is this
 * event", which the event's own site usually only describes in prose.
 *
 * Read-only, short timeouts, and every failure just means "no record": the
 * caller leaves the field blank rather than guessing.
 */
class WordCampCentralDirectory
{
    private const ENDPOINT = 'https://central.wordcamp.org/wp-json/wp/v2/wordcamps';

    private const TIMEOUT_SECONDS = 10;

    /**
     * The record whose registered site URL is this event's site, or null.
     * Matched on the URL, never on a fuzzy title, so "WordCamp Sylhet 2024"
     * can't be mistaken for 2026.
     *
     * @return array<string, mixed>|null
     */
    public function findRecord(string $siteUrl, ?string $eventName = null): ?array
    {
        $wanted = $this->normalize($siteUrl);

        foreach ($this->searchTerms($siteUrl, $eventName) as $term) {
            try {
                $records = Http::timeout(self::TIMEOUT_SECONDS)
                    ->acceptJson()
                    ->get(self::ENDPOINT, ['search' => $term, 'per_page' => 50])
                    ->throw()
                    ->json();
            } catch (Throwable) {
                continue;
            }

            foreach (is_array($records) ? $records : [] as $record) {
                if (is_array($record) && $this->normalize((string) ($record['URL'] ?? '')) === $wanted) {
                    return $record;
                }
            }
        }

        return null;
    }

    /**
     * The event's time zone from its central record (a field whose name
     * mentions "timezone", e.g. "Event Timezone"), if it holds a usable zone.
     *
     * @param  array<string, mixed>  $record
     */
    public function timezone(array $record): ?string
    {
        foreach ($record as $key => $value) {
            if (is_string($key) && preg_match('/time\s*_?zone/i', $key) && is_string($value)) {
                if ($zone = EventTime::normalize($value)) {
                    return $zone;
                }
            }
        }

        return null;
    }

    /**
     * The event's dates from its central record ("Start Date (YYYY-mm-dd)" /
     * "End Date (YYYY-mm-dd)"), which WordCamp stores as the date at midnight
     * UTC — so the UTC date is the event's own calendar date.
     *
     * @param  array<string, mixed>  $record
     * @return array{starts_on: ?string, ends_on: ?string}
     */
    public function dates(array $record): array
    {
        $date = function (mixed $value): ?string {
            if (is_numeric($value) && (int) $value > 0) {
                return gmdate('Y-m-d', (int) $value);
            }
            if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value, $m)) {
                return $m[0];
            }

            return null;
        };

        $start = $end = null;
        foreach ($record as $key => $value) {
            if (! is_string($key)) {
                continue;
            }
            if ($start === null && preg_match('/^start\s*date/i', $key)) {
                $start = $date($value);
            }
            if ($end === null && preg_match('/^end\s*date/i', $key)) {
                $end = $date($value);
            }
        }

        if ($start !== null && $end !== null && $end < $start) {
            $end = null;
        }

        return ['starts_on' => $start, 'ends_on' => $end];
    }

    /**
     * "Venue name — address" on one line; the address alone if there's no
     * name; the registered city if that's all there is; null if none.
     *
     * @param  array<string, mixed>  $record
     */
    public function venueLine(array $record): ?string
    {
        $name = $this->clean((string) ($record['Venue Name'] ?? ''));
        // Organizers often type the venue name into the address box too, so
        // the same part can appear on two lines — keep the first of each, and
        // drop any part that is just the venue name (it leads the line instead).
        $address = collect(preg_split('/\R+/u', (string) ($record['Physical Address'] ?? '')) ?: [])
            ->flatMap(fn ($line) => explode(',', $line))
            ->map(fn ($part) => $this->clean($part))
            ->filter()
            ->unique(fn ($part) => mb_strtolower($part))
            ->reject(fn ($part) => $name !== '' && mb_strtolower($part) === mb_strtolower($name))
            ->implode(', ');

        $line = match (true) {
            // "Grand Hall — 1 Main St, Testville" (unless the address already says the name).
            $name !== '' && $address !== '' && ! str_contains(mb_strtolower($address), mb_strtolower($name)) => "{$name} — {$address}",
            $address !== '' => $address,
            $name !== '' => $name,
            default => $this->clean((string) ($record['Location'] ?? '')),
        };

        return $line === '' ? null : $line;
    }

    /**
     * What to search central for: the site's subdomain ("sylhet"), then the
     * event's name. Central holds every past year of the same city, so the
     * search only narrows things — findRecord() then demands an exact URL.
     *
     * @return list<string>
     */
    private function searchTerms(string $siteUrl, ?string $eventName): array
    {
        $host = (string) parse_url($siteUrl, PHP_URL_HOST);
        $terms = [];

        if (str_ends_with($host, '.wordcamp.org')) {
            $terms[] = explode('.', $host)[0];
        }

        if ($eventName !== null && trim($eventName) !== '') {
            $terms[] = trim($eventName);
        }

        return array_values(array_unique($terms));
    }

    private function normalize(string $url): string
    {
        return rtrim(preg_replace('#^https?://(www\.)?#i', '', mb_strtolower(trim($url))) ?? '', '/');
    }

    /** Central stores some fields HTML-escaped ("St. Joseph&#039;s"), so decode before showing. */
    private function clean(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }
}
