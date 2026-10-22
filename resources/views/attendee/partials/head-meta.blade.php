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
{{-- iOS's own name for the line above — older iOS versions launch a
     home-screen install full-screen only when they see this one. --}}
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
{{-- Stops iOS Safari turning digit runs (dates, ids, times) into blue phone
     links. Real phone numbers stay tappable — Event Info renders those as
     explicit tel: links. --}}
<meta name="format-detection" content="telephone=no">

{{-- Bumped by the admin's "Purge cache": see cache-version.js. --}}
<meta name="campbuddy-cache-version" content="{{ \App\Support\CacheVersion::current() }}">

@if (! empty($tabIcon))
    <link rel="icon" href="{{ $tabIcon }}">
@else
    <link rel="icon" type="image/png" sizes="32x32" href="/media/icons/favicon-32.png">
@endif
<link rel="apple-touch-icon" href="/media/icons/apple-touch-icon.png">
{{-- iPhone launch screens: the paper ground with the logo, one per screen size, so an installed
     CampBuddy opens on its own screen instead of a white flash (public/media/splash). --}}
<link rel="apple-touch-startup-image" href="/media/splash/splash-1320x2868.png" media="(device-width: 440px) and (device-height: 956px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)">
<link rel="apple-touch-startup-image" href="/media/splash/splash-1290x2796.png" media="(device-width: 430px) and (device-height: 932px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)">
<link rel="apple-touch-startup-image" href="/media/splash/splash-1206x2622.png" media="(device-width: 402px) and (device-height: 874px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)">
<link rel="apple-touch-startup-image" href="/media/splash/splash-1179x2556.png" media="(device-width: 393px) and (device-height: 852px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)">
<link rel="apple-touch-startup-image" href="/media/splash/splash-1284x2778.png" media="(device-width: 428px) and (device-height: 926px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)">
<link rel="apple-touch-startup-image" href="/media/splash/splash-1170x2532.png" media="(device-width: 390px) and (device-height: 844px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)">
<link rel="apple-touch-startup-image" href="/media/splash/splash-1125x2436.png" media="(device-width: 375px) and (device-height: 812px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)">
<link rel="apple-touch-startup-image" href="/media/splash/splash-1242x2688.png" media="(device-width: 414px) and (device-height: 896px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)">
<link rel="apple-touch-startup-image" href="/media/splash/splash-828x1792.png" media="(device-width: 414px) and (device-height: 896px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)">
<link rel="apple-touch-startup-image" href="/media/splash/splash-1080x2340.png" media="(device-width: 360px) and (device-height: 780px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)">
<link rel="apple-touch-startup-image" href="/media/splash/splash-750x1334.png" media="(device-width: 375px) and (device-height: 667px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)">
<link rel="apple-touch-startup-image" href="/media/splash/splash-1242x2208.png" media="(device-width: 414px) and (device-height: 736px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)">
<link rel="manifest" href="{{ $manifestUrl }}">
