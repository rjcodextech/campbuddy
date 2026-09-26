<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the fetch log from growing for ever.
 *
 * An active event adds a row every 15 minutes (the schedule fetch), plus the
 * daily ones — about 26,000 rows a month on the live site, 300,000 in a year,
 * and the admin's Errors page and Dashboard scan it. Nothing reads it for more
 * than the last few days, except "the last fetch of each kind" (the Data column,
 * "Last fetch", "Last auto-fetch", the Dashboard's "Last fetched") — so rows
 * older than KEEP_DAYS go, but the newest row of every (event, kind of fetch)
 * stays however old it is: a logo fetched once three weeks ago still says so.
 *
 * Deleted in chunks, so a big backlog (the first run on a long-lived install)
 * never holds a lock on a shared host for long.
 */
class FetchLogRetention
{
    public const KEEP_DAYS = 7;

    public const CHUNK = 5000;

    /**
     * Removes what has expired.
     *
     * @return int how many rows were removed
     */
    public static function prune(?CarbonInterface $now = null, int $chunk = self::CHUNK): int
    {
        $deleted = 0;

        do {
            $ids = self::expired($now)->orderBy('id')->limit($chunk)->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $deleted += DB::table('fetch_log')->whereIn('id', $ids->all())->delete();
        } while ($ids->count() === $chunk);

        return $deleted;
    }

    /** The rows that have expired: older than KEEP_DAYS and not the newest of their (event, kind of fetch). */
    public static function expired(?CarbonInterface $now = null): Builder
    {
        $cutoff = ($now ?? now())->copy()->subDays(self::KEEP_DAYS);

        // One row per event and kind of fetch — a few hundred ids at most.
        $newest = DB::table('fetch_log')->selectRaw('max(id) as id')->groupBy('event_id', 'job_type')->pluck('id')->all();

        return DB::table('fetch_log')->where('fetched_at', '<', $cutoff)->whereNotIn('id', $newest);
    }
}
