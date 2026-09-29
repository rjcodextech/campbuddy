{{--
    One deal in an admin list: logo, name + website, headline, the badges
    that say how it behaves, and the actions passed in the slot.

      <x-admin.deal-row :offer="$offer" :muted="! $offer->is_active">…actions…</x-admin.deal-row>

    Extra badges go in <x-slot:badges>.
--}}
@props(['offer', 'muted' => false])

<div @class(['flex flex-wrap gap-4 px-5 py-4 sm:flex-nowrap sm:px-6', 'bg-paper/60' => $muted])>
    <span class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-line bg-white" aria-hidden="true">
        @if ($offer->mediaAsset)
            <img src="{{ $offer->mediaAsset->url() }}" alt="" class="max-h-11 max-w-11 object-contain">
        @elseif ($offer->icon)
            <span class="text-2xl">{{ $offer->icon }}</span>
        @else
            <x-icon name="tag" class="h-6 w-6 text-muted" />
        @endif
    </span>

    <div class="min-w-0 flex-1 basis-52">
        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
            <span class="font-semibold">{{ $offer->displayName() }}</span>
            @if ($offer->displayWebsite())
                <span class="text-xs text-muted">{{ $offer->displayWebsite() }}</span>
            @endif
            @if ($offer->highlight)
                <x-badge variant="brand">{{ $offer->highlight }}</x-badge>
            @endif
        </div>
        @if ($offer->brand)
            <p class="mt-0.5 text-sm font-medium">{{ $offer->title }}</p>
        @endif
        <p class="mt-0.5 text-sm text-muted">{{ $offer->description }}</p>

        <div class="mt-2 flex flex-wrap gap-1.5">
            @unless ($offer->is_active)
                <x-badge>Off</x-badge>
            @endunless
            <x-badge variant="info">{{ $offer->opens_in_app ? 'Opens in the app' : 'Opens in a new tab' }}</x-badge>
            @if ($offer->capture_leads)
                <x-badge variant="warning">Contact form first</x-badge>
            @endif
            @if ($offer->coupon_code)
                <x-badge>Code: {{ $offer->coupon_code }}</x-badge>
            @endif
            {{ $badges ?? '' }}
        </div>
    </div>

    <div class="flex w-full shrink-0 flex-wrap items-start justify-end gap-2 sm:w-auto">
        {{ $slot }}
    </div>
</div>
