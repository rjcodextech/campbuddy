<x-app-layout :title="$event->display_name">
    <div class="max-w-3xl space-y-6">

        @if (session('status'))
            <div class="p-4 bg-teal/10 text-teal rounded-md">{{ session('status') }}</div>
        @endif

        <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-line p-6">
            <form method="POST" action="{{ route('admin.events.update', $event) }}">
                @csrf
                @method('PUT')
                @include('admin.events._form')

                <div class="mt-6 flex justify-end">
                    <button type="submit" class="px-4 py-2 bg-maroon text-white text-sm font-medium rounded-md hover:bg-maroon-dark">
                        Save changes
                    </button>
                </div>
            </form>
        </div>

        <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-line p-6 flex gap-4">
            <a href="{{ route('admin.events.quests.index', $event) }}" class="text-maroon hover:text-maroon-dark font-medium text-sm">Manage Quests →</a>
            <a href="{{ route('admin.events.offers.index', $event) }}" class="text-maroon hover:text-maroon-dark font-medium text-sm">Manage Offers →</a>
            <a href="{{ route('admin.events.roster.index', $event) }}" class="text-maroon hover:text-maroon-dark font-medium text-sm">Manage Roster →</a>
        </div>

        <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-line p-6">
            <h3 class="text-lg font-medium text-ink mb-2">Ingestion status</h3>

            @if ($lastFetch)
                <p class="text-sm text-ink">
                    Last sessions/speakers/sponsors fetch:
                    <span class="font-medium {{ $lastFetch->status === 'ok' ? 'text-teal' : 'text-maroon' }}">
                        {{ $lastFetch->status }}
                    </span>
                    — {{ $lastFetch->fetched_at->diffForHumans() }}
                </p>
                <p class="text-sm text-muted mt-1">{{ $lastFetch->message }}</p>
            @else
                <p class="text-sm text-muted">No ingestion has run yet for this event.</p>
            @endif

            <form method="POST" action="{{ route('admin.events.refresh', $event) }}" class="mt-4">
                @csrf
                <button type="submit" class="px-4 py-2 bg-paper-soft text-ink text-sm font-medium rounded-md hover:bg-line">
                    Refresh now
                </button>
            </form>
        </div>

        <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-line p-6">
            <h3 class="text-lg font-medium text-ink mb-2">Branding</h3>

            <div class="flex items-center gap-6 mb-4">
                <div class="text-center">
                    <div class="h-16 w-16 flex items-center justify-center border border-line rounded-md bg-paper-soft">
                        @if ($event->logoUrl())
                            <img src="{{ $event->logoUrl() }}" alt="Logo" class="max-h-14 max-w-14 object-contain">
                        @else
                            <span class="text-xs text-muted">No logo</span>
                        @endif
                    </div>
                    <p class="text-xs text-muted mt-1">Logo</p>
                </div>
                <div class="text-center">
                    <div class="h-16 w-16 flex items-center justify-center border border-line rounded-md bg-paper-soft">
                        @if ($event->faviconUrl())
                            <img src="{{ $event->faviconUrl() }}" alt="Favicon" class="max-h-10 max-w-10 object-contain">
                        @else
                            <span class="text-xs text-muted">None</span>
                        @endif
                    </div>
                    <p class="text-xs text-muted mt-1">Favicon</p>
                </div>
            </div>

            @if ($lastBrandingFetch)
                <p class="text-sm text-muted mb-4">
                    Last auto-fetch: {{ $lastBrandingFetch->status }} — {{ $lastBrandingFetch->message }}
                    ({{ $lastBrandingFetch->fetched_at->diffForHumans() }})
                </p>
            @endif

            <form method="POST" action="{{ route('admin.events.refresh-branding', $event) }}" class="mb-4">
                @csrf
                <button type="submit" class="px-4 py-2 bg-paper-soft text-ink text-sm font-medium rounded-md hover:bg-line">
                    Re-fetch branding assets
                </button>
            </form>

            <form method="POST" action="{{ route('admin.events.upload-branding', $event) }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <div>
                    <x-input-label for="logo" value="Upload/replace logo (overrides auto-fetch)" />
                    <input id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/svg+xml,image/webp" class="mt-1 block w-full text-sm">
                    <x-input-error :messages="$errors->get('logo')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="favicon" value="Upload/replace favicon" />
                    <input id="favicon" name="favicon" type="file" accept="image/png,image/jpeg,image/x-icon,image/svg+xml" class="mt-1 block w-full text-sm">
                    <x-input-error :messages="$errors->get('favicon')" class="mt-1" />
                </div>
                <button type="submit" class="px-4 py-2 bg-maroon text-white text-sm font-medium rounded-md hover:bg-maroon-dark">
                    Upload
                </button>
            </form>
        </div>

        <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-line p-6">
            <h3 class="text-lg font-medium text-ink mb-4">Event Information</h3>
            <p class="text-sm text-muted mb-4">Every field is optional and simply omitted from the attendee app when blank.</p>

            <form method="POST" action="{{ route('admin.events.update-info', $event) }}" class="space-y-3">
                @csrf
                @method('PUT')
                @php($info = $event->info ?? [])

                <x-input-label for="venue" value="Venue" />
                <x-text-input id="venue" name="venue" type="text" class="block w-full" :value="$info['venue'] ?? ''" />

                <x-input-label for="important_links" value="Important links (one per line)" />
                <textarea id="important_links" name="important_links" rows="3" class="block w-full border-line rounded-md">{{ $info['important_links'] ?? '' }}</textarea>

                <x-input-label for="wifi" value="Wifi details" />
                <x-text-input id="wifi" name="wifi" type="text" class="block w-full" :value="$info['wifi'] ?? ''" />

                <x-input-label for="registration_info" value="Registration info" />
                <textarea id="registration_info" name="registration_info" rows="2" class="block w-full border-line rounded-md">{{ $info['registration_info'] ?? '' }}</textarea>

                <x-input-label for="contributor_day_location" value="Contributor Day location" />
                <x-text-input id="contributor_day_location" name="contributor_day_location" type="text" class="block w-full" :value="$info['contributor_day_location'] ?? ''" />

                <x-input-label for="code_of_conduct_url" value="Code of conduct URL" />
                <x-text-input id="code_of_conduct_url" name="code_of_conduct_url" type="url" class="block w-full" :value="$info['code_of_conduct_url'] ?? ''" />

                <x-input-label for="emergency_contact" value="Emergency / contact info" />
                <textarea id="emergency_contact" name="emergency_contact" rows="2" class="block w-full border-line rounded-md">{{ $info['emergency_contact'] ?? '' }}</textarea>

                <x-input-label for="social_event_info" value="Social event info" />
                <textarea id="social_event_info" name="social_event_info" rows="2" class="block w-full border-line rounded-md">{{ $info['social_event_info'] ?? '' }}</textarea>

                <x-input-label for="nearby_venue_info" value="Nearby venue info" />
                <textarea id="nearby_venue_info" name="nearby_venue_info" rows="2" class="block w-full border-line rounded-md">{{ $info['nearby_venue_info'] ?? '' }}</textarea>

                <div class="flex justify-end">
                    <button type="submit" class="px-4 py-2 bg-maroon text-white text-sm font-medium rounded-md hover:bg-maroon-dark">
                        Save event information
                    </button>
                </div>
            </form>
        </div>

        @if ($event->status === 'draft')
            <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-maroon/20 p-6">
                <h3 class="text-lg font-medium text-ink mb-2">Delete event</h3>
                <p class="text-sm text-muted mb-4">
                    Only possible while this event is still a draft with nothing ingested — once it's
                    approved/active/archived, use the status field above to archive it instead.
                </p>
                <form method="POST" action="{{ route('admin.events.destroy', $event) }}"
                      onsubmit="return confirm('Delete this draft event? This cannot be undone.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="px-4 py-2 bg-maroon text-white text-sm font-medium rounded-md hover:bg-maroon-dark">
                        Delete draft event
                    </button>
                </form>
            </div>
        @endif
    </div>
</x-app-layout>
