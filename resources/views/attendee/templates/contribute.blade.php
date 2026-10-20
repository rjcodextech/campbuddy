{{--
    Contribute (contribute.js): the "what do you enjoy" chips and a
    contributor-team card. The team-detail dialog is static markup in
    attendee/contribute.blade.php (JS fills its slots). Slot conventions:
    resources/js/attendee/template.js.
--}}

<template id="tpl-contribute-chip">
    <button type="button" class="chip" aria-pressed="false" data-slot="chip"></button>
</template>

<template id="tpl-contribute-team-card">
    <button type="button" class="action-card action-card--wide" style="width:100%;margin-bottom:10px" data-slot="card">
        <span class="action-card__icon" aria-hidden="true" data-slot="emoji"></span>
        <span>
            <span class="action-card__title" data-slot="name"></span>
            <span class="action-card__desc" data-slot="desc"></span>
            <span class="contrib-table-line" data-slot="table"><x-attendee.line-icon name="map-pin" /> <span data-slot="table-text"></span></span>
        </span>
    </button>
</template>

{{-- contribute.js: one Contributor Day table the organizers entered (ContributorTable). --}}
<template id="tpl-contribute-table">
    <li class="contrib-table">
        <div class="contrib-table__top">
            <p class="contrib-table__team" data-slot="team"></p>
            <p class="contrib-table__place" data-slot="place"><x-attendee.line-icon name="map-pin" /> <span data-slot="place-text"></span></p>
        </div>
        <p class="contrib-table__note" data-slot="note"></p>
        <div class="contrib-table__leads-box" data-slot="leads-box">
            <p class="contrib-table__leads-heading"><x-attendee.line-icon name="users" /> Table Leads</p>
            <div class="contrib-table__leads-list" data-slot="leads-list"></div>
        </div>
        <p class="contrib-table__leads-simple" data-slot="leads"><x-attendee.line-icon name="users" /> <span data-slot="leads-text"></span></p>
    </li>
</template>

<template id="tpl-contribute-lead-card">
    <div class="contrib-lead-card">
        <div class="contrib-lead-card__avatar-wrap">
            <img class="contrib-lead-card__avatar" data-slot="avatar" alt="" loading="lazy">
            <span class="contrib-lead-card__initial" data-slot="initial" aria-hidden="true"></span>
        </div>
        <div class="contrib-lead-card__info">
            <div class="contrib-lead-card__header">
                <span class="contrib-lead-card__name" data-slot="name"></span>
                <span class="role-badge role-badge--table_lead">Table Lead</span>
            </div>
            <p class="contrib-lead-card__desig" data-slot="designation"></p>
            <p class="contrib-lead-card__bio" data-slot="bio"></p>
            <div class="contrib-lead-card__links" data-slot="links"></div>
        </div>
    </div>
</template>
