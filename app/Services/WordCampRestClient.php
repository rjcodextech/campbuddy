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
     * Fallback for WordCamps that use custom block themes and don't publish
     * standard wcb_speaker REST posts (e.g. Nairobi). Scrapes public /speakers/ page.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchSpeakersFromPage(): array
    {
        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->retry(2, 500, fn (\Throwable $e) => $this->isTransient($e), throw: false)
                ->get("{$this->baseUrl}/speakers/");

            if (! $response->successful()) {
                return [];
            }

            $html = $response->body();
            if (trim($html) === '') {
                return [];
            }

            $crawler = new Crawler($html);
            $speakers = [];
            $seenNames = [];

            $cardSelectors = [
                'div[class*="spk-card"]',
                '.speaker-card',
                '.wp-block-columns .wp-block-column',
            ];

            foreach ($cardSelectors as $selector) {
                $nodes = $crawler->filter($selector);
                if ($nodes->count() > 0) {
                    $nodes->each(function (Crawler $node) use (&$speakers, &$seenNames) {
                        $nameNode = $node->filter('h2, h3, h4, [class*="name"]')->first();
                        if ($nameNode->count() === 0) {
                            return;
                        }
                        $name = trim($nameNode->text(''));
                        if ($name === '' || isset($seenNames[$name])) {
                            return;
                        }
                        $seenNames[$name] = true;

                        $titleNode = $node->filter('p, [class*="title"], [class*="role"]')->first();
                        $title = $titleNode->count() > 0 ? trim($titleNode->text('')) : '';

                        $imgNode = $node->filter('img')->first();
                        $img = $imgNode->count() > 0
                            ? ($imgNode->attr('data-orig-file') ?? $imgNode->attr('src'))
                            : null;

                        $id = abs(crc32($name)) % 1000000 + 800000;
                        $speakers[] = [
                            'id' => $id,
                            'title' => ['rendered' => $name],
                            'content' => ['rendered' => $title !== '' ? "<p>{$title}</p>" : ''],
                            'link' => "{$this->baseUrl}/speakers/",
                            '_embedded' => [
                                'wp:featuredmedia' => $img ? [
                                    [
                                        'source_url' => $img,
                                        'media_details' => [
                                            'sizes' => [
                                                'medium' => ['source_url' => $img],
                                            ],
                                        ],
                                    ],
                                ] : [],
                            ],
                        ];
                    });

                    if (! empty($speakers)) {
                        break;
                    }
                }
            }

            return $speakers;
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function fetchSponsors(): array
    {
        // Most WordCamp sites keep the sponsor's logo as the post's featured
        // image, not in its text: ask for it with the post.
        return $this->fetchCollection('sponsors', ['_embed' => 'wp:featuredmedia']);
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
        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->retry(2, 500, fn (\Throwable $e) => $this->isTransient($e), throw: false)
                ->get("{$this->baseUrl}/sponsors/");

            if (! $response->successful()) {
                return [];
            }

            $html = $response->body();
            if (trim($html) === '') {
                return [];
            }

            $crawler = new Crawler($html);
            $map = [];

            $crawler->filter('img')->each(function (Crawler $node) use (&$map) {
                $src = $node->attr('data-orig-file')
                    ?? $node->attr('data-full-url')
                    ?? $node->attr('data-large-file')
                    ?? $node->attr('data-src')
                    ?? $node->attr('src');

                if (! $src || str_starts_with($src, 'data:')) {
                    return;
                }

                // Ignore site logos and generic icons
                if (preg_match('/(?:cropped-|site-logo|header-logo|favicon|avatar)/i', $src)) {
                    return;
                }

                $parentLink = $node->closest('a');
                $href = $parentLink ? $parentLink->attr('href') : null;
                $alt = trim((string) $node->attr('alt'));

                if ($href && preg_match('#/sponsor/([^/]+)/?#i', $href, $m)) {
                    $slug = strtolower($m[1]);
                    $map[$slug] = $src;
                    $map[preg_replace('/[^a-z0-9]/', '', $slug)] = $src;
                }

                if ($alt !== '') {
                    $clean = strtolower(trim((string) preg_replace('/\s+logo$/i', '', $alt)));
                    if ($clean !== '') {
                        $map[$clean] = $src;
                        $map[preg_replace('/[^a-z0-9]/', '', $clean)] = $src;
                    }
                }
            });

            return $map;
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function fetchOrganizers(): array
    {
        return $this->fetchCollection('organizers', ['_embed' => 'wp:featuredmedia']);
    }

    /**
     * The event's volunteers (post type wcb_volunteer, newer WordCamp sites
     * only — older ones answer 404). Usually published close to the event.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchVolunteers(): array
    {
        return $this->fetchCollection('wcb_volunteer');
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
