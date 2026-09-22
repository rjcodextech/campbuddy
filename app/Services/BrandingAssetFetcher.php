<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Best-effort branding asset discovery (§0.6, §3.2 BR4) — a one-time job
 * per event, not a daily one, since none of this has a stable contract.
 * Whatever it finds is downloaded and re-hosted by the caller; this class
 * only resolves source URLs, it never fetches the bytes itself.
 */
class BrandingAssetFetcher
{
    private const TIMEOUT_SECONDS = 10;

    private readonly string $baseUrl;

    private readonly string $rootDomain;

    public function __construct(string $siteUrl)
    {
        $this->baseUrl = rtrim($siteUrl, '/');
        $this->rootDomain = (string) parse_url($this->baseUrl, PHP_URL_SCHEME).'://'.parse_url($this->baseUrl, PHP_URL_HOST);
    }

    /**
     * The event's visual logo — only ever found in the homepage header
     * markup (§0.6); WordPress's "Site Icon" setting is a favicon, not a
     * logo, so there's no REST shortcut for this one.
     */
    public function findLogoUrl(): ?string
    {
        $html = $this->fetchHomepageHtml();

        if ($html === null) {
            return null;
        }

        $crawler = new Crawler($html);

        // WordPress core's standard class for a theme's custom logo.
        $logo = $crawler->filter('img.custom-logo');

        if ($logo->count() > 0) {
            return $this->resolveUrl($logo->first()->attr('src'));
        }

        // Fallback: any image inside the page header whose class/alt
        // mentions "logo" — organizer themes vary (§0.6).
        $headerImg = $crawler->filter('header img, .site-logo img, .site-branding img')->reduce(
            fn (Crawler $node) => str_contains(strtolower($node->attr('class') ?? ''), 'logo')
                || str_contains(strtolower($node->attr('alt') ?? ''), 'logo')
        );

        return $headerImg->count() > 0 ? $this->resolveUrl($headerImg->first()->attr('src')) : null;
    }

    /**
     * The favicon, tried in the priority order §3.2 BR4 specifies.
     */
    public function findFaviconUrl(): ?string
    {
        return $this->faviconFromRestRoot()
            ?? $this->faviconFromHomepageHead()
            ?? $this->faviconFromDomainRoot();
    }

    private function faviconFromRestRoot(): ?string
    {
        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)->get("{$this->baseUrl}/wp-json/");
            $iconUrl = $response->json('site_icon_url');

            return is_string($iconUrl) && $iconUrl !== '' ? $iconUrl : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function faviconFromHomepageHead(): ?string
    {
        $html = $this->fetchHomepageHtml();

        if ($html === null) {
            return null;
        }

        $crawler = new Crawler($html);
        $icon = $crawler->filter('link[rel="icon"], link[rel="shortcut icon"]');

        return $icon->count() > 0 ? $this->resolveUrl($icon->first()->attr('href')) : null;
    }

    private function faviconFromDomainRoot(): ?string
    {
        $candidate = "{$this->rootDomain}/favicon.ico";

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)->head($candidate);

            return $response->successful() ? $candidate : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function fetchHomepageHtml(): ?string
    {
        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)->get("{$this->baseUrl}/");

            return $response->successful() ? $response->body() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        if (str_starts_with($url, '//')) {
            return 'https:'.$url;
        }

        if (str_starts_with($url, '/')) {
            return $this->rootDomain.$url;
        }

        return $url;
    }
}
