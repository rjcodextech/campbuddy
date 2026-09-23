{{--
    Camp Card (camp-card.js): the type-and-Enter interest tags in the edit
    form, and the tag pills on each layout's preview. Slot conventions:
    resources/js/attendee/template.js.
--}}

<template id="tpl-tag-input-tag">
    <span class="tag-input__tag">
        <span data-slot="text"></span>
        <button type="button" class="tag-input__remove" data-slot="remove">×</button>
    </span>
</template>

<template id="tpl-camp-card-tag">
    <span class="camp-card__tag" data-slot="tag"></span>
</template>

<template id="tpl-camp-card-tag-empty">
    <span class="camp-card__tag camp-card__tag--placeholder">Nothing chosen to show yet</span>
</template>
