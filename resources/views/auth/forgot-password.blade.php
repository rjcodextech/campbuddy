<x-guest-layout title="Reset your password" subtitle="Enter your email and we'll send you a link to choose a new one.">
    <x-auth-session-status class="mb-5" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="grid gap-5" novalidate
          x-data="{ busy: false }" x-on:submit="busy = true" x-on:pageshow.window="busy = false">
        @csrf

        <x-form.input name="email" type="email" label="Email address" required autofocus inputmode="email" />

        <x-button class="w-full" x-bind:disabled="busy">
            <span x-show="! busy">Email reset link</span>
            <span x-show="busy" x-cloak>Sending…</span>
        </x-button>

        <p class="text-center text-sm">
            <a href="{{ route('login') }}" class="rounded font-medium text-maroon hover:text-maroon-dark hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-maroon/40">Back to sign in</a>
        </p>
    </form>
</x-guest-layout>
