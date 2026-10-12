<?php

namespace App\Support;

use App\Models\Event;
use App\Models\EventFeedback;
use App\Models\PushSubscription;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * "CampBuddy at {WordCamp}": a social media post with how a WordCamp used
 * CampBuddy (Admin → Analytics → Usage post). The numbers are real and only
 * these: GA4 hits tagged with the event's slug (every event page sends it as
 * event_slug), in the CampBuddy stream, bots left out, from 3 days before the
 * event's first day to 3 days after its last day (to today while it isn't
 * over); plus, from the database, the phones with session reminders on and
 * the attendees' rating. Small numbers are left off the card (they read as a
 * flop, not as use); the users count always leads.
 */
class UsagePost
{
    public const DAYS_AROUND = 3;

    public const TILES = 6;

    /** Below this a number isn't shown on the card (the caption still says what's there). */
    public const MIN_SHOWN = 3;

    /** Ratings needed before an average says anything. */
    public const MIN_RATINGS = 5;

    /** GA4 event => [singular, plural] — what one of it is, in the post's words. */
    public const ACTIONS = [
        'session_save' => ['talk saved', 'talks saved'],
        'quest_complete' => ['quest completed', 'quests completed'],
        'meet_add' => ['person added to meet', 'people added to meet'],
        'discovery_wave' => ['wave sent', 'waves sent'],
        'camp_card_download' => ['Camp Card downloaded', 'Camp Cards downloaded'],
        'data_export' => ['day saved as a PDF', 'days saved as a PDF'],
        'install_complete' => ['app installed', 'apps installed'],
    ];

    /** @return array{from: string, to: string}|null null while the event hasn't come within 3 days */
    public static function range(Event $event, ?CarbonImmutable $today = null): ?array
    {
        $first = EventTime::firstDay($event);
        $last = EventTime::lastDay($event);
        if ($first === null || $last === null) {
            return null;
        }
        $today ??= CarbonImmutable::today();
        $from = CarbonImmutable::parse($first)->subDays(self::DAYS_AROUND);
        $to = CarbonImmutable::parse($last)->addDays(self::DAYS_AROUND);
        if ($from->gt($today)) {
            return null;
        }

        return ['from' => $from->toDateString(), 'to' => ($to->gt($today) ? $today : $to)->toDateString()];
    }

    /**
     * Everything the post needs: the numbers (all of them, and the TILES for
     * the card) and the caption.
     */
    public static function build(Event $event, string $stream, bool $fresh = false): array
    {
        $range = self::range($event);
        if ($range === null) {
            return ['ready' => false, 'message' => "{$event->display_name} hasn't started yet: its numbers start 3 days before its first day."];
        }

        $key = 'usage-post:'.md5(implode('|', [$event->id, $stream, $range['from'], $range['to']]));
        if ($fresh) {
            Cache::forget($key);
        }
        $ga = Cache::remember($key, AnalyticsReport::CACHE_SECONDS, fn () => self::fromGa($event->slug, $stream, $range));

        $stats = [];
        $users = (int) ($ga['totals']['totalUsers'] ?? 0);
        $stats[] = ['key' => 'users', 'value' => $users, 'label' => $users === 1 ? 'person used CampBuddy' : 'people used CampBuddy'];
        $pages = (int) ($ga['totals']['screenPageViews'] ?? 0);
        $stats[] = ['key' => 'pages', 'value' => $pages, 'label' => $pages === 1 ? 'page opened' : 'pages opened'];
        foreach (self::ACTIONS as $name => [$one, $many]) {
            $n = (int) ($ga['events'][$name] ?? 0);
            $stats[] = ['key' => $name, 'value' => $n, 'label' => $n === 1 ? $one : $many];
        }
        $joined = (int) ($ga['joined'] ?? 0);
        $stats[] = ['key' => 'discovery_join', 'value' => $joined, 'label' => $joined === 1 ? 'person joined Meet people' : 'people joined Meet people'];
        $phones = PushSubscription::where('event_id', $event->id)->count();
        $stats[] = ['key' => 'reminders', 'value' => $phones, 'label' => $phones === 1 ? 'phone with session reminders on' : 'phones with session reminders on'];

        $ratings = EventFeedback::where('event_id', $event->id);
        $count = (clone $ratings)->count();
        $rating = $count >= self::MIN_RATINGS ? round((float) (clone $ratings)->avg('rating'), 1) : null;

        // The card: the users first, then the biggest of the rest; nothing too small to mean something.
        $shown = array_values(array_filter($stats, fn ($s) => $s['value'] >= self::MIN_SHOWN));
        $lead = array_values(array_filter($shown, fn ($s) => $s['key'] === 'users'));
        $rest = array_values(array_filter($shown, fn ($s) => $s['key'] !== 'users'));
        usort($rest, fn ($a, $b) => $b['value'] <=> $a['value']);
        $tiles = array_slice([...$lead, ...$rest], 0, self::TILES);

        return [
            'ready' => true,
            'event' => [
                'name' => $event->display_name,
                'short' => SocialKit::shortName($event),
                'dates' => SocialKit::dates($event),
                'logo' => $event->logoUrl() ?? $event->faviconUrl(),
                'hashtags' => SocialKit::hashtags($event),
            ],
            'colors' => SocialKit::colors($event),
            'range' => $range,
            'stats' => $stats,
            'tiles' => $tiles,
            'rating' => $rating !== null ? ['average' => $rating, 'count' => $count] : null,
            'minutes' => $users > 0 ? round(((float) ($ga['totals']['userEngagementDuration'] ?? 0)) / $users / 60, 1) : null,
            // Day by day (every day of the range, 0 where nobody came) and the most-saved talks (3 saves or more).
            'daily' => self::everyDay($range, $ga['daily'] ?? []),
            'talks' => array_values(array_filter($ga['talks'] ?? [], fn ($t) => $t['saves'] >= self::MIN_SHOWN)),
            'firstDay' => EventTime::firstDay($event),
            'lastDay' => EventTime::lastDay($event),
            // One caption per network: LinkedIn (the full story), Instagram (short lines, more tags), X (280 or less).
            'captions' => $captions = self::captions($event, $shown, $rating, $count),
            'caption' => $captions['linkedin'],
            'x_length' => self::xLength($captions['x']),
            'fetched_at' => now()->toIso8601String(),
        ];
    }

    private static function fromGa(string $slug, string $stream, array $range): array
    {
        $base = [
            ['filter' => ['fieldName' => 'streamId', 'stringFilter' => ['value' => $stream, 'matchType' => 'EXACT']]],
            ['filter' => ['fieldName' => 'customEvent:event_slug', 'stringFilter' => ['value' => $slug, 'matchType' => 'EXACT']]],
            ['notExpression' => ['filter' => ['fieldName' => 'screenResolution', 'inListFilter' => ['values' => AnalyticsReport::BOT_SCREENS]]]],
        ];
        $dates = [['startDate' => $range['from'], 'endDate' => $range['to']]];
        $reports = AnalyticsReport::runReports([
            'totals' => ['dateRanges' => $dates, 'metrics' => [['name' => 'totalUsers'], ['name' => 'screenPageViews'], ['name' => 'userEngagementDuration']], 'dimensionFilter' => ['andGroup' => ['expressions' => $base]]],
            'events' => [
                'dateRanges' => $dates, 'dimensions' => [['name' => 'eventName']], 'metrics' => [['name' => 'eventCount'], ['name' => 'totalUsers']],
                'dimensionFilter' => ['andGroup' => ['expressions' => [...$base, ['filter' => ['fieldName' => 'eventName', 'inListFilter' => ['values' => [...array_keys(self::ACTIONS), 'discovery_join']]]]]]],
            ],
            // People per day, for the "day by day" card.
            'daily' => ['dateRanges' => $dates, 'dimensions' => [['name' => 'date']], 'metrics' => [['name' => 'totalUsers']], 'dimensionFilter' => ['andGroup' => ['expressions' => $base]], 'orderBys' => [['dimension' => ['dimensionName' => 'date']]], 'limit' => 60],
            // The talks saved most (titles are the event's public schedule).
            'talks' => [
                'dateRanges' => $dates, 'dimensions' => [['name' => 'customEvent:session_title']], 'metrics' => [['name' => 'eventCount']],
                'dimensionFilter' => ['andGroup' => ['expressions' => [...$base, ['filter' => ['fieldName' => 'eventName', 'stringFilter' => ['value' => 'session_save', 'matchType' => 'EXACT']]]]]],
                'orderBys' => [['metric' => ['metricName' => 'eventCount'], 'desc' => true]], 'limit' => 6,
            ],
        ]);

        $events = [];
        $joined = 0;
        foreach ($reports['events'] ?? [] as $row) {
            $events[$row['eventName']] = $row['eventCount'];
            if ($row['eventName'] === 'discovery_join') {
                $joined = $row['totalUsers'];
            }
        }

        $daily = array_map(fn ($r) => ['date' => CarbonImmutable::createFromFormat('Ymd', $r['date'])->toDateString(), 'users' => (int) $r['totalUsers']], $reports['daily'] ?? []);
        $talks = array_values(array_filter(array_map(fn ($r) => ['title' => $r['customEvent:session_title'], 'saves' => (int) $r['eventCount']], $reports['talks'] ?? []), fn ($t) => $t['title'] !== '' && $t['title'] !== '(not set)'));

        return ['totals' => $reports['totals'][0] ?? [], 'events' => $events, 'joined' => $joined, 'daily' => $daily, 'talks' => $talks];
    }

    /** @return list<array{date: string, users: int}> */
    private static function everyDay(array $range, array $daily): array
    {
        $by = array_column($daily, 'users', 'date');
        $out = [];
        for ($d = CarbonImmutable::parse($range['from']); $d->lte(CarbonImmutable::parse($range['to'])); $d = $d->addDay()) {
            $out[] = ['date' => $d->toDateString(), 'users' => (int) ($by[$d->toDateString()] ?? 0)];
        }

        return $out;
    }

    /** X counts every link as 23 characters, whatever its length. */
    public const X_LIMIT = 280;

    private const X_LINK = 23;

    private const SITE = 'campbuddy.club';

    /**
     * The captions: what happened, in numbers, thanks, and the event's tags,
     * written for each network.
     *
     * @return array{linkedin: string, instagram: string, x: string}
     */
    private static function captions(Event $event, array $shown, ?float $rating, int $count): array
    {
        $users = collect($shown)->firstWhere('key', 'users');
        $others = array_values(array_filter($shown, fn ($s) => $s['key'] !== 'users'));
        usort($others, fn ($a, $b) => $b['value'] <=> $a['value']);
        $said = fn ($s) => number_format($s['value']).' '.$s['label'];
        $people = $users ? number_format($users['value']).' '.($users['value'] === 1 ? 'person' : 'people') : null;
        $name = $event->display_name;
        $short = SocialKit::shortName($event);
        $dates = SocialKit::dates($event);
        $tags = SocialKit::hashtags($event);

        // LinkedIn: the whole story, every number, a clickable link.
        $in = ($people ? "{$people} used CampBuddy at {$name}" : "CampBuddy at {$name}").' ('.$dates.').';
        if ($others) {
            $in .= "\n\nWhat they did with it, from 3 days before the WordCamp to 3 days after:\n".implode("\n", array_map(fn ($s) => '• '.$said($s), $others));
        }
        if ($rating !== null) {
            $in .= "\n\nThey rated it {$rating} out of 5 ({$count} ratings).";
        }
        $in .= "\n\nThank you, {$short}: organisers, speakers, volunteers, sponsors and everyone who came."
            ."\n\nCampBuddy is free for every WordCamp: the schedule with reminders, people to meet and your Camp Card. No sign-up."
            ."\nTry it at your next WordCamp: https://".self::SITE
            ."\n\n{$tags} #CampBuddy #WPSimplified";

        // Instagram: short lines, the top numbers, the address written out (links don't work there), more tags.
        $ig = "CampBuddy at {$short} ({$dates})";
        if ($people) {
            $ig .= "\n\n{$people} used it.";
        }
        if ($others) {
            $ig .= "\n".implode("\n", array_map(fn ($s) => '• '.$said($s), array_slice($others, 0, 4)));
        }
        if ($rating !== null) {
            $ig .= "\n\nRated {$rating}/5 by {$count} attendees.";
        }
        $ig .= "\n\nThank you, {$short}! See you at the next WordCamp."
            ."\nFree for every WordCamp, no sign-up: ".self::SITE
            ."\n\n{$tags} #WordPressCommunity #OpenSource #CampBuddy #WPSimplified";

        // X: 280 or less, counted the way X counts; numbers and tags only while they fit.
        $head = ($people ? "{$people} used CampBuddy at {$short}" : "CampBuddy at {$short}")." ({$dates}).";
        $end = "Thank you, {$short}! Free for every WordCamp: ".self::SITE;
        $fits = fn (string $t) => self::xLength($t) <= self::X_LIMIT;
        $body = [];
        foreach (array_slice($others, 0, 3) as $s) {
            $try = [...$body, $said($s)];
            if ($fits($head."\n\n".implode("\n", $try)."\n\n".$end)) {
                $body = $try;
            }
        }
        if ($rating !== null && $fits($head."\n\n".implode("\n", [...$body, "Rated {$rating}/5"])."\n\n".$end)) {
            $body[] = "Rated {$rating}/5";
        }
        $x = $head.($body ? "\n\n".implode("\n", $body) : '')."\n\n".$end;
        // Tags while they fit, the event's own first (#WCDelhi2026 before #WordPress), shown in their usual order.
        $all = [...explode(' ', $tags), '#CampBuddy'];
        $chosen = [];
        foreach ([...array_reverse(explode(' ', $tags)), '#CampBuddy'] as $tag) {
            if ($fits($x."\n\n".implode(' ', array_intersect($all, [...$chosen, $tag])))) {
                $chosen[] = $tag;
            }
        }
        if ($chosen) {
            $x .= "\n\n".implode(' ', array_intersect($all, $chosen));
        }

        return ['linkedin' => $in, 'instagram' => $ig, 'x' => $x];
    }

    /**
     * A text's length the way X counts it: a link is 23, most Latin letters
     * and punctuation 1, anything else (emoji, CJK, some symbols) 2.
     */
    public static function xLength(string $text): int
    {
        $text = preg_replace('~(?:https?://)?campbuddy\.club\S*~u', str_repeat('x', self::X_LINK), $text);
        $n = 0;
        foreach (mb_str_split($text) as $ch) {
            $c = mb_ord($ch);
            $n += ($c <= 4351 || ($c >= 8192 && $c <= 8205) || ($c >= 8208 && $c <= 8223) || ($c >= 8242 && $c <= 8247)) ? 1 : 2;
        }

        return $n;
    }
}
