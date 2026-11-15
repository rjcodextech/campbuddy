{{--
    <x-alert type="success">Saved.</x-alert>
    <x-alert type="error" title="There was a problem">…</x-alert>

    type: success | error | warning | info
--}}
@props(['type' => 'info', 'title' => null, 'dismissible' => false])

@php
    $styles = [
        'success' => ['box' => 'border-teal/30 bg-teal/10 text-ink', 'icon' => 'text-teal', 'name' => 'check-circle'],
        'error' => ['box' => 'border-danger/30 bg-danger-soft text-ink', 'icon' => 'text-danger', 'name' => 'exclamation-circle'],
        'warning' => ['box' => 'border-gold/40 bg-gold/10 text-ink', 'icon' => 'text-gold', 'name' => 'exclamation-triangle'],
        'info' => ['box' => 'border-line bg-white text-ink shadow-xs', 'icon' => 'text-navy', 'name' => 'information-circle'],
    ][$type] ?? null;

    $style = $styles ?? [
        'box' => 'border-line bg-white text-ink shadow-xs', 'icon' => 'text-navy', 'name' => 'information-circle',
    ];
@endphp

<div @if($dismissible) x-data="{ show: true }" x-show="show" x-transition.opacity.duration.300ms @endif role="{{ $type === 'error' ? 'alert' : 'status' }}" {{ $attributes->merge(['class' => 'flex items-start gap-3 rounded-lg border px-4 py-3 text-sm '.$style['box']]) }}>
    <x-icon :name="$style['name']" class="mt-0.5 h-5 w-5 shrink-0 {{ $style['icon'] }}" />
    <div class="min-w-0 flex-1">
        @if ($title)
            <p class="font-semibold">{{ $title }}</p>
        @endif
        <div @class(['mt-1' => $title])>{{ $slot }}</div>
    </div>
    @if ($dismissible)
        <button type="button" x-on:click="show = false" aria-label="Dismiss notification" class="-mr-1 -mt-0.5 inline-flex h-6 w-6 items-center justify-center rounded-md text-muted hover:bg-black/5 hover:text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-maroon/40">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    @endif
</div>
