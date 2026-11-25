<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\CatalogVersion;
use App\Support\ConditionalJson;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * GET /api/v1/events-version — fingerprint of the public WordCamp picker
 * list. Open home pages and the native app picker compare it and refresh
 * automatically when a cron adds, promotes, archives, or updates an event.
 */
class CatalogVersionController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $version = CatalogVersion::current();

        return ConditionalJson::respond($request, json_encode(['version' => $version]), 'W/"'.$version.'"', 20);
    }
}
