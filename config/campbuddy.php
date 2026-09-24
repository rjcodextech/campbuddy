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

    // <title> of the WordCamp picker ("/"): "CampBuddy | Your WordCamp companion".
    'tagline' => 'Your WordCamp companion',

    // Web app manifest — what the install prompt and home screen show.
    'pwa' => [
        'name' => 'CampBuddy | Your WordCamp Companion',
        'short_name' => 'CampBuddy',
        'description' => 'The mobile-first companion for WordCamp attendees — what to do next, session planning, Contributor Day matching and a digital Camp Card.',
        'theme_color' => '#c33a19',
        'background_color' => '#fffaf4',
    ],

];
