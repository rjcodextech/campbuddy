<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ config('campbuddy.name') }} | {{ config('campbuddy.tagline') }}</title>
    <meta name="description" content="CampBuddy is the mobile-first companion app for WordCamp attendees — guidance on what to do next, session planning, Contributor Day matching, and a digital Camp Card, all local-first and privacy-respecting.">

    @include('attendee.partials.head-meta', ['manifestUrl' => route('manifest', absolute: false)])

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @include('attendee.partials.analytics')

    @vite(['resources/scss/main.scss', 'resources/js/attendee/app.js'])
</head>
<body class="app-shell" data-page-type="picker">
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
            {{-- The first screen answers "what is this, and what do I do?" before
            anything else: one plain sentence, the three promises people ask
            about most, and the two ways in. --}}
            <section class="landing-hero" aria-labelledby="landing-hero-title">
                <div class="landing-hero__text">
                    <p class="u-eyebrow">Your WordCamp companion</p>
                    <h1 id="landing-hero-title" class="landing-hero__title">Your friendly guide to WordCamp</h1>
                    <p class="landing-hero__lead">
                        Know what's on, plan the talks you want and meet the right people — all from your phone.
                        Made for first-timers, students and regulars.
                    </p>
                    <ul class="landing-hero__promises" aria-label="Good to know">
                        <li>Free</li>
                        <li>No sign-up</li>
                        <li>Works offline</li>
                    </ul>
                    <div class="landing-hero__actions">
                        <a class="btn btn--primary" href="#find-your-camp" data-track="picker_cta" data-track-target="choose">Choose your WordCamp</a>
                        {{-- WordCamp 101, before an event is even chosen — for someone
                        who isn't sure yet what a WordCamp is. --}}
                        <a class="btn btn--outline" href="{{ route('guide') }}" data-track="guide_open" data-track-surface="picker">First WordCamp? Read this first</a>
                    </div>
                </div>
                <img class="landing-hero__art" src="/media/illustrations/welcome.svg" alt="" width="320" height="200">
            </section>

            @include('attendee.partials.about-campbuddy', ['part' => 'steps'])

            {{-- Stays hidden until onboarding.js finds this device hasn't completed it yet
            (the skip/continue buttons and the profile they save are wired there). --}}
            <section id="onboarding-welcome" aria-labelledby="onboarding-welcome-heading" style="margin-bottom:20px" hidden>
                <div class="onboarding-card">
                    <p class="onboarding-card__badge">Optional · 30 seconds</p>
                    <h2 class="form-group__title" id="onboarding-welcome-heading">Make CampBuddy yours</h2>
                    <p class="form-group__desc">Your answers help us suggest talks and people for you. They stay on this device.</p>

                    <div class="form-field">
                        <label class="form-field__label" for="ob-first">Is this your first WordCamp?</label>
                        <select id="ob-first" data-field="firstWordCamp">
                            <option value="">Prefer not to say</option>
                            <option value="yes">Yes, first one!</option>
                            <option value="no">No, I've been before</option>
                        </select>
                    </div>

                    <div class="form-field">
                        <span class="form-field__label" id="ob-interests-label">What describes you?</span>
                        <div class="chip-group" role="group" aria-labelledby="ob-interests-label">
                            @foreach (['Student', 'Developer', 'Designer', 'Content creator', 'Site builder', 'Community organizer', 'Marketer', 'Business owner'] as $tag)
                                <button type="button" class="chip" aria-pressed="false" data-tag="{{ $tag }}">{{ $tag }}</button>
                            @endforeach
                        </div>
                        <p class="form-field__hint">Pick as many as you like.</p>
                    </div>

                    @include('attendee.partials.form-field', ['id' => 'ob-who', 'label' => 'Who would you like to meet?', 'placeholder' => 'e.g. other plugin developers', 'dataField' => 'whoToMeet', 'maxlength' => 120, 'errorLine' => false])

                    <div class="form-field">
                        <label class="form-field__label" for="ob-contrib">Interested in Contributor Day?</label>
                        <p class="form-field__hint" style="margin:0 0 6px">A hands-on day helping improve WordPress — beginners are welcome.</p>
                        <select id="ob-contrib" data-field="attendingContributorDay">
                            <option value="">Not sure yet</option>
                            <option value="yes">Yes</option>
                            <option value="no">Not this time</option>
                        </select>
                    </div>

                    <div class="onboarding-card__actions">
                        <button type="button" class="btn btn--outline" data-action="skip">Skip for now</button>
                        <button type="button" class="btn btn--primary" data-action="save">Save</button>
                    </div>
                </div>
            </section>

            <section id="find-your-camp" aria-labelledby="find-your-camp-heading">
                <div class="section-head">
                    <h2 id="find-your-camp-heading" class="section-head__title">Choose your WordCamp</h2>
                    <span class="section-head__desc">Tap your event to see its schedule, people and guide</span>
                </div>

                @if (($events ?? collect())->isEmpty())
                    <div class="card" style="text-align:center">
                        <p class="footer-note">No WordCamp is open yet — events appear here a few weeks before they start. Meanwhile, the <a href="{{ route('guide') }}">first-timer guide</a> is a great place to begin.</p>
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
                                data-track="select_event"
                                :data-track-event-slug="$event->slug"
                                :title="$event->display_name"
                                :media-url="$event->markUrl() ?? '/media/icons/icon-192.png'"
                                media-fallback="/media/icons/icon-192.png"
                                media-shape="avatar"
                                :date="$dateLabel"
                                :location="$event->info['venue'] ?? null"
                            >
                                <x-slot:footer>
                                    <span class="event-card__cta">Open event <span aria-hidden="true">→</span></span>
                                </x-slot:footer>
                            </x-attendee.event-card>
                        @endforeach
                    </div>
                @endif
            </section>

            @include('attendee.partials.about-campbuddy', ['part' => 'more'])
        </main>

        <footer class="footer-note landing-footer">&copy; {{ date('Y') }} {{ config('campbuddy.name') }}. Made for the WordPress community.</footer>

        @if (($events ?? collect())->isNotEmpty())
            <nav class="landing-bottom-bar" aria-label="Continue">
                @if ($events->count() === 1)
                    <a href="{{ route('event.home', $events->first()) }}" class="btn btn--primary btn--full landing-bottom-bar__btn" data-track="select_event" data-track-event-slug="{{ $events->first()->slug }}"><span class="landing-bottom-bar__label">Open {{ $events->first()->display_name }}</span> <span aria-hidden="true">→</span></a>
                @else
                    <a href="#find-your-camp" class="btn btn--primary btn--full">Choose your WordCamp ↓</a>
                @endif
            </nav>
        @endif
    </div>

    @include('attendee.templates.shared')
</body>
</html>
