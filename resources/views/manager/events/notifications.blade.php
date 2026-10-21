<x-manager-layout title="Notifications" :subtitle="$event->display_name"
                  :breadcrumbs="[['My events', route('manager.dashboard')], [$event->display_name, route('manager.events.details', $event)], ['Notifications']]">
    <x-manager.event-nav :event="$event" current="notifications" />

    @include('partials.event-broadcast', ['event' => $event, 'sendUrl' => route('manager.events.broadcasts.store', $event)])

    @include('partials.event-notifications', ['event' => $event, 'memoriesUrl' => route('manager.events.memories', $event)])
</x-manager-layout>
