<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Support\EventBroadcasts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Admin → Event → Notifications → "Send a live announcement" (App\Support\EventBroadcasts). */
class BroadcastController extends Controller
{
    public function store(Request $request, Event $event): RedirectResponse
    {
        Gate::authorize('update', $event);
        [$kind, $message] = EventBroadcasts::fromRequest($request, $event);
        EventBroadcasts::send($event, $kind, $message, 'admin: '.$request->user()->name);

        return redirect()->route('admin.events.notifications', $event)->with('status', "Sent: “{$message['title']}”.");
    }
}
