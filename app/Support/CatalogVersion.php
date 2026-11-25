<?php

namespace App\Support;

use App\Models\Event;
use Illuminate\Support\Facades\Cache;

/**
 * A short fingerprint of the public WordCamp directory (Home picker and
 * /api/v1/app/events). Updates whenever crons discover/publish/archive
 * events or refresh their dates, venue, or branding, so both the website
 * home page and native app picker reload automatically.
 */
class CatalogVersion
{
    private const KEY = 'catalog:version';

    public static function current(): string
    {
        return Cache::remember(self::KEY, 120, function () {
            $events = Event::where('status', 'active')
                ->where('is_visible', true)
                ->orderBy('id')
                ->get([
                    'id', 'slug', 'display_name', 'short_name',
                    'starts_on', 'ends_on', 'timezone',
                    'logo_path', 'favicon_path', 'info',
                    'photo_album_url', 'wordpress_tv_url', 'updated_at',
                ]);

            $rows = $events->map(fn (Event $e) => implode('#', [
                $e->id,
                $e->slug,
                $e->display_name,
                $e->short_name ?? '',
                $e->starts_on?->toDateString() ?? '',
                $e->ends_on?->toDateString() ?? '',
                $e->timezone ?? '',
                $e->logo_path ?? '',
                $e->favicon_path ?? '',
                $e->info['venue'] ?? '',
                $e->photo_album_url ?? '',
                $e->wordpress_tv_url ?? '',
                $e->updated_at?->toIso8601String() ?? '',
            ]))->all();

            return substr(sha1(implode('|', $rows)), 0, 12);
        });
    }

    public static function forget(): void
    {
        Cache::forget(self::KEY);
    }
}
