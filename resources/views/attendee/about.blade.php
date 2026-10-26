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
                ['What is CampBuddy?', 'CampBuddy is a free, zero-signup, offline-ready mobile web app designed specifically for WordCamp attendees, organizers, and media partners. Built by WPSimplified, it helps attendees navigate multi-track schedules, exchange digital contact cards via QR codes, find Contributor Day tables, and complete conference quests.'],
                ['Is CampBuddy free for attendees and organizers?', 'Yes, CampBuddy is 100% free and open to the WordPress community. There are no paid tiers, no ads, and no hidden subscriptions.'],
                ['Does CampBuddy require creating an account or downloading an app from the App Store?', 'No. CampBuddy requires zero account creation, zero email registration, and no password. It opens directly in any mobile web browser and can be installed to your home screen as a Progressive Web App (PWA). All personal data stays securely on your phone in IndexedDB.'],
                ['How does CampBuddy work offline on crowded venue Wi-Fi?', 'CampBuddy leverages Service Workers and client-side browser caching (IndexedDB). Once loaded, your schedule, starred sessions, guide, and digital Camp Card work without an internet connection. Per-device rate limits ensure 1,000+ attendees sharing a single venue Wi-Fi NAT IP never face rate limit errors.'],
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
                <a href="{{ route('home') }}" class="topbar__text-btn" data-track="home_link_click" data-track-target="wordcamps">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6" /></svg>
                    <span>All WordCamps</span>
                </a>
            </div>
        </header>

        <main id="main-content" class="landing-main about-page" tabindex="-1">
            {{-- Creative Hero Section (Inspired by WPSimplified) --}}
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
                    The <span class="hero-creative__highlight">Zero-Signup</span>, Offline Companion for WordCamp
                </h1>

                <p class="hero-creative__lead">
                    CampBuddy is the privacy-first mobile companion and media engine for WordCamps worldwide. See what's on now, exchange digital Camp Cards via QR code, find your Contributor Day team, and experience conferences without friction.
                </p>

                <div class="hero-creative__pills">
                    <span class="hero-pill"><x-attendee.line-icon name="lock" /> Zero sign-up (DPDPA 2023)</span>
                    <span class="hero-pill"><x-attendee.line-icon name="wifi" /> Works 100% offline</span>
                    <span class="hero-pill"><x-attendee.line-icon name="zap" /> Instant PWA (No app store)</span>
                    <span class="hero-pill"><x-attendee.line-icon name="heart" /> Free community gift</span>
                </div>

                <div class="hero-creative__actions">
                    <a href="{{ route('home') }}#find-your-camp" class="btn btn--primary">
                        <span>Explore WordCamps</span>
                        <span aria-hidden="true">&rarr;</span>
                    </a>
                    <a href="#three-pillars" class="btn btn--outline">
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

            {{-- Quick Table of Contents / Jump Chips --}}
            <nav class="guide-jump" aria-label="Page sections">
                <a class="chip" href="#three-pillars">3 Core Pillars</a>
                <a class="chip" href="#attendee-features">Attendee Features</a>
                <a class="chip" href="#organizer-features">For Organizers</a>
                <a class="chip" href="#comparison-matrix">App Comparison</a>
                <a class="chip" href="#architecture">Architecture &amp; Privacy</a>
                <a class="chip" href="#faq">FAQ</a>
            </nav>

            {{-- Section 1: The Three Core Pillars --}}
            <section id="three-pillars" class="guide-section" aria-labelledby="pillars-heading">
                <div class="section-head">
                    <h2 id="pillars-heading" class="section-head__title">The 3 Zero-Friction Promises</h2>
                    <span class="section-head__desc">Why CampBuddy is fundamentally different from generic conference apps</span>
                </div>

                <div class="about-grid-3">
                    <div class="about-card">
                        <div class="about-card__icon" style="background:#eef6f2;color:#047a7c;">
                            <x-attendee.line-icon name="lock" />
                        </div>
                        <h3 class="about-card__title">1. Zero Sign-Up &amp; Absolute Privacy</h3>
                        <p class="about-card__text">
                            No passwords, no magic links, no personal email harvesting. CampBuddy is fully compliant with India's <strong>DPDPA 2023</strong> and global privacy standards. All your starred sessions, contact cards, and notes stay strictly on your device in <code>IndexedDB</code>. You own your data.
                        </p>
                    </div>

                    <div class="about-card">
                        <div class="about-card__icon" style="background:#fff3eb;color:#c33a19;">
                            <x-attendee.line-icon name="wifi" />
                        </div>
                        <h3 class="about-card__title">2. Bulletproof Venue Wi-Fi &amp; Offline Cache</h3>
                        <p class="about-card__text">
                            Conference Wi-Fi regularly bottlenecks with 1,000+ smartphones connected to a single router. CampBuddy is cached locally via Service Workers on first visit. Plus, our backend enforces <strong>per-device rate limits</strong> so attendees sharing a single NAT IP never block each other.
                        </p>
                    </div>

                    <div class="about-card">
                        <div class="about-card__icon" style="background:#fef7ea;color:#d98a1c;">
                            <x-attendee.line-icon name="heart" />
                        </div>
                        <h3 class="about-card__title">3. Community-First by WPSimplified</h3>
                        <p class="about-card__text">
                            CampBuddy was created and is actively maintained by <strong>WPSimplified</strong> as a continuous contribution to the global WordPress community. No corporate paywalls, no aggressive banner ads, and no locked features. Free forever for every WordCamp.
                        </p>
                    </div>
                </div>
            </section>

            {{-- Section 2: Complete Attendee Feature Suite --}}
            <section id="attendee-features" class="guide-section" aria-labelledby="features-heading">
                <div class="section-head">
                    <h2 id="features-heading" class="section-head__title">What CampBuddy Does For Attendees</h2>
                    <span class="section-head__desc">Every feature built to solve a real conference pain point</span>
                </div>

                <div class="about-features-list">
                    {{-- Feature 1: Multi-track Schedule & My Day --}}
                    <article class="about-feature-item">
                        <div class="about-feature-item__badge">
                            <x-attendee.line-icon name="calendar" />
                            <span>Schedule Intelligence</span>
                        </div>
                        <h3 class="about-feature-item__title">Smart Multi-Track Schedule &amp; "My Day" Planner</h3>
                        <p class="about-feature-item__description">
                            WordCamps host multiple simultaneous tracks across development, design, business, and community. Flipping between printed handouts or clunky websites is frustrating.
                        </p>
                        <div class="about-feature-item__specs">
                            <div class="spec-box">
                                <strong>Live Time Ribbon:</strong> Automatically shows what is "Happening Now" and "Up Next" based on the current conference hour.
                            </div>
                            <div class="spec-box">
                                <strong>Beginner Badges:</strong> Identifies accessible, welcoming talks for students and first-time attendees.
                            </div>
                            <div class="spec-box">
                                <strong>Lockscreen Push Alerts:</strong> Star the talks you want; receive a quiet Web Push alert 10 minutes before the session starts so you never miss a keynote.
                            </div>
                        </div>
                    </article>

                    {{-- Feature 2: Digital Camp Card & Contact Swap --}}
                    <article class="about-feature-item">
                        <div class="about-feature-item__badge">
                            <x-attendee.line-icon name="id-card" />
                            <span>Seamless Networking</span>
                        </div>
                        <h3 class="about-feature-item__title">Scannable Digital Camp Card &amp; Instant QR Swap</h3>
                        <p class="about-feature-item__description">
                            Paper business cards get crumpled in pockets or lost in coffee queues. Spelling out usernames over loud conference hall chatter takes too long.
                        </p>
                        <div class="about-feature-item__specs">
                            <div class="spec-box">
                                <strong>5 Badge Styles:</strong> Choose from Badge, Ticket, Minimal, Split, and Classic layouts rendered with crisp custom typography.
                            </div>
                            <div class="spec-box">
                                <strong>Instant QR Scan:</strong> Other attendees scan your Camp Card directly with their standard phone camera to land on your LinkedIn or website.
                            </div>
                            <div class="spec-box">
                                <strong>Opt-In "Who to Meet":</strong> Browse attendees by shared skills (e.g. Core contributors, agency founders, plugin developers) and exchange profiles with a single tap.
                            </div>
                        </div>
                    </article>

                    {{-- Feature 3: Contributor Day Guided Matchmaker --}}
                    <article class="about-feature-item">
                        <div class="about-feature-item__badge">
                            <x-attendee.line-icon name="wrench" />
                            <span>Community Impact</span>
                        </div>
                        <h3 class="about-feature-item__title">Contributor Day Matchmaker: 3-Question Table Guide</h3>
                        <p class="about-feature-item__description">
                            Contributor Day is the heart of WordCamp, yet newcomers frequently feel intimidated by 20 tables with unknown acronyms (Core, Polyglots, Meta, CLI, Triage).
                        </p>
                        <div class="about-feature-item__specs">
                            <div class="spec-box">
                                <strong>3-Question Wizard:</strong> Asks what you enjoy (writing docs, speaking regional languages, finding bugs, organizing meetups, writing PHP/JS).
                            </div>
                            <div class="spec-box">
                                <strong>Table Recommendations:</strong> Pinpoints the exact table you should join (e.g. Polyglots, Documentation, Community, Theme Review).
                            </div>
                            <div class="spec-box">
                                <strong>First-Time Icebreakers:</strong> Tells you exactly what tools to have ready (WordPress.org username, Slack) and what to say to the table lead when you sit down.
                            </div>
                        </div>
                    </article>

                    {{-- Feature 4: WordCamp Quests & Gamification --}}
                    <article class="about-feature-item">
                        <div class="about-feature-item__badge">
                            <x-attendee.line-icon name="sparkles" />
                            <span>Conference Gamification</span>
                        </div>
                        <h3 class="about-feature-item__title">WordCamp Quests: Icebreakers That Build Real Community</h3>
                        <p class="about-feature-item__description">
                            Standing alone in a room of 500 attendees is tough for first-timers and students. CampBuddy turns hallway networking into an approachable personal game.
                        </p>
                        <div class="about-feature-item__specs">
                            <div class="spec-box">
                                <strong>Friendly Challenges:</strong> "Say hi to an attendee from another city", "Ask a question during Q&amp;A", "Visit 3 sponsor booths", "Take a group photo".
                            </div>
                            <div class="spec-box">
                                <strong>Progress Badges:</strong> Tick off achievements as you go and unlock conference completion milestones.
                            </div>
                            <div class="spec-box">
                                <strong>Confidence Builder:</strong> Validated across hundreds of attendees as the single best way to overcome social hesitation.
                            </div>
                        </div>
                    </article>

                    {{-- Feature 5: Sponsor Deals, Swag & Memories --}}
                    <article class="about-feature-item">
                        <div class="about-feature-item__badge">
                            <x-attendee.line-icon name="ticket" />
                            <span>Value &amp; Keepsakes</span>
                        </div>
                        <h3 class="about-feature-item__title">Exclusive Sponsor Deals, WordCamp 101 Guide &amp; Memories</h3>
                        <p class="about-feature-item__description">
                            WordCamps offer incredible value that attendees often miss because information is scattered across pamphlets, swag tables, and announcements.
                        </p>
                        <div class="about-feature-item__specs">
                            <div class="spec-box">
                                <strong>Exclusive Deals Hub:</strong> Access verified discounts on WordPress hosting, themes, plugins, and SaaS tools directly inside the app.
                            </div>
                            <div class="spec-box">
                                <strong>WordCamp 101 Guide:</strong> A built-in 5-minute handbook covering jargon (Wapuus, Hallway Track, Swag), student tips, and event etiquette.
                            </div>
                            <div class="spec-box">
                                <strong>Conference Memories &amp; PDF:</strong> View your personalized end-of-event recap, export a keepsake PDF, or migrate data to another phone using a 6-digit sync code.
                            </div>
                        </div>
                    </article>
                </div>
            </section>

            {{-- Section 3: How CampBuddy Empowers Event Organizers --}}
            <section id="organizer-features" class="guide-section" aria-labelledby="organizer-heading">
                <div class="section-head">
                    <h2 id="organizer-heading" class="section-head__title">How CampBuddy Supercharges WordCamp Organizers</h2>
                    <span class="section-head__desc">Operational peace-of-mind for local volunteer teams</span>
                </div>

                <div class="about-grid-2">
                    <div class="about-box">
                        <div class="about-box__icon"><x-attendee.line-icon name="clock" /></div>
                        <h3>Live Schedule Delay Broadcaster</h3>
                        <p>
                            When a keynote speech runs 15 minutes over or lunch is pushed back, organizers can adjust track delay offsets with a single slider in the Admin dashboard. All attendee phones update instantly without reprints or confusion.
                        </p>
                    </div>

                    <div class="about-box">
                        <div class="about-box__icon"><x-attendee.line-icon name="megaphone" /></div>
                        <h3>Emergency Announcements via Web Push</h3>
                        <p>
                            Broadcast urgent notices, room swaps, lost-and-found items, or group photo calls directly to attendees' phone lockscreens via VAPID Web Push notifications—even when the browser tab is closed.
                        </p>
                    </div>

                    <div class="about-box">
                        <div class="about-box__icon"><x-attendee.line-icon name="zap" /></div>
                        <h3>Automated Central Data Sync</h3>
                        <p>
                            Syncs speaker names, bios, avatars, session descriptions, tracks, and sponsor tiers automatically from official WordCamp website feeds. Zero duplicate data entry for busy volunteer teams.
                        </p>
                    </div>

                    <div class="about-box">
                        <div class="about-box__icon"><x-attendee.line-icon name="users" /></div>
                        <h3>Granular Event Manager Delegation</h3>
                        <p>
                            Local organizing leads (e.g. WordCamp Bengaluru, WordCamp Delhi, WordCamp Asia) can be granted dedicated, scoped access to manage their specific event's deals, quests, and delays with full audit activity logs.
                        </p>
                    </div>
                </div>
            </section>

            {{-- Section 5: Comparison Matrix (Infographic) --}}
            <section id="comparison-matrix" class="guide-section" aria-labelledby="matrix-heading">
                <div class="section-head">
                    <h2 id="matrix-heading" class="section-head__title">How CampBuddy Compares</h2>
                    <span class="section-head__desc">Traditional Event Apps vs CampBuddy with WPSimplified</span>
                </div>

                <div class="about-table-wrap">
                    <table class="about-table">
                        <thead>
                            <tr>
                                <th>Feature / Criterion</th>
                                <th>Traditional Event Apps</th>
                                <th class="highlight-col">CampBuddy &times; WPSimplified</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>User Sign-Up &amp; Accounts</strong></td>
                                <td>Mandatory email, password, and social sign-in</td>
                                <td class="highlight-col"><span class="table-tag table-tag--green">Zero Sign-Up Required</span></td>
                            </tr>
                            <tr>
                                <td><strong>App Store Download</strong></td>
                                <td>100MB+ download required from App Store / Play Store</td>
                                <td class="highlight-col"><span class="table-tag table-tag--green">Instant PWA in Web Browser</span></td>
                            </tr>
                            <tr>
                                <td><strong>Data Privacy &amp; DPDPA</strong></td>
                                <td>Centralized user databases, tracking cookies &amp; ads</td>
                                <td class="highlight-col"><span class="table-tag table-tag--green">100% On-Device IndexedDB</span></td>
                            </tr>
                            <tr>
                                <td><strong>Crowded Venue Wi-Fi</strong></td>
                                <td>Crashes or slows down on overloaded venue routers</td>
                                <td class="highlight-col"><span class="table-tag table-tag--green">Offline-First Service Worker</span></td>
                            </tr>
                            <tr>
                                <td><strong>Rate Limiting</strong></td>
                                <td>Shared venue NAT IP gets blocked after 50 hits</td>
                                <td class="highlight-col"><span class="table-tag table-tag--green">Per-Device Anonymous ID Tokens</span></td>
                            </tr>
                            <tr>
                                <td><strong>Networking Exchange</strong></td>
                                <td>Physical business cards easily misplaced or lost</td>
                                <td class="highlight-col"><span class="table-tag table-tag--green">Scannable Digital Camp Card QR</span></td>
                            </tr>
                            <tr>
                                <td><strong>Schedule Delays</strong></td>
                                <td>Outdated printed schedules or delayed notices</td>
                                <td class="highlight-col"><span class="table-tag table-tag--green">1-Click Live Delay Broadcast</span></td>
                            </tr>
                            <tr>
                                <td><strong>Social Reels &amp; Marketing</strong></td>
                                <td>Manual, slow external video editing</td>
                                <td class="highlight-col"><span class="table-tag table-tag--green">Automated 1080&times;1920 Reel Studio</span></td>
                            </tr>
                            <tr>
                                <td><strong>Cost to WordCamp</strong></td>
                                <td>$2,000 to $10,000+ app licensing fees</td>
                                <td class="highlight-col"><span class="table-tag table-tag--green">100% Free Community Contribution</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- Section 6: Architecture & Security Overview --}}
            <section id="architecture" class="guide-section" aria-labelledby="arch-heading">
                <div class="section-head">
                    <h2 id="arch-heading" class="section-head__title">Technical Architecture &amp; Privacy</h2>
                    <span class="section-head__desc">Engineered for speed, security, and low-bandwidth resilience</span>
                </div>

                <div class="about-grid-3">
                    <div class="about-card">
                        <div class="about-card__icon" style="background:#f0fdf4;color:#166534;"><x-attendee.line-icon name="laptop" /></div>
                        <h3 class="about-card__title">Client-First PWA Stack</h3>
                        <p class="about-card__text">
                            Built with lightweight, vanilla JavaScript (ES modules) paired with modern CSS and Service Workers. Loads in under 0.8 seconds on 3G connections. No bloated front-end frameworks draining attendee battery life.
                        </p>
                    </div>

                    <div class="about-card">
                        <div class="about-card__icon" style="background:#eff6ff;color:#1e40af;"><x-attendee.line-icon name="wifi" /></div>
                        <h3 class="about-card__title">Smart Rate Limiting</h3>
                        <p class="about-card__text">
                            CampBuddy identifies clients via an anonymous on-device random identifier rather than raw IP addresses. Even if 1,500 attendees browse simultaneously through a single conference NAT gateway, nobody is locked out.
                        </p>
                    </div>

                    <div class="about-card">
                        <div class="about-card__icon" style="background:#fdf2f8;color:#9d174d;"><x-attendee.line-icon name="lock" /></div>
                        <h3 class="about-card__title">DPDPA 2023 Compliance</h3>
                        <p class="about-card__text">
                            No personal data leaves the device unless explicitly chosen by the user (such as opting into the public attendee matching roster). Any user can wipe their local data instantly with a single tap.
                        </p>
                    </div>
                </div>
            </section>

            {{-- Section 7: Frequently Asked Questions --}}
            <section id="faq" class="guide-section" aria-labelledby="about-faq-heading">
                <div class="section-head">
                    <h2 id="about-faq-heading" class="section-head__title">Frequently Asked Questions</h2>
                    <span class="section-head__desc">Clear answers with zero ambiguity</span>
                </div>

                <div class="guide-accordion">
                    <details class="guide-accordion__item" data-track-open="faq_open" data-track-question="app_store">
                        <summary>Do attendees need to download CampBuddy from the App Store or Google Play?</summary>
                        <p>
                            No. CampBuddy is a Progressive Web App (PWA) that runs instantly in mobile Safari, Chrome, Firefox, or Samsung Internet. Attendees simply visit the website or scan an on-venue QR code. They can optionally tap "Add to Home Screen" to install it as an app without visiting an app store.
                        </p>
                    </details>

                    <details class="guide-accordion__item" data-track-open="faq_open" data-track-question="free_cost">
                        <summary>Is CampBuddy really 100% free?</summary>
                        <p>
                            Yes. CampBuddy is entirely free for both attendees and event organizers. There are no premium features, no subscriptions, and no paid unlockables. It is developed and sponsored by WPSimplified as a community contribution.
                        </p>
                    </details>

                    <details class="guide-accordion__item" data-track-open="faq_open" data-track-question="add_event">
                        <summary>How can my WordCamp (e.g. Bengaluru, Delhi, Asia) get listed on CampBuddy?</summary>
                        <p>
                            Any official WordCamp can be listed. Our platform ingests the public schedule, speaker details, and sponsor lists directly from wordcamp.org. Organizers can reach out to <code>hello@wpsimplified.in</code> to verify and activate custom features such as Live Delays and push alerts.
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

            {{-- Footer CTA Banner --}}
            <section class="about-wps" style="margin:2.5rem 0;padding:2rem 1.5rem;background:var(--color-surface, #fff);border-radius:1rem;border:1px solid var(--color-line, rgba(0,0,0,0.08));text-align:center">
                <div style="display:flex;align-items:center;justify-content:center;gap:0.5rem;margin-bottom:0.5rem">
                    <span style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.06em;font-weight:700;color:var(--color-muted, #5f6f69)">Part of the Ecosystem</span>
                </div>
                <h3 style="font-size:1.5rem;font-weight:800;margin-bottom:0.4rem">
                    <a href="https://wpsimplified.in" target="_blank" rel="noopener" style="color:var(--color-primary, #047857);text-decoration:none">WPSimplified</a> &times; CampBuddy
                </h3>
                <p style="font-size:0.95rem;color:var(--color-muted, #43544e);max-width:34rem;margin:0 auto 1.4rem;line-height:1.6">
                    Simplifying WordPress for everyone. Whether you are attending your first WordCamp or organizing a conference for 2,000 people, we are here to support your journey.
                </p>
                <div style="display:flex;flex-wrap:wrap;gap:0.75rem;justify-content:center">
                    <a href="{{ route('home') }}#find-your-camp" class="btn btn--primary">
                        <span>Select Your WordCamp</span>
                        <span aria-hidden="true">&rarr;</span>
                    </a>
                    <a href="{{ route('guide') }}" class="btn btn--outline">
                        <span>Beginner's WordCamp Guide</span>
                    </a>
                    <a href="https://wpsimplified.in" target="_blank" rel="noopener" class="btn btn--outline" style="border-color:#047857;color:#047857;">
                        <span>Visit WPSimplified</span>
                        <span aria-hidden="true">&rarr;</span>
                    </a>
                </div>
            </section>
        </main>

        <p class="footer-note landing-footer" style="display:flex;flex-wrap:wrap;gap:4px 14px;justify-content:center">
            <a href="{{ route('about') }}">About CampBuddy</a>
            <a href="{{ route('guide') }}">WordCamp 101</a>
            <a href="{{ route('brand-guidelines') }}">Brand Guidelines</a>
            <a href="{{ route('privacy') }}">Privacy notice</a>
            <a href="{{ route('terms') }}">Terms of use</a>
            <a href="mailto:{{ config('campbuddy.legal.grievance_email') }}?subject=CampBuddy%20inquiry">Contact us</a>
            @include('attendee.partials.apk-link')
        </p>
        <footer class="footer-note landing-footer">&copy; {{ date('Y') }} {{ config('campbuddy.name') }} &middot; Built by <a href="https://wpsimplified.in" target="_blank" rel="noopener" style="font-weight:600;color:inherit;text-decoration:underline">WPSimplified</a> for the WordPress community.</footer>
    </div>

    @include('attendee.templates.shared')
</body>
</html>
