@php
    /** @var \App\Models\Offer $offer */
    $form = $offer->leadForm();
    $assetOptions = $mediaAssets->pluck('filename', 'id')->all();
    $modeOptions = ['off' => 'Off — not asked', 'optional' => 'Optional', 'required' => 'Required'];
    $fieldHelp = [
        'name' => 'e.g. “Name”',
        'company' => 'e.g. “Company name”',
        'email' => 'Always asked — the sponsor replies here. e.g. “Registered email at RapidAPI”',
        'mobile' => 'e.g. “Phone number”, hint “Recommended”',
    ];
    $old = fn (string $key, $default) => old($key, $default);

    $name = $offer->exists ? $offer->displayName() : 'New deal';
    $title = $isDefault ? ($offer->exists ? 'Edit default deal' : 'Add a default deal') : ($offer->exists ? 'Edit deal' : 'Add a deal');
    $crumbs = $isDefault
        ? [['Default deals', route('admin.deals.index')], [$name]]
        : [['Events', route('admin.events.index')], [$event->display_name, route('admin.events.edit', $event)], ['Deals', $back], [$name]];
@endphp

<x-app-layout :title="$title" :subtitle="$isDefault ? 'Shown at every event in the countries you pick' : $event->display_name" :breadcrumbs="$crumbs">
    @unless ($isDefault)
        <x-admin.event-nav :event="$event" current="offers" />
    @endunless

    <form method="POST" action="{{ $action }}" class="max-w-4xl space-y-6">
        @csrf
        @if ($offer->exists)
            @method('PUT')
        @endif

        <x-card title="The offer" description="What the card on Explore → Deals says. Keep the headline short; details can say more.">
            <div class="grid gap-4 md:grid-cols-12">
                <x-form.input name="brand" label="Company / product" class="md:col-span-6" :value="$offer->brand" maxlength="120"
                              placeholder="e.g. Hostinger" hint="Shown in bold at the top of the card." />
                <x-form.input name="website" label="Website" class="md:col-span-6" :value="$offer->website" maxlength="120"
                              placeholder="e.g. hostinger.com" hint="Shown under the name. Empty = the link's own domain." />

                <x-form.input name="title" label="Offer headline" required class="md:col-span-8" :value="$offer->title" maxlength="120"
                              placeholder="e.g. 20% off your first plan" />
                <x-form.input name="highlight" label="Highlight" class="md:col-span-4" :value="$offer->highlight" maxlength="40"
                              placeholder="e.g. 20% OFF, FREE" hint="A short tag on the card." />

                <x-form.textarea name="description" label="Details" required rows="3" class="md:col-span-12" :value="$offer->description" maxlength="500"
                                 hint="What the attendee gets and how. Up to 500 characters." />

                <x-form.input name="terms" label="Terms" class="md:col-span-8" :value="$offer->terms" maxlength="255"
                              placeholder="e.g. For new customers. Valid for 6 months." hint="Small print under the details." />
                <x-form.input name="coupon_code" label="Coupon code" class="md:col-span-4" :value="$offer->coupon_code" maxlength="60"
                              hint="Shown with a Copy button." />
            </div>
        </x-card>

        <x-card title="Link">
            <div class="grid gap-4 md:grid-cols-12">
                <x-form.input name="url" type="url" label="Deal link" required class="md:col-span-12" :value="$offer->url" maxlength="500"
                              placeholder="https://…" hint="Referral and affiliate links work as they are." />
                <x-form.input name="cta_label" label="Button text" class="md:col-span-6" :value="$offer->cta_label" maxlength="40"
                              placeholder="Get the deal" />
                <x-form.select name="opens_in_app" label="Opens" class="md:col-span-6"
                               :options="['0' => 'In a new tab (keeps referral credit)', '1' => 'Inside the app']"
                               :value="$offer->opens_in_app ? '1' : '0'"
                               hint="Many sites (Hostinger, WordPress.com) refuse to open inside another app." />
            </div>
        </x-card>

        <x-card title="Look and order">
            <div class="grid gap-4 md:grid-cols-12">
                <x-form.select name="media_asset_id" label="Logo" placeholder="No logo (use emoji)" class="md:col-span-6"
                               :options="$assetOptions" :value="$offer->media_asset_id"
                               hint="Upload it to the Media Library first. A square logo looks best." />
                <x-form.input name="icon" label="Emoji" maxlength="10" class="md:col-span-2" :value="$offer->icon"
                              title="Fallback shown when no logo is chosen" />
                <x-form.input name="sort_order" type="number" min="0" label="Order" class="md:col-span-2" :value="$offer->sort_order" />

                <div class="flex items-end md:col-span-2">
                    <x-form.checkbox name="is_active" label="Active" unchecked="0" :checked="$offer->is_active" />
                </div>

                @if ($isDefault)
                    <x-form.input name="countries" label="Countries" class="md:col-span-12"
                                  :value="old('countries', implode(', ', (array) $offer->countries))" :use-old="false"
                                  placeholder="IN"
                                  hint="Two-letter country codes, comma-separated (IN = India, BD = Bangladesh). Empty = every country. New events in these countries get the deal automatically." />
                @endif
            </div>
        </x-card>

        <x-card title="Contact form" description="Asked before the deal opens. Leave it off for deals that should just link straight out.">
            <div class="space-y-5">
                <x-form.checkbox name="capture_leads" label="Ask for contact details before the deal opens" unchecked="0" :checked="$offer->capture_leads" />

                <x-form.input name="lead_form[intro]" id="lead-form-intro" label="Line above the form" :use-old="false" :show-error="false"
                              :value="$old('lead_form.intro', $form['intro'])" maxlength="255"
                              placeholder="Share a few details to get this deal. They go only to the sponsor, so they can sort it out for you." />

                <div class="overflow-x-auto rounded-lg border border-line">
                    <table class="w-full min-w-[40rem] text-sm">
                        <thead class="bg-paper-soft text-left text-xs uppercase tracking-wide text-muted">
                            <tr>
                                <th class="px-3 py-2 font-medium">Field</th>
                                <th class="px-3 py-2 font-medium">Asked</th>
                                <th class="px-3 py-2 font-medium">Label on the form</th>
                                <th class="px-3 py-2 font-medium">Hint (optional)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach (\App\Support\DealForm::FIELDS as $key => [$defaultLabel])
                                <tr>
                                    <td class="px-3 py-2 align-top font-medium">
                                        {{ ['name' => 'Name', 'company' => 'Company', 'email' => 'Email', 'mobile' => 'Phone'][$key] }}
                                        <span class="block text-xs font-normal text-muted">{{ $fieldHelp[$key] }}</span>
                                    </td>
                                    <td class="px-3 py-2 align-top">
                                        @if ($key === 'email')
                                            <x-badge variant="brand">Required</x-badge>
                                        @else
                                            <x-form.select :name="'lead_form[fields]['.$key.'][mode]'" :id="'lead-'.$key.'-mode'" :label="null" :use-old="false" :show-error="false"
                                                           :options="$modeOptions" :value="$old('lead_form.fields.'.$key.'.mode', $form['fields'][$key]['mode'])"
                                                           :aria-label="$defaultLabel.': asked'" />
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 align-top">
                                        <x-form.input :name="'lead_form[fields]['.$key.'][label]'" :id="'lead-'.$key.'-label'" :label="null" :use-old="false" :show-error="false"
                                                      :value="$old('lead_form.fields.'.$key.'.label', $form['fields'][$key]['label'])" maxlength="80"
                                                      :aria-label="$defaultLabel.': label'" />
                                    </td>
                                    <td class="px-3 py-2 align-top">
                                        <x-form.input :name="'lead_form[fields]['.$key.'][hint]'" :id="'lead-'.$key.'-hint'" :label="null" :use-old="false" :show-error="false"
                                                      :value="$old('lead_form.fields.'.$key.'.hint', $form['fields'][$key]['hint'])" maxlength="120"
                                                      :aria-label="$defaultLabel.': hint'" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="rounded-lg border border-line p-4">
                    <p class="text-sm font-semibold">Products or plans to tick <span class="font-normal text-muted">(optional)</span></p>
                    <p class="mt-0.5 text-xs text-muted">e.g. “Need a special plan for:” with Knit Pay – Pro and Knit Pay – UPI. Attendees tap the ones they want.</p>

                    <div class="mt-3 grid gap-4 md:grid-cols-12">
                        <x-form.select name="lead_form[choices][mode]" id="lead-choices-mode" label="Asked" class="md:col-span-3" :use-old="false" :show-error="false"
                                       :options="$modeOptions" :value="$old('lead_form.choices.mode', $form['choices']['mode'])" />
                        <x-form.input name="lead_form[choices][label]" id="lead-choices-label" label="Question" class="md:col-span-6" :use-old="false" :show-error="false"
                                      :value="$old('lead_form.choices.label', $form['choices']['options'] === [] ? '' : $form['choices']['label'])" maxlength="80"
                                      placeholder="e.g. Need a special plan for" />
                        <div class="flex items-end md:col-span-3">
                            <x-form.checkbox name="lead_form[choices][multiple]" id="lead-choices-multiple" label="More than one allowed" unchecked="0"
                                             :use-old="false" :show-error="false"
                                             :checked="(bool) $old('lead_form.choices.multiple', $form['choices']['multiple'])" />
                        </div>
                        <x-form.textarea name="lead_form[choices][options]" id="lead-choices-options" label="Options, one per line" rows="3" class="md:col-span-12"
                                         :use-old="false" :show-error="false"
                                         :value="$old('lead_form.choices.options', implode(PHP_EOL, $form['choices']['options']))"
                                         :hint="'Up to '.\App\Support\DealForm::MAX_OPTIONS.' options.'" />
                    </div>
                </div>
            </div>

            <x-slot:footer>
                <x-button :href="$back" variant="link" class="px-2">Cancel</x-button>
                <x-button icon="check-circle">{{ $offer->exists ? 'Save deal' : 'Add deal' }}</x-button>
            </x-slot:footer>
        </x-card>
    </form>
</x-app-layout>
