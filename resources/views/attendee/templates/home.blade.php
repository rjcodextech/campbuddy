{{--
    Home's guidance cards (home.js). Each "state" of a card is its own
    template so the copy stays here rather than in JS. Slot conventions:
    resources/js/attendee/template.js.
--}}

{{-- #happening-now --}}
<template id="tpl-home-happening-none">
    <p style="margin:0">Nothing's underway right this minute — check Up Next below for what's coming up.</p>
</template>

<template id="tpl-home-happening-now">
    <span class="badge">Now</span>
    <p style="margin:8px 0 2px;font-weight:700" data-slot="title"></p>
    <p class="footer-note" style="margin:0;text-align:left">Underway<span data-slot="track-part"> · <span data-slot="track"></span></span>.</p>
</template>

{{-- #up-next --}}
<template id="tpl-home-up-next-none">
    <p style="margin:0">That's everything on the schedule for now.</p>
</template>

<template id="tpl-home-up-next">
    <p style="margin:0 0 2px;font-weight:700" data-slot="title"></p>
    <p class="footer-note" style="margin:0;text-align:left"><span data-slot="reason"></span> — starts <span data-slot="when"></span>.</p>
</template>

{{-- #starting-soon-banner --}}
<template id="tpl-home-starting-soon">
    <div class="notice" style="margin-bottom:16px">
        <strong data-slot="title"></strong> starts in <span data-slot="minutes"></span> min<span data-slot="track-part"> — <span data-slot="track"></span></span>. You saved this one.
    </div>
</template>
