<?php

namespace App\Support;

use App\Models\Event;
use App\Services\SiteThemeScraper;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * The WordCamp site's colours, fonts, tagline and artwork (SiteThemeScraper),
 * kept on the event (`site_theme`) for the Social media designs. Read once,
 * the first time the page needs it, and again whenever someone presses "Read
 * the site again"; never on a schedule.
 *
 * Artwork has SLOTS places: what the site has (its home page and its media
 * library), then pieces uploaded by an admin or the event's manager into the
 * slots left empty. A piece taken off is remembered (`hidden`), so reading the
 * site again doesn't bring it back.
 */
class SiteTheme
{
    public const SLOTS = 5;

    /** Kept with the theme across "Read the site again". */
    private const KEPT = ['uploads', 'hidden'];

    /** WordPress's own placeholder taglines: the site didn't choose them. */
    private const PLACEHOLDER_TAGLINES = '/^(just another (wordpress|wordcamp)|my wordpress blog|$)/i';

    /** @return array{colors: list<array>, fonts: array, art: list<array>, tagline: ?string, fetched_at: ?string, error?: string} */
    public static function get(Event $event): array
    {
        $saved = self::saved($event);
        if ($saved !== null) {
            return self::withPieces($saved);
        }

        // First visit: read it now, at most once a minute per event (a site that fails isn't hammered).
        return Cache::add("site-theme:{$event->id}", 1, 60) ? self::refresh($event) : self::empty();
    }

    public static function refresh(Event $event): array
    {
        $site = rtrim((string) $event->source_site_url, '/');
        try {
            $response = Http::timeout(12)->withHeaders(['User-Agent' => 'CampBuddy (+https://campbuddy.club)'])->get($site.'/');
            $response->throw();
            $theme = (new SiteThemeScraper)->parse($response->body(), self::notArt($event), self::mediaLibrary($site))
                + ['tagline' => self::readTagline($site, (string) ($event->info['venue'] ?? '')), 'fetched_at' => now()->toIso8601String()];
        } catch (Throwable $e) {
            report($e);

            return self::empty('The WordCamp site could not be read just now.');
        }

        $theme += array_intersect_key(self::saved($event) ?? [], array_flip(self::KEPT));
        self::store($event, $theme);

        return self::withPieces($theme);
    }

    /** The site's tagline as saved (no fetch), or null. */
    public static function tagline(Event $event): ?string
    {
        $tagline = self::saved($event)['tagline'] ?? null;

        return is_string($tagline) && $tagline !== '' ? $tagline : null;
    }

    /**
     * Adds an uploaded piece of artwork in an empty slot.
     *
     * @return string what happened, for the page
     */
    public static function upload(Event $event, UploadedFile $file, string $kind): string
    {
        $theme = self::saved($event) ?? self::empty();
        if (count(self::pieces($theme)) >= self::SLOTS) {
            return 'All '.self::SLOTS.' artwork places are taken: take one off first.';
        }
        $path = $file->store("social-art/{$event->id}", 'public');
        $theme['uploads'] = [...($theme['uploads'] ?? []), [
            'url' => '/storage/'.$path,
            'kind' => in_array($kind, ['pattern', 'illustration', 'photo'], true) ? $kind : 'illustration',
            'name' => mb_substr(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), 0, 60),
            'uploaded' => true,
        ]];
        $theme['fetched_at'] ??= now()->toIso8601String();
        self::store($event, $theme);

        return 'Artwork added.';
    }

    /**
     * Takes a piece off: an uploaded one is deleted, one from the site is
     * hidden (reading the site again won't bring it back).
     */
    public static function remove(Event $event, string $id): string
    {
        $theme = self::saved($event);
        $piece = collect(self::pieces($theme ?? []))->firstWhere('id', $id);
        if ($theme === null || $piece === null) {
            return 'That artwork was already taken off.';
        }
        if ($piece['uploaded'] ?? false) {
            Storage::disk('public')->delete(substr($piece['url'], strlen('/storage/')));
            $theme['uploads'] = array_values(array_filter($theme['uploads'] ?? [], fn ($u) => $u['url'] !== $piece['url']));
        } else {
            $theme['hidden'] = array_values(array_unique([...($theme['hidden'] ?? []), $piece['url']]));
        }
        self::store($event, $theme);

        return 'Artwork taken off.';
    }

    /**
     * The pieces in use, at most SLOTS: the site's (not hidden; a mix of
     * kinds first, so a pattern, an illustration and a photo all get a
     * place), then the uploaded ones. Each with an `id` for taking it off.
     *
     * @return list<array{url: string, kind: string, name: string, id: string, uploaded?: bool}>
     */
    public static function pieces(array $theme): array
    {
        $hidden = array_flip($theme['hidden'] ?? []);
        $site = array_values(array_filter($theme['art'] ?? [], fn ($a) => ! isset($hidden[$a['url']])));
        $uploads = $theme['uploads'] ?? [];
        $room = max(0, self::SLOTS - count($uploads));

        // One of each kind first, then the rest in the site's order.
        $first = [];
        foreach (['pattern', 'illustration', 'photo'] as $kind) {
            foreach ($site as $i => $a) {
                if ($a['kind'] === $kind) {
                    $first[$i] = $a;
                    break;
                }
            }
        }
        $ordered = array_values($first + $site);

        return array_map(
            fn ($a) => $a + ['id' => substr(md5($a['url']), 0, 12)],
            [...array_slice($ordered, 0, $room), ...$uploads],
        );
    }

    private static function withPieces(array $theme): array
    {
        return ['art' => self::pieces($theme), 'tagline' => $theme['tagline'] ?? null] + $theme;
    }

    private static function store(Event $event, array $theme): void
    {
        try {
            $event->forceFill(['site_theme' => $theme])->save();
        } catch (Throwable $e) {
            // Before `php artisan migrate`: used for this page view, not kept.
            report($e);
        }
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
        return ['colors' => [], 'fonts' => [], 'art' => [], 'tagline' => null, 'fetched_at' => null] + ($error ? ['error' => $error] : []);
    }

    /**
     * Every image in the site's media library (newest 100), so artwork
     * uploaded for any page — not just the home page — is found. Empty when
     * the library can't be read.
     *
     * @return list<string>
     */
    private static function mediaLibrary(string $site): array
    {
        try {
            $items = Http::timeout(10)->acceptJson()
                ->get($site.'/wp-json/wp/v2/media', ['per_page' => 100, 'media_type' => 'image', '_fields' => 'source_url'])
                ->throw()->json();
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        return is_array($items) ? array_values(array_filter(array_map(fn ($i) => is_array($i) ? SafeUrl::web($i['source_url'] ?? null) : null, $items))) : [];
    }

    /**
     * The site's tagline (WordPress "Tagline" setting, from its REST index):
     * "Dil se Dilli, code se community". A leading date ("Nov 21–22, Namma
     * WordPress") is dropped; a tagline that is only a date, or a date and the
     * venue ("6th November, Whitworth Locke"), isn't one; nor is WordPress's
     * own placeholder.
     */
    private static function readTagline(string $site, string $venue = ''): ?string
    {
        try {
            $description = Http::timeout(8)->acceptJson()->get($site.'/wp-json/', ['_fields' => 'description'])->throw()->json('description');
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        return self::cleanTagline((string) $description, $venue);
    }

    public static function cleanTagline(string $description, string $venue = ''): ?string
    {
        $tagline = trim(html_entity_decode(strip_tags($description), ENT_QUOTES | ENT_HTML5));
        $day = '\d{1,2}(?:st|nd|rd|th)?';
        $date = "(?:{$day}(?:\s*(?:[-–&]|to)\s*{$day})?\s+[a-z]+|[a-z]+\s+{$day}(?:\s*(?:[-–&]|to)\s*{$day})?)(?:,?\s*\d{4})?";
        $tagline = trim((string) preg_replace("/^{$date}\s*(?:[,:|·–-]\s*|$)/iu", '', $tagline));
        $months = '/\b(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*\b|\b20\d\d\b/i';
        $isVenue = $venue !== '' && $tagline !== '' && (stripos($venue, $tagline) !== false || stripos($tagline, $venue) !== false);

        return $tagline === '' || $isVenue || preg_match(self::PLACEHOLDER_TAGLINES, $tagline) || preg_match($months, $tagline) || mb_strlen($tagline) > 80 ? null : $tagline;
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
