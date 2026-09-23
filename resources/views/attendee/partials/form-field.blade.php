{{--
    One labelled text field, built the way every attendee form builds them
    (styles: resources/scss/components/_form.scss) so labels, hints and
    spacing can't drift between pages.

    Required: $id (the input's full DOM id), $label
    Optional: $name, $type, $value, $placeholder, $hint, $autocomplete, $inputmode,
              $maxlength, $required, $prefix (a fixed adornment before the input, e.g. "@"),
              $link (true = a URL field) / $handle (true = an X handle) — both are tidied
              and checked by camp-card.js,
              $dataSlot (template.js slot name) / $dataField (onboarding.js answer key),
              $errorLine (false = no per-field error line, for forms that show one message)
--}}
@php
    $showError = $errorLine ?? true;
    $describedBy = trim((($hint ?? null) ? $id.'-hint ' : '').($showError ? $id.'-error' : ''));
@endphp

<div class="form-field">
    <label class="form-field__label" for="{{ $id }}">
        {{ $label }}@if ($required ?? false)<abbr class="form-field__req" title="required">*</abbr>@endif
    </label>

    <div class="form-input-wrap">
        @if ($prefix ?? null)
            <span class="form-input-wrap__prefix" aria-hidden="true">{{ $prefix }}</span>
        @endif

        <input id="{{ $id }}"
               @if ($name ?? null) name="{{ $name }}" @endif
               type="{{ $type ?? 'text' }}"
               @if (($value ?? null) !== null) value="{{ $value }}" @endif
               placeholder="{{ $placeholder ?? '' }}"
               @if ($autocomplete ?? null) autocomplete="{{ $autocomplete }}" @endif
               @if ($inputmode ?? null) inputmode="{{ $inputmode }}" @endif
               @if ($maxlength ?? null) maxlength="{{ $maxlength }}" @endif
               @if ($required ?? false) required @endif
               @if ($link ?? false) data-link-field autocapitalize="none" autocorrect="off" spellcheck="false" @endif
               @if ($handle ?? false) data-handle-field autocapitalize="none" autocorrect="off" spellcheck="false" @endif
               @if ($dataSlot ?? null) data-slot="{{ $dataSlot }}" @endif
               @if ($dataField ?? null) data-field="{{ $dataField }}" @endif
               @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif>
    </div>

    @if ($hint ?? null)
        <p class="form-field__hint" id="{{ $id }}-hint">{{ $hint }}</p>
    @endif
    @if ($showError)
        <p class="form-field__error" id="{{ $id }}-error" role="alert" hidden></p>
    @endif
</div>
