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
        </span>
    </button>
</template>
