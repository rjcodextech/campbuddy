<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\FetchLog;
use App\Services\WordCampDiscoveryScraper;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

/**
 * Central event discovery, from events.wordpress.org's own upcoming
 * in-person WordCamps listing (WordCampDiscoveryScraper). Every
 * discovered event lands as a **draft** — nothing goes live in the app
 * without an admin approving it. Re-running this never touches an event
 * that already exists (matched by source_site_url), so admin edits are
 * never clobbered.
 */
class DiscoverWordCampsJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $discovered = (new WordCampDiscoveryScraper)->discoverUpcomingWordCamps();

        if ($discovered === null) {
            FetchLog::create([
                'source' => 'events_wordpress_org',
                'job_type' => 'discovery',
                'status' => 'error',
                'message' => "Couldn't read the upcoming-events listing — its page structure may have changed.",
                'fetched_at' => now(),
            ]);

            return;
        }

        $created = 0;

        foreach ($discovered as $wordcamp) {
            $url = rtrim($wordcamp['url'], '/');

            if (Event::where('source_site_url', $url)->exists()) {
                continue;
            }

            $info = $wordcamp['location'] ? ['venue' => $wordcamp['location']] : null;

            Event::create([
                'slug' => $this->uniqueSlug($wordcamp['title']),
                'display_name' => $wordcamp['title'],
                'source_site_url' => $url,
                'starts_on' => $wordcamp['starts_on'],
                'status' => 'draft',
                'is_visible' => true,
                'info' => $info,
                // Machine-filled, so the first Event Information fetch may replace it.
                'info_fetched' => $info,
            ]);

            $created++;
        }

        FetchLog::create([
            'source' => 'events_wordpress_org',
            'job_type' => 'discovery',
            'status' => 'ok',
            'message' => "{$created} new WordCamp(s) added as drafts, out of ".count($discovered).' found',
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
