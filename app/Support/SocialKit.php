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
                'headline' => 'Save the date',
                'line' => $when,
                'caption' => "Save the date: {$name} is on {$dates}".($place !== '' ? " at {$place}" : '').".\n\nTalks, workshops, Contributor Day and a room full of people who build with WordPress. First WordCamp? You are very welcome.\n\nTickets and details: {$site}\n\n{$tags}",
            ],
            [
                'key' => 'countdown',
                'label' => 'Countdown',
                'headline' => $days === null ? 'It\'s WordCamp time' : ($days === 1 ? '1 day to go' : "{$days} days to go"),
                'line' => $when,
                'caption' => ($days === null ? "{$name} is here!" : ($days === 1 ? "Tomorrow: {$name}!" : "{$days} days to go until {$name}.")).
                    "\n\nPlan your sessions, find people to meet and get Contributor Day tips in CampBuddy, free and without signing up: {$app}\n\n{$tags}",
            ],
            [
                'key' => 'schedule',
                'label' => 'Schedule is live',
                'headline' => 'The schedule is live',
                'line' => trim(($sessions ? "{$sessions} sessions" : '').($sessions && $speakers ? ' · ' : '').($speakers ? "{$speakers} speakers" : '')) ?: $when,
                'caption' => "The schedule for {$name} is out".($sessions ? ": {$sessions} sessions" : '').($speakers ? " and {$speakers} speakers" : '').".\n\nSave the talks you want and get a reminder before each one: {$app}\n\n{$tags}",
            ],
            [
                'key' => 'app',
                'label' => 'Get CampBuddy',
                'headline' => 'Your WordCamp companion',
                'line' => 'Schedule, people to meet, Contributor Day: scan to open',
                'caption' => "Going to {$name}? CampBuddy puts the schedule, the people you want to meet and Contributor Day tips in one place. Free, no sign-up, works offline.\n\nOpen it here: {$app}\n\n{$tags}",
            ],
            [
                'key' => 'thank-you',
                'label' => 'Thank you',
                'headline' => 'Thank you!',
                'line' => $name,
                'caption' => "Thank you to everyone who made {$name} happen: speakers, sponsors, volunteers, organizers and every single attendee.\n\nSee you at the next WordCamp!\n\n{$tags}",
            ],
        ];

        return $posts;
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
                'photo' => $entry->gravatar_url ? preg_replace('/([?&])s=\d+/', '$1s=600', $entry->gravatar_url) : null,
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
        ];
    }
}
