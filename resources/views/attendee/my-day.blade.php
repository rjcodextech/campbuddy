<x-attendee-layout :event="$event">
    @include('attendee.partials.topbar')

    <main id="main-content" tabindex="-1">
        <div class="section-head">
            <h1 class="section-head__title">My Day</h1>
        </div>

        {{-- MD1: two distinct views, not a blended filtered list. --}}
        <div role="tablist" aria-label="Schedule view" style="display:flex;gap:8px;margin-bottom:14px">
            <button type="button" class="btn btn--compact" data-view-tab="full" role="tab" aria-selected="true">Full Schedule</button>
            <button type="button" class="btn btn--compact btn--outline" data-view-tab="mine" role="tab" aria-selected="false">My Schedule</button>
        </div>

        <div data-view-panel="full">
            <input type="search" id="session-search" class="search-input" placeholder="Search sessions or speakers…">

            <div id="day-filters" class="my-day-filter-row" data-label="Day" role="group" aria-label="Filter by day" hidden></div>
            <div id="track-filters" class="my-day-filter-row" data-label="Track" role="group" aria-label="Filter by track"></div>
            <div id="type-filters" class="my-day-filter-row" data-label="Session type" role="group" aria-label="Filter by session type"></div>

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
