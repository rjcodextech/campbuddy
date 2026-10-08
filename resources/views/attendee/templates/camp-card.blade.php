{{--
    Camp Card (camp-card.js): the type-and-Enter interest tags in the edit
    form, and the tag pills on each layout's preview. Slot conventions:
    resources/js/attendee/template.js.
--}}

<template id="tpl-tag-input-tag">
    <span class="cc-tag">
        <span data-slot="text"></span>
        <button type="button" class="cc-tag__remove" data-slot="remove">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
        </button>
    </span>
</template>

{{-- A tap-to-add suggestion under the interests field. --}}
<template id="tpl-interest-suggestion">
    <button type="button" class="cc-suggest__chip" data-slot="chip"></button>
</template>

@include('attendee.templates.camp-card-tags')
