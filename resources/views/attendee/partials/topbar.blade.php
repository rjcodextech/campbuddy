<header class="topbar">
    {{-- Always CampBuddy's own mark, not the event's — brand recognition
    stays anchored to CampBuddy itself across every event. No event name
    text alongside it: the wordmark already carries the CampBuddy name,
    and the event's own logo/name live prominently on Home's hero
    instead (and in Explore → Event Info). --}}
    <a href="{{ route('home') }}" class="brand">
        <img src="/media/logo-wordmark-sm.png" alt="CampBuddy home" class="brand__logo brand__logo--wordmark" width="351" height="104">
    </a>

    <div class="topbar__actions">
        <button type="button" id="open-on-phone-btn" class="topbar__text-btn" hidden>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="7" y="2" width="10" height="20" rx="2" />
                <path d="M11 18h2" />
            </svg>
            <span>Open on phone</span>
        </button>

        {{-- WordCamp 101 — one tap from any screen for anyone who feels lost. --}}
        @php($onGuide = request()->routeIs('event.guide'))
        <a href="{{ route('event.guide', $event) }}" class="topbar__text-btn @if($onGuide) topbar__text-btn--active @endif"
           data-track="guide_open" data-track-surface="topbar" @if($onGuide) aria-current="page" @endif>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="9" />
                <path d="M9.5 9a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.6v.6" />
                <path d="M12 17h.01" />
            </svg>
            <span>Guide</span>
        </a>

        <a href="{{ route('home') }}" class="topbar__text-btn" data-track="switch_event_click" aria-label="Switch WordCamp">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3 12a9 9 0 0 1 15-6.7L21 8" />
                <path d="M21 3v5h-5" />
                <path d="M21 12a9 9 0 0 1-15 6.7L3 16" />
                <path d="M3 21v-5h5" />
            </svg>
            <span>WordCamps</span>
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
