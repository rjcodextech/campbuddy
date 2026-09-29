{{--
    One deal on Explore → Deals (styles: components/_sponsor.scss, .deal-card).

    Who it's from (logo, name, website) and a highlight tag, then the offer,
    its details and small print, a coupon code with a Copy button, and one
    button that opens it:
      - with a contact form first → deal-leads.js (data-lead-offer-*);
      - inside the app → explore.js / in-app-browser.js (data-inapp-*);
      - in a new tab → a plain link, so a referral or affiliate link keeps
        its credit (a framed site loses its cookies).
--}}
@php
    $name = $offer->displayName();
    $cta = $offer->cta_label ?: 'Get the deal';
@endphp

<article class="deal-card" aria-label="{{ $name }}">
    <div class="deal-card__head">
        <span class="deal-card__logo" aria-hidden="true">
            @if ($offer->mediaAsset)
                <img src="{{ $offer->mediaAsset->url() }}" alt="" loading="lazy">
            @elseif ($offer->icon)
                <span class="deal-card__emoji">{{ $offer->icon }}</span>
            @else
                <x-attendee.line-icon name="tag" />
            @endif
        </span>

        <div class="deal-card__who">
            <span class="deal-card__name">{{ $name }}</span>
            @if ($offer->displayWebsite())
                <span class="deal-card__site">{{ $offer->displayWebsite() }}</span>
            @endif
        </div>

        @if ($offer->highlight)
            <span class="deal-card__tag">{{ $offer->highlight }}</span>
        @endif
    </div>

    @if ($offer->brand)
        <p class="deal-card__title">{{ $offer->title }}</p>
    @endif
    <p class="deal-card__desc">{{ $offer->description }}</p>

    @if ($offer->terms)
        <p class="deal-card__terms">{{ $offer->terms }}</p>
    @endif

    @if ($offer->coupon_code)
        <div class="deal-card__code">
            <span class="deal-card__code-label">Code</span>
            <code class="deal-card__code-value">{{ $offer->coupon_code }}</code>
            <button type="button" class="btn btn--outline btn--compact" data-copy-code="{{ $offer->coupon_code }}">Copy</button>
        </div>
    @endif

    <div class="deal-card__foot">
        @if ($offer->capture_leads)
            <span class="deal-card__note"><x-attendee.line-icon name="clipboard" /> Short form first</span>
            <button type="button" class="btn btn--primary btn--compact deal-card__cta"
                    data-lead-offer-id="{{ $offer->id }}"
                    data-lead-offer-url="{{ $offer->url }}"
                    data-lead-offer-title="{{ $name }}"
                    data-lead-new-tab="{{ $offer->opens_in_app ? '0' : '1' }}"
                    data-lead-form="{{ json_encode($offer->leadForm()) }}">{{ $cta }} →</button>
        @elseif ($offer->opens_in_app)
            <button type="button" class="btn btn--primary btn--compact deal-card__cta"
                    data-inapp-url="{{ $offer->url }}"
                    data-inapp-title="{{ $name }}">{{ $cta }} →</button>
        @else
            <a href="{{ $offer->url }}" target="_blank" rel="noopener sponsored" class="btn btn--primary btn--compact deal-card__cta"
               data-deal-link data-deal-title="{{ $name }}">{{ $cta }} <x-attendee.line-icon name="external" /></a>
        @endif
    </div>
</article>
