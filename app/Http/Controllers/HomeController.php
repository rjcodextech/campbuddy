<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Support\EventTime;
use Illuminate\View\View;

/**
 * Root route: always shows the WordCamp picker — the next 10 upcoming or
 * current events, soonest first — rather than silently skipping it whenever
 * there's only one visible event. Multi-event browsing (§2.2) lands here
 * as more events go live; this is the always-reachable entry point,
 * including as a "WordCamp's" destination from inside an event
 * (see attendee.partials.topbar).
 */
class HomeController extends Controller
{
    private const LIMIT = 10;

    public function __invoke(): View
    {
        // An event is "upcoming" until its last known day has passed: its end
        // date, its start date, or the day of its last scheduled session —
        // whichever is latest (EventTime::lastDay), since scraped events often
        // have no end date. An event with no dates at all is kept — an admin
        // made it live without dates yet — rather than hidden.
        $lastDay = 'COALESCE(ends_on, starts_on)';

        $events = Event::where('status', 'active')
            ->where('is_visible', true)
            // A few days of slack in SQL — the earliest time zone is a day behind
            // UTC, and an event whose end date is missing may still have sessions
            // days after its start (found below, from its schedule)…
            ->where(fn ($q) => $q->whereRaw("{$lastDay} IS NULL")->orWhereRaw("{$lastDay} >= ?", [today()->subDays(3)->toDateString()]))
            // Soonest first; events with no start date go after the dated ones
            // (MySQL sorts NULLs first in ascending order), then by id so the
            // order never shuffles between page loads.
            ->orderByRaw('starts_on IS NULL')
            ->orderBy('starts_on')
            ->orderBy('id')
            ->take(self::LIMIT * 4)
            ->get()
            // …then exact: shown until its last day has ended at the venue.
            ->tap(fn ($found) => EventTime::primeSessionDays($found))
            ->reject(fn (Event $event) => EventTime::isOver($event))
            ->take(self::LIMIT)
            ->values();

        return view('welcome', ['events' => $events]);
    }
}
