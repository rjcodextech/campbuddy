<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;
use Throwable;

/**
 * Reads the pages of one WordCamp site — its Tickets, Contact, Code of
 * Conduct… pages — so their content can be mined for event information.
 *
 * Two ways in, tried in order:
 *   1. wp-json/wp/v2/pages   — structured and cheap, on every WordCamp site.
 *   2. Scraping the homepage's own links, then each page's HTML — for a
 *      site whose REST API is disabled or blocked.
 *
 * Nothing here is fatal: an unreachable site just yields no pages.
 */
class WordCampSitePages
{
    private const TIMEOUT_SECONDS = 10;

    private const MAX_INDEX_PAGES = 3;

    private const MAX_SCRAPED_LINKS = 80;

    /** 'rest', 'scrape' or 'none' — how the page index was obtained. */
    public string $mode = 'none';

    private readonly string $baseUrl;

    private readonly string $host;

    private readonly string $basePath;

    public function __construct(string $siteUrl)
    {
        $this->baseUrl = rtrim($siteUrl, '/');
        $this->host = strtolower((string) parse_url($this->baseUrl, PHP_URL_HOST));
        $this->basePath = rtrim((string) parse_url($this->baseUrl, PHP_URL_PATH), '/');
    }

    /**
     * Every page the site lists.
     *
     * @return list<array{id: int|null, slug: string, url: string, title: string}>
     */
    public function index(): array
    {
        $pages = $this->indexFromRest();

        if ($pages !== []) {
            $this->mode = 'rest';

            return $pages;
        }

        $pages = $this->indexFromHomepage();
        $this->mode = $pages === [] ? 'none' : 'scrape';

        return $pages;
    }

    /**
     * The rendered HTML of the given pages, keyed by page URL. A page whose
     * content can't be read is simply absent from the result.
     *
     * @param  list<array{id: int|null, slug: string, url: string, title: string}>  $pages
     * @return array<string, string>
     */
    public function contents(array $pages): array
    {
        $contents = $this->mode === 'rest' ? $this->contentsFromRest($pages) : [];

        foreach ($pages as $page) {
            if (! isset($contents[$page['url']])) {
                $html = $this->scrapePage($page['url']);

                if ($html !== null) {
                    $contents[$page['url']] = $html;
                }
            }
        }

        return $contents;
    }

    /** @return list<array{id: int|null, slug: string, url: string, title: string}> */
    private function indexFromRest(): array
    {
        $pages = [];
        $page = 1;
        $totalPages = 1;

        try {
            do {
                $response = Http::timeout(self::TIMEOUT_SECONDS)->acceptJson()
                    ->get("{$this->baseUrl}/wp-json/wp/v2/pages", [
                        'per_page' => 100,
                        'page' => $page,
                        '_fields' => 'id,slug,link,title',
                    ])
                    ->throw();

                foreach ($response->json() ?? [] as $item) {
                    if (! is_array($item) || empty($item['link'])) {
                        continue;
                    }

                    $pages[] = [
                        'id' => isset($item['id']) ? (int) $item['id'] : null,
                        'slug' => (string) ($item['slug'] ?? ''),
                        'url' => (string) $item['link'],
                        'title' => $this->plain((string) ($item['title']['rendered'] ?? '')),
                    ];
                }

                $totalPages = (int) $response->header('X-WP-TotalPages', 1);
                $page++;
            } while ($page <= $totalPages && $page <= self::MAX_INDEX_PAGES);
        } catch (Throwable) {
            return [];
        }

        return $pages;
    }

    /** @return list<array{id: int|null, slug: string, url: string, title: string}> */
    private function indexFromHomepage(): array
    {
        $html = $this->fetchHtml("{$this->baseUrl}/");

        if ($html === null) {
            return [];
        }

        $pages = [];

        foreach ($this->crawler($html)->filter('a[href]') as $anchor) {
            $url = $this->internalUrl((string) $anchor->getAttribute('href'));

            if ($url === null || isset($pages[$url])) {
                continue;
            }

            $slug = basename(rtrim((string) parse_url($url, PHP_URL_PATH), '/'));

            if ($slug === '' || str_contains($slug, '.')) {
                continue;
            }

            $pages[$url] = [
                'id' => null,
                'slug' => $slug,
                'url' => $url,
                'title' => $this->plain($anchor->textContent),
            ];

            if (count($pages) >= self::MAX_SCRAPED_LINKS) {
                break;
            }
        }

        return array_values($pages);
    }

    /**
     * One request for all wanted pages, via the REST API's `include` filter.
     *
     * @param  list<array{id: int|null, slug: string, url: string, title: string}>  $pages
     * @return array<string, string>
     */
    private function contentsFromRest(array $pages): array
    {
        $ids = array_values(array_filter(array_column($pages, 'id')));

        if ($ids === []) {
            return [];
        }

        try {
            $items = Http::timeout(self::TIMEOUT_SECONDS)->acceptJson()
                ->get("{$this->baseUrl}/wp-json/wp/v2/pages", [
                    'include' => implode(',', $ids),
                    'per_page' => count($ids),
                    '_fields' => 'id,link,content',
                ])
                ->throw()
                ->json();
        } catch (Throwable) {
            return [];
        }

        $contents = [];

        foreach (is_array($items) ? $items : [] as $item) {
            if (is_array($item) && isset($item['link'], $item['content']['rendered'])) {
                $contents[(string) $item['link']] = (string) $item['content']['rendered'];
            }
        }

        return $contents;
    }

    /** The page's main content area, without the theme's header/nav/footer. */
    private function scrapePage(string $url): ?string
    {
        $html = $this->fetchHtml($url);

        if ($html === null) {
            return null;
        }

        $crawler = $this->crawler($html);

        foreach (['.entry-content', '.wp-block-post-content', 'main', 'article', 'body'] as $selector) {
            $node = $crawler->filter($selector);

            if ($node->count() > 0 && trim($node->text('')) !== '') {
                return $node->first()->html('');
            }
        }

        return null;
    }

    private function fetchHtml(string $url): ?string
    {
        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)->accept('text/html')->get($url);

            return $response->successful() ? $response->body() : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** An in-site absolute URL for a link, or null if it leaves the event's site. */
    private function internalUrl(string $href): ?string
    {
        $href = trim($href);

        if ($href === '' || str_starts_with($href, '#') || preg_match('#^(mailto|tel|javascript):#i', $href)) {
            return null;
        }

        if (str_starts_with($href, '//')) {
            $href = 'https:'.$href;
        } elseif (str_starts_with($href, '/')) {
            $href = 'https://'.$this->host.$href;
        } elseif (! preg_match('#^https?://#i', $href)) {
            $href = $this->baseUrl.'/'.ltrim($href, './');
        }

        $parts = parse_url($href);

        if (strtolower((string) ($parts['host'] ?? '')) !== $this->host) {
            return null;
        }

        $path = (string) ($parts['path'] ?? '/');

        if ($this->basePath !== '' && ! str_starts_with($path, $this->basePath.'/')) {
            return null;
        }

        return 'https://'.$this->host.rtrim($path, '/').'/';
    }

    private function crawler(string $html): Crawler
    {
        $crawler = new Crawler;
        $crawler->addHtmlContent($html, 'UTF-8');

        return $crawler;
    }

    private function plain(string $html): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }
}
