{{-- Shell for the privacy notice and terms: the same top bar and footer as /guide, no event, no tab bar. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $pageTitle }} | {{ config('campbuddy.name') }}</title>
    <meta name="description" content="{{ $pageDescription }}">
    <link rel="canonical" href="{{ url()->current() }}">

    @include('attendee.partials.head-meta', ['manifestUrl' => route('manifest', absolute: false)])

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/scss/main.scss', 'resources/js/attendee/app.js'])

    <style>
        .legal { max-width: 720px; margin: 0 auto; padding: 8px 4px 32px; line-height: 1.6; color: var(--ink); }
        .legal h1 { font-size: 1.75rem; line-height: 1.2; margin: 8px 0 4px; font-weight: 800; }
        .legal h2 { font-size: 1.2rem; margin: 28px 0 6px; font-weight: 800; }
        .legal h3 { font-size: 1rem; margin: 18px 0 4px; font-weight: 700; }
        .legal p, .legal li { font-size: 0.98rem; }
        .legal ul { padding-left: 1.2rem; margin: 6px 0 10px; list-style: disc; }
        .legal li + li { margin-top: 4px; }
        .legal .legal__meta { color: var(--muted); font-size: 0.88rem; margin: 0 0 12px; }
        .legal .legal__box { background: var(--peach); border: 1px solid var(--line); border-radius: 8px; padding: 14px 16px; margin: 14px 0; }
        .legal table { width: 100%; border-collapse: collapse; margin: 10px 0 14px; font-size: 0.92rem; }
        .legal th, .legal td { text-align: left; vertical-align: top; padding: 8px 10px; border: 1px solid var(--line); }
        .legal th { background: var(--peach); font-weight: 700; }
        .legal a { color: var(--brand-primary); text-decoration: underline; }
        .legal-links { display: flex; flex-wrap: wrap; gap: 6px 14px; justify-content: center; margin: 0 0 6px; }
        @media (max-width: 560px) { .legal table, .legal thead, .legal tbody, .legal tr, .legal th, .legal td { display: block; } .legal thead { display: none; } .legal tr { margin-bottom: 10px; border: 1px solid var(--line); border-radius: 8px; overflow: hidden; } .legal td { border: 0; border-bottom: 1px solid var(--line); } .legal td[data-label]::before { content: attr(data-label); display: block; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: var(--brand-primary); margin-bottom: 2px; } }
    </style>
</head>
<body class="app-shell" data-page-type="legal">
    <div class="app-frame">
        <header class="topbar">
            <a href="{{ route('home') }}" class="brand">
                <img src="/media/logo-wordmark-sm.png" alt="CampBuddy home" class="brand__logo brand__logo--wordmark" width="351" height="104">
            </a>
            <div class="topbar__actions">
                <a href="{{ route('home') }}" class="topbar__text-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6" /></svg>
                    <span>All WordCamps</span>
                </a>
            </div>
        </header>

        <main id="main-content" class="landing-main" tabindex="-1">
            <article class="legal">
                @yield('content')
            </article>
        </main>

        <p class="legal-links footer-note landing-footer">
            <a href="{{ route('privacy') }}">Privacy notice</a>
            <a href="{{ route('terms') }}">Terms of use</a>
            <a href="mailto:{{ config('campbuddy.legal.grievance_email') }}?subject=CampBuddy%20data%20request">Data request or complaint</a>
        </p>
        <footer class="footer-note landing-footer">&copy; {{ date('Y') }} {{ config('campbuddy.name') }}. Made for the WordPress community.</footer>
    </div>
</body>
</html>
