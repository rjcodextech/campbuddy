<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * An event's fetched lists (sessions, speakers, sponsors, organizers): kept
 * for good in the event_feeds table, with the cache in front as the fast
 * path. Reads fall back to the database when the cache has expired or been
 * flushed, and put the value back in the cache — so a stopped cron or a
 * cache purge never blanks a live event's schedule and sponsors.
 */
class EventData
{
    public const KINDS = ['sessions', 'speakers', 'sponsors', 'organizers'];

    private const CACHE_DAYS = 14;

    /** @return array<int, mixed>|null null = never fetched */
    public static function get(int $eventId, string $kind): ?array
    {
        $cached = Cache::get(self::key($eventId, $kind));

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $row = DB::table('event_feeds')->where('event_id', $eventId)->where('kind', $kind)->first(['payload', 'fetched_at']);
        } catch (Throwable) {
            // The table isn't there yet (migrations not run) — cache only, as before.
            return null;
        }

        $value = $row ? json_decode($row->payload, true) : null;

        if (! is_array($value)) {
            return null;
        }

        Cache::put(self::key($eventId, $kind), $value, now()->addDays(self::CACHE_DAYS));
        Cache::add("event:{$eventId}:fetched-at", Carbon::parse($row->fetched_at), now()->addDays(self::CACHE_DAYS));

        return $value;
    }

    /** @param array<int, mixed> $items */
    public static function put(int $eventId, string $kind, array $items): void
    {
        Cache::put(self::key($eventId, $kind), $items, now()->addDays(self::CACHE_DAYS));
        DataVersion::forget($eventId);

        try {
            DB::table('event_feeds')->upsert([[
                'event_id' => $eventId,
                'kind' => $kind,
                'payload' => json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'item_count' => count($items),
                'fetched_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]], ['event_id', 'kind'], ['payload', 'item_count', 'fetched_at', 'updated_at']);
        } catch (Throwable $e) {
            // Not migrated yet: the cache still has it. Worth knowing about, not failing over.
            report($e);
        }
    }

    /**
     * Merges a fresh fetch into what's stored, item by item (matched by id):
     * new items are added, changed ones updated, and ones the site no longer
     * lists removed — within SafeSync's safeguards. Nothing is cleared first,
     * so a failed or odd fetch never leaves the app without data.
     *
     * @param  array<int, array<string, mixed>>  $items  the complete list just fetched
     * @return array{added: int, updated: int, removed: int, held: int, total: int}
     */
    public static function sync(int $eventId, string $kind, array $items): array
    {
        $old = [];
        foreach (self::get($eventId, $kind) ?? [] as $item) {
            if (is_array($item) && isset($item['id'])) {
                $old[$item['id']] = $item;
            }
        }

        $new = [];
        foreach ($items as $item) {
            if (is_array($item) && isset($item['id'])) {
                $new[$item['id']] = $item;
            }
        }

        $decision = SafeSync::removals(self::key($eventId, $kind), array_keys($old), array_keys($new));

        $added = count(array_diff_key($new, $old));
        $updated = 0;
        foreach (array_intersect_key($new, $old) as $id => $item) {
            if ($item != $old[$id]) {
                $updated++;
            }
        }

        // The site's order first, then anything held back for one more run.
        $merged = array_values($new);
        foreach ($decision['held'] as $id) {
            $merged[] = $old[$id];
        }

        self::put($eventId, $kind, $merged);

        return [
            'added' => $added,
            'updated' => $updated,
            'removed' => count($decision['remove']),
            'held' => count($decision['held']),
            'total' => count($merged),
        ];
    }

    public static function count(int $eventId, string $kind): ?int
    {
        $value = self::get($eventId, $kind);

        return $value === null ? null : count($value);
    }

    private static function key(int $eventId, string $kind): string
    {
        return "event:{$eventId}:{$kind}";
    }
}
