<x-app-layout :title="'Offers — '.$event->display_name">
    <div class="max-w-4xl space-y-6">
        @if (session('status'))
            <div class="p-4 bg-teal/10 text-teal rounded-md">{{ session('status') }}</div>
        @endif

        <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-line p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-ink">Sponsor offers / deals</h3>
                <a href="{{ route('admin.media.index') }}" class="text-xs text-maroon hover:text-maroon-dark font-medium">Upload a logo in the Media Library →</a>
            </div>

            <div class="space-y-3 mb-6">
                @forelse ($offers as $offer)
                    <form method="POST" action="{{ route('admin.events.offers.update', [$event, $offer]) }}" class="border border-line rounded-md p-3">
                        @csrf
                        @method('PUT')
                        <div class="flex gap-3 items-start">
                            <div class="w-14 h-14 shrink-0 border border-line rounded-md bg-paper-soft flex items-center justify-center overflow-hidden">
                                @if ($offer->mediaAsset)
                                    <img src="{{ $offer->mediaAsset->url() }}" alt="" class="max-w-full max-h-full object-contain">
                                @else
                                    <span class="text-xl">{{ $offer->icon }}</span>
                                @endif
                            </div>
                            <div class="flex-1 grid grid-cols-6 gap-2">
                                <input type="text" name="icon" value="{{ $offer->icon }}" maxlength="10" class="col-span-1 border-line rounded-md text-sm text-center" title="Emoji fallback (used when no logo is chosen)">
                                <select name="media_asset_id" class="col-span-2 border-line rounded-md text-sm">
                                    <option value="">No logo (use emoji)</option>
                                    @foreach ($mediaAssets as $asset)
                                        <option value="{{ $asset->id }}" @selected($offer->media_asset_id === $asset->id)>{{ $asset->filename }}</option>
                                    @endforeach
                                </select>
                                <input type="text" name="title" value="{{ $offer->title }}" required class="col-span-2 border-line rounded-md text-sm">
                                <label class="col-span-1 flex items-center gap-1 text-xs text-muted">
                                    <input type="checkbox" name="is_active" value="1" @checked($offer->is_active)> Active
                                </label>
                                <input type="url" name="url" value="{{ $offer->url }}" required class="col-span-3 border-line rounded-md text-sm">
                                <input type="text" name="description" value="{{ $offer->description }}" required class="col-span-3 border-line rounded-md text-sm" placeholder="Description">
                            </div>
                            <button type="submit" class="text-xs font-medium text-maroon hover:text-maroon-dark self-center">Save</button>
                        </div>
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
                <select name="media_asset_id" class="col-span-2 border-line rounded-md text-sm">
                    <option value="">No logo (use emoji)</option>
                    @foreach ($mediaAssets as $asset)
                        <option value="{{ $asset->id }}">{{ $asset->filename }}</option>
                    @endforeach
                </select>
                <input type="text" name="title" placeholder="Sponsor / title" required class="col-span-3 border-line rounded-md text-sm">
                <input type="url" name="url" placeholder="https://…" required class="col-span-4 border-line rounded-md text-sm">
                <input type="text" name="description" placeholder="Description" required class="col-span-1 border-line rounded-md text-sm">
                <button type="submit" class="col-span-1 px-2 py-1.5 bg-maroon text-white text-xs font-medium rounded-md hover:bg-maroon-dark">Add</button>
            </form>
        </div>
    </div>
</x-app-layout>
