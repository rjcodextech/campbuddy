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


    @include('attendee.partials.analytics')

    @vite(['resources/css/fonts.css', 'resources/scss/main.scss', 'resources/js/attendee/app.js'])
</head>
<body class="app-shell" data-page-type="picker">
    <div class="app-frame">
        @include('attendee.partials.desktop-notice')

        @include('attendee.partials.public-header')

        <main id="main-content" class="landing-main" tabindex="-1">
            @include('attendee.partials.announcement-banner')

            {{-- The first screen answers "what is this, and what do I do?" before
            anything else: one plain sentence, the three promises people ask
            about most, and the two ways in. --}}
            <section class="landing-hero" aria-labelledby="landing-hero-title">
                <div class="landing-hero__text">
                    <div class="hero-kicker">
                        <x-attendee.line-icon name="sparkles" />
                        <span>Open-Source Community Companion</span>
                    </div>
                    <h1 id="landing-hero-title" class="landing-hero__title">
                        Make the most of your <span class="landing-hero__highlight">WordCamp</span>
                    </h1>
                    <p class="landing-hero__lead">
                        Navigate multi-track halls, star talks into your personal "My Day", and exchange contacts in seconds via phone camera &mdash; zero paper cards and no login needed.
                    </p>
                    <ul class="landing-hero__promises" aria-label="Key promises">
                        <li>100% Free &amp; Open Source</li>
                        <li>No Account or Login Needed</li>
                        <li>Runs 100% Offline in Venue</li>
                        <li>Instant Progressive Web App</li>
                    </ul>
                    <div class="landing-hero__actions">
                        <a class="btn btn--primary btn--hero-cta" href="#find-your-camp" data-track="picker_cta" data-track-target="choose">
                            <span>Choose your WordCamp</span>
                            <span aria-hidden="true">&darr;</span>
                        </a>
                        <a class="btn btn--outline btn--hero-guide" href="{{ route('guide') }}" data-track="guide_open" data-track-surface="picker">
                            <span>First WordCamp?</span>
                            <span aria-hidden="true">&rarr;</span>
                        </a>
                    </div>
                    <div class="hero-trust-bar">
                        <span><x-attendee.line-icon name="shield" /> Built by WPSimplified</span>
                        <span class="hero-trust-bar__sep">&middot;</span>
                        <span><x-attendee.line-icon name="heart" /> Open Source Initiative</span>
                        <span class="hero-trust-bar__sep">&middot;</span>
                        <span><x-attendee.line-icon name="lock" /> DPDP Act 2023 Compliant</span>
                    </div>
                </div>

                {{-- Interactive Live Companion Stage (Bespoke Glassmorphic Device Experience) --}}
                <div class="landing-hero__visual">
                    {{-- Ambient Layered Radial Glow behind the Stage --}}
                    <div class="hero-ambient-glow" aria-hidden="true"></div>

                    {{-- Embedded Live Spotlights Data from Active WordCamps --}}
                    <script type="application/json" id="hero-spotlights-data">@json($heroSpotlights ?? [])</script>

                    @php
                        $firstSpotlight = ($heroSpotlights ?? [])[0] ?? [
                            'event_name' => 'WordCamp Delhi 2026',
                            'event_logo' => '/media/icons/icon-192.png',
                            'badge' => 'Speaker Spotlight',
                            'title' => 'Aditya Vikram Singh',
                            'meta' => 'Sharing knowledge with the WordPress community',
                            'url' => '#find-your-camp',
                        ];
                    @endphp

                    <div class="hero-companion-stage hero-stage" id="hero-companion-stage">
                        {{-- Stage Top Navigation Bar --}}
                        <div class="hero-stage__header">
                            <div class="hero-stage__brand" id="hero-stage-event-brand">
                                <img src="{{ $firstSpotlight['event_logo'] ?? '/media/icons/icon-192.png' }}" id="hero-stage-event-logo" alt="" width="32" height="32" class="hero-stage__event-logo">
                                <div class="hero-stage__brand-text">
                                    <div class="hero-stage__brand-eyebrow">
                                        <span class="live-dot live-dot--green live-dot--pulse" aria-hidden="true"></span>
                                        <span>Active WordCamp</span>
                                    </div>
                                    <span id="hero-stage-event-name" class="hero-stage__event-name" title="{{ $firstSpotlight['event_name'] ?? 'WordCamp Delhi 2026' }}">{{ $firstSpotlight['event_name'] ?? 'WordCamp Delhi 2026' }}</span>
                                </div>
                            </div>

                            <div class="hero-stage__actions">
                                <button type="button" class="hero-stage__shuffle-btn" id="hero-shuffle-btn" title="Shuffle community spotlight" aria-label="Shuffle community spotlight">
                                    <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2"/>
                                    </svg>
                                    <span>Shuffle</span>
                                </button>
                            </div>
                        </div>

                        {{-- Card 1: Dynamic Community Spotlight (Real Sessions, Speakers, Organizers, Sponsors) --}}
                        <div class="hero-stage__card hero-stage__card--spotlight" id="hero-spotlight-card">
                            <div class="hero-stage__card-header">
                                <span class="track-pill" id="hero-spotlight-badge">{{ $firstSpotlight['badge'] ?? 'Speaker Spotlight' }}</span>
                                <span class="hero-stage__card-tag">Real-Time Sync</span>
                            </div>

                            <div class="hero-stage__talk-title" id="hero-spotlight-title">{{ $firstSpotlight['title'] ?? 'Aditya Vikram Singh' }}</div>

                            <div class="hero-stage__speaker">
                                <span class="speaker-avatar" id="hero-spotlight-avatar">AV</span>
                                <span class="star-badge" id="hero-spotlight-meta">{{ $firstSpotlight['meta'] ?? 'Sharing knowledge with the WordPress community' }}</span>
                            </div>

                            <div class="hero-stage__card-footer">
                                <a href="{{ $firstSpotlight['url'] ?? '#find-your-camp' }}" class="hero-stage__view-btn" id="hero-spotlight-link">
                                    <span>Explore this WordCamp</span>
                                    <span class="hero-stage__arrow" aria-hidden="true">&rarr;</span>
                                </a>
                            </div>
                        </div>

                        {{-- Card 2: Digital Camp Card Swap --}}
                        <div class="hero-stage__card hero-stage__card--card-swap">
                            <div class="swap-row">
                                <div class="qr-mini">
                                    <x-attendee.line-icon name="qr-code" />
                                </div>
                                <div class="swap-info">
                                    <strong>Instant Digital Camp Card (P2P Swap)</strong>
                                    <span>Swap LinkedIn, website &amp; contacts via camera &mdash; zero paper cards</span>
                                </div>
                                <span class="offline-tag">P2P QR Swap</span>
                            </div>
                        </div>

                        {{-- Quick Feature Chips Dock --}}
                        <div class="hero-stage__chips" aria-label="Core Capabilities">
                            <span class="stage-chip"><x-attendee.line-icon name="calendar" /> Multi-Track</span>
                            <span class="stage-chip"><x-attendee.line-icon name="qr-code" /> Camp Card</span>
                            <span class="stage-chip"><x-attendee.line-icon name="wrench" /> Contributor Day</span>
                            <span class="stage-chip"><x-attendee.line-icon name="ticket" /> Quests</span>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Choosing the event is the one job of this page, so it comes right after the
            hero. Personalising is optional and follows it. --}}
            <section id="find-your-camp" aria-labelledby="find-your-camp-heading">
                <div class="section-head">
                    <span class="u-eyebrow"><x-attendee.line-icon name="map-pin" /> Active &amp; Upcoming Events</span>
                    <h2 id="find-your-camp-heading" class="section-head__title">Choose your WordCamp</h2>
                    <span class="section-head__desc">Tap your event to see its multi-track schedule, attendees, and guide</span>
                </div>

                @if (($events ?? collect())->isEmpty())
                    <div class="card" style="text-align:center">
                        <p class="footer-note">No WordCamp is open yet. Events show up here a few weeks before they start. Until then, the <a href="{{ route('guide') }}" data-track="guide_open" data-track-surface="picker_intro">first-timer guide</a> is a good place to start.</p>
                    </div>
                @else
                    {{-- Country filter + "Load more" (picker-filter.js). Hidden until the
                    script runs, so without it every event simply shows, as before. --}}
                    <div class="picker-filter" data-picker-filter hidden>
                        <div class="picker-filter__row">
                            <label class="u-visually-hidden" for="picker-country">Country</label>
                            <span class="picker-filter__select">
                                <x-attendee.line-icon name="map-pin" class="picker-filter__icon" />
                                <select id="picker-country" data-picker-country></select>
                            </span>
                            <button type="button" class="picker-filter__toggle" data-picker-toggle hidden></button>
                        </div>
                        <p class="picker-filter__status" data-picker-status aria-live="polite"></p>
                    </div>

                    <div class="card-grid" data-picker-list>
                        @foreach ($events as $event)
                            {{-- "21–22 Nov 2026": the short form fits a phone-width card on one line. --}}
                            @php($dateLabel = $event->starts_on ? \App\Support\SocialKit::dates($event) : null)

                            @php($isCompleted = isset($completed[$event->id]))
                            {{-- A finished WordCamp: the card (still opens the event) plus its own
                            "Save your WordCamp Data" button below it, outside the link, that makes
                            the PDF right here (thank-you.js). The wrapper is what the filter counts. --}}
                            @if ($isCompleted)
                                <div class="event-card-wrap" data-picker-card data-country="{{ $eventCountries[$event->id] ?? '' }}">
                            @endif
                            <x-attendee.event-card
                                :href="route('event.home', $event)"
                                @class(['event-card--completed' => $isCompleted])
                                data-track="select_event"
                                :data-track-event-slug="$event->slug"
                                :title="$event->display_name"
                                :media-url="$event->markUrl() ?? '/media/icons/icon-192.png'"
                                media-fallback="/media/icons/icon-192.png"
                                media-shape="avatar"
                                :data-picker-card="! $isCompleted"
                                :data-country="$isCompleted ? null : ($eventCountries[$event->id] ?? '')"
                                :date="$dateLabel"
                                :location="$event->info['venue'] ?? null"
                                location-layout="row"
                            >
                                <x-slot:footer>
                                    @if ($isCompleted)
                                        {{-- Finished: listed for three months (EventTime::LISTED_DAYS), for the PDF export, the thank-you card and the memories. --}}
                                        <span class="event-card__status"><x-attendee.line-icon name="party" /> Completed</span>
                                        <span class="event-card__cta">Open event <span aria-hidden="true">→</span></span>
                                    @else
                                        <span class="event-card__cta">Open event <span aria-hidden="true">→</span></span>
                                    @endif
                                </x-slot:footer>
                            </x-attendee.event-card>
                            @if ($isCompleted)
                                    {{-- Its photo album and talks on WordPress.tv, once the organizers add them (EventMemories). --}}
                                    @php($memoryLinks = \App\Support\EventMemories::links($event))
                                    @if ($memoryLinks !== [])
                                        <div class="event-card__memories">
                                            @isset($memoryLinks['photos'])
                                                <a href="{{ $memoryLinks['photos'] }}" target="_blank" rel="noopener" data-track="memories_open" data-track-target="photos"><x-attendee.line-icon name="camera" /> Photos</a>
                                            @endisset
                                            @isset($memoryLinks['videos'])
                                                <a href="{{ $memoryLinks['videos'] }}" target="_blank" rel="noopener" data-track="memories_open" data-track-target="videos"><x-attendee.line-icon name="play" /> Talks on WordPress.tv</a>
                                            @endisset
                                        </div>
                                    @endif
                                    <button type="button" class="event-card__export" data-export-event="{{ $event->id }}">
                                        <x-attendee.line-icon name="download" /> Save your WordCamp Data
                                    </button>
                                </div>
                            @endif
                        @endforeach
                    </div>

                    <button type="button" class="btn btn--outline picker-more" data-picker-more hidden></button>

                    {{-- A thank-you card per "Completed" WordCamp; thank-you.js opens the first one this phone has data for. --}}
                    @foreach ($events as $event)
                        @if (isset($completed[$event->id]))
                            @include('attendee.partials.thank-you', ['event' => $event])
                        @endif
                    @endforeach

                    {{-- Country names, and each listed country's time zones: the browser
                    matches its own zone against these to find the visitor's country. --}}
                    <script type="application/json" id="picker-countries">{!! json_encode(['names' => (object) ($countryNames ?? []), 'zones' => (object) ($countryZones ?? [])], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
                @endif
            </section>

            {{-- Stays hidden until onboarding.js finds this device hasn't completed it yet
            (the stepper, Skip / Next and the profile it saves are wired there). One
            question at a time; every step can be skipped. The answers go to the same
            kv 'onboarding' record as always, so Home, Contribute, discovery and the
            Camp Card read them unchanged (profile-sync.js). --}}
            <section id="onboarding-welcome" class="ob" aria-labelledby="onboarding-welcome-heading" hidden>
                <div class="onboarding-card">
                    {{-- Before it starts: a short invitation, never a wall of questions. --}}
                    <div class="ob__intro" data-ob-intro>
                        <p class="onboarding-card__badge">Optional · 30 seconds</p>
                        <h2 class="form-group__title" id="onboarding-welcome-heading">Make CampBuddy yours</h2>
                        <p class="form-group__desc">Five quick taps and CampBuddy points you to the right talks and people. It all stays on this phone.</p>
                        <div class="onboarding-card__actions">
                            <button type="button" class="btn btn--outline" data-action="skip-all">Not now</button>
                            <button type="button" class="btn btn--primary" data-action="start">Let's go <x-attendee.line-icon name="arrow-right" /></button>
                        </div>
                    </div>

                    <div class="ob__flow" data-ob-flow hidden>
                        <div class="ob__top">
                            <ol class="ob__dots" aria-hidden="true" data-ob-dots>
                                @foreach (range(1, 5) as $n)
                                    <li class="ob__dot"></li>
                                @endforeach
                            </ol>
                            <p class="ob__count" data-ob-count role="status" aria-live="polite">Step 1 of 5</p>
                        </div>

                        <div class="ob__steps">
                            <div class="ob__step" data-step hidden>
                                <span class="ob__icon" aria-hidden="true"><x-attendee.line-icon name="sprout" /></span>
                                <h3 class="ob__q" tabindex="-1">Is this your first WordCamp?</h3>
                                <p class="ob__why">First-timers get beginner-friendly talks flagged and a short start-here guide.</p>
                                <input type="hidden" data-field="firstWordCamp" value="">
                                <div class="ob__choices" role="group" aria-label="Is this your first WordCamp?">
                                    <button type="button" class="ob__choice" data-choice="yes" aria-pressed="false"><x-attendee.line-icon name="sprout" /><span>Yes, first one!</span></button>
                                    <button type="button" class="ob__choice" data-choice="no" aria-pressed="false"><x-attendee.line-icon name="compass" /><span>No, I've been before</span></button>
                                </div>
                            </div>

                            {{-- The same questions attendee discovery asks (same tags, same limit), so
                            these answers fill in the discovery form and the Camp Card later
                            (profile-sync.js). The tag list is DESCRIBE_TAGS there; a test keeps
                            the two equal. --}}
                            <div class="ob__step" data-step hidden>
                                <span class="ob__icon" aria-hidden="true"><x-attendee.line-icon name="sparkles" /></span>
                                <h3 class="ob__q" id="ob-interests-label" tabindex="-1">What describes you?</h3>
                                <p class="ob__why">We use this to suggest talks, Contributor Day teams and people to meet.</p>
                                <div class="chip-group" role="group" aria-labelledby="ob-interests-label">
                                    @foreach (['developer', 'designer', 'content creator', 'site builder', 'community organizer', 'marketer', 'business owner', 'blogger', 'translator', 'speaker', 'student', 'mentor'] as $tag)
                                        <button type="button" class="chip" aria-pressed="false" data-tag="{{ $tag }}">{{ ucfirst($tag) }}</button>
                                    @endforeach
                                </div>
                                <p class="ob__hint"><span data-ob-picked>0</span> of 5 picked</p>
                            </div>

                            <div class="ob__step" data-step hidden>
                                <span class="ob__icon" aria-hidden="true"><x-attendee.line-icon name="wrench" /></span>
                                <h3 class="ob__q" tabindex="-1">Joining Contributor Day?</h3>
                                <p class="ob__why">A hands-on day improving WordPress. Beginners welcome. We will set up the Contribute page for you.</p>
                                <input type="hidden" data-field="attendingContributorDay" value="">
                                <div class="ob__choices" role="group" aria-label="Joining Contributor Day?">
                                    <button type="button" class="ob__choice" data-choice="yes" aria-pressed="false"><x-attendee.line-icon name="wrench" /><span>Yes</span></button>
                                    <button type="button" class="ob__choice" data-choice="no" aria-pressed="false"><x-attendee.line-icon name="coffee" /><span>Not this time</span></button>
                                </div>
                            </div>

                            <div class="ob__step" data-step hidden>
                                <span class="ob__icon" aria-hidden="true"><x-attendee.line-icon name="users" /></span>
                                <h3 class="ob__q" tabindex="-1">Who would you like to meet?</h3>
                                <p class="ob__why">It pre-fills your attendee profile and Camp Card. Nothing is shared unless you join discovery.</p>
                                <div class="form-field who-tags-field">
                                    <label class="form-field__label" for="ob-who-text">Looking to meet</label>
                                    <input type="hidden" id="ob-who" data-field="whoToMeet" value="">
                                    <div class="who-tags">
                                        <div class="who-tags__items"></div>
                                        <div class="who-tags__input-row">
                                            <input type="text" id="ob-who-text" class="who-tags__input" list="ob-who-datalist" placeholder="e.g. Plugin Developers, Agency Owners…" autocomplete="off">
                                            <button type="button" class="btn btn--compact btn--outline who-tags__add-btn" aria-label="Add entry">Add</button>
                                        </div>
                                    </div>
                                    <datalist id="ob-who-datalist">
                                        @foreach (['Plugin Developers', 'Agency Owners', 'Core Contributors', 'Freelancers', 'Designers & UI/UX', 'Theme Developers', 'Content Creators & Bloggers', 'Marketers & SEO Experts', 'WooCommerce Builders', 'Beginners & First-Timers', 'Students & Learners', 'Community Organizers', 'Educators & Trainers', 'Potential Clients & Partners', 'Hiring Managers', 'Job Seekers', 'QA & Testers', 'Accessibility Advocates', 'Hosting Providers', 'WordPress Enthusiasts'] as $suggestion)
                                            <option value="{{ $suggestion }}"></option>
                                        @endforeach
                                    </datalist>
                                    <div class="who-suggestions">
                                        <span class="who-suggestions__label">Quick suggestions:</span>
                                        <div class="who-suggestions__list">
                                            @foreach (['Plugin Developers', 'Agency Owners', 'Core Contributors', 'Designers & UI/UX', 'Freelancers', 'WooCommerce Builders', 'Job Seekers'] as $pill)
                                                <button type="button" class="who-suggestion-pill" data-suggest="{{ $pill }}">+ {{ $pill }}</button>
                                            @endforeach
                                        </div>
                                    </div>
                                    <p class="form-field__hint">Pick suggestions or type your own. Press Enter, comma or Add to add multiple.</p>
                                </div>
                                @include('attendee.partials.form-field', ['id' => 'ob-profession', 'label' => 'Your profession', 'placeholder' => 'e.g. WordPress Engineer', 'dataField' => 'profession', 'maxlength' => 100, 'errorLine' => false])
                            </div>

                            <div class="ob__step" data-step hidden>
                                <span class="ob__icon" aria-hidden="true"><x-attendee.line-icon name="link" /></span>
                                <h3 class="ob__q" tabindex="-1">Your WordPress.org name</h3>
                                <p class="ob__why">So people can find your profile from your Camp Card. Pasting the profile link works too.</p>
                                @include('attendee.partials.form-field', ['id' => 'ob-wporg', 'label' => 'WordPress.org username', 'placeholder' => 'e.g. yourname', 'dataField' => 'wporg', 'maxlength' => 120, 'errorLine' => false])
                            </div>
                        </div>

                        <div class="ob__nav">
                            <button type="button" class="btn btn--outline ob__back" data-action="back" hidden><x-attendee.line-icon name="chevron-left" /> Back</button>
                            <button type="button" class="ob__skip" data-action="skip">Skip</button>
                            <button type="button" class="btn btn--primary ob__next" data-action="next">Next <x-attendee.line-icon name="chevron-right" /></button>
                        </div>
                        <button type="button" class="ob__quit" data-action="skip-all">Skip all questions</button>
                    </div>

                    {{-- After the last step: what the answers will do. --}}
                    <div class="ob__done" data-ob-done hidden>
                        <span class="ob__icon ob__icon--done" aria-hidden="true"><x-attendee.line-icon name="party" /></span>
                        <h3 class="ob__q" tabindex="-1">You are set</h3>
                        <ul class="ob__summary" data-ob-summary></ul>
                        <button type="button" class="btn btn--primary btn--full" data-action="done">Choose my WordCamp <x-attendee.line-icon name="arrow-right" /></button>
                    </div>
                </div>
            </section>

            @include('attendee.partials.about-campbuddy', ['part' => 'steps'])

            @include('attendee.partials.about-campbuddy', ['part' => 'more'])
        </main>

        @include('attendee.partials.footer')

        {{-- Shown by native-bridge.js, in the store app only: event staff sign in to the panel there. --}}
        <p class="footer-note landing-footer" data-app-only hidden>Event staff: <a href="{{ route('manager.login') }}">Event manager sign in</a> &middot; <a href="{{ route('login') }}">Admin sign in</a></p>

        @if (($events ?? collect())->isNotEmpty())
            {{-- Starts tucked away; landing-bar.js brings it up once the hero's own
            "Choose your WordCamp" button has scrolled off screen. --}}
            <noscript><style>.landing-bottom-bar.is-tucked { transform: none; visibility: visible; }</style></noscript>
            <nav class="landing-bottom-bar is-tucked" aria-label="Continue">
                @if ($events->count() === 1)
                    <a href="{{ route('event.home', $events->first()) }}" class="btn btn--primary btn--full landing-bottom-bar__btn" data-track="select_event" data-track-event-slug="{{ $events->first()->slug }}"><span class="landing-bottom-bar__label">Open {{ $events->first()->display_name }}</span> <span aria-hidden="true">→</span></a>
                @else
                    <a href="#find-your-camp" class="btn btn--primary btn--full" data-track="home_link_click" data-track-target="find_your_camp">Choose your WordCamp ↓</a>
                @endif
            </nav>
        @endif
    </div>

    @include('attendee.templates.shared')
</body>
</html>
