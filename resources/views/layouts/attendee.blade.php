<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#c33a19">
    <meta name="vapid-public-key" content="{{ config('services.vapid.public_key') }}">

    <title>{{ $event->display_name }} — {{ config('app.name') }}</title>
    <meta name="description" content="Your guide to {{ $event->display_name }} — schedule, people, and what to do next.">

    <link rel="icon" href="{{ $event->faviconUrl() ?? '/media/favicon.png' }}">
    <link rel="apple-touch-icon" href="{{ $event->logoUrl() ?? '/media/logo.png' }}">
    <link rel="manifest" href="{{ route('event.manifest', $event) }}">

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
<body class="app-shell">
    <div class="app-frame">
        @include('attendee.partials.desktop-notice')

        <div id="app" data-event-slug="{{ $event->slug }}" data-event-id="{{ $event->id }}">
            {{ $slot }}
        </div>

        @include('attendee.partials.nav')
    </div>

    @include('attendee.templates.shared')
</body>
</html>
