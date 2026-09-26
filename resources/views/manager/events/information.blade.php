@php
    $info = $event->info ?? [];

    // Where a field's value came from: still what the last fetch wrote → auto;
    // anything else that's filled in → typed by a person, and kept on refresh.
    $provenance = function (string $field, ?string $extra = null) use ($info, $event) {
        $value = $info[$field] ?? null;
        $fetched = ($event->info_fetched ?? [])[$field] ?? null;

        $note = match (true) {
            blank($value) => $event->info_fetched_at ? 'Not found on the WordCamp site.' : null,
            $value === $fetched => 'From the WordCamp site.',
            default => 'Your edit — kept on refresh.',
        };

        return trim(($extra ? $extra.' ' : '').($note ?? ''));
    };
@endphp

<x-manager-layout :title="$event->display_name" :subtitle="'/event/'.$event->slug"
                  :breadcrumbs="[['My events', route('manager.dashboard')], [$event->display_name, route('manager.events.details', $event)], ['Event information']]">
    <x-slot:actions>
        <x-event-status :status="$event->status" class="!px-3 !py-1" />
    </x-slot:actions>

    <x-manager.event-nav :event="$event" current="information" />

    <div class="max-w-4xl">
        <form method="POST" action="{{ route('manager.events.information.update', $event) }}">
            @csrf
            @method('PUT')

            <x-card title="Event information" description="Shown to attendees on Explore → Event Info. Filled in from the WordCamp site; anything you type is kept. Blank fields are hidden.">
                <div class="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2">
                    <x-form.input name="venue" label="Venue" :value="$info['venue'] ?? ''" :hint="$provenance('venue')" class="sm:col-span-2" />
                    <x-form.input name="wifi" label="Wifi details" :value="$info['wifi'] ?? ''" :hint="$provenance('wifi')" />
                    <x-form.input name="contributor_day_location" label="Contributor Day location" :value="$info['contributor_day_location'] ?? ''" :hint="$provenance('contributor_day_location')" />
                    <x-form.input name="code_of_conduct_url" type="url" label="Code of conduct URL" :value="$info['code_of_conduct_url'] ?? ''" :hint="$provenance('code_of_conduct_url')" class="sm:col-span-2" placeholder="https://" />
                    <x-form.textarea name="registration_info" label="Registration info" rows="2" :value="$info['registration_info'] ?? ''" :hint="$provenance('registration_info')" />
                    <x-form.textarea name="emergency_contact" label="Emergency / contact info" rows="2" :value="$info['emergency_contact'] ?? ''"
                                     :hint="$provenance('emergency_contact', 'A phone number or email here becomes tappable.')" />
                    <x-form.textarea name="social_event_info" label="Social event info" rows="2" :value="$info['social_event_info'] ?? ''" :hint="$provenance('social_event_info')" />
                    <x-form.textarea name="nearby_venue_info" label="Nearby venues" rows="2" :value="$info['nearby_venue_info'] ?? ''" :hint="$provenance('nearby_venue_info')" />
                    <x-form.textarea name="important_links" label="Important links" rows="4" :value="$info['important_links'] ?? ''"
                                     :hint="$provenance('important_links', 'One link per line.')" class="sm:col-span-2" />
                </div>

                <x-slot:footer>
                    <x-button>Save event information</x-button>
                </x-slot:footer>
            </x-card>
        </form>
    </div>
</x-manager-layout>
