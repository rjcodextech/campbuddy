{{--
    One Free Steal on Explore → Free Steals: a free WordPress plugin or tool
    picked by the CampBuddy team. Same card as a deal (components/_sponsor.scss,
    .deal-card): a category icon, the name and its maker, a Featured tag, a
    "made by someone at this WordCamp" line when the maker is on this event's
    Attendees page (FreeSteal::forEvent), what it does, then the category and
    one button. The link is plain and opens in a new tab: GitHub and
    WordPress.org refuse to be framed.
--}}
{{-- data-steal-*: what explore.js reports as a GA4 item-list item (view / select), with its place in the list. --}}
<article class="deal-card" aria-label="{{ $steal->name }}"
         data-steal-id="{{ $steal->id }}" data-steal-name="{{ $steal->name }}" data-steal-maker="{{ $steal->maker }}" data-steal-category="{{ $steal->category }}" data-position="{{ $position ?? 0 }}">
    <div class="deal-card__head">
        <span class="deal-card__logo" aria-hidden="true"><x-attendee.line-icon :name="$steal->icon()" /></span>

        <div class="deal-card__who">
            <span class="deal-card__name">{{ $steal->name }}</span>
            <span class="deal-card__site">by {{ $steal->maker }}</span>
        </div>

        @if ($steal->is_featured)
            <span class="deal-card__tag">Featured</span>
        @endif
    </div>

    @if ($steal->made_here)
        <p class="free-steal__here"><x-attendee.line-icon name="map-pin" /> Made by someone at this WordCamp</p>
    @endif

    <p class="deal-card__desc">{{ $steal->description }}</p>

    <div class="deal-card__foot">
        <span class="deal-card__note">{{ $steal->category }}</span>
        <a href="{{ $steal->url }}" target="_blank" rel="noopener" class="btn btn--primary btn--compact deal-card__cta"
           data-track="free_steal_open" data-track-offer-title="{{ $steal->name }}" data-track-link-domain="{{ $steal->linkHost() }}">{{ $steal->ctaLabel() }} <x-attendee.line-icon name="external" /></a>
    </div>
</article>
