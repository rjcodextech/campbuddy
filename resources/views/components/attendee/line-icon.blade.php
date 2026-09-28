{{-- One line icon from the sprite (App\Support\LineIcons; partials/line-icons.blade.php). Decorative: always aria-hidden. --}}
@props(['name'])
<svg {{ $attributes->class(['line-icon']) }} aria-hidden="true" focusable="false"><use href="#li-{{ \App\Support\LineIcons::has($name) ? $name : 'sparkles' }}"/></svg>
