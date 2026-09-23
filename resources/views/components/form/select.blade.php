{{--
    <x-form.select name="status" label="Status" :options="['draft' => 'Draft', …]" :value="$event->status" />
    Or pass <option>s in the slot instead of :options (then selection is yours).
    placeholder adds a leading empty option. Props otherwise as form/input.
--}}
@props([
    'name',
    'label' => null,
    'options' => null,
    'value' => null,
    'placeholder' => null,
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
    $current = (string) ($useOld ? old($name, $value) : $value);
    $describedBy = trim(($hint ? $id.'-hint ' : '').($message ? $id.'-error' : ''));
@endphp

<x-form.field :for="$id" :label="$label" :hint="$hint" :error="$message" :required="$required" :class="$attributes->get('class')">
    <select
        id="{{ $id }}"
        name="{{ $name }}"
        @required($required)
        @if ($message) aria-invalid="true" @endif
        @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->except('class')->merge(['class' => 'cb-input']) }}
    >
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif

        @if ($options !== null)
            @foreach ($options as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" @selected($current === (string) $optionValue)>{{ $optionLabel }}</option>
            @endforeach
        @else
            {{ $slot }}
        @endif
    </select>
</x-form.field>
