<x-app-layout title="Notifications" :subtitle="$event->display_name"
              :breadcrumbs="[['Events', route('admin.events.index')], [$event->display_name, route('admin.events.edit', $event)], ['Notifications']]">
    <x-admin.event-nav :event="$event" current="notifications" />

    @include('partials.event-notifications', ['event' => $event, 'memoriesUrl' => route('admin.events.memories', $event)])
</x-app-layout>
