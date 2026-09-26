<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex, nofollow">

        <title>{{ $title ?: 'Sign in' }} | {{ config('campbuddy.name') }} Event manager</title>

        <link rel="icon" type="image/png" sizes="32x32" href="/media/icons/favicon-32.png">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-full bg-paper font-sans text-ink antialiased">
        <main class="flex min-h-screen flex-col items-center justify-center px-4 py-10 sm:px-8">
            <div class="w-full max-w-md">
                <div class="mb-8 flex items-center gap-3">
                    <img src="/media/icons/icon-192.png" alt="" class="h-10 w-10 rounded-xl">
                    <span class="leading-tight">
                        <span class="block text-base font-semibold">{{ config('app.name') }}</span>
                        <span class="block text-[11px] font-medium uppercase tracking-[0.15em] text-maroon">Event manager</span>
                    </span>
                </div>

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
                    No account? An admin creates event manager accounts — ask the person running your WordCamp.
                </p>
            </div>
        </main>
    </body>
</html>
