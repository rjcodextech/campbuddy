<x-app-layout title="Deals" :subtitle="$event->display_name"
              :breadcrumbs="[['Events', route('admin.events.index')], [$event->display_name, route('admin.events.edit', $event)], ['Deals']]">
    <x-slot:actions>
        <x-button :href="route('admin.media.index')" variant="secondary" icon="photo">Media Library</x-button>
        <x-button :href="route('admin.events.offers.create', $event)" icon="plus">Add a deal</x-button>
    </x-slot:actions>

    <x-admin.event-nav :event="$event" current="offers" />

    <div class="max-w-5xl space-y-6">
        <x-alert type="info">
            A deal can ask for a short <strong>contact form</strong> before it opens — you choose which fields
            (name, company, email, phone) and can add products to tick. Answers appear under
            <a href="{{ route('admin.events.deal-leads.index', $event) }}" class="font-medium underline">Deal leads</a>.
            Referral and affiliate links should open <strong>in a new tab</strong> so the sponsor gets the credit.
        </x-alert>

        <x-card title="This event's deals" description="Shown first on Explore → Deals while the event is active." flush>
            @if ($offers->isEmpty())
                <div class="px-5 py-12 text-center sm:px-6">
                    <x-icon name="tag" class="mx-auto h-8 w-8 text-muted/50" />
                    <p class="mt-2 text-sm font-medium">No deals of its own yet</p>
                    <p class="mx-auto mt-1 max-w-sm text-sm text-muted">Add a sponsor offer and it will show up for attendees.</p>
                    <div class="mt-4">
                        <x-button :href="route('admin.events.offers.create', $event)" icon="plus" size="sm">Add a deal</x-button>
                    </div>
                </div>
            @else
                <ul class="divide-y divide-line">
                    @foreach ($offers as $offer)
                        <li>
                            <x-admin.deal-row :offer="$offer" :muted="! $offer->is_active">
                                <x-slot:badges>
                                    <x-badge>Order {{ $offer->sort_order }}</x-badge>
                                    @if ($offer->leads_count)
                                        <x-badge variant="success">{{ $offer->leads_count }} {{ \Illuminate\Support\Str::plural('lead', $offer->leads_count) }}</x-badge>
                                    @endif
                                </x-slot:badges>

                                <x-button :href="route('admin.events.offers.edit', [$event, $offer])" variant="secondary" size="sm" icon="pencil">Edit</x-button>
                                <x-action-form :action="route('admin.events.offers.destroy', [$event, $offer])" method="DELETE"
                                               variant="danger-outline" size="sm" icon="trash"
                                               :confirm="'Remove “'.$offer->displayName().'”'.($offer->leads_count ? ' and its '.$offer->leads_count.' leads' : '').'?'">Remove</x-action-form>
                            </x-admin.deal-row>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        <x-card title="Default deals" flush
                description="Made once under Default deals and shown at every event in their countries, after this event's own. Hide one here if it clashes with a sponsor.">
            @if ($defaults->isEmpty())
                <div class="px-5 py-8 text-center text-sm text-muted sm:px-6">
                    No default deal covers this event's country.
                    <a href="{{ route('admin.deals.index') }}" class="font-medium text-maroon underline">Manage default deals</a>
                </div>
            @else
                <ul class="divide-y divide-line">
                    @foreach ($defaults as $offer)
                        <li>
                            <x-admin.deal-row :offer="$offer" :muted="$offer->hidden_here || ! $offer->is_active">
                                <x-slot:badges>
                                    <x-badge>{{ $offer->countriesLabel() }}</x-badge>
                                    @if ($offer->hidden_here)
                                        <x-badge variant="danger">Hidden at this event</x-badge>
                                    @endif
                                </x-slot:badges>

                                <x-button :href="route('admin.deals.edit', $offer)" variant="link" size="sm" class="px-2">Edit for all events</x-button>
                                <form method="POST" action="{{ route('admin.events.offers.visibility', [$event, $offer]) }}">
                                    @csrf
                                    <input type="hidden" name="hidden" value="{{ $offer->hidden_here ? 0 : 1 }}">
                                    <x-button variant="secondary" size="sm" :icon="$offer->hidden_here ? 'eye' : 'eye-off'">
                                        {{ $offer->hidden_here ? 'Show here' : 'Hide here' }}
                                    </x-button>
                                </form>
                            </x-admin.deal-row>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
</x-app-layout>
