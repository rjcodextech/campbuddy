{{--
    Admin / manager → Event → Social media. Everything is drawn in the browser
    (resources/js/social-kit.js) from App\Support\SocialKit::data(): brand
    colours, event posts with captions, people cards (one ZIP), and the QR codes.

      @include('partials.social-media', ['event' => $event, 'kit' => $kit, 'codes' => $codes, 'colorsUrl' => …,
          'publishUrl' => …|null, 'webhookUrl' => …, 'webhookSet' => bool])
--}}
<div class="max-w-6xl space-y-6" data-social-kit>
    <x-alert type="info">
        CampBuddy's posts for {{ $event->display_name }}, in the event's own colours, with its logo, CampBuddy's mark and a QR to the
        event in CampBuddy on every card (each QR tagged for this event and the post or role). Pick a size and quality, edit the words,
        then download (several cards come as one ZIP), copy the caption or share. Check the words before posting.
    </x-alert>

    {{-- The WordCamp site's own colours, fonts and artwork (App\Support\SiteTheme), used by the designs. --}}
    @php($site = $kit['site'])
    <x-card title="From the WordCamp site" :description="$site['fetched_at'] ? 'Read from '.$event->source_site_url.' on '.\Carbon\Carbon::parse($site['fetched_at'])->format('j M, g:i A').'.' : ($site['error'] ?? 'Not read yet.')">
        <div class="space-y-4">
            <div>
                <p class="cb-label">Colours on the site</p>
                @if ($site['colors'] !== [])
                    <div class="flex flex-wrap items-center gap-2">
                        @foreach ($site['colors'] as $color)
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-line px-2 py-1 text-xs" title="{{ $color['name'] }}">
                                <span class="h-4 w-4 rounded-full border border-line" style="background: {{ $color['hex'] }}"></span>{{ $color['hex'] }}
                            </span>
                        @endforeach
                        <x-button type="button" variant="secondary" size="sm" data-colors-from-site>Use the site's colours</x-button>
                    </div>
                @else
                    <p class="text-sm text-muted">The site doesn't name any colours of its own: the logo's colours are used.</p>
                @endif
            </div>
            <div>
                <p class="cb-label">Fonts on the site</p>
                @if ($site['fonts'] !== [])
                    <label class="flex flex-wrap items-center gap-2 text-sm">
                        <input type="checkbox" class="cb-check" data-site-fonts checked>
                        <span>Use them: headlines in <strong>{{ $site['fonts']['heading']['family'] ?? '—' }}</strong>, text in <strong>{{ $site['fonts']['body']['family'] ?? '—' }}</strong></span>
                        <span class="text-xs text-muted" data-site-fonts-status></span>
                    </label>
                @else
                    <p class="text-sm text-muted">No web fonts found on the site: CampBuddy's own fonts are used.</p>
                @endif
            </div>
            <div>
                <p class="cb-label">Artwork ({{ count($site['art']) }} of {{ \App\Support\SiteTheme::SLOTS }})</p>
                <p class="mb-2 text-xs text-muted">From the site's pages and media library. A pattern makes the border strip and the background; an illustration (a skyline, a motif) stands at the foot of every card, in its own colours; a photo is the background of the Gradient style. Empty places take your own upload.</p>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                    @foreach ($site['art'] as $i => $art)
                        <div class="flex flex-col gap-1 text-xs">
                            <span class="flex h-24 items-center justify-center overflow-hidden rounded-lg border border-line bg-paper-soft">
                                <img src="{{ ($art['uploaded'] ?? false) ? $art['url'] : $imageUrl.'?u='.urlencode($art['url']) }}" alt="" class="max-h-full max-w-full object-contain" loading="lazy">
                            </span>
                            <label class="flex items-center gap-1"><input type="checkbox" class="cb-check" data-site-art="{{ $i }}" checked> {{ ucfirst($art['kind']) }}{{ ($art['uploaded'] ?? false) ? ' · uploaded' : '' }}</label>
                            <span class="truncate text-muted" title="{{ $art['name'] }}">{{ $art['name'] }}</span>
                            <form method="POST" action="{{ $artRemoveUrl }}" x-data x-on:submit="if (! confirm('Take this artwork off?')) $event.preventDefault()">
                                @csrf
                                <input type="hidden" name="piece" value="{{ $art['id'] }}">
                                <x-button variant="link" size="sm">Take off</x-button>
                            </form>
                        </div>
                    @endforeach
                    @for ($slot = count($site['art']); $slot < \App\Support\SiteTheme::SLOTS; $slot++)
                        <form method="POST" action="{{ $artUrl }}" enctype="multipart/form-data" class="flex flex-col gap-1.5 rounded-lg border border-dashed border-line p-2 text-xs">
                            @csrf
                            <span class="font-medium text-muted">Empty place {{ $slot + 1 }}</span>
                            <input type="file" name="art" accept="image/png,image/jpeg,image/webp" required class="w-full text-xs file:mr-2 file:rounded file:border-0 file:bg-paper-soft file:px-2 file:py-1">
                            <select name="kind" class="cb-input py-1 text-xs" aria-label="Kind of artwork">
                                <option value="illustration">Illustration</option>
                                <option value="pattern">Pattern</option>
                                <option value="photo">Photo</option>
                            </select>
                            <x-button type="submit" variant="secondary" size="sm" icon="upload">Upload</x-button>
                        </form>
                    @endfor
                </div>
                @error('art') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
            </div>
        </div>
        <x-slot:footer>
            <x-action-form :action="$siteUrl" variant="secondary" size="sm" icon="refresh">Read the site again</x-action-form>
        </x-slot:footer>
    </x-card>

    {{-- Brand colours --}}
    <form method="POST" action="{{ $colorsUrl }}" data-social-colors>
        @csrf
        <x-card title="Brand colours" description="{{ $kit['site']['colors'] !== [] ? 'Taken from the WordCamp site (made readable). Try others here if you like; the site colours come back next time.' : ($kit['colorsSaved'] ? 'Saved for this event.' : 'Not saved yet: these are picked from the event logo. Adjust and save.') }}">
            <div class="flex flex-wrap items-end gap-4">
                @foreach (['primary' => 'Main', 'secondary' => 'Accent', 'ink' => 'Text', 'paper' => 'Light'] as $key => $label)
                    <label class="flex flex-col gap-1 text-sm">
                        <span class="cb-label">{{ $label }}</span>
                        <input type="color" name="{{ $key }}" value="{{ $kit['colors'][$key] }}" class="h-10 w-16 cursor-pointer rounded border border-line" data-color="{{ $key }}">
                    </label>
                @endforeach
                <x-button type="button" variant="secondary" size="sm" data-colors-from-logo>Pick from logo</x-button>
                <span class="text-sm text-muted" data-colors-status aria-live="polite"></span>
            </div>
            <p class="mt-3 text-xs text-muted">Every card uses only these four colours and tones between them.</p>
            <p class="mt-1 text-xs" data-contrast aria-live="polite"></p>
            <x-slot:footer>
                <x-button size="sm">Save colours</x-button>
            </x-slot:footer>
        </x-card>
    </form>

    {{-- Size, style and people-card design: for every image on the page. --}}
    <div class="sticky top-0 z-10 -mx-1 flex flex-wrap items-end gap-3 rounded-xl border border-line bg-white/95 px-4 py-3 shadow-sm backdrop-blur">
        <label class="flex flex-col gap-1 text-sm">
            <span class="cb-label">Size</span>
            <select class="cb-input w-auto" data-social-size>
                <option value="1080x1350">Portrait 1080 × 1350 (feed)</option>
                <option value="1080x1080">Square 1080 × 1080</option>
                <option value="1080x1920">Story 1080 × 1920</option>
            </select>
        </label>
        <div class="flex flex-col gap-1 text-sm">
            <span class="cb-label" id="social-quality-label">Quality</span>
            <div class="flex flex-wrap gap-1" role="group" aria-labelledby="social-quality-label">
                <button type="button" class="cb-btn cb-btn-sm cb-btn-primary" data-social-quality="social" aria-pressed="true" title="1080 px wide">Social</button>
                <button type="button" class="cb-btn cb-btn-sm cb-btn-secondary" data-social-quality="print" aria-pressed="false" title="6× size (6480 px wide), 600 DPI">Print (6×, 600 DPI)</button>
            </div>
        </div>
        <div class="flex flex-col gap-1 text-sm">
            <span class="cb-label" id="social-style-label">Post design</span>
            <div class="flex flex-wrap gap-1" role="group" aria-labelledby="social-style-label">
                @foreach (['classic' => 'Classic', 'poster' => 'Poster', 'frame' => 'Frame', 'ticket' => 'Ticket', 'magazine' => 'Magazine', 'split' => 'Split', 'minimal' => 'Minimal'] as $key => $label)
                    <button type="button" class="cb-btn cb-btn-sm {{ $loop->first ? 'cb-btn-primary' : 'cb-btn-secondary' }}" data-social-style="{{ $key }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}">{{ $label }}</button>
                @endforeach
            </div>
        </div>
        <div class="flex flex-col gap-1 text-sm">
            <span class="cb-label" id="social-people-design-label">People card design</span>
            <div class="flex flex-wrap gap-1" role="group" aria-labelledby="social-people-design-label">
                @foreach (['classic' => 'Classic', 'polaroid' => 'Polaroid', 'badge' => 'Badge', 'split' => 'Split', 'minimal' => 'Minimal', 'spotlight' => 'Spotlight', 'arch' => 'Arch'] as $key => $label)
                    <button type="button" class="cb-btn cb-btn-sm {{ $loop->first ? 'cb-btn-primary' : 'cb-btn-secondary' }}" data-people-design="{{ $key }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}">{{ $label }}</button>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Event posts --}}
    <section class="space-y-3" aria-labelledby="social-posts-title">
        <h2 id="social-posts-title" class="text-base font-semibold">Event posts</h2>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($kit['posts'] as $post)
                <x-card :title="$post['label']" data-social-post="{{ $post['key'] }}">
                    <div class="space-y-3">
                        {{-- A small preview (so the fields below stay in view); downloads are full size. --}}
                        <canvas class="mx-auto block h-72 w-auto max-w-full rounded-lg border border-line bg-paper-soft" data-preview></canvas>
                        <div class="hidden items-center justify-center gap-2 text-sm [&:not(.hidden)]:flex" data-pager>
                            <button type="button" class="cb-btn cb-btn-secondary cb-btn-sm" data-prev aria-label="Previous card">‹</button>
                            <span data-page-label class="tabular-nums text-muted"></span>
                            <button type="button" class="cb-btn cb-btn-secondary cb-btn-sm" data-next aria-label="Next card">›</button>
                        </div>
                        @if (($post['picker'] ?? null) === 'day' && $kit['schedule']['days'] !== [])
                            <div>
                                <label class="cb-label" for="post-{{ $post['key'] }}-pick">Day</label>
                                <select id="post-{{ $post['key'] }}-pick" class="cb-input" data-pick>
                                    @foreach ($kit['schedule']['days'] as $day)
                                        <option value="{{ $day['key'] }}">{{ $day['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @elseif (($post['picker'] ?? null) === 'session' && $kit['schedule']['sessions'] !== [])
                            <div>
                                <label class="cb-label" for="post-{{ $post['key'] }}-pick">Session</label>
                                <select id="post-{{ $post['key'] }}-pick" class="cb-input" data-pick>
                                    @foreach ($kit['schedule']['sessions'] as $session)
                                        <option value="{{ $session['id'] }}" @selected($session['id'] === $kit['schedule']['next'])>{{ $session['time'] }} · {{ \Illuminate\Support\Str::limit($session['title'], 60) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
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
                <p class="text-sm text-muted">A card for everyone marked on the attendee list (Attendees / Roster → Roles) and everyone who shows their Camp Card there: name, photo, role and talk, the event's branding and a QR to CampBuddy tagged with their role.</p>
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

    <script type="application/json" id="social-kit-data">{!! json_encode($kit + ['publishUrl' => $publishUrl, 'imageUrl' => $imageUrl], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</script>
</div>
