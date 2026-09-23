<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ config('app.name', 'CampBuddy') }} — Your WordCamp companion</title>
    <meta name="description" content="CampBuddy is the mobile-first companion app for WordCamp attendees — guidance on what to do next, session planning, Contributor Day matching, and a digital Camp Card, all local-first and privacy-respecting.">
    <link rel="icon" href="/media/favicon.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/scss/main.scss', 'resources/js/attendee/app.js'])
</head>
<body class="app-shell">
    <div class="landing-frame">
        <header class="topbar">
            <span class="brand">
                <img src="/media/logo.svg" alt="CampBuddy" class="brand__logo brand__logo--wordmark">
            </span>

            <div class="topbar__actions">
                <button type="button" id="install-app-btn" class="topbar__text-btn" hidden>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 3v12" />
                        <path d="M7 10l5 5 5-5" />
                        <path d="M5 21h14" />
                    </svg>
                    <span>Install app</span>
                </button>
            </div>
        </header>

        <main id="main-content" class="landing-main" tabindex="-1">
            <section class="landing-intro">
                <p class="u-eyebrow">Your WordCamp companion</p>
                <h1 class="landing-intro__title">What should I do now?</h1>
                <p class="footer-note" style="text-align:left">
                    CampBuddy guides you through the day — schedule, people, and what to do next.
                    No account, ever.
                </p>
            </section>

            <section id="onboarding-welcome" aria-labelledby="onboarding-welcome-heading" style="margin-bottom:20px" hidden></section>

            <section id="find-your-camp" aria-labelledby="find-your-camp-heading">
                <div class="section-head">
                    <h2 id="find-your-camp-heading" class="section-head__title">Choose your WordCamp</h2>
                </div>

                @if (($events ?? collect())->isEmpty())
                    <div class="card" style="text-align:center">
                        <p class="footer-note">No WordCamp is live yet — check back closer to the event.</p>
                    </div>
                @else
                    <ul class="landing-event-list">
                        @foreach ($events as $event)
                            <li>
                                <a href="{{ route('event.home', $event) }}" class="card landing-event-card">
                                    <img src="{{ $event->faviconUrl() ?? '/media/favicon.png' }}" alt="" class="landing-event-card__logo">
                                    <span class="landing-event-card__text">
                                        {{ $event->display_name }}
                                        @if ($event->starts_on)
                                            <span class="landing-event-card__meta">
                                                {{ $event->starts_on->format('j M Y') }}
                                                @if ($event->ends_on && ! $event->ends_on->isSameDay($event->starts_on))
                                                    – {{ $event->ends_on->format('j M Y') }}
                                                @endif
                                            </span>
                                        @endif
                                    </span>
                                    <span class="landing-event-card__arrow" aria-hidden="true">→</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </main>

        <footer class="footer-note landing-footer">&copy; {{ date('Y') }} {{ config('app.name', 'CampBuddy') }}. Made for the WordPress community.</footer>

        @if (($events ?? collect())->isNotEmpty())
            <div class="landing-bottom-bar">
                @if ($events->count() === 1)
                    <a href="{{ route('event.home', $events->first()) }}" class="btn btn--primary btn--full">Open {{ $events->first()->display_name }} →</a>
                @else
                    <a href="#find-your-camp" class="btn btn--primary btn--full">Choose your WordCamp ↓</a>
                @endif
            </div>
        @endif
    </div>
</body>
</html>
