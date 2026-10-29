{{--
    "Running late" (App\Support\ScheduleDelay): shown on Home and My Day while
    an organizer's delay applies. The times on the page already include it.
--}}
@php($delays = \App\Support\ScheduleDelay::active($event))
@if ($delays !== [])
    <div class="delay-banner" role="status">
        <span class="delay-banner__icon" aria-hidden="true"><x-attendee.line-icon name="clock" /></span>
        <div class="delay-banner__body">
            <p class="delay-banner__title">Running late</p>
            @foreach ($delays as $delay)
                <p class="delay-banner__line">
                    {{ \App\Support\ScheduleDelay::describe($event, $delay) }}@if (! empty($delay['note'])) · {{ $delay['note'] }}@endif
                </p>
            @endforeach
            <p class="delay-banner__hint">The times in CampBuddy already include this.</p>
        </div>
        <button type="button" class="delay-banner__close" onclick="this.closest('.delay-banner').remove()" aria-label="Dismiss delay notification">
            <x-attendee.line-icon name="x" />
        </button>
    </div>
@endif
