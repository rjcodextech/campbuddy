<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Support\ConditionalJson;
use App\Support\DataVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * GET /api/v1/events/{slug}/data-version — the fingerprint of what this
 * event's pages show. An open or installed app asks every few minutes and
 * when it comes back to the foreground, and refreshes itself when the
 * answer differs from the page it's showing (data-freshness.js). Tiny, and
 * revalidated on every ask (an ETag, so "nothing new" is an empty 304): a
 * CDN in front may hold it for at most a few seconds (ConditionalJson).
 * Nothing here is ever cached long — the whole point is that it is current.
 */
class DataVersionController extends Controller
{
    public function __invoke(Request $request, Event $event): Response
    {
        $version = DataVersion::for($event);

        return ConditionalJson::respond($request, json_encode(['version' => $version]), 'W/"'.$version.'"', 20);
    }
}
