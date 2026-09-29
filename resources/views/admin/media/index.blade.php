<x-app-layout title="Media Library" subtitle="Logos for deals and Free Steals. An image nothing uses can be deleted."
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
            {{-- All / In use / Not used: plain links, so a filtered view can be bookmarked. --}}
            <nav class="mb-4 flex flex-wrap gap-2" aria-label="Show">
                @foreach (\App\Http\Controllers\Admin\MediaController::FILTERS as $key => $label)
                    <a href="{{ route('admin.media.index', $key === 'all' ? [] : ['show' => $key]) }}"
                       @if ($show === $key) aria-current="page" @endif
                       @class(['inline-flex min-h-9 items-center rounded-full px-3 text-sm font-medium ring-1',
                               'bg-maroon text-white ring-maroon' => $show === $key,
                               'bg-white text-muted ring-line hover:text-ink' => $show !== $key])>{{ $label }} ({{ $counts[$key] }})</a>
                @endforeach
            </nav>

            @if ($assets->isEmpty() && $show !== 'all')
                <p class="py-8 text-center text-sm text-muted">{{ $show === 'used' ? 'No image is in use.' : 'Every image is in use.' }}</p>
            @elseif ($assets->isEmpty())
                <div class="py-8 text-center">
                    <x-icon name="photo" class="mx-auto h-8 w-8 text-muted/50" />
                    <p class="mt-2 text-sm font-medium">No images uploaded yet</p>
                    <p class="mx-auto mt-1 max-w-sm text-sm text-muted">Upload a sponsor logo above, then choose it when you add a deal.</p>
                </div>
            @else
                <ul class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6">
                    @foreach ($assets as $asset)
                        @php
                            // Where it's used: "Hostinger (deal, default)", "GoDAM (Free Steal)".
                            $uses = $asset->offers->toBase()->map(fn ($deal) => $deal->displayName().' (deal, '.($deal->event?->display_name ?? 'default').')')
                                ->merge($asset->freeSteals->map(fn ($steal) => $steal->name.' (Free Steal)'));
                        @endphp
                        <li class="overflow-hidden rounded-lg border border-line bg-white">
                            <div class="flex aspect-square items-center justify-center bg-paper-soft p-2">
                                <img src="{{ $asset->url() }}" alt="{{ $asset->filename }}" loading="lazy" class="max-h-full max-w-full object-contain">
                            </div>
                            <div class="border-t border-line px-2.5 py-2">
                                <p class="truncate text-xs font-medium" title="{{ $asset->filename }}">{{ $asset->filename }}</p>
                                <p class="text-[11px] text-muted">{{ \Illuminate\Support\Number::fileSize((int) $asset->size) }} · {{ $asset->created_at->format('j M Y') }}</p>

                                @if ($uses->isNotEmpty())
                                    <div class="mt-1.5"><x-badge variant="success">In use · {{ $uses->count() }}</x-badge></div>
                                    <p class="mt-1 text-[11px] leading-snug text-muted" title="{{ $uses->implode(', ') }}">
                                        {{ $uses->take(3)->implode(', ') }}@if ($uses->count() > 3) +{{ $uses->count() - 3 }} more @endif
                                    </p>
                                @else
                                    <div class="mt-1.5 flex flex-wrap items-center justify-between gap-2">
                                        <x-badge>Not used</x-badge>
                                        <x-action-form :action="route('admin.media.destroy', $asset)" method="DELETE" variant="danger-outline" size="sm" icon="trash"
                                                       :confirm="'Delete “'.$asset->filename.'”? The file is removed for good.'"
                                                       :aria-label="'Delete '.$asset->filename">Delete</x-action-form>
                                    </div>
                                @endif
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
