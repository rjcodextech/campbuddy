<x-app-layout title="Event managers" subtitle="People who can edit the events you give them — and nothing else."
              :breadcrumbs="[['Event managers']]">
    <x-alert type="warning" title="The database isn't ready for event managers yet">
        <p>This install is missing the tables event managers are stored in — the new code was uploaded, but its migration hasn't run.</p>
        <p class="mt-2 text-xs">
            <span class="font-semibold">Fix:</span>
            <code class="break-all rounded bg-white/70 px-1.5 py-0.5">php artisan migrate --force</code>
            (or <code class="rounded bg-white/70 px-1.5 py-0.5">php artisan campbuddy:doctor</code>), then reload this page.
        </p>
        <p class="mt-2 text-xs">The <a href="{{ route('admin.errors.index') }}" class="font-medium text-maroon hover:underline">Errors</a> page lists every migration that is waiting.</p>
    </x-alert>
</x-app-layout>
