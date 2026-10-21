<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNativePushTokenRequest;
use App\Models\Event;
use App\Models\NativePushToken;
use App\Support\EventBroadcasts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Registers and removes the native app push token. Like the Web Push
 * endpoint it is anonymous (no login) and is asked for only after the person
 * said yes to notifications.
 */
class NativePushTokenController extends Controller
{
    public function store(StoreNativePushTokenRequest $request, Event $event): JsonResponse
    {
        $isNew = ! NativePushToken::where('token', $request->input('token'))->exists();
        $held = NativePushToken::where('event_id', $event->id)->where('device_id', $request->input('device_id'))->count();

        // One install holds one token; a handful covers token refreshes and
        // keeps this anonymous endpoint from growing the table.
        abort_if($isNew && $held >= 3, 422, 'Too many push tokens on this device.');

        NativePushToken::updateOrCreate(
            ['token' => $request->input('token')],
            [
                'event_id' => $event->id,
                'device_id' => $request->input('device_id'),
                'platform' => $request->input('platform'),
                'app_version' => $request->input('app_version'),
            ]
        );

        return response()->json(null, 201);
    }

    public function destroy(Request $request, Event $event): JsonResponse
    {
        $data = $request->validate(['device_id' => ['required', 'string', 'max:64']]);

        NativePushToken::where('event_id', $event->id)->where('device_id', $data['device_id'])->delete();
        EventBroadcasts::forget($event, $data['device_id']);

        return response()->json(null, 204);
    }
}
