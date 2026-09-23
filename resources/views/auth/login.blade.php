<x-guest-layout title="Sign in" subtitle="Welcome back. Enter your details to manage your events.">
    <x-auth-session-status class="mb-5" :status="session('status')" />

    {{-- busy: stops a double submit and shows progress; pageshow resets it
    if the browser restores this page from the back/forward cache. --}}
    <form method="POST" action="{{ route('login') }}" class="grid gap-5" novalidate
          x-data="{ busy: false }" x-on:submit="busy = true" x-on:pageshow.window="busy = false">
        @csrf

        <x-form.input name="email" type="email" label="Email address" required
                      autocomplete="username" inputmode="email" :autofocus="! old('email')" />

        <x-form.password name="password" label="Password" required
                         autocomplete="current-password" :autofocus="(bool) old('email')" />

        <div class="flex flex-wrap items-center justify-between gap-3">
            <x-form.checkbox name="remember" label="Keep me signed in" unchecked="0" />

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}"
                   class="rounded text-sm font-medium text-maroon hover:text-maroon-dark hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-maroon/40">
                    Forgot your password?
                </a>
            @endif
        </div>

        <x-button class="w-full" x-bind:disabled="busy">
            <span x-show="! busy">Sign in</span>
            <span x-show="busy" x-cloak>Signing in…</span>
        </x-button>
    </form>
</x-guest-layout>
