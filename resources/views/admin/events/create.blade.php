<x-app-layout title="Add event" subtitle="You can add branding, information and quests once it's created."
              :breadcrumbs="[['Events', route('admin.events.index')], ['Add event']]">
    <form method="POST" action="{{ route('admin.events.store') }}" class="max-w-4xl">
        @csrf

        <x-card title="Event details">
            @include('admin.events._form')

            <x-slot:footer>
                <x-button :href="route('admin.events.index')" variant="secondary">Cancel</x-button>
                <x-button>Create event</x-button>
            </x-slot:footer>
        </x-card>
    </form>
</x-app-layout>
