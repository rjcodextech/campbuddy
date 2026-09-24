@php
    $linkLabels = ['twitter' => 'X / Twitter', 'linkedin' => 'LinkedIn', 'website' => 'Website'];
@endphp

<x-app-layout title="Roster" :subtitle="$event->display_name"
              :breadcrumbs="[['Events', route('admin.events.index')], [$event->display_name, route('admin.events.edit', $event)], ['Roster']]">
    <x-admin.event-nav :event="$event" current="roster" />

    <div class="max-w-5xl space-y-6">
        <x-alert type="info">
            This mirrors the event's public Attendees page. <strong>Suppress</strong> hides someone from the attendee
            app (and it stays hidden when the roster is re-imported); <strong>Restore</strong> brings them back.
            <strong>In discovery</strong> means someone picked this name as themselves in attendee discovery — if the
            real person says it wasn't them, <strong>Unlink</strong> frees the name.
        </x-alert>

        <x-card flush>
            <form method="GET" action="{{ route('admin.events.roster.index', $event) }}" role="search"
                  class="flex flex-wrap items-center gap-2 border-b border-line px-5 py-4 sm:px-6">
                <label for="roster-search" class="sr-only">Search by name</label>
                <div class="relative min-w-0 flex-1 basis-64">
                    <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-muted" />
                    <input id="roster-search" type="search" name="q" value="{{ $q }}" placeholder="Search by name…" class="cb-input pl-10">
                </div>
                <x-button variant="secondary">Search</x-button>
                @if ($q !== '')
                    <x-button :href="route('admin.events.roster.index', $event)" variant="link" class="px-2">Clear</x-button>
                @endif
            </form>

            <x-table>
                <x-slot:head>
                    <th>Name</th>
                    <th class="hidden sm:table-cell">Links</th>
                    <th>Status</th>
                    <th class="w-px"><span class="sr-only">Actions</span></th>
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
                        </td>
                        <td class="hidden sm:table-cell">
                            <div class="flex flex-wrap gap-1">
                                @forelse (collect($entry->links)->pluck('type')->unique() as $type)
                                    <x-badge>{{ $linkLabels[$type] ?? ucfirst($type) }}</x-badge>
                                @empty
                                    <span class="text-muted">—</span>
                                @endforelse
                            </div>
                        </td>
                        <td>
                            @if ($entry->is_suppressed)
                                <x-badge variant="danger">Suppressed</x-badge>
                            @else
                                <x-badge variant="success">Visible</x-badge>
                            @endif
                            @if ($entry->in_discovery)
                                <x-badge variant="info">In discovery</x-badge>
                            @endif
                        </td>
                        <td class="text-right">
                            <div class="flex justify-end gap-2">
                            @if ($entry->in_discovery)
                                <x-action-form :action="route('admin.events.roster.release-claim', [$event, $entry])" variant="secondary" size="sm"
                                               :confirm="'Unlink '.$entry->name.' from the discovery profile that claimed this name?'">Unlink</x-action-form>
                            @endif
                            @if ($entry->is_suppressed)
                                <x-action-form :action="route('admin.events.roster.unsuppress', [$event, $entry])" size="sm">Restore</x-action-form>
                            @else
                                <x-action-form :action="route('admin.events.roster.suppress', [$event, $entry])" variant="danger-outline" size="sm">Suppress</x-action-form>
                            @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <x-table.empty :colspan="4" icon="users" :title="$q !== '' ? 'No one matches “'.$q.'”' : 'No attendees imported yet'">
                        @if ($q === '')
                            The roster is imported daily from the event's public Attendees page.
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
</x-app-layout>
