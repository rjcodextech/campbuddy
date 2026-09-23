{{-- Errors for this form live in the "updatePassword" bag (Breeze's PasswordController). --}}
<form method="post" action="{{ route('password.update') }}">
    @csrf
    @method('put')

    <x-card title="Update password" description="Use a long, unique password so your account stays secure.">
        <div class="grid max-w-md grid-cols-1 gap-5">
            <x-form.password name="current_password" id="update_password_current_password" label="Current password"
                             autocomplete="current-password" bag="updatePassword" />
            <x-form.password name="password" id="update_password_password" label="New password"
                             autocomplete="new-password" bag="updatePassword" />
            <x-form.password name="password_confirmation" id="update_password_password_confirmation" label="Confirm new password"
                             autocomplete="new-password" bag="updatePassword" />
        </div>

        <x-slot:footer>
            <x-button>Update password</x-button>
        </x-slot:footer>
    </x-card>
</form>
