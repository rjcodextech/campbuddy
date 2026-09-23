{{--
    <x-form.checkbox name="is_visible" label="Visible in the public app" :checked="$event->is_visible" unchecked="0" />

    unchecked  when set (e.g. "0"), a hidden input sends that value while the
               box is unticked. Needed for a boolean that must be able to
               switch off, and for the box to be restored after a failed submit.
    Props otherwise as form/input.
--}}
@props([
    'name',
    'label',
    'value' => '1',
    'checked' => false,
    'unchecked' => null,
    'hint' => null,
    'id' => null,
    'bag' => 'default',
    'useOld' => true,
    'showError' => true,
])

@php
    $id ??= $name;
    $message = $showError ? $errors->getBag($bag)->first($name) : null;
    // Trust old() only if THIS field was in the submission that failed. An
    // unticked box sends nothing on its own, so that is only knowable when a
    // hidden `unchecked` value rides along with it — without one (or on a page
    // where a *different* form failed) the saved state stands.
    $isChecked = ($useOld && $unchecked !== null && old($name) !== null)
        ? (string) old($name) === (string) $value
        : (bool) $checked;
@endphp

<div {{ $attributes->only('class')->class('min-w-0') }}>
    <label for="{{ $id }}" class="inline-flex cursor-pointer items-start gap-2.5">
        @if ($unchecked !== null)
            <input type="hidden" name="{{ $name }}" value="{{ $unchecked }}">
        @endif
        <input
            id="{{ $id }}"
            type="checkbox"
            name="{{ $name }}"
            value="{{ $value }}"
            @checked($isChecked)
            @if ($message) aria-invalid="true" @endif
            {{ $attributes->except('class')->merge(['class' => 'cb-check mt-0.5']) }}
        >
        <span class="text-sm text-ink">
            {{ $label }}
            @if ($hint)<span class="block text-xs text-muted">{{ $hint }}</span>@endif
        </span>
    </label>
    @if ($message)
        <p class="cb-error" role="alert">{{ $message }}</p>
    @endif
</div>
