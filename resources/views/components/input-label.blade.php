@props(['value'])

<label {{ $attributes->merge(['class' => 'cb-label']) }}>{{ $value ?? $slot }}</label>
