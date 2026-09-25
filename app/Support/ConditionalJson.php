<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * JSON for the public, read-only lists (roster, discovery, data-version), built
 * so ten thousand phones asking the same question cost almost nothing:
 *
 *   - the finished JSON and its ETag are kept for a short while (Cache), so a
 *     burst of requests is one query, not one per phone;
 *   - a phone that already has the current version gets an empty 304 back;
 *   - the headers let a CDN in front (Cloudflare with a cache rule for /api/)
 *     and the browser keep it a moment and refresh it in the background —
 *     `public, max-age=0, s-maxage=N, stale-while-revalidate=2N`. Without a
 *     cache rule at the CDN they change nothing, and browsers still revalidate
 *     every time (max-age=0), so nothing is ever shown stale for long.
 *
 * Only ever for data that is the same for every visitor.
 */
class ConditionalJson
{
    /**
     * @param  callable():mixed  $build  the payload, run only on a cache miss
     */
    public static function cached(Request $request, string $key, int $ttlSeconds, int $sharedSeconds, callable $build): Response
    {
        $entry = Cache::remember($key, $ttlSeconds, function () use ($build) {
            $json = json_encode($build(), JSON_THROW_ON_ERROR);

            return ['json' => $json, 'etag' => self::etagFor($json)];
        });

        return self::respond($request, $entry['json'], $entry['etag'], $sharedSeconds);
    }

    public static function respond(Request $request, string $json, string $etag, int $sharedSeconds): Response
    {
        $headers = [
            'ETag' => $etag,
            'Cache-Control' => "public, max-age=0, s-maxage={$sharedSeconds}, stale-while-revalidate=".($sharedSeconds * 2),
        ];

        if (self::matches($request, $etag)) {
            return response('', 304, $headers);
        }

        return response($json, 200, $headers + ['Content-Type' => 'application/json']);
    }

    public static function etagFor(string $content): string
    {
        return 'W/"'.substr(sha1($content), 0, 20).'"';
    }

    /** Whether the request's If-None-Match already names this version (weak comparison, as HTTP says for GET). */
    private static function matches(Request $request, string $etag): bool
    {
        $header = trim((string) $request->header('If-None-Match', ''));

        if ($header === '') {
            return false;
        }

        if ($header === '*') {
            return true;
        }

        $normalize = fn (string $tag) => preg_replace('#^W/#', '', trim($tag));
        $wanted = $normalize($etag);

        foreach (explode(',', $header) as $candidate) {
            if ($normalize($candidate) === $wanted) {
                return true;
            }
        }

        return false;
    }
}
