<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Support\EventCountry;
use App\Support\EventTime;
use Illuminate\View\View;

/**
 * Root route: always shows the WordCamp picker — every upcoming or current
 * event (up to LIMIT), soonest first, then the ones that finished in the last
 * week as "Completed" — rather than silently skipping it whenever
 * there's only one visible event. Multi-event browsing (§2.2) lands here
 * as more events go live; this is the always-reachable entry point,
 * including as a "WordCamp's" destination from inside an event
 * (see attendee.partials.topbar).
 *
 * The page lists them all; picker-filter.js then shows the first few with a
 * "Load more" button and a country filter, in the browser only — so the
 * HTML stays the same for every visitor (safe to cache at the edge).
 */
class HomeController extends Controller
{
    private const LIMIT = 100;

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
            ->where(fn ($q) => $q->whereRaw("{$lastDay} IS NULL")->orWhereRaw("{$lastDay} >= ?", [today()->subDays(EventTime::RETENTION_DAYS + 3)->toDateString()]))
            // Soonest first; events with no start date go after the dated ones
            // (MySQL sorts NULLs first in ascending order), then by id so the
            // order never shuffles between page loads.
            ->orderByRaw('starts_on IS NULL')
            ->orderBy('starts_on')
            ->orderBy('id')
            ->take(self::LIMIT * 4)
            ->get()
            // …then exact: upcoming until its last day has ended at the venue;
            // after that "Completed" for the retention week (people can still
            // open it, export their day as a PDF, answer the thank-you card),
            // listed after every upcoming one, most recently finished first.
            ->tap(fn ($found) => EventTime::primeSessionDays($found))
            ->filter(fn (Event $event) => ! EventTime::isOver($event) || EventTime::retained($event))
            ->partition(fn (Event $event) => ! EventTime::isOver($event))
            ->pipe(fn ($parts) => $parts[0]->concat($parts[1]->sortByDesc(fn (Event $event) => EventTime::lastDay($event))))
            ->take(self::LIMIT)
            ->values();

        $completed = $events->filter(fn (Event $event) => EventTime::isOver($event))->pluck('id')->flip()->all();

        // Each card's country, and the time zones of those countries so the
        // browser can pick the visitor's own (EventCountry).
        $countries = $events->mapWithKeys(fn (Event $event) => [$event->id => EventCountry::code($event)]);
        $codes = $countries->filter()->unique()->sort()->values();

        return view('welcome', [
            'events' => $events,
            'completed' => $completed,
            'eventCountries' => $countries->all(),
            'countryNames' => $codes->mapWithKeys(fn ($code) => [$code => EventCountry::name($code)])->all(),
            'countryZones' => EventCountry::timezones($codes),
        ]);
    }
}
