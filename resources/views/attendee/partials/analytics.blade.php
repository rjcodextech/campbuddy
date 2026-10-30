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
      - Consent first (DPDP Act 2023): nothing loads until the person taps
        "Allow" on the bar this shows (localStorage cb-analytics-consent).
        Any [data-analytics-choice] button (footer, privacy notice) asks again.
      - Excluded domains (campbuddy.club.rjcodex.com, campbuddy.test, 127.0.0.1,
        localhost) never render or execute analytics.
--}}
@php
    $gaId = config('services.google_analytics.measurement_id');
    $excludedHosts = config('analytics.excluded_hosts', [
        'campbuddy.club.rjcodex.com',
        'campbuddy.test',
        '127.0.0.1',
        'localhost',
    ]);
    $currentHost = strtolower((string) request()->getHost());
    $isExcludedHost = in_array($currentHost, $excludedHosts, true)
        || str_ends_with($currentHost, '.test')
        || str_ends_with($currentHost, '.local');
@endphp
@if ($gaId && ! $isExcludedHost)
<script>
    (function (id) {
        var nav = window.navigator;
        if (nav.globalPrivacyControl || nav.doNotTrack === '1') return;

        var host = (window.location && window.location.hostname) ? window.location.hostname.toLowerCase() : '';
        var excludedHosts = @js($excludedHosts);
        if (excludedHosts.indexOf(host) !== -1 || host.endsWith('.test') || host.endsWith('.local')) return;

        // DPDP Act 2023: Google Analytics is loaded only after the person taps
        // "Allow" (stored on this browser). "No thanks" — or no answer yet —
        // means no tag, no cookie, no hit. "Analytics choice" asks again.
        var KEY = 'cb-analytics-consent';
        var read = function () { try { return localStorage.getItem(KEY); } catch (e) { return null; } };
        var write = function (value) { try { localStorage.setItem(KEY, value); } catch (e) { /* private mode: asked again next time */ } };
        var started = false;

        var start = function () {
            if (started) return;
            started = true;
            window['ga-disable-' + id] = false;

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
            @isset($eventSlug)
            gtag('set', { event_slug: @js($eventSlug) });
            window.__CB_EVENT_SLUG = @js($eventSlug);
            @endisset
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
        };

        var button = function (label, className, onClick) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = className;
            b.textContent = label;
            b.addEventListener('click', onClick);
            return b;
        };

        // The question: a small bar at the foot of the screen, above the tab bar.
        var ask = function () {
            if (document.getElementById('cb-consent')) return;
            var bar = document.createElement('section');
            bar.id = 'cb-consent';
            bar.className = 'consent-bar';
            bar.setAttribute('aria-label', 'Analytics choice');
            var text = document.createElement('p');
            text.className = 'consent-bar__text';
            text.appendChild(document.createTextNode('May CampBuddy count page visits with Google Analytics? No names and nothing you type. '));
            var more = document.createElement('a');
            more.href = '/privacy#data-table';
            more.textContent = 'Privacy notice';
            text.appendChild(more);
            var actions = document.createElement('div');
            actions.className = 'consent-bar__actions';
            actions.appendChild(button('Allow', 'btn btn--primary btn--compact', function () { write('granted'); bar.remove(); start(); }));
            actions.appendChild(button('No thanks', 'btn btn--outline btn--compact', function () {
                write('denied');
                bar.remove();
                window['ga-disable-' + id] = true; // a page that had it on stops sending now
            }));
            bar.appendChild(text);
            bar.appendChild(actions);
            document.body.appendChild(bar);
        };

        var ready = function () {
            document.querySelectorAll('[data-analytics-choice]').forEach(function (el) {
                el.hidden = false;
                if (el.tagName === 'BUTTON') el.addEventListener('click', ask);
            });
            if (read() === null) ask();
        };

        if (read() === 'granted') start();
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ready);
        else ready();
    })(@js($gaId));
</script>
@endif
