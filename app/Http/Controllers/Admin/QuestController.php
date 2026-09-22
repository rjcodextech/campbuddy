<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQuestRequest;
use App\Models\Event;
use App\Models\Quest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Full CRUD on an event's quests (§9) — event-specific quests only; the
 * default/cross-event list is seeded (DefaultQuestSeeder), not admin
 * content, per §3.6 C3.
 */
class QuestController extends Controller
{
    public function index(Event $event): View
    {
        Gate::authorize('viewAny', Quest::class);

        $quests = $event->quests()->orderBy('sort_order')->get();

        return view('admin.quests.index', compact('event', 'quests'));
    }

    public function store(StoreQuestRequest $request, Event $event): RedirectResponse
    {
        $event->quests()->create($request->validated() + ['source' => 'event']);

        return redirect()->route('admin.events.quests.index', $event)->with('status', 'Quest added.');
    }

    public function update(StoreQuestRequest $request, Event $event, Quest $quest): RedirectResponse
    {
        $quest->update($request->validated());

        return redirect()->route('admin.events.quests.index', $event)->with('status', 'Quest updated.');
    }

    public function destroy(Event $event, Quest $quest): RedirectResponse
    {
        Gate::authorize('delete', $quest);

        $quest->delete();

        return redirect()->route('admin.events.quests.index', $event)->with('status', 'Quest removed.');
    }
}
