<x-app-layout title="QR codes" :subtitle="$event->display_name"
              :breadcrumbs="[['Events', route('admin.events.index')], [$event->display_name, route('admin.events.edit', $event)], ['QR codes']]">
    <x-admin.event-nav :event="$event" current="qr" />

    @include('partials.qr-kit', ['event' => $event, 'codes' => $codes])
</x-app-layout>
