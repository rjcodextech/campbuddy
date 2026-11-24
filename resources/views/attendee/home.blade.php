<x-attendee-layout :event="$event" title="Home">
    @include('attendee.partials.topbar')

    <main id="main-content" tabindex="-1">
        @include('attendee.partials.announcement-banner')
        @include('attendee.partials.delay-banner')

        {{-- Screen 1 Hero Card: Tactile Card with Navy background (#1E293B to #0F172A), 30s live clock, next talk countdown --}}
        @php($heroArt = \App\Support\SiteTheme::hero($event))
        <section @class(['tactile-hero', 'home-hero', 'home-hero--art' => $heroArt, 'home-hero--'.($heroArt['kind'] ?? '') => $heroArt, 'home-hero--mask' => ! empty($heroArt['alpha'])])
                 @if ($heroArt) style="--hero-art: url('{{ $heroArt['url'] }}')" @endif aria-label="Live Event Dashboard">
            <div class="tactile-hero__top">
                <div class="tactile-hero__clock-group">
                    <span class="tactile-hero__time" id="tactile-hero-time">{{ now()->format('H:i') }}</span>
                    <span class="tactile-hero__dates" id="tactile-hero-dates">
                        @if ($event->short_name)
                            <span class="home-hero__eyebrow">{{ $event->short_name }}</span>
                            @if ($event->starts_on) &middot; @endif
                        @endif
                        @if ($event->starts_on)
                            <span class="home-hero__meta">{{ \App\Support\SocialKit::dates($event) }}</span>
                        @endif
                    </span>
                </div>

                <div class="tactile-hero__logo-badge" title="{{ $event->display_name }}">
                    <img class="home-hero__logo" src="{{ $event->logoUrl() ?? $event->faviconUrl() ?? '/media/icons/icon-192.png' }}"
                         alt="{{ $event->display_name }}" width="36" height="36"
                         loading="eager" fetchpriority="high" decoding="async"
                         data-fallback="/media/icons/icon-192.png"
                         onerror="this.onerror=null; this.src='/media/icons/icon-192.png';">
                </div>
            </div>

            <div class="tactile-hero__body">
                <h1 class="tactile-hero__title home-hero__title">{{ $event->display_name }}</h1>

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
                    <p class="home-hero__status" id="event-status" hidden>&nbsp;</p>
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

        {{-- Happening now and Up next schedule guidance cards --}}
        <section aria-labelledby="happening-now-heading" class="home-section">
            <div class="section-head">
                <h2 id="happening-now-heading" class="section-head__title">Happening now</h2>
            </div>
            <div id="happening-now" class="card card--now">
                <p class="footer-note" style="margin:0">Loading…</p>
            </div>
        </section>

        <section aria-labelledby="up-next-heading" class="home-section">
            <div class="section-head">
                <h2 id="up-next-heading" class="section-head__title">Up next</h2>
                <a class="section-head__link" href="{{ route('event.my-day', $event) }}" data-track="home_link_click" data-track-target="full_schedule">Full schedule &rarr;</a>
            </div>
            <div id="up-next" class="card"></div>
        </section>

        {{-- First-timers guide card --}}
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

        {{-- One thing to try (Quests recommendation) --}}
        <section aria-labelledby="suggested-action-heading" class="home-section">
            <div class="section-head">
                <h2 id="suggested-action-heading" class="section-head__title">One thing to try</h2>
                <a class="section-head__link" href="{{ route('event.quest', $event) }}" data-track="home_link_click" data-track-target="all_quests">All quests &rarr;</a>
            </div>
            <div id="suggested-action" class="action-card action-card--wide">
                <span class="action-card__icon" aria-hidden="true"><x-attendee.line-icon name="lightbulb" /></span>
                <div>
                    <p class="action-card__title" id="suggested-action-title"></p>
                    <p class="action-card__desc" id="suggested-action-desc"></p>
                </div>
            </div>
        </section>

        {{-- Screen 1 People & Matches Horizontal Strip --}}
        <section class="people-strip" aria-labelledby="people-strip-heading">
            <div class="people-strip__head">
                <h2 id="people-strip-heading" class="people-strip__title">My People &amp; Matches</h2>
                <a href="{{ route('event.explore', $event) }}#people" class="people-strip__link" data-track="home_link_click" data-track-target="all_peers">All peers &rarr;</a>
            </div>

            <div class="people-strip__row" id="people-strip-row">
                {{-- Find Matches / Wave Circle --}}
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

        {{-- Attendee Discovery --}}
        <section aria-labelledby="people-cta-heading" class="home-section">
            <div class="section-head">
                <h2 id="people-cta-heading" class="section-head__title">Meet people</h2>
            </div>
            <div id="people-discovery-home" data-explore-url="{{ route('event.explore', $event) }}"><div class="card discovery-skeleton" aria-hidden="true"></div></div>
        </section>

        {{-- Screen 1 2x3 Tactile Hubs ActionGrid --}}
        <section class="action-grid" aria-labelledby="action-grid-heading">
            <div class="action-grid__head">
                <h2 id="action-grid-heading" class="action-grid__title">Event Hubs</h2>
            </div>

            <div class="action-grid__cells">
                {{-- Row 1: My Day & Schedule --}}
                <a href="{{ route('event.my-day', $event) }}" class="action-tile" data-track="home_link_click" data-track-target="hub_my_day">
                    <div class="action-tile__top">
                        <div class="action-tile__icon-box">
                            <x-attendee.line-icon name="calendar" />
                        </div>
                        <x-attendee.line-icon name="chevron-right" class="action-tile__chevron" />
                    </div>
                    <div class="action-tile__body">
                        <span class="action-tile__title">My Day</span>
                        <span class="action-tile__subtitle"><span id="hub-saved-count">0</span> saved sessions</span>
                    </div>
                </a>

                <a href="{{ route('event.my-day', $event) }}" class="action-tile" data-track="home_link_click" data-track-target="hub_schedule">
                    <div class="action-tile__top">
                        <div class="action-tile__icon-box action-tile__icon-box--gold">
                            <x-attendee.line-icon name="presentation" />
                        </div>
                        <x-attendee.line-icon name="chevron-right" class="action-tile__chevron" />
                    </div>
                    <div class="action-tile__body">
                        <span class="action-tile__title">Schedule</span>
                        <span class="action-tile__subtitle">{{ count($sessions ?? []) }} talks &amp; tracks</span>
                    </div>
                </a>

                {{-- Row 2: CampCard & Attendees --}}
                <a href="{{ route('event.camp-card', $event) }}" class="action-tile" data-track="home_link_click" data-track-target="hub_camp_card">
                    <div class="action-tile__top">
                        <div class="action-tile__icon-box">
                            <x-attendee.line-icon name="qr-code" />
                        </div>
                        <x-attendee.line-icon name="chevron-right" class="action-tile__chevron" />
                    </div>
                    <div class="action-tile__body">
                        <span class="action-tile__title">My CampCard</span>
                        <span class="action-tile__subtitle">QR badge &amp; networking</span>
                    </div>
                </a>

                <a href="{{ route('event.explore', $event) }}" class="action-tile" data-track="home_link_click" data-track-target="hub_attendees">
                    <div class="action-tile__top">
                        <div class="action-tile__icon-box action-tile__icon-box--gold">
                            <x-attendee.line-icon name="users" />
                        </div>
                        <x-attendee.line-icon name="chevron-right" class="action-tile__chevron" />
                    </div>
                    <div class="action-tile__body">
                        <span class="action-tile__title">Attendees</span>
                        <span class="action-tile__subtitle">Directory &amp; speaker roles</span>
                    </div>
                </a>

                {{-- Row 3: Quests & Sponsors/Swag --}}
                <a href="{{ route('event.quest', $event) }}" class="action-tile" data-track="home_link_click" data-track-target="hub_quests">
                    <div class="action-tile__top">
                        <div class="action-tile__icon-box action-tile__icon-box--gold">
                            <x-attendee.line-icon name="target" />
                        </div>
                        <x-attendee.line-icon name="chevron-right" class="action-tile__chevron" />
                    </div>
                    <div class="action-tile__body">
                        <span class="action-tile__title">Quests</span>
                        <span class="action-tile__subtitle">Contributor day roadmap</span>
                    </div>
                </a>

                <a href="{{ route('event.explore', $event) }}#sponsors" class="action-tile" data-track="home_link_click" data-track-target="hub_sponsors">
                    <div class="action-tile__top">
                        <div class="action-tile__icon-box">
                            <x-attendee.line-icon name="gift" />
                        </div>
                        <x-attendee.line-icon name="chevron-right" class="action-tile__chevron" />
                    </div>
                    <div class="action-tile__body">
                        <span class="action-tile__title">Sponsors &amp; Swag</span>
                        <span class="action-tile__subtitle">Steals, deals &amp; booths</span>
                    </div>
                </a>
            </div>
        </section>

        {{-- Your Progress --}}
        <section aria-labelledby="progress-heading" class="home-section">
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

    {{-- Screen 1 Persistent Bottom Sticky SOS & Help Bar --}}
    <div class="sticky-help-bar" id="sticky-help-bar">
        <button type="button" class="sticky-help-bar__btn" id="sticky-help-btn" aria-label="Need Help / SOS Support" data-track="help_sheet_open">
            <div class="sticky-help-bar__left">
                <x-attendee.line-icon name="life-buoy" />
                <div>
                    <div class="sticky-help-bar__title">Need Help at WordCamp?</div>
                    <div class="sticky-help-bar__subtitle">Helpdesk &middot; Incident Support &middot; Organizers</div>
                </div>
            </div>
            <span class="sticky-help-bar__badge">HELP</span>
        </button>
    </div>

    {{-- Attendee Support & Incident Desk Sheet Modal --}}
    <div class="help-modal" id="help-modal" role="dialog" aria-modal="true" aria-labelledby="help-modal-title" hidden>
        <div class="help-modal__sheet">
            <div class="help-modal__header">
                <h3 class="help-modal__title" id="help-modal-title">Attendee Support &amp; Incident Desk</h3>
                <button type="button" class="help-modal__close" id="help-modal-close" aria-label="Close help sheet">
                    <x-attendee.line-icon name="x" />
                </button>
            </div>

            <div class="help-modal__content">
                {{-- Venue Registration Desk --}}
                <div class="help-modal__card help-modal__card--peach">
                    <h4 class="help-modal__card-title">
                        <x-attendee.line-icon name="life-buoy" />
                        Venue Registration &amp; Info Desk
                    </h4>
                    <p class="help-modal__card-desc">
                        Located near the main entrance lobby. Visit the desk for physical badge assistance, track room directions, or lost-and-found items.
                    </p>
                </div>

                {{-- Code of Conduct & Safety --}}
                <div class="help-modal__card">
                    <h4 class="help-modal__card-title">
                        <x-attendee.line-icon name="shield" />
                        Code of Conduct &amp; Safety
                    </h4>
                    <p class="help-modal__card-desc">
                        WordCamp is dedicated to providing a harassment-free experience for everyone. If you experience or witness an incident, report it immediately to any organizer or volunteer wearing a staff badge.
                    </p>
                    <a href="{{ route('terms') }}" class="btn btn--primary btn--compact" style="margin-top:6px">Read Platform Terms &amp; Safety Code</a>
                </div>

                {{-- DPDP Act 2023 & Data Rights --}}
                <div class="help-modal__card">
                    <h4 class="help-modal__card-title">
                        <x-attendee.line-icon name="shield-check" />
                        DPDP Act 2023 &amp; Data Rights
                    </h4>
                    <p class="help-modal__card-desc">
                        You have full rights to inspect, export, or permanently erase your data stored on this device or in discovery.
                    </p>
                    <a href="{{ route('privacy') }}" class="btn btn--outline btn--compact" style="margin-top:6px">Open Privacy Notice &amp; Data Controls</a>
                </div>
            </div>
        </div>
    </div>

    @include('attendee.templates.home')
    @include('attendee.templates.discovery')

    {{-- Raw JSON configuration read by home.js --}}
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
