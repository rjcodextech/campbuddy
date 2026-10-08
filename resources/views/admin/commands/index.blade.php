<x-app-layout title="Commands" subtitle="Every artisan command: CampBuddy's own and Laravel's maintenance ones with a form to run them (the run happens right here, its output shows below it), then the rest for reference."
              :breadcrumbs="[['Commands']]">
    @php($last = session('commandRun'))

    <x-alert type="warning" class="mb-6" title="Runs on the live site">
        Commands that send pushes reach real phones. Tick <strong>dry-run</strong> first where it's offered to see what would happen.
        One command runs at a time; a long one keeps going on the server even if this page stops waiting.
    </x-alert>

    <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-muted">CampBuddy</h2>
    <div class="space-y-6">
        @foreach ($commands as $command)
            @include('admin.commands._card')
        @endforeach
    </div>

    <h2 class="mb-1 mt-10 text-sm font-semibold uppercase tracking-wide text-muted">Laravel maintenance</h2>
    <p class="mb-3 text-sm text-muted">Safe to run from here: clearing caches after an upload, the failed-jobs queue, routes and the schedule.</p>
    <div class="space-y-6">
        @foreach ($maintenance as $command)
            @include('admin.commands._card')
        @endforeach
    </div>

    <x-card title="Every other artisan command" description="Listed for reference. These run only from the server terminal: some change the database or the code (migrate:fresh, db:wipe, make:…)." class="mt-10" flush>
        <x-table>
            <x-slot:head>
                <th>Command</th>
                <th>What it does</th>
            </x-slot:head>
            @foreach ($others as $command)
                <tr>
                    <td class="whitespace-nowrap font-mono text-xs">{{ $command['name'] }}</td>
                    <td class="text-sm text-muted">{{ $command['description'] }}</td>
                </tr>
            @endforeach
        </x-table>
    </x-card>
</x-app-layout>
