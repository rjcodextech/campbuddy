<x-app-layout title="Event managers" subtitle="People who can edit the events you give them — and nothing else."
              :breadcrumbs="[['Event managers']]">
    <x-slot:actions>
        <x-button :href="route('admin.event-managers.activity')" variant="secondary" icon="pencil">Activity</x-button>
        <x-button :href="route('admin.event-managers.create')" icon="plus">Add event manager</x-button>
    </x-slot:actions>

    <x-card flush>
        <x-table>
            <x-slot:head>
                <th>Manager</th>
                <th class="hidden md:table-cell">Phone</th>
                <th class="hidden md:table-cell">WordCamps</th>
                <th>Status</th>
                <th class="hidden lg:table-cell">Last signed in</th>
                <th class="w-px"><span class="sr-only">Actions</span></th>
            </x-slot:head>

            @forelse ($managers as $manager)
                <tr>
                    <td>
                        <a href="{{ route('admin.event-managers.edit', $manager) }}" class="font-medium hover:text-maroon hover:underline">{{ $manager->name }}</a>
                        <div class="text-xs text-muted">{{ $manager->email }}</div>
                        <p class="mt-1.5 text-xs text-muted md:hidden">
                            @if ($manager->events->isEmpty())
                                No WordCamps
                            @else
                                {{ $manager->events->count() }} {{ \Illuminate\Support\Str::plural('WordCamp', $manager->events->count()) }}: {{ $manager->events->pluck('display_name')->implode(', ') }}
                            @endif
                        </p>
                    </td>
                    <td class="hidden whitespace-nowrap text-muted md:table-cell">{{ $manager->phone }}</td>
                    <td class="hidden md:table-cell">
                        @if ($manager->events->isEmpty())
                            <span class="text-xs text-muted">None</span>
                        @else
                            <div class="flex flex-wrap gap-1">
                                @foreach ($manager->events->take(3) as $event)
                                    <x-badge variant="info" class="!inline-block max-w-[9rem] truncate align-middle" :title="$event->display_name">{{ $event->display_name }}</x-badge>
                                @endforeach
                                @if ($manager->events->count() > 3)
                                    <x-badge>+{{ $manager->events->count() - 3 }} more</x-badge>
                                @endif
                            </div>
                        @endif
                    </td>
                    <td>
                        @if ($manager->is_active)
                            <x-badge variant="success">Active</x-badge>
                        @else
                            <x-badge variant="warning">Switched off</x-badge>
                        @endif
                    </td>
                    <td class="hidden whitespace-nowrap text-muted lg:table-cell">{{ $manager->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                    <td class="text-right">
                        <x-button :href="route('admin.event-managers.edit', $manager)" variant="secondary" size="sm">Manage</x-button>
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="6" icon="users" title="No event managers yet">
                    Add someone and pick the WordCamps they look after. They sign in at
                    <span class="break-all font-medium text-ink">{{ route('manager.login') }}</span>.
                </x-table.empty>
            @endforelse
        </x-table>

        @if ($managers->hasPages())
            <x-slot:footer>{{ $managers->links() }}</x-slot:footer>
        @endif
    </x-card>

    <p class="mt-4 text-xs text-muted">
        Sign-in page for managers: <span class="break-all font-medium text-ink">{{ route('manager.login') }}</span>.
        They can edit an event's details, event information and quests &amp; checklist — never its status.
    </p>
</x-app-layout>
