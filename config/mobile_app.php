<?php

/*
|--------------------------------------------------------------------------
| Native app (Capacitor shell) — server-side settings
|--------------------------------------------------------------------------
| Everything here is optional: unset = the matching endpoint answers with an
| empty/safe value and the website behaves exactly as before.
*/
return [
    // Reverse-DNS id, the same in both stores and in capacitor.config.ts.
    'app_id' => env('MOBILE_APP_ID', 'club.campbuddy.app'),

    // Android App Links: SHA-256 certificate fingerprints (comma separated,
    // "AA:BB:…"). Play App Signing's key goes here as well as the upload key.
    'android_sha256' => array_values(array_filter(array_map('trim', explode(',', (string) env('MOBILE_ANDROID_SHA256', ''))))),

    // iOS Universal Links: the Apple Developer Team ID.
    'apple_team_id' => env('MOBILE_APPLE_TEAM_ID'),

    // Oldest build each platform may still run. Anything older sees an
    // "update the app" screen (the only server-side kill switch for a shell
    // that cannot be hot-patched). Semver, e.g. "1.0.0".
    'min_version' => [
        'android' => env('MOBILE_MIN_VERSION_ANDROID', '0.0.1'),
        'ios' => env('MOBILE_MIN_VERSION_IOS', '0.0.1'),
    ],

    'store_url' => [
        'android' => env('MOBILE_STORE_URL_ANDROID'),
        'ios' => env('MOBILE_STORE_URL_IOS'),
    ],

    // Server-controlled switches the app reads on start (no store release
    // needed to turn a native feature off).
    'features' => [
        'native_push' => (bool) env('MOBILE_FEATURE_NATIVE_PUSH', true),
        'local_reminders' => (bool) env('MOBILE_FEATURE_LOCAL_REMINDERS', true),
    ],
];
