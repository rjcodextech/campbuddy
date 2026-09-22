<x-app-layout title="Events">
    <x-slot name="actions">
        <form method="POST" action="{{ route('admin.events.discover') }}" class="inline">
            @csrf
            <button type="submit" class="inline-flex items-center px-4 py-2 bg-paper-soft text-ink text-sm font-medium rounded-md hover:bg-line">
                Discover WordCamps
            </button>
        </form>
        <a href="{{ route('admin.events.create') }}"
           class="inline-flex items-center px-4 py-2 bg-maroon text-white text-sm font-medium rounded-md hover:bg-maroon-dark">
            Add event
        </a>
    </x-slot>

    @if (session('status'))
        <div class="mb-4 p-4 bg-teal/10 text-teal rounded-md">{{ session('status') }}</div>
    @endif

    <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-line">
        <table class="min-w-full divide-y divide-line">
            <thead class="bg-paper-soft">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-muted uppercase">Event</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-muted uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-muted uppercase">Visible</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-muted uppercase">Roster</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($events as $event)
                    <tr>
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-ink">{{ $event->display_name }}</div>
                            <div class="text-sm text-muted">{{ $event->slug }}</div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 text-xs rounded-full bg-paper-soft text-ink">{{ $event->status }}</span>
                        </td>
                        <td class="px-6 py-4 text-sm text-ink">{{ $event->is_visible ? 'Yes' : 'No' }}</td>
                        <td class="px-6 py-4 text-sm text-ink">{{ $event->attendee_roster_count }}</td>
                        <td class="px-6 py-4 text-right text-sm">
                            <a href="{{ route('admin.events.edit', $event) }}" class="text-maroon hover:text-maroon-dark font-medium">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-sm text-muted">
                            No events yet. Add WordCamp Rajasthan 2026 to get started (§2.1 F2).
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $events->links() }}</div>
</x-app-layout>
