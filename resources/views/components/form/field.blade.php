{{--
    The label / hint / error frame every form control shares. Not used
    directly — see form/input, textarea, select, checkbox, file, password.

    $for     id of the control       $label   visible label (optional)
    $hint    help text under it      $error   first error message, if any
    $required  appends a red *
--}}
@props(['for', 'label' => null, 'hint' => null, 'error' => null, 'required' => false])

<div {{ $attributes->class('min-w-0') }}>
    @if ($label)
        <label for="{{ $for }}" class="cb-label mb-1">
            {{ $label }}@if ($required)<span class="ms-0.5 text-danger" aria-hidden="true">*</span>@endif
        </label>
    @endif

    {{ $slot }}

    @if ($hint)
        <p id="{{ $for }}-hint" class="cb-hint">{{ $hint }}</p>
    @endif
    @if ($error)
        <p id="{{ $for }}-error" class="cb-error" role="alert">{{ $error }}</p>
    @endif
</div>
