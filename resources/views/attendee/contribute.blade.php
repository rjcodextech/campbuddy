<x-attendee-layout :event="$event">
    @include('attendee.partials.topbar')

    <main id="main-content" tabindex="-1">
        <section class="u-page-intro">
            <div class="badge">Contribute</div>
            <h1 style="margin:6px 0">Where could you help?</h1>
            <p class="footer-note" style="text-align:left">
                Contributor Day is for developers, designers, writers, translators, testers, organizers and curious
                newcomers — not only coders. A few quick questions (all skippable) point you to a team.
            </p>
        </section>

        <div id="contrib-questions" class="card">
            <p style="font-weight:700;margin:0 0 10px">What kind of work do you enjoy?</p>
            <div class="chip-group" id="contrib-tags"></div>
            <button type="button" class="btn btn--primary btn--full" id="contrib-see-teams">See my matches</button>
        </div>

        <div id="contrib-results" hidden>
            <div class="contributor-result">
                <p class="footer-note" style="text-align:left;margin:0 0 8px">Your opening line — seriously, this is enough:</p>
                <p class="mission-big" style="font-size:18px;margin:0">"Hi, this is my first Contributor Day. Can you help me get started?"</p>
            </div>
            <div id="contrib-team-list" style="margin-top:14px"></div>
            <button type="button" class="btn btn--ghost btn--full" id="contrib-retry" style="margin-top:14px">Answer again</button>
        </div>

        <div id="contrib-all-teams" style="margin-top:24px">
            <p class="section-head__title" style="margin-bottom:10px">All contributor teams</p>
            <div id="contrib-all-list"></div>
        </div>
    </main>

    <dialog id="contrib-team-detail"></dialog>

    <script type="application/json" id="contribute-data">{!! json_encode([
        'contributorDayQuestId' => $contributorDayQuestId,
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!}</script>
</x-attendee-layout>
