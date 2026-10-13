<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Support\DiscoveryReports;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** An event manager's Reports tab: the admin's page, for their own events. */
class ReportsController extends Controller
{
    use ResolvesManagedEvent;

    public function index(Request $request, string $eventId): View
    {
        $event = $this->managedEvent($request, $eventId);

        return view('manager.events.reports', ['event' => $event] + DiscoveryReports::forEvent($event));
    }

    public function remove(Request $request, string $eventId, string $discoveryId): RedirectResponse
    {
        DiscoveryReports::remove($this->managedEvent($request, $eventId), $discoveryId);

        return back()->with('status', 'Profile removed.');
    }

    public function dismiss(Request $request, string $eventId, string $discoveryId): RedirectResponse
    {
        DiscoveryReports::dismiss($this->managedEvent($request, $eventId), $discoveryId);

        return back()->with('status', 'Reports dismissed.');
    }
}
