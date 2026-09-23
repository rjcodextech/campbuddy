<x-attendee-layout :event="$event">
    @include('attendee.partials.topbar')

    <main id="main-content" tabindex="-1">
        <div class="section-head">
            <h1 class="section-head__title">Quest</h1>
            <span class="section-head__desc" id="quest-progress-summary"></span>
        </div>
        <div class="progress" style="margin-bottom:16px">
            <span class="progress__bar" id="quest-progress-bar" style="width:0%"></span>
        </div>

        <div id="quest-empty" class="card" hidden style="text-align:center">
            <p class="mission-big">Your WordCamp adventure starts here</p>
            <p class="footer-note">Pick your first Quest.</p>
        </div>

        <section id="things-to-do-section" aria-labelledby="things-to-do-heading" hidden>
            <div class="section-head">
                <h2 id="things-to-do-heading" class="section-head__title">Things to do</h2>
            </div>
            <div id="things-to-do-list"></div>
        </section>

        <section id="checklist-section" aria-labelledby="checklist-heading" style="margin-top:20px" hidden>
            <div class="section-head">
                <h2 id="checklist-heading" class="section-head__title">Checklist</h2>
            </div>
            <div id="quest-list" class="checklist"></div>
        </section>
    </main>

    <script type="application/json" id="quest-data">{!! json_encode(
        $quests->map(fn ($q) => ['id' => $q->id, 'title' => $q->title, 'description' => $q->description, 'source' => $q->source]),
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
    ) !!}</script>
</x-attendee-layout>
