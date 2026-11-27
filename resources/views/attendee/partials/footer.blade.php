<footer class="site-footer">
    <div class="site-footer__inner">
        <div class="site-footer__grid">
            <div class="site-footer__brand">
                <a href="{{ route('home') }}" class="site-footer__logo-link" aria-label="{{ config('campbuddy.name') }} Home">
                    <img src="/media/logo-wordmark-sm.png" alt="{{ config('campbuddy.name') }}" width="140" height="42">
                </a>
                <p class="site-footer__tagline">
                    Calm, offline-ready WordCamp companion built for crowded hallways, Contributor Day onboarding, and private QR swaps.
                </p>
            </div>

            <div class="site-footer__col">
                <h3 class="site-footer__heading">Explore</h3>
                <ul class="site-footer__list">
                    <li><a href="{{ route('home') }}">All WordCamps</a></li>
                    <li><a href="{{ route('about') }}">About CampBuddy</a></li>
                    <li><a href="{{ route('guide') }}">First-Timer Guide</a></li>
                </ul>
            </div>

            <div class="site-footer__col">
                <h3 class="site-footer__heading">Privacy &amp; App</h3>
                <ul class="site-footer__list">
                    <li><a href="{{ route('privacy') }}">Privacy Policy</a></li>
                    <li><a href="{{ route('terms') }}">Terms of Use</a></li>
                    <li><button type="button" class="footer-nav-links__choice" data-analytics-choice hidden>Analytics choice</button></li>
                    <li class="site-footer__apk-wrap">@include('attendee.partials.apk-link')</li>
                </ul>
            </div>

            <div class="site-footer__col">
                <h3 class="site-footer__heading">Organizers</h3>
                <ul class="site-footer__list">
                    <li><a href="{{ route('manager.login') }}">Event manager sign in</a></li>
                    <li><a href="{{ route('login') }}">Admin sign in</a></li>
                </ul>
                <div class="footer-social-wrap">
                    @include('attendee.partials.footer-social')
                </div>
            </div>
        </div>

        <div class="site-footer__bottom">
            {{-- Mobile & Tablet compact nav fallback --}}
            <nav class="footer-nav-links site-footer__mobile-nav" aria-label="Footer navigation">
                <a href="{{ route('about') }}">About</a>
                <a href="{{ route('guide') }}">Guide</a>
                <a href="{{ route('privacy') }}">Privacy Policy</a>
                <a href="{{ route('terms') }}">Terms of use</a>
                <button type="button" class="footer-nav-links__choice" data-analytics-choice hidden>Analytics choice</button>
                @include('attendee.partials.apk-link')
            </nav>

            <p class="footer-copy">
                &copy; {{ date('Y') }} {{ config('campbuddy.name') }} &middot; Built by <a href="https://wpsimplified.in" target="_blank" rel="noopener">WPSimplified</a> for the WordPress community.
            </p>
        </div>
    </div>
</footer>
