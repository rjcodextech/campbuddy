@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-line focus:border-maroon focus:ring-maroon rounded-md shadow-sm']) }}>
