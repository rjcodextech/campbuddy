<?php

namespace App\Support;

use App\Models\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Sponsor logos, the site's artwork and its font files on a WordCamp site
 * come without CORS headers, so a browser canvas that draws them can't be
 * saved as a PNG. The Social media page asks for them through this:
 * CampBuddy fetches the file once (only from WordCamp / WordPress.com /
 * Gravatar hosts, only images or .woff/.woff2 fonts, at most 4 MB, never
 * SVG), keeps a copy, and serves it from its own address.
 */
class SocialImageProxy
{
    public const HOSTS = ['wordcamp.org', 'wordpress.org', 'wordpress.com', 'wp.com', 'gravatar.com', 'w.org'];

    private const MAX_BYTES = 4 * 1024 * 1024;

    public static function allowed(string $url): bool
    {
        $parts = parse_url($url);
        if (! in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host'])) {
            return false;
        }
        $host = strtolower($parts['host']);
        foreach (self::HOSTS as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return true;
            }
        }

        try {
            $eventHosts = Event::whereNotNull('source_site_url')
                ->pluck('source_site_url')
                ->map(fn ($u) => strtolower((string) parse_url((string) $u, PHP_URL_HOST)))
                ->filter()
                ->unique();

            foreach ($eventHosts as $eventHost) {
                if ($host === $eventHost || str_ends_with($host, '.'.$eventHost)) {
                    return true;
                }
            }
        } catch (Throwable) {
            // DB not reachable during early boot
        }

        return false;
    }

    /** A copy no event uses any more is removed once it is older than this. */
    public const KEEP_HOURS = 24;

    /**
     * Removes only the copies no listed event uses any more (a sponsor taken
     * off, artwork changed, an event archived), once older than `$hours` (so a
     * page open at midnight keeps working). Every copy a listed event still
     * uses stays, however old: it never has to be fetched from the WordCamp
     * site again. Type files whose copy is gone go too.
     *
     * @param  ?list<string>  $inUse  addresses still used (default: inUse())
     * @return array{files: int, bytes: int, kept: int} what was (or, with $dryRun, would be) removed, and how many stay
     */
    public static function prune(int $hours = self::KEEP_HOURS, bool $dryRun = false, ?array $inUse = null): array
    {
        $disk = Storage::disk('local');
        $keep = array_flip(array_map(fn ($url) => 'social-cache/'.sha1($url), $inUse ?? self::inUse()));
        $cutoff = now()->subHours($hours)->getTimestamp();
        $files = 0;
        $bytes = 0;
        $kept = 0;
        foreach ($disk->files('social-cache') as $path) {
            if (str_ends_with($path, '.type')) {
                continue;
            }
            if (isset($keep[$path]) || $disk->lastModified($path) >= $cutoff) {
                $kept++;

                continue;
            }
            $bytes += $disk->size($path);
            $files++;
            if (! $dryRun) {
                $disk->delete([$path, $path.'.type']);
            }
        }
        // A type file whose copy is already gone.
        foreach ($disk->files('social-cache') as $path) {
            if (str_ends_with($path, '.type') && ! $disk->exists(substr($path, 0, -5)) && ! $dryRun) {
                $disk->delete($path);
            }
        }

        // Automatic 30-day post-event cache purge: delete branding storage and application cache
        // for events whose retention window (30 days after event end) has passed.
        $publicDisk = Storage::disk('public');
        Event::query()->get()->filter(fn (Event $event) => EventTime::isOver($event) && ! EventTime::retained($event))->each(function (Event $event) use ($publicDisk, $dryRun, &$files, &$bytes) {
            $dir = "branding/{$event->id}";
            if ($publicDisk->directoryExists($dir) || $publicDisk->exists($dir)) {
                foreach ($publicDisk->allFiles($dir) as $f) {
                    try {
                        $bytes += $publicDisk->size($f);
                    } catch (Throwable) {}
                    $files++;
                }
                if (! $dryRun) {
                    $publicDisk->deleteDirectory($dir);
                }
            }
            if (! $dryRun) {
                EventData::forget($event->id);
            }
        });

        return ['files' => $files, 'bytes' => $bytes, 'kept' => $kept];
    }

    /**
     * Pre-warms/caches image URLs locally with retry on network error.
     *
     * @param string|list<string> $urls
     * @return int count of successfully cached images
     */
    public static function warm(string|array $urls): int
    {
        $urls = is_array($urls) ? $urls : [$urls];
        $disk = Storage::disk('local');
        $saved = 0;

        foreach (array_unique(array_filter($urls)) as $url) {
            if (! self::allowed($url)) {
                continue;
            }
            $path = 'social-cache/'.sha1($url);
            if ($disk->exists($path)) {
                $saved++;
                continue;
            }
            try {
                $response = Http::timeout(10)->retry(2, 200)->get($url);
                if (! $response->successful()) {
                    continue;
                }
                $type = (string) $response->header('Content-Type');
                if (! str_starts_with($type, 'image/') || strlen($response->body()) > self::MAX_BYTES) {
                    continue;
                }
                $disk->put($path, $response->body());
                $disk->put($path.'.type', $type);
                $saved++;
            } catch (Throwable) {
                // Keep moving, will retry on next run
            }
        }

        return $saved;
    }

    /**
     * Every address the Social media pages of retained events can ask this proxy
     * for: sponsor logos, speakers' and sessions' photos, and the site's
     * artwork (in use or not) and font files. Retained = within 30 days of event end.
     *
     * @return list<string>
     */
    public static function inUse(): array
    {
        $urls = [];
        $collect = function ($value) use (&$collect, &$urls) {
            if (is_array($value)) {
                array_walk($value, $collect);
            } elseif (is_string($value) && preg_match('#^https?://#i', $value)) {
                $urls[$value] = true;
            }
        };
        Event::whereIn('status', ['approved', 'active'])->get()
            ->filter(fn (Event $event) => EventTime::retained($event))
            ->each(function (Event $event) use ($collect) {
                try {
                    $collect([SocialKit::sponsors($event), SocialKit::speakers($event), SocialKit::schedule($event), $event->site_theme]);
                } catch (Throwable $e) {
                    report($e);
                }
            });

        return array_keys($urls);
    }

    public static function respond(string $url): Response
    {
        abort_unless(self::allowed($url), 404);

        $path = 'social-cache/'.sha1($url);
        $disk = Storage::disk('local');

        if (! $disk->exists($path)) {
            try {
                $response = Http::timeout(10)->get($url);
            } catch (Throwable) {
                abort(404);
            }
            $type = (string) $response->header('Content-Type');
            $font = (bool) preg_match('/\.(woff2?)$/i', (string) parse_url($url, PHP_URL_PATH), $ext);
            if ($font) {
                // The site's own font files (its headings and text), served as fonts whatever the host labels them.
                $type = strtolower($ext[1]) === 'woff2' ? 'font/woff2' : 'font/woff';
            }
            abort_unless($response->successful() && ($font || str_starts_with($type, 'image/')) && strlen($response->body()) <= self::MAX_BYTES, 404);
            $disk->put($path, $response->body());
            $disk->put($path.'.type', $type);
        }

        return response($disk->get($path), 200, [
            'Content-Type' => $disk->get($path.'.type') ?: 'image/png',
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
