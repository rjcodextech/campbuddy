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
     * Every page of a wp/v2 collection. Transient trouble (a timeout, a 5xx
     * from a busy host, a 429) is retried twice with a short pause before it
     * counts as a failure; a 4xx is a real answer and isn't. A 200 whose body
     * isn't a JSON list — a maintenance page, a security plugin's challenge,
     * a site that has turned the REST API off — is a failure too, never
     * silently read as "this event has no sessions".
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchCollection(string $endpoint): array
    {
        $items = [];
        $page = 1;
        $totalPages = 1;

        do {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->acceptJson()
                ->retry(3, 500, fn (\Throwable $e) => $this->isTransient($e), throw: true)
                ->get("{$this->baseUrl}/wp-json/wp/v2/{$endpoint}", [
                    'per_page' => 100,
                    'page' => $page,
                ])
                ->throw();

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
