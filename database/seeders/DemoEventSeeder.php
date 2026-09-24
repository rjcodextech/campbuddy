<?php

namespace Database\Seeders;

use App\Models\AttendeeRoster;
use App\Models\Event;
use App\Models\Offer;
use App\Services\AttendeeRosterScraper;
use App\Services\WordCampNormalizer;
use App\Services\WordCampRestClient;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * A complete, realistic demo WordCamp for local development, design review
 * and walkthrough testing — `php artisan db:seed --class=DemoEventSeeder`.
 *
 * The schedule is written the way a WordCamp site's wp/v2 REST API returns
 * it (title.rendered, meta._wcpt_session_time, taxonomy term ids…) and run
 * through the real WordCampNormalizer, so what the app shows is exactly
 * what a live ingest would produce. Times are relative to now: something is
 * always "happening now" and something is always "up next". Re-running it
 * resets the demo event.
 *
 * Never run this on production: it creates a public, visible event.
 */
class DemoEventSeeder extends Seeder
{
    public const SLUG = 'demo-wordcamp';

    private const SITE = 'https://demo.wordcamp.org/2026';

    public function run(): void
    {
        Event::where('slug', self::SLUG)->get()->each(function (Event $event) {
            foreach (['sessions', 'speakers', 'sponsors', 'organizers'] as $key) {
                Cache::forget("event:{$event->id}:{$key}");
            }
            $event->delete();
        });

        // Built quietly: the observer would otherwise queue fetches of a site
        // that doesn't exist.
        $event = Event::withoutEvents(fn () => Event::create([
            'slug' => self::SLUG,
            'display_name' => 'WordCamp Demo City 2026',
            'short_name' => 'WordCamp',
            'source_site_url' => self::SITE,
            'starts_on' => today(),
            'ends_on' => today()->addDay(),
            'status' => 'active',
            'is_visible' => true,
            'info' => [
                'venue' => 'Demo City Convention Centre, Hall B — 12 Lakeside Road',
                'wifi' => 'Network: WordCamp-Guest · Password: wapuu2026',
                'registration_info' => 'Registration desk opens at 8:30 in the main lobby. Bring your ticket QR code (on your phone is fine) to collect your badge and swag.',
                'contributor_day_location' => 'Day 2 · Level 1 workshop rooms — bring a laptop and charger',
                'social_event_info' => 'After-party from 19:00 at The Courtyard, 5 minutes\' walk from the venue. Your badge gets you in.',
                'emergency_contact' => '+1 555 0100 222',
                'code_of_conduct_url' => self::SITE.'/code-of-conduct/',
                'nearby_venue_info' => 'Cafés and a pharmacy on Lakeside Road; the metro station is across the square.',
                'important_links' => 'Schedule: '.self::SITE."/schedule/\nTickets: ".self::SITE.'/tickets/',
            ],
        ]));
        $event->seedDefaultChecklist();

        $client = new WordCampRestClient(self::SITE);
        $normalizer = new WordCampNormalizer($client);

        $tracks = [11 => 'Main Hall', 12 => 'Workshop Room'];
        $categories = [21 => 'Beginner friendly', 22 => 'Development', 23 => 'Design', 24 => 'Business', 25 => 'Community'];
        $levels = [31 => 'Gold', 32 => 'Silver', 33 => 'Bronze'];

        $ttl = now()->addDays(14);
        Cache::put("event:{$event->id}:sessions", $normalizer->normalizeSessions($this->sessions(), $tracks, $categories), $ttl);
        Cache::put("event:{$event->id}:speakers", $normalizer->normalizeSpeakers($this->speakers()), $ttl);
        Cache::put("event:{$event->id}:sponsors", $normalizer->normalizeSponsors($this->sponsors(), $levels), $ttl);
        Cache::put("event:{$event->id}:organizers", $normalizer->normalizeOrganizers([]), $ttl);

        $this->roster($event);

        Offer::create([
            'event_id' => $event->id,
            'title' => '3 months free hosting',
            'description' => 'For WordCamp attendees — any new site, no card needed.',
            'url' => 'https://example.com/wordcamp-hosting',
            'icon' => '🎁',
            'sort_order' => 0,
            'is_active' => true,
        ]);
        Offer::create([
            'event_id' => $event->id,
            'title' => '40% off the Pro plugin',
            'description' => 'Leave your email and the sponsor sends you the code.',
            'url' => 'https://example.com/pro-plugin',
            'icon' => '🔌',
            'sort_order' => 10,
            'is_active' => true,
            'capture_leads' => true,
        ]);

        $this->command?->info('Demo event ready: /event/'.self::SLUG);
    }

    /**
     * Day one runs from 90 minutes ago; day two is Contributor Day.
     *
     * @return array<int, array<string, mixed>>
     */
    private function sessions(): array
    {
        $start = now()->subMinutes(90)->startOfHour();
        $day2 = today()->addDay()->setTime(9, 0);

        // [minutes from start, duration min, title, type, track ids, category ids, speaker ids, description]
        $day1 = [
            [0, 30, 'Registration &amp; Breakfast', 'custom', [11], [], [], '<p>Collect your badge and swag at the front desk, grab a coffee, and say hi to someone standing alone.</p>'],
            [30, 15, 'Opening Remarks', 'custom', [11], [], [], ''],
            [45, 45, 'Keynote: Twenty Years of Building the Open Web Together', 'session', [11], [25], [101], '<p>How a blogging tool became the software behind 40% of the web — and why the people in this room are the reason.</p>'],
            [100, 40, 'Your First WordPress Block, Step by Step', 'session', [12], [21, 22], [102], '<p>No prior React experience needed. We\'ll build a small, useful block together and ship it as a plugin.</p><script>alert(1)</script>'],
            [100, 40, 'Design Systems That Don&#8217;t Break Your Site', 'session', [11], [23], [103], '<p>Using theme.json and global styles to keep a site consistent as it grows.</p>'],
            [140, 20, 'Coffee Break', 'custom', [], [], [], ''],
            [160, 40, 'SEO Basics Every Site Owner Should Know', 'session', [11], [21, 24], [104], '<p>Plain-language tips you can apply to your site this afternoon — no plugins required.</p>'],
            [160, 40, 'Accessibility Is Everyone&#8217;s Job', 'session', [12], [21, 23], [105, 103], '<p>Small habits that make your content usable by everyone, with live screen-reader demos.</p>'],
            [200, 60, 'Lunch', 'custom', [], [], [], '<p>Lunch is served in the atrium. Vegetarian and vegan options are labelled.</p>'],
            [260, 40, 'From Freelancer to Agency: Lessons Learned', 'session', [11], [24], [106], ''],
            [260, 40, 'Hands-on: Speeding Up a Slow WordPress Site', 'session', [12], [22], [102], '<p>Bring a laptop. We\'ll profile a real site and fix the three most common bottlenecks.</p>'],
            [300, 30, 'Lightning Talks', 'session', [11], [25], [104, 105, 106], '<p>Five-minute talks from first-time speakers. Short, fun, and a great way to find your people.</p>'],
            [330, 20, 'Closing Remarks &amp; Group Photo', 'custom', [11], [], [], ''],
        ];

        $sessions = [];
        $id = 1001;

        foreach ($day1 as [$offset, $minutes, $title, $type, $trackIds, $categoryIds, $speakerIds, $content]) {
            $sessions[] = $this->session($id++, $start->copy()->addMinutes($offset), $minutes, $title, $type, $trackIds, $categoryIds, $speakerIds, $content);
        }

        $sessions[] = $this->session($id++, $day2, 360, 'Contributor Day', 'custom', [], [21, 25], [],
            '<p>Spend the day helping build WordPress with the Make teams — code, docs, translation, design, support and more. Newcomers welcome: every table has a guide.</p>');

        return $sessions;
    }

    private function session(int $id, Carbon $startsAt, int $minutes, string $title, string $type, array $trackIds, array $categoryIds, array $speakerIds, string $content): array
    {
        return [
            'id' => $id,
            'link' => self::SITE.'/session/'.str($title)->slug().'/',
            'title' => ['rendered' => $title],
            'content' => ['rendered' => $content],
            'excerpt' => ['rendered' => ''],
            'session_track' => $trackIds,
            'session_category' => $categoryIds,
            'meta' => [
                '_wcpt_session_time' => $startsAt->getTimestamp(),
                '_wcpt_session_duration' => $minutes * 60,
                '_wcpt_session_type' => $type,
                '_wcpt_speaker_id' => $speakerIds,
                '_wcpt_session_slides' => '',
                '_wcpt_session_video' => '',
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function speakers(): array
    {
        $people = [
            101 => ['Amara Okafor', 'Core contributor and long-time community organizer. Amara has spoken at WordCamps on four continents.'],
            102 => ['Rahul Mehta', 'Plugin developer who loves teaching. Maintains two popular open-source block plugins.'],
            103 => ['Sofia Lindqvist', 'Product designer focused on design systems and accessible interfaces.'],
            104 => ['Diego Ramírez', 'Runs a small marketing agency and writes a weekly newsletter on SEO for small businesses.'],
            105 => ['Hana Suzuki', 'Accessibility specialist and member of the Make WordPress Accessibility team.'],
            106 => ['Tom Becker', 'Went from solo freelancer to a 12-person agency — and made most of the mistakes along the way.'],
        ];

        return collect($people)->map(fn ($p, $id) => [
            'id' => $id,
            'title' => ['rendered' => $p[0]],
            'content' => ['rendered' => "<h2>{$p[0]}</h2><p>{$p[1]}</p><p><a href=\"https://www.linkedin.com/in/demo-{$id}\">LinkedIn</a></p>"],
            'link' => self::SITE.'/speaker/'.str($p[0])->slug().'/',
            'avatar_urls' => ['96' => 'https://secure.gravatar.com/avatar/'.md5("demo{$id}").'?s=96&d=identicon'],
        ])->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function sponsors(): array
    {
        $sponsors = [
            [201, 'Lakeside Hosting', 31], [202, 'Blockcraft Studio', 31],
            [203, 'Pixel &amp; Press', 32], [204, 'Open Commerce Co.', 32],
            [205, 'Demo City Web Meetup', 33],
        ];

        return array_map(fn ($s) => [
            'id' => $s[0],
            'title' => ['rendered' => $s[1]],
            'content' => ['rendered' => '<p>Proud sponsor of WordCamp Demo City.</p>'],
            'link' => self::SITE.'/sponsor/'.$s[0].'/',
            'sponsor_level' => [$s[2]],
            'meta' => ['_wcpt_sponsor_website' => 'https://example.com/sponsor-'.$s[0]],
        ], $sponsors);
    }

    private function roster(Event $event): void
    {
        $scraper = new AttendeeRosterScraper;
        $names = ['Aisha Khan', 'Ben Carter', 'Chloé Martin', 'Dev Patel', 'Elena Rossi', 'Farah Haddad', 'Gabriel Silva',
            'Hiro Tanaka', 'Isha Sharma', 'Jonas Weber', 'Kavya Nair', 'Liam O\'Brien', 'Maya Cohen', 'Noah Kim',
            'Olivia Brown', 'Priya Iyer', 'Quinn Taylor', 'Ravi Kumar', 'Sara Ahmed', 'Tariq Aziz'];

        foreach ($names as $i => $name) {
            $links = $i % 3 === 0 ? [['type' => 'linkedin', 'url' => 'https://www.linkedin.com/in/demo-'.$i]] : [];

            AttendeeRoster::create([
                'event_id' => $event->id,
                'name' => $name,
                'gravatar_url' => 'https://secure.gravatar.com/avatar/'.md5($name).'?s=96&d=identicon',
                'links' => $links,
                'content_hash' => $scraper->contentHash($name, $links),
            ]);
        }
    }
}
