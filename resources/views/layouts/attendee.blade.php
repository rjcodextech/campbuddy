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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/scss/main.scss', 'resources/js/attendee/app.js'])
</head>
<body class="app-shell">
    <div class="app-frame">
        <div id="app" data-event-slug="{{ $event->slug }}" data-event-id="{{ $event->id }}">
            {{ $slot }}
        </div>

        @include('attendee.partials.nav')
    </div>
</body>
</html>
