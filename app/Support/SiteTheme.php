<?php

namespace App\Support;

use App\Models\Event;
use App\Services\SiteThemeScraper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The WordCamp site's colours, fonts and artwork (SiteThemeScraper), kept on
 * the event (`site_theme`) for the Social media designs. Read once, the first
 * time the page needs it, and again whenever someone presses "Read the site
 * again"; never on a schedule.
 */
class SiteTheme
{
    /** @return array{colors: list<array>, fonts: array, art: list<array>, fetched_at: ?string, error?: string} */
    public static function get(Event $event): array
    {
        $saved = self::saved($event);
        if ($saved !== null) {
            return $saved;
        }

        // First visit: read it now, at most once a minute per event (a site that fails isn't hammered).
        return Cache::add("site-theme:{$event->id}", 1, 60) ? self::refresh($event) : self::empty();
    }

    public static function refresh(Event $event): array
    {
        try {
            $response = Http::timeout(12)->withHeaders(['User-Agent' => 'CampBuddy (+https://campbuddy.club)'])->get(rtrim((string) $event->source_site_url, '/').'/');
            $response->throw();
            $theme = (new SiteThemeScraper)->parse($response->body(), self::notArt($event)) + ['fetched_at' => now()->toIso8601String()];
        } catch (Throwable $e) {
            report($e);

            return self::empty('The WordCamp site could not be read just now.');
        }

        try {
            $event->forceFill(['site_theme' => $theme])->save();
        } catch (Throwable $e) {
            // Before `php artisan migrate`: used for this page view, not kept.
            report($e);
        }

        return $theme;
    }

    private static function saved(Event $event): ?array
    {
        try {
            $theme = $event->site_theme;
        } catch (Throwable) {
            return null;
        }

        return is_array($theme) && isset($theme['fetched_at']) ? $theme : null;
    }

    private static function empty(?string $error = null): array
    {
        return ['colors' => [], 'fonts' => [], 'art' => [], 'fetched_at' => null] + ($error ? ['error' => $error] : []);
    }

    /** Images that are people or sponsors, never artwork. @return list<string> */
    private static function notArt(Event $event): array
    {
        return array_values(array_filter([
            ...array_column(EventData::get($event->id, 'sponsors') ?? [], 'logo_url'),
            ...array_column(EventData::get($event->id, 'speakers') ?? [], 'avatar_url'),
        ]));
    }
}
