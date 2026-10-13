<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DiscoveryProfile;
use App\Models\DiscoveryReport;
use App\Models\Event;
use App\Http\Controllers\Api\DiscoveryController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
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

        $open = DiscoveryReport::where('event_id', $event->id)->where('status', 'open')->latest()->get()->groupBy('discovery_id');
        $closed = DiscoveryReport::where('event_id', $event->id)->where('status', '!=', 'open')->latest('resolved_at')->limit(30)->get();

        return view('admin.events.reports', [
            'event' => $event,
            'open' => $open,
            'closed' => $closed,
            'stillThere' => DiscoveryProfile::where('event_id', $event->id)->whereIn('discovery_id', $open->keys())->pluck('discovery_id')->all(),
        ]);
    }

    /** Takes the reported profile down and closes its reports. */
    public function remove(Event $event, string $discoveryId): RedirectResponse
    {
        Gate::authorize('update', $event);

        DiscoveryProfile::where('event_id', $event->id)->where('discovery_id', $discoveryId)->delete();
        Cache::forget(DiscoveryController::listKey($event));
        $this->close($event, $discoveryId, 'removed');

        return back()->with('status', 'Profile removed.');
    }

    /** The reports were not a problem: close them, leave the profile. */
    public function dismiss(Event $event, string $discoveryId): RedirectResponse
    {
        Gate::authorize('update', $event);

        $this->close($event, $discoveryId, 'dismissed');

        return back()->with('status', 'Reports dismissed.');
    }

    private function close(Event $event, string $discoveryId, string $status): void
    {
        DiscoveryReport::where('event_id', $event->id)->where('discovery_id', $discoveryId)->where('status', 'open')
            ->update(['status' => $status, 'resolved_at' => now()]);
    }
}
