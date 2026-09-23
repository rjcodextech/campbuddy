<header class="topbar">
    <span class="brand">
        @if ($event->logoUrl())
            <img src="{{ $event->logoUrl() }}" alt="" class="brand__logo">
        @endif
        <span>{{ $event->display_name }}</span>
    </span>

    <div class="topbar__actions">
        <a href="{{ route('event.camp-card', $event) }}"
           class="topbar__icon-btn @if (request()->routeIs('event.camp-card')) topbar__icon-btn--active @endif"
           aria-label="Camp Card" aria-current="{{ request()->routeIs('event.camp-card') ? 'page' : 'false' }}">
            <img src="/media/camp-card.svg" alt="" aria-hidden="true">
        </a>

        <button type="button" id="install-app-btn" class="topbar__icon-btn" hidden aria-label="Install app">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 3v12" />
                <path d="M7 10l5 5 5-5" />
                <path d="M5 21h14" />
            </svg>
        </button>
    </div>
</header>
