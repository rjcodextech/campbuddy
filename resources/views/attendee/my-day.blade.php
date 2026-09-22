<x-attendee-layout :event="$event">
    <header class="topbar">
        <span class="brand">
            @if ($event->logoUrl())
                <img src="{{ $event->logoUrl() }}" alt="" class="brand__logo">
            @endif
            <span>{{ $event->display_name }}</span>
        </span>
    </header>

    <main id="main-content" tabindex="-1">
        <div class="section-head">
            <h1 class="section-head__title">My Day</h1>
        </div>

        {{-- MD1: two distinct views, not a blended filtered list. --}}
        <div role="tablist" aria-label="Schedule view" style="display:flex;gap:8px;margin-bottom:14px">
            <button type="button" class="btn btn--compact" data-view-tab="full" role="tab" aria-selected="true">Full Schedule</button>
            <button type="button" class="btn btn--compact btn--ghost" data-view-tab="mine" role="tab" aria-selected="false">My Schedule</button>
        </div>

        <div data-view-panel="full">
            <input type="search" id="session-search" placeholder="Search sessions or speakers…"
                   style="width:100%;padding:10px 12px;border-radius:12px;border:1px solid var(--line);margin-bottom:10px">

            <div id="track-filters" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px"></div>

            <div id="full-schedule-list" class="card"></div>
        </div>

        <div data-view-panel="mine" hidden>
            <div id="my-schedule-list" class="card"></div>
        </div>
    </main>

    <dialog id="session-detail"></dialog>

    <script type="application/json" id="my-day-data">{!! json_encode([
        'sessions' => $sessions,
        'speakers' => $speakers,
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!}</script>
</x-attendee-layout>
