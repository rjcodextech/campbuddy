<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Discovers upcoming in-person WordCamps from events.wordpress.org's own
 * filtered listing — a complete, non-seeded list. (The previous approach,
 * api.wordpress.org/events/1.0/, is a location/radius "events near X" API
 * with no flat global query, so finding "all" WordCamps meant an
 * inherently incomplete seeded search across dozens of cities.)
 *
 * The listing page itself is a JS-rendered filter UI with no public JSON
 * endpoint, but it embeds the full dataset server-side as a
 * `globalEventsPayload["eventsN"] = {...};` assignment (N varies per
 * request/deployment, matched generically here rather than hardcoded) —
 * extracted via regex. Same posture as AttendeeRosterScraper: a narrow,
 * specific HTML-parse job with no stable contract, not a general
 * scraper, so a structural change must be detectable (null return),
 * never silently ingested as an empty result.
 */
class WordCampDiscoveryScraper
{
    private const URL = 'https://events.wordpress.org/upcoming-events/filtered/format/in-person/type/wordcamp/';

    private const TIMEOUT_SECONDS = 15;

    /**
     * @return array<int, array{title: string, url: string, location: ?string, starts_on: string, ends_on: ?string}>|null
     *                                                                                                  null means the page's expected structure wasn't found at
     *                                                                                                  all — distinct from a page that parsed cleanly into zero
     *                                                                                                  upcoming events.
     */
    public function discoverUpcomingWordCamps(): ?array
    {
        try {
            $html = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders(['User-Agent' => 'CampBuddy/1.0 (+https://wordpress.org/plugins/)'])
                ->retry(3, 800, fn (\Throwable $e) => $e instanceof ConnectionException || ($e instanceof RequestException && ($e->response?->serverError() || $e->response?->status() === 429)), throw: true)
                ->get(self::URL)
                ->throw()
                ->body();
        } catch (\Throwable) {
            return null;
        }

        $json = self::extractGlobalPayload($html);
        $payload = $json !== null ? json_decode($json, true) : null;
        $events = is_array($payload) ? ($payload['events'] ?? null) : null;

        if (! is_array($events)) {
            return null;
        }

        return array_values(array_filter(array_map(function (array $event) {
            if (empty($event['title']) || empty($event['url']) || empty($event['timestamp'])) {
                return null;
            }

            return [
                'title' => $event['title'],
                'url' => $event['url'],
                'location' => $event['location'] ?? null,
                'starts_on' => self::localDate($event['date'] ?? null) ?? gmdate('Y-m-d', (int) $event['timestamp']),
                'ends_on' => self::localDate($event['end_date'] ?? null),
            ];
        }, $events)));
    }

    /**
     * Extract the full JSON object assigned to `globalEventsPayload["eventsN"]`
     * safely by balancing braces outside quoted strings.
     */
    private static function extractGlobalPayload(string $html): ?string
    {
        if (! preg_match('/globalEventsPayload\["events\d+"\]\s*=\s*\{/', $html, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $start = $m[0][1] + strlen($m[0][0]) - 1;
        $len = strlen($html);
        $depth = 0;
        $inStr = false;
        $escape = false;

        for ($i = $start; $i < $len; $i++) {
            $ch = $html[$i];
            if ($escape) {
                $escape = false;
                continue;
            }
            if ($inStr) {
                if ($ch === '\\') {
                    $escape = true;
                } elseif ($ch === '"') {
                    $inStr = false;
                }
                continue;
            }
            if ($ch === '"') {
                $inStr = true;
            } elseif ($ch === '{') {
                $depth++;
            } elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($html, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }

    /**
     * The event's own calendar date from its local date-time text
     * ("2026-10-10 09:00:00"). Preferred over the UTC timestamp, whose UTC
     * date is a day early for a morning event east of UTC (e.g. India).
     */
    private static function localDate(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $m) ? $m[1] : null;
    }
}
