{{-- Errors for the confirmation modal live in the "userDeletion" bag (ProfileController::destroy). --}}
<x-card title="Delete account" danger
        description="Permanently deletes your account and everything tied to it. This can't be undone.">
    <x-button type="button" variant="danger" icon="trash"
              x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')">
        Delete my account
    </x-button>
</x-card>

<x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" max-width="md" focusable>
    <form method="post" action="{{ route('profile.destroy') }}" class="p-6">
        @csrf
        @method('delete')

        <div class="flex gap-4">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-danger-soft text-danger">
                <x-icon name="exclamation-triangle" class="h-5 w-5" />
            </span>
            <div class="min-w-0">
                <h2 class="text-base font-semibold text-ink">Delete your account?</h2>
                <p class="mt-1 text-sm text-muted">
                    You'll lose access to the admin panel immediately. Enter your password to confirm.
                </p>
            </div>
        </div>

        <x-form.password name="password" id="delete_account_password" label="Password" bag="userDeletion"
                         autocomplete="current-password" class="mt-5" />

        <div class="mt-6 flex justify-end gap-3">
            <x-button type="button" variant="secondary" x-on:click="$dispatch('close')">Cancel</x-button>
            <x-button variant="danger">Delete account</x-button>
        </div>
    </form>
</x-modal>
