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

{{-- card-share.js: pick yourself on the attendee list --}}
<template id="tpl-cc-share-picker">
    <div class="cc-share__picker">
        <label class="form-field__label" for="cc-share-search">Find your name on the attendee list</label>
        <input type="search" id="cc-share-search" class="search-input" placeholder="Type your name…" autocomplete="off" data-share-search>
        <div class="cc-share__results" data-share-results></div>
        <button type="button" class="btn btn--compact btn--outline" data-share-cancel>Cancel</button>
    </div>
</template>

<template id="tpl-cc-share-result">
    <button type="button" class="cc-share__result" data-slot="name"></button>
</template>

<template id="tpl-cc-share-status">
    <p class="cc-share__status"><x-attendee.line-icon name="users" /><span>On the attendee list as <strong data-slot="name"></strong>. Saving your card updates it there too.</span></p>
</template>
