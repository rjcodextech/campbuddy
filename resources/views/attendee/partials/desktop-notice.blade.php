{{--
    "Best on your phone" notice for laptop/desktop visitors. Stays hidden
    until desktop-notice.js decides this is a desktop-class device (wide
    viewport + mouse-driven) and it hasn't been dismissed before; the
    QR code (this page's URL) is drawn there too. Included at the top of
    .app-frame by layouts/attendee.blade.php and welcome.blade.php.
--}}
<aside id="desktop-notice" class="desktop-notice" role="status" hidden>
    <svg class="desktop-notice__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <rect x="7" y="2" width="10" height="20" rx="2" />
        <path d="M11 18h2" />
    </svg>

    <p class="desktop-notice__text">
        <strong>CampBuddy is designed for your phone.</strong>
        For the best experience, open this page on a mobile device — scan the code to continue there.
    </p>

    <img class="desktop-notice__qr" alt="QR code: scan with your phone to open this page" width="88" height="88" hidden>

    <button type="button" class="desktop-notice__close" data-action="dismiss" aria-label="Dismiss this notice">×</button>
</aside>
