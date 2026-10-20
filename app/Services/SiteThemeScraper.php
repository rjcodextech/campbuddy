<?php

namespace App\Services;

use App\Support\SafeUrl;

/**
 * Reads a WordCamp site's own look from its home page (and the artwork in
 * its media library, when given), for the Social media
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

    // The region's own crafts and forms count as artwork too: a rangoli, a Madhubani band, a mosque frieze.
    private const CRAFT_WORDS = 'rangoli|kolam|alpana|madhubani|warli|paisley|ajrak|ikat|block-?print|kalamkari|phulkari|arabesque|frieze|ornament';

    private const ART_WORDS = '/(pattern|motif|tile|jali|texture|skyline|illustration|sprig|wall|banner|hero|backdrop|bg|background|mandala|border|doodle|wapuu|sketch|artwork|graphic|monument|'.self::CRAFT_WORDS.')/i';

    // Words that name a real piece of artwork, whatever its shape or file type.
    private const SHAPE_WORDS = '/(pattern|motif|tile|jali|texture|mandala|skyline|illustration|sprig|doodle|sketch|'.self::CRAFT_WORDS.')/i';

    // Repeating crafts are patterns; drawn ones (a Warli scene, a frieze) illustrations.
    private const PATTERN_WORDS = '/(pattern|motif|tile|jali|texture|mandala|rangoli|kolam|alpana|paisley|ajrak|ikat|block-?print|kalamkari|phulkari|arabesque|ornament)/i';

    // Photos of the place itself: the venue, the city.
    private const PLACE_WORDS = '/(?<![a-z])(venue|hall|hotel|campus|auditorium|exterior|building|city|aerial|landmark|centre|center)/i';

    // Graphics with words or prices on them (ticket tiers, a venue map, a flyer) aren't artwork either,
    // nor frames, templates and social graphics ("post-frame", "social-frames-featured", made for
    // people's photos or carrying words), hotel rooms, or pictures of the team.
    private const SKIP_WORDS = '/(?<![a-z])(logo|icon|avatar|gravatar|favicon|sponsor|speaker|profile|headshot|flag|qr|whatsapp|jetpack|badge|ticket|price|pricing|map|flyer|poster|screenshot|schedule|cta|frame|template|social|og-image|share|save-the-date|announce|room|organi[sz]er|volunteer|selfie|layout|floor-?plan|post|posts|update|fb|facebook|insta|instagram|twitter|linkedin|featured|story|reel|call[-_ ]for|website[-_ ]?banner|page[-_]?\d{2,}|with (the )?text|text reads)/i';

    /**
     * @param  list<string|array>  $media  the site's media library (see art())
     * @param  array<string, array{width: int, height: int, alpha: bool}>  $probed  pictures CampBuddy opened to measure (see SiteTheme)
     * @param  ?string  $site  the site's address, so artwork from an older edition ("…/2025/files/…") ranks lower
     * @return array{colors: list<array{name: string, hex: string}>, fonts: array<string, array{family: string, faces: list<array{url: string, weight: string, style: string, range: ?string}>}>, art: list<array{url: string, kind: string, name: string}>}
     */
    public function parse(string $html, array $skipUrls = [], array $media = [], array $probed = [], ?string $site = null): array
    {
        return [
            'colors' => $this->colors($html),
            'fonts' => $this->fonts($html),
            'art' => $this->art($html, $skipUrls, $media, $probed, $site),
        ];
    }

    /**
     * The site's own stylesheets worth reading too: WordCamp's Remote CSS and
     * the Customizer's / Jetpack's custom CSS, where many sites keep their
     * colours, fonts and background art. At most two, on the site's own host.
     *
     * @return list<string>
     */
    public function stylesheets(string $html, string $site): array
    {
        $host = parse_url($site, PHP_URL_HOST);
        preg_match_all('/<link\b[^>]*rel=["\']stylesheet["\'][^>]*>/i', $html, $links);
        $out = [];
        foreach ($links[0] as $tag) {
            if (! preg_match('/\shref=["\']([^"\']+)["\']/i', $tag, $m)) {
                continue;
            }
            $url = SafeUrl::web(html_entity_decode($m[1]));
            if ($url && parse_url($url, PHP_URL_HOST) === $host && preg_match('/wordcamp_remote_css|custom-css|custom_css/i', $url)) {
                $out[] = $url;
            }
        }

        return array_slice(array_values(array_unique($out)), 0, 2);
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

    /**
     * The site's artwork, best first: what its home page shows as a backdrop
     * (CSS backgrounds, Cover blocks), and pictures there or in its media
     * library whose file name, title or alt text says artwork (a skyline, a
     * pattern) or the place (the venue, the city). Too small to print, a
     * logo, a person, a sponsor, a frame or a graphic with words: left out.
     * Copies of one piece (white / black, -scaled, -1, cropped-) count once,
     * the biggest kept. The kind comes from the words, then the shape: a
     * wide piece is an illustration (a band), a JPEG a photo.
     *
     * @param  list<string|array{url: string, width?: ?int, height?: ?int, title?: string, alt?: string}>  $media  the site's media library
     * @return list<array{url: string, kind: string, name: string, width?: int, height?: int}>
     */
    private function art(string $html, array $skipUrls, array $media = [], array $probed = [], ?string $site = null): array
    {
        $skip = array_flip(array_map(fn ($u) => $this->original((string) $u), array_filter($skipUrls)));
        $found = [];
        $add = function (string $url, string $source, ?int $w = null, ?int $h = null, string $words = '') use (&$found, $skip) {
            $url = $this->original($url);
            if (! SafeUrl::web($url) || isset($skip[$url]) || ! preg_match('/\.(png|jpe?g|webp)$/i', $url)) {
                return;
            }
            $found[$url] = [
                'url' => $url,
                'sources' => array_values(array_unique([...($found[$url]['sources'] ?? []), $source])),
                'width' => $w ?? ($found[$url]['width'] ?? null),
                'height' => $h ?? ($found[$url]['height'] ?? null),
                'words' => trim(($found[$url]['words'] ?? '').' '.$words),
            ];
        };

        // What the home page shows as a backdrop: CSS backgrounds and Cover blocks.
        preg_match_all('/url\(\s*[\'"]?(https?:\/\/[^\'")\s]+\.(?:png|jpe?g|webp))(?:\?[^\'")\s]*)?[\'"]?\s*\)/i', $html, $bg);
        foreach ($bg[1] as $url) {
            $add($url, 'backdrop');
        }
        preg_match_all('/<img\b[^>]*>/i', $html, $imgs);
        foreach ($imgs[0] as $tag) {
            $attr = fn (string $name) => preg_match('/\s'.$name.'=["\']([^"\']*)["\']/i', $tag, $m) ? html_entity_decode($m[1]) : '';
            if ($attr('src') === '') {
                continue;
            }
            $cover = (bool) preg_match('/wp-block-cover__image|header-image|hero/i', $attr('class'));
            [$w, $h] = $this->tagSize($attr('src'), $attr('width'), $attr('height'), $attr('srcset'));
            $add($attr('src'), $cover ? 'backdrop' : 'page', $w, $h, $attr('alt'));
        }
        // Any other upload of this site the page mentions (a srcset, a link).
        preg_match_all('/https?:\/\/[a-z0-9.-]*wordcamp\.org\/[^\s"\'()<>]*\/files\/[^\s"\'()<>]+\.(?:png|jpe?g|webp)/i', $html, $files);
        foreach ($files[0] as $url) {
            $add($url, 'page');
        }

        // The media library: every page's uploads, with their size, title and alt text.
        foreach ($media as $item) {
            $item = is_array($item) ? $item : ['url' => $item];
            if (is_string($item['url'] ?? null)) {
                $add($item['url'], 'library', $item['width'] ?? null, $item['height'] ?? null, trim(($item['title'] ?? '').' '.($item['alt'] ?? '')));
            }
        }

        // What opening the file told: its real size, and whether it has see-through parts.
        foreach ($probed as $url => $info) {
            if (isset($found[$url])) {
                $found[$url] = ['width' => $info['width'], 'height' => $info['height'], 'alpha' => $info['alpha']] + $found[$url];
            }
        }
        $edition = preg_match('#/(\d{4})/?$#', (string) $site, $y) ? $y[1] : null;

        // One of each piece: its copies count once, the best kept.
        $best = [];
        foreach ($found as $piece) {
            $piece = $this->judge($piece, $edition);
            if ($piece === null) {
                continue;
            }
            $family = $this->family($piece['name']);
            if (! isset($best[$family]) || $this->better($piece, $best[$family])) {
                $best[$family] = $piece;
            }
        }

        $list = array_values($best);
        usort($list, fn ($x, $y) => $y['score'] <=> $x['score']);

        return array_map(fn ($p) => array_filter(
            ['url' => $p['url'], 'kind' => $p['kind'], 'name' => $p['name'], 'width' => $p['width'], 'height' => $p['height']],
            fn ($v) => $v !== null,
        ), array_slice($list, 0, 12));
    }

    /**
     * Whether a picture is artwork, what kind, and how good a pick it is.
     *
     * @return ?array{url: string, kind: string, name: string, width: ?int, height: ?int, score: int}
     */
    private function judge(array $piece, ?string $edition = null): ?array
    {
        $file = basename(parse_url($piece['url'], PHP_URL_PATH) ?: '');
        $name = pathinfo($file, PATHINFO_FILENAME);
        $words = $name.' '.$piece['words'];
        [$w, $h] = [$piece['width'], $piece['height']];
        $backdrop = in_array('backdrop', $piece['sources'], true);
        // Null when nobody opened the file; true for a cut-out (a skyline on nothing), false for a solid picture.
        $alpha = $piece['alpha'] ?? null;

        $aspect = $w && $h ? $w / $h : null;
        $jpeg = (bool) preg_match('/\.jpe?g$/i', $file);
        $tile = (bool) preg_match(self::PATTERN_WORDS, $words);
        $shape = (bool) preg_match(self::SHAPE_WORDS, $words);

        if (preg_match(self::SKIP_WORDS, $words)) {
            return null;
        }
        // A photo-sharing upload ("55314129638_55a52de455_…") or a hash name: a photo of people, not artwork.
        if (preg_match('/^(\d{8,}_|[0-9a-f]{24,})/i', $name)) {
            return null;
        }
        // Too small to print well (a 20 px sprite, a thumbnail); a tile repeats, so it may be smaller.
        if ($w && $h && (max($w, $h) < ($tile ? 48 : 400) || min($w, $h) < ($tile ? 24 : 80))) {
            return null;
        }
        // The shape of a link preview (1200 × 630, 1910 × 1000): a social graphic with words on it.
        if ($aspect !== null && $aspect > 1.86 && $aspect < 1.95 && ! $shape) {
            return null;
        }

        $art = (bool) preg_match(self::ART_WORDS, $words);
        $place = (bool) preg_match(self::PLACE_WORDS, $words);
        if (! $art && ! $place && ! $backdrop) {
            return null;
        }
        // A JPEG named only for a mascot ("wapuu-2026.jpg") is a photo of the plush toy among people.
        if ($jpeg && ! $place && ! $backdrop && ! $shape && ! preg_match('/(hero|banner|backdrop|bg|background|wall)/i', $words)) {
            return null;
        }
        $kind = match (true) {
            $tile => 'pattern',
            (bool) preg_match('/(skyline|illustration|sprig|wall|wapuu|doodle|sketch|madhubani|warli|frieze|monument)/i', $words) => 'illustration',
            $jpeg => 'photo',
            // Something cut out on nothing is drawn art, never a photo.
            $alpha === true && ! $place => 'illustration',
            $aspect !== null && $aspect >= 2.0 => 'illustration',
            // A Cover block's backdrop with no art words is nearly always a photo.
            $backdrop && ! $art => 'photo',
            $place => 'photo',
            default => 'illustration',
        };

        $score = match (true) {
            (bool) preg_match('/(skyline|pattern|motif|jali|tile|sprig|wapuu|rangoli|kolam|madhubani|warli|frieze)/i', $words) => 6,
            $art => 4,
            default => 2,
        };
        $score += $backdrop ? 3 : 0;
        $score += $place && $kind === 'photo' ? 1 : 0;
        $score += $w && $h && max($w, $h) >= 1200 ? 1 : 0;
        // A cut-out reads best on the cards; a solid PNG with no art words is often a graphic with words on it.
        $score += $alpha === true && ! $jpeg ? 2 : 0;
        $score -= $alpha === false && ! $jpeg && ! $shape ? 1 : 0;
        // A Figma export ("Frame-1000015039") or a camera file name says little: below named artwork.
        $score -= preg_match('/^(frame|image|img|dsc|unnamed|untitled|screen|photo|pexels|unsplash)[-_ ]?\d*/i', $name) ? 2 : 0;
        // A background-remover's small preview ("…-removebg-preview"), or last year's edition's file: kept, but after the rest.
        $score -= preg_match('/removebg|[-_]preview$/i', $name) ? 2 : 0;
        $score -= $edition && preg_match('#/(\d{4})/files/#', $piece['url'], $y) && $y[1] !== $edition ? 2 : 0;

        return ['url' => $piece['url'], 'kind' => $kind, 'name' => $name, 'width' => $w, 'height' => $h, 'score' => $score];
    }

    /**
     * The full file's size from an <img> tag: its srcset's widest copy (the
     * full file), else its width / height when the src is the full file
     * itself (WordPress writes the size of the file it shows). Nulls when
     * the tag doesn't say.
     *
     * @return array{0: ?int, 1: ?int}
     */
    private function tagSize(string $src, string $width, string $height, string $srcset): array
    {
        $w = ctype_digit($width) ? (int) $width : null;
        $h = ctype_digit($height) ? (int) $height : null;
        $widest = 0;
        foreach (explode(',', $srcset) as $entry) {
            if (preg_match('/\s(\d+)w\s*$/', trim($entry), $m)) {
                $widest = max($widest, (int) $m[1]);
            }
        }
        if ($widest > 0) {
            return [$widest, $w && $h ? (int) round($widest * $h / $w) : null];
        }
        // A "-300x200" copy shows less than the file has: no size then, rather than a wrong one.
        if (preg_match('/-\d+x\d+\.(?:png|jpe?g|webp)(?:\?.*)?$/i', $src)) {
            return [null, null];
        }

        return $w && $h ? [$w, $h] : [null, null];
    }

    /** "wcjhb-skyline-white-1", "cropped-wcjhb-skyline-black-scaled" → "wcjhb-skyline". */
    private function family(string $name): string
    {
        $name = (string) preg_replace('/^cropped-/', '', str_replace('_', '-', strtolower($name)));
        do {
            $before = $name;
            $name = (string) preg_replace('/[-_](scaled|edited|e\d{10,}|\d{1,2}|v\d+|@?\dx|white|black|light|dark|ink|mono|colou?r|colou?red|warm|cool|trans|transparent|copy|final|new|removebg|preview)$/', '', $name);
        } while ($name !== $before && $name !== '');

        return $name;
    }

    /** Of two copies, the better pick, then the bigger one (a PNG / WebP over a JPEG when sizes are unknown). */
    private function better(array $a, array $b): bool
    {
        $area = fn ($p) => ($p['width'] ?? 0) * ($p['height'] ?? 0);
        if ($a['score'] !== $b['score']) {
            return $a['score'] > $b['score'];
        }
        if ($area($a) !== $area($b)) {
            return $area($a) > $area($b);
        }

        return ! preg_match('/\.jpe?g$/i', $a['url']) && (bool) preg_match('/\.jpe?g$/i', $b['url']);
    }

    /** The full-size file, not a "-300x200" thumbnail. */
    private function original(string $url): string
    {
        $url = html_entity_decode(trim($url));
        // Jetpack's image CDN ("https://i0.wp.com/delhi.wordcamp.org/…?resize=…"): the site's own file.
        $url = (string) preg_replace('#^https?://i\d\.wp\.com/#i', 'https://', $url);
        $url = (string) preg_replace('/\?.*$/', '', $url);

        return (string) preg_replace('/-\d+x\d+(\.(?:png|jpe?g|webp))$/i', '$1', $url);
    }

    private function hex(string $hex): string
    {
        $hex = strtolower($hex);

        return strlen($hex) === 4 ? '#'.$hex[1].$hex[1].$hex[2].$hex[2].$hex[3].$hex[3] : $hex;
    }
}
