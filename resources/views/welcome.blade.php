<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ config('campbuddy.name') }} | {{ config('campbuddy.tagline') }}</title>
    @include('attendee.partials.seo', [
        'seoTitle' => config('campbuddy.name').' | '.config('campbuddy.tagline'),
        'seoDescription' => 'CampBuddy is a free companion app for WordCamp attendees: see what\'s on now, plan the talks you want, meet people who share your interests and follow a friendly first-timer guide. No sign-up.',
        'seoSchema' => [
            ...\App\Support\Seo::site(),
            \App\Support\Seo::faq(array_map(fn ($e) => [$e['q'], $e['a']], \App\Support\FirstTimerGuide::quickQuestions())),
            ($events ?? collect())->isNotEmpty() ? [
                '@type' => 'ItemList',
                'name' => 'Upcoming WordCamps',
                'itemListElement' => $events->values()->map(fn ($e, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'item' => \App\Support\Seo::event($e)])->all(),
            ] : null,
        ],
    ])

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
                        Know what's on, plan your talks and meet the right people — all from your phone.
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
                {{-- Line icons around the CampBuddy mark: what it helps with, at a glance.
                Decorative — the text beside it says the same in words. --}}
                <div class="hero-orbit" aria-hidden="true">
                    <span class="hero-orbit__ring"></span>
                    <span class="hero-orbit__ring hero-orbit__ring--inner"></span>
                    <span class="hero-orbit__core"><img src="/media/icons/icon-192.png" alt="" width="72" height="72"></span>
                    <span class="hero-orbit__item hero-orbit__item--1"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/></svg><b>Talks</b></span>
                    <span class="hero-orbit__item hero-orbit__item--2"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg><b>People</b></span>
                    <span class="hero-orbit__item hero-orbit__item--3"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg><b>Reminders</b></span>
                    <span class="hero-orbit__item hero-orbit__item--4"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM21 14v.01M14 21h.01M17 21h4v-4"/></svg><b>Camp Card</b></span>
                    <span class="hero-orbit__dot hero-orbit__dot--1"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 8h1a4 4 0 1 1 0 8h-1"/><path d="M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4Z"/><path d="M6 2v2M10 2v2M14 2v2"/></svg></span>
                    <span class="hero-orbit__dot hero-orbit__dot--2"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg></span>
                </div>
            </section>

            @include('attendee.partials.about-campbuddy', ['part' => 'steps'])

            {{-- Stays hidden until onboarding.js finds this device hasn't completed it yet
            (the skip/continue buttons and the profile they save are wired there). --}}
            <section id="onboarding-welcome" aria-labelledby="onboarding-welcome-heading" style="margin-bottom:20px" hidden>
                <div class="onboarding-card">
                    <p class="onboarding-card__badge">Optional · 30 seconds</p>
                    <h2 class="form-group__title" id="onboarding-welcome-heading">Make CampBuddy yours</h2>
                    <p class="form-group__desc">Helps us suggest talks and people. Stays on this device.</p>

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
                        <p class="form-field__hint" style="margin:0 0 6px">A hands-on day improving WordPress. Beginners welcome.</p>
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
