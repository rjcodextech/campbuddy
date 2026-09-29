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
    // Only real web addresses become links. Event Info can be auto-filled from
    // a third-party site, and sponsor data is cached from one — a `javascript:`
    // value must never reach an href or the in-app browser's iframe.
    $webUrl = function (?string $v): ?string {
        $v = trim((string) $v);
        return preg_match('#^https?://\S+$#i', $v) === 1 ? $v : null;
    };
    $contactHref = function (?string $v): ?string {
        if (blank($v)) return null;
        if (filter_var($v, FILTER_VALIDATE_EMAIL)) return "mailto:{$v}";
        $digits = preg_replace('/[^\d+]/', '', $v);
        return strlen(preg_replace('/\D/', '', $digits)) >= 7 ? "tel:{$digits}" : null;
    };
@endphp
<x-attendee-layout :event="$event" title="Explore">
    @include('attendee.partials.topbar')

    <main id="main-content" tabindex="-1">
        <div class="section-head">
            <h1 class="section-head__title">Explore</h1>
        </div>

        <div role="tablist" aria-label="Explore section" class="tab-strip">
            <button type="button" class="btn btn--compact" data-explore-tab="people" role="tab" aria-selected="true">People</button>
            <button type="button" class="btn btn--compact btn--outline" data-explore-tab="sponsors" role="tab" aria-selected="false">Sponsors</button>
            <button type="button" class="btn btn--compact btn--outline" data-explore-tab="deals" role="tab" aria-selected="false">Deals</button>
            <button type="button" class="btn btn--compact btn--outline" data-explore-tab="free-steals" role="tab" aria-selected="false">Free Steals</button>
            <button type="button" class="btn btn--compact btn--outline" data-explore-tab="info" role="tab" aria-selected="false">Event Info</button>
        </div>

        <div data-explore-panel="people">
            {{-- people.js fills #people-discovery and #people-roster --}}
            <div id="people-root">
                <div id="people-discovery"><div class="card discovery-skeleton" aria-hidden="true"></div></div>
                <div class="section-head" style="margin-top:24px">
                    <h2 class="section-head__title">Who's attending</h2>
                    <span class="section-head__desc">From the event's own Attendees page</span>
                </div>
                <input type="search" id="roster-search" class="search-input" placeholder="Search attendees…">
                <div id="people-roster" class="card">Loading…</div>
            </div>
        </div>

        <div data-explore-panel="sponsors" hidden>
            <p class="panel-intro">Sponsors help keep tickets cheap. Stop by their booths and say hi. You don't have to buy anything, and many have swag, demos or open jobs.</p>
            @forelse ($sponsorsByTier as $tier => $tierSponsors)
                <div class="sponsor-group">
                    <p class="u-eyebrow">{{ $tier }}</p>
                    <div class="sponsor-group__row">
                        @foreach ($tierSponsors as $sponsor)
                            {{-- Display only: a sponsor chip opens nothing. Sponsor links live on their deals (Deals tab). --}}
                            <span class="sponsor-chip {{ $tierClass($tier) }}">
                                @if (! empty($sponsor['logo_url']))
                                    <img src="{{ $sponsor['logo_url'] }}" alt="{{ $sponsor['name'] }}" class="sponsor-chip__logo" loading="lazy">
                                @else
                                    {{ $sponsor['name'] }}
                                @endif
                            </span>
                        @endforeach
                    </div>
                </div>
            @empty
                <p class="footer-note" style="text-align:left">No sponsors listed yet.</p>
            @endforelse
        </div>

        <div data-explore-panel="deals" hidden>
            <p class="panel-intro">Special offers for this WordCamp's attendees, from its sponsors and friends of the community.</p>
            @if ($offers->isEmpty())
                <p class="footer-note" style="text-align:left">No deals right now. Check back during the event.</p>
            @else
                <div class="deal-list">
                    @foreach ($offers as $offer)
                        @include('attendee.partials.deal-card', ['offer' => $offer])
                    @endforeach
                </div>
            @endif
        </div>

        <div data-explore-panel="free-steals" hidden>
            <p class="panel-intro">Deals help you save on paid things. Free Steals are good WordPress plugins and tools that are already free, picked by the CampBuddy team, from big companies and from people in the community.</p>
            @if ($steals->isEmpty())
                <p class="footer-note" style="text-align:left">Nothing here yet. Check back soon.</p>
            @else
                <div class="deal-list">
                    @foreach ($steals as $steal)
                        @include('attendee.partials.free-steal-card', ['steal' => $steal])
                    @endforeach
                </div>
            @endif
        </div>

        <div data-explore-panel="info" hidden>
            @if ($event->logoUrl())
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px">
                    <img src="{{ $event->logoUrl() }}" alt="" data-fallback="/media/icons/icon-192.png" style="height:44px;width:44px;border-radius:5px;object-fit:contain;background:var(--paper);border:1px solid var(--line)">
                    <span style="font-weight:700">{{ $event->display_name }}</span>
                </div>
            @endif
            @if (empty($info))
                <p class="footer-note" style="text-align:left">Event information hasn't been added yet.</p>
            @else
                @if (!empty($info['venue']))
                    <div class="useful-link"><span class="useful-link__icon" aria-hidden="true"><x-attendee.line-icon name="map-pin" /></span><span><span class="useful-link__title">Venue</span><span class="useful-link__desc">{{ $info['venue'] }}</span></span></div>
                @endif
                @if (!empty($info['wifi']))
                    <div class="useful-link"><span class="useful-link__icon" aria-hidden="true"><x-attendee.line-icon name="wifi" /></span><span><span class="useful-link__title">Wifi</span><span class="useful-link__desc">{{ $info['wifi'] }}</span></span></div>
                @endif
                @if (!empty($info['registration_info']))
                    <div class="useful-link"><span class="useful-link__icon" aria-hidden="true"><x-attendee.line-icon name="ticket" /></span><span><span class="useful-link__title">Registration</span><span class="useful-link__desc">{{ $info['registration_info'] }}</span></span></div>
                @endif
                @if (!empty($info['contributor_day_location']))
                    <div class="useful-link"><span class="useful-link__icon" aria-hidden="true"><x-attendee.line-icon name="wrench" /></span><span><span class="useful-link__title">Contributor Day</span><span class="useful-link__desc">{{ $info['contributor_day_location'] }}</span></span></div>
                @endif
                @if (!empty($info['social_event_info']))
                    <div class="useful-link"><span class="useful-link__icon" aria-hidden="true"><x-attendee.line-icon name="party" /></span><span><span class="useful-link__title">Social event</span><span class="useful-link__desc">{{ $info['social_event_info'] }}</span></span></div>
                @endif
                @if (!empty($info['emergency_contact']))
                    @php($href = $contactHref($info['emergency_contact']))
                    @if ($href)
                        <a class="useful-link" href="{{ $href }}" data-track="useful_link_click" data-track-link-type="emergency"><span class="useful-link__icon" aria-hidden="true"><x-attendee.line-icon name="siren" /></span><span><span class="useful-link__title">Emergency contact</span><span class="useful-link__desc">{{ $info['emergency_contact'] }}</span></span></a>
                    @else
                        <div class="useful-link"><span class="useful-link__icon" aria-hidden="true"><x-attendee.line-icon name="siren" /></span><span><span class="useful-link__title">Emergency contact</span><span class="useful-link__desc">{{ $info['emergency_contact'] }}</span></span></div>
                    @endif
                @endif
                @if ($webUrl($info['code_of_conduct_url'] ?? null))
                    <a class="useful-link" href="{{ $webUrl($info['code_of_conduct_url']) }}" target="_blank" rel="noopener" data-track="useful_link_click" data-track-link-type="code_of_conduct"><span class="useful-link__icon" aria-hidden="true"><x-attendee.line-icon name="clipboard" /></span><span><span class="useful-link__title">Code of Conduct</span></span></a>
                @endif
                @if (!empty($info['nearby_venue_info']))
                    <div class="useful-link"><span class="useful-link__icon" aria-hidden="true"><x-attendee.line-icon name="map" /></span><span><span class="useful-link__title">Nearby</span><span class="useful-link__desc">{{ $info['nearby_venue_info'] }}</span></span></div>
                @endif
                @if (!empty($info['important_links']))
                    @foreach (preg_split('/\r?\n/', trim($info['important_links'])) as $link)
                        @continue(blank($link))
                        {{-- A line may be "Label: https://…" — link the address, show the whole line. --}}
                        @php($linkHref = preg_match('#https?://\S+#i', $link, $urlMatch) ? $urlMatch[0] : null)
                        {{-- A bare address reads better without "https://" and the trailing slash. --}}
                        @php($linkLabel = $linkHref && trim($link) === $linkHref ? preg_replace('#^https?://(www\.)?#i', '', rtrim($linkHref, '/')) : trim($link))
                        @if ($linkHref)
                            <a class="useful-link" href="{{ $linkHref }}" target="_blank" rel="noopener" data-track="useful_link_click" data-track-link-type="important"><span class="useful-link__icon" aria-hidden="true"><x-attendee.line-icon name="link" /></span><span><span class="useful-link__title">{{ $linkLabel }}</span></span></a>
                        @else
                            <div class="useful-link"><span class="useful-link__icon" aria-hidden="true"><x-attendee.line-icon name="link" /></span><span><span class="useful-link__title">{{ trim($link) }}</span></span></div>
                        @endif
                    @endforeach
                @endif
            @endif

            <div id="data-controls" style="margin-top:20px">
                @include('attendee.partials.data-controls')
            </div>
        </div>
    </main>

    @include('attendee.templates.discovery')
    @include('attendee.templates.people')
    @include('attendee.templates.explore')
</x-attendee-layout>
