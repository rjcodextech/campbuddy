<x-manager-layout title="Reports" :subtitle="$event->display_name"
                  :breadcrumbs="[['My events', route('manager.dashboard')], [$event->display_name, route('manager.events.details', $event)], ['Reports']]">
    <x-manager.event-nav :event="$event" current="reports" />

    @include('partials.event-reports', ['open' => $open, 'closed' => $closed, 'stillThere' => $stillThere, 'removeUrl' => fn ($id) => route('manager.events.reports.remove', [$event, $id]), 'dismissUrl' => fn ($id) => route('manager.events.reports.dismiss', [$event, $id])])
</x-manager-layout>
