<x-attendee-layout :event="$event" title="Home">
    @include('attendee.partials.topbar')

    <main id="main-content" class="tactile-home-main" tabindex="-1">
        @include('attendee.partials.announcement-banner')
        @include('attendee.partials.delay-banner')

        {{-- Screen 1 Hero Card: Tactile Card with Navy background (#1E293B to #0F172A), live clock, next talk countdown --}}
        @php($heroArt = \App\Support\SiteTheme::hero($event))
        <section @class(['tactile-hero', 'home-hero', 'home-hero--art' => $heroArt, 'home-hero--'.($heroArt['kind'] ?? '') => $heroArt, 'home-hero--mask' => ! empty($heroArt['alpha'])])
                 @if ($heroArt) style="--hero-art: url('{{ $heroArt['url'] }}')" @endif aria-label="Live Event Dashboard">
            <div class="tactile-hero__top">
                <div class="tactile-hero__clock-group">
                    <span class="tactile-hero__eyebrow">{{ $event->short_name ?: 'WORDCAMP COMPANION' }}</span>
                    <span class="tactile-hero__time" id="tactile-hero-time">{{ now()->format('H:i') }}</span>
                    @if ($event->starts_on)
                        <span class="tactile-hero__dates" id="tactile-hero-dates">{{ \App\Support\SocialKit::dates($event) }}</span>
                    @endif
                </div>

                <div class="tactile-hero__logo-badge" title="{{ $event->display_name }}">
                    <img class="home-hero__logo" src="{{ $event->logoUrl() ?? $event->faviconUrl() ?? '/media/icons/icon-192.png' }}"
                         alt="{{ $event->display_name }}" width="40" height="40"
                         loading="eager" fetchpriority="high" decoding="async"
                         data-fallback="/media/icons/icon-192.png"
                         onerror="this.onerror=null; this.src='/media/icons/icon-192.png';">
                </div>
            </div>

            <div class="tactile-hero__body">
                <h1 class="tactile-hero__title">{{ $event->display_name }}</h1>

                <div class="tactile-hero__pill-row">
                    <div class="tactile-hero__status-pill" id="tactile-hero-status-pill">
                        <span class="tactile-hero__status-dot" aria-hidden="true"></span>
                        <span id="tactile-hero-status-text">
                            @if ($event->starts_on)
                                {{ \App\Support\SocialKit::dates($event) }}
                            @else
                                Active Event
                            @endif
                        </span>
                    </div>
                    {{-- Legacy status element updated silently by home.js without duplicating text on screen --}}
                    <p class="u-visually-hidden" id="event-status" aria-hidden="true"></p>
                </div>
            </div>

            {{-- Next session / Active talk countdown sub-card --}}
            <div class="tactile-hero__next" id="tactile-hero-next">
                <div class="tactile-hero__next-info">
                    <span class="tactile-hero__next-meta" id="tactile-next-meta">SCHEDULE OVERVIEW</span>
                    <p class="tactile-hero__next-title" id="tactile-next-title">{{ count($sessions ?? []) }} sessions scheduled across all tracks</p>
                    <span class="tactile-hero__next-track" id="tactile-next-track">Main Stage &amp; Track Rooms</span>
                </div>

                <a href="{{ route('event.my-day', $event) }}" class="tactile-hero__view-btn" id="tactile-hero-view-btn" data-track="home_link_click" data-track-target="hero_view">
                    VIEW
                </a>
            </div>
        </section>

        {{-- Starting soon banner --}}
        <div id="starting-soon-banner" hidden></div>

        {{-- "Bring it from your last WordCamp" (bring-data.js) --}}
        <div id="bring-data" class="home-section" hidden></div>

        {{-- Screen 1 People & Matches Horizontal Strip (primary peer discovery trigger matching APK) --}}
        <section class="people-strip" aria-labelledby="people-strip-heading">
            <div class="people-strip__head">
                <h2 id="people-strip-heading" class="people-strip__title">My People &amp; Matches</h2>
                <a href="{{ route('event.explore', $event) }}#people" class="people-strip__link" data-track="home_link_click" data-track-target="all_peers">All peers &rarr;</a>
            </div>

            <div class="people-strip__row" id="people-strip-row">
                <a href="{{ route('event.explore', $event) }}#people" class="people-strip__item" data-track="home_link_click" data-track-target="wave_explore">
                    <div class="people-strip__wave-btn" aria-label="Wave / Find Matches">
                        <x-attendee.line-icon name="plus" />
                    </div>
                    <span class="people-strip__name">Wave</span>
                </a>

                {{-- Dynamic peer avatars loaded by home.js --}}
                <div id="people-strip-dynamic" style="display:contents"></div>
            </div>
        </section>

        {{-- Screen 1 2x3 Quick Actions (sleek horizontal APK tiles with distinct destinations) --}}
        <section class="action-grid" aria-labelledby="action-grid-heading">
            <div class="action-grid__head">
                <h2 id="action-grid-heading" class="action-grid__title">Quick Actions</h2>
            </div>

            <div class="action-grid__cells">
                <a href="{{ route('event.my-day', $event) }}" class="action-tile" data-track="home_link_click" data-track-target="hub_schedule">
                    <div class="action-tile__icon-box">
                        <x-attendee.line-icon name="calendar" />
                    </div>
                    <div class="action-tile__body">
                        <span class="action-tile__title">Schedule</span>
                        <span class="action-tile__subtitle">{{ count($sessions ?? []) }} talks &amp; tracks</span>
                    </div>
                </a>

                <a href="{{ route('event.my-day', $event) }}#mine" class="action-tile" data-track="home_link_click" data-track-target="hub_my_day">
                    <div class="action-tile__icon-box action-tile__icon-box--gold">
                        <x-attendee.line-icon name="clock" />
                    </div>
                    <div class="action-tile__body">
                        <span class="action-tile__title">My Day</span>
                        <span class="action-tile__subtitle"><span id="hub-saved-count">0</span> saved sessions</span>
                    </div>
                </a>

                <a href="{{ route('event.explore', $event) }}#sponsors" class="action-tile" data-track="home_link_click" data-track-target="hub_sponsors">
                    <div class="action-tile__icon-box">
                        <x-attendee.line-icon name="gift" />
                    </div>
                    <div class="action-tile__body">
                        <span class="action-tile__title">Sponsors</span>
                        <span class="action-tile__subtitle">Deals &amp; free steals</span>
                    </div>
                </a>

                <a href="{{ route('event.contribute', $event) }}" class="action-tile" data-track="home_link_click" data-track-target="hub_contribute">
                    <div class="action-tile__icon-box action-tile__icon-box--gold">
                        <x-attendee.line-icon name="award" />
                    </div>
                    <div class="action-tile__body">
                        <span class="action-tile__title">Contributor XP</span>
                        <span class="action-tile__subtitle">Tables &amp; badges</span>
                    </div>
                </a>

                <a href="{{ route('event.explore', $event) }}#info" class="action-tile" data-track="home_link_click" data-track-target="hub_info">
                    <div class="action-tile__icon-box">
                        <x-attendee.line-icon name="map-pin" />
                    </div>
                    <div class="action-tile__body">
                        <span class="action-tile__title">Venue &amp; Info</span>
                        <span class="action-tile__subtitle">{{ $event->short_name ?: 'Wi-Fi & venue maps' }}</span>
                    </div>
                </a>

                <a href="{{ route('event.camp-card', $event) }}" class="action-tile" data-track="home_link_click" data-track-target="hub_camp_card">
                    <div class="action-tile__icon-box action-tile__icon-box--gold">
                        <x-attendee.line-icon name="qr-code" />
                    </div>
                    <div class="action-tile__body">
                        <span class="action-tile__title">CampCard QR</span>
                        <span class="action-tile__subtitle">Share digital badge</span>
                    </div>
                </a>
            </div>
        </section>

        {{-- Legacy home-section hooks kept in canonical order for offline/home JS hydration without visual clutter --}}
        <section aria-labelledby="happening-now-heading" class="home-section u-visually-hidden" aria-hidden="true">
            <h2 id="happening-now-heading">Happening now</h2>
            <div id="happening-now"></div>
        </section>

        <section aria-labelledby="up-next-heading" class="home-section u-visually-hidden" aria-hidden="true">
            <h2 id="up-next-heading">Up next</h2>
            <div id="up-next"></div>
        </section>

        {{-- First-timers guide card (shown smoothly inside tactile home just like APK) --}}
        <div class="start-here" id="start-here">
            <span class="start-here__art" aria-hidden="true"><img src="/media/icons/icon-192.png" alt="" width="64" height="64"></span>
            <div>
                <a class="start-here__link" href="{{ route('event.guide', $event) }}" data-track="guide_open" data-track-surface="home_start_here">
                    <span class="start-here__title">New to WordCamp? Start here</span>
                </a>
                <span class="start-here__desc">How the day runs, the words you'll hear, and how to meet people. A 5-minute read.</span>
                <span class="start-here__cta" aria-hidden="true">Read the guide &rarr;</span>
            </div>
            <button type="button" class="start-here__dismiss" id="start-here-dismiss" aria-label="Hide this guide card" data-track="start_here_dismiss"><x-attendee.line-icon name="x" /></button>
        </div>

        {{-- Hidden structured hydration targets kept in canonical DOM order for onboarding/home JS tests --}}
        <div class="u-visually-hidden" aria-hidden="true">
            <p id="suggested-action-title"></p>
            <p id="suggested-action-desc"></p>
            <div id="suggested-action"></div>
            <div id="people-discovery-home" data-explore-url="{{ route('event.explore', $event) }}"></div>
        </div>

        {{-- Quest & Plan Progress Card --}}
        <section aria-labelledby="progress-heading" class="home-section home-progress-card">
            <div class="section-head">
                <h2 id="progress-heading" class="section-head__title">Your progress</h2>
                <span class="section-head__desc" id="progress-summary"></span>
            </div>
            <div class="progress" role="progressbar" aria-labelledby="progress-heading" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="progress-track">
                <span class="progress__bar" id="progress-bar" style="width:0%"></span>
            </div>
        </section>

        {{-- Post-event photo album and recordings --}}
        @include('attendee.partials.memories-card', ['event' => $event])

        {{-- Screen 1 Scrollable Legal & Compliance Footer --}}
        <footer class="legal-footer" aria-label="Legal and Compliance">
            <div class="legal-footer__links">
                <a href="{{ route('privacy') }}" class="legal-footer__link" data-legal="privacy">Privacy Policy</a>
                <span class="legal-footer__sep">&bull;</span>
                <a href="{{ route('terms') }}" class="legal-footer__link" data-legal="terms">Terms of Use</a>
                <span class="legal-footer__sep">&bull;</span>
                <a href="{{ route('privacy') }}#dpdp" class="legal-footer__link" data-legal="privacy">DPDP Act 2023</a>
            </div>
            <div class="legal-footer__staff">
                <span>Event staff:</span>
                <a href="{{ route('manager.login') }}" class="legal-footer__link">Organizer sign in</a>
                <span class="legal-footer__sep">&bull;</span>
                <a href="{{ route('login') }}" class="legal-footer__link">Admin sign in</a>
            </div>
            <p class="legal-footer__tagline">CampBuddy &middot; Local-first Community Companion</p>
        </footer>
    </main>

    {{-- Screen 1 Docked Safety & Help Desk Bar (matches APK StickyHelpBar cleanly above BottomBar) --}}
    <div class="sticky-help-bar" id="sticky-help-bar">
        <button type="button" class="sticky-help-bar__btn" id="sticky-help-btn" aria-label="Safety &amp; Help Desk" data-track="help_sheet_open">
            <div class="sticky-help-bar__left">
                <x-attendee.line-icon name="shield" />
                <div>
                    <div class="sticky-help-bar__title">Safety &amp; Help Desk</div>
                    <div class="sticky-help-bar__subtitle">Venue support &amp; confidential reporting</div>
                </div>
            </div>
            <span class="sticky-help-bar__badge">Emergency</span>
        </button>
    </div>

    {{-- Attendee Support & Incident Desk Sheet Modal --}}
    <div class="help-modal" id="help-modal" role="dialog" aria-modal="true" aria-labelledby="help-modal-title" hidden>
        <div class="help-modal__sheet">
            <div class="help-modal__header">
                <h3 class="help-modal__title" id="help-modal-title">Support &amp; Safety</h3>
                <button type="button" class="help-modal__close" id="help-modal-close" aria-label="Close help sheet">
                    <x-attendee.line-icon name="x" />
                </button>
            </div>

            <div class="help-modal__content">
                <div class="help-modal__card help-modal__card--danger">
                    <h4 class="help-modal__card-title">
                        <x-attendee.line-icon name="shield" />
                        Need Immediate Help?
                    </h4>
                    <p class="help-modal__card-desc">
                        Connect directly with the venue safety &amp; info desk team anytime during the event.
                    </p>
                    <a href="{{ route('event.explore', $event) }}#info" class="help-modal__cta-white">
                        Venue &amp; Organizer Contact
                    </a>
                </div>

                <div class="help-modal__card help-modal__card--peach">
                    <h4 class="help-modal__card-title">
                        <x-attendee.line-icon name="life-buoy" />
                        Venue Info &amp; Rooms
                    </h4>
                    <p class="help-modal__card-desc">
                        Registration &amp; First Aid: Main Entrance Lobby. Quiet &amp; Accessibility Support: Ask any volunteer badge.
                    </p>
                </div>

                <div class="help-modal__card">
                    <h4 class="help-modal__card-title">
                        <x-attendee.line-icon name="shield-check" />
                        Safety Code &amp; DPDP Act 2023 Rights
                    </h4>
                    <p class="help-modal__card-desc">
                        Read our Harassment-Free Code of Conduct or inspect and clear data saved on this device.
                    </p>
                    <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:4px;">
                        <a href="{{ route('terms') }}" class="btn btn--primary btn--compact">Safety Code</a>
                        <a href="{{ route('privacy') }}" class="btn btn--outline btn--compact">Privacy &amp; Data</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('attendee.templates.home')
    @include('attendee.templates.discovery')

    <script type="application/json" id="home-data">{!! json_encode([
        'sessions' => $sessions,
        'quests' => $quests->map(fn ($q) => ['id' => $q->id, 'title' => $q->title, 'description' => $q->description]),
        'moments' => \App\Support\FirstTimerGuide::moments(),
        'sessionNow' => \App\Support\FirstTimerGuide::sessionNow(),
        'startsOn' => \App\Support\EventTime::firstDay($event),
        'endsOn' => ($event->ends_on ?? $event->starts_on)?->toDateString(),
        'pastEvents' => \App\Support\PastEvents::forHome($event),
        'timezone' => $event->timezone,
        'urls' => [
            'myDay' => route('event.my-day', $event),
            'guide' => route('event.guide', $event),
            'quest' => route('event.quest', $event),
            'contribute' => route('event.contribute', $event),
            'explore' => route('event.explore', $event),
            'campCard' => route('event.camp-card', $event),
        ],
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!}</script>
</x-attendee-layout>
