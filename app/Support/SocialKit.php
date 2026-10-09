<?php

namespace App\Support;

use App\Models\Event;
use Carbon\CarbonImmutable;

/**
 * Admin / manager → Event → Social media: everything the page needs to draw
 * the event's posts and people cards in the browser (resources/js/social-kit.js)
 * — the facts, the brand colours, and ready captions built from templates
 * (owner's choice: templates, not AI; every caption can be edited before use).
 *
 * Captions only state what CampBuddy knows about the event: its name, dates,
 * place, links and counts. Nothing about a person beyond their public name,
 * role and talk (the people cards).
 */
class SocialKit
{
    /** Used until the brand colours are picked from the logo or set by hand. */
    public const DEFAULT_COLORS = ['primary' => '#7a1f2b', 'secondary' => '#e0a11b', 'ink' => '#231f20', 'paper' => '#fff8ee'];

    /** @return array{primary: string, secondary: string, ink: string, paper: string} */
    public static function colors(Event $event): array
    {
        $saved = is_array($event->brand_colors ?? null) ? $event->brand_colors : [];
        $colors = self::DEFAULT_COLORS;
        foreach ($colors as $key => $default) {
            if (isset($saved[$key]) && preg_match('/^#[0-9a-f]{6}$/i', $saved[$key])) {
                $colors[$key] = strtolower($saved[$key]);
            }
        }

        return $colors;
    }

    public static function hasSavedColors(Event $event): bool
    {
        return is_array($event->brand_colors ?? null) && $event->brand_colors !== [];
    }

    /** "Rajasthan" from wordcamp-rajasthan-2026 (or the short name, without a #). */
    public static function place(Event $event): string
    {
        $fromSlug = preg_replace(['/^wordcamp-/', '/-\d{4}$/'], '', (string) $event->slug);

        return ucwords(str_replace('-', ' ', (string) $fromSlug));
    }

    /** "#WordCamp #WordPress #WCRajasthan #WCRajasthan2026" */
    public static function hashtags(Event $event): string
    {
        $short = trim((string) $event->short_name);
        $local = $short !== '' && str_starts_with($short, '#') && ! str_contains($short, ' ')
            ? ltrim($short, '#')
            : 'WC'.str_replace(' ', '', self::place($event));
        $year = $event->starts_on?->format('Y');

        return implode(' ', array_values(array_unique(array_filter(['#WordCamp', '#WordPress', '#'.$local, $year ? '#'.$local.$year : null]))));
    }

    /** "3–4 Oct 2026" */
    public static function dates(Event $event): string
    {
        if (! $event->starts_on) {
            return '';
        }
        $start = $event->starts_on;
        $end = $event->ends_on;
        if (! $end || $end->isSameDay($start)) {
            return $start->format('j M Y');
        }
        if ($start->isSameMonth($end)) {
            return $start->format('j').'–'.$end->format('j M Y');
        }

        return $start->format('j M').' – '.$end->format('j M Y');
    }

    /** The venue's name, without its street address. */
    public static function venue(Event $event): string
    {
        $venue = trim((string) ($event->info['venue'] ?? ''));

        return trim(preg_split('/\s+[—–-]\s+/u', $venue)[0] ?? '');
    }

    public static function appUrl(Event $event): string
    {
        return route('event.home', $event).'?'.http_build_query(['utm_source' => 'social', 'utm_medium' => 'post', 'utm_campaign' => $event->slug]);
    }

    /** Days until the first day at the venue; null once it has started. */
    public static function daysToGo(Event $event, ?CarbonImmutable $now = null): ?int
    {
        if (! $event->starts_on) {
            return null;
        }
        $today = CarbonImmutable::parse(EventTime::today($event, $now));
        $days = (int) $today->diffInDays(CarbonImmutable::parse($event->starts_on->format('Y-m-d')), false);

        return $days > 0 ? $days : null;
    }

    /**
     * The event posts: what the image says (headline, line) and a caption.
     *
     * @return list<array{key: string, label: string, headline: string, line: string, caption: string}>
     */
    public static function posts(Event $event, ?CarbonImmutable $now = null): array
    {
        $name = $event->display_name;
        $dates = self::dates($event);
        $place = self::venue($event) !== '' ? self::venue($event) : self::place($event);
        $when = trim($dates.($place !== '' ? ', '.$place : ''));
        $site = $event->source_site_url;
        $app = self::appUrl($event);
        $tags = self::hashtags($event);
        $sessions = count(EventData::get($event->id, 'sessions') ?? []);
        $speakers = count(EventData::get($event->id, 'speakers') ?? []);
        $days = self::daysToGo($event, $now);

        $posts = [
            [
                'key' => 'save-the-date',
                'label' => 'Save the date',
                'kicker' => 'Mark your calendar',
                'body' => 'none',
                'headline' => 'Save the date',
                'line' => $when,
                'caption' => "Save the date: {$name} is on {$dates}".($place !== '' ? " at {$place}" : '').".\n\nTalks, workshops, Contributor Day and a room full of people who build with WordPress. First WordCamp? You are very welcome.\n\nTickets and details: {$site}\n\n{$tags}",
            ],
            [
                'key' => 'countdown',
                'label' => 'Countdown',
                'kicker' => 'Countdown',
                'body' => 'countdown',
                'days' => $days,
                'headline' => $days === null ? 'It\'s WordCamp time' : ($days === 1 ? '1 day to go' : "{$days} days to go"),
                'line' => $when,
                'caption' => ($days === null ? "{$name} is here!" : ($days === 1 ? "Tomorrow: {$name}!" : "{$days} days to go until {$name}.")).
                    "\n\nPlan your sessions, find people to meet and get Contributor Day tips in CampBuddy, free and without signing up: {$app}\n\n{$tags}",
            ],
            [
                'key' => 'schedule',
                'label' => 'Schedule is live',
                'kicker' => 'Just announced',
                'body' => 'none',
                'headline' => 'The schedule is live',
                'line' => trim(($sessions ? "{$sessions} sessions" : '').($sessions && $speakers ? ' · ' : '').($speakers ? "{$speakers} speakers" : '')) ?: $when,
                'caption' => "The schedule for {$name} is out".($sessions ? ": {$sessions} sessions" : '').($speakers ? " and {$speakers} speakers" : '').".\n\nSave the talks you want and get a reminder before each one: {$app}\n\n{$tags}",
            ],
            [
                'key' => 'app',
                'label' => 'Get CampBuddy',
                'kicker' => 'Free · no sign-up',
                'body' => 'qr',
                'headline' => 'Your WordCamp companion',
                'line' => 'Schedule, people to meet, Contributor Day: scan to open',
                'caption' => "Going to {$name}? CampBuddy puts the schedule, the people you want to meet and Contributor Day tips in one place. Free, no sign-up, works offline.\n\nOpen it here: {$app}\n\n{$tags}",
            ],
            [
                'key' => 'thank-you',
                'label' => 'Thank you',
                'kicker' => 'That\'s a wrap',
                'body' => 'none',
                'headline' => 'Thank you!',
                'line' => $name,
                'caption' => "Thank you to everyone who made {$name} happen: speakers, sponsors, volunteers, organizers and every single attendee.\n\nSee you at the next WordCamp!\n\n{$tags}",
            ],
            [
                'key' => 'speakers',
                'label' => 'Speaker lineup',
                'kicker' => 'Speakers announced',
                'headline' => 'Meet the speakers',
                'line' => $speakers ? "{$speakers} speakers · {$when}" : $when,
                'body' => 'photos',
                'caption' => "Here are the speakers of {$name}".($speakers ? ": {$speakers} people sharing what they know about WordPress" : '').".\n\nSee every talk and save your favourites: {$app}\n\n{$tags}",
            ],
            [
                'key' => 'sponsors',
                'label' => 'Thank you, sponsors',
                'kicker' => 'With thanks to',
                'headline' => 'Our sponsors',
                'line' => 'They help keep WordCamp tickets affordable',
                'body' => 'logos',
                'caption' => "A big thank you to the sponsors of {$name}. They help keep WordCamp tickets affordable for everyone. Say hello at their booths!\n\n{$tags}",
            ],
            [
                'key' => 'today',
                'label' => 'Today at WordCamp',
                'picker' => 'day',
                'kicker' => 'On the schedule',
                'headline' => 'Today at '.self::shortName($event),
                'line' => $place,
                'body' => 'list',
                'caption' => '',
            ],
            [
                'key' => 'spotlight',
                'label' => 'Session spotlight',
                'picker' => 'session',
                'kicker' => 'Up next',
                'headline' => '',
                'line' => '',
                'body' => 'session',
                'caption' => '',
            ],
            [
                'key' => 'contributor',
                'label' => 'Contributor Day',
                'kicker' => 'Give back to WordPress',
                'headline' => 'Join Contributor Day',
                'line' => 'No coding needed. Every table welcomes beginners.',
                'body' => 'tables',
                'caption' => "Contributor Day at {$name}: spend a day making WordPress better with the teams that build it. Writers, designers, translators, testers and developers are all welcome, and every table helps beginners get started.\n\nFind your team in CampBuddy: {$app}\n\n{$tags}",
            ],
            [
                'key' => 'volunteers',
                'label' => 'Call for volunteers',
                'kicker' => 'We need you',
                'headline' => 'Volunteer with us',
                'line' => 'Help run '.$name,
                'body' => 'none',
                'caption' => "Want to see how a WordCamp works from the inside? Volunteer at {$name}: welcome attendees, help speakers and meet the whole community.\n\nSign up on the WordCamp site: {$site}\n\n{$tags}",
            ],
        ];

        return $posts;
    }

    /** "WordCamp Rajasthan" without the year, for short headlines. */
    public static function shortName(Event $event): string
    {
        return trim((string) preg_replace('/\s+\d{4}$/', '', (string) $event->display_name));
    }

    /**
     * The schedule as the Today / Spotlight posts need it, with times already
     * in the venue's time zone (any "running late" delay included).
     *
     * @return array{days: list<array{key: string, label: string}>, sessions: list<array<string, mixed>>, next: ?int}
     */
    public static function schedule(Event $event, ?CarbonImmutable $now = null): array
    {
        $zone = EventTime::zone($event);
        $now ??= CarbonImmutable::now();
        $speakers = collect(EventData::get($event->id, 'speakers') ?? [])->keyBy('id');

        $sessions = collect(ScheduleDelay::apply($event, EventData::get($event->id, 'sessions') ?? []))
            ->filter(fn ($s) => is_array($s) && ! empty($s['starts_at']) && ($s['title'] ?? '') !== '')
            ->map(function ($s) use ($zone, $speakers) {
                $start = CarbonImmutable::parse($s['starts_at'])->setTimezone($zone);
                $people = collect($s['speaker_ids'] ?? [])->map(fn ($id) => $speakers->get($id))->filter();

                return [
                    'id' => $s['id'],
                    'title' => $s['title'],
                    'at' => $start->toIso8601String(),
                    'day' => $start->format('Y-m-d'),
                    'time' => $start->format('g:i A'),
                    'track' => $s['track_names'][0] ?? null,
                    'type' => $s['session_type'] ?? null,
                    'speakers' => $people->pluck('name')->values()->all(),
                    'photos' => $people->map(fn ($p) => self::bigPhoto($p['avatar_url'] ?? null))->filter()->values()->all(),
                ];
            })
            ->sortBy('at')
            ->values();

        $days = $sessions->pluck('day')->unique()->values()
            ->map(fn ($day) => ['key' => $day, 'label' => CarbonImmutable::parse($day)->format('D j M')])->all();
        $next = $sessions->first(fn ($s) => CarbonImmutable::parse($s['at'])->greaterThan($now) && ($s['speakers'] !== []))
            ?? $sessions->first(fn ($s) => $s['speakers'] !== []) ?? $sessions->first();

        return ['days' => $days, 'sessions' => $sessions->all(), 'next' => $next['id'] ?? null];
    }

    /** A Gravatar at a size fit for a post. */
    public static function bigPhoto(?string $url, int $size = 600): ?string
    {
        if (! $url) {
            return null;
        }

        return str_contains($url, 'gravatar.com/') ? preg_replace('/([?&])s=\d+/', '$1s='.$size, $url) : $url;
    }

    /** @return list<array{name: string, photo: ?string}> the speakers for the lineup post */
    public static function speakers(Event $event): array
    {
        return collect(EventData::get($event->id, 'speakers') ?? [])
            ->map(fn ($p) => ['name' => (string) ($p['name'] ?? ''), 'photo' => self::bigPhoto($p['avatar_url'] ?? null, 300)])
            ->filter(fn ($p) => $p['name'] !== '')
            ->values()->all();
    }

    /** @return list<array{name: string, logo: ?string, tier: ?string}> */
    public static function sponsors(Event $event): array
    {
        return collect(EventData::get($event->id, 'sponsors') ?? [])
            ->map(fn ($s) => ['name' => (string) ($s['name'] ?? ''), 'logo' => $s['logo_url'] ?? null, 'tier' => $s['tier_names'][0] ?? null])
            ->filter(fn ($s) => $s['name'] !== '')
            ->values()->all();
    }

    /**
     * People for the bulk cards: everyone with a role on the list, or with a
     * Camp Card they chose to show there — name, photo, roles, a speaker's
     * first talk, and a caption.
     *
     * @return list<array<string, mixed>>
     */
    public static function people(Event $event): array
    {
        $roles = RosterRoles::forEvent($event);
        $entries = $event->attendeeRoster()->where('is_suppressed', false)->orderBy('name')
            ->get(['id', 'name', 'gravatar_url']);

        $cards = [];
        try {
            $cards = \App\Models\SharedCampCard::where('event_id', $event->id)->alive($event)->pluck('fields', 'attendee_roster_id')->all();
        } catch (\Throwable) {
            // Before the migration: roles only.
        }

        $tags = self::hashtags($event);
        $people = [];
        foreach ($entries as $entry) {
            $personRoles = $roles[$entry->id]['roles'] ?? [];
            $card = $cards[$entry->id] ?? null;
            if ($personRoles === [] && ! $card) {
                continue;
            }

            $labels = array_map(fn ($r) => RosterRoles::LABELS[$r] ?? $r, $personRoles);
            $talk = $roles[$entry->id]['talks'][0] ?? null;
            $main = $labels[0] ?? 'Attendee';

            $people[] = [
                'id' => $entry->id,
                'name' => $entry->name,
                'photo' => self::bigPhoto($entry->gravatar_url),
                'roles' => $personRoles,
                'role_labels' => $labels,
                'talk' => $talk,
                'card' => $card,
                'caption' => $talk
                    ? "Meet our speaker {$entry->name}! Catch \"{$talk}\" at {$event->display_name}.\n\n{$tags}"
                    : "Meet {$entry->name}, ".strtolower($main === 'Attendee' ? 'one of our attendees' : 'our '.$main)." at {$event->display_name}!\n\n{$tags}",
            ];
        }

        return $people;
    }

    /** @return array<string, mixed> everything social-kit.js needs */
    public static function data(Event $event): array
    {
        return [
            'event' => [
                'name' => $event->display_name,
                'dates' => self::dates($event),
                'place' => self::venue($event) ?: self::place($event),
                'site' => $event->source_site_url,
                'app' => self::appUrl($event),
                'hashtags' => self::hashtags($event),
                'logo' => $event->logoUrl() ?? $event->faviconUrl(),
                'mark' => $event->faviconUrl() ?? $event->logoUrl(),
                'slug' => $event->slug,
            ],
            'colors' => self::colors($event),
            'colorsSaved' => self::hasSavedColors($event),
            'posts' => self::posts($event),
            'people' => self::people($event),
            'speakers' => self::speakers($event),
            'sponsors' => self::sponsors($event),
            'schedule' => self::schedule($event),
            'tables' => collect(\App\Models\ContributorTable::forEvent($event))->map->publicData()->values()->all(),
            'short' => self::shortName($event),
        ];
    }
}
