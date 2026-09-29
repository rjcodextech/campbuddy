{{--
    Explore → Info: the event's practical details in three groups (styles:
    components/_sponsor.scss, .info-*). Every row keeps the .useful-link
    markup, and only real web addresses and real phone numbers / emails
    become links ($webUrl, $contactHref from explore.blade.php): Event Info
    can be auto-filled from a third-party site, so a `javascript:` value
    must never reach an href.

      At the venue — venue (+ Open in Maps), wifi (+ Copy), Contributor Day,
                     social event, nearby
      Help         — emergency contact, registration, Code of Conduct
      Links        — important links, a bare address named by its last part
--}}
@php
    $dates = null;
    if ($event->starts_on) {
        $end = $event->ends_on && ! $event->ends_on->isSameDay($event->starts_on) ? $event->ends_on : null;
        $dates = $end
            ? $event->starts_on->format($event->starts_on->isSameMonth($end) ? 'D j' : 'D j M').' – '.$end->format('D j M Y')
            : $event->starts_on->format('D j M Y');
    }

    $venueRows = array_filter([
        'venue' => $info['venue'] ?? null,
        'wifi' => $info['wifi'] ?? null,
        'contributor_day_location' => $info['contributor_day_location'] ?? null,
        'social_event_info' => $info['social_event_info'] ?? null,
        'nearby_venue_info' => $info['nearby_venue_info'] ?? null,
    ], 'filled');
    $cocUrl = $webUrl($info['code_of_conduct_url'] ?? null);
    $hasHelp = filled($info['emergency_contact'] ?? null) || filled($info['registration_info'] ?? null) || $cocUrl;

    // "https://x.wordcamp.org/2026/tickets/" → "Tickets"; the event's own home → "Event website".
    $linkName = function (string $href): string {
        $parts = array_values(array_filter(explode('/', (string) parse_url($href, PHP_URL_PATH)), 'strlen'));
        $last = end($parts);
        if ($last === false || preg_match('/^\d{4}$/', $last)) {
            return 'Event website';
        }

        return \Illuminate\Support\Str::ucfirst(str_replace(['-', '_'], ' ', urldecode($last)));
    };
    $links = collect(preg_split('/\r?\n/', trim((string) ($info['important_links'] ?? ''))))->filter(fn ($l) => filled($l))->values();
@endphp

<div class="info-head">
    @if ($event->logoUrl())
        <img src="{{ $event->logoUrl() }}" alt="" data-fallback="/media/icons/icon-192.png" class="info-head__logo">
    @endif
    <div class="info-head__text">
        <p class="info-head__name">{{ $event->display_name }}</p>
        @if ($dates)
            <p class="info-head__dates">{{ $dates }}</p>
        @endif
    </div>
</div>

@if (empty($info))
    <p class="footer-note" style="text-align:left">Event information hasn't been added yet.</p>
@else
    @if ($venueRows !== [])
        <section class="info-group" aria-labelledby="info-venue-title">
            <h2 class="info-group__title" id="info-venue-title">At the venue</h2>
            <div class="info-group__body">
                @if (filled($info['venue'] ?? null))
                    <div class="useful-link">
                        <span class="useful-link__icon" aria-hidden="true"><x-attendee.line-icon name="map-pin" /></span>
                        <span class="info-row__main">
                            <span class="useful-link__title">Venue</span>
                            <span class="useful-link__desc">{{ $info['venue'] }}</span>
                            <a class="btn btn--outline btn--compact info-row__button" href="https://www.google.com/maps/search/?api=1&amp;query={{ rawurlencode($info['venue']) }}"
                               target="_blank" rel="noopener" data-track="useful_link_click" data-track-link-type="maps">Open in Maps ↗</a>
                        </span>
                    </div>
                @endif
                @if (filled($info['wifi'] ?? null))
                    <div class="useful-link">
                        <span class="useful-link__icon" aria-hidden="true"><x-attendee.line-icon name="wifi" /></span>
                        <span class="info-row__main">
                            <span class="useful-link__title">Wifi</span>
                            <span class="useful-link__desc info-row__mono">{{ $info['wifi'] }}</span>
                        </span>
                        <button type="button" class="btn btn--outline btn--compact info-row__side" data-copy-code="{{ $info['wifi'] }}" aria-label="Copy the wifi details">Copy</button>
                    </div>
                @endif
                @foreach ([['contributor_day_location', 'wrench', 'Contributor Day'], ['social_event_info', 'party', 'Social event'], ['nearby_venue_info', 'map', 'Nearby']] as [$key, $icon, $title])
                    @if (filled($info[$key] ?? null))
                        <div class="useful-link">
                            <span class="useful-link__icon" aria-hidden="true"><x-attendee.line-icon :name="$icon" /></span>
                            <span class="info-row__main"><span class="useful-link__title">{{ $title }}</span><span class="useful-link__desc">{{ $info[$key] }}</span></span>
                        </div>
                    @endif
                @endforeach
            </div>
        </section>
    @endif

    @if ($hasHelp)
        <section class="info-group" aria-labelledby="info-help-title">
            <h2 class="info-group__title" id="info-help-title">Help</h2>
            <div class="info-group__body">
                @if (filled($info['emergency_contact'] ?? null))
                    @php($href = $contactHref($info['emergency_contact']))
                    @if ($href)
                        <a class="useful-link info-row--alert" href="{{ $href }}" data-track="useful_link_click" data-track-link-type="emergency"><span class="useful-link__icon" aria-hidden="true"><x-attendee.line-icon name="siren" /></span><span class="info-row__main"><span class="useful-link__title">Emergency contact</span><span class="useful-link__desc">{{ $info['emergency_contact'] }}</span></span><span class="info-row__chevron" aria-hidden="true">›</span></a>
                    @else
                        <div class="useful-link info-row--alert"><span class="useful-link__icon" aria-hidden="true"><x-attendee.line-icon name="siren" /></span><span class="info-row__main"><span class="useful-link__title">Emergency contact</span><span class="useful-link__desc">{{ $info['emergency_contact'] }}</span></span></div>
                    @endif
                @endif
                @if (filled($info['registration_info'] ?? null))
                    <div class="useful-link"><span class="useful-link__icon" aria-hidden="true"><x-attendee.line-icon name="ticket" /></span><span class="info-row__main"><span class="useful-link__title">Registration</span><span class="useful-link__desc">{{ $info['registration_info'] }}</span></span></div>
                @endif
                @if ($cocUrl)
                    <a class="useful-link" href="{{ $cocUrl }}" target="_blank" rel="noopener" data-track="useful_link_click" data-track-link-type="code_of_conduct"><span class="useful-link__icon" aria-hidden="true"><x-attendee.line-icon name="clipboard" /></span><span class="info-row__main"><span class="useful-link__title">Code of Conduct</span></span><span class="info-row__chevron" aria-hidden="true">↗</span></a>
                @endif
            </div>
        </section>
    @endif

    @if ($links->isNotEmpty())
        <section class="info-group" aria-labelledby="info-links-title">
            <h2 class="info-group__title" id="info-links-title">Links</h2>
            <div class="info-group__body">
                @foreach ($links as $link)
                    {{-- A line may be "Label: https://…" — link the address, show the label. A line with no address is plain text. --}}
                    @php($linkHref = preg_match('#https?://\S+#i', $link, $urlMatch) ? $urlMatch[0] : null)
                    @if ($linkHref)
                        @php($bare = trim($link) === $linkHref)
                        @php($label = $bare ? $linkName($linkHref) : (trim(preg_replace('#\s*:?\s*https?://\S+#i', '', $link)) ?: $linkName($linkHref)))
                        <a class="useful-link" href="{{ $linkHref }}" target="_blank" rel="noopener" data-track="useful_link_click" data-track-link-type="important"><span class="useful-link__icon" aria-hidden="true"><x-attendee.line-icon name="link" /></span><span class="info-row__main"><span class="useful-link__title">{{ $label }}</span><span class="useful-link__desc">{{ preg_replace('#^https?://(www\.)?#i', '', rtrim($linkHref, '/')) }}</span></span><span class="info-row__chevron" aria-hidden="true">↗</span></a>
                    @else
                        <div class="useful-link"><span class="useful-link__icon" aria-hidden="true"><x-attendee.line-icon name="link" /></span><span class="info-row__main"><span class="useful-link__title">{{ trim($link) }}</span></span></div>
                    @endif
                @endforeach
            </div>
        </section>
    @endif
@endif
