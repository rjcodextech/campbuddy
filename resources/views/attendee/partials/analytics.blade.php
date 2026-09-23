{{--
    Google Analytics 4 — attendee app only (the admin panel is deliberately
    left out: organizers' own clicks would drown the attendee numbers).
    Included in <head> by layouts/attendee.blade.php and welcome.blade.php.

    Renders nothing unless GA_MEASUREMENT_ID is set. Everything after the tag
    loads — events, the allowlist — lives in resources/js/attendee/analytics.js;
    this only bootstraps gtag and decides what the page-level hits may carry.

    Passed in by the including layout:
      $eventSlug  optional — the event the page belongs to; becomes a default
                  parameter on every hit so all reports can be split by event.

    Privacy (spec §8.5), all decided here so no later hit can undo it:
      - page_location / page_referrer are sent with the query string stripped
        (except campaign + tab params). Without this GA4 would ship whatever
        is in the URL — e.g. the attendee name searched on the roster-removal
        page — to Google as part of every hit.
      - Google signals and ad personalisation are off; this is product
        analytics, not advertising.
      - Browsers that send Global Privacy Control / Do Not Track get no tag
        at all.
--}}
@php($gaId = config('services.google_analytics.measurement_id'))
@if ($gaId)
<script>
    (function (id) {
        var nav = window.navigator;
        if (nav.globalPrivacyControl || nav.doNotTrack === '1') return;

        // Query params that are safe (and useful) to keep on a reported URL.
        var keep = /^(utm_[a-z_]+|gclid|dclid|fbclid|msclkid|tab)$/i;
        var scrub = function (raw) {
            try {
                var url = new URL(raw);
                Array.from(url.searchParams.keys()).forEach(function (key) {
                    if (!keep.test(key)) url.searchParams.delete(key);
                });
                url.hash = '';
                return url.href;
            } catch (e) {
                return '';
            }
        };

        window.dataLayer = window.dataLayer || [];
        window.gtag = function () { window.dataLayer.push(arguments); };

        gtag('js', new Date());
        gtag('config', id, {
            page_location: scrub(location.href),
            page_referrer: scrub(document.referrer),
            display_mode: (window.matchMedia('(display-mode: standalone)').matches || nav.standalone === true) ? 'standalone' : 'browser',
            @isset($eventSlug)
            event_slug: @js($eventSlug),
            @endisset
            // Beacon transport so a hit fired as the page unloads (nav-tab
            // taps, "Clear my data" reloading the page) isn't cancelled.
            transport_type: 'beacon',
            allow_google_signals: false,
            allow_ad_personalization_signals: false,
            @if (config('app.debug'))
            debug_mode: true,
            @endif
        });

        var script = document.createElement('script');
        script.async = true;
        script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(id);
        document.head.appendChild(script);
    })(@js($gaId));
</script>
@endif
