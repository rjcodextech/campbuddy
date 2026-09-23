<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Root route: launch ships with exactly one visible event and
 * no central discovery, so there's nothing to "choose" yet — straight to
 * that event's Home. Multi-event browsing replaces this with a
 * real picker later; this controller is the seam where that lands.
 */
class HomeRedirectController extends Controller
{
    public function __invoke(): View|RedirectResponse
    {
        $events = Event::where('status', 'active')->where('is_visible', true)->get();

        if ($events->count() === 1) {
            return redirect()->route('event.home', $events->first());
        }

        return view('welcome', ['events' => $events]);
    }
}
