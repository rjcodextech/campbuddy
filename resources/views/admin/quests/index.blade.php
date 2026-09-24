<x-app-layout title="Quests & checklist" :subtitle="$event->display_name"
              :breadcrumbs="[['Events', route('admin.events.index')], [$event->display_name, route('admin.events.edit', $event)], ['Quests & checklist']]">
    <x-admin.event-nav :event="$event" current="quests" />

    <div class="max-w-4xl space-y-6">
        <x-card title="Checklist" description="The pre-trip checklist attendees tick off on their phone. Rename, reorder (lower numbers first), hide or remove items. The Things to do cards are shared by all events." flush>
            @if ($quests->isEmpty())
                <div class="px-5 py-12 text-center sm:px-6">
                    <x-icon name="inbox" class="mx-auto h-8 w-8 text-muted/50" />
                    <p class="mt-2 text-sm font-medium">No checklist items</p>
                    <p class="mx-auto mt-1 max-w-sm text-sm text-muted">Attendees will only see the built-in Things to do cards. Add an item below.</p>
                </div>
            @else
                {{-- One compact row per item; each input keeps a screen-reader label. --}}
                <ul class="divide-y divide-line">
                    @foreach ($quests as $quest)
                        <li @class(['px-5 py-3 sm:px-6', 'bg-paper/60 text-muted' => ! $quest->is_active])>
                            <div class="flex flex-col gap-2 md:flex-row md:items-center md:gap-3">
                                <form method="POST" action="{{ route('admin.events.quests.update', [$event, $quest]) }}"
                                      class="grid flex-1 grid-cols-[4rem_minmax(0,1fr)] gap-2 md:grid-cols-[4rem_minmax(0,2fr)_minmax(0,1.5fr)_auto_auto] md:items-center md:gap-3">
                                    @csrf
                                    @method('PUT')

                                    <label class="sr-only" for="quest-order-{{ $quest->id }}">Order</label>
                                    <input id="quest-order-{{ $quest->id }}" name="sort_order" type="number" min="0" value="{{ $quest->sort_order }}" title="Order — lower numbers appear first" class="cb-input">

                                    <label class="sr-only" for="quest-title-{{ $quest->id }}">Title</label>
                                    <input id="quest-title-{{ $quest->id }}" name="title" type="text" required value="{{ $quest->title }}" class="cb-input">

                                    <label class="sr-only" for="quest-desc-{{ $quest->id }}">Description</label>
                                    <input id="quest-desc-{{ $quest->id }}" name="description" type="text" placeholder="Description (optional)" value="{{ $quest->description }}" class="cb-input col-span-2 md:col-span-1">

                                    <label class="inline-flex items-center gap-2 text-sm" for="quest-active-{{ $quest->id }}">
                                        <input type="hidden" name="is_active" value="0">
                                        <input id="quest-active-{{ $quest->id }}" type="checkbox" name="is_active" value="1" @checked($quest->is_active) class="cb-check">
                                        <span>Shown</span>
                                    </label>

                                    <x-button variant="secondary" size="sm" class="justify-self-start">Save</x-button>
                                </form>

                                <x-action-form :action="route('admin.events.quests.destroy', [$event, $quest])" method="DELETE"
                                               variant="danger-outline" size="sm" icon="trash"
                                               :confirm="'Remove “'.$quest->title.'”?'" aria-label="Remove {{ $quest->title }}">Remove</x-action-form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        <form method="POST" action="{{ route('admin.events.quests.store', $event) }}">
            @csrf

            <x-card title="Add a checklist item" description="Added to the end of the list.">
                <div class="grid gap-4 md:grid-cols-2">
                    {{-- Ignores old()/inline errors: a failed *row* update shares these field names. The page-level summary reports it. --}}
                    <x-form.input name="title" label="Title" required placeholder="e.g. Bring business cards" id="new-quest-title" :use-old="false" :show-error="false" />
                    <x-form.input name="description" label="Description" placeholder="Optional" id="new-quest-description" :use-old="false" :show-error="false" />
                </div>

                <x-slot:footer>
                    <x-button icon="plus">Add item</x-button>
                </x-slot:footer>
            </x-card>
        </form>
    </div>
</x-app-layout>
