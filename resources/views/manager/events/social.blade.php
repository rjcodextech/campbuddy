<x-manager-layout title="Social media" :subtitle="$event->display_name"
                  :breadcrumbs="[['My events', route('manager.dashboard')], [$event->display_name, route('manager.events.details', $event)], ['Social media']]">
    <x-manager.event-nav :event="$event" current="social" />

    @include('partials.social-media')
</x-manager-layout>
