<?php

namespace App\Support;

use App\Models\Event;
use Illuminate\Support\Facades\Cache;

/**
 * A short fingerprint of what an event's pages show: its details and its
 * fetched lists. It changes only when the content really changes — not on
 * every refresh that finds nothing new — so an open app reloads only when
 * there's something new to see (data-freshness.js).
 */
class DataVersion
{
    public static function for(Event $event): string
    {
        // Forgotten on every data write; the short expiry also picks up admin
        // edits (deals, quests) that don't go through EventData.
        return Cache::remember(self::key($event->id), 120, function () use ($event) {
            $parts = [
                $event->display_name,
                $event->starts_on?->toDateString(),
                $event->ends_on?->toDateString(),
                json_encode($event->info),
                $event->logo_path,
                (string) $event->offers()->max('updated_at'),
                (string) $event->quests()->max('updated_at'),
            ];

            foreach (EventData::KINDS as $kind) {
                $parts[] = md5(json_encode(EventData::get($event->id, $kind)));
            }

            return substr(sha1(implode('|', $parts)), 0, 12);
        });
    }

    /** Called whenever an event or its data is written. */
    public static function forget(int $eventId): void
    {
        Cache::forget(self::key($eventId));
    }

    private static function key(int $eventId): string
    {
        return "event:{$eventId}:data-version";
    }
}
