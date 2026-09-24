<x-app-layout title="Events" subtitle="Every WordCamp CampBuddy knows about, and where it is in its lifecycle."
              :breadcrumbs="[['Events']]">
    <x-slot:actions>
        <x-action-form :action="route('admin.events.discover')" icon="search" variant="secondary">Discover WordCamps</x-action-form>
        <x-button :href="route('admin.events.create')" icon="plus">Add event</x-button>
    </x-slot:actions>

    <x-card flush>
        <x-table>
            <x-slot:head>
                <th>Event</th>
                <th>Status</th>
                <th class="hidden md:table-cell">Visibility</th>
                <th class="hidden md:table-cell">Dates</th>
                <th class="hidden lg:table-cell">Data</th>
                <th class="hidden text-right sm:table-cell">Attendees</th>
                <th class="w-px"><span class="sr-only">Actions</span></th>
            </x-slot:head>

            @forelse ($events as $event)
                <tr>
                    <td>
                        <a href="{{ route('admin.events.edit', $event) }}" class="font-medium hover:text-maroon hover:underline">{{ $event->display_name }}</a>
                        <div class="text-xs text-muted">{{ $event->slug }}</div>
                    </td>
                    <td><x-event-status :status="$event->status" /></td>
                    <td class="hidden md:table-cell">
                        @if ($event->is_visible)
                            <x-badge variant="success">Visible</x-badge>
                        @else
                            <x-badge>Hidden</x-badge>
                        @endif
                    </td>
                    <td class="hidden whitespace-nowrap text-muted md:table-cell">
                        @if ($event->starts_on)
                            {{ $event->starts_on->format('j M Y') }}@if ($event->ends_on && ! $event->ends_on->isSameDay($event->starts_on)) – {{ $event->ends_on->format('j M Y') }}@endif
                        @else
                            —
                        @endif
                    </td>
                    <td class="hidden whitespace-nowrap lg:table-cell">
                        @if ($fetch = $lastFetches[$event->id] ?? null)
                            <x-fetch-status :status="$fetch->status" />
                            <span class="ml-1 text-xs text-muted">{{ $fetch->fetched_at->diffForHumans(short: true) }}</span>
                        @else
                            <span class="text-xs text-muted">Not fetched</span>
                        @endif
                    </td>
                    <td class="hidden text-right tabular-nums sm:table-cell">{{ number_format($event->attendee_roster_count) }}</td>
                    <td class="text-right">
                        <x-button :href="route('admin.events.edit', $event)" variant="secondary" size="sm">Manage</x-button>
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="7" icon="calendar" title="No events yet">
                    Add a WordCamp by hand, or run discovery to find upcoming ones — they land here as drafts.
                </x-table.empty>
            @endforelse
        </x-table>

        @if ($events->hasPages())
            <x-slot:footer>{{ $events->links() }}</x-slot:footer>
        @endif
    </x-card>
</x-app-layout>
