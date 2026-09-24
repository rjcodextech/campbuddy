{{--
    Icon + install ("PWA") tags shared by the WordCamp picker and every event
    page, so they can't drift apart.

    Expects:
      $manifestUrl — this page's manifest (the global one on the picker, an
                     event's own — same name, event start_url — on event pages)
      $tabIcon     — optional: the browser-tab icon (an event's own favicon)

    The home-screen label on iOS comes from apple-mobile-web-app-title, NOT the
    <title> — without it iOS would truncate "CampBuddy | Your WordCamp
    companion" into something ugly. Android/desktop use the manifest's
    short_name. Both are "CampBuddy".
--}}
@php($pwa = config('campbuddy.pwa'))
<meta name="theme-color" content="{{ $pwa['theme_color'] }}">
<meta name="application-name" content="{{ $pwa['short_name'] }}">
<meta name="apple-mobile-web-app-title" content="{{ $pwa['short_name'] }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">

{{-- Bumped by the admin's "Purge cache": see cache-version.js. --}}
<meta name="campbuddy-cache-version" content="{{ \App\Support\CacheVersion::current() }}">

@if (! empty($tabIcon))
    <link rel="icon" href="{{ $tabIcon }}">
@else
    <link rel="icon" type="image/png" sizes="32x32" href="/media/icons/favicon-32.png">
@endif
<link rel="apple-touch-icon" href="/media/icons/apple-touch-icon.png">
<link rel="manifest" href="{{ $manifestUrl }}">
