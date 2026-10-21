<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureEventManager;
use App\Support\EventBroadcasts;
use App\Support\ManagerActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** An event manager's "Send a live announcement" on their event's Notifications tab; logged like every change. */
class BroadcastController extends Controller
{
    use ResolvesManagedEvent;

    public function store(Request $request, string $eventId): RedirectResponse
    {
        $event = $this->managedEvent($request, $eventId);
        $manager = $request->user(EnsureEventManager::GUARD);
        [$kind, $message] = EventBroadcasts::fromRequest($request, $event);
        EventBroadcasts::send($event, $kind, $message, 'manager: '.$manager->name);
        ManagerActivity::record($manager, $event, 'notifications', 'added', 'Sent the announcement '.ManagerActivity::quote($message['title']));

        return redirect()->route('manager.events.notifications', $event)->with('status', "Sent: “{$message['title']}”.");
    }
}
