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
            </div>
        </section>

        <div id="starting-soon-banner" hidden></div>

        <section aria-labelledby="people-cta-heading">
            <div class="section-head">
                <h2 id="people-cta-heading" class="section-head__title">Meet people</h2>
            </div>
            <div id="people-discovery-home" data-explore-url="{{ route('event.explore', $event) }}"></div>
        </section>

        <section aria-labelledby="happening-now-heading" style="margin-top:20px">
            <div class="section-head">
                <h2 id="happening-now-heading" class="section-head__title">Happening now</h2>
            </div>
            <div id="happening-now" class="card card--now">
                <p class="footer-note" style="margin:0">Loading…</p>
            </div>
        </section>

        <section aria-labelledby="up-next-heading" style="margin-top:20px">
            <div class="section-head">
                <h2 id="up-next-heading" class="section-head__title">Up next</h2>
            </div>
            <div id="up-next" class="card"></div>
        </section>

        <section aria-labelledby="suggested-action-heading" style="margin-top:20px">
            <div class="section-head">
                <h2 id="suggested-action-heading" class="section-head__title">One thing to try</h2>
            </div>
            <div id="suggested-action" class="action-card action-card--wide">
                <span class="action-card__icon" aria-hidden="true">💡</span>
                <div>
                    <p class="action-card__title" id="suggested-action-title"></p>
                    <p class="action-card__desc" id="suggested-action-desc"></p>
                </div>
            </div>
        </section>

        <section aria-labelledby="progress-heading" style="margin-top:20px">
            <div class="section-head">
                <h2 id="progress-heading" class="section-head__title">Your progress</h2>
                <span class="section-head__desc" id="progress-summary"></span>
            </div>
            <div class="progress"><span class="progress__bar" id="progress-bar" style="width:0%"></span></div>
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
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!}</script>
</x-attendee-layout>
