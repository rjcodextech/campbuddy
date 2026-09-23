<x-app-layout title="Media Library" subtitle="Logos and images you can attach to sponsor deals."
              :breadcrumbs="[['Media Library']]">
    <div class="space-y-6">
        <form method="POST" action="{{ route('admin.media.store') }}" enctype="multipart/form-data">
            @csrf

            <x-card title="Upload an image">
                <x-form.file name="file" label="Image" required accept="image/png,image/jpeg,image/webp,image/svg+xml"
                             hint="JPG, PNG, WebP or SVG — up to 5 MB." class="max-w-xl" />

                <x-slot:footer>
                    <x-button icon="upload">Upload</x-button>
                </x-slot:footer>
            </x-card>
        </form>

        <x-card title="Library" :description="$assets->total().' '.\Illuminate\Support\Str::plural('image', $assets->total())">
            @if ($assets->isEmpty())
                <div class="py-8 text-center">
                    <x-icon name="photo" class="mx-auto h-8 w-8 text-muted/50" />
                    <p class="mt-2 text-sm font-medium">No images uploaded yet</p>
                    <p class="mx-auto mt-1 max-w-sm text-sm text-muted">Upload a sponsor logo above, then choose it when you add a deal.</p>
                </div>
            @else
                <ul class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6">
                    @foreach ($assets as $asset)
                        <li class="overflow-hidden rounded-lg border border-line bg-white">
                            <div class="flex aspect-square items-center justify-center bg-paper-soft p-2">
                                <img src="{{ $asset->url() }}" alt="{{ $asset->filename }}" loading="lazy" class="max-h-full max-w-full object-contain">
                            </div>
                            <div class="border-t border-line px-2.5 py-2">
                                <p class="truncate text-xs font-medium" title="{{ $asset->filename }}">{{ $asset->filename }}</p>
                                <p class="text-[11px] text-muted">{{ \Illuminate\Support\Number::fileSize((int) $asset->size) }} · {{ $asset->created_at->format('j M Y') }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>

                @if ($assets->hasPages())
                    <div class="mt-6">{{ $assets->links() }}</div>
                @endif
            @endif
        </x-card>
    </div>
</x-app-layout>
