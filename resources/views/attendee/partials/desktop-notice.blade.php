{{--
    "Best on your phone" popup for laptop/desktop visitors. desktop-notice.js
    opens it (as a modal) on a desktop-class device — wide screen + mouse —
    unless it was closed in the last few days; the QR code of this page is
    drawn there too. Closing it keeps the app fully usable here, and the
    topbar's "Open on phone" button (#open-on-phone-btn) brings it back.
    Included at the top of .app-frame by layouts/attendee.blade.php,
    welcome.blade.php and attendee/guide-general.blade.php.
--}}
<dialog id="desktop-notice" class="desktop-notice" aria-labelledby="desktop-notice-title">
    <div class="desktop-notice__art">
        <img src="/media/illustrations/phone-qr.svg" alt="" width="200" height="160">
    </div>

    <div class="desktop-notice__body">
        <p class="u-eyebrow">Best on your phone</p>
        <h2 id="desktop-notice-title" class="desktop-notice__title">CampBuddy is made to walk around WordCamp with you</h2>
        <p class="desktop-notice__text">
            For the best experience — reminders, your Camp Card, finding people in the hallway — open it on your phone.
            Scan this code with your phone's camera:
        </p>

        <div class="desktop-notice__qr-wrap">
            <img class="desktop-notice__qr" alt="QR code that opens this page on your phone" width="132" height="132" hidden>
            <ol class="desktop-notice__steps">
                <li>Open your phone's camera</li>
                <li>Point it at the code</li>
                <li>Tap the link that appears</li>
            </ol>
        </div>

        <button type="button" class="btn btn--primary btn--full" data-action="dismiss">Continue on this computer</button>
    </div>

    <button type="button" class="desktop-notice__close" data-action="dismiss" aria-label="Close">×</button>
</dialog>
