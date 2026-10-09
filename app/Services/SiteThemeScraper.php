<?php

namespace App\Services;

use App\Support\SafeUrl;

/**
 * Reads a WordCamp site's own look from its home page, for the Social media
 * designs: the colours its theme defines (block-theme presets the site
 * changed or added, and colours its own CSS uses most), its heading and body
 * fonts (the @font-face files the site serves), and its artwork — background
 * tiles and patterns from its CSS, and illustrations such as a skyline or a
 * motif from its media library.
 *
 * Only what the page itself shows publicly; nothing is guessed. Logos, icons,
 * photos of people and sponsors are left out.
 */
class SiteThemeScraper
{
    /** Twenty Twenty-Four's own presets: a site that didn't change them didn't choose them. */
    private const THEME_DEFAULTS = [
        'base' => '#f9f9f9', 'base-2' => '#ffffff', 'contrast' => '#111111', 'contrast-2' => '#636363', 'contrast-3' => '#a4a4a4',
        'accent' => '#cfcabe', 'accent-2' => '#c2a990', 'accent-3' => '#d8613c', 'accent-4' => '#b1c5a4', 'accent-5' => '#b5bdbc',
    ];

    /** WordPress core's palette, present on every site. */
    private const CORE = ['black', 'white', 'cyan-bluish-gray', 'pale-pink', 'vivid-red', 'luminous-vivid-orange', 'luminous-vivid-amber', 'light-green-cyan', 'vivid-green-cyan', 'pale-cyan-blue', 'vivid-cyan-blue', 'vivid-purple'];

    private const ART_WORDS = '/(pattern|motif|tile|jali|texture|skyline|illustration|sprig|wall|banner|hero|backdrop|bg|background|mandala|border|frame|doodle)/i';

    private const SKIP_WORDS = '/(logo|icon|avatar|gravatar|favicon|sponsor|speaker|profile|headshot|flag|qr|whatsapp|jetpack|badge)/i';

    /** @return array{colors: list<array{name: string, hex: string}>, fonts: array<string, array{family: string, faces: list<array{url: string, weight: string, style: string, range: ?string}>}>, art: list<array{url: string, kind: string, name: string}>} */
    public function parse(string $html, array $skipUrls = []): array
    {
        return [
            'colors' => $this->colors($html),
            'fonts' => $this->fonts($html),
            'art' => $this->art($html, $skipUrls),
        ];
    }

    /** @return list<array{name: string, hex: string}> */
    private function colors(string $html): array
    {
        $found = [];
        preg_match_all('/--wp--preset--color--([a-z0-9-]+):\s*(#[0-9a-f]{6}|#[0-9a-f]{3})\b/i', $html, $presets, PREG_SET_ORDER);
        foreach ($presets as [, $name, $hex]) {
            $hex = $this->hex($hex);
            if (in_array($name, self::CORE, true) || (self::THEME_DEFAULTS[$name] ?? null) === $hex) {
                continue;
            }
            $found[$hex] ??= ['name' => str_replace('custom-', '', $name), 'hex' => $hex];
        }

        // Colours the site's own CSS and blocks use most (custom CSS, inline styles).
        preg_match_all('/(?:color|background(?:-color)?|fill|border(?:-color)?)\s*:\s*(#[0-9a-f]{6}|#[0-9a-f]{3})\b/i', $html, $used);
        $counts = array_count_values(array_map(fn ($h) => $this->hex($h), $used[1]));
        arsort($counts);
        foreach (array_slice(array_keys($counts), 0, 12) as $hex) {
            if (in_array($hex, self::THEME_DEFAULTS, true) && ! isset($found[$hex])) {
                continue;
            }
            $found[$hex] ??= ['name' => 'used on the site', 'hex' => $hex];
        }

        return array_values(array_slice($found, 0, 16));
    }

    /** @return array<string, array{family: string, faces: list<array{url: string, weight: string, style: string}>}> heading / body */
    private function fonts(string $html): array
    {
        preg_match_all('/--wp--preset--font-family--([a-z0-9-]+):\s*([^;]+);/i', $html, $vars, PREG_SET_ORDER);
        $families = [];
        foreach ($vars as [, $slug, $value]) {
            $families[$slug] = trim(explode(',', $value)[0], " \"'");
        }

        $roles = [];
        foreach (['heading' => '/(?:^|[}\s,])h1(?:[\s,{]|$)[^{]*\{[^}]*?font-family:\s*([^;}]+)/i', 'body' => '/(?:^|[}\s])body\s*\{[^}]*?font-family:\s*([^;}]+)/i'] as $role => $pattern) {
            // The last rule that names a font the site actually serves wins (later CSS overrides earlier).
            preg_match_all($pattern, $html, $m);
            foreach (array_reverse($m[1] ?? []) as $value) {
                $value = trim($value);
                $family = preg_match('/var\(--wp--preset--font-family--([a-z0-9-]+)\)/i', $value, $v)
                    ? ($families[$v[1]] ?? null)
                    : trim(explode(',', $value)[0], " \"'");
                if ($family && $this->served($html, $family)) {
                    $roles[$role] = $family;
                    break;
                }
            }
        }

        preg_match_all('/@font-face\s*\{([^}]*)\}/i', $html, $blocks);
        $faces = [];
        foreach ($blocks[1] as $block) {
            if (! preg_match('/font-family:\s*["\']?([^;"\']+)/i', $block, $fam) || ! preg_match('/url\([\'"]?(https?:[^\'")]+\.woff2?)[\'"]?\)/i', $block, $url)) {
                continue;
            }
            $faces[trim($fam[1])][] = [
                'url' => SafeUrl::web($url[1]),
                'weight' => preg_match('/font-weight:\s*([0-9 ]+|bold|normal)/i', $block, $w) ? trim($w[1]) : '400',
                'style' => preg_match('/font-style:\s*(italic|normal)/i', $block, $s) ? strtolower($s[1]) : 'normal',
                // Sites split a font by script (Latin, Devanagari…); each file says which characters it holds.
                'range' => preg_match('/unicode-range:\s*([^;}]+)/i', $block, $r) ? trim($r[1]) : null,
            ];
        }

        $out = [];
        foreach ($roles as $role => $family) {
            if (! empty($faces[$family])) {
                $out[$role] = ['family' => $family, 'faces' => array_values(array_filter($faces[$family], fn ($f) => $f['url']))];
            }
        }

        return $out;
    }

    private function served(string $html, string $family): bool
    {
        return (bool) preg_match('/@font-face\s*\{[^}]*font-family:\s*["\']?'.preg_quote($family, '/').'["\']?\s*;/i', $html);
    }

    /** @return list<array{url: string, kind: string, name: string}> */
    private function art(string $html, array $skipUrls): array
    {
        $skip = array_flip(array_map(fn ($u) => $this->original((string) $u), array_filter($skipUrls)));
        $art = [];

        // Backgrounds in the site's CSS: tiles and patterns, or a hero photo.
        preg_match_all('/url\([\'"]?(https?:\/\/[^\'")\s]+\.(?:png|jpe?g|webp))[\'"]?\)/i', $html, $bg);
        foreach ($bg[1] as $url) {
            $this->addArt($art, $url, $skip, true);
        }

        // Illustrations from the site's media library.
        preg_match_all('/https?:\/\/[a-z0-9.-]*wordcamp\.org\/[^\s"\'()<>]*\/files\/[^\s"\'()<>]+\.(?:png|jpe?g|webp)/i', $html, $media);
        foreach ($media[0] as $url) {
            $this->addArt($art, $url, $skip, false);
        }

        // Shapes that work as a band (a skyline, a sprig, a motif) before big pictures (a wall, a banner).
        $rank = fn (array $a) => preg_match('/(skyline|sprig|motif|jali|tile|pattern)/i', $a['name']) ? 0 : 1;
        $list = array_values($art);
        usort($list, fn ($x, $y) => $rank($x) <=> $rank($y));

        return array_slice($list, 0, 12);
    }

    private function addArt(array &$art, string $url, array $skip, bool $fromCss): void
    {
        $url = $this->original($url);
        $name = basename(parse_url($url, PHP_URL_PATH) ?: '');
        if (! SafeUrl::web($url) || isset($art[$url]) || isset($skip[$url]) || preg_match(self::SKIP_WORDS, $name)) {
            return;
        }
        if (! $fromCss && ! preg_match(self::ART_WORDS, $name)) {
            return;
        }

        $kind = preg_match('/(pattern|motif|tile|jali|texture|mandala|doodle)/i', $name) ? 'pattern'
            : (preg_match('/\.(jpe?g)$/i', $name) && ! preg_match('/(skyline|illustration|sprig|wall)/i', $name) ? 'photo' : 'illustration');

        $art[$url] = ['url' => $url, 'kind' => $kind, 'name' => pathinfo($name, PATHINFO_FILENAME)];
    }

    /** The full-size file, not a "-300x200" thumbnail. */
    private function original(string $url): string
    {
        return (string) preg_replace('/-\d+x\d+(\.(?:png|jpe?g|webp))$/i', '$1', $url);
    }

    private function hex(string $hex): string
    {
        $hex = strtolower($hex);

        return strlen($hex) === 4 ? '#'.$hex[1].$hex[1].$hex[2].$hex[2].$hex[3].$hex[3] : $hex;
    }
}
