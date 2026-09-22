<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <link rel="icon" href="/media/favicon.png">
</head>
<body style="font-family: system-ui, sans-serif; max-width: 480px; margin: 60px auto; padding: 0 20px; color: #231f20;">
    <img src="/media/logo.svg" alt="" style="height: 48px;">
    <h1>{{ config('app.name') }}</h1>

    @if (($events ?? collect())->isEmpty())
        <p>No WordCamp is live in CampBuddy yet — check back closer to the event.</p>
    @else
        {{-- Multi-event browsing is fast-follow (§2.2); this only renders
        if more than one event is active+visible, which launch doesn't
        expect (§2.1 F2). --}}
        <p>Choose your WordCamp:</p>
        <ul>
            @foreach ($events as $event)
                <li><a href="{{ route('event.home', $event) }}">{{ $event->display_name }}</a></li>
            @endforeach
        </ul>
    @endif
</body>
</html>
