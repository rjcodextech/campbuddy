<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    @php
        $aboutTitle = 'About CampBuddy | Zero-Signup WordCamp Companion & Media Platform';
        $aboutDescription = 'CampBuddy is the free, offline-ready companion app and event media engine for WordCamps worldwide, proudly built by WPSimplified. Explore features for attendees, organizers, and media partnerships.';
    @endphp
    <title>{{ $aboutTitle }}</title>
    @include('attendee.partials.seo', [
        'seoTitle' => $aboutTitle,
        'seoDescription' => $aboutDescription,
        'seoSchema' => [
            \App\Support\Seo::guideArticle('About CampBuddy: The WordCamp Companion by WPSimplified', $aboutDescription),
            \App\Support\Seo::faq([
                ['What is CampBuddy?', 'CampBuddy is a free, zero-signup, offline-ready mobile companion designed specifically for WordCamp attendees, organizers, and media partners. Built by WPSimplified, it helps attendees navigate multi-track schedules, exchange digital contact cards via QR codes, find Contributor Day tables, and complete conference quests.'],
                ['Is CampBuddy free for attendees and organizers?', 'Yes, CampBuddy is 100% free and open to the WordPress community. There are no paid tiers, no ads, and no hidden subscriptions.'],
                ['Does CampBuddy require creating an account or downloading an app from the App Store?', 'No. CampBuddy requires zero account creation, zero email registration, and no password. It opens directly in any mobile web browser and can be installed to your home screen as a Progressive Web App (PWA). All personal data stays securely on your phone in IndexedDB.'],
                ['How does CampBuddy work offline on crowded venue Wi-Fi?', 'CampBuddy leverages Service Workers and client-side browser caching (IndexedDB). Once loaded, your schedule, starred sessions, guide, and digital Camp Card work without an internet connection. What needs a connection: attendee discovery, live announcements, sharing and forms (the full list is in the guide: campbuddy.club/guide#offline). Per-device rate limits ensure 1,000+ attendees sharing a single venue Wi-Fi NAT IP never face rate limit errors.'],
                ['How can WordCamp organizers list their event or partner as a media partner?', 'Organizers can connect their official WordCamp website schedule, enable real-time schedule delay broadcasts, send push announcements, and partner with WPSimplified for automated 1080x1920 social reels, video coverage, and social marketing kits.'],
            ]),
            \App\Support\Seo::breadcrumbs([['CampBuddy', route('home')], ['About CampBuddy', route('about')]]),
        ],
    ])

    @include('attendee.partials.head-meta', ['manifestUrl' => route('manifest', absolute: false)])
    @include('attendee.partials.analytics')
    @vite(['resources/css/fonts.css', 'resources/scss/main.scss', 'resources/js/attendee/app.js'])
</head>
<body class="app-shell" data-page-type="about">
    <div class="app-frame">
        @include('attendee.partials.desktop-notice')

        @include('attendee.partials.public-header')

        <main id="main-content" class="landing-main site-main about-page" tabindex="-1">
            <div class="guide-doc-layout">
                {{-- Left Side: Sticky Navigation Menu --}}
                <aside class="guide-doc-sidebar" aria-label="Page Navigation">
                    <div class="guide-doc-sidebar__card">
                        <div class="guide-doc-sidebar__head">
                            <span class="guide-doc-sidebar__title">
                                <x-attendee.line-icon name="compass" />
                                <span>On This Page</span>
                            </span>
                        </div>
                        <nav class="guide-jump guide-jump--sidebar" aria-label="Page sections">
                            <a class="chip" href="#attendee-features"><x-attendee.line-icon name="sparkles" /> <span>Attendee Features</span></a>
                            <a class="chip" href="#organizer-features"><x-attendee.line-icon name="megaphone" /> <span>For Organizers</span></a>
                            <a class="chip" href="#core-promises"><x-attendee.line-icon name="heart" /> <span>Core Promises</span></a>
                            <a class="chip" href="#faq"><x-attendee.line-icon name="help-circle" /> <span>FAQ</span></a>
                        </nav>
                    </div>
                </aside>

                {{-- Right Side: Complete Page Content --}}
                <div class="guide-doc-content">
                    {{-- Creative Hero Section --}}
                    <section class="hero-creative" aria-labelledby="about-hero-title">
                        <nav class="hero-breadcrumb" aria-label="Breadcrumb">
                            <a href="{{ route('home') }}">Home</a>
                            <span class="hero-breadcrumb__sep" aria-hidden="true">/</span>
                            <span class="hero-breadcrumb__current" aria-current="page">About CampBuddy</span>
                        </nav>

                        <div class="hero-kicker">
                            <x-attendee.line-icon name="sparkles" />
                            <span>A Community Initiative by WPSimplified</span>
                        </div>

                        <h1 id="about-hero-title" class="hero-creative__title">
                            The <span class="hero-creative__highlight">Zero-Signup</span> Offline Companion for WordCamp
                        </h1>

                        <p class="hero-creative__lead">
                            CampBuddy is the privacy-first mobile companion and event engine for WordCamps worldwide. Built by WPSimplified as a 100% free community gift &mdash; zero login, zero paper cards, and runs entirely offline.
                        </p>

                        <div class="hero-creative__pills">
                            <span class="hero-pill"><x-attendee.line-icon name="lock" /> Zero sign-up (DPDPA 2023)</span>
                            <span class="hero-pill"><x-attendee.line-icon name="wifi" /> 100% Offline-Ready</span>
                            <span class="hero-pill"><x-attendee.line-icon name="zap" /> Instant PWA (No app store)</span>
                            <span class="hero-pill"><x-attendee.line-icon name="heart" /> 100% Free Forever</span>
                        </div>

                        <div class="hero-creative__actions">
                            <a href="{{ route('home') }}#find-your-camp" class="btn btn--primary">
                                <span>Explore WordCamps</span>
                                <span aria-hidden="true">&rarr;</span>
                            </a>
                            <a href="#attendee-features" class="btn btn--outline">
                                <span>Core Features</span>
                                <span aria-hidden="true">&darr;</span>
                            </a>
                        </div>

                        <div class="hero-creative__waves" aria-hidden="true">
                            <svg viewBox="0 0 1200 120" preserveAspectRatio="none">
                                <path d="M0,0 C150,90 350,-40 500,40 C650,120 900,10 1200,40 L1200,120 L0,120 Z"></path>
                            </svg>
                        </div>
                    </section>

            {{-- Section 1: Attendee Superpowers (Bento Grid) --}}
            <section id="attendee-features" class="guide-section" aria-labelledby="features-heading">
                <div class="section-head">
                    <span class="u-eyebrow"><x-attendee.line-icon name="sparkles" /> Attendee Experience</span>
                    <h2 id="features-heading" class="section-head__title">Everything you need on WordCamp day</h2>
                    <span class="section-head__desc">Four focused capabilities designed to eliminate conference confusion, paper business cards, and hallway hesitation.</span>
                </div>

                <div class="about-bento">
                    {{-- Bento Card 1: Multi-Track Schedule & My Day --}}
                    <div class="about-bento-card">
                        <span class="about-bento-card__badge">
                            <x-attendee.line-icon name="calendar" />
                            <span>Timetable Intelligence</span>
                        </span>
                        <h3 class="about-bento-card__title">Smart Schedule &amp; "My Day"</h3>
                        <p class="about-bento-card__text">
                            Navigate parallel halls with ease. Star talks to build your personal timetable, see what is "Live Now" across rooms, and receive quiet 10-minute hall alerts so you never miss a keynote.
                        </p>
                        <div class="about-bento-card__chips">
                            <span class="about-chip"><x-attendee.line-icon name="clock" /> Live Now Ribbon</span>
                            <span class="about-chip"><x-attendee.line-icon name="star" /> 1-Tap My Day</span>
                            <span class="about-chip"><x-attendee.line-icon name="siren" /> 10-Min Hall Alert</span>
                        </div>
                    </div>

                    {{-- Bento Card 2: Digital Camp Card --}}
                    <div class="about-bento-card">
                        <span class="about-bento-card__badge">
                            <x-attendee.line-icon name="camera" />
                            <span>Paperless Networking</span>
                        </span>
                        <h3 class="about-bento-card__title">Digital Camp Card &amp; QR Swap</h3>
                        <p class="about-bento-card__text">
                            Your digital conference badge. Other attendees simply scan your QR with their normal phone camera to open your LinkedIn or website in seconds &mdash; zero paper cards and no login required.
                        </p>
                        <div class="about-bento-card__chips">
                            <span class="about-chip"><x-attendee.line-icon name="camera" /> Phone Camera Scan</span>
                            <span class="about-chip"><x-attendee.line-icon name="link" /> Direct LinkedIn</span>
                            <span class="about-chip"><x-attendee.line-icon name="sparkles" /> 7 Theme Styles</span>
                        </div>
                    </div>

                    {{-- Bento Card 3: Discovery & Hallway Quests --}}
                    <div class="about-bento-card">
                        <span class="about-bento-card__badge">
                            <x-attendee.line-icon name="users" />
                            <span>Community &amp; Icebreakers</span>
                        </span>
                        <h3 class="about-bento-card__title">Discovery &amp; Hallway Quests</h3>
                        <p class="about-bento-card__text">
                            Find attendees by shared interests (Core, design, plugins), send private mutual waves, and complete friendly hallway networking quests that make breaking the ice natural and fun.
                        </p>
                        <div class="about-bento-card__chips">
                            <span class="about-chip"><x-attendee.line-icon name="lock" /> 100% Spam-Free</span>
                            <span class="about-chip"><x-attendee.line-icon name="users" /> Mutual Waves</span>
                            <span class="about-chip"><x-attendee.line-icon name="ticket" /> WordCamp Quests</span>
                        </div>
                    </div>

                    {{-- Bento Card 4: Contributor Day Matchmaker --}}
                    <div class="about-bento-card">
                        <span class="about-bento-card__badge">
                            <x-attendee.line-icon name="wrench" />
                            <span>Open Source Contribution</span>
                        </span>
                        <h3 class="about-bento-card__title">Contributor Day in 3 Taps</h3>
                        <p class="about-bento-card__text">
                            First time at Contributor Day? Answer 3 quick questions about what you enjoy to find the exact team table (Docs, Polyglots, Core, Design) waiting for you with ready icebreaker scripts.
                        </p>
                        <div class="about-bento-card__chips">
                            <span class="about-chip"><x-attendee.line-icon name="wrench" /> 3-Question Wizard</span>
                            <span class="about-chip"><x-attendee.line-icon name="graduation-cap" /> College Fresher Tips</span>
                            <span class="about-chip"><x-attendee.line-icon name="coffee" /> Table Icebreakers</span>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Section 2: For Organizers & Media --}}
            <section id="organizer-features" class="guide-section" aria-labelledby="organizer-heading">
                <div class="section-head">
                    <span class="u-eyebrow"><x-attendee.line-icon name="megaphone" /> Organizer &amp; Media Suite</span>
                    <h2 id="organizer-heading" class="section-head__title">Operational calm for volunteer teams</h2>
                    <span class="section-head__desc">Tools built to handle real-world conference disruptions, schedule delays, and automated social media coverage.</span>
                </div>

                <div class="about-grid-3">
                    <div class="about-card">
                        <div class="about-card__icon about-card__icon--gold">
                            <x-attendee.line-icon name="clock" />
                        </div>
                        <h3 class="about-card__title">Live Delay Broadcaster</h3>
                        <p class="about-card__text">
                            Keynote ran 20 minutes late? Shift timetable tracks with a single slider in the organizer dashboard. Every attendee's phone updates instantly without mic shouting or confusion.
                        </p>
                        <span class="about-card__stat">
                            <x-attendee.line-icon name="zap" /> Instant Timetable Shift
                        </span>
                    </div>

                    <div class="about-card">
                        <div class="about-card__icon about-card__icon--primary">
                            <x-attendee.line-icon name="megaphone" />
                        </div>
                        <h3 class="about-card__title">Lockscreen Web Push</h3>
                        <p class="about-card__text">
                            Broadcast urgent notices, room swaps, lunch calls, or lost items directly to attendees' phone lockscreens via Web Push &mdash; even when their browser tab is completely closed.
                        </p>
                        <span class="about-card__stat">
                            <x-attendee.line-icon name="siren" /> Mic-Free Broadcasts
                        </span>
                    </div>

                    <div class="about-card">
                        <div class="about-card__icon about-card__icon--teal">
                            <x-attendee.line-icon name="camera" />
                        </div>
                        <h3 class="about-card__title">Automated Social Reels</h3>
                        <p class="about-card__text">
                            Generate production-ready 1080&times;1920 vertical video spotlights, speaker cards, and timetables for Instagram and LinkedIn with zero manual video editing required.
                        </p>
                        <span class="about-card__stat">
                            <x-attendee.line-icon name="sparkles" /> Viral Event Marketing
                        </span>
                    </div>
                </div>
            </section>

            {{-- Section 3: The 3 Core Promises --}}
            <section id="core-promises" class="guide-section" aria-labelledby="promises-heading">
                <div class="section-head">
                    <span class="u-eyebrow"><x-attendee.line-icon name="heart" /> Core Philosophy</span>
                    <h2 id="promises-heading" class="section-head__title">Why CampBuddy is fundamentally different</h2>
                    <span class="section-head__desc">Built from the ground up to respect attendee privacy, survive crowded venue Wi-Fi, and give back to the WordPress community.</span>
                </div>

                <div class="about-grid-3">
                    <div class="about-card">
                        <div class="about-card__icon about-card__icon--teal">
                            <x-attendee.line-icon name="lock" />
                        </div>
                        <h3 class="about-card__title">Zero Sign-Up &amp; DPDPA 2023</h3>
                        <p class="about-card__text">
                            No passwords, no magic links, and no email harvesting. Fully compliant with India's DPDPA 2023. All your starred sessions, Camp Card, and notes stay strictly on your phone in IndexedDB.
                        </p>
                        <span class="about-card__stat">
                            <x-attendee.line-icon name="lock" /> 100% On-Device Data
                        </span>
                    </div>

                    <div class="about-card">
                        <div class="about-card__icon about-card__icon--primary">
                            <x-attendee.line-icon name="wifi" />
                        </div>
                        <h3 class="about-card__title">Works 100% Offline</h3>
                        <p class="about-card__text">
                            Instant Progressive Web App cached locally on first load. Per-device rate limits ensure 1,500+ attendees sharing venue Wi-Fi NAT gateways never experience connection lockouts.
                            <a href="{{ route('guide') }}#offline">What works offline, and what needs internet</a>
                        </p>
                        <span class="about-card__stat">
                            <x-attendee.line-icon name="wifi" /> Service Worker Cached
                        </span>
                    </div>

                    <div class="about-card">
                        <div class="about-card__icon about-card__icon--gold">
                            <x-attendee.line-icon name="heart" />
                        </div>
                        <h3 class="about-card__title">Free Community Gift</h3>
                        <p class="about-card__text">
                            Conceived, built, and supported by WPSimplified as an ongoing open-source contribution to the global WordPress community. Free forever for every WordCamp worldwide.
                        </p>
                        <span class="about-card__stat">
                            <x-attendee.line-icon name="heart" /> Zero Ads &middot; Zero Paywalls
                        </span>
                    </div>
                </div>
            </section>

            {{-- Section 4: Clear Answers FAQ --}}
            <section id="faq" class="guide-section" aria-labelledby="about-faq-heading">
                <div class="section-head">
                    <span class="u-eyebrow"><x-attendee.line-icon name="help-circle" /> Common Inquiries</span>
                    <h2 id="about-faq-heading" class="section-head__title">Frequently asked questions</h2>
                    <span class="section-head__desc">Clear answers to common questions about privacy, offline reliability, and event listing.</span>
                </div>

                <div class="guide-accordion">
                    <details class="guide-accordion__item" data-track-open="faq_open" data-track-question="app_store">
                        <summary>Do attendees need to download an app from the App Store or Google Play?</summary>
                        <p>
                            No. CampBuddy is a Progressive Web App (PWA) that runs instantly in mobile Safari, Chrome, Firefox, or Samsung Internet. Attendees simply visit the website or scan an on-venue QR code. You can optionally tap "Add to Home Screen" to install it as an app without visiting an app store.
                        </p>
                    </details>

                    <details class="guide-accordion__item" data-track-open="faq_open" data-track-question="free_cost">
                        <summary>Is CampBuddy really 100% free?</summary>
                        <p>
                            Yes. CampBuddy is entirely free for both attendees and event organizers. There are no premium features, no subscriptions, and no paid unlockables. It is developed and sponsored by WPSimplified as a community gift.
                        </p>
                    </details>

                    <details class="guide-accordion__item" data-track-open="faq_open" data-track-question="add_event">
                        <summary>How can our WordCamp get listed on CampBuddy?</summary>
                        <p>
                            Any official WordCamp can be listed. Our platform automatically ingests the public schedule, speaker details, and sponsor lists directly from wordcamp.org. Organizers can reach out to <code>hello@wpsimplified.in</code> to verify and activate custom features like Live Delays and push alerts.
                        </p>
                    </details>

                    <details class="guide-accordion__item" data-track-open="faq_open" data-track-question="data_privacy">
                        <summary>What happens to my personal data after the event ends?</summary>
                        <p>
                            Your starred talks, Camp Card, and notes remain stored locally in your phone's browser cache. You can view your event memories at any time, export them to a PDF keepsake, or wipe all data immediately with the "Wipe Local Data" button in settings.
                        </p>
                    </details>
                </div>
            </section>

            {{-- Data Request & Grievance Complaint --}}
            @include('attendee.partials.data-request-section')

            {{-- Co-Branded WPSimplified Banner --}}
            <section class="about-wps-banner" aria-labelledby="wps-partnership-title">
                <div class="about-wps-banner__inner">
                    <div class="about-wps-banner__badge">
                        <span class="live-dot live-dot--green"></span>
                        <span>Open Source Community Initiative</span>
                    </div>
                    <h3 id="wps-partnership-title" class="about-wps-banner__title">
                        CampBuddy &times; <a href="https://wpsimplified.in" target="_blank" rel="noopener">WPSimplified</a>
                    </h3>
                    <p class="about-wps-banner__desc">
                        Simplifying WordPress for everyone. Built and maintained by WPSimplified as a free, open-source gift for the global WordPress community. Zero sign-up, zero ads, and 100% offline-ready for every WordCamp worldwide.
                    </p>
                    <div class="about-wps-banner__actions">
                        <a href="{{ route('home') }}#find-your-camp" class="btn btn--primary">
                            <span>Select Your WordCamp</span>
                            <span aria-hidden="true">&rarr;</span>
                        </a>
                        <a href="{{ route('guide') }}" class="btn btn--outline">
                            <span>First-Timer Guide</span>
                        </a>
                        <a href="https://wpsimplified.in" target="_blank" rel="noopener" class="btn btn--outline">
                            <span>Visit WPSimplified</span>
                            <span aria-hidden="true">&rarr;</span>
                        </a>
                    </div>
                </div>
            </section>
        </div>
    </div>
</main>

        @include('attendee.partials.footer')
    </div>

    @include('attendee.templates.shared')
</body>
</html>
