{{--
    Live announcements (App\Support\EventBroadcasts) — the same card for the
    admin and the event's managers, on the Notifications page.

      @include('partials.event-broadcast', ['event' => $event, 'sendUrl' => …])
--}}
@php
    $ready = \App\Support\EventBroadcasts::ready();
    $presets = \App\Support\EventBroadcasts::presets();
    $reach = $ready ? \App\Support\EventBroadcasts::reach($event) : ['web' => 0, 'app' => 0, 'seen' => 0];
    $sessions = \App\Support\EventBroadcasts::upcomingSessions($event);
    $history = \App\Support\EventBroadcasts::history($event, 15);
    $zone = \App\Support\EventTime::zone($event);
    $from = \App\Support\EventBroadcasts::windowStart($event)->setTimezone($zone);
@endphp

<div class="max-w-4xl space-y-6">
    <form method="POST" action="{{ $sendUrl }}" data-broadcast-form>
        @csrf
        <x-card title="Send a live announcement" :description="'To '.($reach['web'] + $reach['app']).' phones and browsers that opened this WordCamp since '.$from->format('j M').' and allowed notifications ('.$reach['seen'].' devices opened it). It also shows on Home for '.\App\Support\EventBroadcasts::BANNER_MINUTES.' minutes, for everyone.'">
            @unless ($ready)
                <x-alert type="warning">Run <code>php artisan migrate</code> first.</x-alert>
            @endunless
            <div class="space-y-4">
                <fieldset>
                    <legend class="cb-label">What is happening</legend>
                    <div class="mt-1 flex flex-wrap gap-2">
                        @foreach ($presets as $i => $preset)
                            <label class="cursor-pointer">
                                <input type="radio" name="kind" value="{{ $preset['key'] }}" class="peer sr-only" @checked(old('kind', 'opening') === $preset['key']) data-preset-title="{{ $preset['title'] }}" data-preset-body="{{ $preset['body'] }}">
                                <span class="cb-btn cb-btn-sm cb-btn-secondary peer-checked:border-maroon peer-checked:bg-maroon peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-maroon">{{ $preset['label'] }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form.input name="where" label="Where (optional)" placeholder="the main hall" maxlength="60" />
                    @if ($sessions !== [])
                        <div>
                            <label class="cb-label" for="broadcast-session">Session (for “Session starting”)</label>
                            <select id="broadcast-session" name="session_id" class="cb-input">
                                <option value="">—</option>
                                @foreach ($sessions as $s)
                                    <option value="{{ $s['id'] }}" @selected(old('session_id') === $s['id'])>{{ $s['time'] }} · {{ \Illuminate\Support\Str::limit($s['title'], 60) }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                </div>
                <x-form.input name="title" label="Title (leave empty for the ready-made one)" maxlength="80" />
                <div>
                    <label class="cb-label" for="broadcast-body">Message (leave empty for the ready-made one)</label>
                    <textarea id="broadcast-body" name="body" rows="2" maxlength="240" class="cb-input">{{ old('body') }}</textarea>
                    <p class="mt-1 text-xs text-muted">Ready-made words: <span data-broadcast-preview></span></p>
                </div>
            </div>
            <x-slot:footer>
                <x-button icon="megaphone" :disabled="! $ready" onclick="return confirm('Send this announcement to everyone at the WordCamp now?')">Send now</x-button>
            </x-slot:footer>
        </x-card>
    </form>

    @if ($history !== [])
        <x-card title="Sent announcements" flush>
            <ul class="divide-y divide-line">
                @foreach ($history as $b)
                    <li class="px-5 py-3">
                        <p class="text-sm font-semibold">{{ $b['title'] }}</p>
                        <p class="text-sm text-muted">{{ $b['body'] }}</p>
                        <p class="mt-1 text-xs text-muted">{{ \Carbon\CarbonImmutable::parse($b['at'])->setTimezone($zone)->format('D j M, g:i A') }} · {{ $b['sent_by'] }} · {{ $b['recipients'] }} phones</p>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif
</div>

<script>
    // Shows the ready-made words of the picked announcement (no build step: a few lines inline).
    (() => {
        const form = document.querySelector('[data-broadcast-form]');
        if (!form) return;
        const preview = form.querySelector('[data-broadcast-preview]');
        const show = () => {
            const on = form.querySelector('input[name=kind]:checked');
            preview.textContent = on && on.dataset.presetTitle ? `${on.dataset.presetTitle} — ${on.dataset.presetBody}` : 'none: write your own title and message.';
        };
        form.addEventListener('change', show);
        show();
    })();
</script>
