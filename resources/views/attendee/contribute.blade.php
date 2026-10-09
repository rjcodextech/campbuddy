<x-attendee-layout :event="$event" title="Contribute">
    @include('attendee.partials.topbar')

    <main id="main-content" tabindex="-1">
        <section class="u-page-intro">
            <div class="badge">Contribute</div>
            <h1 class="u-page-title">Where could you help?</h1>
            <p class="footer-note" style="text-align:left">
                You don't need to code. Writers, designers, translators and people who are just curious all have a table.
                Answer a few quick questions to find your team.
                <strong>Students:</strong> your contributions show on your WordPress.org profile, which you can put on your CV.
            </p>
        </section>

        {{-- The picker's "Interested in Contributor Day?" answer, shown and changeable
        here (contribute.js; saved back to the onboarding answers on this device). --}}
        <div id="contrib-day" class="card" style="margin-bottom:14px">
            <p class="form-group__title" id="contrib-day-label">Going to Contributor Day?</p>
            <p class="form-group__desc" aria-live="polite" data-contrib-day-note>It's a hands-on day improving WordPress. You don't need to code, and every table welcomes beginners.</p>
            <div class="chip-group" role="group" aria-labelledby="contrib-day-label" style="margin-bottom:0">
                <button type="button" class="chip" aria-pressed="false" data-contrib-day="yes">Yes</button>
                <button type="button" class="chip" aria-pressed="false" data-contrib-day="">Not sure yet</button>
                <button type="button" class="chip" aria-pressed="false" data-contrib-day="no">Not this time</button>
            </div>
        </div>

        <div id="contrib-questions" class="card">
            <p class="form-group__title" id="contrib-tags-label">What kind of work do you enjoy?</p>
            <p class="form-group__desc">Pick as many as you like, or skip straight to the matches.</p>
            <div class="chip-group" id="contrib-tags" role="group" aria-labelledby="contrib-tags-label"></div>
            <button type="button" class="btn btn--primary btn--full" id="contrib-see-teams">See my matches</button>
        </div>

        <div id="contrib-results" hidden>
            <div class="contributor-result">
                <p class="footer-note" style="text-align:left;margin:0 0 8px">Your opening line. Really, this is enough:</p>
                <p class="mission-big u-text-lg" style="margin:0">"Hi, this is my first Contributor Day. Can you help me get started?"</p>
            </div>
            <div id="contrib-team-list" style="margin-top:14px"></div>
            <button type="button" class="btn btn--outline btn--full" id="contrib-retry" style="margin-top:14px">Answer again</button>
        </div>

        {{-- Filled by contribute.js when the organizers entered tables; hidden otherwise. --}}
        <section id="contrib-tables" class="card contrib-tables" aria-labelledby="contrib-tables-title" hidden>
            <h2 class="section-head__title" id="contrib-tables-title">Tables at this WordCamp</h2>
            <p class="footer-note" style="text-align:left;margin:4px 0 10px">Where each team sits on Contributor Day, and who to say hi to.</p>
            <ul class="contrib-tables__list" id="contrib-tables-list"></ul>
        </section>

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

            <div data-slot="where">
                <p style="font-weight:700;margin-top:14px">Find the table</p>
                <p class="footer-note" style="text-align:left" data-slot="where-text"></p>
            </div>

            <div style="margin-top:18px;display:flex;justify-content:flex-end">
                <button type="button" class="btn btn--primary" data-action="close">Got it</button>
            </div>
        </div>
    </dialog>

    @include('attendee.templates.contribute')

    <script type="application/json" id="contribute-data">{!! json_encode([
        'contributorDayQuestId' => $contributorDayQuestId,
        'tables' => $tables ?? [],
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!}</script>
</x-attendee-layout>
