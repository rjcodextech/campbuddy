<?php

namespace App\Support;

/**
 * "WordCamp 101" — everything a first-time attendee wishes someone had told
 * them: what a WordCamp is, how the day usually runs, the words everyone
 * uses without explaining, etiquette, what to bring, and the questions
 * newcomers are too shy to ask.
 *
 * Generic on purpose: true of every WordCamp. What's specific to one event
 * (its venue, times, wifi) comes from that event's own data, shown beside
 * this on the event's Guide page.
 */
class FirstTimerGuide
{
    /**
     * The shape of a typical WordCamp day, in order. `match` holds words
     * that identify the same moment in a real schedule, so the event's Guide
     * page can say "at this WordCamp: 9:00, Main Hall" (guide.js).
     *
     * @return array<int, array{icon: string, title: string, what: string, tip: string, match: array<int, string>, talk?: bool}>
     */
    public static function day(): array
    {
        return [
            [
                'icon' => '🎫',
                'title' => 'Registration & badge',
                'what' => 'Show your ticket at the front desk and collect your name badge — often with a lanyard and some swag (free goodies).',
                'tip' => 'Arrive 15–20 minutes early. The queue is shortest then, and the breakfast area is a relaxed place for a first hello.',
                'match' => ['registration', 'check-in', 'check in', 'breakfast'],
            ],
            [
                'icon' => '📣',
                'title' => 'Opening remarks',
                'what' => 'The organizers welcome everyone and share housekeeping: where the rooms are, where lunch is, and who to ask for help.',
                'tip' => 'Worth catching — this is where you learn the wifi, the hashtag and any schedule changes.',
                'match' => ['opening', 'welcome'],
            ],
            [
                'icon' => '🎤',
                'title' => 'Keynote',
                'what' => 'A talk for everyone at once, in the biggest room, usually by a well-known community member.',
                'tip' => 'Everyone is in one room, so it\'s the easiest moment to sit next to someone new.',
                'match' => ['keynote'],
                'talk' => true,
            ],
            [
                'icon' => '🗂️',
                'title' => 'Sessions in tracks',
                'what' => 'Talks and workshops run in parallel in different rooms — each room is a "track". You choose which one to attend.',
                'tip' => 'Look for sessions marked beginner-friendly. It\'s completely fine to walk out and switch rooms if a talk isn\'t for you.',
                'match' => [],
            ],
            [
                'icon' => '☕',
                'title' => 'Breaks — the "hallway track"',
                'what' => 'Short breaks between sessions. Many regulars say the conversations in the hallway are the best part of WordCamp.',
                'tip' => 'Join a small group and ask "What\'s been your favourite session so far?" — it works every time.',
                'match' => ['break', 'coffee', 'tea', 'snack'],
            ],
            [
                'icon' => '🍽️',
                'title' => 'Lunch',
                'what' => 'Lunch is usually included in your ticket. Seating is informal.',
                'tip' => 'Sit at a table with people you don\'t know. "Mind if I join you?" is all it takes.',
                'match' => ['lunch'],
            ],
            [
                'icon' => '🏷️',
                'title' => 'Sponsor booths',
                'what' => 'Sponsors are the companies that keep tickets affordable. Their booths are open all day.',
                'tip' => 'You don\'t have to buy anything. Ask what they build — many have swag, contests and job openings.',
                'match' => ['sponsor', 'expo'],
            ],
            [
                'icon' => '⚡',
                'title' => 'Lightning talks',
                'what' => 'A run of very short talks (often five minutes each), frequently by first-time speakers.',
                'tip' => 'A great, low-stakes way to hear lots of ideas — and to spot people you\'d like to talk to afterwards.',
                'match' => ['lightning'],
                'talk' => true,
            ],
            [
                'icon' => '📸',
                'title' => 'Closing & group photo',
                'what' => 'Thank-yous to volunteers, organizers and sponsors, then everyone squeezes into one big photo.',
                'tip' => 'Stay for the photo! It\'s a WordCamp tradition, and you\'ll be glad you\'re in it.',
                'match' => ['closing', 'group photo', 'wrap'],
            ],
            [
                'icon' => '🎉',
                'title' => 'After-party / social',
                'what' => 'An informal evening get-together for attendees, speakers and organizers.',
                'tip' => 'Wear your badge. Speakers are much easier to chat with here than straight after their talk.',
                'match' => ['party', 'social', 'networking', 'after party', 'after-party'],
            ],
            [
                'icon' => '🛠️',
                'title' => 'Contributor Day',
                'what' => 'Often a separate day where attendees help build WordPress itself — code, docs, translation, design, support, marketing and more.',
                'tip' => 'No coding needed. Many events ask you to register for it separately, and to bring a laptop.',
                'match' => ['contributor'],
                'talk' => true,
            ],
        ];
    }

    /**
     * What to actually DO while a schedule item is happening — Home turns
     * "Lunch" into "Lunch — sit with people you haven't met" (spec H5: a
     * schedule fact is never shown without what to do about it). Matched
     * against session titles (whole words) in order; first hit wins. `key`
     * marks the event-wide moments Home's Up Next ranks above ordinary
     * sessions (H2). `talk` says the moment can also be a real talk: a
     * keynote is a session, but a talk titled "Don't Break Your Site" is not
     * a coffee break — so moments without it only match schedule items that
     * aren't talks (WordCamp's "custom" session type).
     *
     * @return array<int, array{match: array<int, string>, now: string, key: bool, talk: bool}>
     */
    public static function moments(): array
    {
        return [
            ['match' => ['registration', 'check-in', 'check in'], 'now' => 'Collect your badge at the front desk — then say hi to someone standing on their own.', 'key' => true, 'talk' => false],
            ['match' => ['breakfast'], 'now' => 'Grab a coffee and join a small group — breakfast is the easiest time to meet people.', 'key' => false, 'talk' => false],
            ['match' => ['opening', 'welcome'], 'now' => 'Find a seat in the main room — this is where you hear the wifi, the hashtag and any changes.', 'key' => true, 'talk' => false],
            ['match' => ['keynote'], 'now' => "Everyone's in one room — sit next to someone new and say hello before it starts.", 'key' => true, 'talk' => true],
            ['match' => ['lunch'], 'now' => "Sit at a table with people you haven't met. \"Mind if I join you?\" is all it takes.", 'key' => true, 'talk' => false],
            ['match' => ['break', 'coffee', 'tea', 'snack'], 'now' => 'Hallway-track time: join a conversation, refill your water, or visit a sponsor booth.', 'key' => false, 'talk' => false],
            ['match' => ['lightning'], 'now' => 'Short talks, lots of ideas — easy to drop in, and a great way to spot people to chat with later.', 'key' => false, 'talk' => true],
            ['match' => ['closing', 'group photo', 'wrap'], 'now' => "Head to the main room — and don't miss the group photo!", 'key' => true, 'talk' => false],
            ['match' => ['party', 'social', 'networking'], 'now' => 'Bring your badge and your Camp Card — speakers are easiest to chat with here.', 'key' => true, 'talk' => false],
            ['match' => ['contributor'], 'now' => 'Pick a team table and say "It\'s my first Contributor Day" — every table welcomes beginners.', 'key' => true, 'talk' => true],
            ['match' => ['workshop', 'hands-on', 'hands on'], 'now' => "Bring your laptop and sit near the front so it's easy to ask for help.", 'key' => false, 'talk' => true],
        ];
    }

    /**
     * Home's fallback lines when no moment matches: one session vs a choice
     * between several tracks.
     *
     * @return array{single: string, several: string}
     */
    public static function sessionNow(): array
    {
        return [
            'single' => "Slip in quietly if it's already started — there's usually room at the back.",
            'several' => "Pick whichever sounds most useful. It's completely fine to switch rooms if it isn't for you.",
        ];
    }

    /**
     * @return array<int, array{term: string, meaning: string}>
     */
    public static function glossary(): array
    {
        return [
            ['term' => 'WordCamp', 'meaning' => 'A conference about WordPress, organized by local volunteers from the WordPress community. There are WordCamps all over the world.'],
            ['term' => 'Track', 'meaning' => 'A room (or theme) where sessions run one after another. Several tracks run at the same time, so you pick one per time slot.'],
            ['term' => 'Session / talk', 'meaning' => 'A presentation by a speaker, usually 20–45 minutes, often followed by questions.'],
            ['term' => 'Workshop', 'meaning' => 'A hands-on session where you follow along, often on your own laptop.'],
            ['term' => 'Keynote', 'meaning' => 'The main talk that everyone attends together, usually at the start or end of the day.'],
            ['term' => 'Lightning talk', 'meaning' => 'A very short talk, typically five minutes, delivered back-to-back with others.'],
            ['term' => 'Hallway track', 'meaning' => 'The conversations that happen outside the session rooms. Not on the schedule — but for many, the best part.'],
            ['term' => 'Swag', 'meaning' => 'Free goodies: stickers, T-shirts, pins and more, from the event and its sponsors.'],
            ['term' => 'Sponsor', 'meaning' => 'A company that helps fund the event so tickets stay affordable. Sponsors usually have booths you can visit.'],
            ['term' => 'Organizers & volunteers', 'meaning' => 'The community members who make the event happen — unpaid. Look for their special T-shirts or badges if you need help.'],
            ['term' => 'Contributor Day', 'meaning' => 'A day of helping improve WordPress with the "Make WordPress" teams. Beginners are welcome at every table.'],
            ['term' => 'Make teams', 'meaning' => 'The groups that build WordPress: Core, Design, Docs, Polyglots (translation), Support, Training, Marketing, Accessibility and more.'],
            ['term' => 'WordPress.org account', 'meaning' => 'A free account on WordPress.org. You\'ll need one to contribute — create it before Contributor Day to save time.'],
            ['term' => 'Make WordPress Slack', 'meaning' => 'The chat workspace where the contributor teams talk. Joining is free, via make.wordpress.org/chat.'],
            ['term' => 'Core', 'meaning' => 'The WordPress software itself, as opposed to plugins and themes built on top of it.'],
            ['term' => 'Block editor (Gutenberg)', 'meaning' => 'The editor you use to build pages and posts out of "blocks". Gutenberg is the name of the project behind it.'],
            ['term' => 'Meetup', 'meaning' => 'A smaller, regular local WordPress gathering. Great for staying in touch with people you meet at WordCamp.'],
            ['term' => 'Wapuu', 'meaning' => 'The cute, unofficial WordPress mascot. Many WordCamps make their own version — look for it on stickers and swag.'],
            ['term' => 'Code of Conduct', 'meaning' => 'The rules everyone agrees to so the event is welcoming and safe for all. Organizers enforce it — reach out to them if anything feels wrong.'],
            ['term' => 'WordPress.tv', 'meaning' => 'Where many WordCamp talks are published after the event, so you can catch sessions you missed.'],
        ];
    }

    /**
     * @return array<int, array{icon: string, title: string, text: string}>
     */
    public static function tips(): array
    {
        return [
            ['icon' => '🙋', 'title' => 'Everyone was new once', 'text' => 'At most WordCamps, lots of people are first-timers too. Saying "It\'s my first WordCamp" is a great conversation starter — people love to help.'],
            ['icon' => '🗣️', 'title' => 'Have a 15-second intro ready', 'text' => 'Your name, what you do with WordPress (or want to), and one thing you\'re curious about today. That\'s it.'],
            ['icon' => '🚶', 'title' => 'Use the "law of two feet"', 'text' => 'If a session isn\'t right for you, quietly leave and find one that is. Nobody minds — it\'s expected.'],
            ['icon' => '⭐', 'title' => 'Don\'t try to see everything', 'text' => 'Save three or four sessions you really want, and leave room for conversations. Most talks go online later on WordPress.tv.'],
            ['icon' => '❓', 'title' => 'Ask questions', 'text' => 'During Q&A or after the talk. Speakers are community members too, and most are delighted someone wants to know more.'],
            ['icon' => '🔋', 'title' => 'Look after yourself', 'text' => 'Bring water and a charger, take breaks, and step out when you need a quiet moment. It\'s a long, busy day.'],
            ['icon' => '🤝', 'title' => 'Keep in touch', 'text' => 'Swap Camp Cards or LinkedIn with people you click with, and look up your local WordPress meetup to see them again.'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function bring(): array
    {
        return [
            'Your ticket (the QR code on your phone is usually enough)',
            'Phone, charger and a power bank',
            'A laptop and charger — if you\'re joining a workshop or Contributor Day',
            'A refillable water bottle',
            'Comfortable shoes — there\'s a lot of walking between rooms',
            'A light jacket or sweater — session rooms can be cold',
            'Your Camp Card instead of business cards',
        ];
    }

    /**
     * @return array<int, array{q: string, a: string}>
     */
    public static function faq(): array
    {
        return [
            ['q' => 'Do I need to be a developer?', 'a' => 'Not at all. WordCamps are for bloggers, business owners, designers, marketers, students, writers and anyone curious about WordPress. Most schedules include beginner-friendly sessions.'],
            ['q' => 'Is it OK to leave a session halfway?', 'a' => 'Yes. Quietly slip out — use the aisle seats if you think you might. Switching rooms is normal at WordCamp.'],
            ['q' => 'What should I wear?', 'a' => 'Whatever you\'re comfortable in. WordCamps are very casual.'],
            ['q' => 'Is food included?', 'a' => 'Usually lunch, snacks and drinks are included in the ticket. Check Explore → Event Info or ask at registration.'],
            ['q' => 'I\'m shy. How do I meet people?', 'a' => 'Try the Quest tab — small, friendly challenges like "Say hello to another first-timer". Lunch tables, coffee breaks and the sponsor area are the easiest places to start a chat.'],
            ['q' => 'Do I need to sign up for Contributor Day separately?', 'a' => 'Often yes — it can have its own registration and a limited number of seats. Check the event\'s website or Explore → Event Info.'],
            ['q' => 'Will the talks be recorded?', 'a' => 'Many are, and are published on WordPress.tv a few weeks later. Don\'t count on every one, though.'],
            ['q' => 'Who do I ask for help at the venue?', 'a' => 'Look for volunteers and organizers — they usually wear special T-shirts or badges — or go to the registration desk. If anything makes you feel unsafe, tell an organizer; that\'s what the Code of Conduct is for.'],
        ];
    }
}
