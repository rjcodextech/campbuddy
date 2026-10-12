<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Admin → Event → Notifications: every push about the event and its state (App\Support\EventNotifications). */
class NotificationsController extends Controller
{
    public function __invoke(Event $event): View
    {
        Gate::authorize('viewAny', Event::class);

        return view('admin.events.notifications', ['event' => $event]);
    }
}
