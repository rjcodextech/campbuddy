@php
    // "Explore | WordCamp Rajasthan 2026 | CampBuddy" — the tab you're on
    // first (that's the part a narrow browser tab still shows), then the
    // event, then the product. explore.js / my-day.js refine it further when
    // an in-page section is switched.
    $documentTitle = collect([$title, $event->display_name, config('campbuddy.name')])->filter()->implode(' | ');

    // For analytics (page_context): which screen, and where the event is in
    // time — so reports can split "used during the event" from "before it".
    $pageType = str_replace(['event.', '-', '.show'], ['', '_', ''], (string) request()->route()?->getName()) ?: 'event_page';
    $today = today();
    $eventPhase = match (true) {
        $event->starts_on === null => 'unknown',
        $today->lt($event->starts_on) => 'before',
        $today->gt($event->ends_on ?? $event->starts_on) => 'after',
        default => 'during',
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="vapid-public-key" content="{{ config('services.vapid.public_key') }}">

    <title>{{ $documentTitle }}</title>
    <meta name="description" content="Your guide to {{ $event->display_name }} — schedule, people, and what to do next.">

    @include('attendee.partials.head-meta', ['manifestUrl' => route('event.manifest', $event, absolute: false), 'tabIcon' => $event->faviconUrl()])

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    {{-- Inter is the app-wide UI font. Playfair Display + JetBrains Mono
    are used nowhere except Camp Card's badge design (components/_camp-card.scss) —
    loaded here in the shared attendee layout since Camp Card is reachable
    from every screen via the bottom nav, so there's no single "Camp Card
    page load" to scope a separate font request to. --}}
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    @include('attendee.partials.analytics', ['eventSlug' => $event->slug])

    @vite(['resources/scss/main.scss', 'resources/js/attendee/app.js'])
</head>
<body class="app-shell" data-page-type="{{ $pageType }}" data-event-phase="{{ $eventPhase }}">
    {{-- --with-nav reserves room under the page for the fixed tab bar. --}}
    <div class="app-frame app-frame--with-nav">
        @include('attendee.partials.desktop-notice')

        <div id="app" data-event-slug="{{ $event->slug }}" data-event-id="{{ $event->id }}">
            {{ $slot }}
        </div>

        @include('attendee.partials.nav')
    </div>

    @include('attendee.templates.shared')
</body>
</html>
