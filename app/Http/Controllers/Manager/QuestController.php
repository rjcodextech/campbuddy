<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Requests\Manager\ManagedQuestRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * An event manager's "Quests & checklist" — the same add / edit / hide /
 * remove as the admin's page, but only for the manager's own events, and a
 * quest is only ever looked up *through* its event, so another event's quest
 * id is a 404.
 */
class QuestController extends Controller
{
    use ResolvesManagedEvent;

    public function index(Request $request, string $eventId): View
    {
        $event = $this->managedEvent($request, $eventId);

        return view('manager.events.quests', ['event' => $event, 'quests' => $event->quests()->orderBy('sort_order')->get()]);
    }

    public function store(ManagedQuestRequest $request, string $eventId): RedirectResponse
    {
        $event = $this->managedEvent($request, $eventId);
        $data = $request->validated();

        // New items go to the end of the list, unless a position was picked.
        $data['sort_order'] ??= ($event->quests()->where('source', 'event')->max('sort_order') ?? 0) + 10;

        $event->quests()->create($data + ['source' => 'event']);

        return redirect()->route('manager.events.quests', $event)->with('status', 'Item added.');
    }

    public function update(ManagedQuestRequest $request, string $eventId, string $questId): RedirectResponse
    {
        $event = $this->managedEvent($request, $eventId);

        $event->quests()->findOrFail($questId)->update($request->validated());

        return redirect()->route('manager.events.quests', $event)->with('status', 'Item updated.');
    }

    public function destroy(Request $request, string $eventId, string $questId): RedirectResponse
    {
        $event = $this->managedEvent($request, $eventId);

        $event->quests()->findOrFail($questId)->delete();

        return redirect()->route('manager.events.quests', $event)->with('status', 'Item removed.');
    }
}
