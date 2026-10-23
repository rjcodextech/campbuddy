<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppRelease;
use App\Support\AppReleases;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Admin → Mobile app → App updates: upload an Android APK (in pieces, by
 * resources/js/app-release-upload.js), publish it to every APK install,
 * withdraw it, or delete a draft. App\Support\AppReleases does the checks.
 */
class AppReleaseController extends Controller
{
    /** One piece of an upload: the raw bytes, with X-Upload-Id and X-Chunk-Index headers. */
    public function chunk(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', \App\Models\Event::class);

        try {
            $next = AppReleases::receiveChunk((string) $request->header('X-Upload-Id'), (int) $request->header('X-Chunk-Index', -1), $request->getContent());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['next' => $next]);
    }

    /** The upload is complete: check it and keep it as a draft. */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', \App\Models\Event::class);
        $data = $request->validate([
            'upload' => ['required', 'string', 'size:36'],
            'sha256' => ['required', 'string', 'regex:/^[a-fA-F0-9]{64}$/'],
            'notes_en' => ['nullable', 'string', 'max:500'],
            'notes_hi' => ['nullable', 'string', 'max:500'],
            'required' => ['nullable', 'boolean'],
        ]);

        try {
            $release = AppReleases::finishUpload($data['upload'], $data['sha256'], $data, $request->user());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        session()->flash('status', "Version {$release->version_name} (build {$release->version_code}) is checked and saved as a draft. Publish it to send it to phones.");

        return response()->json(['redirect' => route('admin.mobile.index', ['tab' => 'updates'])]);
    }

    public function publish(Request $request, AppRelease $release): RedirectResponse
    {
        Gate::authorize('viewAny', \App\Models\Event::class);
        abort_unless($release->status !== AppRelease::LIVE, 409);
        AppReleases::publish($release, $request->user());

        return $this->back("Version {$release->version_name} is live. Phones with the APK app get it when they next open the app (and a push now, where push is set up).");
    }

    public function withdraw(AppRelease $release): RedirectResponse
    {
        Gate::authorize('viewAny', \App\Models\Event::class);
        abort_unless($release->status === AppRelease::LIVE, 409);
        AppReleases::withdraw($release);

        return $this->back("Version {$release->version_name} is no longer offered. Phones that already have it keep it.");
    }

    public function destroy(AppRelease $release): RedirectResponse
    {
        Gate::authorize('viewAny', \App\Models\Event::class);
        abort_if($release->status === AppRelease::LIVE, 409);
        AppReleases::delete($release);

        return $this->back("Version {$release->version_name} is deleted.");
    }

    public function download(AppRelease $release): BinaryFileResponse
    {
        Gate::authorize('viewAny', \App\Models\Event::class);
        abort_unless(is_file(AppReleases::path($release)), 404);

        return response()->download(AppReleases::path($release), $release->fileName(), ['Content-Type' => 'application/vnd.android.package-archive']);
    }

    private function back(string $status): RedirectResponse
    {
        return redirect()->route('admin.mobile.index', ['tab' => 'updates'])->with('status', $status);
    }
}
