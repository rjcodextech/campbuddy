<x-manager-layout :title="$event->display_name" :subtitle="'/event/'.$event->slug"
                  :breadcrumbs="[['My events', route('manager.dashboard')], [$event->display_name]]">
    <x-slot:actions>
        <x-event-status :status="$event->status" class="px-3! py-1!" />
        <x-button :href="route('manager.events.qr', $event)" variant="secondary" icon="device-phone">QR code</x-button>
        @if ($event->status === 'active' && $event->is_visible)
            <x-button :href="route('event.home', $event)" target="_blank" rel="noopener" variant="secondary" icon="external">View in app</x-button>
        @endif
    </x-slot:actions>

    <x-manager.event-nav :event="$event" current="details" />

    <div class="w-full space-y-6">
        {{-- Event overview band: live identity, shareable link, and at-a-glance status tiles --}}
        <section class="rounded-xl border border-line bg-white p-5 shadow-xs sm:p-6" aria-labelledby="manager-overview-heading">
            <div class="flex flex-col gap-4 border-b border-line pb-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex min-w-0 items-center gap-3.5">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-line bg-paper-soft text-base font-bold text-maroon-text" aria-hidden="true">
                        @if ($event->markUrl())
                            <img src="{{ $event->markUrl() }}" alt="" class="max-h-10 max-w-10 object-contain" data-fallback="/media/icons/icon-192.png" onerror="this.onerror=null; this.src='/media/icons/icon-192.png';">
                        @else
                            {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($event->display_name, 0, 1)) }}
                        @endif
                    </span>
                    <div class="min-w-0">
                        <h2 id="manager-overview-heading" class="truncate text-base font-semibold text-ink">{{ $event->display_name }}</h2>
                        <p class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted">
                            @if ($event->starts_on)
                                <span>{{ $event->starts_on->format('j M Y') }}@if ($event->ends_on && ! $event->ends_on->isSameDay($event->starts_on)) – {{ $event->ends_on->format('j M Y') }}@endif</span>
                            @else
                                <span>Dates pending sync</span>
                            @endif
                            @if ($timing)
                                <span aria-hidden="true">&middot;</span>
                                <span @class(['font-semibold', 'text-maroon-text' => $timing['state'] !== 'past', 'text-muted' => $timing['state'] === 'past'])>{{ $timing['label'] }}</span>
                            @endif
                            @if ($event->timezone)
                                <span aria-hidden="true">&middot;</span>
                                <span>{{ $event->timezone }}</span>
                            @endif
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2" x-data="{ copied: false }">
                    <button type="button"
                            class="cb-btn cb-btn-secondary cb-btn-sm"
                            @click="navigator.clipboard?.writeText('{{ url('/event/'.$event->slug) }}'); copied = true; setTimeout(() => copied = false, 2000)">
                        <span x-text="copied ? 'Copied URL' : 'Copy attendee URL'">Copy attendee URL</span>
                    </button>
                    <a href="{{ route('manager.events.delay', $event) }}" class="cb-btn cb-btn-secondary cb-btn-sm">Running late</a>
                    <a href="{{ route('manager.events.notifications', $event) }}" class="cb-btn cb-btn-secondary cb-btn-sm">Broadcast</a>
                </div>
            </div>

            <div class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="rounded-lg border border-line bg-paper/60 p-3.5">
                    <div class="flex items-center justify-between gap-2 text-xs font-medium text-muted">
                        <span>Schedule</span>
                        <x-icon name="calendar" class="h-4 w-4 text-maroon-text" />
                    </div>
                    <p class="mt-1.5 text-lg font-bold text-ink cb-display">{{ number_format($stats['sessions']) }}</p>
                    <p class="mt-0.5 text-xs text-muted">{{ number_format($stats['speakers']) }} {{ \Illuminate\Support\Str::plural('speaker', $stats['speakers']) }}</p>
                </div>

                <a href="{{ route('manager.events.attendees', $event) }}" class="group rounded-lg border border-line bg-paper/60 p-3.5 transition-colors hover:border-maroon/40 hover:bg-paper-soft/60 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-maroon/40">
                    <div class="flex items-center justify-between gap-2 text-xs font-medium text-muted">
                        <span>Attendees</span>
                        <x-icon name="users" class="h-4 w-4 text-maroon-text" />
                    </div>
                    <p class="mt-1.5 text-lg font-bold text-ink cb-display group-hover:text-maroon-text">{{ number_format($stats['roster']) }}</p>
                    <p class="mt-0.5 text-xs text-muted">Manage badges &amp; roles</p>
                </a>

                <a href="{{ route('manager.events.social', $event) }}" class="group rounded-lg border border-line bg-paper/60 p-3.5 transition-colors hover:border-maroon/40 hover:bg-paper-soft/60 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-maroon/40">
                    <div class="flex items-center justify-between gap-2 text-xs font-medium text-muted">
                        <span>Organizers &amp; Sponsors</span>
                        <x-icon name="megaphone" class="h-4 w-4 text-maroon-text" />
                    </div>
                    <p class="mt-1.5 text-lg font-bold text-ink cb-display group-hover:text-maroon-text">{{ number_format($stats['organizers']) }} / {{ number_format($stats['sponsors']) }}</p>
                    <p class="mt-0.5 text-xs text-muted">Open social media studio</p>
                </a>

                <a href="{{ route('manager.events.information', $event) }}" class="group rounded-lg border border-line bg-paper/60 p-3.5 transition-colors hover:border-maroon/40 hover:bg-paper-soft/60 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-maroon/40">
                    <div class="flex items-center justify-between gap-2 text-xs font-medium text-muted">
                        <span>Event Info</span>
                        <x-icon name="information-circle" class="h-4 w-4 text-maroon-text" />
                    </div>
                    <p class="mt-1.5 text-lg font-bold text-ink cb-display group-hover:text-maroon-text">{{ $stats['info_filled'] }} / {{ $stats['info_total'] }}</p>
                    <p class="mt-0.5 text-xs text-muted">Venue, tickets &amp; contacts</p>
                </a>
            </div>
        </section>

        <form method="POST" action="{{ route('manager.events.details.update', $event) }}">
            @csrf
            @method('PUT')

            <x-card title="Event details" description="Identity, source WordCamp website, dates, and public visibility.">
                {{-- Everything except the lifecycle status, which only an admin changes. --}}
                <div class="space-y-6">
                    <div>
                        <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-muted">Identity &amp; source site</h3>
                        <div class="grid grid-cols-1 items-start gap-x-6 gap-y-5 sm:grid-cols-2">
                            <x-form.input name="display_name" label="Display name" required
                                          :value="$event->display_name" placeholder="WordCamp Rajasthan 2026"
                                          hint="Shown to attendees as the event's title." />

                            <x-form.input name="slug" label="URL slug" required
                                          :value="$event->slug" placeholder="wordcamp-rajasthan-2026"
                                          hint="Used in /event/your-slug. Keep stable so QR codes and shared links don't break." />

                            <x-form.input name="short_name" label="Short name / hashtag"
                                          :value="$event->short_name" placeholder="#WCRajasthan" />

                            <x-form.input name="source_site_url" type="url" label="Source site URL" required
                                          :value="$event->source_site_url" placeholder="https://rajasthan.wordcamp.org/2026"
                                          hint="Official WordCamp.org URL used for schedule, people, and sponsor ingestion." />
                        </div>
                    </div>

                    <div class="border-t border-line pt-6">
                        <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-muted">Dates, time zone &amp; app listing</h3>
                        <div class="grid grid-cols-1 items-start gap-x-6 gap-y-5 sm:grid-cols-2">
                            <x-form.input name="starts_on" type="date" label="Starts on"
                                          :value="optional($event->starts_on)->toDateString()"
                                          :hint="$event->dates_locked ? 'Manually set — clear both dates to resume syncing central.wordcamp.org.' : 'Synced from central.wordcamp.org. Edit to override manually.'" />

                            <x-form.input name="ends_on" type="date" label="Ends on"
                                          :value="optional($event->ends_on)->toDateString()"
                                          hint="Determines when attendee-discovery profiles automatically expire." />

                            {{-- Blank = read from the WordCamp site on every fetch; typed = kept. --}}
                            <x-form.input name="timezone" label="Time zone" list="timezone-list"
                                          :value="$event->timezone_locked ? $event->timezone : ''"
                                          :placeholder="$event->timezone ? $event->timezone.' (from the WordCamp site)' : 'Read from the WordCamp site'"
                                          hint="Drives session times, event days, and discovery chat opening." />
                            <datalist id="timezone-list">
                                @foreach (\DateTimeZone::listIdentifiers() as $zoneName)
                                    <option value="{{ $zoneName }}"></option>
                                @endforeach
                            </datalist>

                            <div class="rounded-lg border border-line bg-paper/60 px-4 py-3 sm:mt-6">
                                <x-form.checkbox name="is_visible" label="Visible in the public app"
                                                 hint="Untick to temporarily hide an active event from attendees."
                                                 :checked="$event->is_visible" unchecked="0" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex items-start gap-3 rounded-lg border border-line bg-paper-soft px-4 py-3 text-sm">
                    <x-icon name="lock" class="mt-0.5 h-5 w-5 shrink-0 text-muted" />
                    <p>
                        <span class="font-medium">Status: {{ ucfirst($event->status) }}.</span>
                        <span class="text-muted">Only an admin can change an event's lifecycle status (draft, approved, active, archived).</span>
                    </p>
                </div>

                <x-slot:footer>
                    <x-button>Save changes</x-button>
                </x-slot:footer>
            </x-card>
        </form>
    </div>
</x-manager-layout>
