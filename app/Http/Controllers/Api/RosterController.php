<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/events/{slug}/roster — the ingested Attendees-page
 * mirror. Paginated and never includes suppressed
 * entries.
 */
class RosterController extends Controller
{
    public function __invoke(Event $event, Request $request): JsonResponse
    {
        $roster = $event->attendeeRoster()
            ->where('is_suppressed', false)
            ->orderBy('name')
            ->paginate(50)
            ->through(fn ($entry) => [
                'name' => $entry->name,
                'gravatar_url' => $entry->gravatar_url,
                'links' => $entry->links,
            ]);

        return response()->json($roster);
    }
}
