@php
    $manager = auth('manager')->user();
    $pageTitle = $title ?: 'Event manager';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex, nofollow">

        <title>{{ $pageTitle }} | {{ config('campbuddy.name') }} Event manager</title>

        <link rel="icon" type="image/png" sizes="32x32" href="/media/icons/favicon-32.png">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-full bg-paper font-sans text-ink antialiased">
        <a href="#main-content" class="sr-only z-[60] rounded-md bg-white px-4 py-2 text-sm font-medium text-ink shadow focus:not-sr-only focus:fixed focus:left-3 focus:top-3">
            Skip to content
        </a>

        <div class="flex min-h-screen flex-col">
            <div class="bg-navy text-white">
                <div class="mx-auto flex w-full max-w-6xl items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-8">
                    <a href="{{ route('manager.dashboard') }}" class="flex min-w-0 items-center gap-2.5 rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60">
                        <img src="/media/icons/icon-192.png" alt="" class="h-8 w-8 shrink-0 rounded-lg">
                        <span class="min-w-0 leading-tight">
                            <span class="block truncate text-sm font-semibold">{{ config('app.name') }}</span>
                            <span class="block text-[11px] font-medium uppercase tracking-[0.15em] text-white/50">Event manager</span>
                        </span>
                    </a>

                    <div class="flex shrink-0 items-center gap-3">
                        <span class="hidden min-w-0 text-right leading-tight sm:block">
                            <span class="block max-w-[14rem] truncate text-sm font-medium">{{ $manager?->name }}</span>
                            <span class="block max-w-[14rem] truncate text-xs text-white/50">{{ $manager?->email }}</span>
                        </span>

                        <form method="POST" action="{{ route('manager.logout') }}">
                            @csrf
                            <button type="submit"
                                    class="inline-flex items-center gap-2 rounded-md border border-white/20 px-3 py-1.5 text-sm font-medium text-white/90 transition-colors hover:bg-white/10 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60">
                                <x-icon name="logout" class="h-4 w-4" />
                                Sign out
                            </button>
                        </form>
                    </div>
                </div>
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
                                                <a href="{{ $crumb[1] }}" class="rounded hover:text-ink hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-maroon/40">{{ $crumb[0] }}</a>
                                            @else
                                                <span @if ($loop->last) aria-current="page" @endif class="{{ $loop->last ? 'font-medium text-ink' : '' }}">{{ $crumb[0] }}</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ol>
                            </nav>
                        @endif

                        <h1 class="truncate text-xl font-semibold tracking-tight text-ink sm:text-2xl">{{ $pageTitle }}</h1>
                        @if ($subtitle)
                            <p class="mt-0.5 text-sm text-muted">{{ $subtitle }}</p>
                        @endif
                    </div>

                    @isset($actions)
                        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
                    @endisset
                </div>
            </header>

            <main id="main-content" tabindex="-1" class="mx-auto w-full max-w-6xl flex-1 px-4 py-6 focus:outline-none sm:px-6 lg:px-8">
                @if (session('status'))
                    <x-alert type="success" class="mb-6">{{ session('status') }}</x-alert>
                @endif

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
    </body>
</html>
