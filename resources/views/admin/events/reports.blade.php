<x-app-layout title="Reports" :subtitle="$event->display_name"
              :breadcrumbs="[['Events', route('admin.events.index')], [$event->display_name, route('admin.events.edit', $event)], ['Reports']]">
    <x-admin.event-nav :event="$event" current="reports" />

    @include('partials.event-reports', ['open' => $open, 'closed' => $closed, 'stillThere' => $stillThere, 'removeUrl' => fn ($id) => route('admin.events.reports.remove', [$event, $id]), 'dismissUrl' => fn ($id) => route('admin.events.reports.dismiss', [$event, $id])])
</x-app-layout>
