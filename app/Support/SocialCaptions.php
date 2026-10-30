<?php

namespace App\Support;

/**
 * One caption per network for the Social media studio's posts, people cards
 * and reels, from the words SocialKit writes (owner's choice, 30 Oct 2026).
 * resources/js/social-captions.js puts the browser-made ones (a day's talks,
 * a spotlight, a reel's group) together the same way; tests keep the two alike.
 *
 *  - Instagram: up to 2,200 characters and 5 hashtags in all (the cap Instagram
 *    has shown since Dec 2025, safe either way). A link doesn't open in a
 *    caption, so it points to the QR on the card (or at the end of the reel)
 *    and to campbuddy.club, and writes any other address without https://.
 *  - LinkedIn: up to 3,000 characters, the hook first (about 140 show before
 *    "see more" on a phone), the link, 5 hashtags.
 *  - X: 280 counted the way X counts (a link 23, an emoji 2). The hook and the
 *    link always; then up to 3 hashtags, the list, the body and the site's
 *    tagline, each only while it fits.
 *
 * No emoji, no cut names: a line that doesn't fit is left out, never cut.
 */
class SocialCaptions
{
    public const NETWORKS = ['instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'x' => 'X'];

    public const LIMITS = ['instagram' => 2200, 'linkedin' => 3000, 'x' => 280];

    public const MAX_TAGS = ['instagram' => 5, 'linkedin' => 5, 'x' => 3];

    /** What X counts a link as, whatever its length. */
    private const X_LINK = 23;

    /** A link, with or without https:// (X links a bare address too). */
    private const URL = '~(?:https?://\S+|\b[a-z0-9-]+(?:\.[a-z0-9-]+)*\.[a-z]{2,}(?:/\S*)?)~iu';

    /**
     * @param array{opening?: ?string, hook: string, xhook?: string|list<string>, body?: ?string, xbody?: ?string, lines?: list<string>, extra?: ?string, cta: string, link: string, media?: string} $parts
     *   opening: the site's tagline, before the hook; hook: the first line; xhook: fuller first
     *   lines for X, the first that fits is used; body: a paragraph or two (xbody: X's own);
     *   lines: a list (a day's talks), one per line, as many as fit; extra: one more paragraph
     *   (names), only when it fits; cta + link: what to do and where; media: card | reel.
     * @return array{instagram: string, linkedin: string, x: string}
     */
    public static function make(array $parts, string $hashtags): array
    {
        $p = [
            'opening' => self::clean($parts['opening'] ?? null),
            'hook' => trim((string) $parts['hook']),
            'body' => self::clean($parts['body'] ?? null),
            'extra' => self::clean($parts['extra'] ?? null),
            'lines' => array_values(array_filter(array_map(fn ($l) => trim((string) $l), $parts['lines'] ?? []), fn ($l) => $l !== '')),
        ];
        $cta = trim((string) $parts['cta']);
        $link = trim((string) $parts['link']);
        $where = ($parts['media'] ?? 'card') === 'reel' ? 'the QR at the end of the reel' : 'the QR on the card';
        $tags = self::tags($hashtags);

        return [
            'instagram' => self::long('instagram', $p, "{$cta}: scan {$where}, or open campbuddy.club.", $tags),
            'linkedin' => self::long('linkedin', $p, "{$cta}: {$link}", $tags),
            'x' => self::x($parts, $p, "{$cta}: {$link}", $tags),
        ];
    }

    /** Instagram and LinkedIn: everything (the names after the hook), then without the names, fewer lines, no body, until it is in the limit. */
    private static function long(string $network, array $p, string $end, array $tags): string
    {
        $limit = self::LIMITS[$network];
        $compose = function (array $list, bool $withBody, bool $withExtra) use ($network, $p, $end, $tags) {
            $words = self::join([$p['opening'], $p['hook'], $list === [] ? null : implode("\n", $list), $withExtra ? $p['extra'] : null, $withBody ? $p['body'] : null, $end]);
            if ($network === 'instagram') {
                $words = (string) preg_replace('~https?://~i', '', $words);
            }

            return self::join([$words, self::tagLine($tags, $words, self::MAX_TAGS[$network])]);
        };
        $list = $p['lines'];
        $text = $compose($list, true, true);
        if (mb_strlen($text) > $limit) {
            $text = $compose($list, true, false);
        }
        while ($list !== [] && mb_strlen($text) > $limit) {
            array_pop($list);
            $text = $compose($list, true, false);
        }

        return mb_strlen($text) > $limit ? $compose($list, false, false) : $text;
    }

    private static function x(array $parts, array $p, string $end, array $tags): string
    {
        $fits = fn (string $t) => self::xLength($t) <= self::LIMITS['x'];
        $compose = fn (?string $open, string $head, array $list, array $more, array $chosen) => self::join([
            $open, $head, $list === [] ? null : implode("\n", $list), ...$more, $end, $chosen === [] ? null : implode(' ', $chosen),
        ]);

        // The fullest first line that fits with the hashtags (they come first: the event, CampBuddy,
        // WPSimplified), else the first that fits alone, else the shortest there is.
        $heads = array_values(array_filter(array_map(fn ($h) => trim((string) $h), [...(array) ($parts['xhook'] ?? []), $p['hook']]), fn ($h) => $h !== ''));
        $xTags = fn (string $h) => self::shown($tags, array_slice(array_values(array_filter(self::byPriority($tags), fn ($t) => ! self::has($h, $t))), 0, self::MAX_TAGS['x']));
        $head = null;
        foreach ($heads as $h) {
            if ($fits($compose(null, $h, [], [], $xTags($h)))) {
                $head = $h;
                break;
            }
        }
        foreach ($head === null ? $heads : [] as $h) {
            if ($fits($compose(null, $h, [], [], []))) {
                $head = $h;
                break;
            }
        }
        if ($head === null) {
            usort($heads, fn ($a, $b) => self::xLength($a) <=> self::xLength($b));
            $head = self::shorten($heads[0] ?? '', self::LIMITS['x'] - self::xLength($compose(null, '', [], [], [])) - 2);
        }

        // Hashtags first (the event's own, #CampBuddy, #WPSimplified), then the list, the body, the extra, the tagline.
        $chosen = [];
        foreach (self::byPriority($tags) as $tag) {
            $try = self::shown($tags, [...$chosen, $tag]);
            if (count($try) <= self::MAX_TAGS['x'] && ! self::has($head, $tag) && $fits($compose(null, $head, [], [], $try))) {
                $chosen = $try;
            }
        }
        $list = [];
        foreach ($p['lines'] as $line) {
            if (! $fits($compose(null, $head, [...$list, $line], [], $chosen))) {
                break;
            }
            $list[] = $line;
        }
        $more = [];
        foreach ([self::clean($parts['xbody'] ?? null) ?? $p['body'], $p['extra']] as $para) {
            if ($para !== null && $fits($compose(null, $head, $list, [...$more, $para], $chosen))) {
                $more[] = $para;
            }
        }
        $open = $p['opening'] !== null && $fits($compose($p['opening'], $head, $list, $more, $chosen)) ? $p['opening'] : null;

        return $compose($open, $head, $list, $more, $chosen);
    }

    /**
     * The hashtags a caption may end with, in the order shown: the event's own
     * (#WCRajasthan, from SocialKit::hashtags), #WordCamp, #WordPress,
     * #CampBuddy, #WPSimplified.
     *
     * @return list<string>
     */
    public static function tags(string $hashtags): array
    {
        return array_values(array_unique(array_filter([self::ownTag($hashtags), '#WordCamp', '#WordPress', '#CampBuddy', '#WPSimplified'])));
    }

    /** "#WCRajasthan" from "#WordCamp #WordPress #WCRajasthan #WCRajasthan2026", or null. */
    public static function ownTag(string $hashtags): ?string
    {
        foreach (preg_split('/\s+/', trim($hashtags)) ?: [] as $tag) {
            if (str_starts_with($tag, '#') && mb_strlen($tag) > 1 && ! in_array(mb_strtolower($tag), ['#wordcamp', '#wordpress'], true)) {
                return $tag;
            }
        }

        return null;
    }

    /** How many hashtags a text has (Instagram counts the ones in the words too). */
    public static function tagCount(string $text): int
    {
        return preg_match_all('/(?<![\p{L}\p{N}_&])#[\p{L}\p{N}_]+/u', $text);
    }

    /**
     * The tag line for `words`: the tags it doesn't already say, as many as the
     * network allows in all, the most wanted kept (the event's own, #CampBuddy,
     * #WPSimplified, then #WordCamp, #WordPress).
     */
    private static function tagLine(array $tags, string $words, int $max): string
    {
        $room = $max - self::tagCount($words);
        $chosen = array_slice(array_values(array_filter(self::byPriority($tags), fn ($t) => ! self::has($words, $t))), 0, max(0, $room));

        return implode(' ', self::shown($tags, $chosen));
    }

    /** @return list<string> */
    private static function byPriority(array $tags): array
    {
        $first = array_values(array_diff($tags, ['#WordCamp', '#WordPress']));

        return [...$first, ...array_values(array_intersect($tags, ['#WordCamp', '#WordPress']))];
    }

    /** @return list<string> the chosen tags in the usual order */
    private static function shown(array $tags, array $chosen): array
    {
        return array_values(array_intersect($tags, $chosen));
    }

    private static function has(string $text, string $tag): bool
    {
        return (bool) preg_match('/(?<![\p{L}\p{N}_&])'.preg_quote($tag, '/').'(?![\p{L}\p{N}_])/iu', $text);
    }

    /**
     * A text's length the way X counts it: a link (with or without https://)
     * 23, most Latin letters and punctuation 1, anything else (emoji, CJK,
     * some symbols) 2.
     */
    public static function xLength(string $text): int
    {
        $text = (string) preg_replace(self::URL, str_repeat('x', self::X_LINK), $text);
        $n = 0;
        foreach (mb_str_split($text) as $ch) {
            $c = mb_ord($ch);
            $n += ($c <= 4351 || ($c >= 8192 && $c <= 8205) || ($c >= 8208 && $c <= 8223) || ($c >= 8242 && $c <= 8247)) ? 1 : 2;
        }

        return $n;
    }

    /** "A", "A and B", "A, B and C": every name, whole. */
    public static function names(array $names): string
    {
        $names = array_values(array_filter(array_map(fn ($n) => trim((string) $n), $names), fn ($n) => $n !== ''));
        if (count($names) < 2) {
            return $names[0] ?? '';
        }

        return implode(', ', array_slice($names, 0, -1)).' and '.end($names);
    }

    private static function clean(?string $text): ?string
    {
        $text = trim((string) $text);

        return $text === '' ? null : $text;
    }

    private static function join(array $parts): string
    {
        return implode("\n\n", array_values(array_filter($parts, fn ($p) => is_string($p) && trim($p) !== '')));
    }

    /** Only when even the shortest first line can't fit (never with real event names): whole words, then "…". */
    private static function shorten(string $text, int $room): string
    {
        if (self::xLength($text) <= $room) {
            return $text;
        }
        $words = preg_split('/\s+/', $text) ?: [];
        while (count($words) > 1 && self::xLength(implode(' ', $words).'…') > $room) {
            array_pop($words);
        }

        return implode(' ', $words).'…';
    }
}
