<?php

/*
|--------------------------------------------------------------------------
| CampBuddy brand strings
|--------------------------------------------------------------------------
| Fixed on purpose rather than derived from APP_NAME: page titles and the
| PWA install name are product copy, not per-environment settings, so a
| mis-set APP_NAME on a server can't change what a phone's home screen says.
|
| The site title's tagline and the PWA name differ only by capitalisation
| ("companion" vs "Companion") — both are the exact strings the product
| owner specified.
*/
return [

    'name' => 'CampBuddy',

    /*
    | Who is responsible for attendees' personal data (the privacy notice and terms pages, DPDPA Act 2023).
    | The grievance address is where anyone can ask to see, correct or erase their data, or complain.
    */
    'legal' => [
        'entity' => env('LEGAL_ENTITY', 'WPSimplified'),
        'grievance_email' => env('LEGAL_GRIEVANCE_EMAIL', 'hello@wpsimplified.in'),
        // Promised time to answer a request or complaint.
        'reply_days' => 30,
        // The date the notice was last changed (shown on the page).
        'updated' => '21 October 2026',
    ],

    // <title> of the WordCamp picker ("/"): "CampBuddy | Your WordCamp companion".
    'tagline' => 'Your WordCamp companion',

    // Web app manifest — what the install prompt and home screen show.
    'pwa' => [
        'name' => 'CampBuddy | Your WordCamp Companion',
        'short_name' => 'CampBuddy',
        'description' => 'The mobile-first companion for WordCamp attendees — what to do next, session planning, Contributor Day matching and a digital Camp Card.',
        'theme_color' => '#ff4d2d',
        'background_color' => '#fafafd',
    ],

    /*
    | API rate limits, per minute. Counted per phone (the app's anonymous
    | device id, or a discovery owner token for discovery writes) so people
    | sharing an internet address — a venue's wifi, or a mobile network that
    | puts thousands of phones behind one address — don't use up each other's
    | allowance. The per-address numbers are only a backstop against abuse, set
    | high enough for a full venue. Requests that carry no device id (bots, or
    | a very old copy of the app) get the smaller "anonymous" allowance.
    |
    | Raise any of these from .env without a code change if a big event needs it.
    */
    'rate_limits' => [
        'device_reads' => (int) env('RATE_LIMIT_DEVICE_READS', 120),
        'device_writes' => (int) env('RATE_LIMIT_DEVICE_WRITES', 40),
        'address_reads' => (int) env('RATE_LIMIT_ADDRESS_READS', 3000),
        'address_writes' => (int) env('RATE_LIMIT_ADDRESS_WRITES', 600),
        'anonymous_reads' => (int) env('RATE_LIMIT_ANONYMOUS_READS', 300),
        'anonymous_writes' => (int) env('RATE_LIMIT_ANONYMOUS_WRITES', 60),
    ],

    /*
    | The Studio folder CampBuddy shares with WPSimplified: every audio, video and
    | creative file, kept outside the web root so none of it goes to git or the FTP
    | upload. Social media → Reels → Music offers its music library (Audio/Library,
    | App\Support\StudioMusic). STUDIO_PATH wins; otherwise the first of these
    | folders that exists (local, then the server); with none, the option is left out.
    */
    'studio' => [
        'path' => env('STUDIO_PATH'),
        'candidates' => [
            'E:/Sites/InHouse/wpsimplified/Studio',
            '/home/mzpjfuem/domains/Studio',
        ],
    ],

];
