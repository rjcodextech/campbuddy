{{-- Reported discovery profiles (admin and event manager). $open, $closed, $stillThere, $removeUrl(id), $dismissUrl(id) --}}
<div class="max-w-5xl space-y-6">
        <x-card title="Reported profiles" description="Attendees can report a person from the People list. Remove the profile or dismiss the reports, ideally within a day (the app stores expect it).">
            @forelse ($open as $discoveryId => $reports)
                @php($snapshot = $reports->first()->snapshot ?? [])
                <div class="border-t border-line py-4 first:border-t-0 first:pt-0">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-medium">{{ $snapshot['name'] ?? 'Anonymous attendee' }}
                                @if (! in_array($discoveryId, $stillThere, true))
                                    <x-badge>Already left</x-badge>
                                @endif
                            </p>
                            @if (! empty($snapshot['profession']))
                                <p class="text-sm text-muted">{{ $snapshot['profession'] }}</p>
                            @endif
                            @if (! empty($snapshot['who_to_meet']))
                                <p class="text-sm text-muted">“{{ $snapshot['who_to_meet'] }}”</p>
                            @endif
                            <ul class="mt-2 space-y-0.5 text-sm">
                                @foreach ($reports as $report)
                                    <li><span class="font-medium">{{ \App\Models\DiscoveryReport::REASONS[$report->reason] ?? $report->reason }}</span>
                                        <span class="text-muted">· {{ $report->created_at->diffForHumans() }}</span>
                                        @if ($report->note)<span class="text-muted">· {{ $report->note }}</span>@endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                        <div class="flex items-center gap-2">
                            @if (in_array($discoveryId, $stillThere, true))
                                <form method="POST" action="{{ $removeUrl($discoveryId) }}" onsubmit="return confirm('Remove this profile? They can join again as a new person.')">
                                    @csrf
                                    <x-button variant="danger" size="sm">Remove profile</x-button>
                                </form>
                            @endif
                            <form method="POST" action="{{ $dismissUrl($discoveryId) }}">
                                @csrf
                                <x-button variant="secondary" size="sm">Dismiss</x-button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-muted">No open reports.</p>
            @endforelse
        </x-card>

        @if ($closed->isNotEmpty())
            <x-card title="Recently handled">
                <ul class="space-y-1 text-sm">
                    @foreach ($closed as $report)
                        <li>{{ $report->snapshot['name'] ?? 'Anonymous attendee' }} — {{ \App\Models\DiscoveryReport::REASONS[$report->reason] ?? $report->reason }}
                            <span class="text-muted">· {{ $report->status }} {{ $report->resolved_at?->diffForHumans() }}</span></li>
                    @endforeach
                </ul>
            </x-card>
        @endif
    </div>
