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

        <x-card title="Recent activity" description="What this person changed lately.">
            <x-slot:actions>
                <x-button :href="route('admin.event-managers.activity', ['manager' => $manager->id])" variant="link" size="sm">All activity</x-button>
            </x-slot:actions>

            @forelse ($activity as $change)
                <div class="border-t border-line py-2.5 first:border-t-0 first:pt-0 last:pb-0">
                    <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-0.5">
                        <span class="text-sm font-medium">
                            @if ($change->event)
                                <a href="{{ route('admin.events.edit', $change->event) }}" class="hover:text-maroon hover:underline">{{ $change->event->display_name }}</a>
                            @endif
                        </span>
                        <span class="text-xs text-muted" title="{{ $change->created_at }}">{{ $change->created_at->diffForHumans() }}</span>
                    </div>
                    <p class="mt-0.5 break-words text-sm text-muted">{{ $change->summary }}</p>
                </div>
            @empty
                <p class="text-sm text-muted">Nothing changed yet.</p>
            @endforelse
        </x-card>

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
