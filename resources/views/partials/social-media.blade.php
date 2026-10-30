{{--
    Admin / manager → Event → Social media. Everything is drawn in the browser
    (resources/js/social-kit.js) from App\Support\SocialKit::data(): brand
    colours, event posts with captions, people cards (one ZIP), and the QR codes.

      @include('partials.social-media', ['event' => $event, 'kit' => $kit, 'codes' => $codes, 'colorsUrl' => …,
          'publishUrl' => …|null, 'webhookUrl' => …, 'webhookSet' => bool,
          'musicUrl' => …, 'studioTracks' => App\Support\StudioMusic::tracks()])
--}}
<div class="max-w-6xl space-y-6" data-social-kit>
    <x-alert type="info">
        Posts, people cards and reels for {{ $event->display_name }}, in the event's own colours, with its logo, CampBuddy × WPSimplified
        and a QR to the event in CampBuddy on every card (each QR tagged for this event and the post or role). Open the studio to edit and
        download them; the posts come in the order to share them, each with the day it works best. Check the words before posting.
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
                <p class="mb-2 text-xs text-muted">From the site's pages and media library, best first (too small, logos, people, frames and graphics with words are left out). A pattern lines the cards; an illustration (a skyline, a motif) stands inside the design; a photo of the place is the picture behind picture-led cards. Wrong kind? Change it below the piece. Empty places take your own upload.</p>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                    @foreach ($site['art'] as $i => $art)
                        <div class="flex flex-col gap-1 text-xs">
                            <span class="flex h-24 items-center justify-center overflow-hidden rounded-lg border border-line bg-paper-soft">
                                <img src="{{ ($art['uploaded'] ?? false) ? $art['url'] : $imageUrl.'?u='.urlencode($art['url']) }}" alt="" class="max-h-full max-w-full object-contain" loading="lazy">
                            </span>
                            <label class="flex items-center gap-1"><input type="checkbox" class="cb-check" data-site-art="{{ $i }}" checked> Use{{ ($art['uploaded'] ?? false) ? ' · uploaded' : '' }}</label>
                            <span class="truncate text-muted" title="{{ $art['name'] }}{{ isset($art['width'], $art['height']) ? ' · '.$art['width'].' × '.$art['height'].' px' : '' }}">{{ $art['name'] }}</span>
                            {{-- What it is decides where it goes on a card; CampBuddy guesses, this corrects it. --}}
                            <form method="POST" action="{{ $artKindUrl }}">
                                @csrf
                                <input type="hidden" name="piece" value="{{ $art['id'] }}">
                                <select name="kind" class="cb-input py-1 text-xs" aria-label="What {{ $art['name'] }} is" onchange="this.form.submit()">
                                    @foreach (\App\Support\SiteTheme::KINDS as $kind)
                                        <option value="{{ $kind }}" @selected($art['kind'] === $kind)>{{ ucfirst($kind) }}</option>
                                    @endforeach
                                </select>
                                <noscript><x-button type="submit" variant="link" size="sm">Save</x-button></noscript>
                            </form>
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

                {{-- The rest of what the site has: any of it can take a place ("Use this"). --}}
                @if (! empty($site['more']))
                    <details class="mt-4">
                        <summary class="cursor-pointer text-sm font-medium text-maroon-text">More from the site ({{ count($site['more']) }})</summary>
                        <p class="mt-1 text-xs text-muted">"Use this" puts a piece first; the last piece CampBuddy picked by itself makes room.</p>
                        <div class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                            @foreach ($site['more'] as $piece)
                                <div class="flex flex-col gap-1 text-xs">
                                    <span class="flex h-20 items-center justify-center overflow-hidden rounded-lg border border-line bg-paper-soft">
                                        <img src="{{ $imageUrl.'?u='.urlencode($piece['url']) }}" alt="" class="max-h-full max-w-full object-contain" loading="lazy">
                                    </span>
                                    <span class="truncate text-muted" title="{{ $piece['name'] }}">{{ ucfirst($piece['kind']) }} · {{ $piece['name'] }}{{ $piece['hidden'] ? ' (taken off)' : '' }}</span>
                                    <form method="POST" action="{{ $artUseUrl }}">
                                        @csrf
                                        <input type="hidden" name="piece" value="{{ $piece['id'] }}">
                                        <x-button type="submit" variant="secondary" size="sm">Use this</x-button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    </details>
                @endif
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

    {{-- The studio (resources/js/social-studio.js): posts, people cards and reels, one at a time and full screen —
         the list on the left, the big preview in the middle, its edits on the right. Edits are kept in this browser. --}}
    @php($peopleCount = count($kit['people']))
    <section class="space-y-3" aria-labelledby="social-studio-title">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 id="social-studio-title" class="text-base font-semibold">Studio</h2>
                <p class="text-sm text-muted">Make, edit and download the event's posts, people cards and reels, full screen. Every edit is kept in this browser.</p>
            </div>
            <x-button type="button" icon="download" data-posts-zip>Download all posts (ZIP)</x-button>
        </div>
        <div class="grid gap-4 sm:grid-cols-3">
            @foreach ([['posts', 'Posts', count($kit['posts']).' posts, in the order to share them', 'image'], ['people', 'People cards', $peopleCount.' '.($peopleCount === 1 ? 'card' : 'cards').' for speakers, organizers, volunteers…', 'users'], ['reels', 'Reels', 'Videos with music, 30 s or more, five templates', 'megaphone']] as [$key, $label, $hint, $icon])
                <button type="button" data-studio-open="{{ $key }}" class="group flex items-start gap-4 rounded-xl border border-line bg-white p-5 text-left shadow-xs transition hover:-translate-y-1 hover:border-maroon/30 hover:shadow-lg focus:outline-hidden focus-visible:ring-2 focus-visible:ring-maroon/40">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-maroon/10 text-maroon-text"><x-icon :name="$icon" class="h-5 w-5" /></span>
                    <span class="min-w-0">
                        <span class="block font-display text-lg font-bold text-ink">{{ $label }}</span>
                        <span class="block text-sm text-muted">{{ $hint }}</span>
                        <span class="mt-2 inline-flex items-center gap-1 text-sm font-semibold text-maroon-text">Open studio <span aria-hidden="true">→</span></span>
                    </span>
                </button>
            @endforeach
        </div>
        <p class="text-sm text-muted" data-posts-zip-status aria-live="polite"></p>
        {{-- The posting plan (App\Support\SocialPlan) as a calendar: a tentative day for every post,
             the event days marked; a post opens it in the studio. Phone reminders on those days for
             whoever turns them on (campbuddy:post-reminders). --}}
        @php($remindersUrl ??= null)
        @php($weeks = \App\Support\SocialPlan::calendar($event, $kit['posts']))
        @php($phase = fn (string $key) => match (true) {
            in_array($key, ['save-the-date', 'venue', 'speakers', 'sponsors', 'schedule'], true) => 'bg-navy/10 text-navy ring-navy/20',
            in_array($key, ['first-timer', 'contributor', 'wpsimplified', 'app', 'meet', 'countdown'], true) => 'bg-teal/10 text-teal ring-teal/20',
            in_array($key, ['today', 'spotlight', 'moments'], true) => 'bg-maroon/10 text-maroon-text ring-maroon/20',
            default => 'bg-gold/15 text-ink ring-gold/30',
        })
        <x-card title="Posting plan" description="A tentative day to share each post, counted back from the event's first day. Click a post to open it in the studio.">
            @if ($weeks === [])
                <p class="text-sm text-muted">Set the event's dates to see the plan.</p>
            @else
                <div class="mb-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted">
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-navy/60"></span>Announce</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-teal/70"></span>Build up</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-maroon/70"></span>On the days</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-gold"></span>After</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded bg-maroon/15 ring-1 ring-maroon/30"></span>Event days</span>
                </div>
                <div class="overflow-x-auto">
                    <div class="grid min-w-176 grid-cols-7 gap-px overflow-hidden rounded-lg border border-line bg-line text-xs" role="grid" aria-label="Posting plan calendar">
                        @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dow)
                            <div class="bg-paper-soft px-2 py-1.5 text-center font-semibold uppercase tracking-wide text-muted" role="columnheader">{{ $dow }}</div>
                        @endforeach
                        @foreach ($weeks as $week)
                            @foreach ($week as $day)
                                <div role="gridcell" @class([
                                    'min-h-22 space-y-1 p-1.5',
                                    'bg-white' => ! $day['event'] && ! $day['past'],
                                    'bg-paper/70' => $day['past'] && ! $day['event'],
                                    'bg-maroon/5' => $day['event'],
                                ])>
                                    <div class="flex items-center justify-between">
                                        <span @class([
                                            'inline-flex h-6 min-w-6 items-center justify-center rounded-full px-1 font-semibold tabular-nums',
                                            'bg-maroon-text text-white' => $day['today'],
                                            'text-muted' => ! $day['today'] && $day['past'],
                                            'text-ink' => ! $day['today'] && ! $day['past'],
                                        ])>{{ $day['day'] }}</span>
                                        @if ($day['month'])
                                            <span class="font-semibold uppercase tracking-wide text-muted">{{ $day['month'] }}</span>
                                        @elseif ($day['event'])
                                            <span class="font-semibold text-maroon-text">Event</span>
                                        @endif
                                    </div>
                                    @foreach ($day['posts'] as $post)
                                        <button type="button" data-studio-open="posts" data-studio-post="{{ $post['key'] }}" title="{{ $post['label'] }} · {{ $post['when'] }}"
                                                class="block w-full truncate rounded-md px-1.5 py-1 text-left font-semibold ring-1 ring-inset transition hover:-translate-y-0.5 hover:shadow-xs focus:outline-hidden focus-visible:ring-2 focus-visible:ring-maroon/50 {{ $phase($post['key']) }} {{ $day['past'] && ! $day['today'] ? 'opacity-60' : '' }}">
                                            {{ $post['label'] }}
                                        </button>
                                    @endforeach
                                </div>
                            @endforeach
                        @endforeach
                    </div>
                </div>
            @endif
            @if ($remindersUrl)
                <x-slot:footer>
                    <form method="POST" action="{{ $remindersUrl }}" class="flex flex-wrap items-center gap-3">
                        @csrf
                        <input type="hidden" name="on" value="{{ ($remindersOn ?? false) ? 0 : 1 }}">
                        <x-icon name="megaphone" class="h-5 w-5 text-maroon-text" />
                        <span class="text-sm">
                            <span class="font-semibold">Remind me on my phone when a post is due</span>
                            <span class="block text-xs text-muted">
                                {{ ($remindersOn ?? false) ? 'On.' : 'Off.' }}
                                @if (($remindersPhones ?? 0) > 0)
                                    Goes to the CampBuddy app on {{ $remindersPhones }} {{ $remindersPhones === 1 ? 'phone' : 'phones' }} signed in as you, in the venue's morning.
                                @else
                                    Sign in to the CampBuddy app as an organizer and allow alerts, so it can reach your phone.
                                @endif
                            </span>
                        </span>
                        <x-button size="sm" :variant="($remindersOn ?? false) ? 'secondary' : 'primary'" class="ml-auto">{{ ($remindersOn ?? false) ? 'Turn off' : 'Turn on' }}</x-button>
                    </form>
                </x-slot:footer>
            @endif
        </x-card>
    </section>

    <dialog class="cb-studio" data-studio aria-labelledby="studio-title">
        <div class="flex h-full flex-col">
            {{-- The studio's bar: which event, the three tabs, the settings every image shares, close. --}}
            <header class="flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-line bg-white px-4 py-2">
                <div class="flex min-w-0 items-center gap-2">
                    @if ($event->markUrl())
                        <img src="{{ $event->markUrl() }}" alt="" class="h-8 w-8 rounded-md object-contain">
                    @endif
                    <h2 id="studio-title" class="truncate font-display text-lg font-bold">Studio <span class="font-sans text-sm font-medium text-muted">· {{ $event->display_name }}</span></h2>
                </div>
                <nav class="flex gap-1 rounded-full bg-paper-soft p-1" role="tablist" aria-label="Studio">
                    @foreach (['posts' => 'Posts', 'people' => 'People cards', 'reels' => 'Reels'] as $key => $label)
                        <button type="button" role="tab" data-studio-tab="{{ $key }}" aria-selected="false" class="rounded-full px-4 py-1.5 text-sm font-semibold text-muted aria-selected:bg-white aria-selected:text-maroon-text aria-selected:shadow-xs">{{ $label }}</button>
                    @endforeach
                </nav>
                <div class="ml-auto flex flex-wrap items-center gap-2">
                    <label class="sr-only" for="studio-size">Size</label>
                    <select id="studio-size" class="cb-input w-auto py-1.5 text-sm" data-social-size>
                        <option value="1080x1350">Instagram feed · 1080 × 1350</option>
                        <option value="1080x1080">Instagram / LinkedIn square · 1080 × 1080</option>
                        <option value="1080x1920">Story / reel cover · 1080 × 1920</option>
                        <option value="1600x900">X post · 1600 × 900</option>
                        <option value="1200x627">LinkedIn / blog link · 1200 × 627</option>
                    </select>
                    <div class="flex gap-1" role="group" aria-label="Quality">
                        <button type="button" class="cb-btn cb-btn-sm cb-btn-primary" data-social-quality="social" aria-pressed="true" title="Twice the platform size, PNG, 300 DPI: sharp on every phone">HD</button>
                        <button type="button" class="cb-btn cb-btn-sm cb-btn-secondary" data-social-quality="print" aria-pressed="false" title="6× size, 600 DPI">Print</button>
                    </div>
                    <button type="button" class="cb-btn cb-btn-secondary cb-btn-sm" data-studio-close aria-label="Close the studio">✕ Close</button>
                </div>
            </header>

            {{-- Posts --}}
            <div class="min-h-0 flex-1" data-studio-panel="posts" hidden>
                <div class="grid h-full lg:grid-cols-[15rem_minmax(0,1fr)]">
                    <aside class="flex min-h-0 flex-col border-b border-line bg-white lg:border-b-0 lg:border-r">
                        <div class="space-y-2 border-b border-line p-3">
                            <span class="cb-label" id="social-style-label">Design</span>
                            <div class="flex flex-wrap gap-1" role="group" aria-labelledby="social-style-label">
                                @foreach (['daylight' => 'Daylight', 'classic' => 'Classic', 'poster' => 'Poster', 'frame' => 'Frame', 'ticket' => 'Ticket', 'magazine' => 'Magazine', 'split' => 'Split', 'minimal' => 'Minimal'] as $key => $label)
                                    <button type="button" class="cb-btn cb-btn-sm {{ $loop->first ? 'cb-btn-primary' : 'cb-btn-secondary' }}" data-social-style="{{ $key }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}">{{ $label }}</button>
                                @endforeach
                            </div>
                        </div>
                        <div class="border-b border-line p-3">
                            <x-button type="button" size="sm" icon="download" class="w-full" data-posts-zip>Download all posts (ZIP)</x-button>
                        </div>
                        <ol class="flex min-h-0 flex-1 gap-2 overflow-auto p-2 lg:flex-col" aria-label="Posts, in the order to share them">
                            @foreach ($kit['posts'] as $post)
                                <li class="shrink-0">
                                    <button type="button" data-studio-item="{{ $post['key'] }}" aria-current="false" class="flex w-56 items-center gap-3 rounded-lg p-2 text-left hover:bg-paper-soft aria-current:bg-maroon/10 lg:w-full">
                                        <canvas class="h-16 w-[3.2rem] shrink-0 rounded border border-line bg-paper-soft" width="104" height="130" data-studio-thumb="{{ $post['key'] }}"></canvas>
                                        <span class="min-w-0">
                                            <span class="block truncate text-sm font-semibold text-ink">{{ $loop->iteration }}. {{ $post['label'] }}</span>
                                            @if ($post['when'] ?? null)
                                                <span class="block truncate text-xs text-muted">{{ ($post['date_label'] ?? null) ? $post['date_label'].' · ' : '' }}{{ $post['when'] }}</span>
                                            @endif
                                        </span>
                                    </button>
                                </li>
                            @endforeach
                        </ol>
                    </aside>

                    <div class="min-h-0">
                        @foreach ($kit['posts'] as $post)
                            <div class="grid h-full min-h-0 lg:grid-cols-[minmax(0,1fr)_24rem]" data-social-post="{{ $post['key'] }}" hidden>
                                <div class="flex min-h-0 flex-col items-center justify-center gap-3 bg-paper-soft p-4">
                                    <canvas class="block max-h-[calc(100dvh-11rem)] w-auto max-w-full rounded-xl border border-line bg-white shadow-lg" data-preview></canvas>
                                    <div class="hidden items-center justify-center gap-2 text-sm [&:not(.hidden)]:flex" data-pager>
                                        <button type="button" class="cb-btn cb-btn-secondary cb-btn-sm" data-prev aria-label="Previous card">‹</button>
                                        <span data-page-label class="tabular-nums text-muted"></span>
                                        <button type="button" class="cb-btn cb-btn-secondary cb-btn-sm" data-next aria-label="Next card">›</button>
                                    </div>
                                </div>

                                <div class="min-h-0 space-y-5 overflow-y-auto border-l border-line bg-white p-4">
                                    <div>
                                        <p class="font-display text-lg font-bold">{{ $post['label'] }}</p>
                                        @if ($post['when'] ?? null)
                                            <p class="text-xs text-muted">Share on {{ $post['date_label'] ?? 'its day' }} ({{ $post['when'] }})</p>
                                        @endif
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

                                    <fieldset class="space-y-3">
                                        <legend class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted">Text</legend>
                                        <div>
                                            <label class="cb-label" for="post-{{ $post['key'] }}-kicker">Kicker</label>
                                            <input id="post-{{ $post['key'] }}-kicker" type="text" maxlength="40" value="{{ $post['kicker'] ?? '' }}" class="cb-input" data-field="kicker">
                                        </div>
                                        <div>
                                            <label class="cb-label" for="post-{{ $post['key'] }}-headline">Headline</label>
                                            <input id="post-{{ $post['key'] }}-headline" type="text" maxlength="60" value="{{ $post['headline'] }}" class="cb-input" data-field="headline" aria-describedby="post-{{ $post['key'] }}-headline-hint">
                                            <p id="post-{{ $post['key'] }}-headline-hint" class="cb-hint">Wrap words in *stars* to highlight them.</p>
                                        </div>
                                        <div>
                                            <label class="cb-label" for="post-{{ $post['key'] }}-line">Line under it</label>
                                            <input id="post-{{ $post['key'] }}-line" type="text" maxlength="90" value="{{ $post['line'] }}" class="cb-input" data-field="line">
                                        </div>
                                        <div>
                                            <label class="cb-label" for="post-{{ $post['key'] }}-caption">Caption</label>
                                            <textarea id="post-{{ $post['key'] }}-caption" rows="5" class="cb-input text-sm" data-field="caption">{{ $post['caption'] }}</textarea>
                                        </div>
                                    </fieldset>

                                    <fieldset>
                                        <legend class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted">Colours</legend>
                                        <label class="mb-2 flex items-center gap-2 text-sm"><input type="checkbox" class="cb-check" data-post-own-colors> Own colours for this post</label>
                                        <div class="flex flex-wrap items-end gap-3">
                                            @foreach (['primary' => 'Main', 'secondary' => 'Accent', 'ink' => 'Text', 'paper' => 'Light'] as $key => $label)
                                                <label class="flex flex-col gap-1 text-xs text-muted">
                                                    {{ $label }}
                                                    <input type="color" value="{{ $kit['colors'][$key] }}" class="h-9 w-12 cursor-pointer rounded border border-line" data-post-color="{{ $key }}">
                                                </label>
                                            @endforeach
                                            <x-button type="button" variant="secondary" size="sm" data-post-colors-reset>Reset</x-button>
                                        </div>
                                    </fieldset>

                                    <div>
                                        <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-muted" for="post-{{ $post['key'] }}-art">Image &amp; graphics</label>
                                        <select id="post-{{ $post['key'] }}-art" class="cb-input" data-post-art>
                                            <option value="auto">Artwork: picked for this post</option>
                                            <option value="none">No artwork</option>
                                            @php($artCount = max(1, (int) collect($kit['site']['art'] ?? [])->groupBy('kind')->map->count()->max()))
                                            @for ($i = 0; $i < $artCount; $i++)
                                                <option value="{{ $i }}">Artwork set {{ $i + 1 }}</option>
                                            @endfor
                                        </select>
                                        <p class="cb-hint">Add, upload or untick pieces under “From the WordCamp site”.</p>
                                    </div>

                                    <div>
                                        <span class="mb-2 block text-xs font-semibold uppercase tracking-wide text-muted">Effects <span class="font-normal normal-case">(Daylight)</span></span>
                                        <div class="flex flex-wrap gap-x-4 gap-y-2 text-sm">
                                            <label class="flex items-center gap-2"><input type="checkbox" class="cb-check" data-post-effect="glow" checked> Glow</label>
                                            <label class="flex items-center gap-2"><input type="checkbox" class="cb-check" data-post-effect="dots" checked> Dots</label>
                                            <label class="flex items-center gap-2"><input type="checkbox" class="cb-check" data-post-effect="waves" checked> Waves</label>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-2 border-t border-line pt-4">
                                        <x-button type="button" size="sm" icon="download" data-action="download">Download</x-button>
                                        <x-button type="button" variant="secondary" size="sm" data-action="copy">Copy caption</x-button>
                                        <x-button type="button" variant="secondary" size="sm" data-action="share">Share</x-button>
                                        @if ($publishUrl)
                                            <x-button type="button" variant="secondary" size="sm" data-action="publish">Publish</x-button>
                                        @endif
                                        <x-button type="button" variant="secondary" size="sm" data-post-reset>Undo my edits</x-button>
                                    </div>
                                    <p class="text-xs text-muted" data-share-links></p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- People cards --}}
            <div class="min-h-0 flex-1" data-studio-panel="people" data-social-people hidden>
                <div class="grid h-full lg:grid-cols-[15rem_minmax(0,1fr)]">
                    <aside class="flex min-h-0 flex-col border-b border-line bg-white lg:border-b-0 lg:border-r">
                        <div class="space-y-2 border-b border-line p-3">
                            <span class="cb-label" id="social-people-design-label">Design</span>
                            <div class="flex flex-wrap gap-1" role="group" aria-labelledby="social-people-design-label">
                                @foreach (['daylight' => 'Daylight', 'classic' => 'Classic', 'polaroid' => 'Polaroid', 'badge' => 'Badge', 'split' => 'Split', 'minimal' => 'Minimal', 'spotlight' => 'Spotlight', 'arch' => 'Arch'] as $key => $label)
                                    <button type="button" class="cb-btn cb-btn-sm {{ $loop->first ? 'cb-btn-primary' : 'cb-btn-secondary' }}" data-people-design="{{ $key }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}">{{ $label }}</button>
                                @endforeach
                            </div>
                            <label class="sr-only" for="people-role">Who</label>
                            <select id="people-role" class="cb-input py-1.5 text-sm" data-people-role>
                                <option value="">Everyone ({{ $peopleCount }})</option>
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
                            <label class="sr-only" for="people-search">Find a person</label>
                            <input id="people-search" type="search" placeholder="Find a person…" class="cb-input py-1.5 text-sm" data-people-search>
                            <x-button type="button" size="sm" icon="download" class="w-full" data-people-zip>Download these (ZIP)</x-button>
                            <p class="text-xs text-muted" data-people-status aria-live="polite"></p>
                        </div>
                        <ol class="flex min-h-0 flex-1 gap-1 overflow-auto p-2 lg:flex-col" data-people-rail aria-label="People"></ol>
                    </aside>
                    <div class="min-h-0" data-people-grid>
                        @if ($kit['people'] === [])
                            <p class="p-8 text-center text-sm text-muted">Nobody yet. Mark speakers, organizers, volunteers… on the attendee list first.</p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Reels (resources/js/social-reels.js) --}}
            <div class="min-h-0 flex-1" data-studio-panel="reels" data-social-reels hidden>
                <div class="grid h-full lg:grid-cols-[15rem_minmax(0,1fr)]">
                    <aside class="flex min-h-0 flex-col border-b border-line bg-white lg:border-b-0 lg:border-r">
                        <div class="space-y-2 border-b border-line p-3 text-sm">
                            <label class="flex flex-col gap-1">
                                <span class="cb-label">Music</span>
                                <select class="cb-input py-1.5 text-sm" data-reel-music>
                                    <option value="auto">CampBuddy's music (own per reel)</option>
                                    <option value="file">My own track</option>
                                    @if (($studioTracks ?? []) !== [])
                                        <option value="library">WPSimplified Studio music</option>
                                    @endif
                                    <option value="none">No music</option>
                                </select>
                            </label>
                            <label class="flex flex-col gap-1 hidden" data-reel-file-row>
                                <span class="cb-label">Track (MP3, WAV, M4A)</span>
                                <input type="file" accept="audio/*" class="text-xs" data-reel-file>
                            </label>
                            {{-- The music library CampBuddy shares with WPSimplified (App\Support\StudioMusic). --}}
                            @if (($studioTracks ?? []) !== [])
                                <label class="flex flex-col gap-1 hidden" data-reel-track-row>
                                    <span class="cb-label">Track</span>
                                    <select class="cb-input py-1.5 text-sm" data-reel-track>
                                        @foreach (['reel' => 'Reel cuts (about 30 s)', 'loop' => 'Loops (repeat smoothly)', 'full' => 'Full tracks (5 min)'] as $kind => $group)
                                            @php($inGroup = array_filter($studioTracks, fn ($t) => $t['kind'] === $kind))
                                            @if ($inGroup !== [])
                                                <optgroup label="{{ $group }}">
                                                    @foreach ($inGroup as $track)
                                                        <option value="{{ $musicUrl.'?f='.urlencode($track['file']) }}">{{ $track['title'] }} · {{ $track['seconds'] }} s</option>
                                                    @endforeach
                                                </optgroup>
                                            @endif
                                        @endforeach
                                    </select>
                                </label>
                            @endif
                            <label class="flex flex-col gap-1">
                                <span class="cb-label">Music volume</span>
                                <input type="range" min="0" max="100" value="85" data-reel-volume>
                            </label>
                            <div class="flex flex-wrap gap-2">
                                <x-button type="button" size="sm" icon="download" data-reel-all>Make all (ZIP)</x-button>
                                <x-button type="button" variant="secondary" size="sm" data-reel-stop>Stop</x-button>
                            </div>
                            <p class="text-xs text-muted" data-reel-status aria-live="polite"></p>
                        </div>
                        <ol class="flex min-h-0 flex-1 gap-1 overflow-auto p-2 lg:flex-col" data-reel-rail aria-label="Reels"></ol>
                    </aside>
                    <ul class="min-h-0" data-reel-list></ul>
                </div>
            </div>
        </div>
    </dialog>

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
