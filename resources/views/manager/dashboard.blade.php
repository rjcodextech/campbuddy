<x-manager-layout title="My events" subtitle="The WordCamps you look after. Pick one to update it.">
    @if ($events->isEmpty())
        <x-card>
            <div class="py-8 text-center">
                <x-icon name="calendar" class="mx-auto h-8 w-8 text-muted/50" />
                <p class="mt-2 text-sm font-medium">No events yet</p>
                <p class="mx-auto mt-1 max-w-sm text-sm text-muted">An admin hasn't given you an event to manage yet. Once they do, it shows up here.</p>
            </div>
        </x-card>
    @else
        <ul class="grid gap-4 md:grid-cols-2">
            @foreach ($events as $event)
                @php $timing = \App\Support\EventListing::timing($event, $today); @endphp
                <li class="flex flex-col rounded-xl border border-line bg-white p-5 shadow-xs">
                    <div class="flex items-start gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-line bg-paper-soft text-sm font-semibold text-maroon-text" aria-hidden="true">
                            @if ($event->markUrl())
                                <img src="{{ $event->markUrl() }}" alt="" class="max-h-9 max-w-9 object-contain">
                            @else
                                {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($event->display_name, 0, 1)) }}
                            @endif
                        </span>
                        <div class="min-w-0 flex-1">
                            <h2 class="text-base font-semibold leading-snug">
                                <a href="{{ route('manager.events.details', $event) }}" class="hover:text-maroon-text hover:underline focus:outline-hidden focus-visible:ring-2 focus-visible:ring-maroon/40">{{ $event->display_name }}</a>
                            </h2>
                            <p class="mt-0.5 text-sm text-muted">
                                @if ($event->starts_on)
                                    {{ $event->starts_on->format('j M Y') }}@if ($event->ends_on && ! $event->ends_on->isSameDay($event->starts_on)) – {{ $event->ends_on->format('j M Y') }}@endif
                                    @if ($timing)
                                        · <span @class(['font-medium text-maroon-text' => $timing['state'] !== 'past'])>{{ $timing['label'] }}</span>
                                    @endif
                                @else
                                    Date not set yet
                                @endif
                            </p>
                        </div>
                        <x-event-status :status="$event->status" />
                    </div>

                    {{-- Plain links with <x-button>'s classes: three components per card made this list
                         slow to draw for a manager with hundreds of events. --}}
                    <div class="mt-4 flex flex-wrap gap-2 border-t border-line pt-4">
                        <a href="{{ route('manager.events.details', $event) }}" class="cb-btn cb-btn-secondary cb-btn-sm">Event details</a>
                        <a href="{{ route('manager.events.information', $event) }}" class="cb-btn cb-btn-secondary cb-btn-sm">Event information</a>
                        <a href="{{ route('manager.events.quests', $event) }}" class="cb-btn cb-btn-secondary cb-btn-sm">Quests &amp; checklist</a>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</x-manager-layout>
