<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\RosterController as RosterApi;
use App\Http\Controllers\Controller;
use App\Models\AttendeeRoster;
use App\Models\DiscoveryProfile;
use App\Models\Event;
use App\Models\SharedCampCard;
use App\Support\RosterMarks;
use App\Support\RosterRoles;
use Illuminate\Validation\Rule;
use Illuminate\Database\QueryException;
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

        $query = fn (bool $withDiscovery) => $event->attendeeRoster()
            ->when($request->string('q')->isNotEmpty(), fn ($q) => $q->where('name', 'like', '%'.$request->string('q').'%'))
            ->when($withDiscovery, fn ($q) => $q->withExists('discoveryProfile as in_discovery'))
            ->when($withDiscovery, fn ($q) => $q->withExists('sharedCampCard as has_camp_card'))
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        try {
            $roster = $query(true);
        } catch (QueryException $e) {
            // Migrations not run yet: the list still works, without the
            // "In discovery" marks (the dashboard says what to run).
            report($e);
            $roster = $query(false);
        }

        $roles = RosterRoles::withAutomatic($event);

        return view('admin.roster.index', [
            'event' => $event,
            'roster' => $roster,
            'q' => $request->string('q')->toString(),
            'roles' => $roles['marked'],
            'autoRoles' => $roles['auto'],
            'marksReady' => RosterMarks::available(),
        ]);
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

    /** Sets one attendee's roles by hand (RosterMarks). */
    public function roles(Event $event, AttendeeRoster $entry, Request $request): RedirectResponse
    {
        Gate::authorize('update', $event);
        abort_unless($entry->event_id === $event->id, 404);
        $request->validate(['roles' => ['array'], 'roles.*' => ['string', Rule::in(RosterRoles::ROLES)]]);

        RosterMarks::set($event, $entry, $request->input('roles', []), 'admin: '.$request->user()->name);

        return back()->with('status', "Roles of {$entry->name} saved.");
    }

    /** Takes a shared Camp Card off the attendee list (abuse, or a name claimed by the wrong person). */
    public function removeCard(Event $event, AttendeeRoster $entry): RedirectResponse
    {
        Gate::authorize('update', $event);
        abort_unless($entry->event_id === $event->id, 404);

        SharedCampCard::where('attendee_roster_id', $entry->id)->delete();
        RosterApi::forget($event);

        return redirect()->route('admin.events.roster.index', $event)->with('status', "{$entry->name}'s Camp Card was taken off the attendee list.");
    }

    public function unsuppress(Event $event, AttendeeRoster $entry): RedirectResponse
    {
        Gate::authorize('update', $event);

        $entry->update(['is_suppressed' => false]);

        return redirect()->route('admin.events.roster.index', $event)->with('status', "{$entry->name} restored to the public roster.");
    }
}
