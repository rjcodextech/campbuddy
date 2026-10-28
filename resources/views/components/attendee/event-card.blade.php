{{--
    Meetup-inspired event card (components/attendee/event-card.blade.php)
    Encapsulates upcoming and completed event card rendering with clean Blade architecture.
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
    'memoryLinks' => [],
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
            <div class="event-card__main">
                @if ($mediaUrl)
                    <div class="event-card__media event-card__media--{{ $mediaShape }}">
                        <img src="{{ $mediaUrl }}" alt="" loading="lazy"@if ($mediaFallback) data-fallback="{{ $mediaFallback }}"@endif>
                        @if ($category && $mediaShape === 'banner')
                            <span class="event-card__category">{{ $category }}</span>
                        @endif
                    </div>
                @endif

                <div class="event-card__body">
                    @if ($date)
                        <p class="event-card__date">{{ $date }}</p>
                    @endif

                    <h3 class="event-card__title">{{ $title }}</h3>

                    @if ($subtitle)
                        <p class="event-card__subtitle">{{ $subtitle }}</p>
                    @endif

                    @if ($location && $locationLayout !== 'row')
                        <p class="event-card__location"><x-attendee.line-icon name="map-pin" class="event-card__location-icon" /> {{ $location }}</p>
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

            @if ($location && $locationLayout === 'row')
                <p class="event-card__location event-card__location--row"><x-attendee.line-icon name="map-pin" class="event-card__location-icon" /> {{ $location }}</p>
            @endif

            <div class="event-card__footer">
                @if (isset($footer))
                    <div class="event-card__footer-content">{{ $footer }}</div>
                @else
                    <span class="event-card__status"><x-attendee.line-icon name="party" /> Completed</span>
                    <span class="event-card__cta">Open event <span aria-hidden="true">&rarr;</span></span>
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
    <a href="{{ $href }}" data-picker-card data-country="{{ $country ?? '' }}" data-track="select_event" @if($event) data-track-event-slug="{{ $event->slug }}" @endif {{ $attributes->class(['event-card', 'event-card--location-row' => $location && $locationLayout === 'row']) }}>
        <div class="event-card__main">
            @if ($mediaUrl)
                <div class="event-card__media event-card__media--{{ $mediaShape }}">
                    <img src="{{ $mediaUrl }}" alt="" loading="lazy"@if ($mediaFallback) data-fallback="{{ $mediaFallback }}"@endif>
                    @if ($category && $mediaShape === 'banner')
                        <span class="event-card__category">{{ $category }}</span>
                    @endif
                </div>
            @endif

            <div class="event-card__body">
                @if ($date)
                    <p class="event-card__date">{{ $date }}</p>
                @endif

                <h3 class="event-card__title">{{ $title }}</h3>

                @if ($subtitle)
                    <p class="event-card__subtitle">{{ $subtitle }}</p>
                @endif

                @if ($location && $locationLayout !== 'row')
                    <p class="event-card__location"><x-attendee.line-icon name="map-pin" class="event-card__location-icon" /> {{ $location }}</p>
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

        @if ($location && $locationLayout === 'row')
            <p class="event-card__location event-card__location--row"><x-attendee.line-icon name="map-pin" class="event-card__location-icon" /> {{ $location }}</p>
        @endif

        <div class="event-card__footer">
            @if (isset($footer))
                <div class="event-card__footer-content">{{ $footer }}</div>
            @else
                <span class="event-card__cta">Open event <span aria-hidden="true">&rarr;</span></span>
            @endif
        </div>
    </a>
@endif
