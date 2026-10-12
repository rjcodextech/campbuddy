<?php

namespace App\Support;

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
    public const HOSTS = ['wordcamp.org', 'wordpress.com', 'wp.com', 'gravatar.com'];

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

        return false;
    }

    /** Copies older than this are removed every night (`campbuddy:clear-social-cache`). */
    public const KEEP_HOURS = 24;

    /**
     * Removes kept copies (and their type files) older than `$hours`: the
     * next Social media page that needs one fetches it again. A copy made
     * in the last day stays, so a page open at midnight keeps working.
     *
     * @return array{files: int, bytes: int} what was (or, with $dryRun, would be) removed
     */
    public static function prune(int $hours = self::KEEP_HOURS, bool $dryRun = false): array
    {
        $disk = Storage::disk('local');
        $cutoff = now()->subHours($hours)->getTimestamp();
        $files = 0;
        $bytes = 0;
        foreach ($disk->files('social-cache') as $path) {
            if (str_ends_with($path, '.type') || $disk->lastModified($path) >= $cutoff) {
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

        return ['files' => $files, 'bytes' => $bytes];
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
            abort_unless($response->successful() && ($font || (str_starts_with($type, 'image/') && ! str_contains($type, 'svg'))) && strlen($response->body()) <= self::MAX_BYTES, 404);
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
