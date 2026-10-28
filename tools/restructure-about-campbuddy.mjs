import fs from 'fs';

const content = `{{--
    "What is CampBuddy?" for the WordCamp picker ("/")
      $part = 'steps'  — 4 Superpowers Bento Grid
      $part = 'more'   — Interactive Experience Tour, Pathways, WPS Banner, FAQ
--}}
@if ($part === 'steps')
    <section class="landing-bento hide-in-pwa" aria-labelledby="bento-heading">
        <div class="section-head section-head--center">
            <span class="u-eyebrow"><x-attendee.line-icon name="sparkles" /> Built For The Event Day</span>
            <h2 id="bento-heading" class="section-head__title">How CampBuddy helps you at WordCamp</h2>
            <span class="section-head__desc">Four superpowers that eliminate conference confusion, paper waste, and Wi-Fi dropouts</span>
        </div>

        <div class="bento-grid">
            <div class="bento-card bento-card--schedule">
                <div class="bento-card__header">
                    <div class="bento-card__icon"><x-attendee.line-icon name="calendar" /></div>
                    <span class="bento-card__badge">Works 100% Offline</span>
                </div>
                <h3 class="bento-card__title">1. Multi-Track Schedule &amp; "My Day"</h3>
                <p class="bento-card__desc">Know what's happening right now across multiple halls. Star talks into your personal "My Day" agenda with instant conflict alerts &mdash; cached offline on your device.</p>
                <div class="bento-card__footer">
                    <span class="bento-pill"><x-attendee.line-icon name="clock" /> Conflict Warnings</span>
                    <span class="bento-pill"><x-attendee.line-icon name="star" /> Talk Bookmarks</span>
                </div>
            </div>

            <div class="bento-card bento-card--networking">
                <div class="bento-card__header">
                    <div class="bento-card__icon"><x-attendee.line-icon name="qr-code" /></div>
                    <span class="bento-card__badge">Zero Paper Cards</span>
                </div>
                <h3 class="bento-card__title">2. Digital Camp Card &amp; QR Swap</h3>
                <p class="bento-card__desc">Exchange LinkedIn, website, and social contacts in 2 seconds via your phone camera. Scanners don't need to create an account or download any app.</p>
                <div class="bento-card__footer">
                    <span class="bento-pill"><x-attendee.line-icon name="camera" /> Camera QR Swap</span>
                    <span class="bento-pill"><x-attendee.line-icon name="download" /> Save to Phone</span>
                </div>
            </div>

            <div class="bento-card bento-card--contribute">
                <div class="bento-card__header">
                    <div class="bento-card__icon"><x-attendee.line-icon name="wrench" /></div>
                    <span class="bento-card__badge">30-Second Quiz</span>
                </div>
                <h3 class="bento-card__title">3. Contributor Day Matchmaker</h3>
                <p class="bento-card__desc">First time contributing? A friendly 30-second skill quiz routes you directly to Core, Polyglots, Design, Community, or Docs tables based on your background.</p>
                <div class="bento-card__footer">
                    <span class="bento-pill"><x-attendee.line-icon name="compass" /> Skill Quiz</span>
                    <span class="bento-pill"><x-attendee.line-icon name="users" /> Table Routing</span>
                </div>
            </div>

            <div class="bento-card bento-card--privacy">
                <div class="bento-card__header">
                    <div class="bento-card__icon"><x-attendee.line-icon name="lock" /></div>
                    <span class="bento-card__badge">DPDPA 2023 Compliant</span>
                </div>
                <h3 class="bento-card__title">4. Zero Sign-Up &amp; Absolute Privacy</h3>
                <p class="bento-card__desc">No password fatigue, zero email harvesting, and no tracking cookies. All your conference notes, bookmarks, and cards live strictly on your device in IndexedDB.</p>
                <div class="bento-card__footer">
                    <span class="bento-pill"><x-attendee.line-icon name="shield" /> Client-Side Only</span>
                    <span class="bento-pill"><x-attendee.line-icon name="trash" /> Instant Erasure</span>
                </div>
            </div>
        </div>
    </section>
@else
    {{-- Section: Experience Tour --}}
    <section class="about-tour" aria-labelledby="about-tour-heading">
        <div class="section-head section-head--center">
            <span class="u-eyebrow"><x-attendee.line-icon name="smartphone" /> Inside The App</span>
            <h2 id="about-tour-heading" class="section-head__title">See it in action</h2>
            <span class="section-head__desc">A live preview of the 5 core surfaces built into every WordCamp</span>
        </div>

        <div class="tour" data-tour>
            <div class="tour__phone">
                <div class="tour__speaker"></div>
                <div class="tour__screen" aria-live="off">
                    @foreach ([
                        ['1-home', 'Home shows what\'s on now and next, with a tip for that part of the day.'],
                        ['2-schedule', 'Browse the schedule and star the talks you want. Beginner-friendly ones are marked.'],
                        ['3-people', 'Join attendee discovery to find people who share your interests, then go and say hi.'],
                        ['4-guide', 'First WordCamp? A 5-minute guide explains how the day runs and the words people use.'],
                        ['5-camp-card', 'Your Camp Card: people scan the QR code and land on your LinkedIn.'],
                    ] as $i => [$image, $caption])
                        <figure class="tour__slide" data-tour-slide @if ($i > 0) hidden @endif>
                            <img src="/media/tour/{{ $image }}.jpg" alt="{{ $caption }}" width="360" height="720" @if ($i > 0) loading="lazy" @endif decoding="async">
                        </figure>
                    @endforeach
                </div>
            </div>

            <div class="tour__side">
                <div class="tour__feature-indicator">
                    <span class="live-dot live-dot--green"></span>
                    <span>Interactive Screen Preview</span>
                </div>
                <p class="tour__caption" data-tour-caption aria-live="polite"></p>
                <div class="tour__controls">
                    <button type="button" class="tour__btn" data-tour-prev aria-label="Previous screen"><x-attendee.line-icon name="chevron-left" /></button>
                    <div class="tour__dots" role="tablist" aria-label="Screens" data-tour-dots></div>
                    <button type="button" class="tour__btn" data-tour-next aria-label="Next screen"><x-attendee.line-icon name="chevron-right" /></button>
                    <button type="button" class="tour__btn tour__btn--play" data-tour-toggle aria-label="Pause" data-track="tour_control" data-track-via="pause_play"><x-attendee.line-icon name="pause" /></button>
                </div>
            </div>
        </div>
    </section>

    {{-- Section: Who It's For --}}
    <section class="about-who" aria-labelledby="about-who-heading">
        <div class="section-head section-head--center">
            <span class="u-eyebrow"><x-attendee.line-icon name="users" /> Tailored Pathways</span>
            <h2 id="about-who-heading" class="section-head__title">Who it's for</h2>
            <span class="section-head__desc">Personalized workflows built specifically for your conference goals</span>
        </div>
        <div class="about-who__grid">
            <a class="about-who__card" href="{{ route('guide') }}" data-track="guide_open" data-track-surface="picker_who_first">
                <div class="about-who__badge">First-Timers</div>
                <div class="about-who__icon about-who__icon--first">
                    <x-attendee.line-icon name="sprout" />
                </div>
                <h3 class="about-who__title">Your first WordCamp</h3>
                <p class="about-who__text">Never attended a tech conference? Our 5-minute handbook, beginner-friendly talk flags, and hallway icebreakers guide you step-by-step.</p>
                <ul class="about-who__points">
                    <li>5-minute start-here guide</li>
                    <li>Beginner-friendly talk ratings</li>
                    <li>Hallway track etiquette &amp; tips</li>
                </ul>
                <span class="about-who__cta">Read the first-timer guide &rarr;</span>
            </a>

            <a class="about-who__card" href="{{ route('guide') }}#guide-students" data-track="guide_open" data-track-surface="picker_who_student">
                <div class="about-who__badge">Students &amp; Freshers</div>
                <div class="about-who__icon about-who__icon--student">
                    <x-attendee.line-icon name="graduation-cap" />
                </div>
                <h3 class="about-who__title">College students</h3>
                <p class="about-who__text">The best ₹500 investment you'll make. Meet engineering leads, learn real open-source contribution, and network directly with companies hiring freshers.</p>
                <ul class="about-who__points">
                    <li>15-second intro script</li>
                    <li>Hands-on Contributor Day guide</li>
                    <li>Connect with hiring agencies</li>
                </ul>
                <span class="about-who__cta">What's in it for students &rarr;</span>
            </a>

            <a class="about-who__card" href="#find-your-camp" data-track="home_link_click" data-track-target="find_your_camp">
                <div class="about-who__badge">WordPress Regulars</div>
                <div class="about-who__icon about-who__icon--regular">
                    <x-attendee.line-icon name="users" />
                </div>
                <h3 class="about-who__title">WordCamp regulars</h3>
                <p class="about-who__text">Skip the fluff. Bookmark talks across multiple halls, receive conflict alerts, swap digital Camp Cards, and export your day's contacts into a PDF.</p>
                <ul class="about-who__points">
                    <li>Multi-hall conflict detection</li>
                    <li>Camera QR Camp Card swap</li>
                    <li>One-click PDF data export</li>
                </ul>
                <span class="about-who__cta">Choose your WordCamp &uarr;</span>
            </a>
        </div>
    </section>

    {{-- Section: Co-Branded WPSimplified Banner --}}
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
                Built and maintained by WPSimplified as a free, open-source gift for the global WordPress community. Zero sign-up, zero ads, and 100% offline-ready for every WordCamp worldwide.
            </p>
            <div class="about-wps-banner__actions">
                <a href="{{ route('about') }}" class="btn btn--primary">
                    <span>Read Full Story</span>
                    <span aria-hidden="true">&rarr;</span>
                </a>
            </div>
        </div>
    </section>

    {{-- Section: Quick Questions FAQ --}}
    <section class="about-faq" aria-labelledby="about-faq-heading">
        <div class="section-head section-head--center">
            <span class="u-eyebrow"><x-attendee.line-icon name="help-circle" /> Common Inquiries</span>
            <h2 id="about-faq-heading" class="section-head__title">Quick questions</h2>
            <span class="section-head__desc">Everything you need to know about privacy, offline reliability, and schedule syncing</span>
        </div>
        <div class="guide-accordion">
            @foreach (\\App\\Support\\FirstTimerGuide::quickQuestions() as ['q' => $q, 'a' => $a])
                <details class="guide-accordion__item" data-track-open="faq_open" data-track-question="{{ $q }}">
                    <summary>{{ $q }}</summary>
                    <p>{{ $a }}</p>
                </details>
            @endforeach
        </div>
    </section>
@endif
`;

fs.writeFileSync('resources/views/attendee/partials/about-campbuddy.blade.php', content, 'utf8');
console.log('Restructured about-campbuddy.blade.php');
