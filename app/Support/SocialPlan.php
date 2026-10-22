<?php

namespace App\Support;

use App\Models\Event;
use Carbon\CarbonImmutable;

/**
 * The tentative day to share each social post (the Social media studio's
 * posting plan, its "Download all" posting-plan.txt and the phone reminders,
 * SendPostRemindersCommand): counted back from the event's first day at the
 * venue, the way an event is promoted — announce, build up, the last week, the
 * days themselves, after. A plan that starts late (the event came into
 * CampBuddy close to its date) keeps the order: its posts are spread over the
 * days left before the event, never piled on one day.
 */
class SocialPlan
{
    /** Days from the event's first day (negative = before); 'after' = days after its last day. */
    public const OFFSETS = [
        'save-the-date' => -42,
        'venue' => -35,
        'speakers' => -28,
        'sponsors' => -24,
        'schedule' => -21,
        'first-timer' => -17,
        'contributor' => -14,
        'wpsimplified' => -12,
        'app' => -10,
        'meet' => -7,
        'countdown' => [-7, -3, -1],
        'today' => 0,
        'spotlight' => 0,
        'moments' => 0,
        'thank-you' => ['after' => 1],
    ];

    /**
     * @return array<string, array{date: string, label: string}> post key => its next day to share it (venue time)
     */
    public static function dates(Event $event, ?CarbonImmutable $now = null): array
    {
        $first = EventTime::firstDay($event);
        $last = EventTime::lastDay($event);
        if ($first === null) {
            return [];
        }
        $zone = EventTime::zone($event);
        $today = CarbonImmutable::parse(EventTime::today($event, $now), $zone);
        $start = CarbonImmutable::parse($first, $zone);
        $end = CarbonImmutable::parse($last ?? $first, $zone);

        $planned = [];
        foreach (self::OFFSETS as $key => $offset) {
            if (is_array($offset) && isset($offset['after'])) {
                $planned[$key] = $end->addDays($offset['after']);
            } elseif (is_array($offset)) {
                // The countdown: its next day (7, 3 or 1 day before); the last one once they have all passed.
                $days = array_map(fn ($d) => $start->addDays($d), $offset);
                $planned[$key] = collect($days)->first(fn ($d) => $d->gte($today)) ?? end($days);
            } else {
                $planned[$key] = $start->addDays($offset);
            }
        }

        // Late start: when the event came into CampBuddy after some posts' days, the pre-event posts are
        // spread, in order, from that day to the day before the event. Measured from the day it was added,
        // never from today, so a day that has passed stays passed and is never planned again.
        $added = CarbonImmutable::parse(($event->created_at ?? $now ?? now())->toDateTimeString(), 'UTC')->setTimezone($zone)->startOfDay();
        $before = array_filter($planned, fn ($d) => $d->lt($start));
        $late = array_filter($before, fn ($d) => $d->lt($added));
        if ($late !== [] && $added->lt($start)) {
            $queue = array_keys($before);
            $room = max(1, (int) $added->diffInDays($start));
            foreach ($queue as $i => $key) {
                $planned[$key] = $added->addDays(min($room - 1, (int) floor($i * $room / count($queue))));
            }
        }

        return array_map(fn (CarbonImmutable $d) => ['date' => $d->toDateString(), 'label' => $d->format('D j M')], $planned);
    }

    /**
     * The posting plan as a calendar: whole weeks (Monday first) from the week of
     * the first post (or today, if later) to the week after the event, each day with
     * its posts, marked when it is today, an event day or the first of a month.
     *
     * @param  list<array<string, mixed>>  $posts  SocialKit::posts() (with date)
     * @return list<list<array{date: string, day: int, month: ?string, today: bool, event: bool, past: bool, posts: list<array<string, mixed>>}>>
     */
    public static function calendar(Event $event, array $posts, ?CarbonImmutable $now = null): array
    {
        $dated = array_values(array_filter($posts, fn ($p) => ! empty($p['date'])));
        if ($dated === []) {
            return [];
        }
        $zone = EventTime::zone($event);
        $today = CarbonImmutable::parse(EventTime::today($event, $now), $zone);
        $first = CarbonImmutable::parse(EventTime::firstDay($event) ?? $dated[0]['date'], $zone);
        $last = CarbonImmutable::parse(EventTime::lastDay($event) ?? $first->toDateString(), $zone);
        $from = collect($dated)->map(fn ($p) => CarbonImmutable::parse($p['date'], $zone))->min();
        $to = collect($dated)->map(fn ($p) => CarbonImmutable::parse($p['date'], $zone))->max();
        // The first week shown: the plan's first post, but no earlier than this week once it has passed.
        $start = ($from->lt($today) && $today->lte($to) ? $today : $from)->startOfWeek(CarbonImmutable::MONDAY);
        $end = $to->endOfWeek(CarbonImmutable::SUNDAY);
        $byDate = collect($dated)->groupBy('date');

        $weeks = [];
        for ($d = $start; $d->lte($end); $d = $d->addDay()) {
            $date = $d->toDateString();
            $weeks[intdiv((int) $start->diffInDays($d), 7)][] = [
                'date' => $date,
                'day' => (int) $d->format('j'),
                'month' => $d->day === 1 || $d->equalTo($start) ? $d->format('M') : null,
                'today' => $d->equalTo($today),
                'event' => $d->gte($first) && $d->lte($last),
                'past' => $d->lt($today),
                'posts' => $byDate->get($date, collect())->all(),
            ];
        }

        return array_values($weeks);
    }

    /**
     * The posts due on `$now`'s day at the venue.
     *
     * @return list<string> post keys
     */
    public static function dueToday(Event $event, ?CarbonImmutable $now = null): array
    {
        $today = EventTime::today($event, $now);

        return array_keys(array_filter(self::dates($event, $now), fn ($p) => $p['date'] === $today));
    }
}
