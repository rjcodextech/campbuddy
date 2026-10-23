<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppRelease;
use App\Support\AppReleases;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Android app updates outside the Play Store (App\Support\AppReleases): the APK app asks
 * whether a newer build is live, then downloads it from its versioned address.
 */
class AppUpdateController extends Controller
{
    /** GET /api/v1/app/update?package=club.campbuddy.app&code=15 */
    public function show(Request $request): JsonResponse
    {
        $package = (string) $request->query('package', config('mobile_app.app_id'));
        $package = in_array($package, AppReleases::allowedPackages(), true) ? $package : (string) config('mobile_app.app_id');
        $code = max(0, (int) $request->query('code', 0));

        return response()->json(AppReleases::forApi($package, $code))->header('Cache-Control', 'no-cache, private');
    }

    /**
     * GET /api/v1/app/releases/{package}/{code}.apk — a live release's file. The address never
     * changes for a build, so the CDN may keep it; Range requests let a phone resume a download.
     */
    public function download(string $package, int $code): BinaryFileResponse
    {
        $release = AppRelease::where('package', $package)->where('version_code', $code)->where('status', AppRelease::LIVE)->firstOrFail();
        $path = AppReleases::path($release);
        abort_unless(is_file($path), 404);

        return response()->download($path, $release->fileName(), [
            'Content-Type' => 'application/vnd.android.package-archive',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** GET /app/android — the newest APK, for sharing with people who do not have the app yet. */
    public function latest(): RedirectResponse
    {
        $release = AppReleases::latest((string) config('mobile_app.app_id'));
        abort_if($release === null, 404);

        return redirect()->away($release->downloadUrl());
    }
}
