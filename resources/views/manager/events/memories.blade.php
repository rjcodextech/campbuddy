<x-manager-layout title="Memories" :subtitle="$event->display_name"
                  :breadcrumbs="[['My events', route('manager.dashboard')], [$event->display_name, route('manager.events.details', $event)], ['Memories']]">
    <x-manager.event-nav :event="$event" current="memories" />

    @include('partials.event-memories', ['event' => $event, 'saveUrl' => route('manager.events.memories.store', $event), 'notifyUrl' => fn ($kind) => route('manager.events.memories.notify', [$event, $kind])])
</x-manager-layout>
