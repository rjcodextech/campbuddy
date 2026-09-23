{{-- A full-width "nothing here" row for <x-table>. --}}
@props(['colspan' => 1, 'icon' => 'inbox', 'title' => 'Nothing here yet'])

<tr class="hover:!bg-transparent">
    <td colspan="{{ $colspan }}" class="px-4 py-12 text-center">
        <x-icon :name="$icon" class="mx-auto h-8 w-8 text-muted/50" />
        <p class="mt-2 text-sm font-medium text-ink">{{ $title }}</p>
        @if (trim((string) $slot) !== '')
            <div class="mx-auto mt-1 max-w-sm text-sm text-muted">{{ $slot }}</div>
        @endif
    </td>
</tr>
