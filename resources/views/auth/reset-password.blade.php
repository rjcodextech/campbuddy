<x-guest-layout title="Choose a new password" subtitle="Use a long, unique password you don't use anywhere else.">
    <form method="POST" action="{{ route('password.store') }}" class="grid gap-5" novalidate
          x-data="{ busy: false }" x-on:submit="busy = true" x-on:pageshow.window="busy = false">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-form.input name="email" type="email" label="Email address" required autofocus
                      autocomplete="username" inputmode="email" :value="$request->email" />

        <x-form.password name="password" label="New password" required autocomplete="new-password" />

        <x-form.password name="password_confirmation" label="Confirm new password" required autocomplete="new-password" />

        <x-button class="w-full" x-bind:disabled="busy">
            <span x-show="! busy">Reset password</span>
            <span x-show="busy" x-cloak>Saving…</span>
        </x-button>
    </form>
</x-guest-layout>
