{{-- The line-icon sprite (App\Support\LineIcons), once per page, hidden. Icons
point into it with <use href="#li-name">: <x-attendee.line-icon> in Blade,
lineIcon() in JS (line-icon.js, via #tpl-line-icon below). --}}
<svg xmlns="http://www.w3.org/2000/svg" width="0" height="0" style="position:absolute;width:0;height:0;overflow:hidden" aria-hidden="true" focusable="false">
    @foreach (\App\Support\LineIcons::ICONS as $name => $shape)
        <symbol id="li-{{ $name }}" viewBox="0 0 24 24">{!! $shape !!}</symbol>
    @endforeach
</svg>

<template id="tpl-line-icon">
    <svg class="line-icon" aria-hidden="true" focusable="false"><use data-slot="use" href="#li-sparkles"/></svg>
</template>
