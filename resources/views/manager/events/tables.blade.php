<x-manager-layout title="Contributor Day" :subtitle="$event->display_name"
                  :breadcrumbs="[['My events', route('manager.dashboard')], [$event->display_name, route('manager.events.details', $event)], ['Contributor Day']]">
    <x-manager.event-nav :event="$event" current="tables" />

    @include('partials.contributor-tables', [
        'event' => $event,
        'tables' => $tables,
        'names' => $names,
        'storeUrl' => route('manager.events.tables.store', $event),
        'updateUrl' => fn ($table) => route('manager.events.tables.update', [$event, $table->id]),
        'destroyUrl' => fn ($table) => route('manager.events.tables.destroy', [$event, $table->id]),
    ])
</x-manager-layout>
