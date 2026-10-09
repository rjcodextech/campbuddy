<x-app-layout title="Social media" :subtitle="$event->display_name"
              :breadcrumbs="[['Events', route('admin.events.index')], [$event->display_name, route('admin.events.edit', $event)], ['Social media']]">
    <x-admin.event-nav :event="$event" current="social" />

    @include('partials.social-media')
</x-app-layout>
