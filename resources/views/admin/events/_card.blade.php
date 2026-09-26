{{-- One event as a card on the Events list. Needs $event, $lastFetches, $highlightedIds, $nextUpId, $today. --}}
@php
    $soon = in_array($event->id, $highlightedIds, true);
    $timing = \App\Support\EventListing::timing($event, $today);
    $fetch = $lastFetches[$event->id] ?? null;
    $live = $event->status === 'active' && $event->is_visible;

    $tag = match (true) {
        $soon && ($timing['state'] ?? null) === 'ongoing' => ['Happening now', 'success'],
        $event->id === $nextUpId => ['Next up', 'brand'],
        default => null,
    };
@endphp

<li @class(['cb-event-card', 'cb-event-card-soon' => $soon])>
    <div class="flex items-start gap-3 p-4 sm:p-5">
        <span class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-line bg-white text-base font-semibold text-maroon" aria-hidden="true">
            @if ($event->markUrl())
                <img src="{{ $event->markUrl() }}" alt="" class="max-h-10 max-w-10 object-contain">
            @else
                {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($event->display_name, 0, 1)) }}
            @endif
        </span>

        <div class="min-w-0 flex-1">
            @if ($tag)
                <x-badge :variant="$tag[1]" class="mb-1">{{ $tag[0] }}</x-badge>
            @endif
            <h2 class="text-base font-semibold leading-snug">
                <a href="{{ route('admin.events.edit', $event) }}" class="hover:text-maroon hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-maroon/40">{{ $event->display_name }}</a>
            </h2>
            <p class="mt-0.5 truncate text-xs text-muted" title="{{ $event->slug }}">{{ $event->slug }}</p>
        </div>

        <x-event-status :status="$event->status" class="shrink-0" />
    </div>

    <dl class="grid grid-cols-2 gap-x-4 gap-y-3 border-t border-line px-4 py-3 text-sm sm:px-5">
        <div class="col-span-2 flex flex-wrap items-center justify-between gap-x-3 gap-y-0.5">
            <dt class="sr-only">Dates</dt>
            <dd class="inline-flex items-center gap-1.5 text-muted">
                <x-icon name="calendar" class="h-4 w-4 shrink-0" />
                @if ($event->starts_on)
                    <span>{{ $event->starts_on->format('j M Y') }}@if ($event->ends_on && ! $event->ends_on->isSameDay($event->starts_on)) – {{ $event->ends_on->format('j M Y') }}@endif</span>
                @else
                    <span>Date not set yet</span>
                @endif
            </dd>
            @if ($timing)
                <dd @class(['text-xs', 'font-medium text-maroon' => $timing['state'] !== 'past', 'text-muted' => $timing['state'] === 'past'])>{{ $timing['label'] }}</dd>
            @endif
        </div>

        <div>
            <dt class="text-xs text-muted">Attendees</dt>
            <dd class="font-medium tabular-nums">{{ number_format($event->attendee_roster_count) }}</dd>
        </div>

        <div>
            <dt class="text-xs text-muted">Data</dt>
            <dd class="whitespace-nowrap">
                @if ($fetch)
                    <x-fetch-status :status="$fetch->status" />
                    <span class="ml-1 text-xs text-muted">{{ $fetch->fetched_at->diffForHumans(short: true) }}</span>
                @else
                    <span class="text-xs text-muted">Not fetched</span>
                @endif
            </dd>
        </div>
    </dl>

    <div class="mt-auto flex flex-wrap items-center justify-between gap-2 border-t border-line bg-paper/60 px-4 py-3 sm:px-5">
        @if ($event->is_visible)
            <x-badge variant="success">Visible</x-badge>
        @else
            <x-badge>Hidden</x-badge>
        @endif

        <div class="flex items-center gap-3">
            @if ($live)
                <x-button :href="route('event.home', $event)" target="_blank" rel="noopener" variant="link" size="sm" icon="external">View in app</x-button>
            @endif
            <x-button :href="route('admin.events.edit', $event)" variant="secondary" size="sm">Manage</x-button>
        </div>
    </div>
</li>
