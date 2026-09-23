<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? 'Admin' }} — {{ config('app.name') }}</title>

        <link rel="icon" href="/media/favicon.png">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="flex min-h-screen bg-paper font-sans text-ink antialiased">
        <aside class="hidden w-64 shrink-0 flex-col border-r border-line bg-navy lg:flex">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2 border-b border-white/10 px-6 py-6">
                <img src="/media/logo.svg" alt="" class="h-7 w-7 shrink-0" aria-hidden="true">
                <span class="leading-tight">
                    <span class="block text-sm font-semibold text-white">CampBuddy</span>
                    <span class="block text-[11px] font-medium uppercase tracking-[0.15em] text-white/50">Admin</span>
                </span>
            </a>

            <nav class="flex-1 space-y-1 px-3 py-6 text-sm">
                @php
                    $navItem = fn (string $routeName, string $label) => [
                        'active' => request()->routeIs($routeName.'*'),
                        'href' => route($routeName),
                        'label' => $label,
                    ];

                    $navGroups = array_filter([
                        'Overview' => [
                            $navItem('dashboard', 'Dashboard'),
                        ],
                        'Events' => [
                            $navItem('admin.events.index', 'Events'),
                        ],
                        'Library' => [
                            $navItem('admin.media.index', 'Media Library'),
                        ],
                    ]);
                @endphp

                @foreach ($navGroups as $group => $items)
                    <p class="px-3 pb-1 pt-4 text-[11px] font-semibold uppercase tracking-[0.15em] text-white/40 first:pt-0">
                        {{ $group }}
                    </p>

                    @foreach ($items as $item)
                        <a
                            href="{{ $item['href'] }}"
                            @class([
                                'block rounded-md px-3 py-2 font-medium transition-colors',
                                'bg-maroon/20 text-white' => $item['active'],
                                'text-white/70 hover:bg-white/5 hover:text-white' => ! $item['active'],
                            ])
                        >
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                @endforeach
            </nav>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="flex items-center justify-between gap-4 border-b border-line bg-white px-6 py-4">
                <h1 class="text-xl font-semibold text-ink">{{ $header ?? ($title ?? 'Admin') }}</h1>

                <div class="flex items-center gap-4">
                    @isset($actions){{ $actions }}@endisset

                    <a href="{{ url('/') }}" class="text-xs font-medium uppercase tracking-wide text-muted transition-colors hover:text-ink">
                        View site
                    </a>

                    <span class="text-sm text-muted">{{ auth()->user()?->name }}</span>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-xs font-medium uppercase tracking-wide text-muted transition-colors hover:text-ink">
                            Sign out
                        </button>
                    </form>
                </div>
            </header>

            <main class="flex-1 overflow-y-auto p-6">
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
