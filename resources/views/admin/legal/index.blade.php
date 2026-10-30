<x-app-layout title="Legal documents" subtitle="Manage compliance terms, privacy notice, and attendee legal disclosures"
              :breadcrumbs="[['Legal documents']]">

    <div class="max-w-5xl space-y-6">
        @if (session('message'))
            <x-alert type="success">
                {{ session('message') }}
            </x-alert>
        @endif

        <x-alert type="info">
            <strong>DPDPA 2023 compliance &amp; legal transparency:</strong> Edit your event platform's privacy notice and terms of use here.
            When customized, your custom legal clauses are rendered directly on the public web app and native mobile app.
            Resetting any document safely reverts to CampBuddy's production-tested DPDPA 2023 compliant template without breaking any layouts.
        </x-alert>

        <x-card title="Platform legal documents" description="These documents apply globally across all event editions and mobile apps." flush>
            <ul class="divide-y divide-line">
                @foreach ($documents as $doc)
                    <li class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-base font-semibold text-ink">{{ $doc->name }}</h3>
                                <code class="rounded bg-paper px-1.5 py-0.5 text-xs font-mono text-muted">/{{ $doc->slug }}</code>
                                @if ($doc->is_customized)
                                    <x-badge variant="success">Customized</x-badge>
                                @else
                                    <x-badge>Default template</x-badge>
                                @endif
                            </div>
                            <p class="mt-1 text-sm text-muted">{{ $doc->description }}</p>
                            @if ($doc->is_outdated && $doc->changes)
                                <div class="mt-3 rounded-lg border border-gold/40 bg-gold/10 p-3 text-sm text-ink" role="status">
                                    <p class="font-semibold">The built-in notice changed after this copy was saved ({{ \App\Models\LegalDocument::defaultChangedAt($doc->slug)->format('j M Y') }}). The live page does not show:</p>
                                    <ul class="mt-1 list-disc pl-5">
                                        @foreach ($doc->changes as $change)
                                            <li>{{ $change }}</li>
                                        @endforeach
                                    </ul>
                                    <p class="mt-1">Reset to default (or add these in Edit) so the notice matches what the app does.</p>
                                </div>
                            @endif
                            <div class="mt-2 flex flex-wrap items-center gap-3 text-xs text-muted">
                                <span>Headline: <strong class="text-ink">{{ $doc->title }}</strong></span>
                                @if ($doc->updated_at)
                                    <span>&bull;</span>
                                    <span>Last modified {{ $doc->updated_at->diffForHumans() }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 sm:shrink-0">
                            <x-button :href="route($doc->view_route)" target="_blank" variant="secondary" size="sm" icon="external">
                                View live
                            </x-button>

                            <x-button :href="route('admin.legal.edit', $doc->slug)" size="sm" icon="pencil">
                                Edit
                            </x-button>

                            @if ($doc->is_customized)
                                <x-action-form :action="route('admin.legal.reset', $doc->slug)" method="POST"
                                               variant="danger-outline" size="sm"
                                               :confirm="'Reset '.$doc->name.' back to the built-in default template?'">
                                    Reset default
                                </x-action-form>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-card>

        <div class="rounded-lg border border-line bg-paper/60 p-5 text-xs text-muted">
            <h4 class="font-semibold text-ink">API &amp; Mobile App Synchronization</h4>
            <p class="mt-1">
                The native Android app and PWA fetch legal content directly via <code class="font-mono text-maroon-text">/api/legal/{slug}</code>.
                Any updates made here take effect immediately for mobile app users without requiring an app store update.
            </p>
        </div>
    </div>
</x-app-layout>
