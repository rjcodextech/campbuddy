<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex, nofollow">

        <title>{{ $title ?: 'Sign in' }} | {{ config('campbuddy.name') }} Admin</title>

        <link rel="icon" type="image/png" sizes="32x32" href="/media/icons/favicon-32.png">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-full bg-paper font-sans text-ink antialiased">
        <div class="grid min-h-screen lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">
            {{-- Brand panel — desktop only --}}
            <aside class="relative hidden overflow-hidden bg-navy text-white lg:flex lg:flex-col lg:justify-between lg:p-12 xl:p-16">
                <div class="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full bg-maroon/25 blur-3xl" aria-hidden="true"></div>
                <div class="pointer-events-none absolute -bottom-32 -left-20 h-96 w-96 rounded-full bg-gold/10 blur-3xl" aria-hidden="true"></div>

                <a href="{{ url('/') }}" class="relative flex w-fit items-center gap-3 rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60">
                    <img src="/media/icons/icon-192.png" alt="" class="h-10 w-10 rounded-xl">
                    <span class="text-lg font-semibold">{{ config('app.name') }}</span>
                </a>

                <div class="relative max-w-md">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-gold">Organizer console</p>
                    <h2 class="mt-3 text-3xl font-semibold leading-tight tracking-tight xl:text-4xl">Run every WordCamp from one place.</h2>
                    <ul class="mt-8 space-y-4 text-sm text-white/75">
                        <li class="flex gap-3"><x-icon name="calendar" class="mt-0.5 h-5 w-5 shrink-0 text-gold" /> Publish events, schedules and the attendee checklist.</li>
                        <li class="flex gap-3"><x-icon name="tag" class="mt-0.5 h-5 w-5 shrink-0 text-gold" /> Manage sponsor deals and capture leads.</li>
                        <li class="flex gap-3"><x-icon name="lock" class="mt-0.5 h-5 w-5 shrink-0 text-gold" /> Attendee data stays private and under your control.</li>
                    </ul>
                </div>

                <p class="relative text-xs text-white/50">&copy; {{ date('Y') }} {{ config('app.name') }} · Made for the WordPress community</p>
            </aside>

            <main class="flex flex-col items-center justify-center px-4 py-10 sm:px-8">
                <div class="w-full max-w-md">
                    <a href="{{ url('/') }}" class="mb-8 flex w-fit items-center gap-2.5 rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-maroon/40 lg:hidden">
                        <img src="/media/icons/icon-192.png" alt="" class="h-9 w-9 rounded-lg">
                        <span class="text-base font-semibold">{{ config('app.name') }}</span>
                    </a>

                    @if ($title)
                        <h1 class="text-2xl font-semibold tracking-tight text-ink">{{ $title }}</h1>
                    @endif
                    @if ($subtitle)
                        <p class="mt-1.5 text-sm text-muted">{{ $subtitle }}</p>
                    @endif

                    <div class="mt-6 rounded-xl border border-line bg-white p-6 shadow-sm sm:p-8">
                        {{ $slot }}
                    </div>

                    <p class="mt-6 text-center text-xs text-muted">
                        <a href="{{ url('/') }}" class="rounded font-medium hover:text-ink hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-maroon/40">&larr; Back to the attendee app</a>
                    </p>
                </div>
            </main>
        </div>
    </body>
</html>
