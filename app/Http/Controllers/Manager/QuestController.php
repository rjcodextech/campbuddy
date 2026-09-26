<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureEventManager;
use App\Http\Requests\Manager\ManagedQuestRequest;
use App\Support\DataVersion;
use App\Support\ManagerActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * An event manager's "Quests & checklist" — the same add / edit / hide /
 * remove as the admin's page, but only for the manager's own events, and a
 * quest is only ever looked up *through* its event, so another event's quest
 * id is a 404. Each change is written to the activity log.
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

        $quest = $event->quests()->create($data + ['source' => 'event']);

        ManagerActivity::record($request->user(EnsureEventManager::GUARD), $event, 'quests', 'added', 'Added '.ManagerActivity::quote($quest->title));
        DataVersion::forget($event->id);

        return redirect()->route('manager.events.quests', $event)->with('status', 'Item added.');
    }

    public function update(ManagedQuestRequest $request, string $eventId, string $questId): RedirectResponse
    {
        $event = $this->managedEvent($request, $eventId);
        $quest = $event->quests()->findOrFail($questId);
        $before = $quest->replicate();

        $quest->update($request->validated());

        if ($change = ManagerActivity::questChange($before, $quest)) {
            ManagerActivity::record($request->user(EnsureEventManager::GUARD), $event, 'quests', 'updated', $change);
            DataVersion::forget($event->id);
        }

        return redirect()->route('manager.events.quests', $event)->with('status', 'Item updated.');
    }

    public function destroy(Request $request, string $eventId, string $questId): RedirectResponse
    {
        $event = $this->managedEvent($request, $eventId);
        $quest = $event->quests()->findOrFail($questId);

        $quest->delete();

        ManagerActivity::record($request->user(EnsureEventManager::GUARD), $event, 'quests', 'removed', 'Removed '.ManagerActivity::quote($quest->title));
        DataVersion::forget($event->id);

        return redirect()->route('manager.events.quests', $event)->with('status', 'Item removed.');
    }
}
