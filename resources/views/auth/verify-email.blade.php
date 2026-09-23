<x-guest-layout title="Verify your email" subtitle="Click the link we emailed you to finish setting up. Didn't get it? We'll send another.">
    @if (session('status') === 'verification-link-sent')
        <x-alert type="success" class="mb-5">A new verification link has been sent to your email address.</x-alert>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-button>Resend verification email</x-button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-button variant="link">Sign out</x-button>
        </form>
    </div>
</x-guest-layout>
