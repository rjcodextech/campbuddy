{{--
    The sections of an event manager's event (three to edit, plus its QR codes), as a tab strip.

      <x-manager.event-nav :event="$event" current="details" />

    current: details | information | quests | qr
--}}
@props(['event', 'current'])

@php
    $tabs = [
        'details' => ['Event details', route('manager.events.details', $event)],
        'information' => ['Event information', route('manager.events.information', $event)],
        'quests' => ['Quests & checklist', route('manager.events.quests', $event)],
        'qr' => ['QR codes', route('manager.events.qr', $event)],
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
