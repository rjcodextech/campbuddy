<?php

namespace App\Services;

use App\Models\Event;
use App\Support\CacheVersion;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The admin "Clear cache" action: makes the website and every installed app
 * drop saved copies — without fetching anything. (Fetching fresh event data
 * is its own button: DataRefresher.)
 *
 *   1. Clear the server's caches (application cache, compiled views, cached
 *      config/routes/events) — what `php artisan optimize:clear` does.
 *   2. Purge Cloudflare's edge cache, if credentials are configured.
 *   3. Bump the cache version, which tells every open or installed app to
 *      drop its saved copies and reload (see CacheVersion, cache-version.js).
 *
 * What it deliberately does NOT do:
 *   - Blank a live event. Fetched lists are kept in the event_feeds table
 *     (EventData) and are also set aside during the flush and put back, so
 *     pages keep showing the last good data.
 *   - Touch attendees' personal data (saved sessions, quest progress, Camp
 *     Card) — that lives in IndexedDB on their own devices, and only saved
 *     *copies of pages* are dropped.
 *   - Delete the compiled service manifests in bootstrap/cache: a host where
 *     the web user can't rewrite them would be left unable to boot.
 */
class CachePurger
{
    private const INGESTED_KEYS = ['sessions', 'speakers', 'sponsors', 'organizers'];

    /**
     * @return array{message: string, version: string, cloudflare: string, storage_link: string, caches_cleared: bool}
     */
    public function purge(?string $by = null): array
    {
        $keep = $this->setAsideIngestedData();
        $cachesCleared = $this->clearServerCaches();
        $this->putBack($keep);

        $storageLink = $this->ensureStorageLink();
        $cloudflare = $this->purgeCloudflare();

        $message = $this->summarise($cachesCleared, $cloudflare, $storageLink);
        $version = CacheVersion::bump($by, $message);

        return [
            'message' => $message,
            'version' => $version,
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

    /** One readable sentence for the flash message and the dashboard. */
    private function summarise(bool $cachesCleared, string $cloudflare, string $storageLink): string
    {
        $parts = [$cachesCleared ? 'Server caches cleared' : 'Server caches could only be partly cleared (see the app log)'];

        $parts[] = match (true) {
            $cloudflare === 'purged' => 'Cloudflare purged',
            $cloudflare === 'not_configured' => 'Cloudflare not configured (purge it from its dashboard if it caches pages)',
            default => 'Cloudflare '.$cloudflare,
        };

        if ($storageLink === 'created') {
            $parts[] = 'storage link created';
        }

        $parts[] = 'open apps will reload';

        return implode(' · ', $parts).'.';
    }
}
