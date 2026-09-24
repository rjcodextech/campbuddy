<x-attendee-layout :event="$event" title="My Day">
    @include('attendee.partials.topbar')

    <main id="main-content" tabindex="-1">
        <div class="section-head">
            <h1 class="section-head__title">My Day</h1>
        </div>

        {{-- MD1: two distinct views, not a blended filtered list. --}}
        <div role="tablist" aria-label="Schedule view" class="tab-strip">
            <button type="button" class="btn btn--compact" data-view-tab="full" role="tab" aria-selected="true">Full Schedule</button>
            <button type="button" class="btn btn--compact btn--outline" data-view-tab="mine" role="tab" aria-selected="false">My Schedule</button>
        </div>

        <div data-view-panel="full">
            <label class="u-visually-hidden" for="session-search">Search sessions or speakers</label>
            <input type="search" id="session-search" class="search-input" placeholder="Search sessions or speakers…">

            <div id="day-filters" class="my-day-filter-row" data-label="Day" role="group" aria-label="Filter by day" hidden></div>
            {{-- Track / Type / Topic stay folded away until wanted — on a phone
            four rows of chips pushed the schedule itself off the first screen. --}}
            <details class="filters-panel" id="more-filters" data-track-open="schedule_filters_open">
                <summary class="filters-panel__toggle">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4" /></svg>
                    More filters
                    <span class="filters-panel__count" id="more-filters-count" hidden></span>
                </summary>
                <div id="track-filters" class="my-day-filter-row" data-label="Track" role="group" aria-label="Filter by track"></div>
                <div id="type-filters" class="my-day-filter-row" data-label="Type" role="group" aria-label="Filter by type"></div>
                <div id="topic-filters" class="my-day-filter-row" data-label="Topic" role="group" aria-label="Filter by topic" hidden></div>
            </details>

            <div id="full-schedule-list"></div>
        </div>

        <div data-view-panel="mine" hidden>
            <div id="my-schedule-list"></div>
        </div>
    </main>

    @include('attendee.templates.my-day')

    <script type="application/json" id="my-day-data">{!! json_encode([
        'sessions' => $sessions,
        'speakers' => $speakers,
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!}</script>
</x-attendee-layout>
