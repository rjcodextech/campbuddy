@php
    $r = $report['reports'] ?? [];
    $num = fn ($v) => number_format((float) $v);
    $dur = function ($seconds) {
        $s = (int) round($seconds);
        return $s >= 60 ? intdiv($s, 60).' min '.($s % 60).' s' : $s.' s';
    };
    $pick = fn (string $name, string $dim, string $metric, ?callable $label = null, ?callable $hint = null) => array_map(
        fn ($row) => [$label ? $label($row[$dim]) : ($row[$dim] === '(not set)' ? 'Unknown' : $row[$dim]), $row[$metric], $hint ? $hint($row) : null],
        $r[$name] ?? []
    );

    $t = $r['totals'][0] ?? [];
    $users = (float) ($t['activeUsers'] ?? 0);
    $returning = collect($r['new_returning'] ?? [])->firstWhere('newVsReturning', 'returning')['activeUsers'] ?? 0;

    $events = collect($r['events'] ?? [])->keyBy('eventName');
    $tabNames = ['my_day' => 'My Day', 'explore' => 'Explore', 'quest' => 'Quest', 'contribute' => 'Contribute', 'camp_card' => 'Camp Card', 'home' => 'Home',
        'deals' => 'Deals', 'free-steals' => 'Free Steals', 'sponsors' => 'Sponsors', 'info' => 'Info', 'people' => 'People'];
    $tab = fn ($v) => $tabNames[$v] ?? $v;

    $actions = [
        'What people did in the app' => [
            'session_save' => 'Saved a session', 'session_expand' => 'Opened a session\'s details', 'schedule_filter' => 'Filtered the schedule',
            'quest_complete' => 'Ticked a quest', 'meet_add' => 'Added someone to meet', 'contribute_team_view' => 'Opened a contributor team',
        ],
        'Discovery' => [
            'discovery_join_start' => 'Tapped Join', 'discovery_join' => 'Joined discovery', 'discovery_wave' => 'Waved at someone',
            'discovery_mutual_view' => 'Saw a mutual wave', 'discovery_message' => 'Sent a message', 'device_transfer_done' => 'Moved to a new phone',
            'discovery_join_blocked' => 'Join stopped (missing name / tags, or save failed)',
        ],
        'Camp Card, install, reminders' => [
            'camp_card_save' => 'Saved their Camp Card', 'camp_card_download' => 'Downloaded their Camp Card', 'share' => 'Shared',
            'install_complete' => 'Installed the app', 'onboarding_complete' => 'Finished onboarding', 'calendar_export' => 'Added to calendar',
        ],
        'Deals, sponsors, Free Steals' => [
            'deal_open' => 'Opened a deal', 'generate_lead' => 'Sent a lead', 'deal_code_copy' => 'Copied a coupon code',
            'sponsor_open' => 'Opened a sponsor', 'free_steal_open' => 'Opened a Free Steal', 'free_steal_suggest' => 'Suggested a Free Steal',
        ],
        'After the event' => [
            'thank_you_view' => 'Saw the thank-you card', 'event_feedback' => 'Rated it (see Admin → Feedback)',
            'data_export' => 'Exported their data (PDF or JSON)', 'thank_you_next_click' => 'Tapped "your next WordCamp"',
        ],
    ];
    $hasAppEvents = $events->has('page_context');

    $wordcamps = array_map(fn ($row) => [
        $row['customEvent:event_slug'] === '(not set)' ? 'No WordCamp (home, picker, guide)' : ($eventNames[$row['customEvent:event_slug']] ?? $row['customEvent:event_slug']),
        $row['totalUsers'],
        $row['totalUsers'] > 0 ? 'avg '.$dur($row['userEngagementDuration'] / $row['totalUsers']).' per person · '.$num($row['eventCount']).' events' : null,
    ], $r['wordcamps'] ?? []);

    $daily = $r['daily'] ?? [];
    $dailyMax = max(1, ...array_map(fn ($d) => $d['activeUsers'], $daily ?: [['activeUsers' => 0]]));
    $hours = collect($r['hour'] ?? [])->keyBy(fn ($h) => (int) $h['hour']);
    $hourMax = max(1, ...$hours->pluck('activeUsers')->all() ?: [0]);
    $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    $vitals = [];
    foreach ($r['vitals'] ?? [] as $v) {
        $vitals[$v['customEvent:metric_name']][$v['customEvent:metric_rating']] = $v['eventCount'];
    }
    $vitalNames = ['LCP' => 'LCP · how fast the page shows', 'CLS' => 'CLS · how much things jump', 'INP' => 'INP · how fast a tap responds'];

    $dealNames = ['deal_open' => 'Deal opened', 'free_steal_open' => 'Free Steal opened', 'generate_lead' => 'Lead sent', 'deal_code_copy' => 'Code copied'];
    $installNames = ['install_prompt_open' => 'Install prompt shown', 'install_prompt_result' => 'Install prompt answered', 'install_complete' => 'Installed'];
@endphp

<x-app-layout title="Analytics" subtitle="Google Analytics, read here: who came, from where, and what they did."
              :breadcrumbs="[['Analytics']]">

    <form method="GET" action="{{ route('admin.analytics.index') }}" aria-label="Choose what to report" x-data="{ period: @js($filters['period']) }"
          class="mb-6 rounded-xl border border-line bg-white p-4 shadow-sm">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[repeat(2,minmax(0,1fr))_auto_auto] lg:items-end">
            @if ($streams !== [])
                <x-form.select name="stream" label="Website (GA stream)" :use-old="false" :value="$filters['stream']" :options="$streams" />
            @endif

            <x-form.select name="period" label="Dates" :use-old="false" :value="$filters['period']" :options="$periods" x-model="period" />

            <div class="grid grid-cols-2 gap-3 sm:col-span-2 lg:col-span-1" x-show="period === 'custom'" x-cloak>
                <x-form.input type="date" name="from" label="From" :use-old="false" :value="$filters['from']" max="{{ now()->toDateString() }}" />
                <x-form.input type="date" name="to" label="To" :use-old="false" :value="$filters['to']" max="{{ now()->toDateString() }}" />
            </div>

            <div class="flex flex-wrap items-center gap-3 sm:col-span-2 lg:col-span-1">
                <label class="inline-flex items-center gap-2 text-sm text-ink">
                    <input type="checkbox" name="hide_bots" value="1" @checked($filters['bots']) class="rounded border-line text-maroon focus:ring-maroon/40">
                    Hide bots
                </label>
                <x-button icon="search">Show</x-button>
            </div>
        </div>
    </form>

    @if ($filters['invalid'])
        <x-alert type="warning" title="Those dates didn't work" class="mb-6">
            Pick a start date on or before the end date, no later than today and not older than 14 months. Showing the {{ strtolower($periods[$filters['period']]) }} instead.
        </x-alert>
    @endif

    @if ($error)
        <x-alert type="error" title="Google Analytics couldn't be read">
            <p>{{ $error }}</p>
            <p class="mt-2 text-xs">Needs <code class="rounded bg-white/70 px-1.5 py-0.5">GA_PROPERTY_ID</code> and <code class="rounded bg-white/70 px-1.5 py-0.5">GA_CREDENTIALS_PATH</code> in .env, the service account key file on the server, the service account added to the GA property (Viewer is enough), and the "Google Analytics Data API" and "Google Analytics Admin API" switched on in its Google Cloud project.</p>
        </x-alert>
    @else
        <p class="mb-4 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted">
            <span>{{ \Illuminate\Support\Carbon::parse($filters['from'])->format('j M Y') }} – {{ \Illuminate\Support\Carbon::parse($filters['to'])->format('j M Y') }}</span>
            <span aria-hidden="true">·</span>
            <span>{{ $filters['bots'] ? 'Bots hidden' : 'Bots included' }}</span>
            <span aria-hidden="true">·</span>
            <span>Fetched {{ \Illuminate\Support\Carbon::parse($report['fetched_at'])->diffForHumans() }}; kept for an hour</span>
            <a href="{{ request()->fullUrlWithQuery(['fresh' => 1]) }}" class="font-medium text-maroon hover:underline">Fetch again now</a>
        </p>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat label="People" :value="$num($users)" :hint="$num($t['newUsers'] ?? 0).' new · '.$num($returning).' came back'" icon="users" />
            <x-stat label="Visits (sessions)" :value="$num($t['sessions'] ?? 0)" :hint="round(($t['engagementRate'] ?? 0) * 100).'% actually engaged'" icon="calendar" />
            <x-stat label="Pages viewed" :value="$num($t['screenPageViews'] ?? 0)" :hint="($users ? round(($t['screenPageViews'] ?? 0) / $users, 1) : 0).' per person'" icon="eye" />
            <x-stat label="Time per person" :value="$users ? $dur(($t['userEngagementDuration'] ?? 0) / $users) : '0 s'" hint="Time the app was on screen, over the whole period" icon="dashboard" />
        </div>

        <x-card title="People per day" description="Hover a column for new people, visits and page views." class="mt-6">
            @if ($daily === [])
                <p class="text-sm text-muted">No visits in these dates.</p>
            @else
                <div class="overflow-x-auto">
                    <div class="flex h-56 min-w-[32rem] items-end gap-1 border-b border-line pt-5" role="img" aria-label="People per day">
                        @foreach ($daily as $d)
                            @php $date = \Illuminate\Support\Carbon::createFromFormat('Ymd', $d['date']); @endphp
                            <div class="group relative flex h-full flex-1 flex-col items-center justify-end"
                                 title="{{ $date->format('D j M') }}: {{ $num($d['activeUsers']) }} people, {{ $num($d['newUsers']) }} new, {{ $num($d['sessions']) }} visits, {{ $num($d['screenPageViews']) }} page views">
                                @if (count($daily) <= 31)
                                    <span class="mb-1 text-[11px] tabular-nums text-muted">{{ $num($d['activeUsers']) }}</span>
                                @endif
                                <span class="block w-full max-w-[2.5rem] rounded-t bg-maroon group-hover:bg-maroon-dark" style="height: {{ max(1, $d['activeUsers'] / $dailyMax * 100) }}%"></span>
                            </div>
                        @endforeach
                    </div>
                    <div class="flex min-w-[32rem] gap-1 pt-1.5">
                        @foreach ($daily as $i => $d)
                            @php $date = \Illuminate\Support\Carbon::createFromFormat('Ymd', $d['date']); @endphp
                            <span class="flex-1 text-center text-[11px] leading-tight text-muted">
                                @if (count($daily) <= 14 || $i % (int) ceil(count($daily) / 10) === 0)
                                    {{ $date->format('j M') }}
                                    @if (count($daily) <= 14)
                                        <br>{{ $date->format('D') }}
                                    @endif
                                @endif
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif
        </x-card>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <x-card title="WordCamps" description="People who opened each WordCamp in the app.">
                <x-admin.bar-list :rows="$wordcamps" unit="people" empty="No WordCamp pages were opened in these dates." />
            </x-card>
            <x-card title="Where people came from" description="Visits by source / medium. id-card / print is the QR on printed badges.">
                <x-admin.bar-list :rows="$pick('source', 'sessionSourceMedium', 'sessions', null, fn ($row) => $num($row['activeUsers']).' people')" unit="visits" />
            </x-card>
            <x-card title="Cities">
                <x-admin.bar-list :rows="$pick('city', 'city', 'activeUsers')" unit="people" />
            </x-card>
            <x-card title="Countries">
                <x-admin.bar-list :rows="$pick('country', 'country', 'activeUsers')" unit="people" />
            </x-card>
            <x-card title="Devices">
                <div class="space-y-5">
                    <x-admin.bar-list :rows="$pick('device', 'deviceCategory', 'activeUsers', fn ($v) => ucfirst($v))" unit="people" />
                    <x-admin.bar-list :rows="$pick('os', 'operatingSystem', 'activeUsers')" unit="people" />
                    <x-admin.bar-list :rows="$pick('browser', 'browser', 'activeUsers')" unit="people" />
                </div>
            </x-card>
            <x-card title="How new people first found it" description="The source of each new person's first visit.">
                <x-admin.bar-list :rows="$pick('first_source', 'firstUserSourceMedium', 'newUsers')" unit="new people" />
            </x-card>
        </div>

        <x-card title="Pages" description="Most viewed pages, with how long each person spent on them." flush class="mt-6">
            <x-table>
                <x-slot:head>
                    <th>Page</th>
                    <th class="text-right">Views</th>
                    <th class="text-right">People</th>
                    <th class="hidden text-right sm:table-cell">Time per person</th>
                </x-slot:head>
                @forelse ($r['pages'] ?? [] as $p)
                    <tr>
                        <td class="break-all">{{ $p['pagePath'] }}</td>
                        <td class="text-right tabular-nums">{{ $num($p['screenPageViews']) }}</td>
                        <td class="text-right tabular-nums">{{ $num($p['activeUsers']) }}</td>
                        <td class="hidden whitespace-nowrap text-right tabular-nums sm:table-cell">{{ $p['activeUsers'] ? $dur($p['userEngagementDuration'] / $p['activeUsers']) : '—' }}</td>
                    </tr>
                @empty
                    <x-table.empty :colspan="4" title="No page views in these dates" />
                @endforelse
            </x-table>
        </x-card>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <x-card title="Time of day" description="People per hour, in the GA property's time zone.">
                <div class="overflow-x-auto">
                    <div class="flex h-40 min-w-[24rem] items-end gap-0.5 border-b border-line" role="img" aria-label="People per hour">
                        @for ($h = 0; $h < 24; $h++)
                            @php $v = $hours[$h]['activeUsers'] ?? 0; @endphp
                            <div class="flex h-full flex-1 items-end" title="{{ sprintf('%02d:00–%02d:59', $h, $h) }}: {{ $num($v) }} people">
                                <span class="block w-full rounded-t bg-maroon hover:bg-maroon-dark" style="height: {{ $v ? max(1, $v / $hourMax * 100) : 0 }}%"></span>
                            </div>
                        @endfor
                    </div>
                    <div class="flex min-w-[24rem] gap-0.5 pt-1 text-[11px] text-muted">
                        @for ($h = 0; $h < 24; $h++)
                            <span class="flex-1 text-center">{{ $h % 3 === 0 ? $h : '' }}</span>
                        @endfor
                    </div>
                </div>
            </x-card>
            <x-card title="Day of the week">
                <x-admin.bar-list :rows="array_map(fn ($row) => [$days[(int) $row['dayOfWeek']] ?? $row['dayOfWeek'], $row['activeUsers']], collect($r['weekday'] ?? [])->sortBy(fn ($row) => (int) $row['dayOfWeek'])->values()->all())" unit="people" />
            </x-card>
        </div>

        @if ($hasAppEvents)
            <h2 class="mb-3 mt-10 text-lg font-semibold text-ink">In the app</h2>

            <div class="grid gap-6 lg:grid-cols-2">
                @foreach ($actions as $group => $list)
                    <x-card :title="$group" flush>
                        <x-table>
                            <x-slot:head><th>Action</th><th class="text-right">Times</th><th class="text-right">People</th></x-slot:head>
                            @foreach ($list as $name => $label)
                                @php $e = $events[$name] ?? null; @endphp
                                <tr>
                                    <td>{{ $label }}</td>
                                    <td class="text-right tabular-nums {{ $e ? '' : 'text-muted' }}">{{ $num($e['eventCount'] ?? 0) }}</td>
                                    <td class="text-right tabular-nums {{ $e ? '' : 'text-muted' }}">{{ $num($e['totalUsers'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                        </x-table>
                    </x-card>
                @endforeach

                <x-card title="Bottom menu taps">
                    <x-admin.bar-list :rows="$pick('nav_tab', 'customEvent:tab', 'eventCount', $tab, fn ($row) => $num($row['totalUsers']).' people')" unit="taps" />
                </x-card>
                <x-card title="Explore tabs opened">
                    <x-admin.bar-list :rows="$pick('explore_tab', 'customEvent:tab', 'eventCount', $tab, fn ($row) => $num($row['totalUsers']).' people')" unit="opens" />
                </x-card>
                <x-card title="Most saved sessions">
                    <x-admin.bar-list :rows="$pick('saved_sessions', 'customEvent:session_title', 'eventCount')" unit="saves" empty="No sessions saved in these dates." />
                </x-card>
                <x-card title="Most ticked quests">
                    <x-admin.bar-list :rows="$pick('quests', 'customEvent:quest_title', 'eventCount')" unit="ticks" empty="No quests ticked in these dates." />
                </x-card>
                <x-card title="Deals and Free Steals">
                    <x-admin.bar-list :rows="array_map(fn ($row) => [($dealNames[$row['eventName']] ?? $row['eventName']).': '.$row['customEvent:offer_title'], $row['eventCount'], $num($row['totalUsers']).' people'], $r['deals'] ?? [])" unit="times" empty="Nobody opened a deal or Free Steal in these dates." />
                </x-card>
                <x-card title="Sponsors opened">
                    <x-admin.bar-list :rows="$pick('sponsors', 'customEvent:sponsor_name', 'eventCount')" unit="opens" empty="No sponsor was opened in these dates." />
                </x-card>
                <x-card title="First-timer guide: how far people read" description="People who reached each section, top to bottom.">
                    <x-admin.bar-list :rows="$pick('guide_sections', 'customEvent:section', 'totalUsers', fn ($v) => ucfirst($v))" unit="people" />
                </x-card>
                <x-card title="Most opened FAQ">
                    <x-admin.bar-list :rows="$pick('faq', 'customEvent:question', 'eventCount')" unit="opens" />
                </x-card>
                <x-card title="Install and reminders">
                    <div class="space-y-5">
                        <x-admin.bar-list :rows="array_map(fn ($row) => [collect([$installNames[$row['eventName']] ?? $row['eventName'], $row['customEvent:platform'], $row['customEvent:outcome']])->reject(fn ($v) => $v === '(not set)')->implode(' · '), $row['eventCount'], $num($row['totalUsers']).' people'], $r['install'] ?? [])" unit="times" />
                        <x-admin.bar-list :rows="$pick('reminder', 'customEvent:result', 'eventCount', fn ($v) => 'Reminder offer: '.str_replace('_', ' ', $v), fn ($row) => $num($row['totalUsers']).' people')" unit="times" empty="No reminder offers in these dates." />
                    </div>
                </x-card>
                <x-card title="Opened as" description="Installed app (standalone) or a browser tab.">
                    <x-admin.bar-list :rows="$pick('display_mode', 'customEvent:display_mode', 'totalUsers', fn ($v) => $v === 'standalone' ? 'Installed app' : ($v === 'browser' ? 'Browser' : 'Unknown'), fn ($row) => $num($row['eventCount']).' page opens')" unit="people" />
                </x-card>
            </div>

            <h2 class="mb-3 mt-10 text-lg font-semibold text-ink">Speed and errors</h2>

            <div class="grid gap-6 lg:grid-cols-2">
                <x-card title="Web Vitals" description="Measured on people's own phones. Google calls a page fast when 75% or more is good.">
                    @if ($vitals === [])
                        <p class="text-sm text-muted">No measurements in these dates.</p>
                    @else
                        <div class="space-y-5">
                            @foreach (['LCP', 'CLS', 'INP'] as $m)
                                @php
                                    $g = $vitals[$m]['good'] ?? 0; $n = $vitals[$m]['needs_improvement'] ?? 0; $p = $vitals[$m]['poor'] ?? 0;
                                    $all = max(1, $g + $n + $p); $pc = fn ($x) => round($x / $all * 100);
                                @endphp
                                <div class="space-y-1.5">
                                    <div class="flex flex-wrap items-baseline justify-between gap-2 text-sm">
                                        <span class="font-medium text-ink">{{ $vitalNames[$m] }}</span>
                                        <x-badge :variant="$pc($g) >= 75 ? 'success' : ($pc($g) >= 50 ? 'warning' : 'danger')">{{ $pc($g) }}% good</x-badge>
                                    </div>
                                    <div class="flex h-3 gap-0.5 overflow-hidden rounded">
                                        <span class="bg-teal" style="width: {{ $pc($g) }}%" title="Good: {{ $num($g) }}"></span>
                                        <span class="bg-gold" style="width: {{ $pc($n) }}%" title="Needs improvement: {{ $num($n) }}"></span>
                                        <span class="bg-danger" style="width: {{ $pc($p) }}%" title="Poor: {{ $num($p) }}"></span>
                                    </div>
                                    <p class="text-xs text-muted">{{ $num($g) }} good · {{ $num($n) }} needs improvement · {{ $num($p) }} poor</p>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-card>
                <x-card title="Errors people hit" flush>
                    <x-table>
                        <x-slot:head><th>Error</th><th class="text-right">Times</th><th class="text-right">People</th></x-slot:head>
                        @php
                            $errorRows = array_merge(
                                array_map(fn ($e) => ['Script: '.$e['customEvent:description'], $e['eventCount'], $e['totalUsers']], $r['exceptions'] ?? []),
                                array_map(fn ($e) => ['API: '.$e['customEvent:method'].' '.$e['customEvent:endpoint'].' → '.$e['customEvent:status'], $e['eventCount'], $e['totalUsers']], $r['api_errors'] ?? []),
                            );
                        @endphp
                        @forelse ($errorRows as [$label, $times, $people])
                            <tr>
                                <td class="break-words">{{ $label }}</td>
                                <td class="text-right tabular-nums">{{ $num($times) }}</td>
                                <td class="text-right tabular-nums">{{ $num($people) }}</td>
                            </tr>
                        @empty
                            <x-table.empty :colspan="3" icon="check-circle" title="No errors in these dates" />
                        @endforelse
                    </x-table>
                </x-card>
            </div>
        @endif

        <x-card title="Every event" description="Everything GA counted in these dates, by event name." flush class="mt-6">
            <x-table>
                <x-slot:head><th>Event</th><th class="text-right">Times</th><th class="text-right">People</th></x-slot:head>
                @forelse ($r['events'] ?? [] as $e)
                    <tr>
                        <td class="font-mono text-xs">{{ $e['eventName'] }}</td>
                        <td class="text-right tabular-nums">{{ $num($e['eventCount']) }}</td>
                        <td class="text-right tabular-nums">{{ $num($e['totalUsers']) }}</td>
                    </tr>
                @empty
                    <x-table.empty :colspan="3" title="No events in these dates" />
                @endforelse
            </x-table>
        </x-card>

        <p class="mt-6 text-xs text-muted">
            "Hide bots" leaves out screen sizes only bots report ({{ implode(', ', \App\Support\AnalyticsReport::BOT_SCREENS) }}). GA never receives names, messages or who met whom, so this page only has counts.
        </p>
    @endif
</x-app-layout>
