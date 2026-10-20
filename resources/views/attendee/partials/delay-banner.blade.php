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
        <button type="button" class="delay-banner__close" onclick="this.closest('.delay-banner').remove()" aria-label="Dismiss delay notification" style="background:none;border:none;color:inherit;opacity:0.7;cursor:pointer;padding:4px;margin-left:auto;align-self:flex-start;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px;" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        </button>
    </div>
@endif
