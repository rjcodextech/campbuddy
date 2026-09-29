<x-app-layout title="Free Steals" subtitle="Hand-picked free WordPress plugins and tools, the same at every event"
              :breadcrumbs="[['Free Steals']]">
    <x-slot:actions>
        <x-button :href="route('admin.free-steals.create')" icon="plus">Add a Free Steal</x-button>
    </x-slot:actions>

    <div class="max-w-5xl space-y-6">
        <x-alert type="info">
            These show on <strong>Explore → Free Steals</strong> at every event. Attendees see the first
            {{ \App\Models\FreeSteal::SHOWN }} that are switched on, in this order, so keep the rest here and swap them in
            when you like. <strong>Featured</strong> means the team finds it especially worth a look, never paid placement.
            Mix makers, so a small one sits beside a big one.
        </x-alert>

        <x-card :title="'Free Steals ('.$shownIds->count().' of '.\App\Models\FreeSteal::SHOWN.' showing)'" flush>
            @if ($steals->isEmpty())
                <div class="px-5 py-12 text-center sm:px-6">
                    <x-icon name="download" class="mx-auto h-8 w-8 text-muted/50" />
                    <p class="mt-2 text-sm font-medium">No Free Steals yet</p>
                    <p class="mx-auto mt-1 max-w-sm text-sm text-muted">Add a free plugin or tool worth discovering.</p>
                </div>
            @else
                <ul class="divide-y divide-line">
                    @foreach ($steals as $steal)
                        @php($shown = $shownIds->contains($steal->id))
                        <li @class(['flex flex-wrap gap-4 px-5 py-4 sm:flex-nowrap sm:px-6', 'bg-paper/60' => ! $shown])>
                            <div class="min-w-0 flex-1 basis-52">
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                    <span class="font-semibold">{{ $steal->name }}</span>
                                    <span class="text-xs text-muted">by {{ $steal->maker }}</span>
                                    @if ($steal->is_featured)
                                        <x-badge variant="brand">Featured</x-badge>
                                    @endif
                                </div>
                                <p class="mt-0.5 text-sm text-muted">{{ $steal->description }}</p>

                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    @if ($shown)
                                        <x-badge variant="success">Showing</x-badge>
                                    @elseif ($steal->is_active)
                                        <x-badge variant="warning">On, but past the first {{ \App\Models\FreeSteal::SHOWN }}</x-badge>
                                    @else
                                        <x-badge>Off</x-badge>
                                    @endif
                                    <x-badge variant="info">{{ $steal->category }}</x-badge>
                                    <x-badge>{{ $steal->linkHost() }}</x-badge>
                                    <x-badge>Order {{ $steal->sort_order }}</x-badge>
                                </div>
                            </div>

                            <div class="flex w-full shrink-0 flex-wrap items-start justify-end gap-2 sm:w-auto">
                                <x-button :href="$steal->url" variant="secondary" size="sm" icon="external" target="_blank" rel="noopener">Open</x-button>
                                <x-button :href="route('admin.free-steals.edit', $steal)" variant="secondary" size="sm" icon="pencil">Edit</x-button>
                                <x-action-form :action="route('admin.free-steals.destroy', $steal)" method="DELETE"
                                               variant="danger-outline" size="sm" icon="trash"
                                               :confirm="'Remove “'.$steal->name.'”?'">Remove</x-action-form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
</x-app-layout>
