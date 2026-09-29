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

        {{-- Five equal cells, an icon over a short label, so all five fit a phone (.tab-strip--icons). --}}
        <div role="tablist" aria-label="Explore section" class="tab-strip tab-strip--icons">
            @foreach ([['people', 'users', 'People'], ['sponsors', 'heart', 'Sponsors'], ['deals', 'tag', 'Deals'], ['free-steals', 'sparkles', 'Free Steals'], ['info', 'ticket', 'Info']] as [$tab, $icon, $label])
                <button type="button" @class(['btn btn--compact', 'btn--outline' => ! $loop->first]) data-explore-tab="{{ $tab }}" role="tab" aria-selected="{{ $loop->first ? 'true' : 'false' }}"><x-attendee.line-icon :name="$icon" /><span>{{ $label }}</span></button>
            @endforeach
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
                        @include('attendee.partials.deal-card', ['offer' => $offer, 'position' => $loop->iteration])
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
                        @include('attendee.partials.free-steal-card', ['steal' => $steal, 'position' => $loop->iteration])
                    @endforeach
                </div>
            @endif

            <div class="free-steal-suggest">
                <p class="free-steal-suggest__title">Built a free tool, or know a good one?</p>
                <p class="free-steal-suggest__text">Tell us. The team looks at every suggestion before anything is added.</p>
                <button type="button" class="btn btn--outline btn--compact" data-suggest-steal>Suggest a Free Steal</button>
            </div>
        </div>

        <div data-explore-panel="info" hidden>
            @include('attendee.partials.event-info')

            <div id="data-controls" style="margin-top:20px">
                @include('attendee.partials.data-controls')
            </div>
        </div>
    </main>

    @include('attendee.templates.discovery')
    @include('attendee.templates.people')
    @include('attendee.templates.explore')
    @include('attendee.templates.free-steals')
</x-attendee-layout>
