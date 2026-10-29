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
            'display_override' => ['standalone', 'minimal-ui', 'window-controls-overlay'],
            'orientation' => 'portrait',
            'prefer_related_applications' => false,
            'background_color' => $pwa['background_color'],
            'theme_color' => $pwa['theme_color'],
            'categories' => ['events', 'productivity', 'social'],
            'icons' => [
                ['src' => '/media/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/media/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/media/icons/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'screenshots' => [
                [
                    'src' => '/media/tour/1-home.jpg',
                    'sizes' => '1080x2160',
                    'type' => 'image/jpeg',
                    'form_factor' => 'narrow',
                    'label' => 'Live Schedule and Announcements',
                ],
                [
                    'src' => '/media/tour/2-schedule.jpg',
                    'sizes' => '1080x2160',
                    'type' => 'image/jpeg',
                    'form_factor' => 'narrow',
                    'label' => 'Personalized My Day Planner',
                ],
                [
                    'src' => '/media/tour/3-people.jpg',
                    'sizes' => '1080x2160',
                    'type' => 'image/jpeg',
                    'form_factor' => 'narrow',
                    'label' => 'Attendee Discovery & Networking',
                ],
                [
                    'src' => '/media/tour/5-camp-card.jpg',
                    'sizes' => '1080x2160',
                    'type' => 'image/jpeg',
                    'form_factor' => 'narrow',
                    'label' => 'Digital Camp Card & QR Swap',
                ],
                [
                    'src' => '/media/og-image.png',
                    'sizes' => '1200x630',
                    'type' => 'image/png',
                    'form_factor' => 'wide',
                    'label' => 'CampBuddy Companion App',
                ],
            ],
            // Long-press the installed icon: straight to the day, the people, the Camp Card.
            'shortcuts' => $event ? [
                ['name' => 'My Day', 'url' => route('event.my-day', $event, absolute: false), 'icons' => [['src' => '/media/icons/icon-192.png', 'sizes' => '192x192']]],
                ['name' => 'Explore', 'url' => route('event.explore', $event, absolute: false), 'icons' => [['src' => '/media/icons/icon-192.png', 'sizes' => '192x192']]],
                ['name' => 'Camp Card', 'url' => route('event.camp-card', $event, absolute: false), 'icons' => [['src' => '/media/icons/icon-192.png', 'sizes' => '192x192']]],
            ] : [
                ['name' => 'First-Timer Guide', 'url' => '/guide', 'icons' => [['src' => '/media/icons/icon-192.png', 'sizes' => '192x192']]],
                ['name' => 'Choose WordCamp', 'url' => '/#find-your-camp', 'icons' => [['src' => '/media/icons/icon-192.png', 'sizes' => '192x192']]],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json'], JSON_UNESCAPED_SLASHES);
    }
}
