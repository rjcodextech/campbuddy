{{--
    One Free Steal on Explore → Free Steals: a free WordPress plugin or tool
    picked by the CampBuddy team. Same card as a deal (components/_sponsor.scss,
    .deal-card): a category icon, the name and its maker, a Featured tag,
    what it does, then the category and one button. The link is plain and
    opens in a new tab: GitHub and WordPress.org refuse to be framed.
--}}
<article class="deal-card" aria-label="{{ $steal->name }}">
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

    <p class="deal-card__desc">{{ $steal->description }}</p>

    <div class="deal-card__foot">
        <span class="deal-card__note">{{ $steal->category }}</span>
        <a href="{{ $steal->url }}" target="_blank" rel="noopener" class="btn btn--primary btn--compact deal-card__cta">{{ $steal->ctaLabel() }} ↗</a>
    </div>
</article>
