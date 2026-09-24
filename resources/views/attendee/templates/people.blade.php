{{--
    Explore → People roster (people.js): one row per ingested attendee,
    plus the empty/offline messages. Slot conventions:
    resources/js/attendee/template.js.
--}}

<template id="tpl-roster-offline">
    <p style="margin:0">You're offline. The attendee list will refresh when you're connected again.</p>
</template>

<template id="tpl-roster-error">
    <div>
        <p style="margin:0 0 10px">Couldn't load the attendee list just now.</p>
        <button type="button" class="btn btn--outline btn--compact" data-roster-retry>Try again</button>
    </div>
</template>

<template id="tpl-roster-empty">
    <p style="margin:0">No public attendee list yet — it appears here once people register and choose to be listed on the WordCamp site.</p>
</template>

<template id="tpl-roster-no-match">
    <p style="margin:0">No attendees match your search.</p>
</template>

<template id="tpl-roster-row">
    <div class="roster-row">
        <img class="roster-row__avatar" alt="" width="40" height="40" loading="lazy" decoding="async" data-fallback="/media/illustrations/avatar.svg" data-slot="avatar-img">
        <span class="roster-row__avatar roster-row__avatar--initial" data-slot="avatar-initial"></span>
        <span class="roster-row__name"><span data-slot="name"></span> <span class="roster-row__open" data-slot="open-badge">👋 Open to meet</span></span>
        <div class="roster-row__links" data-slot="links"></div>
        <button type="button" class="meet-btn" data-slot="meet"></button>
    </div>
</template>

<template id="tpl-roster-link">
    <a class="social-icon" target="_blank" rel="noopener" data-slot="link"></a>
</template>

{{-- Simple, recognizable glyphs rather than literal brand logos --}}
<template id="tpl-social-icon-twitter">
    <svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor" aria-hidden="true"><path d="M18.9 3H21l-6.6 7.5L22 21h-6.1l-4.8-6.3L5.6 21H3.5l7-8-7-10h6.2l4.3 5.8L18.9 3z"/></svg>
</template>

<template id="tpl-social-icon-linkedin">
    <svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor" aria-hidden="true"><path d="M4.98 3.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5zM3 9h4v12H3zM9 9h3.8v1.7h.05c.53-1 1.83-2.05 3.76-2.05 4.02 0 4.76 2.65 4.76 6.1V21h-4v-5.6c0-1.34-.02-3.05-1.86-3.05-1.87 0-2.16 1.46-2.16 2.96V21H9z"/></svg>
</template>

<template id="tpl-social-icon-website">
    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.6 2.5 15.4 0 18M12 3c-2.5 2.6-2.5 15.4 0 18"/></svg>
</template>
