@php
    $filtering = $filters['manager'] || $filters['event'];
    $sections = ['details' => 'Details', 'information' => 'Information', 'quests' => 'Quests'];
@endphp

<x-app-layout title="Manager activity" subtitle="What event managers changed, newest first. Entries are kept for {{ $keepDays }} days."
              :breadcrumbs="[['Event managers', route('admin.event-managers.index')], ['Activity']]">
    <form method="GET" action="{{ route('admin.event-managers.activity') }}" role="search" aria-label="Filter activity" x-data
          class="mb-4 rounded-xl border border-line bg-white p-4 shadow-sm">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[repeat(2,minmax(0,1fr))_auto] lg:items-end">
            <x-form.select name="manager" label="Manager" placeholder="Everyone" :use-old="false" :value="(string) $filters['manager']" :options="$managers->all()"
                           x-on:change="$el.form.requestSubmit()" />

            <x-form.select name="event" label="Event" placeholder="All events" :use-old="false" :value="(string) $filters['event']" :options="$events->all()"
                           x-on:change="$el.form.requestSubmit()" />

            <div class="flex items-center gap-2">
                <x-button icon="search">Filter</x-button>
                @if ($filtering)
                    <x-button :href="route('admin.event-managers.activity')" variant="secondary">Reset</x-button>
                @endif
            </div>
        </div>
    </form>

    <x-card flush>
        <x-table>
            <x-slot:head>
                <th>When</th>
                <th>Manager</th>
                <th class="hidden sm:table-cell">Event</th>
                <th class="hidden md:table-cell">Section</th>
                <th>What changed</th>
            </x-slot:head>

            @forelse ($changes as $change)
                <tr class="align-top">
                    <td class="whitespace-nowrap text-muted" title="{{ $change->created_at }}">{{ $change->created_at->diffForHumans() }}</td>
                    <td class="font-medium">
                        @if ($change->event_manager_id)
                            <a href="{{ route('admin.event-managers.edit', $change->event_manager_id) }}" class="hover:text-maroon hover:underline">{{ $change->manager_name }}</a>
                        @else
                            {{ $change->manager_name }} <span class="text-xs font-normal text-muted">(account deleted)</span>
                        @endif
                        <span class="mt-0.5 block text-xs font-normal text-muted sm:hidden">{{ $change->event?->display_name }}</span>
                    </td>
                    <td class="hidden sm:table-cell">
                        @if ($change->event)
                            <a href="{{ route('admin.events.edit', $change->event) }}" class="hover:text-maroon hover:underline">{{ $change->event->display_name }}</a>
                        @endif
                    </td>
                    <td class="hidden md:table-cell"><x-badge variant="info">{{ $sections[$change->section] ?? $change->section }}</x-badge></td>
                    <td class="min-w-[12rem] break-words text-muted">{{ $change->summary }}</td>
                </tr>
            @empty
                <x-table.empty :colspan="5" icon="{{ $filtering ? 'search' : 'pencil' }}" title="{{ $filtering ? 'No changes match these filters' : 'No changes yet' }}">
                    @if ($filtering)
                        <a href="{{ route('admin.event-managers.activity') }}" class="font-medium text-maroon hover:underline">Reset the filters</a>.
                    @else
                        When an event manager saves something, it shows up here.
                    @endif
                </x-table.empty>
            @endforelse
        </x-table>

        @if ($changes->hasPages())
            <x-slot:footer>{{ $changes->links() }}</x-slot:footer>
        @endif
    </x-card>
</x-app-layout>
