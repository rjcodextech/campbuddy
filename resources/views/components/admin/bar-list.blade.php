{{--
    A ranked list with a bar per row, longest bar = largest value.
    <x-admin.bar-list :rows="[['Jaipur', 50], ['Mumbai', 24, '30 sessions']]" unit="users" />
    Each row: [label, value, optional hint shown on hover]. Empty rows → the empty text.
--}}
@props(['rows' => [], 'unit' => '', 'empty' => 'No data for these dates.'])

@php($max = max(1, ...array_map(fn ($r) => (float) $r[1], $rows ?: [[null, 0]])))

@if ($rows === [])
    <p class="text-sm text-muted">{{ $empty }}</p>
@else
    <ul {{ $attributes->class('space-y-1.5') }}>
        @foreach ($rows as $row)
            @php($title = $row[0].': '.number_format($row[1]).($unit ? " {$unit}" : '').(! empty($row[2]) ? " · {$row[2]}" : ''))
            <li class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-3 gap-y-1 rounded px-1 py-0.5 text-sm hover:bg-paper sm:grid-cols-[minmax(0,40%)_1fr_auto]" title="{{ $title }}">
                <span class="break-words leading-snug text-ink">{{ $row[0] }}</span>
                <span class="col-span-2 row-start-2 h-2.5 overflow-hidden rounded-r bg-paper-soft sm:col-span-1 sm:row-start-auto">
                    <span class="block h-full min-w-[2px] rounded-r bg-maroon" style="width: {{ round($row[1] / $max * 100, 1) }}%"></span>
                </span>
                <span class="text-right text-xs tabular-nums text-muted">{{ number_format($row[1]) }}</span>
            </li>
        @endforeach
    </ul>
@endif
