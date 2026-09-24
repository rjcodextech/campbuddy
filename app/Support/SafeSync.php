<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Decides which previously stored items a fresh fetch may remove.
 *
 * A re-fetch merges into what's stored — new items are added, existing ones
 * updated, and only items the source no longer lists are removed — so data is
 * never cleared first and a bad fetch can't blank the app. Two safeguards:
 *
 *   - An empty result removes nothing. A whole schedule, sponsor list or
 *     attendee list vanishing at once is far more likely a site hiccup.
 *   - A big drop (more than half of at least MIN_FOR_GUARD items) is held back
 *     for one run. If the next fetch still doesn't list them, they go then —
 *     so real removals still happen, just one refresh later.
 */
class SafeSync
{
    private const MIN_FOR_GUARD = 6;

    /**
     * @param  string  $key  what's being synced, e.g. "event:5:sessions"
     * @param  array<int, int|string>  $oldIds  ids stored now
     * @param  array<int, int|string>  $newIds  ids the fetch returned
     * @param  bool  $confirmAll  hold back every removal for one run, not only big drops
     * @return array{remove: array<int, int|string>, held: array<int, int|string>}
     */
    public static function removals(string $key, array $oldIds, array $newIds, bool $confirmAll = false): array
    {
        $missing = array_values(array_diff($oldIds, $newIds));
        $pendingKey = "safe-sync:{$key}";

        if ($missing === []) {
            Cache::forget($pendingKey);

            return ['remove' => [], 'held' => []];
        }

        if ($newIds === []) {
            return ['remove' => [], 'held' => $missing];
        }

        // Already held back last time and still missing: now they really go.
        $pending = (array) Cache::get($pendingKey, []);
        $confirmed = array_values(array_intersect($missing, $pending));
        $fresh = array_values(array_diff($missing, $pending));

        $bigDrop = count($oldIds) >= self::MIN_FOR_GUARD && count($missing) > count($oldIds) / 2;

        if (($bigDrop || $confirmAll) && $fresh !== []) {
            Cache::forever($pendingKey, $fresh);

            return ['remove' => $confirmed, 'held' => $fresh];
        }

        Cache::forget($pendingKey);

        return ['remove' => $missing, 'held' => []];
    }
}
