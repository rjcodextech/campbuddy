{{--
    The sections of an event manager's event (three to edit, plus its QR codes), as a tab strip.

      <x-manager.event-nav :event="$event" current="details" />

    current: details | information | quests | attendees | tables | delay | social | memories | notifications | reports
--}}
@props(['event', 'current'])

@php
    $tabs = [
        'details' => ['Event details', route('manager.events.details', $event)],
        'information' => ['Event information', route('manager.events.information', $event)],
        'quests' => ['Quests & checklist', route('manager.events.quests', $event)],
        'attendees' => ['Attendees', route('manager.events.attendees', $event)],
        'tables' => ['Contributor Day', route('manager.events.tables', $event)],
        'delay' => ['Running late', route('manager.events.delay', $event)],
        'social' => ['Social media', route('manager.events.social', $event)],
        'memories' => ['Memories', route('manager.events.memories', $event)],
        'notifications' => ['Notifications', route('manager.events.notifications', $event)],
        'reports' => ['Reports', route('manager.events.reports', $event)],
    ];
@endphp

{{-- On a phone: a tab strip on top, scrolled so the open section is in view (most tabs sit past the edge).
     From lg up: a menu card on the left that stays in view while the page scrolls (.cb-event-shell in
     resources/css/app.css puts the page's content in a second column, as on the admin's event pages). --}}
<nav aria-label="Event sections" x-data x-init="$nextTick(() => { const tab = $el.querySelector('[aria-current=page]'); if (tab && $el.scrollWidth > $el.clientWidth) $el.scrollLeft += tab.getBoundingClientRect().left - $el.getBoundingClientRect().left - ($el.clientWidth - tab.offsetWidth) / 2 })" {{ $attributes->class('cb-event-shell -mx-4 mb-6 overflow-x-auto border-b border-line px-4 sm:mx-0 sm:px-0 lg:overflow-visible lg:rounded-xl lg:border lg:bg-white lg:p-2 lg:shadow-xs') }}>
    <p class="hidden px-3 pt-2 pb-1 text-xs font-semibold uppercase tracking-wide text-muted lg:block">This event</p>
    <ul class="flex min-w-max items-center gap-1 lg:min-w-0 lg:flex-col lg:items-stretch">
        @foreach ($tabs as $key => [$label, $url])
            <li>
                <a href="{{ $url }}"
                   @if ($current === $key) aria-current="page" @endif
                   @class([
                       '-mb-px inline-flex items-center border-b-2 px-3 py-2.5 text-sm font-medium transition-colors focus:outline-hidden focus-visible:ring-2 focus-visible:ring-maroon/40 lg:mb-0 lg:flex lg:w-full lg:rounded-lg lg:border-b-0',
                       'border-maroon text-maroon lg:bg-maroon/10' => $current === $key,
                       'border-transparent text-muted hover:border-line hover:text-ink lg:hover:bg-paper-soft' => $current !== $key,
                   ])>
                    {{ $label }}
                </a>
            </li>
        @endforeach
    </ul>
</nav>
