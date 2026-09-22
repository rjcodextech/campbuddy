<x-app-layout :title="'Offers — '.$event->display_name">
    <div class="max-w-3xl space-y-6">
        @if (session('status'))
            <div class="p-4 bg-teal/10 text-teal rounded-md">{{ session('status') }}</div>
        @endif

        <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-line p-6">
            <h3 class="text-lg font-medium text-ink mb-4">Sponsor offers / deals</h3>

            <div class="space-y-3 mb-6">
                @forelse ($offers as $offer)
                    <form method="POST" action="{{ route('admin.events.offers.update', [$event, $offer]) }}" class="grid grid-cols-6 gap-2 items-center border border-line rounded-md p-3">
                        @csrf
                        @method('PUT')
                        <input type="text" name="icon" value="{{ $offer->icon }}" maxlength="10" class="col-span-1 border-line rounded-md text-sm text-center">
                        <input type="text" name="title" value="{{ $offer->title }}" required class="col-span-2 border-line rounded-md text-sm">
                        <input type="url" name="url" value="{{ $offer->url }}" required class="col-span-2 border-line rounded-md text-sm">
                        <label class="col-span-1 flex items-center gap-1 text-xs text-muted">
                            <input type="checkbox" name="is_active" value="1" @checked($offer->is_active)> Active
                        </label>
                        <input type="text" name="description" value="{{ $offer->description }}" required class="col-span-5 border-line rounded-md text-sm" placeholder="Description">
                        <button type="submit" class="col-span-1 text-xs font-medium text-maroon hover:text-maroon-dark">Save</button>
                    </form>
                    <form method="POST" action="{{ route('admin.events.offers.destroy', [$event, $offer]) }}" class="-mt-2 mb-2"
                          onsubmit="return confirm('Remove this offer?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-xs text-muted hover:text-maroon">Remove</button>
                    </form>
                @empty
                    <p class="text-sm text-muted">No offers yet.</p>
                @endforelse
            </div>

            <form method="POST" action="{{ route('admin.events.offers.store', $event) }}" class="grid grid-cols-6 gap-2 items-center border-t border-line pt-4">
                @csrf
                <input type="text" name="icon" value="🏷" maxlength="10" class="col-span-1 border-line rounded-md text-sm text-center">
                <input type="text" name="title" placeholder="Sponsor / title" required class="col-span-2 border-line rounded-md text-sm">
                <input type="url" name="url" placeholder="https://…" required class="col-span-3 border-line rounded-md text-sm">
                <input type="text" name="description" placeholder="Description" required class="col-span-5 border-line rounded-md text-sm">
                <button type="submit" class="col-span-1 px-2 py-1.5 bg-maroon text-white text-xs font-medium rounded-md hover:bg-maroon-dark">Add</button>
            </form>
        </div>
    </div>
</x-app-layout>
