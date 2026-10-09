<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureEventManager;
use App\Models\ContributorTable;
use App\Support\ContributorTables;
use App\Support\ManagerActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * An event manager's "Contributor Day": the same tables page as the admin's,
 * for their own events only; a table is only ever looked up through its
 * event. Every change goes to the activity log.
 */
class ContributorTableController extends Controller
{
    use ResolvesManagedEvent;

    public function index(Request $request, string $eventId): View
    {
        $event = $this->managedEvent($request, $eventId);

        return view('manager.events.tables', [
            'event' => $event,
            'tables' => ContributorTable::forEvent($event),
            'names' => $event->attendeeRoster()->where('is_suppressed', false)->orderBy('name')->pluck('name')->unique()->values(),
        ]);
    }

    public function store(Request $request, string $eventId): RedirectResponse
    {
        $event = $this->managedEvent($request, $eventId);
        $table = ContributorTable::create(['event_id' => $event->id] + ContributorTables::fields($event, $request->validate(ContributorTables::rules())));
        ContributorTables::changed($event);
        ManagerActivity::record($request->user(EnsureEventManager::GUARD), $event, 'tables', 'added', 'Added table '.ManagerActivity::quote(ContributorTables::describe($table)));

        return redirect()->route('manager.events.tables', $event)->with('status', ContributorTables::describe($table).' added.');
    }

    public function update(Request $request, string $eventId, string $tableId): RedirectResponse
    {
        $event = $this->managedEvent($request, $eventId);
        $table = ContributorTable::where('event_id', $event->id)->findOrFail($tableId);
        $table->update(ContributorTables::fields($event, $request->validate(ContributorTables::rules())));
        ContributorTables::changed($event);
        ManagerActivity::record($request->user(EnsureEventManager::GUARD), $event, 'tables', 'updated', 'Changed table '.ManagerActivity::quote(ContributorTables::describe($table)));

        return redirect()->route('manager.events.tables', $event)->with('status', ContributorTables::describe($table).' saved.');
    }

    public function destroy(Request $request, string $eventId, string $tableId): RedirectResponse
    {
        $event = $this->managedEvent($request, $eventId);
        $table = ContributorTable::where('event_id', $event->id)->findOrFail($tableId);
        $table->delete();
        ContributorTables::changed($event);
        ManagerActivity::record($request->user(EnsureEventManager::GUARD), $event, 'tables', 'removed', 'Removed table '.ManagerActivity::quote(ContributorTables::describe($table)));

        return redirect()->route('manager.events.tables', $event)->with('status', ContributorTables::describe($table).' removed.');
    }
}
