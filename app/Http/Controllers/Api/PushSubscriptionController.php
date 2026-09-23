<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePushSubscriptionRequest;
use App\Models\Event;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/push/subscribe — registers a Web Push endpoint for a
 * device. Requested only right after a bookmark, never on
 * page load (enforced client-side, N1).
 */
class PushSubscriptionController extends Controller
{
    public function __invoke(StorePushSubscriptionRequest $request, Event $event): JsonResponse
    {
        PushSubscription::updateOrCreate(
            ['endpoint' => $request->input('endpoint')],
            [
                'event_id' => $event->id,
                'device_id' => $request->input('device_id'),
                'p256dh_key' => $request->input('keys.p256dh'),
                'auth_key' => $request->input('keys.auth'),
            ]
        );

        return response()->json(null, 201);
    }
}
