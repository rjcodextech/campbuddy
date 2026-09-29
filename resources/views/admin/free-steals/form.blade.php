@php
    /** @var \App\Models\FreeSteal $steal */
    $name = $steal->exists ? $steal->name : 'New Free Steal';
    $back = route('admin.free-steals.index');
@endphp

<x-app-layout :title="$steal->exists ? 'Edit Free Steal' : 'Add a Free Steal'" subtitle="Shown on Explore → Free Steals at every event"
              :breadcrumbs="[['Free Steals', $back], [$name]]">
    <form method="POST" action="{{ $action }}" class="max-w-4xl space-y-6">
        @csrf
        @if ($steal->exists)
            @method('PUT')
        @endif

        <x-card title="The card" description="Plain words: what it does, in a sentence someone new to it would get.">
            <div class="grid gap-4 md:grid-cols-12">
                <x-form.input name="name" label="Product name" required class="md:col-span-6" :value="$steal->name" maxlength="120"
                              placeholder="e.g. Visual Blueprint Builder" />
                <x-form.input name="maker" label="Maker / company" required class="md:col-span-6" :value="$steal->maker" maxlength="120"
                              placeholder="e.g. Lubus" hint="Shown as “by …” under the name." />

                <x-form.textarea name="description" label="Description" required rows="3" class="md:col-span-12" :value="$steal->description" maxlength="300"
                                 hint="Up to 300 characters." />

                <x-form.input name="category" label="Category" required class="md:col-span-6" :value="$steal->category" maxlength="80"
                              placeholder="e.g. Developer Tools / Playground"
                              hint="Its first word picks the card's icon (AI, Gutenberg, Security, Performance, WooCommerce…)." />
            </div>
        </x-card>

        <x-card title="Link">
            <div class="grid gap-4 md:grid-cols-12">
                <x-form.input name="url" type="url" label="Link" required class="md:col-span-8" :value="$steal->url" maxlength="500"
                              placeholder="https://github.com/…" hint="GitHub, WordPress.org or the maker's own page. Opens in a new tab." />
                <x-form.input name="cta_label" label="Button text" class="md:col-span-4" :value="$steal->cta_label" maxlength="40"
                              :placeholder="\App\Models\FreeSteal::DEFAULT_CTA" />
            </div>
        </x-card>

        <x-card title="Show and order">
            <div class="grid gap-4 md:grid-cols-12">
                <x-form.input name="sort_order" type="number" min="0" label="Order" class="md:col-span-3" :value="$steal->sort_order"
                              hint="Lower shows first." />
                <div class="flex items-end md:col-span-4">
                    <x-form.checkbox name="is_active" label="Show on Explore" unchecked="0" :checked="$steal->is_active"
                                     :hint="'Only the first '.\App\Models\FreeSteal::SHOWN.' switched-on ones show.'" />
                </div>
                <div class="flex items-end md:col-span-5">
                    <x-form.checkbox name="is_featured" label="Featured" unchecked="0" :checked="$steal->is_featured"
                                     hint="The team's pick. Never paid placement." />
                </div>
            </div>

            <x-slot:footer>
                <x-button :href="$back" variant="link" class="px-2">Cancel</x-button>
                <x-button icon="check-circle">{{ $steal->exists ? 'Save' : 'Add Free Steal' }}</x-button>
            </x-slot:footer>
        </x-card>
    </form>
</x-app-layout>
