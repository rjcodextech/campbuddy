{{--
    A password field with a show/hide toggle. Never re-fills its value.
    Props as form/input (name, label, hint, required, id, bag).
--}}
@props([
    'name' => 'password',
    'label' => 'Password',
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
    <div class="relative" x-data="{ show: false }">
        <input
            id="{{ $id }}"
            name="{{ $name }}"
            x-bind:type="show ? 'text' : 'password'"
            type="password"
            @required($required)
            @if ($message) aria-invalid="true" @endif
            @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            {{ $attributes->except('class')->merge(['class' => 'cb-input pr-11']) }}
        >
        <button type="button"
                class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-md text-muted hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-maroon/40"
                x-on:click="show = ! show"
                x-bind:aria-label="show ? 'Hide password' : 'Show password'"
                aria-label="Show password">
            <span x-show="! show"><x-icon name="eye" class="h-5 w-5" /></span>
            <span x-show="show" x-cloak><x-icon name="eye-off" class="h-5 w-5" /></span>
        </button>
    </div>
</x-form.field>
