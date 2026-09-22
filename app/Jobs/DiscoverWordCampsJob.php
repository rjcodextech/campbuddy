<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\FetchLog;
use App\Services\WordPressEventsClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

/**
 * Central event discovery (§5.3, fast-follow per §2.2, now built at the
 * user's request). Every discovered event lands as a **draft** — nothing
 * goes live in the app without an admin approving it (§5.3, §9's
 * Events-Create). Re-running this never touches an event that already
 * exists (matched by source_site_url), so admin edits are never clobbered.
 */
class DiscoverWordCampsJob implements ShouldQueue
{
    use Queueable;

    /**
     * A seed list covering WordCamp's major regions — the discovery API
     * has no global/unscoped query (§5.3's location-radius caveat), so
     * broad coverage means searching many cities and unioning results.
     *
     * @var array<int, string>
     */
    public const SEED_LOCATIONS = [
        // Asia
        'Jaipur, India', 'Delhi, India', 'Mumbai, India', 'Bengaluru, India',
        'Chennai, India', 'Pune, India', 'Hyderabad, India', 'Kolkata, India',
        'Jakarta, Indonesia', 'Manila, Philippines', 'Bangkok, Thailand',
        'Tokyo, Japan', 'Seoul, South Korea', 'Singapore', 'Dhaka, Bangladesh',
        'Kathmandu, Nepal', 'Colombo, Sri Lanka',
        // Europe
        'London, UK', 'Paris, France', 'Berlin, Germany', 'Madrid, Spain',
        'Rome, Italy', 'Amsterdam, Netherlands', 'Warsaw, Poland',
        'Lisbon, Portugal', 'Vienna, Austria', 'Stockholm, Sweden',
        'Kyiv, Ukraine', 'Athens, Greece', 'Bucharest, Romania',
        // North America
        'New York, USA', 'Los Angeles, USA', 'Chicago, USA', 'Toronto, Canada',
        'Mexico City, Mexico', 'Vancouver, Canada', 'Seattle, USA', 'Miami, USA',
        // South America
        'São Paulo, Brazil', 'Buenos Aires, Argentina', 'Bogotá, Colombia',
        'Lima, Peru', 'Santiago, Chile',
        // Africa
        'Lagos, Nigeria', 'Nairobi, Kenya', 'Cairo, Egypt', 'Cape Town, South Africa',
        'Accra, Ghana',
        // Oceania
        'Sydney, Australia', 'Melbourne, Australia', 'Auckland, New Zealand',
    ];

    public function handle(): void
    {
        $client = new WordPressEventsClient;
        $discovered = $client->discoverUpcomingWordCamps(self::SEED_LOCATIONS);

        $created = 0;

        foreach ($discovered as $wordcamp) {
            $exists = Event::where('source_site_url', rtrim($wordcamp['url'], '/'))->exists();

            if ($exists) {
                continue;
            }

            Event::create([
                'slug' => $this->uniqueSlug($wordcamp['title']),
                'display_name' => $wordcamp['title'],
                'source_site_url' => rtrim($wordcamp['url'], '/'),
                'starts_on' => str($wordcamp['date'])->before(' '),
                'ends_on' => str($wordcamp['end_date'])->before(' '),
                'status' => 'draft',
                'is_visible' => true,
            ]);

            $created++;
        }

        FetchLog::create([
            'source' => 'wordpress_events_api',
            'job_type' => 'discovery',
            'status' => 'ok',
            'message' => "{$created} new WordCamp(s) added as drafts, out of ".count($discovered)." found",
            'fetched_at' => now(),
        ]);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $suffix = 1;

        while (Event::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
