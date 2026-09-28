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
                'icon' => 'ticket',
                'title' => 'Registration & badge',
                'what' => 'Show your ticket at the front desk and pick up your name badge. There is often a lanyard and some swag (free stickers, T-shirts and the like) too.',
                'tip' => 'Get there 15 to 20 minutes early. The queue is short, and people standing around with a coffee are easy to talk to.',
                'match' => ['registration', 'check-in', 'check in', 'breakfast'],
            ],
            [
                'icon' => 'megaphone',
                'title' => 'Opening remarks',
                'what' => 'The organizers welcome everyone and share housekeeping: where the rooms are, where lunch is, and who to ask for help.',
                'tip' => 'Try not to miss it. This is where you hear the wifi password, the hashtag and any changes to the schedule.',
                'match' => ['opening', 'welcome'],
            ],
            [
                'icon' => 'mic',
                'title' => 'Keynote',
                'what' => 'A talk for everyone at once, in the biggest room, usually by a well-known community member.',
                'tip' => 'The whole event is in one room, so it\'s a nice moment to sit next to someone new.',
                'match' => ['keynote'],
                'talk' => true,
            ],
            [
                'icon' => 'columns',
                'title' => 'Sessions in tracks',
                'what' => 'Talks and workshops run at the same time in different rooms. Each room is called a "track", and you pick which one to go to.',
                'tip' => 'Look for sessions marked beginner-friendly. If a talk isn\'t for you, it\'s fine to walk out and try another room.',
                'match' => [],
            ],
            [
                'icon' => 'coffee',
                'title' => 'Breaks (the "hallway track")',
                'what' => 'Short breaks between sessions. Ask a regular and they\'ll often say the hallway chats are the best part of the day.',
                'tip' => 'Walk up to a small group and ask "Which session have you liked most so far?" People are happy to answer.',
                'match' => ['break', 'coffee', 'tea', 'snack'],
            ],
            [
                'icon' => 'utensils',
                'title' => 'Lunch',
                'what' => 'Lunch is usually part of your ticket, and you can sit anywhere.',
                'tip' => 'Pick a table with people you don\'t know and ask "Mind if I join you?" People are usually happy to make room.',
                'match' => ['lunch'],
            ],
            [
                'icon' => 'tag',
                'title' => 'Sponsor booths',
                'what' => 'Sponsors are the companies that pay for a big part of the event, which is why tickets are cheap. Their booths are open all day.',
                'tip' => 'You don\'t have to buy anything. Just ask what they make. Many have swag, a contest or open jobs.',
                'match' => ['sponsor', 'expo'],
            ],
            [
                'icon' => 'zap',
                'title' => 'Lightning talks',
                'what' => 'A run of very short talks (often five minutes each), frequently by first-time speakers.',
                'tip' => 'You hear a lot of ideas in a short time, and you find out who you want to talk to afterwards.',
                'match' => ['lightning'],
                'talk' => true,
            ],
            [
                'icon' => 'camera',
                'title' => 'Closing & group photo',
                'what' => 'Thank-yous to volunteers, organizers and sponsors, then everyone squeezes into one big photo.',
                'tip' => 'Stay for the photo. It\'s a WordCamp tradition and it\'s nice to be in it.',
                'match' => ['closing', 'group photo', 'wrap'],
            ],
            [
                'icon' => 'party',
                'title' => 'After-party / social',
                'what' => 'An informal evening get-together for attendees, speakers and organizers.',
                'tip' => 'Keep your badge on. Speakers have more time to chat here than right after their talk.',
                'match' => ['party', 'social', 'networking', 'after party', 'after-party'],
            ],
            [
                'icon' => 'wrench',
                'title' => 'Contributor Day',
                'what' => 'Usually a separate day where attendees work on WordPress itself: code, docs, translation, design, support, marketing and more.',
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
            ['match' => ['registration', 'check-in', 'check in'], 'now' => 'Pick up your badge at the front desk, then say hi to someone standing on their own.', 'key' => true, 'talk' => false],
            ['match' => ['breakfast'], 'now' => 'Get a coffee and join a small group. Breakfast is the easiest time to meet people.', 'key' => false, 'talk' => false],
            ['match' => ['opening', 'welcome'], 'now' => 'Find a seat in the main room. The wifi password, the hashtag and any changes get announced here.', 'key' => true, 'talk' => false],
            ['match' => ['keynote'], 'now' => "Everyone's in one room. Sit next to someone new and say hello before it starts.", 'key' => true, 'talk' => true],
            ['match' => ['lunch'], 'now' => "Sit at a table with people you haven't met. Just ask \"Mind if I join you?\"", 'key' => true, 'talk' => false],
            ['match' => ['break', 'coffee', 'tea', 'snack'], 'now' => 'Hallway-track time: join a conversation, refill your water, or visit a sponsor booth.', 'key' => false, 'talk' => false],
            ['match' => ['lightning'], 'now' => 'Short talks, one after another. Easy to drop in, and you\'ll spot people to talk to later.', 'key' => false, 'talk' => true],
            ['match' => ['closing', 'group photo', 'wrap'], 'now' => "Head to the main room, and stay for the group photo.", 'key' => true, 'talk' => false],
            ['match' => ['party', 'social', 'networking'], 'now' => 'Bring your badge and your Camp Card. Speakers have time to chat here.', 'key' => true, 'talk' => false],
            ['match' => ['contributor'], 'now' => 'Pick a team table and say "It\'s my first Contributor Day". Every table takes beginners.', 'key' => true, 'talk' => true],
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
            'single' => "If it has already started, slip in quietly. There's usually room at the back.",
            'several' => "Pick the one that sounds most useful. If it isn't for you, it's fine to switch rooms.",
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
            ['term' => 'Hallway track', 'meaning' => 'The conversations outside the session rooms. It isn\'t on the schedule, but plenty of people come for it.'],
            ['term' => 'Swag', 'meaning' => 'Free goodies: stickers, T-shirts, pins and more, from the event and its sponsors.'],
            ['term' => 'Sponsor', 'meaning' => 'A company that helps fund the event so tickets stay affordable. Sponsors usually have booths you can visit.'],
            ['term' => 'Organizers & volunteers', 'meaning' => 'The community members who run the event, without pay. If you need help, look for their T-shirts or badges.'],
            ['term' => 'Contributor Day', 'meaning' => 'A day of helping improve WordPress with the "Make WordPress" teams. Beginners are welcome at every table.'],
            ['term' => 'Make teams', 'meaning' => 'The groups that build WordPress: Core, Design, Docs, Polyglots (translation), Support, Training, Marketing, Accessibility and more.'],
            ['term' => 'WordPress.org account', 'meaning' => 'A free account on WordPress.org. You need one to contribute, so make it before Contributor Day.'],
            ['term' => 'Make WordPress Slack', 'meaning' => 'The chat workspace where the contributor teams talk. Joining is free, via make.wordpress.org/chat.'],
            ['term' => 'Core', 'meaning' => 'The WordPress software itself, as opposed to plugins and themes built on top of it.'],
            ['term' => 'Block editor (Gutenberg)', 'meaning' => 'The editor you use to build pages and posts out of "blocks". Gutenberg is the name of the project behind it.'],
            ['term' => 'Meetup', 'meaning' => 'A smaller, regular local WordPress gathering. Great for staying in touch with people you meet at WordCamp.'],
            ['term' => 'Wapuu', 'meaning' => 'The WordPress mascot. Many WordCamps draw their own version, so look for it on stickers and swag.'],
            ['term' => 'Code of Conduct', 'meaning' => 'The rules everyone agrees to so the event is safe and welcoming. If something feels wrong, tell an organizer.'],
            ['term' => 'WordPress.tv', 'meaning' => 'Where many WordCamp talks are published after the event, so you can catch sessions you missed.'],
        ];
    }

    /**
     * @return array<int, array{icon: string, title: string, text: string}>
     */
    public static function tips(): array
    {
        return [
            ['icon' => 'hand', 'title' => 'Everyone was new once', 'text' => 'At most WordCamps a big share of the room is new too. "It\'s my first WordCamp" is a perfectly good opening line, and people will help you out.'],
            ['icon' => 'message-circle', 'title' => 'Have a 15-second intro ready', 'text' => 'Your name, what you do with WordPress (or want to do), and one thing you want to learn today.'],
            ['icon' => 'log-out', 'title' => 'Use the "law of two feet"', 'text' => 'If a session isn\'t right for you, leave quietly and find one that is. It\'s completely normal.'],
            ['icon' => 'star', 'title' => 'Don\'t try to see everything', 'text' => 'Save three or four sessions you really want, and leave room for conversations. Most talks go online later on WordPress.tv.'],
            ['icon' => 'help-circle', 'title' => 'Ask questions', 'text' => 'During Q&A or after the talk. Speakers are community members like you, and most are glad someone wants to know more.'],
            ['icon' => 'battery', 'title' => 'Look after yourself', 'text' => 'Bring water and a charger, take breaks, and step out when you need a quiet moment. It\'s a long, busy day.'],
            ['icon' => 'users', 'title' => 'Keep in touch', 'text' => 'Swap Camp Cards or LinkedIn with people you click with, and look up your local WordPress meetup to see them again.'],
        ];
    }

    /**
     * For college students — often the biggest group of first-timers, and the
     * ones with the most to gain: skills, experience, and people who hire.
     *
     * @return array<int, array{icon: string, title: string, text: string}>
     */
    public static function students(): array
    {
        return [
            ['icon' => 'ticket', 'title' => 'Ask about student tickets', 'text' => 'WordCamp tickets are cheap, and many events have a student price or free volunteer spots. Check the event\'s Tickets page, or email the organizers and ask.'],
            ['icon' => 'lightbulb', 'title' => 'Learn what the industry actually uses', 'text' => 'Talks come from people who build sites, plugins and businesses for a living. Look for "Beginner friendly" sessions in My Day. If some of it goes over your head, take one idea home and try it.'],
            ['icon' => 'wrench', 'title' => 'Get real open-source experience', 'text' => 'At Contributor Day you work on WordPress itself: code, design, docs, translation, testing. Your contributions show on your WordPress.org profile, which you can link from your CV and LinkedIn.'],
            ['icon' => 'briefcase', 'title' => 'Meet people who hire', 'text' => 'Sponsors and agencies are often looking for interns and juniors. At their booth, ask: "What do you look for in someone just starting out?" Then show your Camp Card so they can find you later.'],
            ['icon' => 'heart', 'title' => 'Volunteer next time', 'text' => 'WordCamps are run by volunteers. Helping at the registration desk or in a session room is the quickest way to get to know the organizers, and it goes on your CV too.'],
            ['icon' => 'book-open', 'title' => 'Keep learning after', 'text' => 'Learn WordPress (learn.wordpress.org) has free courses and online workshops, and your local WordPress Meetup is where you\'ll see today\'s people again.'],
        ];
    }

    /** A 15-second introduction a student can say out loud. */
    public static function studentIntro(): string
    {
        return '"Hi, I\'m Priya. I study computer science at … and I\'m just getting into WordPress. What are you working on?"';
    }

    /**
     * @return array<int, string>
     */
    public static function bring(): array
    {
        return [
            'Your ticket (the QR code on your phone is usually enough)',
            'Phone, charger and a power bank',
            'A laptop and charger, if you\'re doing a workshop or Contributor Day',
            'A refillable water bottle',
            'Comfortable shoes (you walk between rooms a lot)',
            'A light jacket or sweater, because session rooms can be cold',
            'Your Camp Card instead of business cards',
        ];
    }

    /**
     * "Quick questions" about CampBuddy itself, on the picker — also its
     * FAQPage structured data, so answer engines quote the same words.
     *
     * @return array<int, array{q: string, a: string}>
     */
    public static function quickQuestions(): array
    {
        return [
            ['q' => 'What is CampBuddy?', 'a' => 'A free web app for WordCamp attendees. It shows what\'s on right now, lets you plan your talks, helps you find people to meet, and gives you a Camp Card to swap contacts.'],
            ['q' => 'Is it free?', 'a' => 'Yes. No ads, nothing to pay for.'],
            ['q' => 'Do I need to sign up or download an app?', 'a' => 'No. There\'s no account and nothing to get from an app store. It opens in your phone\'s browser. Tap "Install app" if you want it on your home screen.'],
            ['q' => 'Where does the schedule come from?', 'a' => 'From each WordCamp\'s own website, refreshed through the day. The WordCamp website always has the final word.'],
            ['q' => 'I\'m a student. Is this for me?', 'a' => 'Yes. Pick "Student" when you set up, and read the student tips in the first-timer guide.'],
            ['q' => 'What happens to what I type in?', 'a' => 'It stays on your phone. Only what you choose to share in attendee discovery is shown to other attendees, and you can leave any time.'],
            ['q' => 'Does it work with bad wifi?', 'a' => 'Yes. Pages you\'ve opened keep working offline.'],
        ];
    }

    /**
     * @return array<int, array{q: string, a: string}>
     */
    public static function faq(): array
    {
        return [
            ['q' => 'I\'m a student with no WordPress experience. Will I fit in?', 'a' => 'Yes. Plenty of attendees are students or changing careers, and speakers like questions from newcomers. Start with "Beginner friendly" sessions, and read "For students" above.'],
            ['q' => 'Do I need to be a developer?', 'a' => 'Not at all. WordCamps are for bloggers, business owners, designers, marketers, students, writers and anyone curious about WordPress. Most schedules include beginner-friendly sessions.'],
            ['q' => 'Is it OK to leave a session halfway?', 'a' => 'Yes. Slip out quietly, and sit near the aisle if you think you might. People switch rooms all the time at WordCamp.'],
            ['q' => 'What should I wear?', 'a' => 'Whatever you\'re comfortable in. WordCamps are very casual.'],
            ['q' => 'Is food included?', 'a' => 'Usually lunch, snacks and drinks are included in the ticket. Check Explore → Event Info or ask at registration.'],
            ['q' => 'I\'m shy. How do I meet people?', 'a' => 'Open the Quest tab. It has small tasks like "Say hello to another first-timer". Lunch tables, coffee breaks and the sponsor area are the easiest places to start talking.'],
            ['q' => 'Do I need to sign up for Contributor Day separately?', 'a' => 'Often, yes. It can have its own registration and a limited number of seats. Check the event\'s website or Explore → Event Info.'],
            ['q' => 'Will the talks be recorded?', 'a' => 'Many are, and are published on WordPress.tv a few weeks later. Don\'t count on every one, though.'],
            ['q' => 'Who do I ask for help at the venue?', 'a' => 'Look for volunteers and organizers (they usually wear special T-shirts or badges), or go to the registration desk. If anything makes you feel unsafe, tell an organizer. That is what the Code of Conduct is for.'],
        ];
    }
}
