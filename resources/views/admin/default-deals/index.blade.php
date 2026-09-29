<x-app-layout title="Default deals" subtitle="Shown at every event in their countries — today's and every one found later"
              :breadcrumbs="[['Default deals']]">
    <x-slot:actions>
        <x-button :href="route('admin.media.index')" variant="secondary" icon="photo">Media Library</x-button>
        <x-button :href="route('admin.deals.create')" icon="plus">Add a default deal</x-button>
    </x-slot:actions>

    <div class="max-w-5xl space-y-6">
        <x-alert type="info">
            A default deal is made once and shows on <strong>Explore → Deals</strong> at every event in its countries,
            after the event's own sponsor deals. Newly discovered events get it automatically. Editing it here changes it
            everywhere; an event can hide one from its own Deals page. Leads remember which event they came from.
        </x-alert>

        <x-card title="Default deals" flush>
            @if ($deals->isEmpty())
                <div class="px-5 py-12 text-center sm:px-6">
                    <x-icon name="tag" class="mx-auto h-8 w-8 text-muted/50" />
                    <p class="mt-2 text-sm font-medium">No default deals yet</p>
                    <p class="mx-auto mt-1 max-w-sm text-sm text-muted">Add one, pick its countries, and every event there shows it.</p>
                </div>
            @else
                <ul class="divide-y divide-line">
                    @foreach ($deals as $deal)
                        <li>
                            <x-admin.deal-row :offer="$deal" :muted="! $deal->is_active">
                                <x-slot:badges>
                                    <x-badge>{{ $deal->countriesLabel() }}</x-badge>
                                    <x-badge>{{ $reach[$deal->id] }} upcoming {{ \Illuminate\Support\Str::plural('event', $reach[$deal->id]) }}</x-badge>
                                    @if ($deal->hidden_at_events_count)
                                        <x-badge variant="danger">Hidden at {{ $deal->hidden_at_events_count }}</x-badge>
                                    @endif
                                    <x-badge>Order {{ $deal->sort_order }}</x-badge>
                                </x-slot:badges>

                                @if ($deal->capture_leads || $deal->leads_count)
                                    <x-button :href="route('admin.deals.leads', $deal)" variant="secondary" size="sm" icon="inbox">Leads ({{ $deal->leads_count }})</x-button>
                                @endif
                                <x-button :href="route('admin.deals.edit', $deal)" variant="secondary" size="sm" icon="pencil">Edit</x-button>
                                <x-action-form :action="route('admin.deals.destroy', $deal)" method="DELETE"
                                               variant="danger-outline" size="sm" icon="trash"
                                               :confirm="'Remove “'.$deal->displayName().'” from every event'.($deal->leads_count ? ', with its '.$deal->leads_count.' leads' : '').'?'">Remove</x-action-form>
                            </x-admin.deal-row>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
</x-app-layout>
