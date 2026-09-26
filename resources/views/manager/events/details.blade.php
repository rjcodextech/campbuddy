<x-manager-layout :title="$event->display_name" :subtitle="'/event/'.$event->slug"
                  :breadcrumbs="[['My events', route('manager.dashboard')], [$event->display_name]]">
    <x-slot:actions>
        <x-event-status :status="$event->status" class="!px-3 !py-1" />
        @if ($event->status === 'active' && $event->is_visible)
            <x-button :href="route('event.home', $event)" target="_blank" rel="noopener" variant="secondary" icon="external">View in app</x-button>
        @endif
    </x-slot:actions>

    <x-manager.event-nav :event="$event" current="details" />

    <div class="max-w-4xl">
        <form method="POST" action="{{ route('manager.events.details.update', $event) }}">
            @csrf
            @method('PUT')

            <x-card title="Event details">
                {{-- Everything except the lifecycle status, which only an admin changes. --}}
                <div class="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2">
                    <x-form.input name="display_name" label="Display name" required
                                  :value="$event->display_name" placeholder="WordCamp Rajasthan 2026"
                                  hint="Shown to attendees as the event's title." />

                    <x-form.input name="slug" label="URL slug" required
                                  :value="$event->slug" placeholder="wordcamp-rajasthan-2026"
                                  hint="The event's address: /event/your-slug. Change it only if you must — links, QR codes and apps that already use the old address stop working." />

                    <x-form.input name="short_name" label="Short name / hashtag"
                                  :value="$event->short_name" placeholder="#WCRajasthan" />

                    <x-form.input name="source_site_url" type="url" label="Source site URL" required
                                  :value="$event->source_site_url" placeholder="https://rajasthan.wordcamp.org/2026"
                                  hint="The event's own WordCamp.org site. Schedule and sponsors are read from here." />

                    <x-form.input name="starts_on" type="date" label="Starts on"
                                  :value="optional($event->starts_on)->toDateString()" />

                    <x-form.input name="ends_on" type="date" label="Ends on"
                                  :value="optional($event->ends_on)->toDateString()"
                                  hint="Drives when attendee-discovery profiles expire." />

                    {{-- Blank = read from the WordCamp site on every fetch; typed = kept. --}}
                    <x-form.input name="timezone" label="Time zone" list="timezone-list"
                                  :value="$event->timezone_locked ? $event->timezone : ''"
                                  :placeholder="$event->timezone ? $event->timezone.' (from the WordCamp site)' : 'Read from the WordCamp site'"
                                  hint="Session times, event days and when the discovery chat opens all use this. Leave blank to use the WordCamp site's own setting." />
                    <datalist id="timezone-list">
                        @foreach (\DateTimeZone::listIdentifiers() as $zoneName)
                            <option value="{{ $zoneName }}"></option>
                        @endforeach
                    </datalist>

                    <div class="sm:pt-7">
                        <x-form.checkbox name="is_visible" label="Visible in the public app"
                                         hint="Untick to hide an active event from attendees."
                                         :checked="$event->is_visible" unchecked="0" />
                    </div>
                </div>

                <div class="mt-6 flex items-start gap-3 rounded-lg border border-line bg-paper-soft px-4 py-3 text-sm">
                    <x-icon name="lock" class="mt-0.5 h-5 w-5 shrink-0 text-muted" />
                    <p>
                        <span class="font-medium">Status: {{ ucfirst($event->status) }}.</span>
                        <span class="text-muted">Only an admin can change an event's status (draft, approved, active, archived).</span>
                    </p>
                </div>

                <x-slot:footer>
                    <x-button>Save changes</x-button>
                </x-slot:footer>
            </x-card>
        </form>
    </div>
</x-manager-layout>
