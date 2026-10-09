<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureEventManager;
use App\Models\AttendeeRoster;
use App\Support\ManagerActivity;
use App\Support\RosterMarks;
use App\Support\RosterRoles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * An event manager's "Attendees": the event's attendee list with each
 * person's roles, which they can set by hand (RosterMarks) — media partner,
 * sponsor, table lead, or a wrong automatic one taken off. Only for their own
 * events; nothing else about the list (suppressing, discovery) is theirs to
 * change. Every change goes to the activity log.
 */
class AttendeeController extends Controller
{
    use ResolvesManagedEvent;

    public function index(Request $request, string $eventId): View
    {
        $event = $this->managedEvent($request, $eventId);
        $q = $request->string('q')->toString();
        $role = $request->string('role')->toString();
        $both = RosterRoles::withAutomatic($event);
        $roles = $both['marked'];

        $roster = $event->attendeeRoster()
            ->where('is_suppressed', false)
            ->when($q !== '', fn ($query) => $query->where('name', 'like', '%'.$q.'%'))
            ->when(in_array($role, RosterRoles::ROLES, true), fn ($query) => $query->whereIn('id', collect($roles)->filter(fn ($r) => in_array($role, $r['roles'], true))->keys()->all()))
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        return view('manager.events.attendees', [
            'event' => $event,
            'roster' => $roster,
            'q' => $q,
            'role' => $role,
            'roles' => $roles,
            'autoRoles' => $both['auto'],
            'marksReady' => RosterMarks::available(),
        ]);
    }

    public function roles(Request $request, string $eventId, string $entryId): RedirectResponse
    {
        $event = $this->managedEvent($request, $eventId);
        $entry = AttendeeRoster::where('event_id', $event->id)->where('is_suppressed', false)->findOrFail($entryId);
        $request->validate(['roles' => ['array'], 'roles.*' => ['string', Rule::in(RosterRoles::ROLES)]]);

        $manager = $request->user(EnsureEventManager::GUARD);
        $change = RosterMarks::set($event, $entry, $request->input('roles', []), 'manager: '.$manager->name);

        $words = array_filter([
            $change['added'] ? 'added '.implode(', ', array_map(fn ($r) => RosterRoles::LABELS[$r], $change['added'])) : null,
            $change['removed'] ? 'took off '.implode(', ', array_map(fn ($r) => RosterRoles::LABELS[$r], $change['removed'])) : null,
        ]);
        if ($words !== []) {
            ManagerActivity::record($manager, $event, 'attendees', 'updated', 'Roles of '.ManagerActivity::quote($entry->name).': '.implode('; ', $words));
        }

        return back()->with('status', "Roles of {$entry->name} saved.");
    }
}
