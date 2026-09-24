{{--
    Meetup-inspired event card — richer presentation than the plain
    .card (components/_card.scss): media/avatar on the left, metadata
    (date → title → subtitle → location, tags pinned to the bottom) on
    the right, and a full-width footer below for "View event" / a QR
    code / "powered by" branding.

    The whole card is the tap target (an <a>), matching every other
    card-as-link pattern in the attendee app (.action-card,
    the old .landing-event-card).
--}}
@props([
    'href',
    'title',
    'subtitle' => null,
    'mediaUrl' => null,
    'mediaFallback' => null, // shown instead if mediaUrl fails to load (see image-fallback.js)
    'mediaShape' => 'banner', // 'banner' (16:9) or 'avatar' (small circle) — see _event-card.scss
    'category' => null,
    'date' => null,
    'location' => null,
    'tags' => [],
])

<a href="{{ $href }}" {{ $attributes->class(['event-card']) }}>
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

            @if ($location)
                <p class="event-card__location"><span aria-hidden="true">📍</span> {{ $location }}</p>
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

    @if (isset($qrCode) || isset($footer))
        <div class="event-card__footer">
            @isset($qrCode)
                <div class="event-card__qr">{{ $qrCode }}</div>
            @endisset

            @isset($footer)
                <div class="event-card__footer-content">{{ $footer }}</div>
            @endisset
        </div>
    @endif
</a>
