<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventFeedback;
use App\Support\EventTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The after-event thank-you card's rating. Only once the event is over; the
 * same device sending again changes its earlier answer instead of adding one.
 * The `website` field is a honeypot, answered 201 and not stored.
 */
class EventFeedbackController extends Controller
{
    public function store(Request $request, Event $event): JsonResponse
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:100'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:500'],
            'website' => ['nullable', 'string', 'max:500'],
        ]);

        abort_unless(EventTime::isOver($event), 422, 'Feedback opens once the event is over.');

        if (filled($data['website'] ?? null)) {
            return response()->json(null, 201);
        }

        EventFeedback::updateOrCreate(
            ['event_id' => $event->id, 'device_hash' => EventFeedback::deviceHash($event, $data['device_id'])],
            ['rating' => $data['rating'], 'comment' => filled($data['comment'] ?? null) ? trim($data['comment']) : null]
        );

        return response()->json(null, 201);
    }
}
