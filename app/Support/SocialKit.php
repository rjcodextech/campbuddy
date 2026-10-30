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
        $at = $place !== '' ? " at {$place}" : '';
        $sessions = count(EventData::get($event->id, 'sessions') ?? []);
        $speakers = count(EventData::get($event->id, 'speakers') ?? []);
        $count = fn (int $n, string $one, string $many) => $n.' '.($n === 1 ? $one : $many);
        // The people and sponsors by name in the long captions (every name, whole), when there are 30 or fewer.
        $named = fn (array $list) => $list !== [] && count($list) <= 30 ? SocialCaptions::names(array_column($list, 'name')) : null;
        $speakerNames = $named(self::speakers($event));
        $sponsorNames = $named(self::sponsors($event));
        $sched = self::schedule($event, $now);
        $timed = $sched['sessions'] !== [];
        $dayCount = count($sched['days']);
        $days = self::daysToGo($event, $now);
        $link = fn (string $key) => self::link($event, $key);
        // The site's own tagline ("Dil se Dilli, code se community"), as the site says it.
        $tagline = SiteTheme::tagline($event);
        $opening = $tagline !== null ? $tagline.(preg_match('/[.!?]$/u', $tagline) ? '' : '.') : null;
        // Every event gets its own wording: the same event always the same, the next one different.
        $pick = fn (string $key, array $options) => $options[crc32($event->slug.'|'.$key) % count($options)];

        // The venue's short name (before its first comma) and the event's own hashtag, for the new posts.
        $placeShort = trim(explode(',', $place)[0] ?? '') ?: $short;
        $hashtag = (string) (collect(preg_split('/\s+/', trim(self::hashtags($event))) ?: [])
            ->first(fn ($tag) => str_starts_with($tag, '#') && ! in_array(mb_strtolower($tag), ['#wordcamp', '#wordpress'], true)) ?? '');

        $posts = [
            [
                'key' => 'save-the-date',
                'label' => 'Coming up',
                'kicker' => $tagline ?? $pick('std-kicker', ['Coming up', 'Save the date', 'Mark your calendar']),
                'headline' => $pick('std-head', ["{$short} is coming", "See you at {$short}", "{$short}, here we come"]),
                'line' => $when,
                'body' => 'none',
                'parts' => ['opening' => $opening, 'cta' => 'Plan your days in CampBuddy'] + $pick('std-caption', [
                    ['hook' => "Save the date: {$name} is on {$dates}{$at}.", 'body' => 'Going? CampBuddy is your free WordCamp companion: the schedule, the people to meet and Contributor Day tips, all in one place. No sign-up, nothing to install.'],
                    ['hook' => "{$dates}{$at}: that's {$name}. Mark your calendar.", 'body' => 'Planning to go? Save the talks you want, find people who share your interests and keep it all on your phone, even offline. Free, no sign-up.'],
                    ['hook' => "{$name} is coming: {$dates}{$at}.", 'body' => 'Get ready early with CampBuddy: the full schedule with reminders, people worth meeting and Contributor Day tips. Free, nothing to install.'],
                ]),
            ],
            [
                'key' => 'countdown',
                'label' => 'Countdown',
                'kicker' => 'Countdown',
                'headline' => $days === null ? "{$short} is here" : ($days === 1 ? $pick('cd-one', ['Tomorrow!', 'See you tomorrow']) : $pick('cd-head', ["{$days} days to go", "{$days} days until {$short}", "Only {$days} days left"])),
                'line' => $pick('cd-line', ['Get ready with CampBuddy', 'Plan your days in CampBuddy', 'Your schedule is ready in CampBuddy']),
                'body' => 'countdown',
                'days' => $days,
                // No other number in these words: the studio changes the count of days as the days pass.
                'parts' => [
                    'hook' => $days === null ? "{$name} is here!" : ($days === 1 ? "{$name} is tomorrow!" : "{$days} days to go until {$name}."),
                    'cta' => 'Get ready in CampBuddy',
                ] + $pick('cd-caption', [
                    ['body' => 'Pick your sessions, find people to meet and pack the right things. CampBuddy has the schedule, reminders and a packing list, free and without a sign-up.'],
                    ['body' => 'Time to plan: save the talks you want, see who shares your interests and get a reminder before each session.'],
                    ['body' => 'Make your plan now and enjoy the day: the schedule, reminders and a packing list are all in CampBuddy.'],
                ]),
            ],
            [
                'key' => 'schedule',
                'label' => 'Schedule in CampBuddy',
                'kicker' => 'Now in CampBuddy',
                'headline' => $pick('sch-head', ['The full schedule, in your pocket', 'Every talk, one tap away', "Plan your {$short} in minutes"]),
                'line' => trim(($sessions ? "{$sessions} sessions" : '').($sessions && $speakers ? ' · ' : '').($speakers ? "{$speakers} speakers" : '')) ?: $when,
                'body' => 'points',
                'points' => array_values(array_filter([
                    $sessions ? "{$sessions} sessions".($dayCount > 1 ? " over {$dayCount} days" : '') : null,
                    $speakers ? "{$speakers} ".($speakers === 1 ? 'speaker' : 'speakers').' on stage' : null,
                    'Save a talk, get a reminder before it',
                    'Your own day, in one tap',
                ])),
                'parts' => [
                    'hook' => "The {$name} schedule is in CampBuddy".($sessions ? ': '.$count($sessions, 'session', 'sessions').($speakers ? ' and '.$count($speakers, 'speaker', 'speakers') : '') : '').'.',
                    'cta' => 'See the full schedule',
                ] + $pick('sch-caption', [
                    ['body' => 'Save the talks you like and get a reminder before each one, even offline.'],
                    ['body' => 'Tap a talk to save it, and CampBuddy reminds you before it starts. Works offline too.'],
                    ['body' => 'Build your own day in a minute and never miss the talk you came for.'],
                ]),
            ],
            [
                'key' => 'app',
                'label' => 'Get CampBuddy',
                'kicker' => $pick('app-kicker', ['Get CampBuddy', 'Your WordCamp app', 'One link, the whole day']),
                'headline' => $pick('app-head', ['Your WordCamp companion', "Your {$short} companion", 'One app for your whole WordCamp']),
                'line' => $pick('app-line', ["For everyone at {$short}", 'Schedule, people and reminders', "Everything for {$short}, in one place"]),
                'body' => 'features',
                'parts' => ['cta' => "Get CampBuddy for {$short}", 'xbody' => 'Schedule, reminders, people to meet and deals. Free, no sign-up.'] + $pick('app-caption', [
                    ['hook' => "Going to {$name}? Keep the whole day in one link.", 'body' => 'CampBuddy puts the schedule, the people you want to meet, Contributor Day tips and deals in one place. Free, no sign-up, works offline.'],
                    ['hook' => "One link for all of {$name}.", 'body' => "Your schedule with reminders, people who share your interests, your Contributor Day team and deals. That's CampBuddy, free and without a sign-up."],
                    ['hook' => "Make the most of {$name} with CampBuddy.", 'body' => 'Save talks, get reminders, find people to meet and pick a Contributor Day table. Free, nothing to install.'],
                ]),
            ],
            [
                'key' => 'speakers',
                'label' => 'Speakers',
                'kicker' => 'Speakers',
                'headline' => $pick('spk-head', ["Meet the {$short} speakers", "The voices of {$short}", "On stage at {$short}"]),
                'line' => $pick('spk-line', ['Their talks are in CampBuddy', 'Save their talks in CampBuddy', 'Get a reminder before each talk']),
                'body' => 'photos',
                'parts' => [
                    'hook' => "Meet the speakers of {$name}".($speakers ? ': '.$count($speakers, 'person sharing what they know', 'people sharing what they know') : '').'.',
                    'extra' => $speakerNames !== null ? "With {$speakerNames}." : null,
                    'cta' => 'Save their talks in CampBuddy',
                ] + $pick('spk-caption', [
                    ['body' => 'Find every talk, save the ones you want and get a reminder before it starts.'],
                    ['body' => "See who is talking about what, and save the talks you don't want to miss."],
                    ['body' => 'Their talks are in CampBuddy: save yours and get a nudge before they start.'],
                ]),
            ],
            [
                'key' => 'sponsors',
                'label' => 'Sponsors',
                'kicker' => $pick('spo-kicker', ['Say hi at their booths', 'Sponsors', 'Thank you, sponsors']),
                'headline' => $pick('spo-head', ['The sponsors who make it happen', "The sponsors behind {$short}", 'Thanks to these sponsors']),
                'line' => 'Their deals are in CampBuddy',
                'body' => 'logos',
                'parts' => [
                    'extra' => $sponsorNames !== null ? "Thank you, {$sponsorNames}." : null,
                    'cta' => 'See their attendee deals in CampBuddy',
                ] + $pick('spo-caption', [
                    ['hook' => "These sponsors help keep {$name} affordable.", 'body' => 'Stop by their booths to say thanks, and find the deals they offer attendees in CampBuddy.'],
                    ['hook' => "{$name} happens with help from these sponsors.", 'body' => 'Visit their booths and see their attendee deals in CampBuddy.'],
                    ['hook' => "Say thanks to the sponsors of {$name}.", 'body' => 'Drop by their booths, and check CampBuddy for the deals they offer attendees.'],
                ]),
            ],
            [
                'key' => 'today',
                'label' => 'Day schedule',
                'picker' => 'day',
                'kicker' => 'On the schedule',
                'headline' => $pick('day-head', ["Today at {$short}", "Your day at {$short}", "What's on at {$short}"]),
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
                'headline' => $pick('con-head', ['Find your Contributor Day table', 'Give back to WordPress', 'Pick your Contributor Day team']),
                'line' => $pick('con-line', ['CampBuddy matches you with a team', 'No coding needed, every table welcomes you', 'Three questions, one team for you']),
                'body' => 'tables',
                'parts' => ['cta' => 'Find your table in CampBuddy'] + $pick('con-caption', [
                    ['hook' => "Not sure which Contributor Day table to join at {$name}?", 'body' => 'Answer three quick questions in CampBuddy and it suggests a team for you. No coding needed, every table welcomes beginners.'],
                    ['hook' => "Contributor Day at {$name} has a place for you.", 'body' => 'Translators, designers, writers, testers and developers all have a table. CampBuddy asks three questions and points you to yours.'],
                    ['hook' => "Your first contribution to WordPress can happen at {$name}.", 'body' => 'On Contributor Day, let CampBuddy suggest a team that fits you. No coding needed.'],
                ]),
            ],
            [
                'key' => 'meet',
                'label' => 'Find your people',
                'kicker' => 'Don\'t go alone',
                'headline' => $pick('meet-head', ['Find people to meet', 'Meet your people', 'Come for the talks, stay for the people']),
                'line' => 'CampBuddy shows who shares your interests',
                'body' => 'points',
                'points' => ['See who shares your interests', 'Say hi with one tap', 'Swap Camp Cards with a QR', 'Your Contributor Day table'],
                'parts' => ['cta' => 'Find your people in CampBuddy'] + $pick('meet-caption', [
                    ['hook' => "First WordCamp, or don't know anyone at {$name}?", 'body' => 'CampBuddy shows attendees who share your interests, and gives you an easy way to say hi.'],
                    ['hook' => "The best part of {$name} is the people.", 'body' => 'CampBuddy shows who shares your interests, so the first hello is easy. When you meet, swap Camp Cards with a QR.'],
                    ['hook' => "Going to {$name} alone? You won't be for long.", 'body' => 'CampBuddy shows attendees with the same interests and helps you say hi.'],
                ]),
            ],
            [
                'key' => 'thank-you',
                'label' => 'Thank you',
                'kicker' => 'That\'s a wrap',
                'headline' => $pick('ty-head', ["Thank you, {$short}!", "That's a wrap, {$short}!", "Thanks for a great {$short}"]),
                'line' => 'Your day is saved in CampBuddy',
                'body' => 'points',
                'points' => ['Your talks and notes stay in the app', 'The people you met, all in one place', 'Save your whole day as a PDF', 'See you at the next WordCamp'],
                'parts' => [
                    'opening' => $opening,
                    'hook' => "Thank you for using CampBuddy at {$name}!",
                    'xbody' => 'See you at the next WordCamp.',
                    'cta' => 'Open your day in CampBuddy',
                ] + $pick('ty-caption', [
                    ['body' => "Your sessions, notes and the people you met stay in the app, and you can save it all as a PDF.\n\nThank you to the organizers, volunteers, speakers and sponsors who made it happen. See you at the next WordCamp."],
                    ['body' => "Everything from your day stays in the app: talks, notes and new friends. Save it as a PDF to keep.\n\nA big thank you to everyone who made {$short} happen. See you at the next WordCamp."],
                    ['body' => "Your notes and the people you met are still in the app, ready to save as a PDF.\n\nThanks to the organizers, volunteers, speakers and sponsors. See you at the next WordCamp."],
                ]),
            ],
        ];

        // Fifteen posts in the order an event is promoted (22 Oct 2026): announce, build up, the last
        // week, the days themselves and after. Each says when it works best (shown in the studio and
        // in the posting plan that comes with "Download all").
        $posts = array_merge($posts, [
            [
                'key' => 'venue',
                'label' => 'Venue',
                'kicker' => $pick('ven-kicker', ['Getting there', 'The venue', 'Where it happens']),
                'headline' => $place !== '' ? $pick('ven-head', ["See you at {$placeShort}", "{$short} happens here", "Your way to {$short}"]) : "Where {$short} happens",
                'line' => $when,
                'body' => 'points',
                'points' => array_values(array_filter([
                    $place !== '' ? $place : null,
                    $dates !== '' ? $dates : null,
                    'Directions, Wi-Fi and food in CampBuddy',
                ])),
                'parts' => ['cta' => 'Get the venue details in CampBuddy'] + $pick('ven-caption', [
                    ['hook' => "{$name} happens{$at} on {$dates}.", 'body' => 'Directions, Wi-Fi, food and everything you need on the day are in CampBuddy, free and without a sign-up.'],
                    ['hook' => "Where is {$name}?".($place !== '' ? " Right here: {$place}." : ''), 'body' => 'Plan your way in, and find the Wi-Fi, food and help desk in CampBuddy.'],
                    ['hook' => "Mark the place: {$name}{$at}, {$dates}.", 'body' => 'CampBuddy has the venue details, the schedule and the people to meet, all on your phone.'],
                ]),
            ],
            [
                'key' => 'first-timer',
                'label' => 'First WordCamp?',
                'kicker' => $pick('ft-kicker', ['First time?', 'New to WordCamp?', 'Pehla WordCamp?']),
                'headline' => $pick('ft-head', ['Your first WordCamp? *Start here*', "First time at {$short}? *You'll love it*", 'New to WordCamp? *Read this first*']),
                'line' => 'A 5-minute guide in CampBuddy',
                'body' => 'points',
                'points' => ['What happens on the day', "Words you'll hear", 'What to bring', 'How to meet people'],
                'parts' => [
                    'cta' => 'Read the 5-minute guide',
                    'link' => route('event.guide', $event).'?'.http_build_query(['utm_source' => 'campbuddy', 'utm_medium' => 'social', 'utm_campaign' => $event->slug, 'utm_content' => 'first-timer']),
                ] + $pick('ft-caption', [
                    ['hook' => "Going to your first WordCamp? {$name} is for you too.", 'body' => "CampBuddy's 5-minute guide explains the day, the words you'll hear, what to bring and how to meet people."],
                    ['hook' => 'First WordCamp? Everyone has one.', 'body' => "Read the CampBuddy guide before {$name}: how the day runs, what to bring and how to say hi."],
                    ['hook' => 'Never been to a WordCamp? Start here.', 'body' => "Read CampBuddy's short guide for {$name}, then save your talks and find people with the same interests."],
                ]),
            ],
            [
                'key' => 'wpsimplified',
                'label' => 'Learn with WPSimplified',
                'kicker' => $pick('wps-kicker', ['Get WordCamp-ready', 'Before you go', 'Learn first']),
                'headline' => $pick('wps-head', ["Brush up on WordPress before *{$short}*", 'Learn WordPress, then *meet the people* behind it', "Come to {$short} *ready to learn*"]),
                'line' => 'Free tutorials by WPSimplified, in Hindi and English',
                'body' => 'points',
                'points' => ['Step-by-step video tutorials', 'Podcasts with the community', 'Shorts: one tip in a minute'],
                'parts' => ['cta' => 'Plan your day in CampBuddy', 'xbody' => 'Free tutorials in Hindi and English: https://wpsimplified.in'] + $pick('wps-caption', [
                    ['hook' => "Get ready for {$name} with free WordPress tutorials.", 'body' => "WPSimplified makes free tutorials, podcasts and shorts in Hindi and English: https://wpsimplified.in\n\nThen plan your day in CampBuddy, made by WPSimplified for the WordPress community."],
                    ['hook' => "New to WordPress, or want to go deeper before {$name}?", 'body' => "WPSimplified has free step-by-step tutorials in Hindi and English: https://wpsimplified.in\n\nAnd CampBuddy, also by WPSimplified, keeps your WordCamp day in one place."],
                    ['hook' => "Learn a little WordPress before {$name}.", 'body' => "WPSimplified's free videos, podcasts and shorts make it simple: https://wpsimplified.in\n\nSee you there, with CampBuddy on your phone."],
                ]),
            ],
            [
                'key' => 'moments',
                'label' => 'Share your moments',
                'kicker' => $hashtag !== '' ? $hashtag : 'Share it',
                'headline' => $pick('mom-head', ["Share your *{$short}* moments", 'Your photos make the day *last*', "Post it, tag it, *keep it*"]),
                'line' => ($hashtag !== '' ? "Tag {$hashtag} · " : '').'Your day stays in CampBuddy Memories',
                'body' => 'points',
                'points' => array_values(array_filter([
                    $hashtag !== '' ? "Post with {$hashtag}" : 'Post your photos',
                    'Add #CampBuddy and #WPSimplified',
                    'Save your day in CampBuddy Memories',
                ])),
                'parts' => ['cta' => 'Open your Memories in CampBuddy'] + $pick('mom-caption', [
                    ['hook' => "At {$name}? Share your favourite moments".($hashtag !== '' ? " with {$hashtag}" : '').'.', 'body' => 'Your talks, notes and the people you met stay in CampBuddy Memories.'],
                    ['hook' => "Talks, hallway chats, selfies with new friends: post your {$name} moments".($hashtag !== '' ? " with {$hashtag}" : '').'.', 'body' => 'CampBuddy keeps your day in Memories.'],
                    ['hook' => "Make {$name} last: share your photos".($hashtag !== '' ? " with {$hashtag}" : '').'.', 'body' => 'Keep your day, with the people you met, in CampBuddy.'],
                ]),
            ],
        ]);
        $plan = [
            'save-the-date' => '6+ weeks before',
            'venue' => '5 weeks before',
            'speakers' => '4 weeks before',
            'sponsors' => '3–4 weeks before',
            'schedule' => '3 weeks before',
            'first-timer' => '2–3 weeks before',
            'contributor' => '2 weeks before',
            'wpsimplified' => '2 weeks before',
            'app' => '10 days before',
            'meet' => '1 week before',
            'countdown' => '7, 3 and 1 day before',
            'today' => 'Each morning of the event',
            'spotlight' => '30 minutes before a talk',
            'moments' => 'During the event',
            'thank-you' => 'The day after',
        ];
        // …and the tentative day for it (SocialPlan), counted back from the event's first day.
        $dates = SocialPlan::dates($event, $now);
        $posts = collect($posts)
            ->sortBy(fn ($post) => array_search($post['key'], array_keys($plan), true))
            ->map(fn ($post) => $post + [
                'when' => $plan[$post['key']] ?? null,
                'date' => $dates[$post['key']]['date'] ?? null,
                'date_label' => $dates[$post['key']]['label'] ?? null,
            ])
            ->values()->all();

        // One caption per network (SocialCaptions), each post with its own tagged link; `caption` is the
        // LinkedIn one, the fullest, for what takes a single caption (the native app, Publish).
        // The day list and the spotlight write theirs in the browser, from the day or session picked.
        $tags = self::hashtags($event);
        $posts = array_map(function (array $post) use ($link, $tags) {
            $parts = $post['parts'] ?? null;
            unset($post['parts']);
            $post['captions'] = $parts !== null ? SocialCaptions::make($parts + ['link' => $link($post['key'])], $tags) : ['instagram' => '', 'linkedin' => '', 'x' => ''];
            $post['caption'] = $post['captions']['linkedin'];

            return $post;
        }, $posts);

        // A day list or a spotlight needs a schedule with times: without one they'd be empty cards.
        return array_values(array_filter($posts, fn ($post) => $timed || ! in_array($post['key'], ['today', 'spotlight'], true)));
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

        if (! str_contains($url, 'gravatar.com/')) {
            return $url;
        }
        $url = (string) preg_replace('/([?&])s=\d+/', '$1s='.$size, $url);

        // No picture of their own: Gravatar answers 404 instead of its grey silhouette, and the
        // card draws their initials in the brand colours.
        return str_contains($url, 'd=') ? (string) preg_replace('/([?&])d=[^&]*/', '$1d=404', $url) : $url.(str_contains($url, '?') ? '&' : '?').'d=404';
    }

    /** @return list<array{name: string, photo: ?string}> the speakers for the lineup post */
    public static function speakers(Event $event): array
    {
        return collect(EventData::get($event->id, 'speakers') ?? [])
            ->map(fn ($p) => ['name' => (string) ($p['name'] ?? ''), 'photo' => self::bigPhoto($p['avatar_url'] ?? null, 300)])
            ->filter(fn ($p) => $p['name'] !== '')
            // The same person listed twice: one face.
            ->unique(fn ($p) => mb_strtolower(trim($p['name'])))
            ->values()->all();
    }

    /** @return list<array{name: string, logo: ?string, tier: ?string}> */
    public static function sponsors(Event $event): array
    {
        return collect(EventData::get($event->id, 'sponsors') ?? [])
            ->map(fn ($s) => ['name' => (string) ($s['name'] ?? ''), 'logo' => $s['logo_url'] ?? null, 'tier' => $s['tier_names'][0] ?? null])
            ->filter(fn ($s) => $s['name'] !== '')
            ->unique(fn ($s) => mb_strtolower(trim($s['name'])))
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
        $columns = \Illuminate\Support\Facades\Schema::hasColumn('attendee_roster', 'photo_url') ? ['id', 'name', 'gravatar_url', 'photo_url'] : ['id', 'name', 'gravatar_url'];
        $entries = $event->attendeeRoster()->where('is_suppressed', false)->orderBy('name')->get($columns);

        $cards = [];
        try {
            $cards = \App\Models\SharedCampCard::where('event_id', $event->id)->alive($event)->pluck('fields', 'attendee_roster_id')->all();
        } catch (\Throwable) {
            // Before the migration: roles only.
        }

        $tags = self::hashtags($event);
        $people = [];
        // One person, one card: two list entries for the same person (two
        // tickets) are told apart by their Gravatar, else their name, and
        // their roles are put together.
        $seen = [];
        foreach ($entries as $entry) {
            $personRoles = $roles[$entry->id]['roles'] ?? [];
            $card = $cards[$entry->id] ?? null;
            if ($personRoles === [] && ! $card) {
                continue;
            }
            $key = $entry->gravatar_url ? (string) preg_replace('/\?.*$/', '', $entry->gravatar_url) : mb_strtolower(trim($entry->name));
            if (isset($seen[$key])) {
                $i = $seen[$key];
                $people[$i]['roles'] = array_values(array_unique([...$people[$i]['roles'], ...$personRoles]));
                $people[$i]['role_labels'] = array_map(fn ($r) => RosterRoles::LABELS[$r] ?? $r, $people[$i]['roles']);
                $people[$i]['talk'] ??= $roles[$entry->id]['talks'][0] ?? null;
                $people[$i]['card'] ??= $card;

                continue;
            }
            $seen[$key] = count($people);

            $labels = array_map(fn ($r) => RosterRoles::LABELS[$r] ?? $r, $personRoles);
            $talk = $roles[$entry->id]['talks'][0] ?? null;

            $role = $personRoles[0] ?? 'camp-card';
            $link = self::link($event, 'people-'.str_replace('_', '-', $role));
            $people[] = [
                'id' => $entry->id,
                'name' => $entry->name,
                // The best photo found for them (their site photo, a real Gravatar, their WordPress.org picture).
                'photo' => self::bigPhoto($entry->photo()),
                'roles' => $personRoles,
                'role_labels' => $labels,
                'talk' => $talk,
                'card' => $card,
                'link' => $link,
            ];
        }

        // Their captions once their roles are all in (one person, two tickets: both roles).
        // `caption` is the LinkedIn one, for what takes a single caption (the native app).
        foreach ($people as $i => $person) {
            $people[$i]['captions'] = SocialCaptions::make(self::personParts($event, $person) + ['link' => $person['link']], $tags);
            $people[$i]['caption'] = $people[$i]['captions']['linkedin'];
        }

        return $people;
    }

    /**
     * What a person's caption says, by their first role: a speaker's talk, an
     * organizer's or volunteer's thanks, a table lead's table… Always their
     * whole name; nothing about them beyond their public name, role and talk.
     *
     * @param  array<string, mixed>  $person
     * @return array<string, mixed>
     */
    private static function personParts(Event $event, array $person): array
    {
        $name = trim((string) $person['name']);
        $at = $event->display_name;
        $talk = trim((string) ($person['talk'] ?? ''));

        return match ($person['roles'][0] ?? 'camp-card') {
            'speaker' => $talk !== ''
                ? ['hook' => "{$name} is speaking at {$at}.", 'xhook' => "{$name} is speaking at {$at}: \"{$talk}\"", 'body' => "Talk: \"{$talk}\"", 'xbody' => 'Get a reminder before it starts.', 'cta' => 'Save the talk in CampBuddy']
                : ['hook' => "{$name} is speaking at {$at}.", 'body' => 'Find their talk in the schedule and get a reminder before it starts.', 'cta' => 'Save the talk in CampBuddy'],
            'organizer' => ['hook' => "Meet {$name}, one of the organizers of {$at}.", 'body' => 'Organizers are the people who make a WordCamp happen. Say thanks when you see them!', 'cta' => 'Find everyone worth meeting in CampBuddy'],
            'volunteer' => ['hook' => "Meet {$name}, volunteering at {$at}.", 'body' => 'Volunteers keep the day running. Say thanks when you see them!', 'cta' => 'Find everyone worth meeting in CampBuddy'],
            'sponsor' => ['hook' => "Meet {$name} from the sponsors of {$at}.", 'body' => 'Say hi when you see them at the event.', 'cta' => "See the sponsors' attendee deals in CampBuddy"],
            'microsponsor' => ['hook' => "{$name} is a microsponsor of {$at}.", 'body' => 'Thank you for supporting your WordPress community!', 'cta' => 'Find everyone worth meeting in CampBuddy'],
            'media_partner' => ['hook' => "Meet {$name}, media partner of {$at}.", 'body' => 'Follow their coverage of the event, and say hi when you see them.', 'cta' => 'Find everyone worth meeting in CampBuddy'],
            'table_lead' => ['hook' => "Meet {$name}, a Contributor Day table lead at {$at}.", 'body' => 'Join their table and give back to WordPress. Many tables need no coding at all.', 'cta' => 'Pick your table in CampBuddy'],
            default => ['hook' => "Say hi to {$name} at {$at}!", 'body' => 'CampBuddy shows who shares your interests, so the first hello is easy.', 'cta' => 'Find them in CampBuddy'],
        };
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
                // The countdown counts from these in the browser, so a card drawn tomorrow says one day less.
                'starts' => $event->starts_on?->format('Y-m-d'),
                'timezone' => EventTime::zone($event)->getName(),
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
            // The WordCamp site's own colours, fonts and artwork (read from its home page).
            'site' => SiteTheme::get($event),
        ];
    }
}
