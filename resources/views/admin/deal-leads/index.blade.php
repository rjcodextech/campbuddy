@php
    $hasFilters = collect($filters)->filter(fn ($v) => filled($v))->isNotEmpty();
@endphp

<x-app-layout title="Deal leads" :subtitle="$event->display_name"
              :breadcrumbs="[['Events', route('admin.events.index')], [$event->display_name, route('admin.events.edit', $event)], ['Deal leads']]">
    <x-slot:actions>
        <x-button :href="route('admin.events.deal-leads.export', array_merge(['event' => $event], $filters))" variant="secondary" icon="download">
            Export CSV
        </x-button>
    </x-slot:actions>

    <x-admin.event-nav :event="$event" current="leads" />

    <div class="max-w-5xl space-y-6">
        <x-card title="Filter" description="Narrow the list — the CSV export uses the same filters.">
            <form method="GET" action="{{ route('admin.events.deal-leads.index', $event) }}" class="flex flex-wrap items-end gap-4">
                <x-form.select name="offer_id" label="Deal" placeholder="All deals" class="w-full sm:w-56"
                               :options="$offers->pluck('title', 'id')->all()" :value="$filters['offer_id'] ?? ''" :use-old="false" :show-error="false" />
                <x-form.input name="from" type="date" label="From" class="w-full sm:w-44" :value="$filters['from'] ?? ''" :use-old="false" :show-error="false" />
                <x-form.input name="to" type="date" label="To" class="w-full sm:w-44" :value="$filters['to'] ?? ''" :use-old="false" :show-error="false" />

                <div class="flex items-center gap-2">
                    <x-button variant="secondary">Apply</x-button>
                    @if ($hasFilters)
                        <x-button :href="route('admin.events.deal-leads.index', $event)" variant="link" class="px-2">Clear</x-button>
                    @endif
                </div>
            </form>
        </x-card>

        <x-card flush>
            <x-table>
                <x-slot:head>
                    <th>Deal</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Mobile</th>
                    <th>Submitted</th>
                </x-slot:head>

                @forelse ($leads as $lead)
                    <tr>
                        <td>{{ $lead->offer?->title ?? '—' }}</td>
                        <td class="font-medium">{{ $lead->name }}</td>
                        <td><a href="mailto:{{ $lead->email }}" class="text-maroon hover:underline">{{ $lead->email }}</a></td>
                        <td class="whitespace-nowrap">{{ $lead->mobile ?? '—' }}</td>
                        <td class="whitespace-nowrap text-muted">{{ $lead->created_at->format('d M Y, g:ia') }}</td>
                    </tr>
                @empty
                    <x-table.empty :colspan="5" icon="inbox" :title="$hasFilters ? 'No leads match these filters' : 'No leads captured yet'">
                        @unless ($hasFilters)
                            Turn on “Require contact info” for a deal and attendee details will appear here.
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
    </div>
</x-app-layout>
