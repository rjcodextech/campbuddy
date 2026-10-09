<x-app-layout title="Contributor Day" :subtitle="$event->display_name"
              :breadcrumbs="[['Events', route('admin.events.index')], [$event->display_name, route('admin.events.edit', $event)], ['Contributor Day']]">
    <x-admin.event-nav :event="$event" current="tables" />

    @include('partials.contributor-tables', [
        'event' => $event,
        'tables' => $tables,
        'names' => $names,
        'storeUrl' => route('admin.events.tables.store', $event),
        'updateUrl' => fn ($table) => route('admin.events.tables.update', [$event, $table]),
        'destroyUrl' => fn ($table) => route('admin.events.tables.destroy', [$event, $table]),
    ])
</x-app-layout>
