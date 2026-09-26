<x-app-layout :title="$manager->name" :subtitle="$manager->email"
              :breadcrumbs="[['Event managers', route('admin.event-managers.index')], [$manager->name]]">
    <x-slot:actions>
        @if ($manager->is_active)
            <x-badge variant="success" class="!px-3 !py-1">Active</x-badge>
        @else
            <x-badge variant="warning" class="!px-3 !py-1">Switched off</x-badge>
        @endif
    </x-slot:actions>

    <div class="max-w-4xl space-y-6">
        <form method="POST" action="{{ route('admin.event-managers.update', $manager) }}">
            @csrf
            @method('PUT')

            <x-card title="Event manager">
                @include('admin.event-managers._form')

                <x-slot:footer>
                    <x-button>Save changes</x-button>
                </x-slot:footer>
            </x-card>
        </form>

        <x-card title="Sign-in page" description="Send the manager this address, with their email and password.">
            <p class="break-all font-mono text-sm">{{ route('manager.login') }}</p>
            <p class="mt-2 text-xs text-muted">
                @if ($manager->last_login_at)
                    Last signed in {{ $manager->last_login_at->diffForHumans() }}.
                @else
                    Hasn't signed in yet.
                @endif
            </p>
        </x-card>

        <x-card title="Delete event manager" danger
                description="Removes the account. The events and everything they edited stay as they are. To just stop them signing in, untick Active instead.">
            <x-action-form :action="route('admin.event-managers.destroy', $manager)" method="DELETE" variant="danger" icon="trash"
                           :confirm="'Delete “'.$manager->name.'”? They will no longer be able to sign in.'">
                Delete event manager
            </x-action-form>
        </x-card>
    </div>
</x-app-layout>
