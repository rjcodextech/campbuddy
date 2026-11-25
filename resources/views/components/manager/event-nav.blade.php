{{--
    The sections of an event manager's event as a mobile pill scroller / desktop left rail.
      <x-manager.event-nav :event="$event" current="details" />
--}}
@props(['event', 'current'])

@php
    $groups = [
        'Setup' => [
            'details' => ['Event details', route('manager.events.details', $event)],
            'information' => ['Event information', route('manager.events.information', $event)],
            'quests' => ['Quests & checklist', route('manager.events.quests', $event)],
        ],
        'People & Day' => [
            'attendees' => ['Attendees', route('manager.events.attendees', $event)],
            'tables' => ['Contributor Day', route('manager.events.tables', $event)],
            'delay' => ['Running late', route('manager.events.delay', $event)],
        ],
        'Engage' => [
            'social' => ['Social media', route('manager.events.social', $event)],
            'memories' => ['Memories', route('manager.events.memories', $event)],
            'notifications' => ['Notifications', route('manager.events.notifications', $event)],
            'reports' => ['Reports', route('manager.events.reports', $event)],
        ],
    ];
    $tabs = array_merge(...array_values($groups));
@endphp

<nav aria-label="Event sections" x-data x-init="$nextTick(() => { const tab = $el.querySelector('[aria-current=page]'); if (tab && $el.scrollWidth > $el.clientWidth) $el.scrollLeft += tab.getBoundingClientRect().left - $el.getBoundingClientRect().left - ($el.clientWidth - tab.offsetWidth) / 2 })"
     {{ $attributes->class('cb-event-shell scrollbar-none mb-6 overflow-x-auto rounded-xl border border-line bg-white p-1.5 shadow-xs lg:overflow-visible lg:p-2') }}>
    {{-- Mobile & Tablet horizontal pill bar --}}
    <ul class="flex min-w-max items-center gap-1 lg:hidden">
        @foreach ($tabs as $key => [$label, $url])
            <li>
                <a href="{{ $url }}"
                   @if ($current === $key) aria-current="page" @endif
                   @class([
                       'inline-flex items-center whitespace-nowrap rounded-lg px-3 py-2 text-xs font-semibold transition-colors focus:outline-hidden focus-visible:ring-2 focus-visible:ring-maroon/40',
                       'bg-maroon text-white shadow-xs' => $current === $key,
                       'text-muted hover:bg-paper-soft hover:text-ink' => $current !== $key,
                   ])>
                    {{ $label }}
                </a>
            </li>
        @endforeach
    </ul>

    {{-- Desktop grouped vertical navigation --}}
    <div class="hidden space-y-3 lg:block">
        @foreach ($groups as $heading => $items)
            <div>
                <p class="px-3 pb-1 pt-1 text-[11px] font-semibold uppercase tracking-wider text-muted">{{ $heading }}</p>
                <ul class="space-y-0.5">
                    @foreach ($items as $key => [$label, $url])
                        <li>
                            <a href="{{ $url }}"
                               @if ($current === $key) aria-current="page" @endif
                               @class([
                                   'flex w-full items-center rounded-lg px-3 py-2 text-sm font-medium transition-colors focus:outline-hidden focus-visible:ring-2 focus-visible:ring-maroon/40',
                                   'bg-maroon/10 font-semibold text-maroon-text' => $current === $key,
                                   'text-muted hover:bg-paper-soft hover:text-ink' => $current !== $key,
                               ])>
                                {{ $label }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
</nav>
