<?php

namespace App\Support;

use App\Models\Event;

/**
 * The WordCamps that ended in the last RETENTION_DAYS (other than this one,
 * newest first), for "Bring it from your last WordCamp" on an event's Home
 * (bring-data.js): the phone checks which of them it holds something for.
 * Only while this event isn't over: bringing things into a finished event
 * helps no one.
 */
class PastEvents
{
    public const MAX = 8;

    /** @return list<array{id: int, name: string, slug: string, daysAgo: int}> */
    public static function forHome(Event $event): array
    {
        if (EventTime::isOver($event)) {
            return [];
        }

        $events = Event::where('status', 'active')->where('is_visible', true)->where('id', '!=', $event->id)
            ->whereNotNull('starts_on')->where('starts_on', '<=', today()->toDateString())
            ->where('starts_on', '>=', today()->subDays(EventTime::RETENTION_DAYS + EventTime::MAX_EVENT_SPAN_DAYS + 1)->toDateString())
            ->get();
        EventTime::primeSessionDays($events);

        return $events
            ->map(fn (Event $e) => ['id' => $e->id, 'name' => $e->display_name, 'slug' => $e->slug, 'daysAgo' => EventTime::daysSinceEnd($e)])
            ->filter(fn ($e) => $e['daysAgo'] !== null && $e['daysAgo'] <= EventTime::RETENTION_DAYS)
            ->sortBy('daysAgo')
            ->take(self::MAX)
            ->values()
            ->all();
    }
}
