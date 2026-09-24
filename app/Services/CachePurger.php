<?php

namespace App\Services;

use App\Jobs\FetchEventInfoJob;
use App\Jobs\FetchSpeakersSponsorsSessionsJob;
use App\Models\Event;
use App\Models\FetchLog;
use App\Support\CacheVersion;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The admin "Purge cache & refresh data" action: everything needed for the
 * website and the installed PWA to show fresh data.
 *
 *   1. Clear the server's caches (application cache, compiled views, cached
 *      config/routes/events) — what `php artisan optimize:clear` does.
 *   2. Re-fetch each live event's schedule, speakers, sponsors and event
 *      information from its WordCamp site.
 *   3. Purge Cloudflare's edge cache, if credentials are configured.
 *   4. Bump the cache version, which tells every open or installed app to
 *      drop its saved copies and reload (see CacheVersion, cache-version.js).
 *
 * What it deliberately does NOT do:
 *   - Blank a live event. The ingested schedule lives in the application
 *     cache, so it's set aside during the flush and put back; the new fetch
 *     replaces it only once it succeeds. If a WordCamp's site is down at that
 *     moment, attendees keep the last good schedule instead of an empty one.
 *   - Touch attendees' personal data (saved sessions, quest progress, Camp
 *     Card) — that lives in IndexedDB on their own devices, and only saved
 *     *copies of pages* are dropped.
 *   - Re-scrape the attendee roster: it's a scrape of someone else's site,
 *     limited to once a day (IN2).
 *   - Delete the compiled service manifests in bootstrap/cache: a host where
 *     the web user can't rewrite them would be left unable to boot.
 */
class CachePurger
{
    /** Kept generous, but well inside Cloudflare's ~100 s origin timeout. */
    private const SYNC_BUDGET_SECONDS = 30;

    private const INGESTED_KEYS = ['sessions', 'speakers', 'sponsors', 'organizers'];

    /**
     * @return array{message: string, version: string, events: array<string, array<int, string>>, cloudflare: string, storage_link: string, caches_cleared: bool}
     */
    public function purge(?string $by = null): array
    {
        @set_time_limit(180);

        $startedAt = microtime(true);
        $runStartedAt = now()->subSecond();

        $keep = $this->setAsideIngestedData();
        $cachesCleared = $this->clearServerCaches();
        $this->putBack($keep);

        $storageLink = $this->ensureStorageLink();
        $events = $this->refreshEvents($startedAt, $runStartedAt);
        $cloudflare = $this->purgeCloudflare();

        $message = $this->summarise($cachesCleared, $events, $cloudflare, $storageLink);
        $version = CacheVersion::bump($by, $message);

        return [
            'message' => $message,
            'version' => $version,
            'events' => $events,
            'cloudflare' => $cloudflare,
            'storage_link' => $storageLink,
            'caches_cleared' => $cachesCleared,
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function setAsideIngestedData(): array
    {
        $keep = [];

        foreach (Event::pluck('id') as $id) {
            foreach (self::INGESTED_KEYS as $key) {
                $value = Cache::get("event:{$id}:{$key}");

                if ($value !== null) {
                    $keep["event:{$id}:{$key}"] = $value;
                }
            }
        }

        return $keep;
    }

    /** @param  array<string, mixed>  $keep */
    private function putBack(array $keep): void
    {
        foreach ($keep as $key => $value) {
            Cache::put($key, $value, now()->addDays(14));
        }
    }

    private function clearServerCaches(): bool
    {
        try {
            return Artisan::call('optimize:clear', ['--except' => 'compiled']) === 0;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    /**
     * `public/storage` is what lets the web server hand out uploaded logos
     * directly. It's missing on many hosts (never created, or created in a
     * folder that isn't the web root). Try to create it; if the host won't
     * allow that, nothing is lost — PublicStorageController serves the files.
     */
    private function ensureStorageLink(): string
    {
        $links = (array) config('filesystems.links', []);

        if ($links === []) {
            return 'unavailable';
        }

        $created = false;

        foreach ($links as $link => $target) {
            if (file_exists($link)) {
                continue;
            }

            try {
                if (is_link($link)) {
                    @unlink($link); // a dangling link — replace it
                }

                if (Artisan::call('storage:link', ['--relative' => true]) !== 0 || ! file_exists($link)) {
                    return 'unavailable';
                }

                $created = true;
            } catch (Throwable) {
                return 'unavailable';
            }
        }

        return $created ? 'created' : 'present';
    }

    /**
     * Refreshes each live event's ingested data now, while the time budget
     * lasts; whatever's left over goes to the queue (or waits for the next
     * scheduled run if there is no real queue).
     *
     * @return array<string, array<int, string>>
     */
    private function refreshEvents(float $startedAt, \DateTimeInterface $runStartedAt): array
    {
        $result = ['refreshed' => [], 'queued' => [], 'failed' => [], 'skipped' => []];
        $hasQueue = config('queue.default') !== 'sync';

        $events = Event::whereIn('status', ['approved', 'active'])->orderBy('starts_on')->orderBy('id')->get();

        foreach ($events as $event) {
            $jobs = $event->status === 'active'
                ? [FetchSpeakersSponsorsSessionsJob::class, FetchEventInfoJob::class]
                : [FetchEventInfoJob::class];

            if (microtime(true) - $startedAt < self::SYNC_BUDGET_SECONDS) {
                foreach ($jobs as $job) {
                    try {
                        $job::dispatchSync($event);
                    } catch (Throwable) {
                        // The job logged its own failure; the log check below reports it.
                    }
                }

                $failed = FetchLog::where('event_id', $event->id)
                    ->where('status', 'error')
                    ->where('fetched_at', '>=', $runStartedAt)
                    ->exists();

                $result[$failed ? 'failed' : 'refreshed'][] = $event->display_name;
            } elseif ($hasQueue) {
                foreach ($jobs as $job) {
                    $job::dispatch($event);
                }

                $result['queued'][] = $event->display_name;
            } else {
                $result['skipped'][] = $event->display_name;
            }
        }

        return $result;
    }

    /** @return string  purged | not_configured | failed: <why> */
    private function purgeCloudflare(): string
    {
        $zone = config('services.cloudflare.zone_id');
        $token = config('services.cloudflare.api_token');

        if (blank($zone) || blank($token)) {
            return 'not_configured';
        }

        try {
            $response = Http::withToken($token)
                ->timeout(15)
                ->post("https://api.cloudflare.com/client/v4/zones/{$zone}/purge_cache", ['purge_everything' => true]);

            if ($response->successful() && $response->json('success') === true) {
                return 'purged';
            }

            return 'failed: '.($response->json('errors.0.message') ?? 'HTTP '.$response->status());
        } catch (Throwable $e) {
            return 'failed: '.substr($e->getMessage(), 0, 120);
        }
    }

    /**
     * One readable sentence for the flash message and the dashboard.
     *
     * @param  array<string, array<int, string>>  $events
     */
    private function summarise(bool $cachesCleared, array $events, string $cloudflare, string $storageLink): string
    {
        $parts = [$cachesCleared ? 'Server caches cleared' : 'Server caches could only be partly cleared (see the app log)'];

        $refreshed = count($events['refreshed']);
        $parts[] = $refreshed === 0 && array_sum(array_map('count', $events)) === 0
            ? 'no live events to refresh'
            : "fresh data fetched for {$refreshed} ".str('event')->plural($refreshed);

        if ($events['queued'] !== []) {
            $parts[] = count($events['queued']).' more queued (ready within a minute or two)';
        }

        if ($events['skipped'] !== []) {
            $parts[] = count($events['skipped']).' left for the next scheduled refresh';
        }

        if ($events['failed'] !== []) {
            $parts[] = 'couldn\'t fully refresh '.implode(', ', $events['failed']).' (its WordCamp site didn\'t answer as expected) — the last good data is still showing';
        }

        $parts[] = match (true) {
            $cloudflare === 'purged' => 'Cloudflare purged',
            $cloudflare === 'not_configured' => 'Cloudflare not configured (purge it from its dashboard if it caches pages)',
            default => 'Cloudflare '.$cloudflare,
        };

        if ($storageLink === 'created') {
            $parts[] = 'storage link created';
        }

        $parts[] = 'open apps will reload with the fresh data';

        return implode(' · ', $parts).'.';
    }
}
