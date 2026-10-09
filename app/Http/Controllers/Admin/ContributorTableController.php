<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContributorTable;
use App\Models\Event;
use App\Support\ContributorTables;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Admin → Event → Contributor Day: the event's tables and their leads (ContributorTables). */
class ContributorTableController extends Controller
{
    public function index(Event $event): View
    {
        Gate::authorize('viewAny', Event::class);

        return view('admin.events.tables', [
            'event' => $event,
            'tables' => ContributorTable::forEvent($event),
            'names' => $event->attendeeRoster()->where('is_suppressed', false)->orderBy('name')->pluck('name')->unique()->values(),
        ]);
    }

    public function store(Request $request, Event $event): RedirectResponse
    {
        Gate::authorize('update', $event);
        $table = ContributorTable::create(['event_id' => $event->id] + ContributorTables::fields($event, $request->validate(ContributorTables::rules())));
        ContributorTables::changed($event);

        return redirect()->route('admin.events.tables.index', $event)->with('status', ContributorTables::describe($table).' added.');
    }

    public function update(Request $request, Event $event, ContributorTable $table): RedirectResponse
    {
        Gate::authorize('update', $event);
        abort_unless($table->event_id === $event->id, 404);
        $table->update(ContributorTables::fields($event, $request->validate(ContributorTables::rules())));
        ContributorTables::changed($event);

        return redirect()->route('admin.events.tables.index', $event)->with('status', ContributorTables::describe($table).' saved.');
    }

    public function destroy(Event $event, ContributorTable $table): RedirectResponse
    {
        Gate::authorize('update', $event);
        abort_unless($table->event_id === $event->id, 404);
        $table->delete();
        ContributorTables::changed($event);

        return redirect()->route('admin.events.tables.index', $event)->with('status', ContributorTables::describe($table).' removed.');
    }
}
