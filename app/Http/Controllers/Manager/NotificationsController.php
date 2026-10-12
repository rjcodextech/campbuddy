<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** An event manager's Notifications tab: the admin's page, for their own events. */
class NotificationsController extends Controller
{
    use ResolvesManagedEvent;

    public function __invoke(Request $request, string $eventId): View
    {
        return view('manager.events.notifications', ['event' => $this->managedEvent($request, $eventId)]);
    }
}
