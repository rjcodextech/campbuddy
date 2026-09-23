{{--
    <x-alert type="success">Saved.</x-alert>
    <x-alert type="error" title="There was a problem">…</x-alert>

    type: success | error | warning | info
--}}
@props(['type' => 'info', 'title' => null])

@php
    $styles = [
        'success' => ['box' => 'border-teal/30 bg-teal/10 text-ink', 'icon' => 'text-teal', 'name' => 'check-circle'],
        'error' => ['box' => 'border-danger/30 bg-danger-soft text-ink', 'icon' => 'text-danger', 'name' => 'exclamation-circle'],
        'warning' => ['box' => 'border-gold/40 bg-gold/10 text-ink', 'icon' => 'text-gold', 'name' => 'exclamation-triangle'],
        'info' => ['box' => 'border-line bg-white text-ink shadow-sm', 'icon' => 'text-navy', 'name' => 'information-circle'],
    ][$type] ?? null;

    $style = $styles ?? [
        'box' => 'border-line bg-white text-ink shadow-sm', 'icon' => 'text-navy', 'name' => 'information-circle',
    ];
@endphp

<div role="{{ $type === 'error' ? 'alert' : 'status' }}" {{ $attributes->merge(['class' => 'flex gap-3 rounded-lg border px-4 py-3 text-sm '.$style['box']]) }}>
    <x-icon :name="$style['name']" class="mt-0.5 h-5 w-5 shrink-0 {{ $style['icon'] }}" />
    <div class="min-w-0 flex-1">
        @if ($title)
            <p class="font-semibold">{{ $title }}</p>
        @endif
        <div @class(['mt-1' => $title])>{{ $slot }}</div>
    </div>
</div>
