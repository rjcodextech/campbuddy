@props(['messages'])

@if ($messages)
    <ul {{ $attributes->merge(['class' => 'cb-error space-y-1']) }} role="alert">
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
