{{--
    Atmospheric Directory Event Card (components/attendee/event-card.blade.php)
    Shows dark obsidian-to-coral banner strip with Country pill, Date pill,
    WordCamp emblem, venue details, session count, and Workspace CTA.
--}}
@props([
    'event' => null,
    'href' => null,
    'title' => null,
    'subtitle' => null,
    'mediaUrl' => null,
    'mediaFallback' => '/media/icons/icon-192.png',
    'mediaShape' => 'avatar',
    'category' => null,
    'date' => null,
    'location' => null,
    'locationLayout' => 'row',
    'tags' => [],
    'isCompleted' => false,
    'country' => null,
    'countryName' => null,
    'sessionsCount' => 0,
    'memoryLinks' => [],
    'priority' => false,
])

@php
    if ($event) {
        $href ??= route('event.home', $event);
        $title ??= $event->display_name;
        $mediaUrl ??= $event->markUrl() ?? '/media/icons/icon-192.png';
        $location ??= $event->info['venue'] ?? null;
    }
@endphp

@if ($isCompleted)
    <div class="event-card-wrap event-card-wrap--completed" data-picker-card data-country="{{ $country ?? '' }}">
        <a href="{{ $href }}" data-track="select_event" @if($event) data-track-event-slug="{{ $event->slug }}" @endif {{ $attributes->class(['event-card', 'event-card--completed', 'event-card--location-row' => $location && $locationLayout === 'row']) }}>
            <div class="event-card__banner event-card__banner--completed">
                <div class="event-card__banner-top">
                    @if ($countryName || $country)
                        <span class="event-card__country-pill">{{ $countryName ?: $country }}</span>
                    @endif
                    @if ($date)
                        <span class="event-card__date-pill">{{ $date }}</span>
                    @endif
                </div>
            </div>

            <div class="event-card__main">
                @if ($mediaUrl)
                    <div class="event-card__media event-card__media--{{ $mediaShape }}">
                        <img src="{{ $mediaUrl }}" alt="" @if($priority) loading="eager" fetchpriority="high" @else loading="lazy" @endif decoding="async"@if ($mediaFallback) data-fallback="{{ $mediaFallback }}" onerror="this.onerror=null; this.src='{{ $mediaFallback }}';"@endif>
                    </div>
                @endif

                <div class="event-card__body">
                    <h3 class="event-card__title">{{ $title }}</h3>
                    <span class="event-card__offline-pill" data-offline-badge hidden>Offline Ready</span>
                    @if ($subtitle)
                        <p class="event-card__subtitle">{{ $subtitle }}</p>
                    @endif
                </div>
            </div>

            @if ($location)
                <p class="event-card__location event-card__location--row"><x-attendee.line-icon name="map-pin" class="event-card__location-icon" /> {{ $location }}</p>
            @endif

            <div class="event-card__footer">
                @if (isset($footer))
                    <div class="event-card__footer-content">{{ $footer }}</div>
                @else
                    <span class="event-card__status"><x-attendee.line-icon name="party" /> Completed</span>
                    <span class="event-card__cta">Open archive <span aria-hidden="true">&rarr;</span></span>
                @endif
            </div>
        </a>

        @if ($memoryLinks !== [])
            <div class="event-card__memories">
                @isset($memoryLinks['photos'])
                    <a href="{{ $memoryLinks['photos'] }}" target="_blank" rel="noopener" data-track="memories_open" data-track-target="photos"><x-attendee.line-icon name="camera" /> Photos</a>
                @endisset
                @isset($memoryLinks['videos'])
                    <a href="{{ $memoryLinks['videos'] }}" target="_blank" rel="noopener" data-track="memories_open" data-track-target="videos"><x-attendee.line-icon name="play" /> Talks on WordPress.tv</a>
                @endisset
            </div>
        @endif

        <button type="button" class="event-card__export" data-export-event="{{ $event?->id }}">
            <x-attendee.line-icon name="download" /> Save your WordCamp Data
        </button>
    </div>
@else
    <a href="{{ $href }}" data-picker-card data-country="{{ $country ?? '' }}" data-track="select_event" @if($event) data-track-event-slug="{{ $event->slug }}" @endif {{ $attributes->class(['event-card', 'event-card--upcoming', 'event-card--location-row' => $location && $locationLayout === 'row']) }}>
        <div class="event-card__banner">
            <div class="event-card__banner-top">
                @if ($countryName || $country)
                    <span class="event-card__country-pill">{{ $countryName ?: $country }}</span>
                @endif
                @if ($date)
                    <span class="event-card__date-pill">{{ $date }}</span>
                @endif
            </div>
        </div>

        <div class="event-card__main">
            @if ($mediaUrl)
                <div class="event-card__media event-card__media--{{ $mediaShape }}">
                    <img src="{{ $mediaUrl }}" alt="" @if($priority) loading="eager" fetchpriority="high" @else loading="lazy" @endif decoding="async"@if ($mediaFallback) data-fallback="{{ $mediaFallback }}" onerror="this.onerror=null; this.src='{{ $mediaFallback }}';"@endif>
                </div>
            @endif

            <div class="event-card__body">
                <h3 class="event-card__title">{{ $title }}</h3>
                <span class="event-card__offline-pill" data-offline-badge hidden>Offline Ready</span>

                @if ($subtitle)
                    <p class="event-card__subtitle">{{ $subtitle }}</p>
                @endif

                @if (count($tags))
                    <div class="event-card__tags">
                        @foreach ($tags as $tag)
                            <span class="event-card__tag">{{ $tag }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        @if ($location)
            <p class="event-card__location event-card__location--row"><x-attendee.line-icon name="map-pin" class="event-card__location-icon" /> {{ $location }}</p>
        @endif

        <div class="event-card__footer">
            @if (isset($footer))
                <div class="event-card__footer-content">{{ $footer }}</div>
            @else
                <span class="event-card__sessions-count">
                    @if ($sessionsCount > 0)
                        {{ $sessionsCount }} Sessions
                    @else
                        Schedule syncing
                    @endif
                </span>
                <span class="event-card__cta">Open Workspace <span aria-hidden="true">&rarr;</span></span>
            @endif
        </div>
    </a>
@endif
