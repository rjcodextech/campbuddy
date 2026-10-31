{{-- "Your choices" (notification-choices.js fills and wires it): notifications on this phone — talk
     reminders and the organizers' updates — with "Turn on all" while something is off, and the Google
     Analytics choice (its rows are shown by attendee/partials/analytics.blade.php only where analytics can
     run). Saying no to analytics changes nothing else, and the card says so. Shown next to "Your data". --}}
<div class="card choices-card" data-notification-choices>
    <p style="font-weight:700;margin:0 0 4px">Your choices</p>
    <ul class="choices-card__list">
        <li><span>Talk reminders</span> <strong data-choice="reminders">…</strong></li>
        <li><span>Live updates from the organizers</span> <strong data-choice="updates">…</strong></li>
        <li data-analytics-choice hidden><span>Analytics (website)</span> <strong data-choice="analytics">…</strong></li>
    </ul>
    <p class="footer-note" style="text-align:left;margin:8px 0 0" data-choice-note hidden></p>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
        <button type="button" class="btn btn--primary btn--compact" data-choice-turn-on hidden>Turn on all</button>
        <button type="button" class="btn btn--outline btn--compact" data-analytics-choice hidden>Change analytics choice</button>
    </div>
    <p class="footer-note" style="text-align:left;margin:8px 0 0" data-analytics-choice hidden>Saying no to analytics changes nothing else in CampBuddy.</p>
</div>
