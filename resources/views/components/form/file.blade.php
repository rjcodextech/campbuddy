{{-- <x-form.file name="logo" label="Logo" accept="image/png,image/svg+xml" hint="PNG or SVG, max 5 MB" /> --}}
@props([
    'name',
    'label' => null,
    'hint' => null,
    'required' => false,
    'id' => null,
    'bag' => 'default',
])

@php
    $id ??= $name;
    $message = $errors->getBag($bag)->first($name);
    $describedBy = trim(($hint ? $id.'-hint ' : '').($message ? $id.'-error' : ''));
@endphp

<x-form.field :for="$id" :label="$label" :hint="$hint" :error="$message" :required="$required" :class="$attributes->get('class')">
    <input
        id="{{ $id }}"
        name="{{ $name }}"
        type="file"
        @required($required)
        @if ($message) aria-invalid="true" @endif
        @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->except('class')->merge(['class' => 'cb-file']) }}
    >
</x-form.field>
