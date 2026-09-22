<x-app-layout :title="'Quests — '.$event->display_name">
    <div class="max-w-3xl space-y-6">
        @if (session('status'))
            <div class="p-4 bg-teal/10 text-teal rounded-md">{{ session('status') }}</div>
        @endif

        <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-line p-6">
            <h3 class="text-lg font-medium text-ink mb-4">Event-specific quests</h3>

            <div class="space-y-3 mb-6">
                @forelse ($quests as $quest)
                    <form method="POST" action="{{ route('admin.events.quests.update', [$event, $quest]) }}" class="flex items-start gap-3 border border-line rounded-md p-3">
                        @csrf
                        @method('PUT')
                        <input type="text" name="title" value="{{ $quest->title }}" required class="flex-1 border-line rounded-md text-sm">
                        <input type="text" name="description" value="{{ $quest->description }}" placeholder="Description" class="flex-[2] border-line rounded-md text-sm">
                        <label class="flex items-center gap-1 text-xs text-muted whitespace-nowrap">
                            <input type="checkbox" name="is_active" value="1" @checked($quest->is_active)> Active
                        </label>
                        <button type="submit" class="text-xs font-medium text-maroon hover:text-maroon-dark whitespace-nowrap">Save</button>
                    </form>
                    <form method="POST" action="{{ route('admin.events.quests.destroy', [$event, $quest]) }}" class="-mt-2 mb-2"
                          onsubmit="return confirm('Remove this quest?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-xs text-muted hover:text-maroon">Remove</button>
                    </form>
                @empty
                    <p class="text-sm text-muted">No event-specific quests yet — default quests still show to attendees.</p>
                @endforelse
            </div>

            <form method="POST" action="{{ route('admin.events.quests.store', $event) }}" class="flex items-start gap-3 border-t border-line pt-4">
                @csrf
                <input type="text" name="title" placeholder="New quest title" required class="flex-1 border-line rounded-md text-sm">
                <input type="text" name="description" placeholder="Description (optional)" class="flex-[2] border-line rounded-md text-sm">
                <button type="submit" class="px-3 py-1.5 bg-maroon text-white text-xs font-medium rounded-md hover:bg-maroon-dark whitespace-nowrap">Add</button>
            </form>
        </div>
    </div>
</x-app-layout>
