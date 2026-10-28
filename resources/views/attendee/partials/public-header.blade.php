<header class="topbar topbar--public">
    <div class="topbar__inner">
        <a href="{{ route('home') }}" class="brand" data-track="home_link_click" data-track-target="brand">
            <img src="/media/logo-wordmark-sm.png" alt="CampBuddy home" class="brand__logo brand__logo--wordmark" width="351" height="104">
        </a>

        <nav class="topbar__nav" aria-label="Main Navigation">
            <a href="{{ route('home') }}" 
               class="topbar__text-btn {{ request()->routeIs('home') ? 'topbar__text-btn--active' : '' }}" 
               data-track="home_link_click" 
               data-track-target="wordcamps"
               @if(request()->routeIs('home')) aria-current="page" @endif>
                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="10"/>
                    <polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/>
                </svg>
                <span>WordCamps</span>
            </a>
            <a href="{{ route('about') }}" 
               class="topbar__text-btn {{ request()->routeIs('about') ? 'topbar__text-btn--active' : '' }}" 
               data-track="about_open" 
               data-track-surface="topbar"
               @if(request()->routeIs('about')) aria-current="page" @endif>
                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/>
                    <path d="M5 3v4M3 5h4M19 17v4M17 19h4"/>
                </svg>
                <span>About</span>
            </a>
            <a href="{{ route('guide') }}" 
               class="topbar__text-btn {{ request()->routeIs('guide*') ? 'topbar__text-btn--active' : '' }}" 
               data-track="guide_open" 
               data-track-surface="topbar"
               @if(request()->routeIs('guide*')) aria-current="page" @endif>
                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
                    <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
                </svg>
                <span>Guide</span>
            </a>
        </nav>

        <div class="topbar__actions">
            <button type="button" id="open-on-phone-btn" class="topbar__text-btn topbar__text-btn--ghost" hidden title="Open on phone">
                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="7" y="2" width="10" height="20" rx="2" />
                    <path d="M11 18h2" />
                </svg>
                <span>Open on phone</span>
            </button>

            <button type="button" id="install-app-btn" class="topbar__text-btn topbar__text-btn--install" hidden title="Install CampBuddy PWA">
                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                <span>Install app</span>
            </button>
        </div>
    </div>
</header>
