<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Support\ConditionalJson;
use App\Support\EventBroadcasts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * The attendee side of live announcements (App\Support\EventBroadcasts):
 *   - POST …/seen: "this device has the event open" (the app, the PWA, the
 *     website), so an announcement reaches it — only the anonymous device id;
 *   - GET  …/live-notice: the announcement sent in the last half hour, for the
 *     banner on Home. Shared by everyone, so built once every 15 seconds and
 *     answered with an ETag (an unchanged notice costs a 304).
 */
class EventLiveController extends Controller
{
    public function seen(Request $request, Event $event): JsonResponse
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'min:8', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'platform' => ['nullable', 'string', 'in:web,pwa,android,ios'],
        ]);

        EventBroadcasts::seen($event, $data['device_id'], $data['platform'] ?? 'web');

        return response()->json(null, 204);
    }

    public function notice(Request $request, Event $event): Response
    {
        $notice = Cache::remember("event:{$event->id}:live-notice", 15, fn () => EventBroadcasts::current($event));
        $json = json_encode(['data' => $notice]);

        return ConditionalJson::respond($request, $json, 'W/"n'.($notice['id'] ?? 0).'"', 15);
    }
}
