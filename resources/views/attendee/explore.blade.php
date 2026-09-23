@php
    $tierOrder = ['platinum', 'gold', 'silver', 'bronze'];
    $tierClass = function (string $tier) {
        $slug = strtolower($tier);
        return match (true) {
            str_contains($slug, 'plat') => 'sponsor-chip--platinum',
            str_contains($slug, 'gold') => 'sponsor-chip--platinum',
            str_contains($slug, 'silver') => 'sponsor-chip--silver',
            str_contains($slug, 'bronze') => 'sponsor-chip--bronze',
            default => '',
        };
    };
    $sponsorsByTier = collect($sponsors)->groupBy(fn ($s) => $s['tier_names'][0] ?? 'Sponsor');
    $info = $event->info ?? [];
    // Emergency contact is the one Event Info field worth making
    // tappable — a phone number or email is actionable in a way plain
    // venue/wifi/registration text isn't. Everything else in this panel
    // renders as plain info, not a link to nowhere.
    $contactHref = function (?string $v): ?string {
        if (blank($v)) return null;
        if (filter_var($v, FILTER_VALIDATE_EMAIL)) return "mailto:{$v}";
        $digits = preg_replace('/[^\d+]/', '', $v);
        return strlen(preg_replace('/\D/', '', $digits)) >= 7 ? "tel:{$digits}" : null;
    };
@endphp
<x-attendee-layout :event="$event">
    @include('attendee.partials.topbar')

    <main id="main-content" tabindex="-1">
        <div class="section-head">
            <h1 class="section-head__title">Explore</h1>
        </div>

        <div role="tablist" aria-label="Explore section" style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
            <button type="button" class="btn btn--compact" data-explore-tab="people" role="tab" aria-selected="true">People</button>
            <button type="button" class="btn btn--compact btn--ghost" data-explore-tab="sponsors" role="tab" aria-selected="false">Sponsors</button>
            <button type="button" class="btn btn--compact btn--ghost" data-explore-tab="deals" role="tab" aria-selected="false">Deals</button>
            <button type="button" class="btn btn--compact btn--ghost" data-explore-tab="info" role="tab" aria-selected="false">Event Info</button>
        </div>

        <div data-explore-panel="people">
            <div id="people-root"></div>
        </div>

        <div data-explore-panel="sponsors" hidden>
            @forelse ($sponsorsByTier as $tier => $tierSponsors)
                <div class="sponsor-group">
                    <p class="u-eyebrow">{{ $tier }}</p>
                    <div class="sponsor-group__row">
                        @foreach ($tierSponsors as $sponsor)
                            <button type="button" class="sponsor-chip {{ $tierClass($tier) }}"
                                    data-inapp-url="{{ $sponsor['website'] ?? $sponsor['link'] }}"
                                    data-inapp-title="{{ $sponsor['name'] }}">
                                @if ($sponsor['logo_url'])
                                    <img src="{{ $sponsor['logo_url'] }}" alt="{{ $sponsor['name'] }}" class="sponsor-chip__logo">
                                @else
                                    {{ $sponsor['name'] }}
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>
            @empty
                <p class="footer-note" style="text-align:left">No sponsors listed yet.</p>
            @endforelse
        </div>

        <div data-explore-panel="deals" hidden>
            @if ($offers->isEmpty())
                <p class="footer-note" style="text-align:left">No active deals right now — check back later.</p>
            @else
                <div class="offer-grid">
                    @foreach ($offers as $offer)
                        @if ($offer->capture_leads)
                            <button type="button" class="offer-card"
                                    data-lead-offer-id="{{ $offer->id }}"
                                    data-lead-offer-url="{{ $offer->url }}"
                                    data-lead-offer-title="{{ $offer->title }}">
                                @if ($offer->mediaAsset)
                                    <img src="{{ $offer->mediaAsset->url() }}" alt="" style="height:32px;width:auto;max-width:80px;object-fit:contain">
                                @else
                                    <span class="offer-card__icon" aria-hidden="true">{{ $offer->icon }}</span>
                                @endif
                                <span class="offer-card__title">{{ $offer->title }}</span>
                                <span class="offer-card__desc">{{ $offer->description }}</span>
                            </button>
                        @else
                            <button type="button" class="offer-card"
                                    data-inapp-url="{{ $offer->url }}"
                                    data-inapp-title="{{ $offer->title }}">
                                @if ($offer->mediaAsset)
                                    <img src="{{ $offer->mediaAsset->url() }}" alt="" style="height:32px;width:auto;max-width:80px;object-fit:contain">
                                @else
                                    <span class="offer-card__icon" aria-hidden="true">{{ $offer->icon }}</span>
                                @endif
                                <span class="offer-card__title">{{ $offer->title }}</span>
                                <span class="offer-card__desc">{{ $offer->description }}</span>
                            </button>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>

        <div data-explore-panel="info" hidden>
            @if ($event->logoUrl())
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:18px">
                    <img src="{{ $event->logoUrl() }}" alt="" style="height:44px;width:44px;border-radius:12px;object-fit:contain;background:var(--paper);border:1px solid var(--line)">
                    <span style="font-weight:700">{{ $event->display_name }}</span>
                </div>
            @endif
            @if (empty($info))
                <p class="footer-note" style="text-align:left">Event information hasn't been added yet.</p>
            @else
                @if (!empty($info['venue']))
                    <div class="useful-link"><span class="useful-link__icon" aria-hidden="true">📍</span><span><span class="useful-link__title">Venue</span><span class="useful-link__desc">{{ $info['venue'] }}</span></span></div>
                @endif
                @if (!empty($info['wifi']))
                    <div class="useful-link"><span class="useful-link__icon" aria-hidden="true">📶</span><span><span class="useful-link__title">Wifi</span><span class="useful-link__desc">{{ $info['wifi'] }}</span></span></div>
                @endif
                @if (!empty($info['registration_info']))
                    <div class="useful-link"><span class="useful-link__icon" aria-hidden="true">🎫</span><span><span class="useful-link__title">Registration</span><span class="useful-link__desc">{{ $info['registration_info'] }}</span></span></div>
                @endif
                @if (!empty($info['contributor_day_location']))
                    <div class="useful-link"><span class="useful-link__icon" aria-hidden="true">🤝</span><span><span class="useful-link__title">Contributor Day</span><span class="useful-link__desc">{{ $info['contributor_day_location'] }}</span></span></div>
                @endif
                @if (!empty($info['social_event_info']))
                    <div class="useful-link"><span class="useful-link__icon" aria-hidden="true">🎉</span><span><span class="useful-link__title">Social event</span><span class="useful-link__desc">{{ $info['social_event_info'] }}</span></span></div>
                @endif
                @if (!empty($info['emergency_contact']))
                    @php($href = $contactHref($info['emergency_contact']))
                    @if ($href)
                        <a class="useful-link" href="{{ $href }}"><span class="useful-link__icon" aria-hidden="true">🚨</span><span><span class="useful-link__title">Emergency contact</span><span class="useful-link__desc">{{ $info['emergency_contact'] }}</span></span></a>
                    @else
                        <div class="useful-link"><span class="useful-link__icon" aria-hidden="true">🚨</span><span><span class="useful-link__title">Emergency contact</span><span class="useful-link__desc">{{ $info['emergency_contact'] }}</span></span></div>
                    @endif
                @endif
                @if (!empty($info['code_of_conduct_url']))
                    <a class="useful-link" href="{{ $info['code_of_conduct_url'] }}" target="_blank" rel="noopener"><span class="useful-link__icon" aria-hidden="true">📋</span><span><span class="useful-link__title">Code of conduct</span></span></a>
                @endif
                @if (!empty($info['nearby_venue_info']))
                    <div class="useful-link"><span class="useful-link__icon" aria-hidden="true">🗺</span><span><span class="useful-link__title">Nearby</span><span class="useful-link__desc">{{ $info['nearby_venue_info'] }}</span></span></div>
                @endif
                @if (!empty($info['important_links']))
                    @foreach (preg_split('/\r?\n/', trim($info['important_links'])) as $link)
                        @continue(blank($link))
                        <a class="useful-link" href="{{ $link }}" target="_blank" rel="noopener"><span class="useful-link__icon" aria-hidden="true">🔗</span><span><span class="useful-link__title">{{ $link }}</span></span></a>
                    @endforeach
                @endif
            @endif

            <div id="data-controls" style="margin-top:20px"></div>
        </div>
    </main>
</x-attendee-layout>
