<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/app-config — what the native app asks on every start: the
 * oldest build still allowed, where to update, and which native features are
 * switched on. Public and cacheable for a minute.
 */
class AppConfigController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $config = config('mobile_app');

        return response()
            ->json([
                'min_version' => $config['min_version'],
                'store_url' => $config['store_url'],
                'features' => $config['features'],
            ])
            ->header('Cache-Control', 'public, max-age=60');
    }
}
