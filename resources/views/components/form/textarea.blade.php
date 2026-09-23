{{-- <x-form.textarea name="wifi" label="Wifi details" :value="$info['wifi'] ?? ''" rows="3" /> — props as form/input. --}}
@props([
    'name',
    'label' => null,
    'value' => null,
    'rows' => 3,
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
    <textarea
        id="{{ $id }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        @required($required)
        @if ($message) aria-invalid="true" @endif
        @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->except('class')->merge(['class' => 'cb-input']) }}
    >{{ $current }}</textarea>
</x-form.field>
