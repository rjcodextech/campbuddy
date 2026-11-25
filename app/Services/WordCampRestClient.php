<?php

namespace App\Services;

use App\Support\EventTime;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Thin client over a WordCamp site's own wp-json/wp/v2 REST API.
 * Every site on the shared WordCamp.org codebase exposes the same schema,
 * so this is the single, generic ingestion path for any event — no
 * per-event special-casing and no dependency on wpsimplified.in.
 */
class WordCampRestClient
{
    private const TIMEOUT_SECONDS = 10;

    private const MAX_PAGES = 10;

    /** Domains recognised as a speaker's social links. */
    private const SOCIAL_DOMAINS = [
        'twitter.com' => 'twitter',
        'x.com' => 'twitter',
        'linkedin.com' => 'linkedin',
        'github.com' => 'github',
        'facebook.com' => 'facebook',
        'instagram.com' => 'instagram',
        'mastodon.social' => 'mastodon',
    ];

    private readonly string $baseUrl;

    public function __construct(string $siteUrl)
    {
        $this->baseUrl = rtrim($siteUrl, '/');
    }

    /**
     * The site's time zone, from its REST index (`/wp-json/`): timezone_string,
     * or gmt_offset when the site is set to a plain "UTC+5.5". Null if the
     * index doesn't say.
     */
    public function fetchTimezone(): ?string
    {
        $index = Http::timeout(self::TIMEOUT_SECONDS)
            ->acceptJson()
            ->retry(2, 500, fn (\Throwable $e) => $this->isTransient($e), throw: true)
            ->get("{$this->baseUrl}/wp-json/")
            ->throw()
            ->json();

        return is_array($index) ? EventTime::fromWordPress($index['timezone_string'] ?? null, $index['gmt_offset'] ?? null) : null;
    }

    /** @return array<int, array<string, mixed>> */
    public function fetchSessions(): array
    {
        return $this->fetchCollection('sessions');
    }

    /** @return array<int, array<string, mixed>> */
    public function fetchSpeakers(): array
    {
        // The photo the organizers put on the speaker's page (the featured
        // image) comes with each post: many speakers have no Gravatar.
        return $this->fetchCollection('speakers', ['_embed' => 'wp:featuredmedia']);
    }

    /**
     * Fallback / supplement for WordCamps that use custom block themes and either omit
     * wcb_speaker REST posts (e.g. Nairobi) or add extra speakers on /speakers/.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchSpeakersFromPage(): array
    {
        return $this->extractPeopleFromPaths(['/speakers/'], 'speaker');
    }

    /** @return array<int, array<string, mixed>> */
    public function fetchSponsors(): array
    {
        // Most WordCamp sites keep the sponsor's logo as the post's featured
        // image, not in its text: ask for it with the post.
        return $this->fetchCollection('sponsors', ['_embed' => 'wp:featuredmedia']);
    }

    /**
     * Scrapes the event's public /sponsors/ page (and media/community partner pages)
     * into logos, page-only sponsors, media partners, and microsponsors.
     *
     * @return array{
     *   logo_map: array<string, string>,
     *   sponsors: array<int, array<string, mixed>>,
     *   media_partners: array<int, array<string, mixed>>,
     *   microsponsors: array<int, array<string, mixed>>
     * }
     */
    public function fetchSponsorsPageData(): array
    {
        $logoMap = [];
        $sponsors = [];
        $mediaPartners = [];
        $microsponsors = [];

        $seenSponsors = [];
        $seenPartners = [];

        $html = $this->fetchHtml("{$this->baseUrl}/sponsors/");
        if ($html !== null) {
            $this->parseSponsorsHtml($html, $logoMap, $sponsors, $mediaPartners, $microsponsors, $seenSponsors, $seenPartners);
        }

        // Pull dedicated partner pages if present (/media-partners/, /community-partners/, /partners/).
        $foundDedicatedPartners = false;
        foreach (['/media-partners/', '/community-partners/', '/partners/'] as $path) {
            $pHtml = $this->fetchHtml($this->baseUrl.$path);
            if ($pHtml === null) {
                continue;
            }
            $this->parseMediaPartnersHtml($pHtml, $logoMap, $mediaPartners, $seenPartners);
            $foundDedicatedPartners = true;
            if (count($mediaPartners) > 0) {
                break;
            }
        }

        // If /partners/ returned generic call-for-partners without items, keep whatever /sponsors/ found cleanly.
        if (! $foundDedicatedPartners && $mediaPartners === [] && $html !== null) {
            // Already handled inside parseSponsorsHtml.
        }

        return [
            'logo_map' => $logoMap,
            'sponsors' => array_values($sponsors),
            'media_partners' => array_values($mediaPartners),
            'microsponsors' => array_values($microsponsors),
        ];
    }

    /**
     * Scrapes the event's public sponsors page (/sponsors/) to map sponsor
     * slugs and names to their displayed logo images. Essential fallback when
     * WordPress REST API media endpoints are unauthenticated or un-embedded.
     *
     * @return array<string, string> slug/normalized-name => image url
     */
    public function fetchSponsorLogosFromPage(): array
    {
        return $this->fetchSponsorsPageData()['logo_map'];
    }

    /** @return array<int, array<string, mixed>> */
    public function fetchOrganizers(): array
    {
        return $this->fetchCollection('organizers', ['_embed' => 'wp:featuredmedia']);
    }

    /**
     * Organizers listed directly on /organizers/ or /team/ HTML (excluding volunteer subsections).
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchOrganizersFromPage(): array
    {
        $people = $this->extractPeopleFromPaths(['/organizers/', '/team/'], 'organizer');

        return array_values(array_filter($people, fn ($p) => ($p['_section_role'] ?? 'organizer') !== 'volunteer'));
    }

    /**
     * The event's volunteers (REST wcb_volunteer / volunteers, plus HTML /volunteers/ fallback).
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchVolunteers(): array
    {
        $items = $this->fetchCollection('wcb_volunteer');
        if ($items === []) {
            $items = $this->fetchCollection('volunteers');
        }

        return $items;
    }

    /**
     * Volunteers listed on /volunteers/ or inside a "Volunteers" section on /organizers/.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchVolunteersFromPage(): array
    {
        $fromVolunteersPage = $this->extractPeopleFromPaths(['/volunteers/'], 'volunteer');
        if ($fromVolunteersPage !== []) {
            return $fromVolunteersPage;
        }

        $fromOrgPage = $this->extractPeopleFromPaths(['/organizers/', '/team/'], 'organizer');

        return array_values(array_filter($fromOrgPage, fn ($p) => ($p['_section_role'] ?? '') === 'volunteer'));
    }

    private function fetchHtml(string $url): ?string
    {
        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders(['User-Agent' => 'CampBuddy/1.0 (+https://campbuddy.club)'])
                ->accept('text/html')
                ->retry(2, 500, fn (\Throwable $e) => $this->isTransient($e), throw: false)
                ->get($url);

            if (! $response->successful()) {
                return null;
            }

            $body = $response->body();

            return trim($body) !== '' ? $body : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, string>  $logoMap
     * @param  array<string, array<string, mixed>>  $sponsors
     * @param  array<string, array<string, mixed>>  $mediaPartners
     * @param  array<int, array<string, mixed>>  $microsponsors
     * @param  array<string, bool>  $seenSponsors
     * @param  array<string, bool>  $seenPartners
     */
    private function parseSponsorsHtml(
        string $html,
        array &$logoMap,
        array &$sponsors,
        array &$mediaPartners,
        array &$microsponsors,
        array &$seenSponsors,
        array &$seenPartners,
    ): void {
        try {
            $crawler = new Crawler($html);
            $root = $crawler->filter('.entry-content, main article, main')->first();
            if ($root->count() === 0) {
                return;
            }

            $siteHost = strtolower((string) parse_url($this->baseUrl, PHP_URL_HOST));
            $currentKind = 'sponsor';
            $currentTier = 'Sponsor';

            $walk = function (\DOMNode $node) use (&$walk, &$currentKind, &$currentTier, &$logoMap, &$sponsors, &$mediaPartners, &$microsponsors, &$seenSponsors, &$seenPartners, $siteHost): void {
                if (! $node instanceof \DOMElement) {
                    return;
                }

                $tag = strtolower($node->nodeName);
                $class = strtolower($node->getAttribute('class'));
                if ($tag === 'nav' || $tag === 'footer' || str_contains($class, 'sharedaddy') || str_contains($class, 'sd-sharing') || str_contains($class, 'jp-relatedposts')) {
                    return;
                }

                if (in_array($tag, ['h1', 'h2', 'h3', 'h4'], true) || ($tag === 'p' && ! $node->getElementsByTagName('img')->length && mb_strlen(trim($node->textContent)) <= 50)) {
                    $headingText = trim((string) preg_replace('/\s+/u', ' ', $node->textContent));
                    $classified = $this->classifySponsorHeading($headingText);
                    if ($classified !== null) {
                        [$currentKind, $currentTier] = $classified;
                    }
                    if (in_array($tag, ['h1', 'h2', 'h3', 'h4'], true)) {
                        return;
                    }
                }

                // Detect tier from Gutenberg/WordCamp container classes (e.g. wcdh-sp-bronze, wcorg-sponsor-level-gold)
                if ($class !== '' && preg_match('/(?:wcdh-sp-|wcorg-sponsor-level-)([a-z0-9_-]+)/i', $class, $tm)) {
                    $token = str_replace(['afterparty', '-', '_'], ['After Party', ' ', ' '], strtolower($tm[1]));
                    if (! in_array($token, ['tier', 'logo', 'logos', 'grid', 'list', 'row', 'cards', 'wrap', 'container', 'section', 'items'], true)) {
                        $classified = $this->classifySponsorHeading($token.' Sponsor');
                        if ($classified !== null) {
                            [$currentKind, $currentTier] = $classified;
                        }
                    }
                }

                if ($currentKind === 'ignore') {
                    return;
                }

                // Classic WordCamp or block-theme sponsor post card (.wcorg-sponsor, .wp-block-wordcamp-sponsor)
                if (preg_match('/\b(wcorg-sponsor|wcb_sponsor|wp-block-wordcamp-sponsor)\b/', $class)) {
                    $titleNode = (new Crawler($node))->filter('.wcorg-sponsor-title, h2, h3, h4')->first();
                    $name = $titleNode->count() ? trim($titleNode->text('')) : '';
                    $imgNode = (new Crawler($node))->filter('img')->first();
                    $src = $imgNode->count() ? $this->imageSrc($imgNode) : null;
                    $alt = $imgNode->count() ? trim((string) $imgNode->attr('alt')) : '';
                    $linkNode = (new Crawler($node))->filter('a')->first();
                    $href = $linkNode->count() ? $linkNode->attr('href') : null;
                    $this->recordSponsorItem($name, $alt, $href, $src, $currentKind, $currentTier, $siteHost, $logoMap, $sponsors, $mediaPartners, $microsponsors, $seenSponsors, $seenPartners);

                    return;
                }

                if ($tag === 'img') {
                    $img = new Crawler($node);
                    $src = $this->imageSrc($img);
                    if (! $src || $this->isDecorativeImage($src, (string) $img->attr('alt'))) {
                        return;
                    }
                    $a = $img->closest('a');
                    $href = $a ? $a->attr('href') : null;
                    $card = $img->closest('.wp-block-column, figure, li');
                    $cardHeading = $card ? $card->filter('figcaption, h3, h4, strong')->first() : null;
                    $nameHint = ($cardHeading && $cardHeading->count() > 0) ? trim($cardHeading->text('')) : '';
                    $this->recordSponsorItem($nameHint, (string) $img->attr('alt'), $href, $src, $currentKind, $currentTier, $siteHost, $logoMap, $sponsors, $mediaPartners, $microsponsors, $seenSponsors, $seenPartners);

                    return;
                }

                // Text-only microsponsor or community partner lists inside UL/OL or paragraph blocks
                if ($currentKind === 'microsponsor' && $tag === 'li') {
                    $txt = trim((string) preg_replace('/\s+/u', ' ', $node->textContent));
                    if ($txt !== '' && mb_strlen($txt) <= 80 && ! preg_match('/^share\s+this/i', $txt)) {
                        $microsponsors[] = ['name' => $txt, 'tier_name' => 'Microsponsor'];
                    }

                    return;
                }

                foreach ($node->childNodes as $child) {
                    $walk($child);
                }
            };

            $walk($root->getNode(0));
        } catch (\Throwable) {
            // Keep partial results if any
        }
    }

    /**
     * @param  array<string, string>  $logoMap
     * @param  array<string, array<string, mixed>>  $mediaPartners
     * @param  array<string, bool>  $seenPartners
     */
    private function parseMediaPartnersHtml(string $html, array &$logoMap, array &$mediaPartners, array &$seenPartners): void
    {
        try {
            $crawler = new Crawler($html);
            $root = $crawler->filter('.entry-content, main article, main')->first();
            if ($root->count() === 0) {
                return;
            }
            $siteHost = strtolower((string) parse_url($this->baseUrl, PHP_URL_HOST));

            // Walk candidate cards/columns/figures containing images or headings (e.g. Delhi h3+img, Bengaluru img+alt)
            $cards = $root->filter('.wp-block-column, figure.wp-block-image, .wp-block-group');
            $seenNodes = [];
            $cards->each(function (Crawler $card) use (&$logoMap, &$mediaPartners, &$seenPartners, $siteHost, &$seenNodes) {
                // Skip container wrappers that hold inner columns/figures
                if ($card->filter('.wp-block-column')->count() > 1 || $card->filter('img')->count() > 1) {
                    return;
                }
                $img = $card->filter('img')->first();
                if ($img->count() === 0) {
                    return;
                }
                $src = $this->imageSrc($img);
                $alt = trim((string) $img->attr('alt'));
                if (! $src || $this->isDecorativeImage($src, $alt)) {
                    return;
                }
                if (isset($seenNodes[$src])) {
                    return;
                }
                $seenNodes[$src] = true;

                $heading = $card->filter('h2, h3, h4, figcaption, strong')->first();
                $nameHint = $heading->count() ? trim($heading->text('')) : '';
                if ($this->isUiNoiseHeading($nameHint)) {
                    $nameHint = '';
                }
                $a = $img->closest('a') ?? $card->filter('a')->first();
                $href = ($a && $a->count() > 0) ? $a->attr('href') : null;
                $dummySponsors = [];
                $dummyMicro = [];
                $dummySeenSp = [];
                $this->recordSponsorItem($nameHint, $alt, $href, $src, 'media_partner', 'Media Partner', $siteHost, $logoMap, $dummySponsors, $mediaPartners, $dummyMicro, $dummySeenSp, $seenPartners);
            });

            // Fallback: plain ungrouped <img> gallery on /media-partners/
            $root->filter('img')->each(function (Crawler $img) use (&$logoMap, &$mediaPartners, &$seenPartners, $siteHost, &$seenNodes) {
                $src = $this->imageSrc($img);
                $alt = trim((string) $img->attr('alt'));
                if (! $src || isset($seenNodes[$src]) || $this->isDecorativeImage($src, $alt)) {
                    return;
                }
                $a = $img->closest('a');
                $href = $a ? $a->attr('href') : null;
                $dummySponsors = [];
                $dummyMicro = [];
                $dummySeenSp = [];
                $this->recordSponsorItem('', $alt, $href, $src, 'media_partner', 'Media Partner', $siteHost, $logoMap, $dummySponsors, $mediaPartners, $dummyMicro, $dummySeenSp, $seenPartners);
            });
        } catch (\Throwable) {
            // Safe fallback
        }
    }

    /** @return array{0: string, 1: string}|null [kind, tier] */
    private function classifySponsorHeading(string $heading): ?array
    {
        $h = strtolower(trim($heading));
        if ($h === '') {
            return null;
        }
        if (preg_match('/^(sponsor\s+with|call\s+for\s+sponsor|become\s+a\s+sponsor|why\s+sponsor|reach\s+|hire\s+|get\s+real|back\s+the|share\s+this|subscribe|apply\s+to)/i', $h)) {
            return ['ignore', ''];
        }
        if (preg_match('/media[\s_-]*partner|community[\s_-]*partner|outreach[\s_-]*partner/i', $h)) {
            $label = preg_match('/community/i', $h) ? 'Community Partner' : 'Media Partner';

            return ['media_partner', $label];
        }
        if (preg_match('/micro[\s_-]*sponsor/i', $h)) {
            return ['microsponsor', 'Microsponsor'];
        }
        if (preg_match('/(platinum|gold|silver|bronze|startup|in[\s_-]*kind|after[\s_-]*party|coffee|lunch|lanyard|supporter|venue|hospitality|track|patron|tier|sponsor)/i', $h) && mb_strlen($h) <= 60) {
            $tier = trim((string) preg_replace('/\s+sponsors?$/i', '', trim($heading)));
            if ($tier !== '' && strcasecmp($tier, 'Sponsors') !== 0) {
                return ['sponsor', $tier];
            }
        }

        return null;
    }

    private function recordSponsorItem(
        string $nameHint,
        string $alt,
        ?string $href,
        ?string $src,
        string $kind,
        string $tier,
        string $siteHost,
        array &$logoMap,
        array &$sponsors,
        array &$mediaPartners,
        array &$microsponsors,
        array &$seenSponsors,
        array &$seenPartners,
    ): void {
        $slug = null;
        $internalLink = null;
        $externalWebsite = null;

        if ($href && preg_match('#^https?://#i', $href)) {
            if (preg_match('#/sponsor/([^/]+)/?#i', $href, $m)) {
                $slug = strtolower($m[1]);
                $internalLink = $href;
            } else {
                $host = strtolower((string) parse_url($href, PHP_URL_HOST));
                if ($host !== '' && $host !== $siteHost && ! str_ends_with($host, '.wordcamp.org')) {
                    $externalWebsite = $href;
                }
            }
        }

        // Clean alt text: ignore long accessibility prose ("Logo for WP Poets, featuring...") -> use heading/filename instead.
        $cleanAlt = trim((string) preg_replace(['/^logo\s+for\s+/i', '/\s+logo$/i'], '', $alt));
        if (mb_strlen($cleanAlt) > 55 || str_contains($cleanAlt, ',') || preg_match('/\b(featuring|against|background|icon)\b/i', $cleanAlt)) {
            $cleanAlt = '';
        }

        $cleanName = $nameHint !== '' && mb_strlen($nameHint) <= 55 ? $nameHint : $cleanAlt;
        if ($cleanName === '' && $slug) {
            $cleanName = ucwords(str_replace(['-', '_'], ' ', $slug));
        }
        if ($cleanName === '' && $src) {
            $cleanName = $this->humanizeFilename($src);
        }
        $cleanName = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode($cleanName, ENT_QUOTES | ENT_HTML5)));
        if ($cleanName === '') {
            return;
        }

        if ($src) {
            if ($slug) {
                $logoMap[$slug] = $src;
                $logoMap[preg_replace('/[^a-z0-9]/', '', $slug)] = $src;
            }
            $lower = strtolower($cleanName);
            $logoMap[$lower] = $src;
            $logoMap[preg_replace('/[^a-z0-9]/', '', $lower)] = $src;
        }

        $dedupeKey = preg_replace('/[^a-z0-9]/', '', strtolower($cleanName));
        if ($dedupeKey === '') {
            return;
        }

        $record = [
            'name' => $cleanName,
            'slug' => $slug ?? strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', trim($cleanName, '-'))),
            'tier_name' => $tier,
            'logo_url' => $src,
            'website' => $externalWebsite,
            'link' => $internalLink ?? $externalWebsite,
        ];

        if ($kind === 'media_partner') {
            if (! isset($seenPartners[$dedupeKey])) {
                $seenPartners[$dedupeKey] = true;
                $mediaPartners[$dedupeKey] = $record;
            }

            return;
        }

        if ($kind === 'microsponsor') {
            $microsponsors[] = $record;
        }

        if (! isset($seenSponsors[$dedupeKey])) {
            $seenSponsors[$dedupeKey] = true;
            $sponsors[$dedupeKey] = $record;
        }
    }

    /**
     * Extracts speakers / organizers / volunteers from block-theme and classic WordCamp HTML pages.
     *
     * @param  list<string>  $paths
     * @return array<int, array<string, mixed>>
     */
    private function extractPeopleFromPaths(array $paths, string $defaultRole): array
    {
        foreach ($paths as $path) {
            $html = $this->fetchHtml($this->baseUrl.$path);
            if ($html === null) {
                continue;
            }

            $people = $this->parsePeopleHtml($html, $path, $defaultRole);
            if ($people !== []) {
                return $people;
            }
        }

        return [];
    }

    /** @return array<int, array<string, mixed>> */
    private function parsePeopleHtml(string $html, string $path, string $defaultRole): array
    {
        try {
            $crawler = new Crawler($html);
            $root = $crawler->filter('.entry-content, main article, main')->first();
            if ($root->count() === 0) {
                return [];
            }

            $people = [];
            $seenNames = [];

            // 1. Specific classic / speaker-card selectors first
            $cardSelectors = [
                'div[class*="spk-card"]',
                '.speaker-card',
                '.wcorg-speaker',
                '.wcorg-organizer',
                '.wcorg-volunteer',
                '.wp-block-wordcamp-speakers > li',
                '.wp-block-wordcamp-organizers > li',
            ];

            foreach ($cardSelectors as $selector) {
                $nodes = $root->filter($selector);
                if ($nodes->count() === 0) {
                    continue;
                }
                $nodes->each(function (Crawler $node) use (&$people, &$seenNames, $path, $defaultRole) {
                    $nameNode = $node->filter('h2, h3, h4, [class*="name"]')->first();
                    if ($nameNode->count() === 0) {
                        return;
                    }
                    $name = trim(preg_replace('/\s+/u', ' ', html_entity_decode($nameNode->text(''), ENT_QUOTES | ENT_HTML5)));
                    $key = strtolower($name);
                    if ($name === '' || $this->isUiNoiseHeading($name) || isset($seenNames[$key])) {
                        return;
                    }
                    $seenNames[$key] = true;

                    $bioParts = [];
                    $node->filter('p, [class*="title"], [class*="role"]')->each(function (Crawler $p) use (&$bioParts) {
                        $t = trim(preg_replace('/\s+/u', ' ', $p->text('')));
                        if ($t !== '') {
                            $bioParts[] = $t;
                        }
                    });
                    $bioHtml = $bioParts !== [] ? '<p>'.e(implode("\n", array_unique($bioParts))).'</p>' : '';

                    $imgNode = $node->filter('img')->first();
                    $img = $imgNode->count() ? $this->imageSrc($imgNode) : null;
                    $people[] = $this->syntheticPerson($name, $bioHtml, $img, $this->extractSocialLinks($node->html()), $path, $defaultRole);
                });

                if ($people !== []) {
                    return $people;
                }
            }

            // 2. Modern WordPress block-theme cards (.wp-block-column or leaf .wp-block-group holding 1 portrait + 1 short heading)
            $sectionRole = $defaultRole;
            $crawler->filter('.entry-content h2, .entry-content h3, .entry-content h4, .entry-content .wp-block-column, .entry-content .wp-block-group')->each(
                function (Crawler $node) use (&$people, &$seenNames, &$sectionRole, $path, $defaultRole) {
                    $tag = strtolower($node->getNode(0)->nodeName);
                    if (in_array($tag, ['h2', 'h3', 'h4'], true)) {
                        $t = trim($node->text(''));
                        if (preg_match('/\bvolunteers?\b/i', $t) && mb_strlen($t) <= 40) {
                            $sectionRole = 'volunteer';
                        } elseif (preg_match('/\b(organis|organiz|lead\s+team|core\s+team)\b/i', $t) && mb_strlen($t) <= 40) {
                            $sectionRole = 'organizer';
                        }

                        return;
                    }

                    // Skip large wrapper containers holding multiple sub-cards or headings
                    if ($node->filter('.wp-block-column')->count() > 1 || $node->filter('h2, h3, h4')->count() !== 1) {
                        return;
                    }

                    $heading = $node->filter('h2, h3, h4')->first();
                    $name = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode($heading->text(''), ENT_QUOTES | ENT_HTML5)));
                    if ($name === '' || mb_strlen($name) > 55 || $this->isUiNoiseHeading($name)) {
                        return;
                    }
                    // Likely section banner heading if no image & > 4 words
                    $imgNode = $node->filter('img')->first();
                    $img = $imgNode->count() ? $this->imageSrc($imgNode) : null;
                    if ($img === null && str_word_count($name) > 4) {
                        return;
                    }
                    if ($img && $this->isDecorativeImage($img, (string) $imgNode->attr('alt'))) {
                        $img = null;
                    }

                    $key = strtolower($name);
                    if (isset($seenNames[$key])) {
                        return;
                    }
                    $seenNames[$key] = true;

                    $bioParts = [];
                    $node->filter('p')->each(function (Crawler $p) use (&$bioParts, $name) {
                        $t = trim((string) preg_replace('/\s+/u', ' ', $p->text('')));
                        if ($t !== '' && strcasecmp($t, $name) !== 0 && ! preg_match('/^about\s+/i', $t)) {
                            $bioParts[] = $t;
                        }
                    });
                    $bioHtml = $bioParts !== [] ? '<p>'.e(implode("\n", array_unique($bioParts))).'</p>' : '';

                    $people[] = $this->syntheticPerson($name, $bioHtml, $img, $this->extractSocialLinks($node->html()), $path, $sectionRole ?: $defaultRole);
                }
            );

            return $people;
        } catch (\Throwable) {
            return [];
        }
    }

    private function syntheticPerson(string $name, string $bioHtml, ?string $img, array $socialLinks, string $path, string $sectionRole): array
    {
        return [
            'id' => abs(crc32($name)) % 1000000 + 800000,
            'title' => ['rendered' => $name],
            'content' => ['rendered' => $bioHtml],
            'link' => $this->baseUrl.$path,
            'social_links' => $socialLinks,
            '_section_role' => $sectionRole,
            '_embedded' => [
                'wp:featuredmedia' => $img ? [[
                    'source_url' => $img,
                    'media_details' => ['sizes' => ['medium' => ['source_url' => $img]]],
                ]] : [],
            ],
        ];
    }

    private function imageSrc(Crawler $img): ?string
    {
        $candidate = $img->attr('data-orig-file')
            ?? $img->attr('data-full-url')
            ?? $img->attr('data-large-file')
            ?? $img->attr('data-src')
            ?? $img->attr('data-lazy-src')
            ?? $img->attr('src');

        return ($candidate && ! str_starts_with($candidate, 'data:')) ? $candidate : null;
    }

    private function isDecorativeImage(string $src, string $alt): bool
    {
        if (preg_match('/(?:cropped-|site-logo|header-logo|favicon|tabebuia|sprig|rangoli|banner|divider|spacer|emoji|s\.w\.org\/images)/i', $src)) {
            return ! preg_match('/\blogo\b/i', $alt);
        }

        return false;
    }

    private function isUiNoiseHeading(string $text): bool
    {
        $h = strtolower(trim($text));
        if ($h === '' || str_starts_with($h, '//')) {
            return true;
        }

        return preg_match('/^(share\s+this|subscribe|join\s+us|get\s+your\s+ticket|meet\s+the|who\s+is\s+talking|the\s+crew|the\s+humans|the\s+lead\s+team|organis|organiz|our\s+volunteers?|say\s+hello|outlets\s+covering|the\s+people\s+telling|the\s+call\s+is|where\s+it\s+turns|two\s+different|who\s+is\s+carrying)/i', $h) === 1;
    }

    private function humanizeFilename(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $base = pathinfo($path, PATHINFO_FILENAME);
        $base = (string) preg_replace('/(-\d+x\d+|-scaled|-edited|-e\d+|-logo|_logo|-light|-dark)$/i', '', $base);
        $base = trim((string) preg_replace('/[-_]+/', ' ', $base));
        if ($base === '' || preg_match('/^(image|img|photo|\d+)$/i', $base)) {
            return '';
        }

        return mb_convert_case($base, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Resolves a taxonomy's numeric term IDs to names — session
     * tracks and sponsor tiers are meaningless without this join.
     *
     * @return array<int, string> term ID => name
     */
    public function fetchTaxonomyNames(string $taxonomyEndpoint): array
    {
        $terms = $this->fetchCollection($taxonomyEndpoint);

        return collect($terms)->pluck('name', 'id')->all();
    }

    /**
     * Scans a speaker/organizer bio's rendered HTML for links to known
     * social platforms — the one thing not in structured meta.
     *
     * @return array<int, array{type: string, url: string}>
     */
    public function extractSocialLinks(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $crawler = new Crawler($html);
        $links = [];

        $crawler->filter('a')->each(function (Crawler $node) use (&$links) {
            $href = $node->attr('href');

            if (! $href) {
                return;
            }

            $host = strtolower((string) parse_url($href, PHP_URL_HOST));
            $host = preg_replace('/^www\./', '', $host);

            foreach (self::SOCIAL_DOMAINS as $domain => $type) {
                if ($host === $domain || str_ends_with($host, ".{$domain}")) {
                    $links[$href] = ['type' => $type, 'url' => $href];

                    return;
                }
            }
        });

        return array_values($links);
    }

    /**
     * Pulls the first rendered <img> src from a post's content HTML —
     * used as a sponsor's logo since it's embedded in the render, not a
     * structured field (mirrors the branding-asset approach).
     */
    public function extractFirstImage(string $html): ?string
    {
        if (trim($html) === '') {
            return null;
        }

        $crawler = new Crawler($html);
        $images = $crawler->filter('img');

        if ($images->count() === 0) {
            return null;
        }

        foreach ($images as $img) {
            $crawlerImg = new Crawler($img);
            $candidate = $crawlerImg->attr('data-orig-file')
                ?? $crawlerImg->attr('data-full-url')
                ?? $crawlerImg->attr('data-large-file')
                ?? $crawlerImg->attr('data-src')
                ?? $crawlerImg->attr('data-lazy-src')
                ?? $crawlerImg->attr('src');

            if ($candidate && ! str_starts_with($candidate, 'data:')) {
                return $candidate;
            }
        }

        return $images->first()->attr('src');
    }

    /**
     * Every page of a wp/v2 collection. Transient trouble (a timeout, a 5xx
     * from a busy host, a 429) is retried twice with a short pause before it
     * counts as a failure; a 4xx is a real answer and isn't. A 200 whose body
     * isn't a JSON list — a maintenance page, a security plugin's challenge,
     * a site that has turned the REST API off — is a failure too, never
     * silently read as "this event has no sessions".
     *
     * @param  array<string, string>  $query  extra query arguments (e.g. _embed)
     * @return array<int, array<string, mixed>>
     */
    private function fetchCollection(string $endpoint, array $query = []): array
    {
        $items = [];
        $page = 1;
        $totalPages = 1;

        do {
            try {
                $response = Http::timeout(self::TIMEOUT_SECONDS)
                    ->acceptJson()
                    ->retry(3, 500, fn (\Throwable $e) => $this->isTransient($e), throw: true)
                    ->get("{$this->baseUrl}/wp-json/wp/v2/{$endpoint}", [
                        'per_page' => 100,
                        'page' => $page,
                        ...$query,
                    ])
                    ->throw();
            } catch (RequestException $e) {
                // If the endpoint is not registered on the WordPress site yet (404), return empty list on page 1.
                if ($e->response->status() === 404 && $page === 1) {
                    return [];
                }

                throw $e;
            }

            $batch = $response->json();

            if (! is_array($batch) || ! array_is_list($batch)) {
                throw new UnexpectedResponseException(
                    "{$endpoint}: the site answered, but not with a list of items (is its REST API switched off or behind a login?)."
                );
            }

            // Only well-formed posts: an item without a numeric id can't be
            // bookmarked, linked to a speaker or reminded about.
            foreach ($batch as $item) {
                if (is_array($item) && isset($item['id']) && is_int($item['id'])) {
                    $items[] = $item;
                }
            }

            $totalPages = max(1, (int) $response->header('X-WP-TotalPages'));
            $page++;
        } while ($page <= $totalPages && $page <= self::MAX_PAGES);

        return $items;
    }

    private function isTransient(\Throwable $e): bool
    {
        if ($e instanceof ConnectionException) {
            return true;
        }

        if ($e instanceof RequestException) {
            $status = $e->response->status();

            return $status === 429 || $status >= 500;
        }

        return false;
    }
}
