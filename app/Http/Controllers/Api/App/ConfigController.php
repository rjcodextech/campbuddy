<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Support\MobileAppConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/app/config — the native app's server-driven settings (tabs, home
 * layout, texts, colours, banner, switches). Public, cacheable for a minute,
 * answers 304 when the app already holds this version.
 */
class ConfigController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $config = MobileAppConfig::published();

        // A banner past its end time is simply not sent.
        if (! empty($config['banner']['until']) && strtotime($config['banner']['until']) < now()->timestamp) {
            $config['banner'] = null;
        }

        $response = response()->json($config, 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ->header('Cache-Control', 'public, max-age=60')
            ->setEtag(md5(json_encode($config)));
        $response->isNotModified($request);

        return $response;
    }
}
