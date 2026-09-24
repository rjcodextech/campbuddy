<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Serves storage/app/public at /storage/{path} — the fallback for hosts where
 * the `public/storage` symlink is missing or unusable.
 *
 * `php artisan storage:link` creates the link inside Laravel's own public/
 * folder, which is NOT the web root on shared/cPanel hosting when the app
 * sits above public_html (the docroot workaround in the deployment docs). On
 * such a host every uploaded or auto-fetched event logo 404s. When the link
 * does work, the web server serves the file itself and this route never runs.
 *
 * Only image types are ever served: nothing else belongs on the public disk,
 * and this keeps a stray upload from being served as HTML/script.
 */
class PublicStorageController extends Controller
{
    private const TYPES = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        'ico' => 'image/x-icon',
        'svg' => 'image/svg+xml',
    ];

    public function __invoke(Request $request, string $path): BinaryFileResponse
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $disk = Storage::disk('public');

        try {
            $found = isset(self::TYPES[$extension]) && $disk->exists($path);
        } catch (Throwable) {
            // Path traversal ("../"), unreadable path, etc. — same answer as "not there".
            $found = false;
        }

        abort_unless($found, 404);

        $headers = [
            'Content-Type' => self::TYPES[$extension],
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ];

        // An SVG can carry <script>. Opened directly (not through an <img>) it
        // would run on this origin, so a sandboxed, script-less policy goes with it.
        if ($extension === 'svg') {
            $headers['Content-Security-Policy'] = "default-src 'none'; style-src 'unsafe-inline'; sandbox";
        }

        return response()->file($disk->path($path), $headers)->setAutoEtag();
    }
}
