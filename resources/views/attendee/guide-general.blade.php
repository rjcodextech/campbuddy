{{-- /guide — WordCamp 101 before an event is picked. Same shell as the
picker page (welcome.blade.php): no event, so no tab bar. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>WordCamp 101 | {{ config('campbuddy.name') }}</title>
    <meta name="description" content="New to WordCamp? What happens during the day, the words people use, what to bring and how to meet people — a friendly guide for first-time attendees.">

    @include('attendee.partials.head-meta', ['manifestUrl' => route('manifest', absolute: false)])

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @include('attendee.partials.analytics')

    @vite(['resources/scss/main.scss', 'resources/js/attendee/app.js'])
</head>
<body class="app-shell" data-page-type="guide_general">
    <div class="app-frame">
        @include('attendee.partials.desktop-notice')

        <header class="topbar">
            <a href="{{ route('home') }}" class="brand">
                <img src="/media/logo-wordmark-sm.png" alt="CampBuddy home" class="brand__logo brand__logo--wordmark" width="351" height="104">
            </a>
            <div class="topbar__actions">
        <button type="button" id="open-on-phone-btn" class="topbar__text-btn" hidden>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="7" y="2" width="10" height="20" rx="2" />
                <path d="M11 18h2" />
            </svg>
            <span>Open on phone</span>
        </button>

                <a href="{{ route('home') }}" class="topbar__text-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6" /></svg>
                    <span>All WordCamps</span>
                </a>
            </div>
        </header>

        <main id="main-content" class="landing-main guide" tabindex="-1">
            @include('attendee.partials.guide-body', ['event' => null])
        </main>

        <footer class="footer-note landing-footer">&copy; {{ date('Y') }} {{ config('campbuddy.name') }}. Made for the WordPress community.</footer>
    </div>

    @include('attendee.templates.shared')
</body>
</html>
