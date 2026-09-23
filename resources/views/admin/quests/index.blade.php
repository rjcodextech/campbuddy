<x-app-layout title="Quests & checklist" :subtitle="$event->display_name"
              :breadcrumbs="[['Events', route('admin.events.index')], [$event->display_name, route('admin.events.edit', $event)], ['Quests & checklist']]">
    <x-admin.event-nav :event="$event" current="quests" />

    <div class="max-w-4xl space-y-6">
        <x-alert type="info">
            Every event starts with a <strong>pre-trip checklist</strong> — the items below. Rename, reorder, hide
            (untick <em>Active</em>) or remove any of them for this event. The eight <em>Things to do</em> cards at the
            top of the Quest tab are the same for every event and aren't edited here.
        </x-alert>

        <x-card title="Checklist" description="Attendees tick these off on their own phone. Lower numbers appear first." flush>
            @if ($quests->isEmpty())
                <div class="px-5 py-12 text-center sm:px-6">
                    <x-icon name="inbox" class="mx-auto h-8 w-8 text-muted/50" />
                    <p class="mt-2 text-sm font-medium">No checklist items</p>
                    <p class="mx-auto mt-1 max-w-sm text-sm text-muted">Attendees will only see the built-in Things to do cards. Add an item below.</p>
                </div>
            @else
                <ul class="divide-y divide-line">
                    @foreach ($quests as $quest)
                        <li @class(['px-5 py-4 sm:px-6', 'bg-paper/60' => ! $quest->is_active])>
                            <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_auto] md:items-end">
                                <form method="POST" action="{{ route('admin.events.quests.update', [$event, $quest]) }}"
                                      class="grid gap-3 md:grid-cols-[4.5rem_minmax(0,2fr)_minmax(0,1.3fr)] md:items-end">
                                    @csrf
                                    @method('PUT')

                                    <x-form.input name="sort_order" type="number" min="0" label="Order"
                                                  :id="'quest-order-'.$quest->id" :value="$quest->sort_order" :use-old="false" :show-error="false" />
                                    <x-form.input name="title" label="Title" required
                                                  :id="'quest-title-'.$quest->id" :value="$quest->title" :use-old="false" :show-error="false" />
                                    <x-form.input name="description" label="Description" placeholder="Optional"
                                                  :id="'quest-desc-'.$quest->id" :value="$quest->description" :use-old="false" :show-error="false" />

                                    <div class="flex items-center justify-between gap-3 md:col-span-3">
                                        <x-form.checkbox name="is_active" label="Active — shown to attendees" unchecked="0"
                                                         :id="'quest-active-'.$quest->id" :checked="$quest->is_active" :use-old="false" :show-error="false" />
                                        <x-button variant="secondary" size="sm">Save</x-button>
                                    </div>
                                </form>

                                <x-action-form :action="route('admin.events.quests.destroy', [$event, $quest])" method="DELETE"
                                               variant="danger-outline" size="sm" icon="trash" class="md:pb-0.5"
                                               :confirm="'Remove “'.$quest->title.'”?'">Remove</x-action-form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        <form method="POST" action="{{ route('admin.events.quests.store', $event) }}">
            @csrf

            <x-card title="Add a checklist item">
                <div class="grid gap-4 md:grid-cols-2">
                    {{-- Ignores old()/inline errors: a failed *row* update shares these field names. The page-level summary reports it. --}}
                    <x-form.input name="title" label="Title" required placeholder="e.g. Bring business cards" id="new-quest-title" :use-old="false" :show-error="false" />
                    <x-form.input name="description" label="Description" placeholder="Optional" id="new-quest-description" :use-old="false" :show-error="false" />
                </div>
                <p class="mt-3 text-xs text-muted">New items are added to the end of the list.</p>

                <x-slot:footer>
                    <x-button icon="plus">Add item</x-button>
                </x-slot:footer>
            </x-card>
        </form>
    </div>
</x-app-layout>
