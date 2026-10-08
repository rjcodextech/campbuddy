<?php

namespace App\Services;

use App\Support\SafeUrl;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Parses a WordCamp site's public Attendees page — the one place
 * in the ingestion pipeline with no REST endpoint and no stable contract
 * (it's CampTix's plain template markup, "tix-*" classes). A structural change here must be detectable, not silently ingested as
 * an empty roster.
 */
class AttendeeRosterScraper
{
    /**
     * @return array<int, array{name: string, gravatar_url: ?string, links: array<int, array{type: string, url: string}>, microsponsor: bool}>|null
     *                                                                                                                          null means the page's expected structure wasn't found at
     *                                                                                                                          all — distinct from a structure that parsed
     *                                                                                                                          cleanly into zero entries (nobody's opted in yet).
     */
    public function parse(string $html): ?array
    {
        $crawler = new Crawler($html);
        $list = $crawler->filter('.tix-attendee-list');

        if ($list->count() === 0) {
            return null;
        }

        $entries = [];

        // One list at a time, in page order: a page may list its microsponsors
        // in a block of their own (isMicrosponsorList).
        $list->each(function (Crawler $oneList) use (&$entries) {
            $this->parseList($oneList, $this->isMicrosponsorList($oneList), $entries);
        });

        return $entries;
    }

    /**
     * Whether this list is a page's separate "Microsponsors" block: the list,
     * or one of the few blocks around it, is marked as such (a class like
     * "microsponsor"), or carries a heading saying so while holding no other
     * attendee list. The plain CampTix page has no such block — nobody is
     * marked there.
     */
    private function isMicrosponsorList(Crawler $list): bool
    {
        $pattern = '/micro[\s_-]*sponsor/i';
        $node = $list->getNode(0);

        for ($depth = 0; $node instanceof \DOMElement && $depth < 4; $depth++, $node = $node->parentNode) {
            if (preg_match($pattern, $node->getAttribute('class').' '.$node->getAttribute('id'))) {
                return true;
            }

            if ($depth === 0) {
                continue;
            }

            $block = new Crawler($node);
            if ($block->filter('.tix-attendee-list')->count() !== 1) {
                return false;
            }

            foreach ($block->filter('h1, h2, h3, h4') as $heading) {
                if (preg_match($pattern, $heading->textContent)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @param array<int, array<string, mixed>> $entries */
    private function parseList(Crawler $list, bool $microsponsor, array &$entries): void
    {
        $list->filter('li')->each(function (Crawler $li) use (&$entries, $microsponsor) {
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
            $gravatarUrl = $avatar->count() ? SafeUrl::web($avatar->attr('src')) : null;

            $links = [];
            $li->filter('a.tix-field')->each(function (Crawler $a) use (&$links) {
                // These are typed in by the attendees themselves. Only web
                // addresses are kept — a javascript: link would run in CampBuddy.
                $href = SafeUrl::web($a->attr('href'));
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

            $entries[] = ['name' => $name, 'gravatar_url' => $gravatarUrl, 'links' => $links, 'microsponsor' => $microsponsor];
        });
    }

    public function contentHash(string $name, array $links): string
    {
        $linkKey = collect($links)->map(fn ($l) => "{$l['type']}:{$l['url']}")->sort()->implode('|');

        return hash('sha256', "{$name}|{$linkKey}");
    }
}
