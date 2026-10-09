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
        <p class="contrib-table__team" data-slot="team"></p>
        <p class="contrib-table__place" data-slot="place"><x-attendee.line-icon name="map-pin" /> <span data-slot="place-text"></span></p>
        <p class="contrib-table__leads" data-slot="leads"><x-attendee.line-icon name="users" /> <span data-slot="leads-text"></span></p>
        <p class="contrib-table__note" data-slot="note"></p>
    </li>
</template>
