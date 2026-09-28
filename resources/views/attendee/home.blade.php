<x-attendee-layout :event="$event" title="Home">
    @include('attendee.partials.topbar')

    <main id="main-content" tabindex="-1">
        {{-- Event branding lives here now, not the global topbar (§Header) —
        logo falls back to CampBuddy's own icon so this never looks broken
        for an event that hasn't uploaded one. --}}
        <section class="home-hero" aria-label="Event">
            <img class="home-hero__logo" src="{{ $event->logoUrl() ?? $event->faviconUrl() ?? '/media/icons/icon-192.png' }}" alt="" width="52" height="52" data-fallback="/media/icons/icon-192.png">
            <div class="home-hero__body">
                <p class="home-hero__eyebrow">{{ $event->short_name ?? 'WordCamp' }}</p>
                <h1 class="home-hero__title">{{ $event->display_name }}</h1>
                @if ($event->starts_on)
                    <p class="home-hero__meta">
                        {{ $event->starts_on->format('j M Y') }}
                        @if ($event->ends_on && ! $event->ends_on->isSameDay($event->starts_on))
                            – {{ $event->ends_on->format('j M Y') }}
                        @endif
                    </p>
                @endif
                {{-- "Starts in 3 days" / "Day 1 of 2" / "That's a wrap" — home.js,
                from the device's own clock and date. --}}
                <p class="home-hero__status" id="event-status" hidden></p>
            </div>
        </section>

        <div id="starting-soon-banner" hidden></div>

        {{-- Shown by default — first-timers are who CampBuddy is for — and
        hidden by home.js for someone who told onboarding it isn't their first
        WordCamp, or who dismissed it. --}}
        <div class="start-here" id="start-here">
            <span class="start-here__art" aria-hidden="true"><img src="/media/icons/icon-192.png" alt="" width="64" height="64"></span>
            <div>
                <a class="start-here__link" href="{{ route('event.guide', $event) }}" data-track="guide_open" data-track-surface="home_start_here">
                    <span class="start-here__title">New to WordCamp? Start here</span>
                </a>
                <span class="start-here__desc">The day, the words people use, and how to meet people — in 5 minutes.</span>
                <span class="start-here__cta" aria-hidden="true">Read the guide →</span>
            </div>
            <button type="button" class="start-here__dismiss" id="start-here-dismiss" aria-label="Hide this guide card" data-track="start_here_dismiss">×</button>
        </div>

        <section aria-labelledby="happening-now-heading">
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
                <a class="section-head__link" href="{{ route('event.my-day', $event) }}" data-track="home_link_click" data-track-target="full_schedule">Full schedule →</a>
            </div>
            <div id="up-next" class="card"></div>
        </section>

        <section aria-labelledby="suggested-action-heading" class="home-section">
            <div class="section-head">
                <h2 id="suggested-action-heading" class="section-head__title">One thing to try</h2>
                <a class="section-head__link" href="{{ route('event.quest', $event) }}" data-track="home_link_click" data-track-target="all_quests">All quests →</a>
            </div>
            <div id="suggested-action" class="action-card action-card--wide">
                <span class="action-card__icon" aria-hidden="true">💡</span>
                <div>
                    <p class="action-card__title" id="suggested-action-title"></p>
                    <p class="action-card__desc" id="suggested-action-desc"></p>
                </div>
            </div>
        </section>

        <section aria-labelledby="people-cta-heading" class="home-section">
            <div class="section-head">
                <h2 id="people-cta-heading" class="section-head__title">Meet people</h2>
            </div>
            <div id="people-discovery-home" data-explore-url="{{ route('event.explore', $event) }}"><div class="card discovery-skeleton" aria-hidden="true"></div></div>
        </section>

        <section aria-labelledby="progress-heading" class="home-section">
            <div class="section-head">
                <h2 id="progress-heading" class="section-head__title">Your progress</h2>
                <span class="section-head__desc" id="progress-summary"></span>
            </div>
            <div class="progress" role="progressbar" aria-labelledby="progress-heading" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="progress-track">
                <span class="progress__bar" id="progress-bar" style="width:0%"></span>
            </div>
        </section>
    </main>

    @include('attendee.templates.home')
    @include('attendee.templates.discovery')

    {{-- Raw JSON (HEX-escaped so it's safe inside a <script> tag), read
    back with JSON.parse(textContent) client-side — not Js::from(), which
    outputs a JSON.parse(...) JS *expression*, not parseable JSON itself. --}}
    <script type="application/json" id="home-data">{!! json_encode([
        'sessions' => $sessions,
        'quests' => $quests->map(fn ($q) => ['id' => $q->id, 'title' => $q->title, 'description' => $q->description]),
        'moments' => \App\Support\FirstTimerGuide::moments(),
        'sessionNow' => \App\Support\FirstTimerGuide::sessionNow(),
        'startsOn' => $event->starts_on?->toDateString(),
        'endsOn' => ($event->ends_on ?? $event->starts_on)?->toDateString(),
        'urls' => [
            'myDay' => route('event.my-day', $event),
            'guide' => route('event.guide', $event),
            'quest' => route('event.quest', $event),
            'contribute' => route('event.contribute', $event),
        ],
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!}</script>
</x-attendee-layout>
