<x-app-layout title="Dashboard" subtitle="What needs your attention across every WordCamp.">
    <x-slot:actions>
        <x-action-form :action="route('admin.events.discover')" icon="search" variant="secondary">Discover WordCamps</x-action-form>
        <x-button :href="route('admin.events.create')" icon="plus">Add event</x-button>
    </x-slot:actions>

    {{-- What's silently broken on this install (system checks + failed fetches) is
    on its own page — Errors — so the dashboard stays a glance. One line says
    whether it's worth a look; it turns red when a system check is failing. --}}
    @if ($errorSummary['total'] > 0)
        <a href="{{ route('admin.errors.index') }}"
           @class([
               'mb-6 flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg border px-4 py-3 text-sm transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-maroon/40',
               'border-danger/30 bg-danger-soft hover:border-danger/50' => $errorSummary['critical'] > 0,
               'border-gold/40 bg-gold/10 hover:border-gold' => $errorSummary['critical'] === 0,
           ])>
            <x-icon :name="$errorSummary['critical'] > 0 ? 'exclamation-circle' : 'exclamation-triangle'"
                    class="h-5 w-5 shrink-0 {{ $errorSummary['critical'] > 0 ? 'text-danger' : 'text-gold' }}" />
            <span class="min-w-0 flex-1">
                <span class="font-semibold">{{ $errorSummary['total'] }} {{ \Illuminate\Support\Str::plural('problem', $errorSummary['total']) }} need attention</span>
                <span class="text-muted">
                    — {{ $errorSummary['system'] }} system {{ \Illuminate\Support\Str::plural('check', $errorSummary['system']) }}@if ($errorSummary['critical'] > 0) ({{ $errorSummary['critical'] }} critical)@endif,
                    {{ $errorSummary['fetch'] }} {{ \Illuminate\Support\Str::plural('fetch problem', $errorSummary['fetch']) }} in the last 7 days
                </span>
            </span>
            <span class="inline-flex items-center gap-1 font-medium text-maroon">View errors <x-icon name="chevron-right" class="h-4 w-4" /></span>
        </a>
    @else
        <p class="mb-6 flex items-center gap-2 text-sm text-muted">
            <x-icon name="check-circle" class="h-5 w-5 text-teal" />
            No problems — every check passes and every fetch finished cleanly.
            <a href="{{ route('admin.errors.index') }}" class="font-medium text-maroon hover:underline">Errors</a>
        </p>
    @endif

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
        <div class="space-y-6 lg:col-span-2">
            <x-card title="Recently updated events" flush>
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

        </div>

        <div class="space-y-6">
            @if ($draftEvents->isNotEmpty())
                <x-card title="Needs approval" description="Drafts found by discovery.">
                    @foreach ($draftEvents as $event)
                        <a href="{{ route('admin.events.edit', $event) }}"
                           class="-mx-2 flex items-center justify-between gap-3 rounded-md px-2 py-2 text-sm hover:bg-paper-soft focus:outline-none focus-visible:ring-2 focus-visible:ring-maroon/40">
                            <span class="truncate font-medium">{{ $event->display_name }}</span>
                            <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-muted" />
                        </a>
                    @endforeach
                </x-card>
            @endif

            {{-- Two separate jobs, two buttons: fresh data from the WordCamp
            sites (DataRefresher) vs. dropping saved copies (CachePurger). --}}
            <x-card title="Event data" description="Schedule, speakers, sponsors and event info from each WordCamp site.">
                <p class="text-sm text-muted">
                    Updates by itself every 15 minutes. Nothing is cleared first — new items are added, changes
                    updated, removed ones dropped — and open apps update on their own.
                </p>
                <p class="mt-3 border-t border-line pt-3 text-xs text-muted">
                    @if ($lastDataFetch)
                        <span class="font-medium text-ink">Last fetched {{ $lastDataFetch->fetched_at->diffForHumans() }}</span>
                        @if ($lastDataFetch->event) — {{ $lastDataFetch->event->display_name }} @endif
                    @else
                        Not fetched yet.
                    @endif
                </p>

                <x-slot:footer>
                    <x-action-form :action="route('admin.data.refresh')" icon="refresh" variant="primary">Refresh event data now</x-action-form>
                </x-slot:footer>
            </x-card>

            <x-card title="Cache" description="Site or installed app showing an old page?">
                <p class="text-sm text-muted">
                    Clears the server's caches{{ $cloudflareConfigured ? ' and Cloudflare' : '' }} and makes every open app reload.
                    It doesn't fetch data, and attendees' saved sessions and Camp Cards aren't touched.
                </p>
                @unless ($cloudflareConfigured)
                    <p class="mt-2 text-xs text-muted">Using Cloudflare? Set <code>CLOUDFLARE_ZONE_ID</code> and <code>CLOUDFLARE_API_TOKEN</code> to purge it here too.</p>
                @endunless

                <p class="mt-3 border-t border-line pt-3 text-xs text-muted">
                    @if ($lastPurge)
                        <span class="font-medium text-ink">Last cleared {{ \Illuminate\Support\Carbon::parse($lastPurge['purged_at'])->diffForHumans() }}</span>
                        @if (! empty($lastPurge['by'])) by {{ $lastPurge['by'] }} @endif
                        @if (! empty($lastPurge['summary'])) — {{ $lastPurge['summary'] }} @endif
                    @else
                        Not cleared yet.
                    @endif
                </p>

                <x-slot:footer>
                    <x-action-form :action="route('admin.cache.purge')" icon="refresh" variant="secondary"
                                   confirm="Clear all caches? Every open app will reload.">
                        Clear cache
                    </x-action-form>
                </x-slot:footer>
            </x-card>
        </div>
    </div>
</x-app-layout>
