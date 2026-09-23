<x-guest-layout title="Confirm your password" subtitle="This is a secure area. Please confirm your password to continue.">
    <form method="POST" action="{{ route('password.confirm') }}" class="grid gap-5" novalidate
          x-data="{ busy: false }" x-on:submit="busy = true" x-on:pageshow.window="busy = false">
        @csrf

        <x-form.password name="password" label="Password" required autofocus autocomplete="current-password" />

        <x-button class="w-full" x-bind:disabled="busy">
            <span x-show="! busy">Confirm</span>
            <span x-show="busy" x-cloak>Confirming…</span>
        </x-button>
    </form>
</x-guest-layout>
