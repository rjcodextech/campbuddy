{{--
    Memories (App\Support\EventMemories): the photo album and the talks on
    WordPress.tv, once an organizer has added them. On Home and on the
    Memories page; renders nothing while neither link is set.

      @include('attendee.partials.memories-card', ['event' => $event, 'headingLevel' => 2])
--}}
@php
    $memoryLinks = \App\Support\EventMemories::links($event);
    $memoryCopy = [
        'photos' => ['camera', 'Photo album', 'The official photos. Find yourself in them.'],
        'videos' => ['play', 'Speaker sessions on WordPress.tv', 'Missed a talk, or want to watch one again? The recordings are here.'],
    ];
@endphp
@if ($memoryLinks !== [])
    <section class="memories home-section" aria-labelledby="memories-heading">
        <div class="section-head">
            <h2 id="memories-heading" class="section-head__title">Memories</h2>
        </div>
        <div class="memories__list">
            @foreach ($memoryLinks as $kind => $url)
                @php([$icon, $title, $desc] = $memoryCopy[$kind])
                <a class="action-card action-card--wide memories__link" href="{{ $url }}" target="_blank" rel="noopener" id="memories-{{ $kind }}" data-track="memories_open" data-track-target="{{ $kind }}">
                    <span class="action-card__icon" aria-hidden="true"><x-attendee.line-icon :name="$icon" /></span>
                    <span class="memories__text">
                        <span class="action-card__title">{{ $title }}</span>
                        <span class="action-card__desc">{{ $desc }}</span>
                    </span>
                    <span class="memories__out" aria-hidden="true"><x-attendee.line-icon name="external" /></span>
                </a>
            @endforeach
        </div>
    </section>
@endif
