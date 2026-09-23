{{--
    One labelled text field in the Camp Card editor — same markup everywhere
    so labels, hints and spacing can't drift.

    Required: $id (also the input's name suffix), $name, $label
    Optional: $type, $placeholder, $hint, $autocomplete, $inputmode, $maxlength,
              $required, $prefix (a fixed adornment shown before the input, e.g. "@"),
              $link (true = a URL field) / $handle (true = an X handle) — both are
              tidied and checked by camp-card.js
--}}
@php
    $inputId = 'cc-'.$id;
    $describedBy = trim(($hint ?? null ? $inputId.'-hint ' : '').$inputId.'-error');
@endphp

<div class="cc-field">
    <label class="cc-field__label" for="{{ $inputId }}">
        {{ $label }}@if ($required ?? false)<abbr class="cc-field__req" title="required">*</abbr>@endif
    </label>

    <div class="cc-input-wrap">
        @if ($prefix ?? null)
            <span class="cc-input-wrap__prefix" aria-hidden="true">{{ $prefix }}</span>
        @endif

        <input class="cc-input @if ($prefix ?? null) cc-input--prefixed @endif"
               id="{{ $inputId }}"
               name="{{ $name }}"
               type="{{ $type ?? 'text' }}"
               placeholder="{{ $placeholder ?? '' }}"
               @if ($autocomplete ?? null) autocomplete="{{ $autocomplete }}" @endif
               @if ($inputmode ?? null) inputmode="{{ $inputmode }}" @endif
               @if ($maxlength ?? null) maxlength="{{ $maxlength }}" @endif
               @if ($required ?? false) required @endif
               @if ($link ?? false) data-link-field autocapitalize="none" autocorrect="off" spellcheck="false" @endif
               @if ($handle ?? false) data-handle-field autocapitalize="none" autocorrect="off" spellcheck="false" @endif
               aria-describedby="{{ $describedBy }}">
    </div>

    @if ($hint ?? null)
        <p class="cc-field__hint" id="{{ $inputId }}-hint">{{ $hint }}</p>
    @endif
    <p class="cc-field__error" id="{{ $inputId }}-error" role="alert" hidden></p>
</div>
