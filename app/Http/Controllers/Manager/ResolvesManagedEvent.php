<?php

namespace App\Http\Controllers\Manager;

use App\Http\Middleware\EnsureEventManager;
use App\Models\Event;
use Illuminate\Http\Request;

/**
 * Finds an event only among the signed-in manager's own — an event that isn't
 * theirs is a 404, exactly like one that doesn't exist.
 */
trait ResolvesManagedEvent
{
    private function managedEvent(Request $request, string $eventId): Event
    {
        return $request->user(EnsureEventManager::GUARD)->events()->whereKey($eventId)->firstOrFail();
    }
}
