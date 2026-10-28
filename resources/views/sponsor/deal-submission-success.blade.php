<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Deal Submitted | {{ config('campbuddy.name') }}</title>
    <link rel="icon" type="image/png" sizes="32x32" href="/media/icons/favicon-32.png">
    @vite(['resources/css/fonts.css', 'resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-paper font-sans text-ink antialiased">
    <div class="min-h-screen flex flex-col justify-between py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-xl mx-auto w-full">
            <div class="text-center mb-8">
                <a href="{{ url('/') }}" class="inline-flex items-center gap-2.5">
                    <img src="/media/icons/icon-192.png" alt="" class="h-10 w-10 rounded-xl">
                    <span class="text-xl font-bold tracking-tight text-ink">{{ config('app.name') }}</span>
                </a>
            </div>

            <div class="bg-white border border-line rounded-2xl p-8 sm:p-10 shadow-xs text-center">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-teal/15 text-teal mb-6">
                    <x-icon name="check-circle" class="h-10 w-10" />
                </div>

                <span class="inline-block px-3 py-1 text-xs font-semibold uppercase tracking-wider text-teal bg-teal/10 rounded-full mb-3">
                    Staged for Verification
                </span>

                <h1 class="text-2xl font-bold tracking-tight text-ink sm:text-3xl">
                    Deal Submitted Successfully!
                </h1>

                @if (session('submitted_sponsor'))
                    <p class="mt-2 text-base font-semibold text-maroon">
                        {{ session('submitted_sponsor') }}
                    </p>
                @endif

                <p class="mt-4 text-sm text-muted leading-relaxed">
                    Thank you for supporting WordCamp attendees! Your deal details have been securely recorded in our temporary review staging.
                </p>

                <div class="mt-6 rounded-xl bg-paper p-5 border border-line text-left text-xs sm:text-sm text-muted space-y-2.5">
                    <div class="flex items-start gap-2.5">
                        <x-icon name="check" class="h-4 w-4 text-teal shrink-0 mt-0.5" />
                        <span><strong>Verification & Review:</strong> Our administrative team verifies sponsor authenticity to protect attendees from unverified or spam deals.</span>
                    </div>
                    <div class="flex items-start gap-2.5">
                        <x-icon name="check" class="h-4 w-4 text-teal shrink-0 mt-0.5" />
                        <span><strong>Publication:</strong> Once approved, your deal will go live in the Explore &rarr; Deals section for all attendees of the WordCamp.</span>
                    </div>
                    <div class="flex items-start gap-2.5">
                        <x-icon name="check" class="h-4 w-4 text-teal shrink-0 mt-0.5" />
                        <span><strong>Notification:</strong> An alert has been delivered to our review inbox (<span class="font-mono text-ink">hello@wpsimplified.in</span>).</span>
                    </div>
                </div>

                <div class="mt-8 flex flex-col sm:flex-row gap-3 justify-center">
                    <x-button :href="route('sponsor-deals.create')" variant="secondary" icon="plus">
                        Submit Another Deal
                    </x-button>
                    <x-button :href="url('/')" variant="primary">
                        Return to Homepage
                    </x-button>
                </div>
            </div>

            <p class="mt-8 text-center text-xs text-muted">
                &copy; {{ date('Y') }} {{ config('app.name') }}. WordCamp is a registered trademark of the WordPress Foundation.
            </p>
        </div>
    </div>
</body>
</html>
