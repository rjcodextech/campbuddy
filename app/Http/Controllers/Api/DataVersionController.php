<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Support\DataVersion;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/events/{slug}/data-version — the fingerprint of what this
 * event's pages show. An open or installed app asks every few minutes and
 * when it comes back to the foreground, and refreshes itself when the
 * answer differs from the page it's showing (data-freshness.js). Tiny and
 * never cached, so it's always current.
 */
class DataVersionController extends Controller
{
    public function __invoke(Event $event): JsonResponse
    {
        return response()
            ->json(['version' => DataVersion::for($event)])
            ->header('Cache-Control', 'no-store, max-age=0');
    }
}
