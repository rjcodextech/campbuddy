<?php

namespace App\Support;

use App\Models\Event;
use Illuminate\Support\Collection;

/**
 * Admin → Events → Media Partner Pitch: the one source of its words. The page, the copied email and the
 * printable proposal all read from here, so they can no longer say different things.
 *
 * Every claim is something CampBuddy does today or a number published on wpsimplified.in (30 Oct 2026):
 * 18+ community events, 13 cities, 5 years, media partner of WordCamp Rajasthan 2026 and Bengaluru 2026.
 * Nothing here says "no personal data" or "100% offline": attendees can choose to share (discovery,
 * Camp Card, deal forms), and live updates need a connection (see /guide#offline).
 */
class MediaPitch
{
    public const APPLICANT = [
        'name' => 'Sunil Kumar Sharma',
        'role' => 'Lead, WPSimplified & CampBuddy',
        'email' => 'hello@wpsimplified.in',
        'phone' => '+91-7877994704',
    ];

    public const CHANNELS = [
        'campbuddy' => [
            'website' => 'https://campbuddy.club/',
            'youtube' => 'https://www.youtube.com/@CampBuddyClub',
            'instagram' => 'https://www.instagram.com/campbuddyclub/',
            'x' => 'https://x.com/CampBuddyClub',
        ],
        'wpsimplified' => [
            'website' => 'https://wpsimplified.in/',
            'youtube' => 'https://www.youtube.com/@WPSimplifiedBySunil',
            'instagram' => 'https://www.instagram.com/wpsimplified/',
            'x' => 'https://x.com/iwpsimplified',
            'linkedin' => 'https://www.linkedin.com/company/wpsimplified/',
        ],
    ];

    /** The co-branded logo with the guideline's clear space (28% of its height) on every side. */
    public const LOGO_PNG = '/media/campbuddy-wpsimplified-logo-clear.png';

    public const LOGO_PRINT_PNG = '/media/campbuddy-wpsimplified-logo-clear-900dpi.png';

    public const LOGO_SVG = '/media/campbuddy-wpsimplified-logo-clear.svg';

    /**
     * The four website bios, each short (a listing, ~60 words) and full (an about page).
     *
     * @return array<string, array{label: string, short: string, full: string}>
     */
    public static function bios(): array
    {
        return [
            'campbuddy' => [
                'label' => 'CampBuddy',
                'short' => 'CampBuddy is a free companion app for WordCamp attendees, built by WPSimplified. It answers "What should I do right now?" with the live schedule, My Day with session reminders, quests for first-timers, Contributor Day table matching, a digital Camp Card with a QR code, and sponsor deals. It opens in any phone browser, with no sign-up, and keeps working on patchy venue Wi-Fi.',
                'full' => "CampBuddy is a free companion app for WordCamp attendees, built by WPSimplified. It answers the question everyone has in a busy venue: \"What should I do right now?\"\n\nFor attendees: the live multi-track schedule with \"Happening now\" and \"Up next\", My Day with session reminders, quests that walk first-timers through the day, Contributor Day table matching by skill, and a digital Camp Card with a QR code that opens your LinkedIn, website or another link. People who choose to can find others with the same interests and arrange to meet.\n\nFor organizers and sponsors: the event's schedule, speakers and sponsors come from the WordCamp's own website and stay in sync, organizers can send \"running late\" notices and announcements to attendees' phones, and sponsors get a deals page with an optional lead form.\n\nIt opens in any phone browser with no account and no app store. After the first visit the schedule, My Day and Camp Card keep working offline, and anything that shares data asks first.",
            ],
            'wpsimplified' => [
                'label' => 'WPSimplified',
                'short' => 'WPSimplified is a WordPress learning channel and community by Sunil Kumar Sharma: tutorials, podcasts and shorts every week, in Hindi and English, from a first site to custom plugins. It also covers WordPress events: 18+ community events across 13 cities, and media partner of WordCamp Rajasthan 2026 and WordCamp Bengaluru 2026.',
                'full' => "WPSimplified (wpsimplified.in) is a WordPress learning platform and community run by Sunil Kumar Sharma. Every week it publishes practical tutorials, podcasts and shorts on YouTube, in Hindi and English, explained the way you would explain them to a friend: from building a first site to plugin development, performance and freelancing.\n\nWPSimplified is also part of the WordCamp community in India. Over five years it has been at 18+ community events in 13 cities, as speaker, organizer, volunteer, photographer and A/V crew, and it is a media partner of WordCamp Rajasthan 2026 and WordCamp Bengaluru 2026. Its event coverage includes interviews with speakers and community members, short reels and recap videos.\n\nThe idea behind it is simple: no gatekeeping. Good WordPress knowledge should reach people in the language they think in.",
            ],
            'sunil' => [
                'label' => 'Sunil Kumar Sharma',
                'short' => 'Sunil Kumar Sharma is a WordPress developer and technical architect with more than ten years of experience building custom plugins and large WordPress sites. He leads WPSimplified and CampBuddy, and has spoken, organized and volunteered at WordCamps across India and Asia. His motto: "Zero gatekeeping, tech sabko sikhana is the vibe."',
                'full' => "Sunil Kumar Sharma is a WordPress developer and technical architect with more than ten years of experience building custom plugins, themes and high-traffic WordPress sites.\n\nHe has been a speaker, organizer, volunteer and mentor at WordCamps including WordCamp Kolhapur, Nepal, Bhopal, Rajasthan, Bengaluru, Delhi and Asia. Through WPSimplified he teaches WordPress development in Hindi and English, with a focus on developers and creators outside the big metros. He built CampBuddy so that every WordCamp attendee, first-timers most of all, knows what to do next.\n\nHis motto: \"Zero gatekeeping, tech sabko sikhana is the vibe.\"",
            ],
            'joint' => [
                'label' => 'CampBuddy × WPSimplified',
                'short' => 'CampBuddy × WPSimplified: an attendee companion app and a WordPress media channel from one team, led by Sunil Kumar Sharma. CampBuddy puts the live schedule, My Day, quests and Camp Cards on every attendee\'s phone; WPSimplified covers the event before, during and after, in Hindi and English. Media partner of WordCamp Rajasthan 2026 and WordCamp Bengaluru 2026.',
                'full' => "CampBuddy × WPSimplified is one team, led by Sunil Kumar Sharma, that supports WordCamps in two ways.\n\nCampBuddy is a free companion app for attendees: the live schedule, My Day with reminders, quests for first-timers, Contributor Day table matching, digital Camp Cards with a QR code, and a deals page for sponsors. It opens in any phone browser with no sign-up, stays in sync with the event's website, and lets organizers send live updates.\n\nWPSimplified is a WordPress learning channel in Hindi and English. Around an event it publishes announcement posts and reels, films short interviews on site, and follows up with a highlight video and a recap article. It has been at 18+ community events in 13 cities and is a media partner of WordCamp Rajasthan 2026 and WordCamp Bengaluru 2026.",
            ],
        ];
    }

    /**
     * What the page fills in for one event: name, dates, venue, the organizers' address, its CampBuddy page.
     *
     * @return array{slug: string, name: string, dates: string, venue: string, email: string, link: ?string}
     */
    public static function eventFields(Event $event): array
    {
        $info = is_array($event->info) ? $event->info : [];

        return [
            'slug' => (string) $event->slug,
            'name' => (string) $event->display_name,
            'dates' => self::dates($event),
            // The venue's name, not its street address ("PWCC - Penang Waterfront Convention Centre — 1B, …").
            'venue' => trim(explode(' — ', (string) ($info['venue'] ?? ''))[0]),
            'email' => self::organizerEmail($event),
            // Only an event attendees can open (event.public) gets a link.
            'link' => $event->status === 'active' && $event->is_visible ? route('event.home', $event) : null,
        ];
    }

    /** "12–13 Dec 2026", "21 Nov 2026", or "" without dates. */
    public static function dates(Event $event): string
    {
        $start = $event->starts_on;
        $end = $event->ends_on;
        if (! $start) {
            return '';
        }
        if (! $end || $end->isSameDay($start)) {
            return $start->format('j M Y');
        }
        if ($start->format('M Y') === $end->format('M Y')) {
            return $start->format('j').'–'.$end->format('j M Y');
        }

        return $start->format('j M').' – '.$end->format('j M Y');
    }

    /**
     * The organizers' address: the one their site publishes (event info), else the WordCamp convention
     * <city>@wordcamp.org from the site's address (delhi.wordcamp.org/2026 → delhi@wordcamp.org).
     */
    public static function organizerEmail(Event $event): string
    {
        $info = is_array($event->info) ? $event->info : [];
        $published = trim((string) ($info['emergency_contact'] ?? ''));
        if (filter_var($published, FILTER_VALIDATE_EMAIL)) {
            return strtolower($published);
        }

        $host = strtolower((string) parse_url((string) $event->source_site_url, PHP_URL_HOST));
        if (preg_match('/^([a-z0-9-]+)\.wordcamp\.org$/', $host, $m) && $m[1] !== 'central') {
            return "{$m[1]}@wordcamp.org";
        }

        return '';
    }

    /** How many upcoming WordCamps CampBuddy is ready for (the picker's own list). */
    public static function upcomingCount(): int
    {
        return rescue(fn () => AppEventApi::listed(null, true)->reject(fn (Event $e) => EventTime::isOver($e))->count(), 0, false);
    }

    /** @return Collection<int, array> every event the admin can pick, newest first */
    public static function events(): Collection
    {
        return Event::query()
            ->orderByRaw('starts_on IS NULL, starts_on DESC')
            ->get(['id', 'slug', 'display_name', 'starts_on', 'ends_on', 'source_site_url', 'info', 'status', 'is_visible'])
            ->map(fn (Event $e) => self::eventFields($e));
    }

    /**
     * The proposal, as sections both the email and the printable page are built from.
     * $f: event, details (" (dates, venue)"), link, name, role, email, phone (may be ""), count.
     *
     * @param  array<string, string>  $f
     * @return array{intro: array<int, string>, who: array<string, string>, before: array<int, string>, during: array<int, string>, after: array<int, string>, promise: string, ask: array<int, string>, closing: string}
     */
    public static function sections(array $f): array
    {
        $event = $f['event'];
        $ready = $f['ready'] ?? str_replace(['{event}', '{link}'], [$event, $f['link']], self::readyLine($f['link'] !== ''));
        $count = (int) ($f['count'] ?? 0) >= 2 ? " It is ready for {$f['count']} upcoming WordCamps." : '';

        return [
            'intro' => [
                "I'm {$f['name']} ({$f['role']}). We would like to be a media partner for {$event}{$f['details']}.",
                "In short: WPSimplified covers WordCamps in Hindi and English, and CampBuddy gives attendees a free companion app on their phone. {$ready}",
            ],
            'who' => [
                'CampBuddy (https://campbuddy.club)' => "A free attendee companion that opens in any phone browser, no account needed: the live schedule with \"Happening now\", My Day with session reminders, quests for first-timers, Contributor Day table matching, digital Camp Cards with a QR code, and a deals page for sponsors. It keeps working offline after the first visit.{$count}",
                'WPSimplified (https://wpsimplified.in)' => 'WordPress tutorials, podcasts and shorts every week in Hindi and English, by Sunil Kumar Sharma. 18+ community events in 13 cities over five years; media partner of WordCamp Rajasthan 2026 and WordCamp Bengaluru 2026.',
            ],
            'before' => [
                'Announcement posts, stories and short reels on our channels (YouTube, Instagram, X and LinkedIn): dates, speakers, tickets and venue.',
                "{$event} on CampBuddy with the full schedule, speakers, sponsors and venue guide, kept in sync with your website.",
            ],
            'during' => [
                'CampBuddy for attendees: "Happening now", My Day, Camp Card QR swaps and Contributor Day tables. Your team can send "running late" notices and announcements to attendees\' phones.',
                'On-site video: short interviews with speakers, sponsors and attendees, and behind-the-scenes clips.',
            ],
            'after' => [
                'A highlight video and the interviews on YouTube (@WPSimplifiedBySunil and @CampBuddyClub).',
                "A recap article on wpsimplified.in with highlights, session takeaways and photos.",
            ],
            'promise' => "Everything we publish credits {$event} and links to your website. We follow the WordCamp Code of Conduct and the event's photo policy.",
            'ask' => [
                "A media partner listing on the {$event} website (logo, short bio and link). Logo: https://campbuddy.club".self::LOGO_PNG.'. The short bio is below.',
                'Media passes for our small crew (camera and coverage).',
                'Permission to share the CampBuddy QR code with attendees, for example on a slide or at the registration desk.',
            ],
            'closing' => 'Happy to adjust any of this to what works best for you. Thank you for considering us.',
        ];
    }

    /** The sentence about the event on CampBuddy: a link to try it, or the promise to set it up. */
    public static function readyLine(bool $hasLink): string
    {
        return $hasLink
            ? '{event} is already set up on CampBuddy, so you can try it now: {link}'
            : 'We will set {event} up on CampBuddy with your schedule, speakers and sponsors.';
    }

    /**
     * The plain-text email, with {tokens} the page fills in as the fields change. Plain text on purpose:
     * Gmail and Outlook show Markdown ("###", "*") as typed.
     */
    public static function emailTemplate(int $upcoming = 0): string
    {
        $s = self::sections([
            'event' => '{event}', 'details' => '{details}', 'link' => '{link}', 'ready' => '{ready}', 'name' => '{name}',
            'role' => '{role}', 'email' => '{email}', 'phone' => '{phone}', 'count' => (string) $upcoming,
        ]);
        $list = fn (array $items, int $from = 1) => implode("\n", array_map(fn ($i, $text) => ($from + $i).'. '.$text, array_keys($items), $items));
        $joint = self::bios()['joint']['short'];

        return implode("\n", [
            'Dear {event} organizing team,',
            '',
            implode("\n\n", $s['intro']),
            '',
            'WHO WE ARE',
            ...array_map(fn ($who, $text) => "- {$who}: {$text}", array_keys($s['who']), $s['who']),
            '',
            'WHAT WE WILL DO',
            'Before the event',
            $list($s['before']),
            'During the event',
            $list($s['during'], 3),
            'After the event',
            $list($s['after'], 5),
            $s['promise'],
            '',
            'WHAT WE ASK FOR',
            $list($s['ask']),
            '',
            'LINKS',
            'CampBuddy: https://campbuddy.club | YouTube @CampBuddyClub | Instagram @campbuddyclub | X @CampBuddyClub',
            'WPSimplified: https://wpsimplified.in | YouTube @WPSimplifiedBySunil | Instagram @wpsimplified | X @iwpsimplified | LinkedIn company/wpsimplified',
            '',
            'SHORT BIO FOR YOUR WEBSITE',
            $joint,
            '',
            $s['closing'],
            '',
            'Warm regards,',
            '{name}',
            '{role}',
            'Email: {email}{phone}',
        ]);
    }

    public static function subjectTemplate(): string
    {
        return 'Media partnership for {event}: CampBuddy × WPSimplified';
    }
}
