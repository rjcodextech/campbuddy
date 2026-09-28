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
            {{-- The first screen: what this is, in plain words, and the two ways in.
            On wider screens a real Home screen sits beside the text. --}}
            <section class="landing-hero" aria-labelledby="landing-hero-title">
                <div class="landing-hero__text">
                    <h1 id="landing-hero-title" class="landing-hero__title">Get more out of your WordCamp</h1>
                    <p class="landing-hero__lead">
                        See what's on right now, save the talks you want and find people worth meeting. It opens in your phone's browser and keeps working when the venue wifi gives up.
                    </p>
                    <p class="landing-hero__note">Free, and no sign-up.</p>
                    <div class="landing-hero__actions">
                        <a class="btn btn--primary" href="#find-your-camp" data-track="picker_cta" data-track-target="choose">Choose your WordCamp</a>
                        {{-- WordCamp 101, before an event is even chosen, for someone
                        who isn't sure yet what a WordCamp is. --}}
                        <a class="btn btn--outline" href="{{ route('guide') }}" data-track="guide_open" data-track-surface="picker">First WordCamp? Start with the guide</a>
                    </div>
                </div>
                <div class="landing-hero__shot" aria-hidden="true">
                    <img src="/media/tour/1-home.jpg" alt="" width="360" height="720" loading="lazy" decoding="async">
                </div>
            </section>

            <section id="find-your-camp" aria-labelledby="find-your-camp-heading">
                <div class="section-head">
                    <h2 id="find-your-camp-heading" class="section-head__title">Choose your WordCamp</h2>
                    <span class="section-head__desc">Tap an event for its schedule, people and guide</span>
                </div>

                @if (($events ?? collect())->isEmpty())
                    <div class="card" style="text-align:center">
                        <p class="footer-note">No WordCamp is open yet. Events show up here a few weeks before they start. Until then, have a look at the <a href="{{ route('guide') }}">first-timer guide</a>.</p>
                    </div>
                @else
                    <div class="card-grid card-grid--picker">
                        @foreach ($events as $event)
                            @php
                                // "2–3 Oct 2026", "30 Oct – 1 Nov 2026": the year (and
                                // month) only once when both days share it.
                                $dateLabel = null;
                                if ($start = $event->starts_on) {
                                    $end = $event->ends_on;
                                    $dateLabel = match (true) {
                                        ! $end || $end->isSameDay($start) => $start->format('j M Y'),
                                        $end->format('Y-m') === $start->format('Y-m') => $start->format('j').'–'.$end->format('j M Y'),
                                        $end->year === $start->year => $start->format('j M').' – '.$end->format('j M Y'),
                                        default => $start->format('j M Y').' – '.$end->format('j M Y'),
                                    };
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
                            />
                        @endforeach
                    </div>
                @endif
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
