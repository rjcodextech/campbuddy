<header class="topbar">
    {{-- Always CampBuddy's own mark, not the event's — brand recognition
    stays anchored to CampBuddy itself across every event. No event name
    text alongside it: the wordmark already carries the CampBuddy name,
    and the event's own logo/name live prominently on Home's hero
    instead (and in Explore → Event Info). --}}
    <span class="brand">
        <img src="/media/logo.svg" alt="CampBuddy" class="brand__logo brand__logo--wordmark">
    </span>

    <div class="topbar__actions">
        <a href="{{ route('home') }}" class="topbar__text-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3 12a9 9 0 0 1 15-6.7L21 8" />
                <path d="M21 3v5h-5" />
                <path d="M21 12a9 9 0 0 1-15 6.7L3 16" />
                <path d="M3 21v-5h5" />
            </svg>
            <span>WordCamp's</span>
        </a>

        <button type="button" id="install-app-btn" class="topbar__text-btn" hidden>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 3v12" />
                <path d="M7 10l5 5 5-5" />
                <path d="M5 21h14" />
            </svg>
            <span>Install app</span>
        </button>
    </div>
</header>
