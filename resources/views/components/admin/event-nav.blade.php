{{--
    The tab strip shared by every page that belongs to one event, so an
    admin can hop between its sections without going back to the list.

      <x-admin.event-nav :event="$event" current="quests" />

    current: details | quests | offers | roster | leads
--}}
@props(['event', 'current'])

@php
    $tabs = [
        'details' => ['Details', route('admin.events.edit', $event)],
        'quests' => ['Quests & checklist', route('admin.events.quests.index', $event)],
        'offers' => ['Deals', route('admin.events.offers.index', $event)],
        'roster' => ['Roster', route('admin.events.roster.index', $event)],
        'leads' => ['Deal leads', route('admin.events.deal-leads.index', $event)],
    ];
@endphp

<nav aria-label="Event sections" {{ $attributes->class('-mx-4 mb-6 overflow-x-auto border-b border-line px-4 sm:mx-0 sm:px-0') }}>
    <ul class="flex min-w-max items-center gap-1">
        @foreach ($tabs as $key => [$label, $url])
            <li>
                <a href="{{ $url }}"
                   @if ($current === $key) aria-current="page" @endif
                   @class([
                       '-mb-px inline-flex items-center border-b-2 px-3 py-2.5 text-sm font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-maroon/40',
                       'border-maroon text-maroon' => $current === $key,
                       'border-transparent text-muted hover:border-line hover:text-ink' => $current !== $key,
                   ])>
                    {{ $label }}
                </a>
            </li>
        @endforeach
    </ul>
</nav>
