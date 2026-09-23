{{-- Separate from the profile form below: a form can't nest inside another. --}}
<form id="send-verification" method="post" action="{{ route('verification.send') }}">
    @csrf
</form>

<form method="post" action="{{ route('profile.update') }}">
    @csrf
    @method('patch')

    <x-card title="Profile information" description="Your name and the email address you sign in with.">
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <x-form.input name="name" label="Name" required autofocus autocomplete="name" :value="$user->name" />
            <x-form.input name="email" type="email" label="Email address" required autocomplete="username" :value="$user->email" />
        </div>

        @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
            <x-alert type="warning" class="mt-5">
                Your email address is unverified.
                <button form="send-verification" class="font-medium underline hover:no-underline focus:outline-none focus-visible:ring-2 focus-visible:ring-maroon/40">
                    Re-send the verification email.
                </button>
            </x-alert>
        @endif

        <x-slot:footer>
            <x-button>Save</x-button>
        </x-slot:footer>
    </x-card>
</form>
