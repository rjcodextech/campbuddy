<?php

namespace App\Support;

use App\Models\ContributorTable;
use App\Models\Event;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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
        // edits (deals, quests) that don't go through EventData. The counts are there
        // because "last updated" can't see a removal: taking away one of ten
        // leaves the newest timestamp as it was.
        return Cache::remember(self::key($event->id), 120, function () use ($event) {
            $roster = DB::table('attendee_roster')
                ->where('event_id', $event->id)
                ->where('is_suppressed', false)
                ->selectRaw('COUNT(*) as cnt, MAX(updated_at) as max_upd, SUM(CASE WHEN photo_url IS NOT NULL THEN 1 ELSE 0 END) as photos')
                ->first();

            $parts = [
                $event->status,
                $event->is_visible ? '1' : '0',
                $event->display_name,
                $event->starts_on?->toDateString(),
                $event->ends_on?->toDateString(),
                json_encode($event->info),
                $event->logo_path,
                $event->favicon_path,
                $event->timezone,
                // Memories: the photo album and WordPress.tv links on Home.
                $event->photo_album_url,
                $event->wordpress_tv_url,
                // The site's artwork & tagline behind the name on Home.
                is_array($event->site_theme) ? ($event->site_theme['hero']['url'] ?? null) : null,
                is_array($event->site_theme) ? ($event->site_theme['tagline'] ?? null) : null,
                // Running late delays.
                json_encode($event->schedule_delays),
                // Every deal shown here: its own and the default deals for its country.
                md5(\App\Models\Offer::shownAt($event)->map(fn ($offer) => $offer->id.'@'.$offer->updated_at)->implode(',')),
                // Free Steals: the same list everywhere (a removal changes the count).
                (string) \App\Models\FreeSteal::max('updated_at').'#'.\App\Models\FreeSteal::count(),
                (string) $event->quests()->max('updated_at'),
                (string) $event->quests()->count(),
                (string) ContributorTable::where('event_id', $event->id)->max('updated_at'),
                (string) ContributorTable::where('event_id', $event->id)->count(),
                ($roster->cnt ?? 0).'#'.($roster->max_upd ?? '').'#'.(int) ($roster->photos ?? 0),
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
        CatalogVersion::forget();
        // The event's last day is worked out from its schedule (EventTime::lastDay).
        EventTime::forgetSessionDay($eventId);
    }

    private static function key(int $eventId): string
    {
        return "event:{$eventId}:data-version";
    }
}
