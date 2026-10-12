<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Support\AnalyticsReport;
use App\Support\UsagePost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Admin → Analytics → Usage post: the numbers for one WordCamp, as JSON for usage-post.js (App\Support\UsagePost). */
class UsagePostController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event' => ['required', 'string', 'max:191'],
            'stream' => ['nullable', 'string', 'max:40'],
            'fresh' => ['nullable', 'boolean'],
        ]);
        $event = Event::where('slug', $data['event'])->firstOrFail();

        if ($problem = AnalyticsReport::setupProblem()) {
            return response()->json(['ready' => false, 'message' => $problem], 422);
        }

        try {
            $streams = AnalyticsReport::streams();
            $stream = array_key_exists((string) ($data['stream'] ?? ''), $streams) ? $data['stream'] : array_key_first($streams);
            if ($stream === null) {
                return response()->json(['ready' => false, 'message' => 'The GA property has no data streams.'], 422);
            }

            return response()->json(UsagePost::build($event, (string) $stream, (bool) ($data['fresh'] ?? false)));
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['ready' => false, 'message' => AnalyticsReport::explain($e)], 502);
        }
    }
}
