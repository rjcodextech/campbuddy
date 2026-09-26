<x-manager-guest-layout title="Event manager sign in" subtitle="Sign in to update the events an admin has given you.">
    {{-- busy: stops a double submit and shows progress; pageshow resets it
    if the browser restores this page from the back/forward cache. --}}
    <form method="POST" action="{{ route('manager.login.store') }}" class="grid gap-5" novalidate
          x-data="{ busy: false }" x-on:submit="busy = true" x-on:pageshow.window="busy = false">
        @csrf

        <x-form.input name="email" type="email" label="Email address" required
                      autocomplete="username" inputmode="email" :autofocus="! old('email')" />

        <x-form.password name="password" label="Password" required
                         autocomplete="current-password" :autofocus="(bool) old('email')" />

        <x-form.checkbox name="remember" label="Keep me signed in" unchecked="0" />

        <x-button class="w-full" x-bind:disabled="busy">
            <span x-show="! busy">Sign in</span>
            <span x-show="busy" x-cloak>Signing in…</span>
        </x-button>
    </form>

    <p class="mt-5 border-t border-line pt-4 text-xs text-muted">
        Forgot your password? Ask an admin to set a new one for you.
    </p>
</x-manager-guest-layout>
