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
     * CampBuddy's own posts about an event — CampBuddy talking to the people
     * going to it, not the organizers announcing it. What the image says
     * (kicker, headline, line) and a caption; each post's QR / link carries
     * its own `utm_content` (link()).
     *
     * @return list<array<string, mixed>>
     */
    public static function posts(Event $event, ?CarbonImmutable $now = null): array
    {
        $name = $event->display_name;
        $short = self::shortName($event);
        $dates = self::dates($event);
        $place = self::venue($event) !== '' ? self::venue($event) : self::place($event);
        $when = trim($dates.($place !== '' ? ' · '.$place : ''));
        $tags = self::hashtags($event).' #CampBuddy';
        $sessions = count(EventData::get($event->id, 'sessions') ?? []);
        $speakers = count(EventData::get($event->id, 'speakers') ?? []);
        $days = self::daysToGo($event, $now);
        $link = fn (string $key) => self::link($event, $key);

        return [
            [
                'key' => 'save-the-date',
                'label' => 'Coming up',
                'kicker' => 'Coming up',
                'headline' => "{$short} is coming",
                'line' => $when,
                'body' => 'none',
                'caption' => "{$name} is on {$dates}".($place !== '' ? " at {$place}" : '').".\n\nGoing? Open it in CampBuddy, your free WordCamp companion: the schedule, people to meet and Contributor Day tips in one place. No sign-up, no download.\n\n{$link('save-the-date')}\n\n{$tags}",
            ],
            [
                'key' => 'countdown',
                'label' => 'Countdown',
                'kicker' => 'Countdown',
                'headline' => $days === null ? "{$short} is here" : ($days === 1 ? 'Tomorrow!' : "{$days} days to go"),
                'line' => 'Get ready with CampBuddy',
                'body' => 'countdown',
                'days' => $days,
                'caption' => ($days === null ? "{$name} is here!" : ($days === 1 ? "{$name} is tomorrow!" : "{$days} days to go until {$name}.")).
                    "\n\nPick your sessions, find people to meet and pack the right things with CampBuddy. Free, no sign-up:\n\n{$link('countdown')}\n\n{$tags}",
            ],
            [
                'key' => 'schedule',
                'label' => 'Schedule in CampBuddy',
                'kicker' => 'Now in CampBuddy',
                'headline' => 'The full schedule, in your pocket',
                'line' => trim(($sessions ? "{$sessions} sessions" : '').($sessions && $speakers ? ' · ' : '').($speakers ? "{$speakers} speakers" : '')) ?: $when,
                'body' => 'none',
                'caption' => "The {$name} schedule is in CampBuddy".($sessions ? ": {$sessions} sessions" : '').($speakers ? " and {$speakers} speakers" : '').". Save the talks you like and get a reminder before each one, even offline.\n\n{$link('schedule')}\n\n{$tags}",
            ],
            [
                'key' => 'app',
                'label' => 'Get CampBuddy',
                'kicker' => 'Free · no sign-up',
                'headline' => 'Your WordCamp companion',
                'line' => "For everyone at {$short}",
                'body' => 'features',
                'caption' => "Going to {$name}? CampBuddy puts the schedule, the people you want to meet, Contributor Day tips and deals in one place. Free, no sign-up, works offline.\n\nScan the code or open: {$link('app')}\n\n{$tags}",
            ],
            [
                'key' => 'speakers',
                'label' => 'Speakers',
                'kicker' => 'Speakers',
                'headline' => "Meet the {$short} speakers",
                'line' => 'Their talks are in CampBuddy',
                'body' => 'photos',
                'caption' => "Meet the speakers of {$name}".($speakers ? ": {$speakers} people sharing what they know" : '').". Find every talk, save the ones you want and get a reminder in CampBuddy.\n\n{$link('speakers')}\n\n{$tags}",
            ],
            [
                'key' => 'sponsors',
                'label' => 'Sponsors',
                'kicker' => 'Say hi at their booths',
                'headline' => 'The sponsors who make it happen',
                'line' => 'Their deals are in CampBuddy',
                'body' => 'logos',
                'caption' => "These sponsors help keep {$name} affordable. Stop by their booths, and find their deals for attendees in CampBuddy.\n\n{$link('sponsors')}\n\n{$tags}",
            ],
            [
                'key' => 'today',
                'label' => 'Day schedule',
                'picker' => 'day',
                'kicker' => 'On the schedule',
                'headline' => "Today at {$short}",
                'line' => 'Save your picks in CampBuddy',
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
                'kicker' => 'Contributor Day',
                'headline' => 'Find your Contributor Day table',
                'line' => 'CampBuddy matches you with a team',
                'body' => 'tables',
                'caption' => "Contributor Day at {$name}: not sure which table to join? Answer three quick questions in CampBuddy and it suggests the team for you. No coding needed, every table welcomes beginners.\n\n{$link('contributor')}\n\n{$tags}",
            ],
            [
                'key' => 'meet',
                'label' => 'Find your people',
                'kicker' => 'Don\'t go alone',
                'headline' => 'Find people to meet',
                'line' => 'CampBuddy shows who shares your interests',
                'body' => 'none',
                'caption' => "First WordCamp, or don't know anyone at {$name}? CampBuddy shows attendees who share your interests, and gives you an easy way to say hi.\n\n{$link('meet')}\n\n{$tags}",
            ],
            [
                'key' => 'thank-you',
                'label' => 'Thank you',
                'kicker' => 'That\'s a wrap',
                'headline' => "Thank you, {$short}!",
                'line' => 'Your day is saved in CampBuddy',
                'body' => 'none',
                'caption' => "Thank you for using CampBuddy at {$name}! Your sessions, notes and the people you met stay in the app, and you can save it all as a PDF.\n\nSee you at the next WordCamp.\n\n{$link('thank-you')}\n\n{$tags}",
            ],
        ];
    }

    /**
     * The link every card's QR (and caption) opens: the event in CampBuddy,
     * tagged with the event and the post or role it came from.
     */
    public static function link(Event $event, string $content): string
    {
        return route('event.home', $event).'?'.http_build_query([
            'utm_source' => 'campbuddy',
            'utm_medium' => 'social',
            'utm_campaign' => $event->slug,
            'utm_content' => $content,
        ]);
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

            $role = $personRoles[0] ?? 'camp-card';
            $link = self::link($event, 'people-'.str_replace('_', '-', $role));
            $people[] = [
                'id' => $entry->id,
                'name' => $entry->name,
                'photo' => self::bigPhoto($entry->gravatar_url),
                'roles' => $personRoles,
                'role_labels' => $labels,
                'talk' => $talk,
                'card' => $card,
                'link' => $link,
                'caption' => $talk
                    ? "{$entry->name} is speaking at {$event->display_name}: \"{$talk}\". Save the talk in CampBuddy and get a reminder before it starts.\n\n{$link}\n\n{$tags} #CampBuddy"
                    : "Say hi to {$entry->name}".($main === 'Attendee' ? '' : ', '.strtolower($main))." at {$event->display_name}! Find them, and everyone else worth meeting, in CampBuddy.\n\n{$link}\n\n{$tags} #CampBuddy",
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
                // Every card's QR: the event in CampBuddy; social-kit.js adds the card's utm_content.
                'qr' => route('event.home', $event).'?'.http_build_query(['utm_source' => 'campbuddy', 'utm_medium' => 'social', 'utm_campaign' => $event->slug]),
                // Same event, same look; another event, another (social-kit.js seeds its shapes with this).
                'seed' => crc32((string) $event->slug),
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
