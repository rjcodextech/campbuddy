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
 * site again doesn't bring it back; so is a piece someone chose from the
 * site's other artwork (`pinned`, "Use this") and a kind someone corrected
 * (`kinds`).
 */
class SiteTheme
{
    public const SLOTS = 5;

    public const KINDS = ['pattern', 'illustration', 'photo'];

    /** Kept with the theme across "Read the site again". */
    private const KEPT = ['uploads', 'hidden', 'pinned', 'kinds'];

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
            'kind' => in_array($kind, self::KINDS, true) ? $kind : 'illustration',
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
            $theme['pinned'] = array_values(array_diff($theme['pinned'] ?? [], [$piece['url']]));
        }
        self::store($event, $theme);

        return 'Artwork taken off.';
    }

    /**
     * The site's other artwork, not in use now (pieces taken off included),
     * to choose from with "Use this".
     *
     * @return list<array{url: string, kind: string, name: string, id: string, hidden: bool}>
     */
    public static function more(array $theme): array
    {
        $used = array_column(self::pieces($theme), 'url');
        $hidden = array_flip($theme['hidden'] ?? []);

        return array_values(array_map(
            fn ($a) => $a + ['id' => substr(md5($a['url']), 0, 12), 'hidden' => isset($hidden[$a['url']])],
            array_filter($theme['art'] ?? [], fn ($a) => ! in_array($a['url'], $used, true)),
        ));
    }

    /**
     * Puts one of the site's other pieces in use: it comes first, and the
     * last piece CampBuddy picked by itself makes room.
     */
    public static function use(Event $event, string $id): string
    {
        $theme = self::saved($event);
        $piece = collect(self::more($theme ?? []))->firstWhere('id', $id);
        if ($theme === null || $piece === null) {
            return 'That artwork is already in use.';
        }
        $room = self::SLOTS - count($theme['uploads'] ?? []);
        if ($room <= 0) {
            return 'All '.self::SLOTS.' artwork places hold uploads: take one off first.';
        }
        $theme['hidden'] = array_values(array_diff($theme['hidden'] ?? [], [$piece['url']]));
        $theme['pinned'] = array_slice(array_values(array_unique([$piece['url'], ...($theme['pinned'] ?? [])])), 0, $room);
        self::store($event, $theme);

        return 'Artwork "'.$piece['name'].'" is in use.';
    }

    /** Says what a piece is (pattern, illustration or photo) when the guess was wrong. */
    public static function setKind(Event $event, string $id, string $kind): string
    {
        $theme = self::saved($event);
        $piece = collect(self::pieces($theme ?? []))->firstWhere('id', $id);
        if ($theme === null || $piece === null || ! in_array($kind, self::KINDS, true)) {
            return 'That artwork is no longer in use.';
        }
        $theme['kinds'] = [...($theme['kinds'] ?? []), $piece['url'] => $kind];
        self::store($event, $theme);

        return 'Artwork "'.$piece['name'].'" is now used as '.($kind === 'illustration' ? 'an illustration' : 'a '.$kind).'.';
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
        $kinds = $theme['kinds'] ?? [];
        $withKind = fn (array $a) => in_array($kinds[$a['url']] ?? null, self::KINDS, true) ? ['kind' => $kinds[$a['url']]] + $a : $a;
        $site = array_values(array_map($withKind, array_filter($theme['art'] ?? [], fn ($a) => ! isset($hidden[$a['url']]))));
        $uploads = array_map($withKind, $theme['uploads'] ?? []);
        $room = max(0, self::SLOTS - count($uploads));

        // Pieces someone chose ("Use this") first, the newest choice first…
        $pinned = [];
        foreach ($theme['pinned'] ?? [] as $url) {
            foreach ($site as $i => $a) {
                if ($a['url'] === $url) {
                    $pinned[$i] = $a;
                }
            }
        }

        // …then one of each kind, then the rest in the site's order.
        $first = $pinned;
        foreach (self::KINDS as $kind) {
            if (in_array($kind, array_column($first, 'kind'), true)) {
                continue;
            }
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
        return ['art' => self::pieces($theme), 'more' => self::more($theme), 'tagline' => $theme['tagline'] ?? null] + $theme;
    }

    /**
     * The piece the attendee app shows faintly behind the event's name (Home
     * and Memories): a local copy, so it works offline and asks nothing of
     * the WordCamp site. Null while the event has no illustration or pattern.
     *
     * @return ?array{url: string, kind: string}
     */
    public static function hero(Event $event): ?array
    {
        $hero = self::saved($event)['hero'] ?? null;

        return is_array($hero) && is_string($hero['url'] ?? null) && str_starts_with($hero['url'], '/storage/site-art/') ? $hero : null;
    }

    /**
     * Keeps `hero` in step with the artwork in use: the first illustration
     * (a skyline reads best as a backdrop), else the first pattern; never a
     * photo (too busy behind words). Fetched once and kept on the public disk;
     * the old copy is deleted when the choice changes.
     */
    private static function withHero(Event $event, array $theme): array
    {
        $pieces = self::pieces($theme);
        $pick = collect($pieces)->firstWhere('kind', 'illustration') ?? collect($pieces)->firstWhere('kind', 'pattern');
        $old = $theme['hero'] ?? null;

        if ($pick !== null && ($old['source'] ?? null) === $pick['url'] && ($old['kind'] ?? null) === $pick['kind']) {
            return $theme;
        }

        $disk = Storage::disk('public');
        if (is_array($old) && str_starts_with((string) ($old['url'] ?? ''), '/storage/site-art/')) {
            $disk->delete(substr($old['url'], strlen('/storage/')));
        }
        unset($theme['hero']);
        if ($pick === null) {
            return $theme;
        }

        try {
            if ($pick['uploaded'] ?? false) {
                $body = $disk->get(substr($pick['url'], strlen('/storage/')));
            } else {
                $response = Http::timeout(10)->withHeaders(['User-Agent' => 'CampBuddy (+https://campbuddy.club)'])->get($pick['url']);
                $body = $response->successful() ? $response->body() : null;
            }
            $info = is_string($body) && strlen($body) <= 4 * 1024 * 1024 ? @getimagesizefromstring($body) : false;
            $ext = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'][$info['mime'] ?? ''] ?? null;
            if ($ext === null) {
                return $theme;
            }
            $path = "site-art/{$event->id}/hero-".substr(md5($pick['url']), 0, 12).".{$ext}";
            $disk->put($path, $body);
            $theme['hero'] = ['url' => '/storage/'.$path, 'kind' => $pick['kind'], 'source' => $pick['url'], 'alpha' => self::hasAlpha($body, $ext)];
        } catch (Throwable $e) {
            report($e);
        }

        return $theme;
    }

    /**
     * Whether an image has see-through parts (a skyline cut out on nothing):
     * such a piece is drawn as a silhouette in the app's colour, so a white
     * skyline doesn't vanish on the cream page.
     */
    private static function hasAlpha(string $body, string $ext): bool
    {
        if ($ext === 'png') {
            // IHDR colour type 4 or 6 has an alpha channel; a tRNS chunk makes one colour transparent.
            return in_array(ord($body[25] ?? "\0"), [4, 6], true) || str_contains(substr($body, 0, 4096), 'tRNS');
        }
        if ($ext === 'webp') {
            $chunk = substr($body, 12, 4);
            if ($chunk === 'VP8X') {
                return (ord($body[20] ?? "\0") & 0x10) !== 0;
            }
            if ($chunk === 'VP8L' && strlen($body) >= 25) {
                return ((unpack('V', substr($body, 21, 4))[1] >> 28) & 1) === 1;
            }
        }

        return false;
    }

    private static function store(Event $event, array $theme): void
    {
        $theme = self::withHero($event, $theme);
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
     * Every image in the site's media library (the newest 200), with its
     * size, title and alt text, so artwork uploaded for any page — not just
     * the home page — is found, and judged by more than its file name.
     * Empty when the library can't be read.
     *
     * @return list<array{url: string, width: ?int, height: ?int, title: string, alt: string}>
     */
    private static function mediaLibrary(string $site): array
    {
        $out = [];
        foreach ([1, 2] as $page) {
            try {
                $items = Http::timeout(10)->acceptJson()
                    ->get($site.'/wp-json/wp/v2/media', ['per_page' => 100, 'page' => $page, 'media_type' => 'image', '_fields' => 'source_url,title,alt_text,media_details'])
                    ->throw()->json();
            } catch (Throwable $e) {
                if ($page === 1) {
                    report($e);
                }
                break;
            }
            if (! is_array($items)) {
                break;
            }
            foreach ($items as $item) {
                $url = is_array($item) ? SafeUrl::web($item['source_url'] ?? null) : null;
                if ($url === null) {
                    continue;
                }
                $size = fn (string $side) => is_numeric($item['media_details'][$side] ?? null) ? (int) $item['media_details'][$side] : null;
                $out[] = [
                    'url' => $url,
                    'width' => $size('width'),
                    'height' => $size('height'),
                    'title' => html_entity_decode(strip_tags((string) ($item['title']['rendered'] ?? '')), ENT_QUOTES | ENT_HTML5),
                    'alt' => (string) ($item['alt_text'] ?? ''),
                ];
            }
            // A second page only when the first was full.
            if (count($items) < 100) {
                break;
            }
        }

        return $out;
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
