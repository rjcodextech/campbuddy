<?php

namespace App\Services;

use Symfony\Component\DomCrawler\Crawler;

/**
 * Works out an event's "Event Information" (venue, links, wifi, …) from
 * what the event itself publishes: central.wordcamp.org's record for the
 * facts organizers register, and the event site's own pages for the rest.
 *
 * The rule throughout is *blank rather than wrong*: a field gets a value
 * only when a source clearly states it, and null otherwise. Nothing is
 * inferred, defaulted or padded — an attendee reading "Wifi: …" in the app
 * should be able to trust it.
 */
class EventInfoFetcher
{
    /** The fields of events.info, in the order the admin form shows them. */
    public const FIELDS = [
        'venue',
        'important_links',
        'wifi',
        'registration_info',
        'contributor_day_location',
        'code_of_conduct_url',
        'emergency_contact',
        'social_event_info',
        'nearby_venue_info',
    ];

    /** Each field's longest storable value (mirrors EventController::updateInfo's rules). */
    private const MAX_LENGTH = [
        'venue' => 255, 'important_links' => 2000, 'wifi' => 500, 'registration_info' => 1000,
        'contributor_day_location' => 255, 'code_of_conduct_url' => 500, 'emergency_contact' => 500,
        'social_event_info' => 1000, 'nearby_venue_info' => 1000,
    ];

    /**
     * Which page holds what. A page matches on a word in its URL slug
     * (preferred — slugs are stable across languages) or, failing that, on
     * its title. An exact slug match beats a partial one, so /tickets/ wins
     * over /ticket-countdown/.
     */
    private const PAGES = [
        'tickets' => ['slug' => ['tickets', 'ticket', 'registration', 'register', 'bilet', 'billets', 'entradas'], 'title' => '/\b(tickets?|registration|bilet\w*)\b/iu'],
        'schedule' => ['slug' => ['schedule', 'agenda', 'program', 'programme'], 'title' => '/\b(schedule|agenda|programme?)\b/iu'],
        'location' => ['slug' => ['venue', 'location', 'directions'], 'title' => '/\b(venue|location|directions)\b/iu'],
        'contact' => ['slug' => ['contact', 'kontakt'], 'title' => '/\b(contact|kontakt)\b/iu'],
        'faq' => ['slug' => ['faq', 'faqs'], 'title' => '/\bfaq\b/iu'],
        'conduct' => ['slug' => ['conduct', 'kodeks'], 'title' => '/(code of conduct|kodeks)/iu'],
        'contributor' => ['slug' => ['contributor'], 'title' => '/contributor day/iu'],
        'social' => ['slug' => ['party', 'afterparty', 'social', 'networking', 'dinner'], 'title' => '/\b(after-?party|social event|party|networking|dinner)\b/iu'],
        'nearby' => ['slug' => ['accommodation', 'hotel', 'hotels', 'travel', 'nearby', 'transport', 'stay'], 'title' => '/\b(accommodation|hotels?|where to stay|travel|nearby|transport)\b/iu'],
    ];

    /** Pages whose text is worth reading (the schedule is only ever linked to). */
    private const READ = ['tickets', 'location', 'contact', 'faq', 'conduct', 'contributor', 'social', 'nearby'];

    /** Human-readable summary of where the data came from, for the fetch log. */
    public string $sources = '';

    /** True if at least one source answered — so a total outage isn't mistaken for "nothing to find". */
    public bool $reachable = false;

    private readonly string $siteUrl;

    public function __construct(
        string $siteUrl,
        private readonly ?string $eventName = null,
        private readonly ?WordCampCentralDirectory $central = null,
        private readonly ?WordCampSitePages $pages = null,
    ) {
        $this->siteUrl = rtrim($siteUrl, '/');
    }

    /**
     * @return array<string, string|null> every field in FIELDS, null where nothing was found
     */
    public function fetch(): array
    {
        $directory = $this->central ?? new WordCampCentralDirectory;
        $record = $directory->findRecord($this->siteUrl, $this->eventName);

        $site = $this->pages ?? new WordCampSitePages($this->siteUrl);
        $index = $site->index();
        $picked = $this->pickPages($index);
        $html = $site->contents(array_values(array_intersect_key($picked, array_flip(self::READ))));

        $this->reachable = $record !== null || $index !== [];
        $this->sources = sprintf(
            'central.wordcamp.org: %s; site pages: %s',
            $record ? 'record found' : 'no record',
            $site->mode === 'none' ? 'unreachable' : "{$site->mode}, ".count($index).' pages'
        );

        // Each page we could read, as a DOM (for paragraphs/links) and as
        // flat text (for sentences), by role: tickets, contact…
        $crawlers = [];
        $texts = [];
        foreach (self::READ as $role) {
            if (isset($picked[$role], $html[$picked[$role]['url']])) {
                $crawlers[$role] = $this->crawler($this->spaced($html[$picked[$role]['url']]));
                $texts[$role] = $this->plainText($crawlers[$role]);
            }
        }

        $found = [
            'venue' => $record ? $directory->venueLine($record) : null,
            'important_links' => $this->importantLinks($picked),
            'wifi' => $this->wifi($texts),
            // Only the page's opening paragraphs, and only if they're about tickets —
            // a FAQ answer further down that happens to say "attendee" isn't registration info.
            'registration_info' => isset($crawlers['tickets']) ? $this->firstParagraph($crawlers['tickets'], '/\b(tickets?|registration|register|admission)\b/iu', 2) : null,
            'contributor_day_location' => $this->contributorDay($texts),
            'code_of_conduct_url' => $picked['conduct']['url'] ?? null,
            'emergency_contact' => $this->contact($texts, $crawlers),
            'social_event_info' => isset($crawlers['social']) ? $this->firstParagraph($crawlers['social']) : null,
            'nearby_venue_info' => isset($crawlers['nearby']) ? $this->firstParagraph($crawlers['nearby']) : null,
        ];

        return collect(self::FIELDS)
            ->mapWithKeys(fn (string $field) => [$field => $this->tidy($field, $found[$field] ?? null)])
            ->all();
    }

    /**
     * The best page for each role.
     *
     * @param  list<array{id: int|null, slug: string, url: string, title: string}>  $index
     * @return array<string, array{id: int|null, slug: string, url: string, title: string}>
     */
    private function pickPages(array $index): array
    {
        $picked = [];

        foreach (self::PAGES as $role => $rule) {
            $best = null;
            $bestScore = 0;

            foreach ($index as $page) {
                $tokens = preg_split('/[-_]+/', strtolower($page['slug'])) ?: [];
                $score = match (true) {
                    in_array($page['slug'], $rule['slug'], true) => 3,
                    array_intersect($tokens, $rule['slug']) !== [] => 2,
                    $page['title'] !== '' && preg_match($rule['title'], $page['title']) === 1 => 1,
                    default => 0,
                };

                // Best score wins; on a tie the shorter (more general) slug does.
                if ($score > $bestScore || ($score === $bestScore && $score > 0 && strlen($page['slug']) < strlen($best['slug']))) {
                    $best = $page;
                    $bestScore = $score;
                }
            }

            if ($best !== null) {
                $picked[$role] = $best;
            }
        }

        return $picked;
    }

    /** The event's site plus the handful of pages an attendee actually needs, one URL per line. */
    private function importantLinks(array $picked): ?string
    {
        $links = [];

        foreach (['tickets', 'schedule', 'location', 'contact', 'faq'] as $role) {
            if (isset($picked[$role])) {
                $links[] = $picked[$role]['url'];
            }
        }

        // The site's front page only counts as a link if we found a site to link.
        return $links === [] ? null : implode("\n", array_unique([$this->siteUrl.'/', ...$links]));
    }

    /**
     * A sentence about wifi that actually gives a network or password —
     * "wifi will be available" alone is not information.
     *
     * @param  array<string, string>  $texts
     */
    private function wifi(array $texts): ?string
    {
        foreach ($texts as $text) {
            foreach ($this->sentences($text) as $sentence) {
                if (preg_match('/\bwi-?fi\b/iu', $sentence) && preg_match('/\b(password|passcode|ssid|network)\b/iu', $sentence)) {
                    return $sentence;
                }
            }
        }

        return null;
    }

    /**
     * Where Contributor Day is. A sentence on the Contributor Day page that
     * says where it happens is the most trustworthy; failing that, a labelled
     * "Venue: …" line right after the words Contributor Day (some sites keep
     * a Contributor Day / Conference Day block on their Location page).
     *
     * @param  array<string, string>  $texts
     */
    private function contributorDay(array $texts): ?string
    {
        foreach ($this->sentences($texts['contributor'] ?? '') as $sentence) {
            if (preg_match('/contributor\s+day/iu', $sentence)
                && preg_match('/\b(venue|location|held at|take[s]? place (?:at|in)|kick(?:s)? off at|hosted at)\b/iu', $sentence)) {
                return $sentence;
            }
        }

        foreach (['location', 'contributor'] as $role) {
            // No \b before the label: block themes can run labels together ("…PMVenue: …").
            if (preg_match('/Contributor\s+Day\b.{0,200}?(?:Venue|Location|Where|Address)\s*:\s*(.+?)(?=\s*(?:Get\s+Directions?|Conference\s+Day|Date\s*:|Time\s*:|Contributor\s+Day|$))/isu', $texts[$role] ?? '', $m)) {
                return trim($m[1]);
            }
        }

        return null;
    }

    /**
     * Who to reach: a phone number the page itself labels as an emergency /
     * medical line, else the organizers' contact email — taken from the
     * Contact page (whatever domain they publish there), or failing that an
     * @wordcamp.org address (the event's own alias) found on another page.
     *
     * Deliberately not used: the shared safety-reporting address
     * (report@wordcamp.org — every event has it), and a personal address
     * that only appears in passing inside long prose such as the Code of
     * Conduct — that's someone's private inbox, not a published contact.
     *
     * @param  array<string, string>  $texts
     * @param  array<string, Crawler>  $crawlers
     */
    private function contact(array $texts, array $crawlers): ?string
    {
        foreach ($texts as $text) {
            if (preg_match('/\b(?:emergency|first[- ]aid|medical|helpline)\b[^0-9+]{0,80}(\+?\d[\d\s().-]{6,18}\d)/iu', $text, $m)) {
                return trim($m[1]);
            }
        }

        $usable = fn (string $email) => filter_var($email, FILTER_VALIDATE_EMAIL)
            && ! preg_match('/^(report|noreply|no-reply|donotreply)@/', $email);

        // 1. The Contact page: this is where organizers publish how to reach them.
        foreach ($this->emailsOn('contact', $texts, $crawlers) as $email) {
            if ($usable($email)) {
                return $email;
            }
        }

        // 2. The event's own @wordcamp.org alias, from the other pages.
        foreach (['conduct', 'faq'] as $role) {
            foreach ($this->emailsOn($role, $texts, $crawlers) as $email) {
                if ($usable($email) && str_ends_with($email, '@wordcamp.org')) {
                    return $email;
                }
            }
        }

        return null;
    }

    /**
     * Every email address on one page — mailto: links first, then any in the text.
     *
     * @param  array<string, string>  $texts
     * @param  array<string, Crawler>  $crawlers
     * @return list<string>
     */
    private function emailsOn(string $role, array $texts, array $crawlers): array
    {
        if (! isset($crawlers[$role])) {
            return [];
        }

        $emails = [];

        foreach ($crawlers[$role]->filter('a[href^="mailto:"]') as $anchor) {
            $emails[] = strtolower(trim(explode('?', substr((string) $anchor->getAttribute('href'), 7))[0]));
        }

        preg_match_all('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', $texts[$role] ?? '', $found);

        return array_values(array_unique([...$emails, ...array_map('strtolower', $found[0])]));
    }

    /**
     * The first paragraph that reads like real content (not a button label
     * or a "view this page in a browser" notice), cut at a sentence end.
     * $within limits how far down the page to look — the opening paragraphs
     * describe the page; later ones are FAQ answers and asides.
     */
    private function firstParagraph(Crawler $crawler, ?string $mustMatch = null, ?int $within = null): ?string
    {
        $seen = 0;

        foreach ($crawler->filter('p') as $node) {
            $paragraph = trim(preg_replace('/\s+/u', ' ', $node->textContent) ?? '');

            if (mb_strlen($paragraph) < 40 || preg_match('/(view this page in a browser|cookie|all rights reserved|©)/iu', $paragraph)) {
                continue;
            }

            if ($within !== null && ++$seen > $within) {
                return null;
            }

            if ($mustMatch !== null && ! preg_match($mustMatch, $paragraph)) {
                continue;
            }

            return $this->summarize($paragraph, 300);
        }

        return null;
    }
    /** Whole sentences up to $max characters; a lone over-long sentence is cut at a word with "…". */
    private function summarize(string $text, int $max): string
    {
        $out = '';

        foreach ($this->sentences($text) as $sentence) {
            if ($out !== '' && mb_strlen($out.' '.$sentence) > $max) {
                break;
            }

            $out = trim($out.' '.$sentence);
        }

        if (mb_strlen($out) > $max) {
            $out = rtrim(mb_substr($out, 0, $max - 1), " \t.,;:-").'…';
            $out = preg_replace('/\s+\S*…$/u', '…', $out) ?? $out;
        }

        return $out;
    }

    /** @return list<string> */
    private function sentences(string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/(?<=[.!?])\s+/u', $text) ?: [])));
    }

    /**
     * Puts a space after every block boundary, so flattening the DOM to text
     * can't glue neighbouring lines together ("…2026Time: 9:00…").
     */
    private function spaced(string $html): string
    {
        return preg_replace('/(<\/(?:p|div|li|h[1-6]|tr|td|th|section|article|blockquote)>|<br\s*\/?>)/i', '$1 ', $html) ?? $html;
    }

    private function crawler(string $html): Crawler
    {
        $crawler = new Crawler;
        $crawler->addHtmlContent('<body>'.$html.'</body>', 'UTF-8');

        return $crawler;
    }

    private function plainText(Crawler $crawler): string
    {
        foreach ($crawler->filter('script, style, noscript') as $node) {
            $node->parentNode?->removeChild($node);
        }

        return trim(preg_replace('/\s+/u', ' ', $crawler->text('')) ?? '');
    }

    /** Trim, normalise line endings, enforce the field's length, and turn blanks into null. */
    private function tidy(string $field, ?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim(str_replace("\r\n", "\n", $value));

        if ($value === '') {
            return null;
        }

        return mb_strlen($value) > self::MAX_LENGTH[$field]
            ? rtrim(mb_substr($value, 0, self::MAX_LENGTH[$field] - 1)).'…'
            : $value;
    }
}
