<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendeeRoster;
use App\Models\DiscoveryProfile;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * View/search the ingested roster and the takedown path —
 * suppression, not deletion, so a re-run of the scraper can't silently
 * un-suppress someone who asked to be removed.
 */
class RosterController extends Controller
{
    public function index(Event $event, Request $request): View
    {
        Gate::authorize('viewAny', Event::class);

        $roster = $event->attendeeRoster()
            ->when($request->string('q')->isNotEmpty(), fn ($q) => $q->where('name', 'like', '%'.$request->string('q').'%'))
            ->withExists('discoveryProfile as in_discovery')
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        return view('admin.roster.index', ['event' => $event, 'roster' => $roster, 'q' => $request->string('q')->toString()]);
    }

    public function suppress(Event $event, AttendeeRoster $entry): RedirectResponse
    {
        Gate::authorize('update', $event);

        $entry->suppress();

        return redirect()->route('admin.events.roster.index', $event)->with('status', "{$entry->name} suppressed from the public roster.");
    }

    /**
     * Frees an attendee-list name from the discovery profile that claimed it —
     * for when the real person says "that wasn't me". The profile stays in
     * discovery, just without the name; the person can then pick it themselves.
     */
    public function releaseClaim(Event $event, AttendeeRoster $entry): RedirectResponse
    {
        Gate::authorize('update', $event);
        abort_unless($entry->event_id === $event->id, 404);

        DiscoveryProfile::where('attendee_roster_id', $entry->id)->update(['attendee_roster_id' => null]);

        return redirect()->route('admin.events.roster.index', $event)->with('status', "{$entry->name} is no longer linked to a discovery profile — they can now pick their own name.");
    }

    public function unsuppress(Event $event, AttendeeRoster $entry): RedirectResponse
    {
        Gate::authorize('update', $event);

        $entry->update(['is_suppressed' => false]);

        return redirect()->route('admin.events.roster.index', $event)->with('status', "{$entry->name} restored to the public roster.");
    }
}
