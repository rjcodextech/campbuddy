@php
    $labels = ['overview' => 'Overview', 'navigation' => 'Navigation', 'home' => 'Home', 'text' => 'Text & languages', 'look' => 'Look', 'banner' => 'Banner', 'switches' => 'Switches', 'history' => 'History'];
    $strings = $config['strings'];
    $defaultsEn = \App\Support\MobileAppDefaults::strings('en');
    $input = 'block w-full rounded-md border-line text-sm shadow-sm focus:border-maroon focus:ring-maroon';
@endphp

<x-app-layout title="Mobile app" subtitle="Everything the CampBuddy app shows, without a store release"
              :breadcrumbs="[['Mobile app']]">
    <div class="max-w-5xl space-y-6">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif
        @if (! empty($errors_list))
            <x-alert variant="danger">
                <p class="font-medium">Not saved. Please fix:</p>
                <ul class="mt-1 list-disc pl-5 text-sm">
                    @foreach ($errors_list as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </x-alert>
        @endif

        <nav aria-label="Mobile app sections" class="overflow-x-auto border-b border-line">
            <ul class="flex min-w-max items-center gap-1">
                @foreach ($tabs as $key)
                    <li>
                        <a href="{{ route('admin.mobile.index', ['tab' => $key]) }}" @if ($tab === $key) aria-current="page" @endif
                           @class(['-mb-px inline-flex items-center border-b-2 px-3 py-3 text-sm font-medium', 'border-maroon text-maroon' => $tab === $key, 'border-transparent text-muted hover:border-line hover:text-ink' => $tab !== $key])>{{ $labels[$key] }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>

        {{-- Overview --}}
        @if ($tab === 'overview')
            <x-card title="Where things stand">
                <dl class="grid gap-4 text-sm sm:grid-cols-3">
                    <div><dt class="text-muted">Live version</dt><dd class="text-lg font-semibold">{{ $liveVersion ?: 'Built-in defaults' }}</dd></div>
                    <div><dt class="text-muted">Draft</dt><dd class="text-lg font-semibold">{{ $hasDraft ? 'Waiting to be published' : 'None' }}</dd></div>
                    <div><dt class="text-muted">API</dt><dd class="break-all text-sm">{{ url('/api/v1/app/config') }}</dd></div>
                </dl>
                <p class="mt-4 text-sm text-muted">Tabs, the Home layout, every text (English and Hindi), colours, the banner and the on/off switches are changed here. New screens or buttons that do not exist yet need an app update. Edits go to a draft first; phones see them only after you publish.</p>
            </x-card>

            @if ($hasDraft)
                <x-card title="Publish the draft">
                    <form method="POST" action="{{ route('admin.mobile.publish') }}" class="flex flex-wrap items-end gap-3">
                        @csrf
                        <div class="min-w-[16rem] flex-1">
                            <label for="note" class="mb-1 block text-sm font-medium">Note (what changed)</label>
                            <input id="note" name="note" maxlength="200" class="{{ $input }}" placeholder="e.g. Delhi banner, Hindi texts">
                        </div>
                        <x-button>Publish</x-button>
                    </form>
                    <form method="POST" action="{{ route('admin.mobile.discard') }}" class="mt-3" onsubmit="return confirm('Throw the draft away?')">
                        @csrf
                        <x-button variant="secondary" size="sm">Discard the draft</x-button>
                    </form>
                </x-card>
            @endif
        @endif

        {{-- Navigation --}}
        @if ($tab === 'navigation')
            <form method="POST" action="{{ route('admin.mobile.save', 'navigation') }}" class="space-y-6">
                @csrf
                @foreach (['tabs' => ['Attendee tabs (bottom bar inside an event)', $config['tabs'], 'tabs'], 'organizer' => ['Organizer tabs (Admin and Event manager)', $config['organizer']['tabs'], 'organizer_tabs']] as [$title, $list, $field])
                    <x-card :title="$title" description="Switch tabs on or off, set their order (1 is first) and pick their icon. The tab names are on the Text tab.">
                        <div class="divide-y divide-line">
                            @foreach ($list as $tabRow)
                                <div class="grid items-center gap-3 py-3 sm:grid-cols-[1fr_6rem_12rem_6rem]">
                                    <div><p class="font-medium">{{ $strings['en'][$tabRow['title']] ?? $tabRow['key'] }}</p><p class="text-xs text-muted">{{ $tabRow['key'] }}</p></div>
                                    <label class="text-sm">Order <input type="number" min="1" max="20" name="{{ $field }}[{{ $tabRow['key'] }}][order]" value="{{ $tabRow['order'] }}" class="{{ $input }}"></label>
                                    <label class="text-sm">Icon
                                        <select name="{{ $field }}[{{ $tabRow['key'] }}][icon]" class="{{ $input }}">
                                            @foreach ($icons as $icon)
                                                <option value="{{ $icon }}" @selected($tabRow['icon'] === $icon)>{{ $icon }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="{{ $field }}[{{ $tabRow['key'] }}][enabled]" value="1" @checked($tabRow['enabled'])> On</label>
                                </div>
                            @endforeach
                        </div>
                    </x-card>
                @endforeach
                <x-button>Save to draft</x-button>
            </form>
        @endif

        {{-- Home --}}
        @if ($tab === 'home')
            <form method="POST" action="{{ route('admin.mobile.save', 'home') }}" class="space-y-6">
                @csrf
                <x-card title="Event Home blocks" description="Order, on or off, and when each block shows. What is happening and what is next matter most on the day, so keep them near the top.">
                    <div class="divide-y divide-line">
                        @foreach ($config['home']['sections'] as $i => $section)
                            <div class="grid items-center gap-3 py-3 sm:grid-cols-[1fr_6rem_14rem_6rem]">
                                <div><p class="font-medium">{{ $section['title'] ? ($strings['en'][$section['title']] ?? $section['type']) : ucfirst(str_replace('_', ' ', $section['type'])) }}</p><p class="text-xs text-muted">{{ $section['type'] }}</p></div>
                                <label class="text-sm">Order <input type="number" min="1" max="50" name="sections[{{ $i }}][order]" value="{{ $section['order'] }}" class="{{ $input }}"></label>
                                <label class="text-sm">Show when
                                    <select name="sections[{{ $i }}][visible_if]" class="{{ $input }}">
                                        <option value="">Always</option>
                                        @foreach ($conditions as $condition)
                                            <option value="{{ $condition }}" @selected(($section['visible_if'] ?? null) === $condition)>{{ str_replace(['_', ':'], [' ', ' = '], $condition) }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="sections[{{ $i }}][enabled]" value="1" @checked($section['enabled'])> On</label>
                            </div>
                        @endforeach
                    </div>
                </x-card>
                <x-button>Save to draft</x-button>
            </form>
        @endif

        {{-- Text --}}
        @if ($tab === 'text')
            <form method="POST" action="{{ route('admin.mobile.save', 'text') }}" class="space-y-6">
                @csrf
                <x-card title="Texts" description="Every sentence and label in the app. Plain text only. English is required; if a Hindi text is empty the app uses the English one. You can change these, not add new ones.">
                    <div class="divide-y divide-line">
                        @foreach ($defaultsEn as $key => $default)
                            <div class="grid gap-3 py-3 lg:grid-cols-2">
                                <div>
                                    <label class="block text-sm font-medium" for="en-{{ $key }}">{{ $key }} <span class="text-muted">· English</span></label>
                                    <textarea id="en-{{ $key }}" name="strings[en][{{ $key }}]" rows="2" maxlength="300" class="{{ $input }}">{{ $strings['en'][$key] ?? $default }}</textarea>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium" for="hi-{{ $key }}">हिन्दी</label>
                                    <textarea id="hi-{{ $key }}" name="strings[hi][{{ $key }}]" rows="2" maxlength="300" class="{{ $input }}">{{ $strings['hi'][$key] ?? '' }}</textarea>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-card>
                <x-button>Save to draft</x-button>
            </form>
        @endif

        {{-- Look --}}
        @if ($tab === 'look')
            <form method="POST" action="{{ route('admin.mobile.save', 'look') }}" class="space-y-6">
                @csrf
                <x-card title="Colours and logo" description="Text must stay readable: the editor checks contrast and refuses colours that are too faint.">
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach (['primary' => 'Main colour (buttons)', 'accent' => 'Accent', 'background' => 'Background', 'ink' => 'Text', 'muted' => 'Quiet text'] as $token => $label)
                            <label class="text-sm font-medium">{{ $label }}
                                <span class="mt-1 flex items-center gap-2">
                                    <input type="color" value="{{ $config['theme'][$token] }}" oninput="this.nextElementSibling.value = this.value.toUpperCase()" class="h-9 w-12 rounded border-line">
                                    <input name="{{ $token }}" value="{{ old($token, $config['theme'][$token]) }}" pattern="#[0-9A-Fa-f]{6}" maxlength="7" class="{{ $input }}">
                                </span>
                            </label>
                        @endforeach
                        <label class="text-sm font-medium">Corner roundness (0 to 28)
                            <input type="number" min="0" max="28" name="radius" value="{{ old('radius', $config['theme']['radius']) }}" class="mt-1 {{ $input }}">
                        </label>
                        <label class="text-sm font-medium sm:col-span-2">Logo (https image address, optional)
                            <input name="logo" value="{{ old('logo', $config['theme']['logo']) }}" class="mt-1 {{ $input }}" placeholder="https://campbuddy.club/media/logo.png">
                        </label>
                        <label class="text-sm font-medium">Splash line
                            <input name="splash_text" maxlength="60" value="{{ old('splash_text', $config['theme']['splash_text']) }}" class="mt-1 {{ $input }}">
                        </label>
                    </div>
                    <div class="mt-5 rounded-lg border border-line p-4" style="background: {{ $config['theme']['background'] }}; color: {{ $config['theme']['ink'] }}">
                        <p class="text-sm font-semibold">Preview of the current draft</p>
                        <p class="text-sm" style="color: {{ $config['theme']['muted'] }}">Quiet text looks like this.</p>
                        <span class="mt-2 inline-block px-4 py-2 text-sm font-semibold text-white" style="background: {{ $config['theme']['primary'] }}; border-radius: {{ $config['theme']['radius'] }}px">Button</span>
                        <span class="ml-2 inline-block px-4 py-2 text-sm font-semibold" style="border: 2px solid {{ $config['theme']['accent'] }}; border-radius: {{ $config['theme']['radius'] }}px">Accent</span>
                    </div>
                </x-card>
                <x-button>Save to draft</x-button>
            </form>
        @endif

        {{-- Banner --}}
        @if ($tab === 'banner')
            @php($banner = $config['banner'])
            <form method="POST" action="{{ route('admin.mobile.save', 'banner') }}" class="space-y-6">
                @csrf
                <x-card title="Announcement banner" description="One banner at the top of the app. It disappears by itself after the end time.">
                    <label class="flex items-center gap-2 text-sm font-medium"><input type="checkbox" name="banner_on" value="1" @checked($banner)> Show a banner</label>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <label class="text-sm font-medium sm:col-span-2">English text <input name="text_en" maxlength="200" value="{{ old('text_en', $banner['text']['en'] ?? '') }}" class="mt-1 {{ $input }}"></label>
                        <label class="text-sm font-medium sm:col-span-2">हिन्दी <input name="text_hi" maxlength="200" value="{{ old('text_hi', $banner['text']['hi'] ?? '') }}" class="mt-1 {{ $input }}"></label>
                        <label class="text-sm font-medium">Style
                            <select name="style" class="mt-1 {{ $input }}">
                                @foreach (['info', 'warning', 'success'] as $style)
                                    <option value="{{ $style }}" @selected(($banner['style'] ?? 'info') === $style)>{{ ucfirst($style) }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="text-sm font-medium">Ends (optional)
                            <input type="datetime-local" name="until" value="{{ old('until', ! empty($banner['until']) ? \Illuminate\Support\Carbon::parse($banner['until'])->format('Y-m-d\TH:i') : '') }}" class="mt-1 {{ $input }}">
                        </label>
                        <label class="text-sm font-medium">Button does
                            <select name="action_type" class="mt-1 {{ $input }}">
                                <option value="">Nothing</option>
                                <option value="event" @selected(($banner['action']['type'] ?? '') === 'event')>Open a WordCamp</option>
                                <option value="url" @selected(($banner['action']['type'] ?? '') === 'url')>Open a web address</option>
                            </select>
                        </label>
                        <label class="text-sm font-medium">WordCamp address name (slug) or https link
                            <input name="action_slug" value="{{ old('action_slug', $banner['action']['slug'] ?? '') }}" class="mt-1 {{ $input }}" placeholder="wordcamp-delhi-2026">
                        </label>
                        <label class="text-sm font-medium sm:col-span-2">Web address (when the button opens a link)
                            <input name="action_url" value="{{ old('action_url', $banner['action']['url'] ?? '') }}" class="mt-1 {{ $input }}" placeholder="https://…">
                        </label>
                    </div>
                </x-card>
                <x-button>Save to draft</x-button>
            </form>
        @endif

        {{-- Switches --}}
        @if ($tab === 'switches')
            <form method="POST" action="{{ route('admin.mobile.save', 'switches') }}" class="space-y-6">
                @csrf
                <x-card title="Features on or off">
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach (['native_push' => 'Push messages', 'local_reminders' => 'Session reminders', 'discovery' => 'Meet people (discovery)', 'organizer_login' => 'Organizer sign-in', 'report_block' => 'Report and block'] as $flag => $label)
                            <label class="flex items-center gap-2 text-sm font-medium"><input type="checkbox" name="flags[{{ $flag }}]" value="1" @checked($config['flags'][$flag] ?? false)> {{ $label }}</label>
                        @endforeach
                    </div>
                </x-card>
                <x-card title="Oldest app allowed" description="Apps older than this show a 'please update' screen. Use it only for a real emergency.">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="text-sm font-medium">Android <input name="min_app[android]" value="{{ old('min_app.android', $config['min_app']['android']) }}" class="mt-1 {{ $input }}"></label>
                        <label class="text-sm font-medium">iPhone <input name="min_app[ios]" value="{{ old('min_app.ios', $config['min_app']['ios']) }}" class="mt-1 {{ $input }}"></label>
                    </div>
                </x-card>
                <x-button>Save to draft</x-button>
            </form>
        @endif

        {{-- History --}}
        @if ($tab === 'history')
            <x-card title="Published versions" description="Restoring an old version publishes it again as a new version.">
                @forelse ($history as $row)
                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line py-3 first:border-t-0">
                        <div>
                            <p class="font-medium">Version {{ $row['version'] }} @if ($row['status'] === 'published')<x-badge variant="success">Live</x-badge>@endif</p>
                            <p class="text-sm text-muted">{{ $row['published_at'] ? \Illuminate\Support\Carbon::parse($row['published_at'])->format('j M Y, g:ia') : '' }} @if ($row['by']) · {{ $row['by'] }} @endif @if ($row['note']) · {{ $row['note'] }} @endif</p>
                        </div>
                        @if ($row['status'] !== 'published')
                            <form method="POST" action="{{ route('admin.mobile.rollback', $row['version']) }}" onsubmit="return confirm('Make version {{ $row['version'] }} live again?')">
                                @csrf
                                <x-button variant="secondary" size="sm">Restore</x-button>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-muted">Nothing has been published yet. Phones use the built-in defaults.</p>
                @endforelse
            </x-card>
        @endif
    </div>
</x-app-layout>
