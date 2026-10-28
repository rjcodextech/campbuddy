@php
    // The sidebar: a few groups, each a submenu of [label, url, is this the open page].
    // A group with the open page starts unfolded; the others fold away until clicked.
    $is = fn (string ...$names) => request()->routeIs(...$names);
    $mobileTab = $is('admin.mobile.*') ? (in_array(request()->query('tab'), \App\Http\Controllers\Admin\MobileAppController::TABS, true) ? request()->query('tab') : 'overview') : null;
    $navGroups = [
        ['label' => 'Dashboard', 'icon' => 'dashboard', 'href' => route('dashboard'), 'items' => [], 'active' => $is('dashboard')],
        ['label' => 'Events', 'icon' => 'calendar', 'items' => [
            ['All events', route('admin.events.index'), $is('admin.events.*') && ! $is('admin.events.create')],
            ['Add event', route('admin.events.create'), $is('admin.events.create')],
            ['Media Partner Pitch', route('admin.media-pitch.index'), $is('admin.media-pitch.*')],
        ]],
        ['label' => 'Deals & offers', 'icon' => 'tag', 'items' => [
            ['Default deals', route('admin.deals.index'), $is('admin.deals.*')],
            ['Free Steals', route('admin.free-steals.index'), $is('admin.free-steals.*')],
        ]],
        ['label' => 'People', 'icon' => 'users', 'items' => [
            ['Event managers', route('admin.event-managers.index'), $is('admin.event-managers.*') && ! $is('admin.event-managers.activity')],
            ['Manager activity', route('admin.event-managers.activity'), $is('admin.event-managers.activity')],
            ['Feedback', route('admin.feedback.index'), $is('admin.feedback.*')],
        ]],
        ['label' => 'Mobile app', 'icon' => 'device-phone', 'items' => collect(\App\Http\Controllers\Admin\MobileAppController::LABELS)
            ->map(fn ($label, $tab) => [$label, route('admin.mobile.index', $tab === 'overview' ? [] : ['tab' => $tab]), $mobileTab === $tab])->values()->all()],
        ['label' => 'Reports', 'icon' => 'chart-bar', 'items' => [
            ['Analytics', route('admin.analytics.index'), $is('admin.analytics.index')],
            ['Usage post', route('admin.analytics.usage-post'), $is('admin.analytics.usage-post')],
            ['Errors', route('admin.errors.index'), $is('admin.errors.*')],
        ]],
        ['label' => 'Settings', 'icon' => 'adjustments', 'items' => [
            ['Media Library', route('admin.media.index'), $is('admin.media.*')],
            ['Legal documents', route('admin.legal.index'), $is('admin.legal.*')],
            ['Commands', route('admin.commands.index'), $is('admin.commands.*')],
        ]],
    ];
    foreach ($navGroups as $i => $group) {
        $navGroups[$i]['active'] ??= collect($group['items'])->contains(fn ($item) => $item[2]);
    }

    $user = auth()->user();
    $pageTitle = $title ?: 'Admin';

    // Breeze's profile/password/verification controllers flash a code, not a sentence.
    $flash = session('status');
    $flashMessages = [
        'profile-updated' => 'Your profile has been updated.',
        'password-updated' => 'Your password has been updated.',
        'verification-link-sent' => 'A new verification link has been sent.',
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex, nofollow">

        <title>{{ $pageTitle }} | {{ config('campbuddy.name') }} Admin</title>

        <link rel="icon" type="image/png" sizes="32x32" href="/media/icons/favicon-32.png">

        @vite(['resources/css/fonts.css', 'resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-full bg-paper font-sans text-ink antialiased">
        <div x-data="{ sidebarOpen: false }" x-on:keydown.escape.window="sidebarOpen = false" class="min-h-screen lg:flex">
            <a href="#main-content" class="sr-only z-60 rounded-md bg-white px-4 py-2 text-sm font-medium text-ink shadow-sm focus:not-sr-only focus:fixed focus:left-3 focus:top-3">
                Skip to content
            </a>

            {{-- Mobile: dim the page behind the open drawer --}}
            <div x-show="sidebarOpen" x-cloak x-transition.opacity
                 class="fixed inset-0 z-40 bg-ink/50 lg:hidden" x-on:click="sidebarOpen = false" aria-hidden="true"></div>

            <aside id="admin-sidebar"
                   x-bind:class="{ 'max-lg:translate-x-0': sidebarOpen }"
                   class="fixed inset-y-0 left-0 z-50 flex w-64 shrink-0 -translate-x-full flex-col bg-navy transition-transform duration-200 lg:sticky lg:top-0 lg:z-auto lg:h-screen lg:translate-x-0">
                <div class="flex items-center justify-between border-b border-white/10 px-5 py-5">
                    <a href="{{ route('dashboard') }}" class="group relative after:absolute after:-inset-y-1 after:inset-x-0 after:content-[''] flex items-center gap-2.5 rounded-md focus:outline-hidden focus-visible:ring-2 focus-visible:ring-white/60">
                        <img src="/media/icons/icon-192.png" alt="" class="cb-logo-in h-8 w-8 shrink-0 rounded-lg motion-safe:transition-transform motion-safe:duration-300 motion-safe:group-hover:-rotate-6 motion-safe:group-hover:scale-110">
                        <span class="leading-tight">
                            <span class="block text-sm font-semibold text-white">{{ config('app.name') }}</span>
                            <span class="block text-xs font-medium uppercase tracking-[0.15em] text-white/50">Admin</span>
                        </span>
                    </a>

                    <button type="button" class="rounded-md p-1.5 text-white/70 hover:bg-white/10 hover:text-white lg:hidden"
                            x-on:click="sidebarOpen = false" aria-label="Close menu">
                        <x-icon name="close" class="h-5 w-5" />
                    </button>
                </div>

                <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-5" aria-label="Main">
                    @foreach ($navGroups as $group)
                        @if (empty($group['items']))
                            <a href="{{ $group['href'] }}"
                               @if ($group['active']) aria-current="page" @endif
                               @class([
                                   'group relative flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors focus:outline-hidden focus-visible:ring-2 focus-visible:ring-white/60',
                                   'bg-white/10 text-white' => $group['active'],
                                   'text-white/70 hover:bg-white/5 hover:text-white' => ! $group['active'],
                               ])>
                                @if ($group['active'])
                                    <span class="absolute inset-y-1.5 left-0 w-1 rounded-r-sm bg-maroon" aria-hidden="true"></span>
                                @endif
                                <x-icon :name="$group['icon']" class="h-5 w-5 shrink-0 motion-safe:transition-transform motion-safe:duration-200 motion-safe:group-hover:scale-110" />
                                {{ $group['label'] }}
                            </a>
                        @else
                            <div x-data="{ open: {{ $group['active'] ? 'true' : 'false' }} }">
                                <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open.toString()" aria-expanded="{{ $group['active'] ? 'true' : 'false' }}"
                                        aria-controls="nav-{{ \Illuminate\Support\Str::slug($group['label']) }}"
                                        @class([
                                            'group flex w-full items-center gap-3 rounded-md px-3 py-2 text-left text-sm font-medium transition-colors focus:outline-hidden focus-visible:ring-2 focus-visible:ring-white/60',
                                            'text-white' => $group['active'],
                                            'text-white/70 hover:bg-white/5 hover:text-white' => ! $group['active'],
                                        ])>
                                    <x-icon :name="$group['icon']" class="h-5 w-5 shrink-0 motion-safe:transition-transform motion-safe:duration-200 motion-safe:group-hover:scale-110" />
                                    <span class="flex-1">{{ $group['label'] }}</span>
                                    <x-icon name="chevron-down" class="h-4 w-4 shrink-0 text-white/50 motion-safe:transition-transform motion-safe:duration-200" x-bind:class="open ? 'rotate-0' : '-rotate-90'" />
                                </button>
                                <ul id="nav-{{ \Illuminate\Support\Str::slug($group['label']) }}" x-show="open" @unless ($group['active']) style="display: none" @endunless
                                    class="mt-0.5 mb-1 ml-5 space-y-0.5 border-l border-white/10 pl-3">
                                    @foreach ($group['items'] as [$label, $href, $active])
                                        <li>
                                            <a href="{{ $href }}"
                                               @if ($active) aria-current="page" @endif
                                               @class([
                                                   'relative block rounded-md px-3 py-1.5 text-sm transition-colors focus:outline-hidden focus-visible:ring-2 focus-visible:ring-white/60',
                                                   'bg-white/10 font-medium text-white' => $active,
                                                   'text-white/65 hover:bg-white/5 hover:text-white' => ! $active,
                                               ])>
                                                @if ($active)
                                                    <span class="absolute inset-y-1 -left-3.5 w-0.5 rounded-r-sm bg-maroon" aria-hidden="true"></span>
                                                @endif
                                                {{ $label }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    @endforeach

                    <div class="pt-4">
                        <a href="{{ url('/') }}" target="_blank" rel="noopener"
                           class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-white/70 transition-colors hover:bg-white/5 hover:text-white focus:outline-hidden focus-visible:ring-2 focus-visible:ring-white/60">
                            <x-icon name="external" class="h-5 w-5 shrink-0" />
                            View attendee app
                        </a>
                    </div>
                </nav>

                <div class="border-t border-white/10 p-3">
                    <a href="{{ route('profile.edit') }}"
                       @if (request()->routeIs('profile.*')) aria-current="page" @endif
                       @class([
                           'flex items-center gap-3 rounded-md px-3 py-2 transition-colors focus:outline-hidden focus-visible:ring-2 focus-visible:ring-white/60',
                           'bg-white/10' => request()->routeIs('profile.*'),
                           'hover:bg-white/5' => ! request()->routeIs('profile.*'),
                       ])>
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-maroon text-sm font-semibold text-white" aria-hidden="true">
                            {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($user?->name ?? '?', 0, 1)) }}
                        </span>
                        <span class="min-w-0 leading-tight">
                            <span class="block truncate text-sm font-medium text-white">{{ $user?->name }}</span>
                            <span class="block truncate text-xs text-white/50">{{ $user?->email }}</span>
                        </span>
                    </a>

                    <form method="POST" action="{{ route('logout') }}" class="mt-1">
                        @csrf
                        <button type="submit"
                                class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-white/70 transition-colors hover:bg-white/5 hover:text-white focus:outline-hidden focus-visible:ring-2 focus-visible:ring-white/60">
                            <x-icon name="logout" class="h-5 w-5 shrink-0" />
                            Sign out
                        </button>
                    </form>
                </div>
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                {{-- Mobile top bar --}}
                <div class="sticky top-0 z-30 flex h-14 items-center gap-3 border-b border-line bg-white px-4 lg:hidden">
                    <button type="button" class="-ml-1 rounded-md p-2 text-ink hover:bg-paper-soft focus:outline-hidden focus-visible:ring-2 focus-visible:ring-maroon/40"
                            x-on:click="sidebarOpen = true" aria-controls="admin-sidebar" x-bind:aria-expanded="sidebarOpen.toString()" aria-label="Open menu">
                        <x-icon name="menu" class="h-6 w-6" />
                    </button>
                    <img src="/media/icons/icon-192.png" alt="" class="h-7 w-7 rounded-md">
                    <span class="text-sm font-semibold">{{ config('app.name') }} Admin</span>
                </div>

                <header class="border-b border-line bg-white">
                    <div class="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-between gap-x-4 gap-y-3 px-4 py-5 sm:px-6 lg:px-8">
                        <div class="min-w-0">
                            @if (count($breadcrumbs))
                                <nav aria-label="Breadcrumb" class="mb-1">
                                    <ol class="flex flex-wrap items-center gap-1 text-xs text-muted">
                                        @foreach ($breadcrumbs as $crumb)
                                            <li class="flex items-center gap-1">
                                                @if (! $loop->first)
                                                    <x-icon name="chevron-right" class="h-3 w-3 text-muted/60" />
                                                @endif
                                                @if (isset($crumb[1]) && ! $loop->last)
                                                    <a href="{{ $crumb[1] }}" class="relative after:absolute after:-inset-y-3 after:inset-x-0 after:content-[''] rounded hover:text-ink hover:underline focus:outline-hidden focus-visible:ring-2 focus-visible:ring-maroon/40">{{ $crumb[0] }}</a>
                                                @else
                                                    <span @if ($loop->last) aria-current="page" @endif class="{{ $loop->last ? 'font-medium text-ink' : '' }}">{{ $crumb[0] }}</span>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ol>
                                </nav>
                            @endif

                            <h1 class="cb-enter truncate text-xl font-semibold tracking-tight text-ink sm:text-2xl">{{ $pageTitle }}</h1>
                            @if ($subtitle)
                                <p class="mt-0.5 text-sm text-muted">{{ $subtitle }}</p>
                            @endif
                        </div>

                        @isset($actions)
                            <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
                        @endisset
                    </div>
                </header>

                <main id="main-content" tabindex="-1" class="cb-enter cb-enter-late mx-auto w-full max-w-6xl flex-1 px-4 py-6 focus:outline-hidden sm:px-6 lg:px-8">
                    @if ($flash)
                        <x-alert type="success" class="mb-6">{{ $flashMessages[$flash] ?? $flash }}</x-alert>
                    @endif

                    {{-- A manual action that didn't fully work (a refresh, a fetch)
                    says so in its own colour — never as a green "success". --}}
                    @if (session('warning'))
                        <x-alert type="warning" class="mb-6" title="Partly done">{{ session('warning') }}</x-alert>
                    @endif

                    @if (session('error'))
                        <x-alert type="error" class="mb-6" title="That didn't work">{{ session('error') }}</x-alert>
                    @endif

                    @if ($errors->any())
                        <x-alert type="error" class="mb-6" title="Please fix the following and try again.">
                            <ul class="list-inside list-disc space-y-0.5">
                                @foreach (array_unique($errors->all()) as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                            </ul>
                        </x-alert>
                    @endif

                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
