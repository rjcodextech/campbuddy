<?php

namespace App\Support;

use App\Models\Event;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Search (SEO), answer-engine (AEO) and generative-engine (GEO) metadata:
 * per-page title/description/robots, schema.org JSON-LD, and the URL list
 * behind /sitemap.xml, /llms.txt and IndexNow pings.
 *
 * Only public, factual things go in here — event names, dates, venues and
 * the published schedule. Nothing about attendees, ever.
 */
class Seo
{
    public const IMAGE = '/media/og-image.png';

    /** Event screens that are only a personal tool — kept out of search. */
    private const PRIVATE_PAGES = ['event.quest', 'event.camp-card', 'event.roster-removal.show', 'event.roster-removal.search'];

    /** Event screens listed in the sitemap, with how often they change. */
    public const EVENT_PAGES = [
        'event.home' => 'daily',
        'event.my-day' => 'daily',
        'event.explore' => 'daily',
        'event.guide' => 'weekly',
        'event.contribute' => 'weekly',
    ];

    /** Events a search engine may see: live, visible, newest dates first. */
    public static function publicEvents(): Collection
    {
        return Event::where('status', 'active')
            ->where('is_visible', true)
            ->orderByDesc('starts_on')
            ->orderBy('id')
            ->get();
    }

    public static function dates(Event $event): ?string
    {
        if (! $event->starts_on) {
            return null;
        }

        $label = $event->starts_on->format('j M Y');
        if ($event->ends_on && ! $event->ends_on->isSameDay($event->starts_on)) {
            $label = $event->starts_on->format('j M').' – '.$event->ends_on->format('j M Y');
        }

        return $label;
    }

    /**
     * Title, description and robots for one event screen.
     *
     * @return array{description: string, robots: string}
     */
    public static function eventPage(Event $event, ?string $route): array
    {
        $name = $event->display_name;
        $when = collect([self::dates($event), self::venue($event)])->filter()->implode(', ');
        $at = $when ? " ({$when})" : '';

        $description = match ($route) {
            'event.my-day' => "Full schedule for {$name}{$at}: talks, workshops, tracks and speakers. Star the sessions you want and get a reminder before they start.",
            'event.explore' => "Sponsors, attendees and venue information for {$name}{$at}.",
            'event.contribute' => "Contributor Day at {$name}: find the WordPress team that suits you — no coding needed — and what to say when you get there.",
            'event.guide' => "First time at {$name}? What happens during the day, the words people use, what to bring and how to meet people.",
            'event.quest' => "Small, friendly challenges that make meeting people at {$name} easy.",
            'event.camp-card' => "Make a digital name card with a QR code for {$name}.",
            default => "{$name}{$at}: what's on now, the full schedule, people to meet and a friendly guide for first-timers.",
        };

        return [
            'description' => Str::limit($description, 300),
            'robots' => in_array($route, self::PRIVATE_PAGES, true) ? 'noindex, follow' : 'index, follow, max-image-preview:large',
        ];
    }

    /**
     * JSON-LD for one event screen: the event itself, with its sessions on the
     * schedule page, plus a breadcrumb trail.
     */
    public static function eventSchema(Event $event, ?string $route, string $pageName): array
    {
        $node = self::event($event);

        if ($route === 'event.my-day') {
            $sessions = collect(EventData::get($event->id, 'sessions') ?? [])
                ->filter(fn ($s) => is_array($s) && filled($s['title'] ?? null) && filled($s['starts_at'] ?? null))
                ->take(150);

            if ($sessions->isNotEmpty()) {
                $speakers = collect(EventData::get($event->id, 'speakers') ?? [])->keyBy('id');
                $node['subEvent'] = $sessions->map(fn ($s) => self::session($s, $speakers, $event))->values()->all();
            }
        }

        $extra = [];
        if ($route === 'event.guide') {
            $description = self::eventPage($event, $route)['description'];
            $extra = [
                self::guideArticle("New to {$event->display_name}? Start here.", $description),
                self::faq(array_map(fn ($e) => [$e['q'], $e['a']], FirstTimerGuide::faq())),
                self::glossary(),
            ];
        }

        $crumbs = [['CampBuddy', route('home')], [$event->display_name, route('event.home', $event)]];
        if ($route !== 'event.home') {
            $crumbs[] = [$pageName, url()->current()];
        }

        return [$node, ...$extra, self::breadcrumbs($crumbs)];
    }

    /** schema.org Event for a WordCamp. */
    public static function event(Event $event): array
    {
        $node = [
            '@type' => 'Event',
            'name' => $event->display_name,
            'url' => route('event.home', $event),
            'description' => "{$event->display_name} is a WordCamp: a community-organized conference about WordPress.",
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'eventStatus' => 'https://schema.org/EventScheduled',
            'image' => [self::absolute($event->logoUrl() ?? self::IMAGE)],
        ];

        if ($event->starts_on) {
            $node['startDate'] = $event->starts_on->toDateString();
            $node['endDate'] = ($event->ends_on ?? $event->starts_on)->toDateString();
        }

        if ($venue = self::venue($event)) {
            $node['location'] = ['@type' => 'Place', 'name' => $venue, 'address' => $venue];
        }

        if ($site = SafeUrl::web($event->source_site_url)) {
            $node['sameAs'] = $site;
            $node['organizer'] = ['@type' => 'Organization', 'name' => "{$event->display_name} organizers", 'url' => $site];
        }

        return $node;
    }

    private static function session(array $s, Collection $speakers, Event $event): array
    {
        $node = [
            '@type' => 'Event',
            'name' => Str::limit((string) $s['title'], 200),
            'startDate' => $s['starts_at'],
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        ];

        if (($s['duration_seconds'] ?? 0) > 0) {
            $node['endDate'] = date('c', strtotime($s['starts_at']) + (int) $s['duration_seconds']);
        }
        if (filled($s['description'] ?? null)) {
            $node['description'] = Str::limit((string) $s['description'], 300);
        }
        if (! empty($s['track_names'])) {
            $node['location'] = ['@type' => 'Place', 'name' => implode(', ', $s['track_names'])];
        } elseif ($venue = self::venue($event)) {
            $node['location'] = ['@type' => 'Place', 'name' => $venue];
        }

        $names = collect($s['speaker_ids'] ?? [])->map(fn ($id) => $speakers[$id]['name'] ?? null)->filter()->values();
        if ($names->isNotEmpty()) {
            $node['performer'] = $names->map(fn ($n) => ['@type' => 'Person', 'name' => $n])->all();
        }

        return $node;
    }

    /** The site, the product and who it's for — on the picker. */
    public static function site(): array
    {
        $home = route('home');

        return [
            [
                '@type' => 'WebSite',
                '@id' => $home.'#website',
                'name' => config('campbuddy.name'),
                'alternateName' => config('campbuddy.tagline'),
                'url' => $home,
                'inLanguage' => 'en',
            ],
            [
                '@type' => 'WebApplication',
                'name' => config('campbuddy.name'),
                'url' => $home,
                'description' => config('campbuddy.pwa.description'),
                'applicationCategory' => 'LifestyleApplication',
                'operatingSystem' => 'Any (web browser)',
                'browserRequirements' => 'Requires a modern web browser',
                'isAccessibleForFree' => true,
                'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'],
                'audience' => ['@type' => 'Audience', 'audienceType' => 'WordCamp attendees, first-timers and students'],
                'image' => self::absolute(self::IMAGE),
            ],
        ];
    }

    /** @param  array<int, array{0: string, 1: string}>  $items  [name, answer] */
    public static function faq(array $items): array
    {
        return [
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn ($qa) => [
                '@type' => 'Question',
                'name' => $qa[0],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $qa[1]],
            ], $items),
        ];
    }

    /** The guide's glossary as a DefinedTermSet — easy for answer engines to quote. */
    public static function glossary(): array
    {
        return [
            '@type' => 'DefinedTermSet',
            'name' => 'WordCamp words you\'ll hear',
            'hasDefinedTerm' => array_map(fn ($e) => [
                '@type' => 'DefinedTerm',
                'name' => $e['term'],
                'description' => $e['meaning'],
            ], FirstTimerGuide::glossary()),
        ];
    }

    public static function guideArticle(string $headline, string $description): array
    {
        return [
            '@type' => 'Article',
            'headline' => $headline,
            'description' => $description,
            'url' => url()->current(),
            'image' => self::absolute(self::IMAGE),
            'inLanguage' => 'en',
            'publisher' => ['@type' => 'Organization', 'name' => config('campbuddy.name'), 'url' => route('home')],
            'about' => ['@type' => 'Thing', 'name' => 'WordCamp'],
        ];
    }

    /** @param  array<int, array{0: string, 1: string}>  $items  [name, url] */
    public static function breadcrumbs(array $items): array
    {
        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn ($item, $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $item[0],
                'item' => $item[1],
            ], $items, array_keys($items)),
        ];
    }

    /** One <script type="application/ld+json"> body for a list of nodes. */
    public static function jsonLd(array $nodes): string
    {
        return json_encode(
            ['@context' => 'https://schema.org', '@graph' => array_values(array_filter($nodes))],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE
        );
    }

    /**
     * Every indexable URL with its last-modified time.
     *
     * @return array<int, array{loc: string, lastmod: ?string, changefreq: string, priority: string}>
     */
    public static function urls(): array
    {
        $urls = [
            ['loc' => route('home'), 'lastmod' => null, 'changefreq' => 'daily', 'priority' => '1.0'],
            ['loc' => route('guide'), 'lastmod' => null, 'changefreq' => 'monthly', 'priority' => '0.9'],
        ];

        foreach (self::publicEvents() as $event) {
            $lastmod = $event->updated_at?->toAtomString();
            $upcoming = ! EventTime::isOver($event);

            foreach (self::EVENT_PAGES as $route => $freq) {
                $urls[] = [
                    'loc' => route($route, $event),
                    'lastmod' => $lastmod,
                    'changefreq' => $upcoming ? $freq : 'monthly',
                    'priority' => $route === 'event.home' ? ($upcoming ? '0.9' : '0.6') : ($upcoming ? '0.7' : '0.4'),
                ];
            }
        }

        return $urls;
    }

    /** A stable IndexNow key derived from APP_KEY, unless one is configured. */
    public static function indexNowKey(): string
    {
        return (string) (config('services.indexnow.key') ?: substr(hash('sha256', 'indexnow|'.config('app.key')), 0, 32));
    }

    public static function venue(Event $event): ?string
    {
        $venue = trim((string) ($event->info['venue'] ?? ''));

        return $venue !== '' ? Str::limit($venue, 160) : null;
    }

    public static function absolute(string $url): string
    {
        return Str::startsWith($url, ['http://', 'https://']) ? $url : url($url);
    }
}
