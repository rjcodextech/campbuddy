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
            <p class="form-group__title" id="contrib-tags-label">What kind of work do you enjoy?</p>
            <p class="form-group__desc">Pick as many as you like, or skip straight to the matches.</p>
            <div class="chip-group" id="contrib-tags" role="group" aria-labelledby="contrib-tags-label"></div>
            <button type="button" class="btn btn--primary btn--full" id="contrib-see-teams">See my matches</button>
        </div>

        <div id="contrib-results" hidden>
            <div class="contributor-result">
                <p class="footer-note" style="text-align:left;margin:0 0 8px">Your opening line — seriously, this is enough:</p>
                <p class="mission-big u-text-lg" style="margin:0">"Hi, this is my first Contributor Day. Can you help me get started?"</p>
            </div>
            <div id="contrib-team-list" style="margin-top:14px"></div>
            <button type="button" class="btn btn--outline btn--full" id="contrib-retry" style="margin-top:14px">Answer again</button>
        </div>

        <div id="contrib-all-teams" style="margin-top:24px">
            <p class="section-head__title" style="margin-bottom:10px">All contributor teams</p>
            <div id="contrib-all-list"></div>
        </div>
    </main>

    {{-- Filled in (slots only) each time a team is opened — contribute.js --}}
    <dialog id="contrib-team-detail">
        <div class="dialog-card">
            <p class="badge">
                <span data-slot="badge-technical">Technical background helps</span>
                <span data-slot="badge-plain">No technical background needed</span>
            </p>
            <h2 style="margin:10px 0 4px"><span data-slot="emoji"></span> <span data-slot="name"></span></h2>
            <p data-slot="description"></p>

            <p style="font-weight:700;margin-top:14px">Who it suits</p>
            <p class="footer-note" style="text-align:left" data-slot="who-it-suits"></p>

            <p style="font-weight:700;margin-top:14px">A beginner-friendly task</p>
            <p class="footer-note" style="text-align:left" data-slot="beginner-task"></p>

            <p style="font-weight:700;margin-top:14px">At their table</p>
            <p class="footer-note" style="text-align:left" data-slot="at-the-table"></p>

            <div style="margin-top:18px;display:flex;justify-content:flex-end">
                <button type="button" class="btn btn--primary" data-action="close">Got it</button>
            </div>
        </div>
    </dialog>

    @include('attendee.templates.contribute')

    <script type="application/json" id="contribute-data">{!! json_encode([
        'contributorDayQuestId' => $contributorDayQuestId,
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!}</script>
</x-attendee-layout>
