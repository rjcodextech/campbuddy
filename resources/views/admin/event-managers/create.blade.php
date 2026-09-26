<x-app-layout title="Add event manager" subtitle="Give someone a few WordCamps to look after."
              :breadcrumbs="[['Event managers', route('admin.event-managers.index')], ['Add']]">
    <form method="POST" action="{{ route('admin.event-managers.store') }}" class="max-w-4xl">
        @csrf

        <x-card title="Event manager">
            @include('admin.event-managers._form')

            <x-slot:footer>
                <x-button :href="route('admin.event-managers.index')" variant="secondary">Cancel</x-button>
                <x-button>Create event manager</x-button>
            </x-slot:footer>
        </x-card>
    </form>
</x-app-layout>
