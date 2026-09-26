@php
    $filtering = $filters['event'] || $filters['job'] || $filters['result'] || $filters['period'] !== \App\Support\ErrorReport::DEFAULT_PERIOD;
@endphp

<x-app-layout title="Errors" subtitle="What is broken or failing right now. Attendees keep seeing the last good data meanwhile."
              :breadcrumbs="[['Errors']]">
    {{-- What's silently broken on this install (App\Support\SystemHealth):
    the cron, pending migrations, stuck or failed jobs, live events with no data.
    `php artisan campbuddy:doctor` fixes most of it in one go. --}}
    <x-card title="System checks" description="The cron, the database, background jobs and live events' data.">
        @if ($problems === [])
            <p class="flex items-center gap-2 text-sm text-muted">
                <x-icon name="check-circle" class="h-5 w-5 text-teal" /> All checks pass.
            </p>
        @else
            <div class="space-y-3">
                @foreach ($problems as $problem)
                    <x-alert :type="$problem['level'] === 'error' ? 'error' : 'warning'" :title="$problem['title']">
                        <p>{{ $problem['detail'] }}</p>
                        @if ($problem['fix'])
                            <p class="mt-2 text-xs"><span class="font-semibold">Fix:</span> <code class="break-all rounded bg-white/70 px-1.5 py-0.5">{{ $problem['fix'] }}</code></p>
                        @endif
                    </x-alert>
                @endforeach
            </div>
            <p class="mt-4 text-xs text-muted">After a deploy, <code class="rounded bg-paper-soft px-1.5 py-0.5">php artisan campbuddy:doctor</code> runs the migrations, fetches every live event's data and re-checks all of this.</p>
        @endif
    </x-card>

    <form method="GET" action="{{ route('admin.errors.index') }}" role="search" aria-label="Filter fetch problems" x-data
          class="mb-4 mt-6 rounded-xl border border-line bg-white p-4 shadow-sm">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[repeat(4,minmax(0,1fr))_auto] lg:items-end">
            <x-form.select name="period" label="Period" :use-old="false" :value="$filters['period']" :options="$periods"
                           x-on:change="$el.form.requestSubmit()" />

            <x-form.select name="event" label="Event" placeholder="All events" :use-old="false" :value="(string) $filters['event']" :options="$events->all()"
                           x-on:change="$el.form.requestSubmit()" />

            <x-form.select name="job" label="What failed" placeholder="Anything" :use-old="false" :value="$filters['job']" :options="$jobLabels"
                           x-on:change="$el.form.requestSubmit()" />

            <x-form.select name="result" label="Result" placeholder="Failed or partly" :use-old="false" :value="$filters['result']"
                           :options="['error' => 'Failed', 'partial' => 'Partly worked']"
                           x-on:change="$el.form.requestSubmit()" />

            <div class="flex items-center gap-2 sm:col-span-2 lg:col-span-1">
                <x-button icon="search">Filter</x-button>
                @if ($filtering)
                    <x-button :href="route('admin.errors.index')" variant="secondary">Reset</x-button>
                @endif
            </div>
        </div>
    </form>

    <x-card title="Fetch problems" description="Fetches that failed or only partly worked. The same failure repeating is one row with a count." flush>
        <x-table>
            <x-slot:head>
                <th>Event</th>
                <th class="hidden sm:table-cell">What</th>
                <th class="hidden sm:table-cell">Result</th>
                <th class="hidden sm:table-cell">Times</th>
                <th class="hidden md:table-cell">Last seen</th>
                <th>Message</th>
            </x-slot:head>

            @forelse ($fetchProblems as $row)
                <tr class="align-top">
                    <td class="font-medium">
                        @if ($row->event)
                            <a href="{{ route('admin.events.edit', $row->event) }}" class="hover:text-maroon hover:underline">{{ $row->event->display_name }}</a>
                        @else
                            <span class="text-muted">All events</span>
                        @endif
                        <span class="mt-1 flex flex-wrap items-center gap-1.5 text-xs font-normal text-muted sm:hidden">
                            {{ $jobLabels[$row->job_type] ?? $row->job_type }} <x-fetch-status :status="$row->status" />
                        </span>
                    </td>
                    <td class="hidden whitespace-nowrap sm:table-cell">{{ $jobLabels[$row->job_type] ?? $row->job_type }}</td>
                    <td class="hidden sm:table-cell"><x-fetch-status :status="$row->status" /></td>
                    <td class="hidden tabular-nums sm:table-cell">×{{ $row->times }}</td>
                    <td class="hidden whitespace-nowrap text-muted md:table-cell" title="{{ $row->last_at }}">{{ \Illuminate\Support\Carbon::parse($row->last_at)->diffForHumans() }}</td>
                    <td class="min-w-[12rem] text-muted">
                        {{-- Some upstream errors are a whole HTML page; keep the row short, the full text is on hover. --}}
                        <span class="line-clamp-3 break-words" title="{{ $row->message }}">{{ $row->message ?: '—' }}</span>
                        <span class="block text-xs md:hidden">{{ \Illuminate\Support\Carbon::parse($row->last_at)->diffForHumans() }} · ×{{ $row->times }}</span>
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="6" icon="check-circle" title="{{ $filtering ? 'No fetch problems match these filters' : 'No fetch problems' }}">
                    @if ($filtering)
                        Try a wider period, or <a href="{{ route('admin.errors.index') }}" class="font-medium text-maroon hover:underline">reset the filters</a>.
                    @else
                        Every fetch in the {{ strtolower($periods[$filters['period']]) }} finished cleanly.
                    @endif
                </x-table.empty>
            @endforelse
        </x-table>

        @if ($fetchProblems->hasPages())
            <x-slot:footer>{{ $fetchProblems->links() }}</x-slot:footer>
        @endif
    </x-card>
</x-app-layout>
