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
                               :options="$offers->mapWithKeys(fn ($offer) => [$offer->id => $offer->displayName().($offer->isDefault() ? ' (default)' : '')])->all()" :value="$filters['offer_id'] ?? ''" :use-old="false" :show-error="false" />
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

        @include('admin.deal-leads._table', ['showEvent' => false])
    </div>
</x-app-layout>
