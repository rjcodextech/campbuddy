{{--
    One Free Steal on Explore → Free Steals: a free WordPress plugin or tool
    picked by the CampBuddy team, as one row on a shelf (components/_sponsor.scss,
    .steal-row): its logo (or a category icon without one), the name and its
    maker, a Featured tag, and "Made at this WordCamp" when the maker is on
    this event's Attendees page (FreeSteal::forEvent). Tapping the row opens
    its sheet (explore.js, like the meet sheet): what it does, the category and
    one button. The link is plain and opens in a new tab: GitHub and
    WordPress.org refuse to be framed.
--}}
@php($sheet = 'steal-sheet-'.$steal->id)

{{-- data-steal-*: what explore.js reports as a GA4 item-list item (view / select), with its place in the list. --}}
<article class="steal-row" aria-label="{{ $steal->name }}"
         data-steal-id="{{ $steal->id }}" data-steal-name="{{ $steal->name }}" data-steal-maker="{{ $steal->maker }}" data-steal-category="{{ $steal->category }}" data-position="{{ $position ?? 0 }}">
    <button type="button" class="steal-row__open" aria-haspopup="dialog" aria-controls="{{ $sheet }}" data-steal-open>
        <span class="deal-card__logo" aria-hidden="true">@if ($steal->mediaAsset)<img src="{{ $steal->mediaAsset->url() }}" alt="" loading="lazy">@else<x-attendee.line-icon :name="$steal->icon()" />@endif</span>

        <span class="steal-row__who">
            <span class="steal-row__name">{{ $steal->name }}</span>
            <span class="steal-row__maker">by {{ $steal->maker }}</span>
            @if ($steal->made_here)
                <span class="steal-row__here"><x-attendee.line-icon name="map-pin" /> Made at this WordCamp</span>
            @endif
        </span>

        <span class="steal-row__side">
            @if ($steal->is_featured)
                <span class="deal-card__tag">Featured</span>
            @endif
            <x-attendee.line-icon name="chevron-right" class="steal-row__chev" />
        </span>
    </button>

    <dialog class="steal-sheet" id="{{ $sheet }}" aria-labelledby="{{ $sheet }}-name">
        <div class="steal-sheet__card">
            <span class="steal-sheet__grab" aria-hidden="true"></span>

            <div class="steal-sheet__head">
                <span class="deal-card__logo steal-sheet__logo" aria-hidden="true">@if ($steal->mediaAsset)<img src="{{ $steal->mediaAsset->url() }}" alt="" loading="lazy">@else<x-attendee.line-icon :name="$steal->icon()" />@endif</span>
                <div class="deal-card__who">
                    <span class="deal-card__name" id="{{ $sheet }}-name">{{ $steal->name }}</span>
                    <span class="deal-card__site">by {{ $steal->maker }}</span>
                </div>
                <button type="button" class="topbar__icon-btn" aria-label="Close" data-steal-close><x-attendee.line-icon name="x" /></button>
            </div>

            @if ($steal->is_featured || $steal->made_here)
                <div class="steal-sheet__tags">
                    @if ($steal->is_featured)
                        <span class="deal-card__tag">Featured</span>
                    @endif
                    @if ($steal->made_here)
                        <span class="free-steal__here"><x-attendee.line-icon name="map-pin" /> Made by someone at this WordCamp</span>
                    @endif
                </div>
            @endif

            <p class="deal-card__desc">{{ $steal->description }}</p>
            <p class="steal-sheet__category">{{ $steal->category }}</p>

            <a href="{{ $steal->url }}" target="_blank" rel="noopener" class="btn btn--primary btn--full deal-card__cta"
               data-track="free_steal_open" data-track-offer-title="{{ $steal->name }}" data-track-link-domain="{{ $steal->linkHost() }}">{{ $steal->ctaLabel() }} <x-attendee.line-icon name="external" /></a>
            <p class="steal-sheet__host">Opens {{ $steal->linkHost() }} in a new tab</p>
        </div>
    </dialog>
</article>
