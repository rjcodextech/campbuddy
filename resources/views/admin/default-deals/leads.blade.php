@php
    $hasFilters = collect($filters)->filter(fn ($v) => filled($v))->isNotEmpty();
@endphp

<x-app-layout title="Leads" :subtitle="$deal->displayName().' · every event'"
              :breadcrumbs="[['Default deals', route('admin.deals.index')], [$deal->displayName(), route('admin.deals.edit', $deal)], ['Leads']]">
    <x-slot:actions>
        <x-button :href="route('admin.deals.leads.export', array_merge(['deal' => $deal], $filters))" variant="secondary" icon="download">
            Export CSV
        </x-button>
    </x-slot:actions>

    <div class="max-w-5xl space-y-6">
        <x-card title="Filter" description="Narrow the list — the CSV export uses the same filters.">
            <form method="GET" action="{{ route('admin.deals.leads', $deal) }}" class="flex flex-wrap items-end gap-4">
                <x-form.select name="event_id" label="Event" placeholder="All events" class="w-full sm:w-64"
                               :options="$events->all()" :value="$filters['event_id'] ?? ''" :use-old="false" :show-error="false" />
                <x-form.input name="from" type="date" label="From" class="w-full sm:w-44" :value="$filters['from'] ?? ''" :use-old="false" :show-error="false" />
                <x-form.input name="to" type="date" label="To" class="w-full sm:w-44" :value="$filters['to'] ?? ''" :use-old="false" :show-error="false" />

                <div class="flex items-center gap-2">
                    <x-button variant="secondary">Apply</x-button>
                    @if ($hasFilters)
                        <x-button :href="route('admin.deals.leads', $deal)" variant="link" class="px-2">Clear</x-button>
                    @endif
                </div>
            </form>
        </x-card>

        @include('admin.deal-leads._table', ['showEvent' => true])
    </div>
</x-app-layout>
