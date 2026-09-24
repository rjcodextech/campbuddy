{{--
    Home's guidance cards (home.js). Each "state" of a card is its own
    template so the copy stays here rather than in JS. Slot conventions:
    resources/js/attendee/template.js.
--}}

{{-- #happening-now: nothing on right now, during the event --}}
<template id="tpl-home-happening-none">
    <div>
        <p style="margin:0;font-weight:700">Nothing's on stage right this minute.</p>
        <p class="home-guidance">Good moment for the hallway track — chat with someone nearby, or visit a sponsor booth. <span data-slot="next-part">Next up: <strong data-slot="next"></strong> <span data-slot="next-when"></span>.</span></p>
    </div>
</template>

{{-- #happening-now: before the event has started --}}
<template id="tpl-home-happening-before">
    <div>
        <p style="margin:0;font-weight:700"><span data-slot="countdown"></span></p>
        <p class="home-guidance">A few minutes now makes the day much easier:</p>
        <ul class="home-prep">
            <li><a data-slot="guide">Read the 5-minute first-timer guide</a></li>
            <li><a data-slot="my-day">Save 3 sessions you'd enjoy</a></li>
            <li><a data-slot="quest">Tick off the "get ready" checklist</a></li>
        </ul>
    </div>
</template>

{{-- #happening-now: the event is over --}}
<template id="tpl-home-happening-after">
    <div>
        <p style="margin:0;font-weight:700">That's a wrap — thanks for coming!</p>
        <p class="home-guidance">Connect with the people you met while it's fresh, look up your local WordPress meetup, and watch for talk recordings on WordPress.tv.</p>
    </div>
</template>

{{-- #happening-now: one or more sessions underway --}}
<template id="tpl-home-happening-now">
    <div>
        <span class="badge">Now</span>
        <ul class="home-now-list">
            <slot data-slot="items"></slot>
        </ul>
        <p class="home-guidance" data-slot="guidance"></p>
    </div>
</template>

<template id="tpl-home-now-item">
    <li class="home-now-item">
        <span class="home-now-item__title" data-slot="title"></span>
        <span class="home-now-item__meta" data-slot="meta"></span>
    </li>
</template>

{{-- #up-next --}}
<template id="tpl-home-up-next-none">
    <p style="margin:0">That's everything on the schedule for now.</p>
</template>

<template id="tpl-home-up-next">
    <div>
        <p style="margin:0 0 2px;font-weight:700" data-slot="title"></p>
        <p class="footer-note" style="margin:0;text-align:left"><span data-slot="reason"></span> — starts <span data-slot="when"></span><span data-slot="track-part"> · <span data-slot="track"></span></span>.</p>
        <p class="home-guidance" data-slot="guidance"></p>
    </div>
</template>

{{-- #starting-soon-banner --}}
<template id="tpl-home-starting-soon">
    <div class="notice" style="margin-bottom:16px">
        <strong data-slot="title"></strong> starts in <span data-slot="minutes"></span> min<span data-slot="track-part"> — <span data-slot="track"></span></span>. You saved this one.
    </div>
</template>
