<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\JsonResponse;

/**
 * The PWA manifest. There is one product identity — "CampBuddy | Your
 * WordCamp Companion", short name "CampBuddy", CampBuddy's own icon — on
 * every page. Installing from an event's page still opens that event
 * directly (start_url), but the name and icon on the home screen are always
 * CampBuddy's: an event's own logo is rarely square, and an install prompt
 * that changes name per page is confusing.
 *
 * Every URL here is a root-relative path, resolved by the browser against the
 * manifest's own URL — so it stays correct whatever APP_URL says or which
 * host/scheme the visitor arrived on.
 */
class ManifestController extends Controller
{
    public function __invoke(?Event $event = null): JsonResponse
    {
        $pwa = config('campbuddy.pwa');
        $startUrl = $event ? route('event.home', $event, absolute: false) : '/';

        return response()->json([
            'id' => $startUrl,
            'name' => $pwa['name'],
            'short_name' => $pwa['short_name'],
            'description' => $pwa['description'],
            'lang' => 'en',
            'start_url' => $startUrl,
            // The whole origin, not just /event/{slug}: the WordCamp picker
            // ("/") and every other event must stay inside the installed app
            // rather than bouncing out to a browser tab.
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => $pwa['background_color'],
            'theme_color' => $pwa['theme_color'],
            'categories' => ['events', 'productivity', 'social'],
            'icons' => [
                ['src' => '/media/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/media/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/media/icons/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json'], JSON_UNESCAPED_SLASHES);
    }
}
