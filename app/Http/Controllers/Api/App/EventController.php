<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Support\AppEventApi;
use App\Support\EventMemories;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The native app's read-only event data: GET /api/v1/app/events and
 * /app/events/{slug}/… . Public, like the website pages: only live, visible
 * events (the memories page also stays open after archiving, like the web).
 * Every answer carries an ETag, so a phone that already holds it gets a 304.
 */
class EventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $events = AppEventApi::listed($request->query('country'), $request->query('include') !== 'live')
            ->map(fn (Event $e) => AppEventApi::card($e))->all();

        return $this->json($request, ['data' => $events]);
    }

    public function show(Request $request, Event $event): JsonResponse
    {
        return $this->json($request, ['data' => AppEventApi::detail($event)]);
    }

    public function sessions(Request $request, Event $event): JsonResponse
    {
        return $this->json($request, ['data' => AppEventApi::sessions($event)]);
    }

    public function speakers(Request $request, Event $event): JsonResponse
    {
        return $this->json($request, ['data' => AppEventApi::speakers($event)]);
    }

    public function sponsors(Request $request, Event $event): JsonResponse
    {
        return $this->json($request, ['data' => AppEventApi::sponsors($event)]);
    }

    public function organizers(Request $request, Event $event): JsonResponse
    {
        return $this->json($request, ['data' => AppEventApi::organizers($event)]);
    }

    public function deals(Request $request, Event $event): JsonResponse
    {
        return $this->json($request, ['data' => AppEventApi::deals($event)]);
    }

    public function freeSteals(Request $request, Event $event): JsonResponse
    {
        return $this->json($request, ['data' => AppEventApi::freeSteals($event)]);
    }

    public function quests(Request $request, Event $event): JsonResponse
    {
        return $this->json($request, ['data' => AppEventApi::quests($event)]);
    }

    public function tables(Request $request, Event $event): JsonResponse
    {
        return $this->json($request, ['data' => AppEventApi::tables($event)]);
    }

    public function info(Request $request, Event $event): JsonResponse
    {
        return $this->json($request, ['data' => AppEventApi::info($event)]);
    }

    public function guide(Request $request, Event $event): JsonResponse
    {
        return $this->json($request, ['data' => AppEventApi::guide()]);
    }

    public function delay(Request $request, Event $event): JsonResponse
    {
        return $this->json($request, ['data' => AppEventApi::delay($event)], 15);
    }

    /** Memories outlive the archive, so this one is not behind event.public. */
    public function memories(Request $request, Event $event): JsonResponse
    {
        abort_unless($event->is_visible && $event->status !== 'draft' && EventMemories::pageIsPublic($event), 404);

        $links = EventMemories::links($event);

        // An object even when empty, so the apps read it as one.
        return $this->json($request, ['data' => $links === [] ? (object) [] : $links]);
    }

    /**
     * Everything a phone needs to follow an event offline, in one call. With
     * ?since=<data_version> a phone that is already current gets 304.
     */
    public function bundle(Request $request, Event $event): JsonResponse
    {
        $version = \App\Support\DataVersion::for($event);

        if ($request->query('since') === $version) {
            return response()->json(null, 304)->header('Cache-Control', 'public, max-age=15');
        }

        return $this->json($request, ['data' => [
            'event' => AppEventApi::detail($event),
            'sessions' => AppEventApi::sessions($event),
            'speakers' => AppEventApi::speakers($event),
            'sponsors' => AppEventApi::sponsors($event),
            'organizers' => AppEventApi::organizers($event),
            'deals' => AppEventApi::deals($event),
            'free_steals' => AppEventApi::freeSteals($event),
            'quests' => AppEventApi::quests($event),
            'contributor_tables' => AppEventApi::tables($event),
            'guide' => AppEventApi::guide(),
            'data_version' => $version,
        ]], 30);
    }

    private function json(Request $request, array $body, int $maxAge = 60): JsonResponse
    {
        $response = response()->json($body, 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ->header('Cache-Control', "public, max-age={$maxAge}")
            ->setEtag(md5(json_encode($body)));
        $response->isNotModified($request);

        return $response;
    }
}
