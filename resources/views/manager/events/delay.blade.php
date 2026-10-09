<x-manager-layout title="Running late" :subtitle="$event->display_name"
                  :breadcrumbs="[['My events', route('manager.dashboard')], [$event->display_name, route('manager.events.details', $event)], ['Running late']]">
    <x-manager.event-nav :event="$event" current="delay" />

    @include('partials.schedule-delay', ['event' => $event, 'saveUrl' => route('manager.events.delay.store', $event), 'clearUrl' => route('manager.events.delay.clear', $event)])
</x-manager-layout>
