{{--
    Quest (quest.js): a "Things to do" card and a Checklist row. Slot
    conventions: resources/js/attendee/template.js.
--}}

<template id="tpl-quest-thing">
    <div class="quest-card" data-slot="card">
        <div class="quest-card__icon" aria-hidden="true" data-slot="icon"></div>
        <div class="quest-card__body">
            <p class="quest-card__title" data-slot="title"></p>
            <p class="quest-card__desc" data-slot="desc"></p>
            <div class="quest-card__actions">
                <a class="btn btn--outline btn--compact" data-slot="nav"><span data-slot="nav-label"></span> →</a>
                <button type="button" class="btn btn--compact" data-slot="toggle"></button>
            </div>
        </div>
    </div>
</template>

<template id="tpl-quest-checklist-item">
    <label class="checklist__item" data-slot="item">
        <input type="checkbox" class="checklist__input" data-slot="input">
        <span>
            <span class="checklist__label" data-slot="label"></span>
            <span class="footer-note" style="display:block;text-align:left;margin-top:2px" data-slot="desc"></span>
        </span>
    </label>
</template>
