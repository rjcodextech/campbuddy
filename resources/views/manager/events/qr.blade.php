<x-manager-layout title="QR codes" :subtitle="$event->display_name"
                  :breadcrumbs="[['My events', route('manager.dashboard')], [$event->display_name, route('manager.events.details', $event)], ['QR codes']]">
    <x-manager.event-nav :event="$event" current="qr" />

    @include('partials.qr-kit', ['event' => $event, 'codes' => $codes])
</x-manager-layout>
