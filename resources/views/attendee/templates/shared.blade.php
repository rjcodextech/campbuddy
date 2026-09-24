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
