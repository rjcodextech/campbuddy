{{--
    The leads table, shared by an event's Deal leads page and a default
    deal's leads page. $leads (paginator), $hasFilters, $showEvent
--}}
<x-card flush>
    <x-table>
        <x-slot:head>
            <th>Deal</th>
            @if ($showEvent)
                <th>Event</th>
            @endif
            <th>Name</th>
            <th>Email</th>
            <th>Mobile</th>
            <th>Details</th>
            <th>Submitted</th>
        </x-slot:head>

        @forelse ($leads as $lead)
            <tr>
                <td>{{ $lead->offer?->displayName() ?? '—' }}</td>
                @if ($showEvent)
                    <td>{{ $lead->event?->display_name ?? '—' }}</td>
                @endif
                <td class="font-medium">{{ $lead->name ?? '—' }}</td>
                <td><a href="mailto:{{ $lead->email }}" class="text-maroon hover:underline">{{ $lead->email }}</a></td>
                <td class="whitespace-nowrap">{{ $lead->mobile ?? '—' }}</td>
                <td class="text-muted">
                    @if ($lead->company)
                        <span class="block">{{ $lead->company }}</span>
                    @endif
                    @if ($lead->choices)
                        <span class="block">{{ implode(', ', $lead->choices) }}</span>
                    @endif
                    @if (! $lead->company && ! $lead->choices)
                        —
                    @endif
                </td>
                <td class="whitespace-nowrap text-muted">{{ $lead->created_at->format('d M Y, g:ia') }}</td>
            </tr>
        @empty
            <x-table.empty :colspan="$showEvent ? 7 : 6" icon="inbox" :title="$hasFilters ? 'No leads match these filters' : 'No leads captured yet'">
                @unless ($hasFilters)
                    Turn on the contact form for a deal and what attendees fill in will appear here.
                @endunless
            </x-table.empty>
        @endforelse
    </x-table>

    @if ($leads->hasPages())
        <x-slot:footer>
            <span class="mr-auto text-xs text-muted">Showing {{ $leads->firstItem() }}–{{ $leads->lastItem() }} of {{ number_format($leads->total()) }}</span>
            {{ $leads->links() }}
        </x-slot:footer>
    @endif
</x-card>
