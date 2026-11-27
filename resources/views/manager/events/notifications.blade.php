<x-manager-layout title="Notifications" :subtitle="$event->display_name"
                  :breadcrumbs="[['My events', route('manager.dashboard')], [$event->display_name, route('manager.events.details', $event)], ['Notifications']]">
    <x-manager.event-nav :event="$event" current="notifications" />

    <div class="w-full space-y-6">
        @include('partials.event-broadcast', ['event' => $event, 'sendUrl' => route('manager.events.broadcasts.store', $event)])

        @include('partials.event-notifications', ['event' => $event, 'memoriesUrl' => route('manager.events.memories', $event)])
    </div>
</x-manager-layout>
