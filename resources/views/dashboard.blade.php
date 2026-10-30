<x-app-layout title="Dashboard" subtitle="What needs your attention across every WordCamp.">
    <x-slot:actions>
        <x-action-form :action="route('admin.events.discover')" icon="search" variant="secondary">Discover WordCamps</x-action-form>
        <x-button :href="route('admin.events.create')" icon="plus">Add event</x-button>
    </x-slot:actions>

    @if ($pendingDealSubmissionsCount > 0)
        <a href="{{ route('admin.deal-submissions.index') }}"
           class="mb-6 flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg border border-gold/40 bg-gold/10 px-4 py-3 text-sm transition-colors hover:border-gold focus:outline-hidden focus-visible:ring-2 focus-visible:ring-maroon/40">
            <x-icon name="tag" class="h-5 w-5 shrink-0 text-gold" />
            <span class="min-w-0 flex-1">
                <span class="font-semibold">{{ $pendingDealSubmissionsCount }} sponsor {{ \Illuminate\Support\Str::plural('deal submission', $pendingDealSubmissionsCount) }} {{ $pendingDealSubmissionsCount === 1 ? 'is' : 'are' }} waiting for verification</span>
                <span class="text-muted">— staged in review queue until approved into event deals</span>
            </span>
            <span class="inline-flex items-center gap-1 font-medium text-maroon-text">Review submissions <x-icon name="chevron-right" class="h-4 w-4" /></span>
        </a>
    @endif

    {{-- What's silently broken on this install (system checks + failed fetches) is
    on its own page — Errors — so the dashboard stays a glance. One line says
    whether it's worth a look; it turns red when a system check is failing. --}}
    @if ($errorSummary['total'] > 0)
        <a href="{{ route('admin.errors.index') }}"
           @class([
               'mb-6 flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg border px-4 py-3 text-sm transition-colors focus:outline-hidden focus-visible:ring-2 focus-visible:ring-maroon/40',
               'border-danger/30 bg-danger-soft hover:border-danger/50' => $errorSummary['critical'] > 0,
               'border-gold/40 bg-gold/10 hover:border-gold' => $errorSummary['critical'] === 0,
           ])>
            <x-icon :name="$errorSummary['critical'] > 0 ? 'exclamation-circle' : 'exclamation-triangle'"
                    class="h-5 w-5 shrink-0 motion-safe:animate-pulse {{ $errorSummary['critical'] > 0 ? 'text-danger' : 'text-gold' }}" />
            <span class="min-w-0 flex-1">
                <span class="font-semibold">{{ $errorSummary['total'] }} {{ \Illuminate\Support\Str::plural('problem', $errorSummary['total']) }} {{ $errorSummary['total'] === 1 ? 'needs' : 'need' }} attention</span>
                <span class="text-muted">
                    — {{ $errorSummary['system'] }} system {{ \Illuminate\Support\Str::plural('check', $errorSummary['system']) }}@if ($errorSummary['critical'] > 0) ({{ $errorSummary['critical'] }} critical)@endif,
                    {{ $errorSummary['fetch'] }} {{ \Illuminate\Support\Str::plural('fetch problem', $errorSummary['fetch']) }} in the last 7 days
                </span>
            </span>
            <span class="inline-flex items-center gap-1 font-medium text-maroon-text">View errors <x-icon name="chevron-right" class="h-4 w-4" /></span>
        </a>
    @else
        <p class="mb-6 flex items-center gap-2 text-sm text-muted">
            <x-icon name="check-circle" class="h-5 w-5 text-teal" />
            No problems — every check passes and every fetch finished cleanly.
            <a href="{{ route('admin.errors.index') }}" class="font-medium text-maroon-text hover:underline">Errors</a>
        </p>
    @endif

    @php
        // "Live now", "Tomorrow" or "12 days" — from the server's today.
        $countdown = function ($event) {
            $days = (int) now()->startOfDay()->diffInDays($event->starts_on, false);

            return match (true) {
                $days <= 0 => ['Live', 'now'],
                $days === 1 => ['1', 'day to go'],
                default => [(string) $days, 'days to go'],
            };
        };
        $dates = fn ($event) => $event->ends_on && ! $event->ends_on->isSameDay($event->starts_on)
            ? $event->starts_on->format('j M').' – '.$event->ends_on->format('j M Y')
            : $event->starts_on->format('j M Y');
        $next = $nextEvents->first();
    @endphp

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- The next WordCamp, big, with a countdown; the few after it beneath. --}}
        <section class="lg:col-span-2" aria-labelledby="coming-up">
            <h2 id="coming-up" class="mb-3 text-xs font-semibold uppercase tracking-wide text-muted">Coming up</h2>

            @if ($next)
                @php([$big, $small] = $countdown($next))
                <div class="cb-hero relative overflow-hidden rounded-2xl bg-navy p-6 text-white shadow-md sm:p-7">
                    <span class="cb-hero-glow" aria-hidden="true"></span>

                    <div class="relative flex flex-wrap items-start justify-between gap-6">
                        <div class="flex min-w-0 items-start gap-4">
                            <span class="cb-logo-in flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white p-1.5 shadow-xs">
                                @if ($next->logoUrl())
                                    <img src="{{ $next->logoUrl() }}" alt="" class="h-full w-full object-contain">
                                @else
                                    <x-icon name="calendar" class="h-7 w-7 text-maroon-text" />
                                @endif
                            </span>
                            <div class="min-w-0">
                                <p class="text-xs font-medium uppercase tracking-[0.15em] text-white/60">Next WordCamp</p>
                                <h3 class="mt-1 wrap-break-word text-xl font-semibold tracking-tight sm:text-2xl">{{ $next->display_name }}</h3>
                                <p class="mt-1 text-sm text-white/70">{{ $dates($next) }}</p>
                            </div>
                        </div>

                        <div class="cb-enter text-right" aria-label="{{ $big }} {{ $small }}">
                            <span class="block text-5xl font-bold tabular-nums leading-none tracking-tight text-gold">{{ $big }}</span>
                            <span class="mt-1 block text-xs font-medium uppercase tracking-wide text-white/60">{{ $small }}</span>
                        </div>
                    </div>

                    <div class="relative mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-white/10 pt-4">
                        <span class="flex items-center gap-4 text-sm text-white/80">
                            <span class="inline-flex items-center gap-1.5"><x-icon name="users" class="h-4 w-4" /> {{ number_format($next->roster_count) }} on the roster</span>
                            {{-- Light on the dark card (the paper-tinted pill was 2.8:1 here). --}}
                            <x-event-status :status="$next->status" class="bg-white/15! text-white! ring-white/30!" />
                        </span>
                        <span class="flex flex-wrap gap-2">
                            <a href="{{ route('event.home', $next) }}" target="_blank" rel="noopener"
                               class="cb-btn cb-btn-sm border-white/20 bg-white/10 text-white hover:bg-white/20 focus-visible:ring-white/60 focus-visible:ring-offset-navy">
                                <x-icon name="external" class="h-4 w-4" /> Open app
                            </a>
                            <a href="{{ route('admin.events.edit', $next) }}" class="cb-btn cb-btn-sm cb-btn-primary focus-visible:ring-offset-navy">Manage</a>
                        </span>
                    </div>
                </div>

                @if ($nextEvents->count() > 1)
                    <ul class="mt-3 grid gap-3 sm:grid-cols-3 lg:grid-cols-1 2xl:grid-cols-3">
                        @foreach ($nextEvents->skip(1) as $event)
                            @php([$big, $small] = $countdown($event))
                            <li>
                                <a href="{{ route('admin.events.edit', $event) }}"
                                   class="group flex h-full items-center gap-3 rounded-xl border border-line bg-white p-3 shadow-xs transition hover:border-maroon/40 hover:shadow-md motion-safe:hover:-translate-y-0.5 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-maroon/40">
                                    <span class="flex h-11 w-11 shrink-0 flex-col items-center justify-center rounded-lg bg-maroon/10 text-maroon-text motion-safe:transition-transform motion-safe:duration-300 motion-safe:group-hover:-rotate-6">
                                        <span class="text-base font-bold tabular-nums leading-none">{{ $big }}</span>
                                        <span class="text-xs font-semibold uppercase leading-tight">{{ $big === 'Live' ? 'now' : 'days' }}</span>
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block wrap-break-word text-sm font-medium leading-snug text-ink">{{ $event->display_name }}</span>
                                        <span class="block text-xs text-muted">{{ $dates($event) }}</span>
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            @else
                <div class="flex flex-col items-center rounded-2xl border border-dashed border-line bg-white px-6 py-10 text-center">
                    <x-icon name="calendar" class="h-8 w-8 text-muted" />
                    <p class="mt-2 font-medium">No upcoming WordCamps</p>
                    <p class="mt-1 text-sm text-muted">Approve a draft or run discovery to find the next ones.</p>
                </div>
            @endif
        </section>

        {{-- Drafts wait on an admin: the one thing here that needs a decision. --}}
        <section aria-labelledby="needs-approval">
            <h2 id="needs-approval" class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-muted">
                Needs approval
                @if ($draftCount > 0)
                    <span class="rounded-full bg-maroon-text px-2 py-0.5 text-xs font-semibold tabular-nums text-white">{{ $draftCount }}</span>
                @endif
            </h2>

            <div class="rounded-2xl border border-line bg-white p-2 shadow-xs">
                @forelse ($draftEvents as $event)
                    <a href="{{ route('admin.events.edit', $event) }}"
                       class="group flex items-center justify-between gap-3 rounded-lg px-3 py-2.5 text-sm hover:bg-paper-soft focus:outline-hidden focus-visible:ring-2 focus-visible:ring-maroon/40">
                        <span class="min-w-0">
                            <span class="block wrap-break-word font-medium leading-snug">{{ $event->display_name }}</span>
                            @if ($event->starts_on)
                                <span class="block text-xs text-muted">{{ $event->starts_on->format('j M Y') }}</span>
                            @endif
                        </span>
                        <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-muted motion-safe:transition-transform motion-safe:group-hover:translate-x-0.5" />
                    </a>
                @empty
                    <p class="flex items-center gap-2 px-3 py-6 text-sm text-muted">
                        <x-icon name="check-circle" class="h-5 w-5 text-teal" /> Nothing waiting.
                    </p>
                @endforelse

                @if ($draftCount > $draftEvents->count())
                    <a href="{{ route('admin.events.index') }}" class="block border-t border-line px-3 py-2.5 text-sm font-medium text-maroon-text hover:underline">
                        All {{ $draftCount }} drafts
                    </a>
                @endif
            </div>
        </section>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-3">
        <x-stat label="Upcoming WordCamps" :value="$upcomingCount" icon="calendar" :href="route('admin.events.index')"
                hint="Approved or active, not over yet" />
        <x-stat label="Attendees listed" :value="number_format($rosterCount)" icon="users"
                hint="Public, not suppressed" />
        <x-stat label="Deal leads" :value="number_format($leadCount)" icon="tag"
                :hint="$recentLeadCount.' in the last 7 days'" />
    </div>

    {{-- Two separate jobs, two buttons: fresh data from the WordCamp
    sites (DataRefresher) vs. dropping saved copies (CachePurger). --}}
    <section class="mt-6 grid gap-4 md:grid-cols-2" aria-label="Maintenance">
        <div class="flex flex-col justify-between gap-4 rounded-xl border border-line bg-white p-5 shadow-xs">
            <div>
                <h2 class="flex items-center gap-2 text-sm font-semibold"><x-icon name="refresh" class="h-4 w-4 text-maroon-text" /> Event data</h2>
                <p class="mt-1 text-xs text-muted">Updates by itself every 15 minutes; open apps update on their own.</p>
                <p class="mt-2 text-xs text-muted">
                    @if ($lastDataFetch)
                        <span class="font-medium text-ink">Last fetched {{ $lastDataFetch->fetched_at->diffForHumans() }}</span>
                        @if ($lastDataFetch->event) — {{ $lastDataFetch->event->display_name }} @endif
                    @else
                        Not fetched yet.
                    @endif
                </p>
            </div>
            <x-action-form :action="route('admin.data.refresh')" icon="refresh" variant="primary">Refresh event data now</x-action-form>
        </div>

        <div class="flex flex-col justify-between gap-4 rounded-xl border border-line bg-white p-5 shadow-xs">
            <div>
                <h2 class="flex items-center gap-2 text-sm font-semibold"><x-icon name="trash" class="h-4 w-4 text-maroon-text" /> Cache</h2>
                <p class="mt-1 text-xs text-muted">
                    Clears the server's caches{{ $cloudflareConfigured ? ' and Cloudflare' : '' }} and makes every open app reload.
                    Saved sessions and Camp Cards aren't touched.
                </p>
                @unless ($cloudflareConfigured)
                    <p class="mt-1 text-xs text-muted">Using Cloudflare? Set <code>CLOUDFLARE_ZONE_ID</code> and <code>CLOUDFLARE_API_TOKEN</code> to purge it here too.</p>
                @endunless
                <p class="mt-2 text-xs text-muted">
                    @if ($lastPurge)
                        <span class="font-medium text-ink">Last cleared {{ \Illuminate\Support\Carbon::parse($lastPurge['purged_at'])->diffForHumans() }}</span>
                        @if (! empty($lastPurge['by'])) by {{ $lastPurge['by'] }} @endif
                        @if (! empty($lastPurge['summary'])) — {{ $lastPurge['summary'] }} @endif
                    @else
                        Not cleared yet.
                    @endif
                </p>
            </div>
            <x-action-form :action="route('admin.cache.purge')" icon="refresh" variant="secondary"
                           confirm="Clear all caches? Every open app will reload.">
                Clear cache
            </x-action-form>
        </div>
    </section>
</x-app-layout>
