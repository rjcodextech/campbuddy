<x-app-layout :title="'Edit ' . $docMeta['name']"
              subtitle="Update the content, purpose statements, and disclosures shown on the website and mobile app."
              :breadcrumbs="[['Legal documents', route('admin.legal.index')], ['Edit ' . $docMeta['name']]]">

    <div class="max-w-5xl space-y-6">
        @if ($errors->any())
            <x-alert type="danger">
                Please check the form below for errors before saving.
            </x-alert>
        @endif

        <form method="POST" action="{{ route('admin.legal.update', $slug) }}" class="space-y-6">
            @csrf
            @method('PUT')

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
                        <x-form.wysiwyg name="content" label="Document content (WYSIWYG editor)" required
                                         :value="$document?->content ?? $defaultContent"
                                         :defaultContent="$defaultContent"
                                         minHeight="520px"
                                         hint="Rich visual editor with formatting toolbar, responsive tables, callout boxes, live attendee preview and source code toggle." />
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
