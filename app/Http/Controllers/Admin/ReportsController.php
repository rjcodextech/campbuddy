<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Support\DiscoveryReports;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Admin → Event → Reports: what attendees reported on discovery profiles.
 * Each profile is one row with who reported it and why; the admin either
 * removes the profile (it vanishes from every phone within seconds) or
 * dismisses the reports. App stores expect reports to be acted on within a day.
 */
class ReportsController extends Controller
{
    public function index(Event $event): View
    {
        Gate::authorize('viewAny', Event::class);

        return view('admin.events.reports', ['event' => $event] + DiscoveryReports::forEvent($event));
    }

    /** Takes the reported profile down and closes its reports. */
    public function remove(Event $event, string $discoveryId): RedirectResponse
    {
        Gate::authorize('update', $event);

        DiscoveryReports::remove($event, $discoveryId);

        return back()->with('status', 'Profile removed.');
    }

    /** The reports were not a problem: close them, leave the profile. */
    public function dismiss(Event $event, string $discoveryId): RedirectResponse
    {
        Gate::authorize('update', $event);

        DiscoveryReports::dismiss($event, $discoveryId);

        return back()->with('status', 'Reports dismissed.');
    }
}
