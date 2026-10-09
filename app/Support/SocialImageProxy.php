<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Sponsor logos on a WordCamp site come without CORS headers, so a browser
 * canvas that draws them can't be saved as a PNG. The Social media page asks
 * for them through this: CampBuddy fetches the image once (only from
 * WordCamp / WordPress.com / Gravatar hosts, only images, at most 4 MB),
 * keeps a copy, and serves it from its own address.
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

    public static function respond(string $url): Response
    {
        abort_unless(self::allowed($url), 404);

        $path = 'social-cache/'.sha1($url);
        $disk = Storage::disk('local');

        if (! $disk->exists($path)) {
            try {
                $response = Http::timeout(10)->accept('image/*')->get($url);
            } catch (Throwable) {
                abort(404);
            }
            $type = (string) $response->header('Content-Type');
            abort_unless($response->successful() && str_starts_with($type, 'image/') && ! str_contains($type, 'svg') && strlen($response->body()) <= self::MAX_BYTES, 404);
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
