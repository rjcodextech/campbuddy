<x-app-layout :title="'Roster — '.$event->display_name">
    <div class="max-w-3xl space-y-6">
        @if (session('status'))
            <div class="p-4 bg-teal/10 text-teal rounded-md">{{ session('status') }}</div>
        @endif

        <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-line p-6">
            <form method="GET" class="mb-4">
                <input type="text" name="q" value="{{ $q }}" placeholder="Search by name…" class="w-full border-line rounded-md text-sm">
            </form>

            <table class="min-w-full divide-y divide-line">
                <thead>
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium text-muted uppercase">Name</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-muted uppercase">Links</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-muted uppercase">Status</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($roster as $entry)
                        <tr>
                            <td class="px-3 py-2 text-sm text-ink flex items-center gap-2">
                                @if ($entry->gravatar_url)
                                    <img src="{{ $entry->gravatar_url }}" alt="" class="w-6 h-6 rounded-full">
                                @endif
                                {{ $entry->name }}
                            </td>
                            <td class="px-3 py-2 text-xs text-muted">{{ collect($entry->links)->pluck('type')->implode(', ') }}</td>
                            <td class="px-3 py-2 text-xs">
                                @if ($entry->is_suppressed)
                                    <span class="text-maroon">Suppressed</span>
                                @else
                                    <span class="text-teal">Visible</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right">
                                @if ($entry->is_suppressed)
                                    <form method="POST" action="{{ route('admin.events.roster.unsuppress', [$event, $entry]) }}">
                                        @csrf
                                        <button type="submit" class="text-xs text-teal hover:underline">Restore</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('admin.events.roster.suppress', [$event, $entry]) }}">
                                        @csrf
                                        <button type="submit" class="text-xs text-maroon hover:underline">Suppress</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="mt-4">{{ $roster->links() }}</div>
        </div>
    </div>
</x-app-layout>
