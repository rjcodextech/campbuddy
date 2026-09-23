<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\SessionBookmark;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Bookmarks are local-only by default — this endpoint only
 * exists because a reminder requires the server to know which
 * session to push about. A plain bookmark with no reminder never calls
 * this at all.
 */
class BookmarkController extends Controller
{
    public function store(Request $request, Event $event): JsonResponse
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:64'],
            'session_id' => ['required', 'integer'],
            'reminder_enabled' => ['boolean'],
        ]);

        SessionBookmark::updateOrCreate(
            ['event_id' => $event->id, 'device_id' => $data['device_id'], 'session_id' => $data['session_id']],
            ['reminder_enabled' => $data['reminder_enabled'] ?? true]
        );

        return response()->json(null, 201);
    }

    public function destroy(Request $request, Event $event): JsonResponse
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:64'],
            'session_id' => ['required', 'integer'],
        ]);

        SessionBookmark::where('event_id', $event->id)
            ->where('device_id', $data['device_id'])
            ->where('session_id', $data['session_id'])
            ->delete();

        return response()->json(null, 204);
    }
}
