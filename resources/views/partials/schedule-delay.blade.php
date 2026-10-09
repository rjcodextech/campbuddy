{{--
    "Running late" (App\Support\ScheduleDelay) — the same page for the admin and
    an event's managers; only the routes differ.

      @include('partials.schedule-delay', ['event' => $event, 'saveUrl' => …, 'clearUrl' => …])
--}}
@php
    $delays = \App\Support\ScheduleDelay::active($event);
    $tracks = \App\Support\ScheduleDelay::tracks($event);
@endphp

<div class="max-w-3xl space-y-6">
    <x-alert type="info">
        Sessions running late? Set it here. CampBuddy moves the times on attendees' Home and My Day (the planned time is shown
        crossed out), shows a "Running late" note, and sends session reminders that much later. A delay covers sessions from
        the time you pick until the end of that day; the next day starts on time again. Times are the venue's
        ({{ \App\Support\EventTime::zone($event)->getName() }}).
    </x-alert>

    <x-card title="Now" :description="$delays === [] ? 'Everything is on time.' : 'Attendees see this now.'">
        @if ($delays !== [])
            <ul class="space-y-2 text-sm">
                @foreach ($delays as $delay)
                    <li class="flex flex-wrap items-center gap-2">
                        <x-badge variant="danger">{{ \App\Support\ScheduleDelay::describe($event, $delay) }}</x-badge>
                        @if (! empty($delay['note']))
                            <span class="text-muted">{{ $delay['note'] }}</span>
                        @endif
                        <span class="text-xs text-muted">set by {{ $delay['set_by'] ?? '—' }}</span>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($delays !== [])
            <x-slot:footer>
                <x-action-form :action="$clearUrl" variant="secondary" size="sm" confirm="Back on time? This clears every delay.">Back on time (clear all)</x-action-form>
            </x-slot:footer>
        @endif
    </x-card>

    <form method="POST" action="{{ $saveUrl }}">
        @csrf
        <x-card title="Set a delay" description="One per track; setting it again replaces it. 0 minutes clears it.">
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="delay-minutes" class="cb-label">Minutes late</label>
                    <input id="delay-minutes" name="minutes" type="number" min="0" max="{{ \App\Support\ScheduleDelay::MAX_MINUTES }}" step="5" value="{{ old('minutes', 15) }}" class="cb-input" required>
                    <x-input-error :messages="$errors->get('minutes')" class="mt-1" />
                </div>
                <div>
                    <label for="delay-track" class="cb-label">Which sessions</label>
                    <select id="delay-track" name="track" class="cb-input">
                        <option value="">Whole event</option>
                        @foreach ($tracks as $track)
                            <option value="{{ $track }}" @selected(old('track') === $track)>{{ $track }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="delay-from" class="cb-label">For sessions starting from</label>
                    <input id="delay-from" name="from" type="datetime-local" value="{{ old('from', \App\Support\ScheduleDelayForm::defaultFrom($event)) }}" class="cb-input" required>
                    <x-input-error :messages="$errors->get('from')" class="mt-1" />
                </div>
                <div>
                    <label for="delay-note" class="cb-label">Note for attendees <span class="font-normal text-muted">(optional)</span></label>
                    <input id="delay-note" name="note" type="text" maxlength="140" value="{{ old('note') }}" placeholder="e.g. Keynote started late" class="cb-input">
                </div>
            </div>

            <x-slot:footer>
                <x-button>Save delay</x-button>
            </x-slot:footer>
        </x-card>
    </form>
</div>
