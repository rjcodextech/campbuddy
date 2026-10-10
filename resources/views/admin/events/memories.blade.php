<x-app-layout title="Memories" :subtitle="$event->display_name"
              :breadcrumbs="[['Events', route('admin.events.index')], [$event->display_name, route('admin.events.edit', $event)], ['Memories']]">
    <x-admin.event-nav :event="$event" current="memories" />

    @include('partials.event-memories', ['event' => $event, 'saveUrl' => route('admin.events.memories.store', $event), 'notifyUrl' => fn ($kind) => route('admin.events.memories.notify', [$event, $kind])])
</x-app-layout>
