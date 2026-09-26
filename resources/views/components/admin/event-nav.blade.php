{{--
    The header every page of one event shares: who the event is (logo, name,
    status, dates, where it lives) and the tab strip to hop between its
    sections, with a count on each tab that holds a list.

      <x-admin.event-nav :event="$event" current="quests" />

    current: details | quests | offers | roster | leads
--}}
@props(['event', 'current'])

@php
    // The three lists an event owns in one query, its deal leads in another.
    $event->loadCount(['quests', 'offers', 'attendeeRoster']);
    $leadCount = \App\Models\OfferLead::where('event_id', $event->id)->count();

    $tabs = [
        'details' => ['Details', route('admin.events.edit', $event), null],
        'quests' => ['Quests & checklist', route('admin.events.quests.index', $event), $event->quests_count],
        'offers' => ['Deals', route('admin.events.offers.index', $event), $event->offers_count],
        'roster' => ['Roster', route('admin.events.roster.index', $event), $event->attendee_roster_count],
        'leads' => ['Deal leads', route('admin.events.deal-leads.index', $event), $leadCount],
    ];

    $timing = \App\Support\EventListing::timing($event);
    $live = $event->status === 'active' && $event->is_visible;
@endphp

<div {{ $attributes->class('mb-6 overflow-hidden rounded-xl border border-line bg-white shadow-sm') }}>
    <div class="flex flex-wrap items-center gap-x-4 gap-y-3 px-4 py-4 sm:px-5">
        <span class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-line bg-paper-soft text-base font-semibold text-maroon" aria-hidden="true">
            @if ($event->markUrl())
                <img src="{{ $event->markUrl() }}" alt="" class="max-h-10 max-w-10 object-contain">
            @else
                {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($event->display_name, 0, 1)) }}
            @endif
        </span>

        <div class="min-w-0 flex-1 basis-56">
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                <a href="{{ route('admin.events.edit', $event) }}" class="text-base font-semibold hover:text-maroon hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-maroon/40">{{ $event->display_name }}</a>
                <x-event-status :status="$event->status" />
                @unless ($event->is_visible)
                    <x-badge>Hidden</x-badge>
                @endunless
            </div>

            <p class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-sm text-muted">
                @if ($event->starts_on)
                    <span class="inline-flex items-center gap-1.5">
                        <x-icon name="calendar" class="h-4 w-4 shrink-0" />
                        {{ $event->starts_on->format('j M Y') }}@if ($event->ends_on && ! $event->ends_on->isSameDay($event->starts_on)) – {{ $event->ends_on->format('j M Y') }}@endif
                    </span>
                    @if ($timing)
                        <span @class(['text-xs', 'font-medium text-maroon' => $timing['state'] !== 'past'])>{{ $timing['label'] }}</span>
                    @endif
                @else
                    <span>Date not set yet</span>
                @endif
                <span class="text-xs">/event/{{ $event->slug }}</span>
            </p>
        </div>

        @if ($live)
            <x-button :href="route('event.home', $event)" target="_blank" rel="noopener" variant="secondary" size="sm" icon="external">View in app</x-button>
        @endif
    </div>

    <nav aria-label="Event sections" class="overflow-x-auto border-t border-line bg-paper/60 px-2 sm:px-3">
        <ul class="flex min-w-max items-center gap-1">
            @foreach ($tabs as $key => [$label, $url, $count])
                <li>
                    <a href="{{ $url }}"
                       @if ($current === $key) aria-current="page" @endif
                       @class([
                           '-mb-px inline-flex items-center gap-1.5 border-b-2 px-3 py-3 text-sm font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-maroon/40',
                           'border-maroon text-maroon' => $current === $key,
                           'border-transparent text-muted hover:border-line hover:text-ink' => $current !== $key,
                       ])>
                        {{ $label }}
                        @if ($count !== null)
                            <span @class([
                                'rounded-full px-1.5 py-0.5 text-[11px] font-semibold tabular-nums leading-none',
                                'bg-maroon/10 text-maroon' => $current === $key,
                                'bg-paper-soft text-muted' => $current !== $key,
                            ])>{{ number_format($count) }}</span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>
</div>
