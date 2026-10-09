<x-manager-layout title="Attendees" :subtitle="$event->display_name"
                  :breadcrumbs="[['My events', route('manager.dashboard')], [$event->display_name, route('manager.events.details', $event)], ['Attendees']]">
    <x-manager.event-nav :event="$event" current="attendees" />

    <div class="max-w-5xl space-y-6">
        <x-alert type="info">
            The attendee list from your WordCamp's public Attendees page. Mark who is an organizer, speaker, volunteer,
            media partner, sponsor or table lead: attendees see these as badges on the list in CampBuddy. "auto" roles
            come from your site (speaker and organizer pages, volunteers); you can take any of them off.
        </x-alert>

        <x-card flush>
            <form method="GET" action="{{ route('manager.events.attendees', $event) }}" role="search"
                  class="flex flex-wrap items-center gap-2 border-b border-line px-5 py-4 sm:px-6">
                <label for="attendee-search" class="sr-only">Search by name</label>
                <div class="relative min-w-0 flex-1 basis-56">
                    <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-muted" />
                    <input id="attendee-search" type="search" name="q" value="{{ $q }}" placeholder="Search by name…" class="cb-input pl-10">
                </div>
                <label for="attendee-role" class="sr-only">Role</label>
                <select id="attendee-role" name="role" class="cb-input w-auto">
                    <option value="">Everyone</option>
                    @foreach (\App\Support\RosterRoles::LABELS as $key => $label)
                        <option value="{{ $key }}" @selected($role === $key)>{{ $label }}s</option>
                    @endforeach
                </select>
                <x-button variant="secondary">Show</x-button>
                @if ($q !== '' || $role !== '')
                    <x-button :href="route('manager.events.attendees', $event)" variant="link" class="px-2">Clear</x-button>
                @endif
            </form>

            <x-table>
                <x-slot:head>
                    <th>Name</th>
                    <th class="w-px"><span class="sr-only">Roles</span></th>
                </x-slot:head>

                @forelse ($roster as $entry)
                    <tr>
                        <td>
                            <div class="flex items-center gap-3">
                                @if ($entry->gravatar_url)
                                    <img src="{{ $entry->gravatar_url }}" alt="" class="h-8 w-8 rounded-full bg-paper-soft object-cover">
                                @else
                                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-paper-soft text-xs font-semibold text-muted" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($entry->name, 0, 1)) }}</span>
                                @endif
                                <span class="font-medium">{{ $entry->name }}</span>
                            </div>
                            @if (! empty($roles[$entry->id]['roles']))
                                <div class="mt-1 flex flex-wrap gap-1 pl-11">@include('partials.roster-role-badges', ['roles' => $roles[$entry->id]['roles']])</div>
                            @endif
                        </td>
                        <td class="text-right">
                            @if ($marksReady)
                                @include('partials.roster-roles-form', [
                                    'action' => route('manager.events.attendees.roles', [$event, $entry->id]),
                                    'entry' => $entry,
                                    'current' => $roles[$entry->id]['roles'] ?? [],
                                    'auto' => $autoRoles[$entry->id]['roles'] ?? [],
                                ])
                            @endif
                        </td>
                    </tr>
                @empty
                    <x-table.empty :colspan="2" icon="users" :title="$q !== '' || $role !== '' ? 'No one matches' : 'No attendees imported yet'">
                        @if ($q === '' && $role === '')
                            The list is imported every night from your site's public Attendees page.
                        @endif
                    </x-table.empty>
                @endforelse
            </x-table>

            @if ($roster->hasPages())
                <x-slot:footer>
                    <span class="mr-auto text-xs text-muted">Showing {{ $roster->firstItem() }}–{{ $roster->lastItem() }} of {{ number_format($roster->total()) }}</span>
                    {{ $roster->links() }}
                </x-slot:footer>
            @endif
        </x-card>
    </div>
</x-manager-layout>
