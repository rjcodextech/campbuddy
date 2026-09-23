<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * The official WordCamp discovery API:
 * api.wordpress.org/events/1.0/. It's a location/radius "events near X"
 * API (built for the wp-admin events widget), not a flat global list —
 * so finding "all" upcoming WordCamps means a seeded search across many
 * locations, unioned and de-duped by URL.
 */
class WordPressEventsClient
{
    private const TIMEOUT_SECONDS = 10;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function eventsNear(string $location): array
    {
        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->get('https://api.wordpress.org/events/1.0/', [
                    'location' => $location,
                    'number' => 100,
                ])
                ->throw();

            return $response->json('events') ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Unions results across a seed list of locations, keeping only
     * upcoming WordCamps (not meetups), de-duped by URL.
     *
     * @param  array<int, string>  $seedLocations
     * @return array<int, array{title: string, url: string, date: string, end_date: string}>
     */
    public function discoverUpcomingWordCamps(array $seedLocations): array
    {
        $byUrl = [];

        foreach ($seedLocations as $location) {
            foreach ($this->eventsNear($location) as $event) {
                if (($event['type'] ?? null) !== 'wordcamp') {
                    continue;
                }

                if (! isset($event['end_date']) || $event['end_date'] < now()->toDateTimeString()) {
                    continue;
                }

                $byUrl[$event['url']] = $event;
            }
        }

        return array_values($byUrl);
    }
}
