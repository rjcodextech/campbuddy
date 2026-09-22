@php
    $tabs = [
        ['route' => 'event.home', 'icon' => 'home', 'label' => 'Home'],
        ['route' => 'event.my-day', 'icon' => 'my-day', 'label' => 'My Day'],
        ['route' => 'event.quest', 'icon' => 'quest', 'label' => 'Quest'],
        ['route' => 'event.contribute', 'icon' => 'contribute', 'label' => 'Contribute'],
        ['route' => 'event.explore', 'icon' => 'explore', 'label' => 'Explore'],
        ['route' => 'event.camp-card', 'icon' => 'camp-card', 'label' => 'Camp Card'],
    ];
@endphp
<nav class="bottom-nav" aria-label="Primary">
    @foreach ($tabs as $tab)
        @php($active = request()->routeIs($tab['route']))
        @if (\Illuminate\Support\Facades\Route::has($tab['route']))
            <a href="{{ route($tab['route'], $event) }}"
               class="bottom-nav__item @if($active) bottom-nav__item--active @endif"
               aria-current="{{ $active ? 'page' : 'false' }}">
                <img src="/media/{{ $tab['icon'] }}.svg" alt="" class="bottom-nav__icon" aria-hidden="true">
                <span class="bottom-nav__label">{{ $tab['label'] }}</span>
            </a>
        @else
            <span class="bottom-nav__item" aria-disabled="true">
                <img src="/media/{{ $tab['icon'] }}.svg" alt="" class="bottom-nav__icon" aria-hidden="true">
                <span class="bottom-nav__label">{{ $tab['label'] }}</span>
            </span>
        @endif
    @endforeach
</nav>
