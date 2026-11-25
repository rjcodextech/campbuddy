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
            @foreach ([['people', 'users', 'People'], ['sponsors', 'heart', 'Sponsors'], ['deals', 'tag', 'Deals'], ['free-steals', 'sparkles', 'Steals'], ['info', 'ticket', 'Info']] as [$tab, $icon, $label])
                <button type="button" @class(['btn btn--compact', 'btn--outline' => ! $loop->first]) data-explore-tab="{{ $tab }}" role="tab" title="{{ $tab === 'free-steals' ? 'Free Steals' : $label }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}"><x-attendee.line-icon :name="$icon" /><span>{{ $label }}</span></button>
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
                <div id="people-roster" class="card" aria-live="polite" aria-busy="true">
                    <div class="discovery-skeleton" aria-hidden="true" style="height:72px;margin:0"></div>
                </div>
            </div>
        </div>

        <div data-explore-panel="sponsors" hidden>
            <p class="panel-intro">Sponsors help keep tickets cheap. Stop by their booths and say hi. You don't have to buy anything, and many have swag, demos or open jobs.</p>
            @forelse ($sponsorsByTier as $tier => $tierSponsors)
                <div class="sponsor-group">
                    <p class="u-eyebrow">{{ $tier }}</p>
                    <div @class(['sponsor-group__row', 'sponsor-group__row--single' => $tierSponsors->count() === 1])>
                        @foreach ($tierSponsors as $sponsor)
                            @php
                                $sponsorUrl = $webUrl($sponsor['website'] ?? $sponsor['link'] ?? null);
                                $sponsorDomain = $sponsorUrl ? preg_replace('/^www\./', '', (string) parse_url($sponsorUrl, PHP_URL_HOST)) : null;
                                $sponsorInitial = mb_strtoupper(mb_substr(trim((string) ($sponsor['name'] ?? 'S')), 0, 1));
                            @endphp
                            @if ($sponsorUrl)
                                <button type="button" class="sponsor-chip {{ $tierClass($tier) }} sponsor-chip--interactive"
                                        data-sponsor-chip
                                        data-inapp-url="{{ $sponsorUrl }}"
                                        data-inapp-title="{{ $sponsor['name'] }}"
                                        data-track="sponsor_open"
                                        data-track-sponsor-name="{{ $sponsor['name'] }}"
                                        data-track-link-domain="{{ parse_url($sponsorUrl, PHP_URL_HOST) }}"
                                        data-track-target="sponsor_chip"
                                        title="{{ $sponsor['name'] }} (opens website)">
                                    @if (! empty($sponsor['logo_url']))
                                        <img src="{{ $sponsor['logo_url'] }}" alt="{{ $sponsor['name'] }}" class="sponsor-chip__logo" loading="lazy" onerror="this.hidden=true;">
                                    @endif
                                    <span class="sponsor-chip__head">
                                        <span class="sponsor-chip__monogram" aria-hidden="true">{{ $sponsorInitial }}</span>
                                        <span class="sponsor-chip__name">{{ $sponsor['name'] }}</span>
                                    </span>
                                    @if ($sponsorDomain)
                                        <span class="sponsor-chip__domain">{{ $sponsorDomain }}</span>
                                    @endif
                                </button>
                            @else
                                <span class="sponsor-chip {{ $tierClass($tier) }} sponsor-chip--interactive"
                                      data-sponsor-chip
                                      data-inapp-title="{{ $sponsor['name'] }}"
                                      data-track="sponsor_open"
                                      data-track-sponsor-name="{{ $sponsor['name'] }}"
                                      data-track-target="sponsor_chip"
                                      title="{{ $sponsor['name'] }}">
                                    @if (! empty($sponsor['logo_url']))
                                        <img src="{{ $sponsor['logo_url'] }}" alt="{{ $sponsor['name'] }}" class="sponsor-chip__logo" loading="lazy" onerror="this.hidden=true;">
                                    @endif
                                    <span class="sponsor-chip__head">
                                        <span class="sponsor-chip__monogram" aria-hidden="true">{{ $sponsorInitial }}</span>
                                        <span class="sponsor-chip__name">{{ $sponsor['name'] }}</span>
                                    </span>
                                </span>
                            @endif
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="empty-state">
                    <p class="empty-state__title">No sponsors listed yet</p>
                    <p class="empty-state__text">Sponsors help keep tickets accessible. When they are announced by the organizers, they'll appear here.</p>
                </div>
            @endforelse
        </div>

        <div data-explore-panel="deals" hidden>
            <p class="panel-intro">Special offers for this WordCamp's attendees, from its sponsors and friends of the community.</p>
            @if ($offers->isEmpty())
                <div class="empty-state">
                    <p class="empty-state__title">No deals right now</p>
                    <p class="empty-state__text">Sponsor deals and discounts will be posted here as the event approaches. Check back during the event.</p>
                </div>
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
                <div class="empty-state">
                    <p class="empty-state__title">Nothing here yet.</p>
                    <p class="empty-state__text">Curated free community tools and plugins will show up here. Check back soon, or suggest your favourite tool below!</p>
                </div>
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
                <button type="button" class="btn btn--outline btn--compact" data-suggest-steal data-track="free_steal_suggest_open" data-track-target="suggest_button">Suggest a Free Steal</button>
            </div>
        </div>

        <div data-explore-panel="info" hidden>
            @include('attendee.partials.event-info')

            <div id="data-controls" style="margin-top:20px">
                @include('attendee.partials.data-controls')
            </div>
            <div id="your-choices" style="margin-top:20px">
                @include('attendee.partials.notification-choices')
            </div>
        </div>
    </main>

    @include('attendee.templates.discovery')
    @include('attendee.templates.people')
    @include('attendee.templates.explore')
    @include('attendee.templates.free-steals')
</x-attendee-layout>
