@php
    $info = $event->info ?? [];

    // Where a field's value came from: still what the last fetch wrote → auto;
    // anything else that's filled in → typed by an admin, and kept on refresh.
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

<x-app-layout :title="$event->display_name"
              :breadcrumbs="[['Events', route('admin.events.index')], [$event->display_name]]">
    {{-- Who the event is, where it stands, and the tabs to its other pages. --}}
    <x-admin.event-nav :event="$event" current="details" />

    {{-- The forms on the left; what attendees see and the branding on the right (below on a phone). --}}
    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_21rem] lg:items-start">
        <div class="min-w-0 space-y-6">
            {{-- Core details --}}
            <form method="POST" action="{{ route('admin.events.update', $event) }}">
                @csrf
                @method('PUT')

                <x-card title="Event details">
                    @include('admin.events._form')

                    <x-slot:footer>
                        <x-button>Save changes</x-button>
                    </x-slot:footer>
                </x-card>
            </form>

            {{-- What attendees see on Explore → Event Info. The fetch button posts to this
                 separate form (via its form="" attribute) because a form can't nest inside another. --}}
            <form id="fetch-info-form" method="POST" action="{{ route('admin.events.fetch-info', $event) }}" class="hidden">
                @csrf
            </form>

            <form method="POST" action="{{ route('admin.events.update-info', $event) }}">
                @csrf
                @method('PUT')

                <x-card title="Event information" description="Shown on Explore → Event Info. Filled in from the WordCamp site; anything you type is kept. Blank fields are hidden.">
                    <x-slot:actions>
                        <x-button type="submit" form="fetch-info-form" variant="secondary" size="sm" icon="refresh">Fetch latest</x-button>
                    </x-slot:actions>

                    <p class="mb-5 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted">
                        @if ($lastInfoFetch)
                            <x-fetch-status :status="$lastInfoFetch->status" />
                            Last fetched {{ $lastInfoFetch->fetched_at->diffForHumans() }} — {{ $lastInfoFetch->message }}
                        @else
                            Not fetched yet — use “Fetch latest” to read it from the WordCamp site.
                        @endif
                    </p>

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

            @if ($event->status === 'draft')
                <x-card title="Delete event" danger
                        description="Only possible while the event is still a draft. Once it's approved, active or archived, archive it instead.">
                    <x-action-form :action="route('admin.events.destroy', $event)" method="DELETE" variant="danger" icon="trash"
                                   :confirm="'Delete “'.$event->display_name.'”? This cannot be undone.'">
                        Delete draft event
                    </x-action-form>
                </x-card>
            @endif
        </div>

        <div class="min-w-0 space-y-6">
            {{-- Sessions / speakers / sponsors ingestion --}}
            <x-card title="Event data" description="What attendees see right now. Updates by itself every 15 minutes (the attendee list daily) — new items added, changes updated, removed ones dropped; never cleared first.">
                <x-slot:actions>
                    <x-action-form :action="route('admin.events.refresh', $event)" icon="refresh" size="sm">Refresh now</x-action-form>
                </x-slot:actions>

                <dl class="grid grid-cols-3 gap-2">
                    @foreach ($dataCounts as $label => $count)
                        <div class="rounded-lg border border-line bg-paper-soft px-3 py-2">
                            <dt class="text-xs text-muted">{{ $label }}</dt>
                            <dd class="text-lg font-semibold tabular-nums {{ $count === null ? 'text-muted' : '' }}">{{ $count ?? '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
                @if (in_array(null, $dataCounts, true))
                    <p class="mt-2 text-xs text-muted">"—" means nothing has been fetched yet.</p>
                @endif

                <div class="mt-5 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                    @if ($lastFetch)
                        <span class="font-medium">Last fetch:</span>
                        <x-fetch-status :status="$lastFetch->status" />
                        <span class="text-muted">{{ $lastFetch->fetched_at->diffForHumans() }}</span>
                        @if ($lastFetch->message)
                            <span class="basis-full text-muted">{{ $lastFetch->message }}</span>
                        @endif
                    @else
                        <span class="text-muted">No schedule fetch has run yet for this event.</span>
                    @endif
                </div>

                @if ($recentFetches->isNotEmpty())
                    <details class="mt-5 rounded-lg border border-line">
                        <summary class="cursor-pointer px-4 py-2.5 text-sm font-medium">Recent fetch history ({{ $recentFetches->count() }})</summary>
                        <ul class="divide-y divide-line border-t border-line text-sm">
                            @foreach ($recentFetches as $log)
                                <li class="px-4 py-2.5">
                                    <div class="flex flex-wrap items-center justify-between gap-x-2 gap-y-1">
                                        <span class="font-medium">{{ $jobLabels[$log->job_type] ?? $log->job_type }}</span>
                                        <span class="flex items-center gap-2">
                                            <x-fetch-status :status="$log->status" />
                                            <span class="text-xs text-muted" title="{{ $log->fetched_at }}">{{ $log->fetched_at->diffForHumans() }}</span>
                                        </span>
                                    </div>
                                    @if ($log->message)
                                        <p class="mt-0.5 break-words text-xs text-muted">{{ $log->message }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            </x-card>

            {{-- Logo + favicon --}}
            <x-card title="Branding" description="The event's logo and favicon, shown in the attendee app.">
                <div class="flex flex-wrap items-center gap-5">
                    <figure class="text-center">
                        <div class="flex h-20 w-20 items-center justify-center rounded-lg border border-line bg-paper-soft">
                            @if ($event->logoUrl())
                                <img src="{{ $event->logoUrl() }}" alt="Current logo" class="max-h-16 max-w-16 object-contain">
                            @else
                                <span class="text-xs text-muted">No logo</span>
                            @endif
                        </div>
                        <figcaption class="mt-1 text-xs text-muted">Logo</figcaption>
                    </figure>

                    <figure class="text-center">
                        <div class="flex h-20 w-20 items-center justify-center rounded-lg border border-line bg-paper-soft">
                            @if ($event->faviconUrl())
                                <img src="{{ $event->faviconUrl() }}" alt="Current favicon" class="max-h-10 max-w-10 object-contain">
                            @else
                                <span class="text-xs text-muted">None</span>
                            @endif
                        </div>
                        <figcaption class="mt-1 text-xs text-muted">Favicon</figcaption>
                    </figure>
                </div>

                <div class="mt-4">
                    @if ($lastBrandingFetch)
                        <p class="text-sm text-muted">
                            Last auto-fetch:
                            <x-fetch-status :status="$lastBrandingFetch->status" />
                            {{ $lastBrandingFetch->fetched_at->diffForHumans() }}
                        </p>
                        @if ($lastBrandingFetch->message)
                            <p class="mt-1 text-xs text-muted">{{ $lastBrandingFetch->message }}</p>
                        @endif
                    @else
                        <p class="text-sm text-muted">Branding hasn't been auto-fetched yet.</p>
                    @endif

                    <x-action-form :action="route('admin.events.refresh-branding', $event)" icon="refresh" size="sm" class="mt-3">
                        Re-fetch branding
                    </x-action-form>
                </div>

                <form method="POST" action="{{ route('admin.events.upload-branding', $event) }}" enctype="multipart/form-data"
                      class="mt-6 border-t border-line pt-5">
                    @csrf

                    <p class="mb-4 text-sm font-medium">Upload your own <span class="font-normal text-muted">(overrides the auto-fetched files)</span></p>

                    <div class="grid grid-cols-1 gap-5">
                        <x-form.file name="logo" label="Logo" accept="image/png,image/jpeg,image/svg+xml,image/webp"
                                     hint="PNG, JPG, SVG or WebP." />
                        <x-form.file name="favicon" label="Favicon" accept="image/png,image/jpeg,image/x-icon,image/svg+xml"
                                     hint="PNG, JPG, ICO or SVG." />
                    </div>

                    <div class="mt-5">
                        <x-button variant="secondary" icon="upload">Upload</x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
</x-app-layout>
