<?php

namespace App\Http\Controllers;

use App\Models\Event;
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
        // date, or its start date when no end date is known (the same marker the
        // lifecycle sweep archives on). An event with no dates at all is kept —
        // an admin made it live without dates yet — rather than hidden.
        $lastDay = 'COALESCE(ends_on, starts_on)';

        $events = Event::where('status', 'active')
            ->where('is_visible', true)
            ->where(fn ($q) => $q->whereRaw("{$lastDay} IS NULL")->orWhereRaw("{$lastDay} >= ?", [today()->toDateString()]))
            // Soonest first; events with no start date go after the dated ones
            // (MySQL sorts NULLs first in ascending order), then by id so the
            // order never shuffles between page loads.
            ->orderByRaw('starts_on IS NULL')
            ->orderBy('starts_on')
            ->orderBy('id')
            ->take(self::LIMIT)
            ->get();

        return view('welcome', ['events' => $events]);
    }
}
