{{--
    A headline number. <x-stat label="Events" :value="12" hint="3 active" icon="calendar" :href="route(…)" />
    With href the whole tile is a link.
--}}
@props(['label', 'value', 'hint' => null, 'icon' => null, 'href' => null])

@php($tag = $href ? 'a' : 'div')

<{{ $tag }} @if ($href) href="{{ $href }}" @endif
    {{ $attributes->class([
        'group flex items-start gap-4 rounded-xl border border-line bg-white p-5 shadow-xs motion-safe:transition motion-safe:duration-200',
        'hover:border-maroon/40 hover:shadow-md motion-safe:hover:-translate-y-0.5 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-maroon/40' => $href,
    ]) }}>
    @if ($icon)
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-maroon/10 text-maroon-text motion-safe:transition-transform motion-safe:duration-300 motion-safe:ease-out motion-safe:group-hover:-rotate-6 motion-safe:group-hover:scale-110">
            <x-icon :name="$icon" class="h-5 w-5" />
        </span>
    @endif
    <span class="min-w-0">
        <span class="block text-sm text-muted">{{ $label }}</span>
        <span class="mt-0.5 block font-display text-2xl font-semibold tabular-nums tracking-tight text-ink">{{ $value }}</span>
        @if ($hint)
            <span class="mt-0.5 block text-xs text-muted">{{ $hint }}</span>
        @endif
    </span>
</{{ $tag }}>
