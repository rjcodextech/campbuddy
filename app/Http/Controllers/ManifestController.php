<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\JsonResponse;

/**
 * Per-event PWA manifest — install-to-home-screen picks up that event's
 * own name/colors/icon (§3.2), not a generic CampBuddy manifest.
 */
class ManifestController extends Controller
{
    public function __invoke(Event $event): JsonResponse
    {
        $icon = $event->logoUrl() ?? url('/media/icon.svg');

        return response()->json([
            'name' => $event->display_name,
            'short_name' => $event->short_name ?? $event->display_name,
            'start_url' => route('event.home', $event),
            'scope' => route('event.home', $event),
            'display' => 'standalone',
            'background_color' => '#fffaf4',
            'theme_color' => $event->primary_color ?? '#c33a19',
            'icons' => [
                ['src' => $icon, 'sizes' => 'any', 'type' => 'image/svg+xml'],
            ],
        ])->header('Content-Type', 'application/manifest+json');
    }
}
