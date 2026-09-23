<?php

namespace App\Services;

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

    /** @return array<int, array<string, mixed>> */
    public function fetchSessions(): array
    {
        return $this->fetchCollection('sessions');
    }

    /** @return array<int, array<string, mixed>> */
    public function fetchSpeakers(): array
    {
        return $this->fetchCollection('speakers');
    }

    /** @return array<int, array<string, mixed>> */
    public function fetchSponsors(): array
    {
        return $this->fetchCollection('sponsors');
    }

    /** @return array<int, array<string, mixed>> */
    public function fetchOrganizers(): array
    {
        return $this->fetchCollection('organizers');
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

        return $images->count() > 0 ? $images->first()->attr('src') : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchCollection(string $endpoint): array
    {
        $items = [];
        $page = 1;
        $totalPages = 1;

        do {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->get("{$this->baseUrl}/wp-json/wp/v2/{$endpoint}", [
                    'per_page' => 100,
                    'page' => $page,
                ])
                ->throw();

            $items = array_merge($items, $response->json() ?? []);
            $totalPages = (int) $response->header('X-WP-TotalPages', 1);
            $page++;
        } while ($page <= $totalPages && $page <= self::MAX_PAGES);

        return $items;
    }
}
