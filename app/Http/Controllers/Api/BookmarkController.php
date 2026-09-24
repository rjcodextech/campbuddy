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
    /** No real schedule has more sessions than this worth a reminder on one device. */
    private const MAX_PER_DEVICE = 150;

    public function store(Request $request, Event $event): JsonResponse
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:64'],
            'session_id' => ['required', 'integer', 'min:1'],
            'reminder_enabled' => ['boolean'],
        ]);

        // An anonymous endpoint must not be a way to fill the table: one
        // device can hold reminders for a whole schedule, not unbounded rows.
        $held = SessionBookmark::where('event_id', $event->id)->where('device_id', $data['device_id'])->count();
        $isNew = ! SessionBookmark::where('event_id', $event->id)
            ->where('device_id', $data['device_id'])
            ->where('session_id', $data['session_id'])
            ->exists();

        abort_if($isNew && $held >= self::MAX_PER_DEVICE, 422, 'Too many reminders on this device.');

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
            'session_id' => ['required', 'integer', 'min:1'],
        ]);

        SessionBookmark::where('event_id', $event->id)
            ->where('device_id', $data['device_id'])
            ->where('session_id', $data['session_id'])
            ->delete();

        return response()->json(null, 204);
    }
}
