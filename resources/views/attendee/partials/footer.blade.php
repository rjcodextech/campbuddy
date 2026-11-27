{{-- The site footer (picker, About, Guide, legal pages): minimal — the logo, the
    social links and the copyright. Privacy / Terms stay as a small line by the
    copyright, with the "Analytics choice" button (shown only where Google
    Analytics can run: it asks the consent question again) and the Android APK
    link (only while one is published). Styles: components/_landing.scss. --}}
<footer class="site-footer">
    <div class="site-footer__inner">
        <div class="site-footer__top">
            <a href="{{ route('home') }}" class="site-footer__logo-link" aria-label="{{ config('campbuddy.name') }} Home">
                <img src="/media/logo-wordmark-sm.png" alt="{{ config('campbuddy.name') }}" width="140" height="42">
            </a>

            @include('attendee.partials.footer-social')
        </div>

        <div class="site-footer__bottom">
            <p class="footer-copy">
                &copy; {{ date('Y') }} {{ config('campbuddy.name') }} &middot; Built by <a href="https://wpsimplified.in" target="_blank" rel="noopener">WPSimplified</a> for the WordPress community.
            </p>

            <nav class="site-footer__legal" aria-label="Legal">
                <a href="{{ route('privacy') }}">Privacy</a>
                <a href="{{ route('terms') }}">Terms</a>
                <button type="button" class="footer-nav-links__choice" data-analytics-choice hidden>Analytics choice</button>
                @include('attendee.partials.apk-link')
            </nav>
        </div>
    </div>
</footer>
