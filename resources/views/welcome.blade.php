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

        <header class="topbar">
            <a href="{{ route('home') }}" class="brand" data-track="home_link_click" data-track-target="brand">
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
            @include('attendee.partials.announcement-banner')

            {{-- The first screen answers "what is this, and what do I do?" before
            anything else: one plain sentence, the three promises people ask
            about most, and the two ways in. --}}
            <section class="landing-hero" aria-labelledby="landing-hero-title">
                <div class="landing-hero__text">
                    <p class="u-eyebrow">Your WordCamp companion</p>
                    <h1 id="landing-hero-title" class="landing-hero__title">Make the most of your WordCamp</h1>
                    <p class="landing-hero__lead">
                        See what's on, plan your talks and meet people who share your interests. All on your phone.
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

            {{-- Choosing the event is the one job of this page, so it comes right after the
            hero. Personalising is optional and follows it. --}}
            <section id="find-your-camp" aria-labelledby="find-your-camp-heading">
                <div class="section-head">
                    <h2 id="find-your-camp-heading" class="section-head__title">Choose your WordCamp</h2>
                    <span class="section-head__desc">Tap your event to see its schedule, people and guide</span>
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

                    <button type="button" class="btn btn--outline btn--full picker-more" data-picker-more hidden></button>

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

        {{-- Shown by native-bridge.js, in the store app only: event staff sign in to the panel there. --}}
        <p class="footer-note landing-footer" data-app-only hidden>Event staff: <a href="{{ route('manager.login') }}">Event manager sign in</a> &middot; <a href="{{ route('login') }}">Admin sign in</a></p>
        <p class="footer-note landing-footer" style="display:flex;flex-wrap:wrap;gap:4px 14px;justify-content:center"><a href="{{ route('privacy') }}">Privacy notice</a> <a href="{{ route('terms') }}">Terms of use</a> <a href="mailto:{{ config('campbuddy.legal.grievance_email') }}?subject=CampBuddy%20data%20request">Data request or complaint</a> @include('attendee.partials.apk-link')</p>
        <footer class="footer-note landing-footer">&copy; {{ date('Y') }} {{ config('campbuddy.name') }}. Made for the WordPress community.</footer>

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
