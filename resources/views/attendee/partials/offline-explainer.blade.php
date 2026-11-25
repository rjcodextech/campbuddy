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
        <span class="section-head__desc">Open a WordCamp once online and your schedule, guide, and Camp Card keep working without Wi-Fi.</span>
    </div>

    <div class="offline-explainer__grid">
        <div class="card offline-explainer__card offline-explainer__card--yes">
            <h3 class="offline-explainer__title"><x-attendee.line-icon name="download" /> Works offline</h3>
            <ul class="offline-explainer__list">
                <li>Full schedule: rooms, times, and speaker info</li>
                <li>My Day bookmarks and personal notes</li>
                <li>Quest checklist, Contribute tables, and this guide</li>
                <li>Cached Explore tabs (sponsors, deals, attendee list)</li>
                <li>Your Camp Card &amp; QR code</li>
                <li>Pending session reminders (sent once back online)</li>
            </ul>
        </div>

        <div class="card offline-explainer__card offline-explainer__card--no">
            <h3 class="offline-explainer__title"><x-attendee.line-icon name="wifi" /> Needs a connection</h3>
            <ul class="offline-explainer__list">
                <li>Opening a WordCamp for the first time</li>
                <li>Live announcements and delay updates</li>
                <li>Attendee discovery waves and messages</li>
                <li>Sharing your Camp Card, deal forms, or device sync</li>
                <li>Unseen images and external WordPress.tv / website links</li>
            </ul>
        </div>
    </div>

    <p class="offline-explainer__note">Your saved data stays on this device. Back up or clear it any time in My Day &rarr; Your data.</p>
</section>
