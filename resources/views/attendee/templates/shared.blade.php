{{--
    Client-side templates needed on every attendee-facing page — included
    by layouts/attendee.blade.php (event pages) and welcome.blade.php
    (the WordCamp picker). How <template>s are used, and what data-slot
    means: resources/js/attendee/template.js.
--}}

{{-- toast.js --}}
<template id="tpl-toast">
    <div class="toast" role="status" data-slot="toast"></div>
</template>

{{-- install.js: header "Install app" where the browser can't show its own install
     prompt — every iPhone/iPad browser, Firefox on Android, in-app browsers — and after a
     native prompt was dismissed. install.js keeps only the one data-install-steps list
     that fits the device. --}}
<template id="tpl-install-dialog">
    <dialog>
        <div class="dialog-card">
            <p style="font-weight:700;margin:0 0 8px">Install CampBuddy</p>

            <div data-install-steps="ios">
                <p class="footer-note" style="text-align:left">Add CampBuddy to your home screen for the full app experience:</p>
                <ol class="install-steps">
                    <li>Tap the Share icon — a square with an arrow pointing up (if you don't see it, tap the ••• menu first)</li>
                    <li>Scroll down and choose "Add to Home Screen"</li>
                    <li>Tap "Add", then open CampBuddy from your home screen</li>
                </ol>
            </div>

            <div data-install-steps="menu">
                <p class="footer-note" style="text-align:left">Add CampBuddy to your home screen for the full app experience:</p>
                <ol class="install-steps">
                    <li>Open your browser's menu — the ⋮ button</li>
                    <li>Choose "Install app" or "Add to Home screen"</li>
                    <li>Confirm, then open CampBuddy from your home screen or app list</li>
                </ol>
            </div>

            <div data-install-steps="in-app">
                <p class="footer-note" style="text-align:left">This app can't install CampBuddy from inside itself. Open the page in your phone's browser first:</p>
                <ol class="install-steps">
                    <li>Tap the ⋯ or ⋮ menu (or Share) of the app you're in</li>
                    <li>Choose "Open in Safari", "Open in Chrome" or "Open in browser"</li>
                    <li>Then tap "Install app" again</li>
                </ol>
            </div>

            <button type="button" class="btn btn--primary btn--full" data-action="close">Got it</button>
        </div>
    </dialog>
</template>

{{-- push.js: reminders need the PWA installed first on iOS (N3) --}}
<template id="tpl-reminder-ios-dialog">
    <dialog>
        <div class="dialog-card">
            <p style="font-weight:700;margin:0 0 8px">Get reminders on iPhone/iPad</p>
            <p class="footer-note" style="text-align:left">Add CampBuddy to your home screen first, then reminders can work:</p>
            <ol class="install-steps">
                <li>Tap the Share button in Safari</li>
                <li>Choose "Add to Home Screen"</li>
                <li>Open CampBuddy from your home screen and star the session again</li>
            </ol>
            <button type="button" class="btn btn--primary btn--full" data-action="close">Got it</button>
        </div>
    </dialog>
</template>

{{-- "Meet this person" (meet-sheet.js): a note about someone to meet, with an
    optional time — saved on this device only, and shown in My Day → My schedule. --}}
<template id="tpl-meet-sheet">
    <dialog class="meet-sheet" aria-labelledby="meet-sheet-title">
        <form class="meet-sheet__card" method="dialog">
            <div class="meet-sheet__head">
                <img class="meet-sheet__avatar" alt="" width="48" height="48" data-fallback="/media/illustrations/avatar.svg" data-slot="avatar">
                <div class="meet-sheet__who">
                    <p class="meet-sheet__eyebrow">Add to people to meet</p>
                    <h2 class="meet-sheet__name" id="meet-sheet-title" data-slot="name"></h2>
                    <p class="meet-sheet__sub" data-slot="sub"></p>
                </div>
                <button type="button" class="meet-sheet__close" data-meet-close aria-label="Close">×</button>
            </div>

            <label class="meet-sheet__label" for="meet-note">Your note</label>
            <textarea id="meet-note" maxlength="280" rows="3" placeholder="Why meet them? e.g. Ask about their WooCommerce plugin" data-slot="note"></textarea>

            <fieldset class="meet-sheet__when">
                <legend class="meet-sheet__label">When</legend>
                <label class="meet-sheet__choice"><input type="radio" name="meet-when" value="any" data-slot="when-any"> Any time at the event</label>
                <label class="meet-sheet__choice"><input type="radio" name="meet-when" value="time" data-slot="when-time"> At a set time</label>
                <input type="datetime-local" id="meet-at" aria-label="Meeting time" data-slot="at">
            </fieldset>

            <p class="meet-sheet__privacy">🔒 Saved on this phone only — they aren't told.</p>

            <div class="meet-sheet__calendar" data-slot="calendar">
                <button type="button" class="btn btn--outline btn--compact" data-meet-ics>📅 Add to calendar</button>
                <a class="btn btn--outline btn--compact" target="_blank" rel="noopener" data-meet-google>Google Calendar</a>
            </div>

            <div class="meet-sheet__actions">
                <button type="button" class="btn btn--outline btn--compact meet-sheet__remove" data-meet-remove data-slot="remove">Remove</button>
                <button type="button" class="btn btn--primary" data-meet-save data-slot="save">Save to My schedule</button>
            </div>
        </form>
    </dialog>
</template>

{{-- "Things left today" (plan-reminder.js): shown once per visit on event days. --}}
<template id="tpl-plan-reminder">
    <div class="plan-reminder" role="status">
        <span class="plan-reminder__icon" aria-hidden="true">📋</span>
        <p class="plan-reminder__text"><strong data-slot="count"></strong> <span data-slot="rest"></span></p>
        <a class="btn btn--primary btn--compact" data-slot="link">Check</a>
        <button type="button" class="plan-reminder__close" data-plan-dismiss aria-label="Dismiss">×</button>
    </div>
</template>
