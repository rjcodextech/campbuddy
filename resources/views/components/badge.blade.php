{{--
    A small status pill. <x-badge variant="success">Active</x-badge>

    variant: neutral | success | warning | danger | info | brand
    Event lifecycle statuses map through x-badge in the events views:
    draft → neutral, approved → info, active → success, archived → warning.
--}}
@props(['variant' => 'neutral'])

@php
    // Text a shade deeper than the brand colour where the tint behind it pulls contrast under
    // WCAG AA 4.5:1 (teal on its tint was ~4.0:1, maroon ~4.2:1).
    $styles = [
        'neutral' => 'bg-paper-soft text-[#574f4b] ring-line',
        'success' => 'bg-teal/10 text-[#035d5f] ring-teal/25',
        'warning' => 'bg-gold/15 text-[#8a5a00] ring-gold/40',
        'danger' => 'bg-danger-soft text-danger ring-danger/25',
        'info' => 'bg-navy/5 text-navy ring-navy/20',
        'brand' => 'bg-maroon/10 text-maroon-dark ring-maroon/25',
    ][$variant] ?? 'bg-paper-soft text-[#574f4b] ring-line';
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset '.$styles]) }}>{{ $slot }}</span>
