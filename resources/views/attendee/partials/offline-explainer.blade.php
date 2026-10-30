{{--
    What "100% offline" means, in plain words: what keeps working with no connection and what needs one.
    On the guide (/guide#offline, saved for offline use with every event) — the service worker's offline
    page links here. Keep it true: each line matches a test (tests/browser/offline.e2e.mjs, tests/js/*,
    native OfflineRepositoryTest). Styles: components/_guide.scss (.offline-explainer).
--}}
<section id="offline" class="guide-section offline-explainer" data-track-section-view="offline" aria-labelledby="offline-heading">
    <div class="section-head">
        <span class="u-eyebrow"><x-attendee.line-icon name="wifi" /> Offline</span>
        <h2 id="offline-heading" class="guide-section__title">What works offline, and what needs internet</h2>
        <span class="section-head__desc">"100% offline" means this: open your WordCamp once while you are online, and CampBuddy saves every page of it on your phone. After that, what you use during the day keeps working with no connection, on the website and in the Android app.</span>
    </div>

    <div class="offline-explainer__grid">
        <div class="card offline-explainer__card offline-explainer__card--yes">
            <h3 class="offline-explainer__title"><x-attendee.line-icon name="download" /> Works with no connection</h3>
            <ul class="offline-explainer__list">
                <li>The full schedule: every session, its time, room and speakers</li>
                <li>My Day: the sessions you saved, your notes and your plan</li>
                <li>Quest, the Contribute tables and this guide</li>
                <li>Explore: deals, free steals, sponsors, and the attendee list as last saved</li>
                <li>Your Camp Card and its QR code (saving it as an image or a PDF too, once you have opened that screen online)</li>
                <li>Photos and logos you have already seen</li>
                <li>Turning a session reminder on or off: it is kept and sent when you are back online</li>
            </ul>
        </div>

        <div class="card offline-explainer__card offline-explainer__card--no">
            <h3 class="offline-explainer__title"><x-attendee.line-icon name="wifi" /> Needs a connection</h3>
            <ul class="offline-explainer__list">
                <li>Opening a WordCamp for the first time, and seeing new WordCamps in the list</li>
                <li>Live changes: "running late" notices, the organizers' announcements, schedule changes. They arrive when you reconnect</li>
                <li>Attendee discovery: joining, waves and messages, new matches</li>
                <li>Showing your Camp Card on the attendee list, deal forms, feedback, suggesting a Free Steal, moving your data to a new phone</li>
                <li>Reminders and notifications travel over the internet: with no connection they arrive late, when the phone is back online</li>
                <li>Photos you have not seen yet, and links out: the WordCamp website, tickets, maps, WordPress.tv</li>
            </ul>
        </div>
    </div>

    <p class="offline-explainer__note">Your own things (saved sessions, notes, quest ticks, your Camp Card) stay on your phone either way. Clearing the browser's site data or uninstalling the app removes them, so save a copy first from My Day &rarr; Your data.</p>
</section>
