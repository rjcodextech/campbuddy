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
        <p class="empty-state__text">Tap the ☆ next to any session in Full schedule to save it here. Tip: pick three or four, and leave room for the hallway track.</p>
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
                {{-- My schedule only, once the session has started. --}}
                <div class="plan-status" data-slot="status-row">
                    <button type="button" class="plan-status__btn" data-status="attended" data-slot="attended">✓ Attended</button>
                    <button type="button" class="plan-status__btn plan-status__btn--no" data-status="missed" data-slot="missed">✗ Missed</button>
                </div>
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

{{-- My schedule: the plan's progress, and a calendar export of all of it. --}}
<template id="tpl-plan-summary">
    <div class="plan-summary">
        <div class="plan-summary__top">
            <div>
                <p class="plan-summary__title" data-slot="title">Your plan</p>
                <p class="plan-summary__count" data-slot="count"></p>
            </div>
            <span class="plan-summary__ring" data-slot="ring" aria-hidden="true"></span>
        </div>
        <div class="plan-summary__bar" role="progressbar" aria-valuemin="0" data-slot="bar"><span data-slot="fill"></span></div>
        <div class="plan-summary__actions">
            <button type="button" class="chip" aria-pressed="false" data-plan-hide-done data-slot="hide-done">Hide done</button>
            <button type="button" class="btn btn--outline btn--compact" data-plan-calendar data-slot="calendar">📅 Add all to calendar</button>
        </div>
    </div>
</template>

<template id="tpl-plan-people">
    <div>
        <h2 class="plan-section-title" id="plan-people-heading">People to meet</h2>
        <slot data-slot="items"></slot>
        <p class="plan-empty" data-slot="empty">No one yet. In <a data-slot="explore">Explore → People</a>, tap <strong>+ Meet</strong> next to anyone and add a note.</p>
    </div>
</template>

<template id="tpl-plan-person">
    <article class="plan-person" data-slot="card">
        <img class="plan-person__avatar" alt="" width="44" height="44" loading="lazy" data-fallback="/media/illustrations/avatar.svg" data-slot="avatar">
        <div class="plan-person__body">
            <p class="plan-person__name"><span data-slot="name"></span> <span class="plan-person__state" data-slot="state"></span></p>
            <p class="plan-person__when" data-slot="when"></p>
            <p class="plan-person__note" data-slot="note"></p>
            <div class="plan-person__actions">
                <button type="button" class="plan-status__btn" data-person-status="met" data-slot="met">✓ Met</button>
                <button type="button" class="plan-status__btn plan-status__btn--no" data-person-status="missed" data-slot="missed">✗ Couldn't meet</button>
                <button type="button" class="plan-person__edit" data-person-edit>Edit · Calendar</button>
            </div>
        </div>
    </article>
</template>

<template id="tpl-plan-sessions-done">
    <p class="plan-empty">All your saved sessions are done ✓ — tap <strong>Hide done</strong> to see them again.</p>
</template>
