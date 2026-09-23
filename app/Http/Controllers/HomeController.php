<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\View\View;

/**
 * Root route: always shows the WordCamp picker — up to the 5 soonest
 * upcoming/current events — rather than silently skipping it whenever
 * there's only one visible event. Multi-event browsing (§2.2) lands here
 * as more events go live; this is the always-reachable entry point,
 * including as a "switch WordCamp" destination from inside an event
 * (see attendee.partials.topbar).
 */
class HomeController extends Controller
{
    public function __invoke(): View
    {
        $events = Event::where('status', 'active')
            ->where('is_visible', true)
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', today()))
            ->orderBy('starts_on')
            ->take(5)
            ->get();

        return view('welcome', ['events' => $events]);
    }
}
