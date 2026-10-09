<x-app-layout title="Running late" :subtitle="$event->display_name"
              :breadcrumbs="[['Events', route('admin.events.index')], [$event->display_name, route('admin.events.edit', $event)], ['Running late']]">
    <x-admin.event-nav :event="$event" current="delay" />

    @include('partials.schedule-delay', ['event' => $event, 'saveUrl' => route('admin.events.delay.store', $event), 'clearUrl' => route('admin.events.delay.clear', $event)])
</x-app-layout>
