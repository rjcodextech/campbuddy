<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureEventManager;
use App\Support\EventListing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "My events": the events an admin gave this manager, soonest first. One
 * event goes straight to it — there is nothing to choose.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        $manager = $request->user(EnsureEventManager::GUARD);

        $events = EventListing::ordered($manager->events())->get();

        if ($events->count() === 1) {
            return redirect()->route('manager.events.details', $events->first()->id);
        }

        return view('manager.dashboard', ['manager' => $manager, 'events' => $events, 'today' => EventListing::today()]);
    }
}
