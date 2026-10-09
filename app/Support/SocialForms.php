<?php

namespace App\Support;

use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** The webhook and Publish requests of the Social media page, for the admin and managers alike. */
class SocialForms
{
    /** @return string what changed, for the status line and the activity log */
    public static function saveWebhook(Request $request, Event $event): string
    {
        if ($request->boolean('remove')) {
            SocialPublisher::setWebhook($event, null);

            return 'Publish webhook removed';
        }

        $url = trim((string) $request->validate(['webhook' => ['required', 'string', 'max:500', 'url']])['webhook']);
        if (! SocialPublisher::allowed($url)) {
            throw ValidationException::withMessages(['webhook' => 'Use an https webhook from Make.com, Zapier, n8n or Pipedream.']);
        }
        SocialPublisher::setWebhook($event, $url);

        return 'Publish webhook set ('.SocialPublisher::masked($event->fresh()).')';
    }

    public static function publish(Request $request, Event $event, string $by): JsonResponse
    {
        $data = $request->validate([
            'image' => ['required', 'file', 'mimetypes:image/png', 'max:8192'],
            'title' => ['nullable', 'string', 'max:120'],
            'caption' => ['required', 'string', 'max:3000'],
            'kind' => ['required', 'string', 'max:40'],
        ]);

        $result = SocialPublisher::publish($event, $request->file('image'), (string) ($data['title'] ?? ''), $data['caption'], $data['kind'], $by);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }
}
