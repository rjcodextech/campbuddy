@php
    $filtering = $filters['q'] !== '' || $filters['status'] || $filters['visibility'] || $filters['when'];
    $tableView = $view === 'table';
@endphp

<x-app-layout title="Events" subtitle="Every WordCamp CampBuddy knows about — the one happening soonest comes first."
              :breadcrumbs="[['Events']]">
    <x-slot:actions>
        <x-action-form :action="route('admin.events.discover')" icon="search" variant="secondary">Discover WordCamps</x-action-form>
        <x-button :href="route('admin.events.create')" icon="plus">Add event</x-button>
    </x-slot:actions>

    {{-- Filters: a plain GET form, so a filtered list can be bookmarked or shared. The selects submit by themselves. --}}
    <form method="GET" action="{{ route('admin.events.index') }}" role="search" aria-label="Filter events" x-data
          class="mb-4 rounded-xl border border-line bg-white p-4 shadow-sm">
        @if ($tableView)
            <input type="hidden" name="view" value="table">
        @endif

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,2fr)_repeat(3,minmax(0,1fr))_auto] lg:items-end">
            <x-form.input name="q" type="search" label="Search" :value="$filters['q']" :use-old="false"
                          placeholder="Name, slug or #hashtag" autocomplete="off" />

            <x-form.select name="status" label="Status" placeholder="Any status" :use-old="false" :value="$filters['status']"
                           :options="['draft' => 'Draft', 'approved' => 'Approved', 'active' => 'Active', 'archived' => 'Archived']"
                           x-on:change="$el.form.requestSubmit()" />

            <x-form.select name="visibility" label="Visibility" placeholder="Any visibility" :use-old="false" :value="$filters['visibility']"
                           :options="['visible' => 'Visible in the app', 'hidden' => 'Hidden']"
                           x-on:change="$el.form.requestSubmit()" />

            <x-form.select name="when" label="When" placeholder="Any date" :use-old="false" :value="$filters['when']"
                           :options="\App\Support\EventListing::WHEN"
                           x-on:change="$el.form.requestSubmit()" />

            <div class="flex items-center gap-2 sm:col-span-2 lg:col-span-1">
                <x-button icon="search">Filter</x-button>
                @if ($filtering)
                    <x-button :href="route('admin.events.index', $tableView ? ['view' => 'table'] : [])" variant="secondary">Reset</x-button>
                @endif
            </div>
        </div>
    </form>

    <div class="mb-3 flex flex-wrap items-center justify-between gap-x-4 gap-y-2 text-sm text-muted">
        <p>
            {{ number_format($events->total()) }} {{ \Illuminate\Support\Str::plural('event', $events->total()) }}@if ($filtering) match @endif
            @if ($highlightedIds !== [])
                <span class="ml-3 inline-flex items-center gap-2">
                    <span class="h-3 w-1 rounded-full bg-maroon" aria-hidden="true"></span>
                    Highlighted: the next {{ count($highlightedIds) }} by date
                </span>
            @endif
        </p>

        {{-- Cards or the compact table; the same filters and paging in both. --}}
        <div class="inline-flex rounded-lg border border-line bg-white p-0.5 text-sm font-medium" role="group" aria-label="Show events as">
            <a href="{{ request()->fullUrlWithQuery(['view' => null, 'page' => null]) }}"
               @if (! $tableView) aria-current="true" @endif
               @class([
                   'inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-maroon/40',
                   'bg-maroon text-white' => ! $tableView,
                   'text-muted hover:text-ink' => $tableView,
               ])>
                <x-icon name="dashboard" class="h-4 w-4" /> Cards
            </a>
            <a href="{{ request()->fullUrlWithQuery(['view' => 'table', 'page' => null]) }}"
               @if ($tableView) aria-current="true" @endif
               @class([
                   'inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-maroon/40',
                   'bg-maroon text-white' => $tableView,
                   'text-muted hover:text-ink' => ! $tableView,
               ])>
                <x-icon name="menu" class="h-4 w-4" /> Table
            </a>
        </div>
    </div>

    @if (! $tableView)
        @if ($events->isEmpty())
            <x-card>
                <div class="py-8 text-center">
                    @if ($filtering)
                        <x-icon name="search" class="mx-auto h-8 w-8 text-muted/50" />
                        <p class="mt-2 text-sm font-medium">No events match these filters</p>
                        <p class="mx-auto mt-1 max-w-sm text-sm text-muted">Try a different search, or <a href="{{ route('admin.events.index') }}" class="font-medium text-maroon hover:underline">reset the filters</a>.</p>
                    @else
                        <x-icon name="calendar" class="mx-auto h-8 w-8 text-muted/50" />
                        <p class="mt-2 text-sm font-medium">No events yet</p>
                        <p class="mx-auto mt-1 max-w-sm text-sm text-muted">Add a WordCamp by hand, or run discovery to find upcoming ones — they land here as drafts.</p>
                    @endif
                </div>
            </x-card>
        @else
            <ul class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($events as $event)
                    @include('admin.events._card')
                @endforeach
            </ul>

            @if ($events->hasPages())
                <div class="mt-6">{{ $events->links() }}</div>
            @endif
        @endif
    @else
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
                    @php
                        $soon = in_array($event->id, $highlightedIds, true);
                        $timing = \App\Support\EventListing::timing($event, $today);
                    @endphp
                    <tr @class(['cb-row-soon' => $soon])>
                        {{-- The transparent bar keeps every row's text on the same line as the highlighted ones. --}}
                        <td @class(['border-l-4 border-transparent' => ! $soon])>
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <a href="{{ route('admin.events.edit', $event) }}" class="font-medium hover:text-maroon hover:underline">{{ $event->display_name }}</a>
                                @if ($soon && $timing['state'] === 'ongoing')
                                    <x-badge variant="success">Happening now</x-badge>
                                @elseif ($event->id === $nextUpId)
                                    <x-badge variant="brand">Next up</x-badge>
                                @endif
                            </div>
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
                        <td class="hidden whitespace-nowrap md:table-cell">
                            @if ($event->starts_on)
                                <span class="text-muted">{{ $event->starts_on->format('j M Y') }}@if ($event->ends_on && ! $event->ends_on->isSameDay($event->starts_on)) – {{ $event->ends_on->format('j M Y') }}@endif</span>
                                @if ($timing)
                                    <div @class(['text-xs', 'font-medium text-maroon' => $timing['state'] !== 'past', 'text-muted' => $timing['state'] === 'past'])>{{ $timing['label'] }}</div>
                                @endif
                            @else
                                <span class="text-muted">—</span>
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
                    @if ($filtering)
                        <x-table.empty :colspan="7" icon="search" title="No events match these filters">
                            Try a different search, or <a href="{{ route('admin.events.index', ['view' => 'table']) }}" class="font-medium text-maroon hover:underline">reset the filters</a>.
                        </x-table.empty>
                    @else
                        <x-table.empty :colspan="7" icon="calendar" title="No events yet">
                            Add a WordCamp by hand, or run discovery to find upcoming ones — they land here as drafts.
                        </x-table.empty>
                    @endif
                @endforelse
            </x-table>

            @if ($events->hasPages())
                <x-slot:footer>{{ $events->links() }}</x-slot:footer>
            @endif
        </x-card>
    @endif
</x-app-layout>
