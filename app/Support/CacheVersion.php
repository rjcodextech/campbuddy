<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * The "cache version" — a millisecond timestamp bumped every time an admin
 * purges the caches. It is how a purge reaches phones: every attendee page
 * carries the current version in a <meta> tag, and the app's JS compares it
 * with the one it last saw on that device; a newer version means "drop your
 * saved copies and reload".
 *
 * Kept in a small file on the private disk, deliberately NOT in the
 * application cache: the purge empties that cache, and the version has to
 * survive it. The same file remembers who purged and when, for the dashboard.
 */
class CacheVersion
{
    private const FILE = 'cache-purge.json';

    /** The version as a string of digits; "0" until the first purge. */
    public static function current(): string
    {
        return (string) (self::read()['version'] ?? 0);
    }

    /**
     * @return array{version: int, purged_at: string, by: ?string, summary: ?string}|null
     */
    public static function last(): ?array
    {
        $record = self::read();

        return isset($record['version'], $record['purged_at']) ? $record : null;
    }

    /**
     * Starts a new version. Always strictly greater than the last one, even
     * if the clock hasn't moved (or has been set back), so every device that
     * has seen the old version sees this as newer.
     */
    public static function bump(?string $by = null, ?string $summary = null): string
    {
        $version = max((int) (microtime(true) * 1000), (int) (self::read()['version'] ?? 0) + 1);

        Storage::disk('local')->put(self::FILE, json_encode([
            'version' => $version,
            'purged_at' => now()->toIso8601String(),
            'by' => $by,
            'summary' => $summary,
        ], JSON_UNESCAPED_SLASHES));

        return (string) $version;
    }

    /** @return array<string, mixed> */
    private static function read(): array
    {
        try {
            $disk = Storage::disk('local');

            if (! $disk->exists(self::FILE)) {
                return [];
            }

            $record = json_decode((string) $disk->get(self::FILE), true);

            return is_array($record) ? $record : [];
        } catch (Throwable) {
            // An unreadable file must never take a page down — "no purge yet" is a safe answer.
            return [];
        }
    }
}
