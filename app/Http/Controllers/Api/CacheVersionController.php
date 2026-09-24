<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\CacheVersion;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/cache-version — the number an admin's "Purge cache" bumps.
 * An installed app that's been sitting open asks for it when it returns to
 * the foreground, and reloads if it's newer than the one it last saw
 * (cache-version.js). Never cached, by the browser or a CDN: the whole point
 * is that it's always current.
 */
class CacheVersionController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()
            ->json(['version' => CacheVersion::current()])
            ->header('Cache-Control', 'no-store, max-age=0');
    }
}
