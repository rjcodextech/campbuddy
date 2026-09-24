{{--
    My Day (my-day.js): schedule filter chips, day groups, session rows and
    the inline session detail. Slot conventions: resources/js/attendee/template.js.
--}}

<template id="tpl-my-day-empty-full">
    <p style="margin:0;padding:15px 0">No sessions match — try clearing a filter or the search.</p>
</template>

{{-- The event has no schedule at all yet (not published, or not fetched). --}}
<template id="tpl-my-day-no-schedule">
    <div class="empty-state">
        <img src="/media/illustrations/schedule.svg" alt="" width="160" height="120">
        <p class="empty-state__title">The schedule isn't published yet</p>
        <p class="empty-state__text">Organizers usually share it a few weeks before the event. Check back soon — it will appear here automatically.</p>
    </div>
</template>

<template id="tpl-my-day-empty-mine">
    <div class="empty-state">
        <img src="/media/illustrations/schedule.svg" alt="" width="160" height="120">
        <p class="empty-state__title">Your day is still empty</p>
        <p class="empty-state__text">Tap the ☆ next to any session in Full Schedule to save it here. Tip: pick three or four, and leave room for the hallway track.</p>
    </div>
</template>

{{-- Day / Track / Session-type filter rows --}}
<template id="tpl-my-day-filter-chip">
    <button type="button" class="chip" aria-pressed="false" data-slot="chip"></button>
</template>

{{-- Session rows are direct children of .schedule-day (its rows'
    :last-child divider rule depends on it), hence the unwrapped slot. --}}
<template id="tpl-schedule-day">
    <div class="schedule-day">
        <p class="schedule-day-heading" data-slot="heading"></p>
        <slot data-slot="items"></slot>
    </div>
</template>

<template id="tpl-schedule-session">
    <div class="schedule-item-row">
        <div class="schedule-item" data-slot="item">
            <div class="schedule-item__time" data-slot="time"></div>
            <div>
                <button type="button" class="schedule-item__title" data-open-detail data-slot="title-btn">
                    <span data-slot="title"></span>
                    <svg class="schedule-item__chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6" /></svg>
                </button>
                <p class="schedule-item__speakers" data-slot="speakers"></p>
                <span class="schedule-item__meta"><span class="schedule-item__live" data-slot="live">Live now</span><span data-slot="meta"></span></span>
                <span class="schedule-item__tags" data-slot="tags"></span>
                <div class="notice" style="margin-top:6px" data-slot="overlap-row">Overlaps with <span data-slot="overlap"></span></div>
            </div>
            <button type="button" class="schedule-item__star" data-slot="star">★</button>
        </div>
        <div class="schedule-item-detail" data-slot="detail"></div>
    </div>
</template>

{{-- The inline replacement for the old session-detail dialog: time,
    speakers + bios, slides/video links, save toggle — rendered under the
    tapped session. Top-level siblings inside .schedule-item-detail. --}}
<template id="tpl-schedule-detail">
    <p class="schedule-item-detail__meta" data-slot="time"></p>

    <p class="schedule-item-detail__desc" data-slot="description"></p>

    <slot data-slot="speakers"></slot>

    <div class="schedule-item-detail__links" data-slot="links">
        <a class="btn--link" target="_blank" rel="noopener" data-slot="slides">Slides</a>
        <a class="btn--link" target="_blank" rel="noopener" data-slot="video">Video</a>
    </div>

    <div class="schedule-item-detail__actions">
        <button type="button" class="btn btn--compact" data-toggle-save data-slot="save"></button>
    </div>
</template>

<template id="tpl-schedule-speaker">
    <div class="schedule-item-detail__speaker-block">
        <div class="schedule-item-detail__speaker">
            <img class="schedule-item-detail__speaker-avatar" alt="" width="36" height="36" loading="lazy" decoding="async" data-fallback="/media/illustrations/avatar.svg" data-slot="avatar-img">
            <span class="schedule-item-detail__speaker-avatar schedule-item-detail__speaker-avatar--initial" data-slot="avatar-initial"></span>
            <p class="schedule-item-detail__speaker-name" data-slot="name"></p>
        </div>
        <div class="schedule-item-detail__bio" data-slot="bio"></div>
    </div>
</template>

<template id="tpl-schedule-tag">
    <span class="schedule-tag" data-slot="tag"></span>
</template>
