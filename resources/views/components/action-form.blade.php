{{--
    A one-button form for a single action — refresh, suppress, delete…
    Handles CSRF and method spoofing, and asks first when given `confirm`.

      <x-action-form :action="route('admin.events.refresh', $event)" icon="refresh" variant="secondary">
          Refresh now
      </x-action-form>

      <x-action-form :action="…" method="DELETE" variant="danger-outline" size="sm"
                     confirm="Remove this quest?">Remove</x-action-form>
--}}
@props([
    'action',
    'method' => 'POST',
    'variant' => 'secondary',
    'size' => 'md',
    'icon' => null,
    'confirm' => null,
])

<form method="POST" action="{{ $action }}" {{ $attributes->only('class')->merge(['class' => 'inline']) }}
      @if ($confirm) x-data x-on:submit="if (! confirm(@js($confirm))) $event.preventDefault()" @endif>
    @csrf
    @if (! in_array(strtoupper($method), ['POST', 'GET'], true))
        @method($method)
    @endif

    <x-button :variant="$variant" :size="$size" :icon="$icon" {{ $attributes->except('class') }}>{{ $slot }}</x-button>
</form>
