<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * The two files that let a phone open campbuddy.club links in the app:
 * Android App Links (assetlinks.json) and iOS Universal Links
 * (apple-app-site-association). Both are served as JSON, never redirected.
 */
class WellKnownController extends Controller
{
    public function assetLinks(): JsonResponse
    {
        $fingerprints = config('mobile_app.android_sha256');

        $body = $fingerprints === [] ? [] : [[
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target' => [
                'namespace' => 'android_app',
                'package_name' => config('mobile_app.app_id'),
                'sha256_cert_fingerprints' => $fingerprints,
            ],
        ]];

        return response()->json($body)->header('Cache-Control', 'public, max-age=3600');
    }

    public function appleAppSiteAssociation(): JsonResponse
    {
        $team = config('mobile_app.apple_team_id');

        $body = ['applinks' => [
            'details' => $team ? [[
                'appIDs' => [$team.'.'.config('mobile_app.app_id')],
                'components' => [
                    ['/' => '/event/*'],
                    ['/' => '/'],
                ],
            ]] : [],
        ]];

        return response()->json($body)->header('Cache-Control', 'public, max-age=3600');
    }
}
