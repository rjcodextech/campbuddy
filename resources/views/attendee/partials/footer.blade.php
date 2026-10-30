<footer class="site-footer">
    <div class="site-footer__inner">
        {{-- Shown by native-bridge.js, in the store app only: event staff sign in to the panel there. --}}
        <p class="footer-note landing-footer" data-app-only hidden>Event staff: <a href="{{ route('manager.login') }}">Event manager sign in</a> &middot; <a href="{{ route('login') }}">Admin sign in</a></p>

        {{-- 1. Community & Social Channels --}}
        <div class="footer-social-wrap">
            @include('attendee.partials.footer-social')
        </div>

        {{-- 2. Minimal Navigation Links (Placed below Community section) --}}
        <nav class="footer-nav-links" aria-label="Footer navigation">
            <a href="{{ route('about') }}">About</a>
            <a href="{{ route('guide') }}">Guide</a>
            <a href="{{ route('privacy') }}">Privacy Policy</a>
            <a href="{{ route('terms') }}">Terms of use</a>
            {{-- Shown by the analytics partial where Google Analytics can run: asks the consent question again. --}}
            <button type="button" class="footer-nav-links__choice" data-analytics-choice hidden>Analytics choice</button>
            @include('attendee.partials.apk-link')
        </nav>

        {{-- 3. Copyright Notice --}}
        <p class="footer-copy">
            &copy; {{ date('Y') }} {{ config('campbuddy.name') }} &middot; Built by <a href="https://wpsimplified.in" target="_blank" rel="noopener">WPSimplified</a> for the WordPress community.
        </p>
    </div>
</footer>
