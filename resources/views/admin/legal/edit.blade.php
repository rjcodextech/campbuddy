<x-app-layout :title="'Edit ' . $docMeta['name']"
              subtitle="Update the content, purpose statements, and disclosures shown on the website and mobile app."
              :breadcrumbs="[['Legal documents', route('admin.legal.index')], ['Edit ' . $docMeta['name']]]">

    <div class="max-w-4xl space-y-6">
        @if ($errors->any())
            <x-alert type="danger">
                Please check the form below for errors before saving.
            </x-alert>
        @endif

        <form method="POST" action="{{ route('admin.legal.update', $slug) }}" class="space-y-6">
            @csrf

            <x-card :title="$docMeta['name'] . ' details'">
                <div class="space-y-5">
                    <x-form.input name="title" label="Document headline" required
                                  :value="$document?->title ?: $defaultMeta['title']"
                                  placeholder="e.g. Privacy notice &amp; consent"
                                  hint="Main heading displayed at the top of the legal page and mobile sheet." />

                    <x-form.input name="meta_description" label="Meta description (SEO)"
                                  :value="$document?->meta_description ?: $defaultMeta['meta_description']"
                                  placeholder="e.g. CampBuddy data collection, purpose limitations, and attendee rights."
                                  hint="Used for social shares and search engine snippets (under 160 characters recommended)." />

                    <div>
                        <x-form.textarea name="content" label="Document content (HTML / formatted text)" required
                                         :value="$document?->content ?? ''"
                                         rows="18"
                                         hint="HTML tags like <h2>, <h3>, <p>, <ul>, <li>, <strong>, <code>, and <a> are fully supported with responsive prose styling." />

                        <div class="mt-2 rounded-md bg-paper p-3 text-xs text-muted">
                            <span class="font-semibold text-ink">Formatting Tip:</span> You can use standard HTML tags
                            (e.g. <code class="font-mono text-maroon">&lt;h2&gt;</code>, <code class="font-mono text-maroon">&lt;p&gt;</code>,
                            <code class="font-mono text-maroon">&lt;ul&gt;</code>, <code class="font-mono text-maroon">&lt;li&gt;</code>,
                            <code class="font-mono text-maroon">&lt;a href="..."&gt;</code>). The frontend prose styling will automatically format them cleanly to match CampBuddy's design system.
                        </div>
                    </div>
                </div>

                <x-slot:footer>
                    <div class="flex w-full items-center justify-between">
                        <div class="flex items-center gap-2">
                            <x-button :href="route('admin.legal.index')" variant="secondary">Cancel</x-button>
                            <x-button>Save changes</x-button>
                        </div>

                        @if ($document && !empty(trim($document->content ?? '')))
                            <a href="{{ route( $docMeta['view_route'] ) }}" target="_blank" rel="noopener noreferrer"
                               class="text-xs text-maroon hover:underline">
                                Preview live page &rarr;
                            </a>
                        @endif
                    </div>
                </x-slot:footer>
            </x-card>
        </form>
    </div>
</x-app-layout>
