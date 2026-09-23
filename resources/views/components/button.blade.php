{{--
    The one button. Renders an <a> when given href, otherwise a <button>.

      <x-button>Save</x-button>                              primary submit
      <x-button variant="secondary" size="sm">Filter</x-button>
      <x-button href="{{ route('…') }}" icon="plus">Add event</x-button>
      <x-button variant="danger-outline" type="button">Delete</x-button>

    variant: primary | secondary | danger | danger-outline | link
    size:    md | sm
--}}
@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'submit',
    'icon' => null,
])

@php
    // Spelled out in full (not built as 'cb-btn-'.$variant): Tailwind only
    // keeps component classes whose whole name appears in a scanned file.
    $variants = [
        'primary' => 'cb-btn-primary',
        'secondary' => 'cb-btn-secondary',
        'danger' => 'cb-btn-danger',
        'danger-outline' => 'cb-btn-danger-outline',
        'link' => 'cb-btn-link',
    ];

    $classes = 'cb-btn '.($variants[$variant] ?? $variants['primary']).($size === 'sm' ? ' cb-btn-sm' : '');
    $iconClass = $size === 'sm' ? 'h-4 w-4' : 'h-5 w-5';
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-icon :name="$icon" :class="$iconClass" />@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-icon :name="$icon" :class="$iconClass" />@endif
        {{ $slot }}
    </button>
@endif
