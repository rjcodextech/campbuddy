<?php

namespace App\Services;

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
     * @return array<int, array{title: string, url: string, location: ?string, starts_on: string}>|null
     *         null means the page's expected structure wasn't found at
     *         all — distinct from a page that parsed cleanly into zero
     *         upcoming events.
     */
    public function discoverUpcomingWordCamps(): ?array
    {
        try {
            $html = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders(['User-Agent' => 'CampBuddy/1.0 (+https://wordpress.org/plugins/)'])
                ->get(self::URL)
                ->throw()
                ->body();
        } catch (\Throwable) {
            return null;
        }

        if (! preg_match('/globalEventsPayload\["events\d+"\]\s*=\s*(\{.*?\});/s', $html, $matches)) {
            return null;
        }

        $payload = json_decode($matches[1], true);
        $events = $payload['events'] ?? null;

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
                'starts_on' => gmdate('Y-m-d', (int) $event['timestamp']),
            ];
        }, $events)));
    }
}
