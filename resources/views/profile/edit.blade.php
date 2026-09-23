<x-app-layout title="Account" subtitle="Your sign-in details and password." :breadcrumbs="[['Account']]">
    <div class="max-w-3xl space-y-6">
        @include('profile.partials.update-profile-information-form')
        @include('profile.partials.update-password-form')
        @include('profile.partials.delete-user-form')
    </div>
</x-app-layout>
