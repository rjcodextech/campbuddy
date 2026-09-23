@php
    $assetOptions = $mediaAssets->pluck('filename', 'id')->all();
@endphp

<x-app-layout title="Deals" :subtitle="$event->display_name"
              :breadcrumbs="[['Events', route('admin.events.index')], [$event->display_name, route('admin.events.edit', $event)], ['Deals']]">
    <x-slot:actions>
        <x-button :href="route('admin.media.index')" variant="secondary" icon="photo">Media Library</x-button>
    </x-slot:actions>

    <x-admin.event-nav :event="$event" current="offers" />

    <div class="max-w-5xl space-y-6">
        <x-alert type="info">
            <strong>Require contact info</strong> asks an attendee for their name, email and (optionally) mobile number
            before that deal opens — useful when a sponsor wants to follow up. Leave it off for deals that should just
            link straight out. Captured details appear under <a href="{{ route('admin.events.deal-leads.index', $event) }}" class="font-medium underline">Deal leads</a>.
        </x-alert>

        <x-card title="Sponsor deals" description="Shown on Explore → Deals while the event is active." flush>
            @if ($offers->isEmpty())
                <div class="px-5 py-12 text-center sm:px-6">
                    <x-icon name="tag" class="mx-auto h-8 w-8 text-muted/50" />
                    <p class="mt-2 text-sm font-medium">No deals yet</p>
                    <p class="mx-auto mt-1 max-w-sm text-sm text-muted">Add a sponsor offer below and it will show up for attendees.</p>
                </div>
            @else
                <ul class="divide-y divide-line">
                    @foreach ($offers as $offer)
                        <li @class(['px-5 py-4 sm:px-6', 'bg-paper/60' => ! $offer->is_active])>
                            <div class="flex gap-4">
                                <div class="hidden h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-line bg-paper-soft sm:flex" aria-hidden="true">
                                    @if ($offer->mediaAsset)
                                        <img src="{{ $offer->mediaAsset->url() }}" alt="" class="max-h-full max-w-full object-contain">
                                    @else
                                        <span class="text-2xl">{{ $offer->icon }}</span>
                                    @endif
                                </div>

                                <div class="grid min-w-0 flex-1 gap-3 md:grid-cols-[minmax(0,1fr)_auto] md:items-end">
                                    <form method="POST" action="{{ route('admin.events.offers.update', [$event, $offer]) }}"
                                          class="grid gap-3 md:grid-cols-12 md:items-end">
                                        @csrf
                                        @method('PUT')

                                        <x-form.input name="sort_order" type="number" min="0" label="Order" class="md:col-span-2"
                                                      :id="'offer-order-'.$offer->id" :value="$offer->sort_order" :use-old="false" :show-error="false" />
                                        <x-form.input name="title" label="Title" required class="md:col-span-4"
                                                      :id="'offer-title-'.$offer->id" :value="$offer->title" :use-old="false" :show-error="false" />
                                        <x-form.select name="media_asset_id" label="Logo" placeholder="No logo (use emoji)" class="md:col-span-4"
                                                       :id="'offer-logo-'.$offer->id" :options="$assetOptions" :value="$offer->media_asset_id" :use-old="false" :show-error="false" />
                                        <x-form.input name="icon" label="Emoji" maxlength="10" class="md:col-span-2"
                                                      :id="'offer-icon-'.$offer->id" :value="$offer->icon" :use-old="false" :show-error="false"
                                                      title="Fallback shown when no logo is chosen" />

                                        <x-form.input name="url" type="url" label="Link" required class="md:col-span-6"
                                                      :id="'offer-url-'.$offer->id" :value="$offer->url" :use-old="false" :show-error="false" />
                                        <x-form.input name="description" label="Description" required class="md:col-span-6"
                                                      :id="'offer-desc-'.$offer->id" :value="$offer->description" :use-old="false" :show-error="false" />

                                        <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-3 md:col-span-12">
                                            <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                                                <x-form.checkbox name="is_active" label="Active" unchecked="0"
                                                                 :id="'offer-active-'.$offer->id" :checked="$offer->is_active" :use-old="false" :show-error="false" />
                                                <x-form.checkbox name="capture_leads" label="Require contact info" unchecked="0"
                                                                 :id="'offer-leads-'.$offer->id" :checked="$offer->capture_leads" :use-old="false" :show-error="false" />
                                            </div>
                                            <x-button variant="secondary" size="sm">Save</x-button>
                                        </div>
                                    </form>

                                    <x-action-form :action="route('admin.events.offers.destroy', [$event, $offer])" method="DELETE"
                                                   variant="danger-outline" size="sm" icon="trash" class="md:pb-0.5"
                                                   :confirm="'Remove “'.$offer->title.'”?'">Remove</x-action-form>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        <form method="POST" action="{{ route('admin.events.offers.store', $event) }}">
            @csrf

            <x-card title="Add a deal">
                {{-- Ignores old()/inline errors: a failed *row* update shares these field names. The page-level summary reports it. --}}
                <div class="grid gap-4 md:grid-cols-12">
                    <x-form.input name="title" label="Sponsor / title" required class="md:col-span-5" id="new-offer-title" :use-old="false" :show-error="false" />
                    <x-form.select name="media_asset_id" label="Logo" placeholder="No logo (use emoji)" class="md:col-span-5"
                                   id="new-offer-logo" :options="$assetOptions" :use-old="false" :show-error="false" />
                    <x-form.input name="icon" label="Emoji" maxlength="10" value="🏷" class="md:col-span-2" id="new-offer-icon" :use-old="false" :show-error="false" />

                    <x-form.input name="url" type="url" label="Link" required placeholder="https://…" class="md:col-span-6" id="new-offer-url" :use-old="false" :show-error="false" />
                    <x-form.input name="description" label="Description" required class="md:col-span-6" id="new-offer-desc" :use-old="false" :show-error="false" />

                    <div class="md:col-span-12">
                        <x-form.checkbox name="capture_leads" label="Require contact info before the deal opens" unchecked="0"
                                         id="new-offer-leads" :use-old="false" :show-error="false" />
                    </div>
                </div>

                <x-slot:footer>
                    <x-button icon="plus">Add deal</x-button>
                </x-slot:footer>
            </x-card>
        </form>
    </div>
</x-app-layout>
