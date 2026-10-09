{{--
    Admin / manager → Event → Social media. Everything is drawn in the browser
    (resources/js/social-kit.js) from App\Support\SocialKit::data(): brand
    colours, event posts with captions, people cards (one ZIP), and the QR codes.

      @include('partials.social-media', ['event' => $event, 'kit' => $kit, 'codes' => $codes, 'colorsUrl' => …,
          'publishUrl' => …|null, 'webhookUrl' => …, 'webhookSet' => bool])
--}}
<div class="max-w-6xl space-y-6" data-social-kit>
    <x-alert type="info">
        Ready-made posts for {{ $event->display_name }} in its own colours: pick a size, edit the words, then copy the caption and
        download the image (or share it straight from your phone). Captions are written from the event's details — check them before posting.
    </x-alert>

    {{-- Brand colours --}}
    <form method="POST" action="{{ $colorsUrl }}" data-social-colors>
        @csrf
        <x-card title="Brand colours" description="{{ $kit['colorsSaved'] ? 'Saved for this event.' : 'Not saved yet: these are picked from the event logo. Adjust and save.' }}">
            <div class="flex flex-wrap items-end gap-4">
                @foreach (['primary' => 'Main', 'secondary' => 'Accent', 'ink' => 'Text', 'paper' => 'Light'] as $key => $label)
                    <label class="flex flex-col gap-1 text-sm">
                        <span class="cb-label">{{ $label }}</span>
                        <input type="color" name="{{ $key }}" value="{{ $kit['colors'][$key] }}" class="h-10 w-16 cursor-pointer rounded border border-line" data-color="{{ $key }}">
                    </label>
                @endforeach
                <x-button type="button" variant="secondary" size="sm" data-colors-from-logo>Pick from logo</x-button>
            </div>
            <x-slot:footer>
                <x-button size="sm">Save colours</x-button>
            </x-slot:footer>
        </x-card>
    </form>

    {{-- Event posts --}}
    <section class="space-y-3" aria-labelledby="social-posts-title">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="social-posts-title" class="text-base font-semibold">Event posts</h2>
            <label class="flex items-center gap-2 text-sm">
                <span>Size</span>
                <select class="cb-input w-auto" data-social-size>
                    <option value="1080x1350">1080 × 1350 (portrait: Instagram, LinkedIn, Facebook)</option>
                    <option value="1080x1080">1080 × 1080 (square)</option>
                </select>
            </label>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($kit['posts'] as $post)
                <x-card :title="$post['label']" data-social-post="{{ $post['key'] }}">
                    <div class="space-y-3">
                        <canvas class="w-full rounded-lg border border-line bg-paper-soft" data-preview></canvas>
                        <div>
                            <label class="cb-label" for="post-{{ $post['key'] }}-headline">Headline on the image</label>
                            <input id="post-{{ $post['key'] }}-headline" type="text" maxlength="60" value="{{ $post['headline'] }}" class="cb-input" data-field="headline">
                        </div>
                        <div>
                            <label class="cb-label" for="post-{{ $post['key'] }}-line">Line under it</label>
                            <input id="post-{{ $post['key'] }}-line" type="text" maxlength="90" value="{{ $post['line'] }}" class="cb-input" data-field="line">
                        </div>
                        <div>
                            <label class="cb-label" for="post-{{ $post['key'] }}-caption">Caption</label>
                            <textarea id="post-{{ $post['key'] }}-caption" rows="6" class="cb-input text-sm" data-field="caption">{{ $post['caption'] }}</textarea>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <x-button type="button" size="sm" icon="download" data-action="download">Download</x-button>
                            <x-button type="button" variant="secondary" size="sm" data-action="copy">Copy caption</x-button>
                            <x-button type="button" variant="secondary" size="sm" data-action="share">Share</x-button>
                            @if ($publishUrl)
                                <x-button type="button" variant="secondary" size="sm" data-action="publish">Publish</x-button>
                            @endif
                        </div>
                        <p class="text-xs text-muted" data-share-links></p>
                    </div>
                </x-card>
            @endforeach
        </div>
    </section>

    {{-- People cards --}}
    <section class="space-y-3" aria-labelledby="social-people-title" data-social-people>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 id="social-people-title" class="text-base font-semibold">People cards</h2>
                <p class="text-sm text-muted">"Meet our speaker" style posts for everyone marked on the attendee list (Attendees / Roster → Roles) and everyone who shows their Camp Card there. Name, photo, role and talk only.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <label class="sr-only" for="people-role">Who</label>
                <select id="people-role" class="cb-input w-auto" data-people-role>
                    <option value="">Everyone ({{ count($kit['people']) }})</option>
                    @foreach (\App\Support\RosterRoles::LABELS as $role => $label)
                        @php($n = collect($kit['people'])->filter(fn ($p) => in_array($role, $p['roles'], true))->count())
                        @if ($n > 0)
                            <option value="{{ $role }}">{{ $label }}s ({{ $n }})</option>
                        @endif
                    @endforeach
                    @php($withCard = collect($kit['people'])->filter(fn ($p) => $p['card'])->count())
                    @if ($withCard > 0)
                        <option value="card">Shared a Camp Card ({{ $withCard }})</option>
                    @endif
                </select>
                <x-button type="button" size="sm" icon="download" data-people-zip>Download all (ZIP)</x-button>
            </div>
        </div>
        <p class="text-sm text-muted" data-people-status aria-live="polite"></p>
        @if ($kit['people'] === [])
            <x-card>
                <p class="py-4 text-center text-sm text-muted">Nobody yet. Mark speakers, organizers, volunteers… on the attendee list first.</p>
            </x-card>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" data-people-grid></div>
            <div class="text-center"><x-button type="button" variant="secondary" size="sm" data-people-more hidden>Show more</x-button></div>
        @endif
    </section>

    @if ($publishUrl !== null || $webhookUrl)
        @include('partials.social-publish', ['webhookUrl' => $webhookUrl, 'webhookSet' => $webhookSet])
    @endif

    {{-- QR codes (the kit that was its own tab) --}}
    <section id="qr" class="space-y-3" aria-labelledby="social-qr-title">
        <h2 id="social-qr-title" class="text-base font-semibold">QR codes</h2>
        @include('partials.qr-kit', ['event' => $event, 'codes' => $codes])
    </section>

    <script type="application/json" id="social-kit-data">{!! json_encode($kit + ['publishUrl' => $publishUrl], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</script>
</div>
