<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureEventManager;
use App\Support\EventMemories;
use App\Support\ManagerActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** An event manager's Memories: the admin's page, for their own events; every change logged. */
class MemoriesController extends Controller
{
    use ResolvesManagedEvent;

    public function index(Request $request, string $eventId): View
    {
        return view('manager.events.memories', ['event' => $this->managedEvent($request, $eventId)]);
    }

    public function store(Request $request, string $eventId): RedirectResponse
    {
        $event = $this->managedEvent($request, $eventId);
        $summary = EventMemories::save($request, $event);
        if ($summary !== 'Nothing changed') {
            ManagerActivity::record($request->user(EnsureEventManager::GUARD), $event, 'memories', 'updated', $summary);
        }

        return redirect()->route('manager.events.memories', $event)->with('status', $summary.'.');
    }

    public function notify(Request $request, string $eventId, string $kind): RedirectResponse
    {
        $event = $this->managedEvent($request, $eventId);
        abort_unless(array_key_exists($kind, EventMemories::KINDS), 404);
        EventMemories::notify($event, $kind);
        $label = EventMemories::KINDS[$kind]['label'];
        ManagerActivity::record($request->user(EnsureEventManager::GUARD), $event, 'memories', 'notified', "Sent the {$label} notification");

        return redirect()->route('manager.events.memories', $event)
            ->with('status', $label.': notification sent to '.EventMemories::recipients($event).' phones.');
    }
}
