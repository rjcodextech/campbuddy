<x-app-layout title="Media Library">
    <div class="space-y-6">
        @if (session('status'))
            <div class="p-4 bg-teal/10 text-teal rounded-md">{{ session('status') }}</div>
        @endif

        <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-line p-6">
            <form method="POST" action="{{ route('admin.media.store') }}" enctype="multipart/form-data" class="flex items-end gap-3">
                @csrf
                <div class="flex-1">
                    <x-input-label for="file" value="Upload image (jpg, png, webp, svg — max 5MB)" />
                    <input id="file" name="file" type="file" accept="image/png,image/jpeg,image/webp,image/svg+xml" required class="mt-1 block w-full text-sm">
                    <x-input-error :messages="$errors->get('file')" class="mt-1" />
                </div>
                <button type="submit" class="px-4 py-2 bg-maroon text-white text-sm font-medium rounded-md hover:bg-maroon-dark">
                    Upload
                </button>
            </form>
        </div>

        <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-line p-6">
            @if ($assets->isEmpty())
                <p class="text-sm text-muted">No images uploaded yet.</p>
            @else
                <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-4">
                    @foreach ($assets as $asset)
                        <div class="border border-line rounded-md overflow-hidden">
                            <div class="aspect-square bg-paper-soft flex items-center justify-center">
                                <img src="{{ $asset->url() }}" alt="{{ $asset->filename }}" class="max-w-full max-h-full object-contain">
                            </div>
                            <p class="text-xs text-muted p-2 truncate" title="{{ $asset->filename }}">{{ $asset->filename }}</p>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4">{{ $assets->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
