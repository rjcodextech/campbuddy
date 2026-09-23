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
    <div class="app-frame">
        @include('attendee.partials.desktop-notice')

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

            {{-- Stays hidden until onboarding.js finds this device hasn't completed it yet
            (the skip/continue buttons and the profile they save are wired there). --}}
            <section id="onboarding-welcome" aria-labelledby="onboarding-welcome-heading" style="margin-bottom:20px" hidden>
                <div class="onboarding-card">
                    <p class="section-head__title" id="onboarding-welcome-heading" style="margin-bottom:2px">Tell us a little about you</p>
                    <p class="footer-note" style="text-align:left;margin-bottom:16px">Every question here is skippable.</p>

                    <label class="field">
                        <span>Is this your first WordCamp?</span>
                        <select data-field="firstWordCamp">
                            <option value="">Prefer not to say</option>
                            <option value="yes">Yes, first one!</option>
                            <option value="no">No, I've been before</option>
                        </select>
                    </label>

                    <div class="field">
                        <span>What are you into?</span>
                        <div class="onboarding-card__tags">
                            @foreach (['Developer', 'Designer', 'Content creator', 'Site builder', 'Community organizer', 'Marketer', 'Business owner'] as $tag)
                                <button type="button" class="pill" data-tag="{{ $tag }}">{{ $tag }}</button>
                            @endforeach
                        </div>
                    </div>

                    <label class="field">
                        <span>Who would you like to meet?</span>
                        <input type="text" data-field="whoToMeet" placeholder="e.g. other plugin developers">
                    </label>

                    <label class="field">
                        <span>Interested in Contributor Day?</span>
                        <select data-field="attendingContributorDay">
                            <option value="">Not sure yet</option>
                            <option value="yes">Yes</option>
                            <option value="no">Not this time</option>
                        </select>
                    </label>

                    <div class="onboarding-card__actions">
                        <button type="button" class="btn btn--outline" data-action="skip">Skip</button>
                        <button type="button" class="btn btn--primary" data-action="save">Continue</button>
                    </div>
                </div>
            </section>

            <section id="find-your-camp" aria-labelledby="find-your-camp-heading">
                <div class="section-head">
                    <h2 id="find-your-camp-heading" class="section-head__title">Choose your WordCamp</h2>
                </div>

                @if (($events ?? collect())->isEmpty())
                    <div class="card" style="text-align:center">
                        <p class="footer-note">No WordCamp is live yet — check back closer to the event.</p>
                    </div>
                @else
                    <div class="card-grid">
                        @foreach ($events as $event)
                            @php
                                $dateLabel = null;
                                if ($event->starts_on) {
                                    $dateLabel = $event->starts_on->format('j M Y');
                                    if ($event->ends_on && ! $event->ends_on->isSameDay($event->starts_on)) {
                                        $dateLabel .= ' – ' . $event->ends_on->format('j M Y');
                                    }
                                }
                            @endphp

                            <x-attendee.event-card
                                :href="route('event.home', $event)"
                                :title="$event->display_name"
                                :media-url="$event->faviconUrl() ?? '/media/favicon.png'"
                                media-shape="avatar"
                                :date="$dateLabel"
                                :location="$event->info['venue'] ?? null"
                            >
                                <x-slot:footer>
                                    <span class="event-card__cta">View event <span aria-hidden="true">→</span></span>
                                </x-slot:footer>
                            </x-attendee.event-card>
                        @endforeach
                    </div>
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

    @include('attendee.templates.shared')
</body>
</html>
