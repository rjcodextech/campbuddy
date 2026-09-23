{{--
    Client-side templates needed on every attendee-facing page — included
    by layouts/attendee.blade.php (event pages) and welcome.blade.php
    (the WordCamp picker). How <template>s are used, and what data-slot
    means: resources/js/attendee/template.js.
--}}

{{-- toast.js --}}
<template id="tpl-toast">
    <div class="toast" data-slot="toast"></div>
</template>

{{-- install.js: header "Install app" on iOS, where beforeinstallprompt never fires --}}
<template id="tpl-install-ios-dialog">
    <dialog>
        <div class="dialog-card">
            <p style="font-weight:700;margin:0 0 8px">Install CampBuddy</p>
            <p class="footer-note" style="text-align:left">Add CampBuddy to your home screen for the full app experience:</p>
            <ol class="ios-install-steps">
                <li>Tap the Share button in Safari</li>
                <li>Choose "Add to Home Screen"</li>
                <li>Open CampBuddy from your home screen</li>
            </ol>
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
            <ol class="ios-install-steps">
                <li>Tap the Share button in Safari</li>
                <li>Choose "Add to Home Screen"</li>
                <li>Open CampBuddy from your home screen and bookmark again</li>
            </ol>
            <button type="button" class="btn btn--primary btn--full" data-action="close">Got it</button>
        </div>
    </dialog>
</template>
