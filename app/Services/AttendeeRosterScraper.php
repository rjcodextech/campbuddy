<?php

namespace App\Services;

use Symfony\Component\DomCrawler\Crawler;

/**
 * Parses a WordCamp site's public Attendees page (§0.3) — the one place
 * in the ingestion pipeline with no REST endpoint and no stable contract
 * (it's CampTix's plain template markup, "tix-*" classes). §3.3 IN4:
 * a structural change here must be detectable, not silently ingested as
 * an empty roster.
 */
class AttendeeRosterScraper
{
    /**
     * @return array<int, array{name: string, gravatar_url: ?string, links: array<int, array{type: string, url: string}>}>|null
     *         null means the page's expected structure wasn't found at
     *         all (§3.3 IN4) — distinct from a structure that parsed
     *         cleanly into zero entries (nobody's opted in yet).
     */
    public function parse(string $html): ?array
    {
        $crawler = new Crawler($html);
        $list = $crawler->filter('.tix-attendee-list');

        if ($list->count() === 0) {
            return null;
        }

        $entries = [];

        $list->filter('li')->each(function (Crawler $li) use (&$entries) {
            $nameNode = $li->filter('.tix-attendee-name');

            if ($nameNode->count() === 0) {
                return;
            }

            $first = trim($nameNode->filter('.tix-first')->count() ? $nameNode->filter('.tix-first')->text() : '');
            $last = trim($nameNode->filter('.tix-last')->count() ? $nameNode->filter('.tix-last')->text() : '');
            $name = trim("{$first} {$last}");

            if ($name === '') {
                return;
            }

            $avatar = $li->filter('img.avatar');
            $gravatarUrl = $avatar->count() ? $avatar->attr('src') : null;

            $links = [];
            $li->filter('a.tix-field')->each(function (Crawler $a) use (&$links) {
                $href = $a->attr('href');
                if (! $href) {
                    return;
                }

                $class = $a->attr('class') ?? '';
                $type = match (true) {
                    str_contains($class, 'linkedin') => 'linkedin',
                    str_contains($class, 'twitter') || str_contains($class, 'x-handle') => 'twitter',
                    default => 'website',
                };

                $links[] = ['type' => $type, 'url' => $href];
            });

            $entries[] = ['name' => $name, 'gravatar_url' => $gravatarUrl, 'links' => $links];
        });

        return $entries;
    }

    public function contentHash(string $name, array $links): string
    {
        $linkKey = collect($links)->map(fn ($l) => "{$l['type']}:{$l['url']}")->sort()->implode('|');

        return hash('sha256', "{$name}|{$linkKey}");
    }
}
