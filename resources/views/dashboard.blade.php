<x-app-layout title="Dashboard" subtitle="What needs your attention across every WordCamp.">
    <x-slot:actions>
        <x-action-form :action="route('admin.events.discover')" icon="search" variant="secondary">Discover WordCamps</x-action-form>
        <x-button :href="route('admin.events.create')" icon="plus">Add event</x-button>
    </x-slot:actions>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat label="Events" :value="$eventCount" icon="calendar" :href="route('admin.events.index')"
                :hint="($eventsByStatus['active'] ?? 0).' active · '.($eventsByStatus['archived'] ?? 0).' archived'" />
        <x-stat label="Awaiting approval" :value="$eventsByStatus['draft'] ?? 0" icon="inbox" :href="route('admin.events.index')"
                hint="Draft events found by discovery" />
        <x-stat label="Attendees listed" :value="number_format($rosterCount)" icon="users"
                hint="Public, not suppressed" />
        <x-stat label="Deal leads" :value="number_format($leadCount)" icon="tag"
                :hint="$recentLeadCount.' in the last 7 days · '.$activeDeals.' active '.\Illuminate\Support\Str::plural('deal', $activeDeals)" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <x-card title="Recently updated events" flush class="lg:col-span-2">
            <x-slot:actions>
                <x-button :href="route('admin.events.index')" variant="link" size="sm">View all</x-button>
            </x-slot:actions>

            <x-table>
                <x-slot:head>
                    <th>Event</th>
                    <th>Status</th>
                    <th class="hidden sm:table-cell">Updated</th>
                    <th class="w-px"><span class="sr-only">Actions</span></th>
                </x-slot:head>

                @forelse ($recentEvents as $event)
                    <tr>
                        <td>
                            <a href="{{ route('admin.events.edit', $event) }}" class="font-medium hover:text-maroon hover:underline">{{ $event->display_name }}</a>
                            <div class="text-xs text-muted">{{ $event->slug }}</div>
                        </td>
                        <td><x-event-status :status="$event->status" /></td>
                        <td class="hidden whitespace-nowrap text-muted sm:table-cell">{{ $event->updated_at->diffForHumans() }}</td>
                        <td class="text-right">
                            <x-button :href="route('admin.events.edit', $event)" variant="secondary" size="sm">Manage</x-button>
                        </td>
                    </tr>
                @empty
                    <x-table.empty :colspan="4" icon="calendar" title="No events yet">
                        Add your first WordCamp, or run discovery to find upcoming ones.
                    </x-table.empty>
                @endforelse
            </x-table>
        </x-card>

        <div class="space-y-6">
            <x-card title="Needs approval" description="Drafts found by discovery.">
                @forelse ($draftEvents as $event)
                    <a href="{{ route('admin.events.edit', $event) }}"
                       class="-mx-2 flex items-center justify-between gap-3 rounded-md px-2 py-2 text-sm hover:bg-paper-soft focus:outline-none focus-visible:ring-2 focus-visible:ring-maroon/40">
                        <span class="truncate font-medium">{{ $event->display_name }}</span>
                        <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-muted" />
                    </a>
                @empty
                    <p class="text-sm text-muted">Nothing waiting — you're all caught up.</p>
                @endforelse
            </x-card>

            <x-card title="Ingestion problems" description="Recent fetches that didn't finish cleanly.">
                @forelse ($failedFetches as $log)
                    <div class="border-t border-line py-2.5 first:border-t-0 first:pt-0 last:pb-0">
                        <div class="flex items-center justify-between gap-2">
                            <span class="truncate text-sm font-medium">{{ $log->event?->display_name ?? 'All events' }}</span>
                            <x-badge variant="danger">{{ $log->status }}</x-badge>
                        </div>
                        <p class="mt-0.5 line-clamp-2 text-xs text-muted">{{ $log->message ?: $log->job_type }}</p>
                        <p class="mt-0.5 text-xs text-muted/70">{{ $log->fetched_at->diffForHumans() }}</p>
                    </div>
                @empty
                    <p class="flex items-center gap-2 text-sm text-muted">
                        <x-icon name="check-circle" class="h-5 w-5 text-teal" /> No recent failures.
                    </p>
                @endforelse
            </x-card>
        </div>
    </div>
</x-app-layout>
