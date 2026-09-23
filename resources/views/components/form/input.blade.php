{{--
    <x-form.input name="slug" label="Slug" :value="$event->slug" required hint="…" />

    Fills from old() after a failed submit, shows the field's validation
    error, and wires aria-invalid / aria-describedby.

    id         override when several forms on one page reuse a field name
    bag        named error bag (the profile forms use "updatePassword" …)
    useOld     false to ignore old() — for the per-row forms in a list,
               where every row shares the same field names
    showError  false to hide the inline error (same reason; the page-level
               summary still lists it)
--}}
@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'hint' => null,
    'required' => false,
    'id' => null,
    'bag' => 'default',
    'useOld' => true,
    'showError' => true,
])

@php
    $id ??= $name;
    $message = $showError ? $errors->getBag($bag)->first($name) : null;
    $current = $useOld ? old($name, $value) : $value;
    $describedBy = trim(($hint ? $id.'-hint ' : '').($message ? $id.'-error' : ''));
@endphp

<x-form.field :for="$id" :label="$label" :hint="$hint" :error="$message" :required="$required" :class="$attributes->get('class')">
    <input
        id="{{ $id }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($current !== null && $type !== 'password') value="{{ $current }}" @endif
        @required($required)
        @if ($message) aria-invalid="true" @endif
        @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->except('class')->merge(['class' => 'cb-input']) }}
    >
</x-form.field>
