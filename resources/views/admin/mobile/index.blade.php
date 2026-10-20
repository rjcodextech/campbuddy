@php
    $labels = ['overview' => 'Overview', 'navigation' => 'Navigation', 'home' => 'Home', 'text' => 'Text & languages', 'look' => 'Look', 'banner' => 'Banner', 'switches' => 'Switches', 'history' => 'History'];
    $strings = $config['strings'];
    $defaultsEn = \App\Support\MobileAppDefaults::strings('en');
    $input = 'block w-full rounded-md border-line text-sm shadow-sm focus:border-maroon focus:ring-maroon';
@endphp

<x-app-layout title="Mobile app" subtitle="Everything the CampBuddy app shows, without a store release"
              :breadcrumbs="[['Mobile app']]">
    <div class="max-w-6xl space-y-6">
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
                        @foreach (['primary' => 'Main colour (buttons)', 'accent' => 'Accent (stars, highlights)', 'background' => 'Page background', 'surface' => 'Cards', 'peach' => 'Soft highlight', 'line' => 'Borders', 'ink' => 'Text', 'muted' => 'Quiet text', 'danger' => 'Warnings', 'success' => 'Success'] as $token => $label)
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
            @php
                $banner = $config['banner'];
                $allEvents = \App\Models\Event::orderBy('starts_on', 'desc')->get(['slug', 'display_name']);
                $initialStyle = old('style', $banner['style'] ?? 'info');
                $initialActionType = old('action_type', $banner['action']['type'] ?? '');
                $initialSlug = old('action_slug', $banner['action']['slug'] ?? '');
                $initialUrl = old('action_url', $banner['action']['url'] ?? '');
                $initialTextEn = old('text_en', $banner['text']['en'] ?? '');
                $initialTextHi = old('text_hi', $banner['text']['hi'] ?? '');
                $initialUntil = old('until', ! empty($banner['until']) ? \Illuminate\Support\Carbon::parse($banner['until'])->format('Y-m-d\TH:i') : '');
                $bannerOn = (bool) old('banner_on', $banner ? true : false);
            @endphp

            <form method="POST" action="{{ route('admin.mobile.save', 'banner') }}" id="banner-form" class="space-y-6">
                @csrf

                <div class="grid gap-6 lg:grid-cols-12 items-start">
                    {{-- Left column: Form Controls --}}
                    <div class="lg:col-span-7 space-y-5">

                        {{-- 1. Status Hero Card --}}
                        <div class="rounded-xl border border-line bg-surface p-5 shadow-xs">
                            <div class="flex items-center justify-between gap-4">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2.5">
                                        <h3 class="font-semibold text-ink text-base">Announcement banner status</h3>
                                        <span id="preview-status-pill" class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $bannerOn ? 'bg-[#DCFCE7] text-[#15803D]' : 'bg-line/40 text-muted' }}">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $bannerOn ? 'bg-[#16A34A] animate-pulse' : 'bg-muted' }}"></span>
                                            <span id="preview-status-text">{{ $bannerOn ? 'Active on App' : 'Disabled / Hidden' }}</span>
                                        </span>
                                    </div>
                                    <p class="text-xs text-muted">Displays a high-visibility announcement card at the top of the app across attendee screens.</p>
                                </div>
                                <label class="relative inline-flex cursor-pointer items-center">
                                    <input type="checkbox" name="banner_on" id="banner_on" value="1" @checked($bannerOn) class="peer sr-only">
                                    <div class="h-7 w-12 rounded-full bg-line peer-checked:bg-maroon peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-maroon/30 after:absolute after:left-[3px] after:top-[3px] after:h-5.5 after:w-5.5 after:rounded-full after:border after:border-line after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-5 peer-checked:after:border-white"></div>
                                </label>
                            </div>
                        </div>

                        {{-- 2. Bilingual Content Card --}}
                        <x-card title="Announcement text" description="Provide your message in English and Hindi. Live preview updates immediately.">
                            <div class="space-y-4">
                                {{-- English Copy --}}
                                <div>
                                    <div class="flex items-center justify-between text-xs mb-1.5">
                                        <label for="text_en" class="font-semibold text-ink">English text <span class="text-danger">*</span></label>
                                        <span id="counter_en" class="text-muted font-mono">0 / 200</span>
                                    </div>
                                    <textarea name="text_en" id="text_en" rows="2" maxlength="200" class="{{ $input }}" placeholder="e.g. WordCamp Rajasthan 2026 is live! Check today's schedule and explore sessions.">{{ $initialTextEn }}</textarea>

                                    {{-- Quick presets --}}
                                    <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                        <span class="text-[11px] text-muted font-medium mr-1">Presets:</span>
                                        <button type="button" class="preset-btn text-[11px] bg-peach/50 hover:bg-peach text-ink font-medium px-2 py-0.5 rounded border border-line cursor-pointer" data-for="en" data-text="WordCamp Rajasthan 2026 is live! Check schedule and sessions.">Live now</button>
                                        <button type="button" class="preset-btn text-[11px] bg-peach/50 hover:bg-peach text-ink font-medium px-2 py-0.5 rounded border border-line cursor-pointer" data-for="en" data-text="Keynote session starts in 15 minutes in Main Auditorium.">Keynote starting</button>
                                        <button type="button" class="preset-btn text-[11px] bg-peach/50 hover:bg-peach text-ink font-medium px-2 py-0.5 rounded border border-line cursor-pointer" data-for="en" data-text="Lunch & community networking is now open in Hall B.">Lunch served</button>
                                        <button type="button" class="preset-btn text-[11px] bg-peach/50 hover:bg-peach text-ink font-medium px-2 py-0.5 rounded border border-line cursor-pointer" data-for="en" data-text="Official WordCamp photo album has been published! Find yourself.">Photos published</button>
                                    </div>
                                </div>

                                {{-- Hindi Copy --}}
                                <div>
                                    <div class="flex items-center justify-between text-xs mb-1.5">
                                        <label for="text_hi" class="font-semibold text-ink">हिन्दी (Hindi text)</label>
                                        <span id="counter_hi" class="text-muted font-mono">0 / 200</span>
                                    </div>
                                    <textarea name="text_hi" id="text_hi" rows="2" maxlength="200" class="{{ $input }}" placeholder="उदा. WordCamp Rajasthan 2026 शुरू हो चुका है! आज का शेड्यूल देखें।">{{ $initialTextHi }}</textarea>

                                    {{-- Hindi presets --}}
                                    <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                        <span class="text-[11px] text-muted font-medium mr-1">सुझाव:</span>
                                        <button type="button" class="preset-btn text-[11px] bg-peach/50 hover:bg-peach text-ink font-medium px-2 py-0.5 rounded border border-line cursor-pointer" data-for="hi" data-text="WordCamp Rajasthan शुरू हो चुका है! आज का शेड्यूल देखें।">शुरू हुआ</button>
                                        <button type="button" class="preset-btn text-[11px] bg-peach/50 hover:bg-peach text-ink font-medium px-2 py-0.5 rounded border border-line cursor-pointer" data-for="hi" data-text="मुख्य सभागार में कीनोट सत्र 15 मिनट में शुरू हो रहा है।">कीनोट</button>
                                        <button type="button" class="preset-btn text-[11px] bg-peach/50 hover:bg-peach text-ink font-medium px-2 py-0.5 rounded border border-line cursor-pointer" data-for="hi" data-text="हॉल बी में लंच और नेटवर्किंग शुरू हो चुकी है।">लंच</button>
                                        <button type="button" class="preset-btn text-[11px] bg-peach/50 hover:bg-peach text-ink font-medium px-2 py-0.5 rounded border border-line cursor-pointer" data-for="hi" data-text="आधिकारिक फ़ोटो एल्बम प्रकाशित हो चुका है! अपनी फ़ोटो देखें।">फ़ोटो</button>
                                    </div>
                                </div>
                            </div>
                        </x-card>

                        {{-- 3. Visual Style Selector Card --}}
                        <x-card title="Visual style & tone" description="Select the mood and color palette for this announcement.">
                            {{-- Synchronized select field for backend form submission and test compatibility --}}
                            <select name="style" id="style_select" class="sr-only">
                                <option value="info" @selected($initialStyle === 'info')>Info</option>
                                <option value="warning" @selected($initialStyle === 'warning')>Warning</option>
                                <option value="success" @selected($initialStyle === 'success')>Success</option>
                            </select>

                            <div class="grid gap-3 sm:grid-cols-3" id="style-cards-group">
                                {{-- Info --}}
                                <div class="style-card relative flex flex-col justify-between rounded-xl border p-3.5 cursor-pointer transition-all {{ $initialStyle === 'info' ? 'border-primary ring-2 ring-primary/20 bg-[#FFF5EE]' : 'border-line hover:border-muted bg-white' }}" data-style="info">
                                    <div class="flex items-center gap-2.5">
                                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-primary">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" /></svg>
                                        </span>
                                        <div>
                                            <p class="font-semibold text-sm text-ink">Info / General</p>
                                            <p class="text-[11px] text-muted">Warm terracotta</p>
                                        </div>
                                    </div>
                                    <p class="mt-2 text-xs text-muted">Schedules, general notices & updates.</p>
                                </div>

                                {{-- Warning --}}
                                <div class="style-card relative flex flex-col justify-between rounded-xl border p-3.5 cursor-pointer transition-all {{ $initialStyle === 'warning' ? 'border-amber-500 ring-2 ring-amber-500/20 bg-[#FFFBEB]' : 'border-line hover:border-muted bg-white' }}" data-style="warning">
                                    <div class="flex items-center gap-2.5">
                                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-amber-100 text-amber-700">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        </span>
                                        <div>
                                            <p class="font-semibold text-sm text-ink">Urgent Alert</p>
                                            <p class="text-[11px] text-muted">Golden amber</p>
                                        </div>
                                    </div>
                                    <p class="mt-2 text-xs text-muted">Delays, hall changes & urgent alerts.</p>
                                </div>

                                {{-- Success --}}
                                <div class="style-card relative flex flex-col justify-between rounded-xl border p-3.5 cursor-pointer transition-all {{ $initialStyle === 'success' ? 'border-emerald-500 ring-2 ring-emerald-500/20 bg-[#F0FDF4]' : 'border-line hover:border-muted bg-white' }}" data-style="success">
                                    <div class="flex items-center gap-2.5">
                                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" /></svg>
                                        </span>
                                        <div>
                                            <p class="font-semibold text-sm text-ink">Celebration</p>
                                            <p class="text-[11px] text-muted">Emerald mint</p>
                                        </div>
                                    </div>
                                    <p class="mt-2 text-xs text-muted">Highlights, photo drops & wrap-ups.</p>
                                </div>
                            </div>
                        </x-card>

                        {{-- 4. Button Action (Call to Action) --}}
                        <x-card title="Interactive button (action)" description="Attach a tap action to navigate attendees to an event or website.">
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-xs font-semibold text-ink mb-1.5">Action behavior</label>
                                    <div class="grid grid-cols-3 gap-2">
                                        <div class="action-type-pill flex items-center justify-center p-2.5 rounded-lg border text-xs cursor-pointer transition-all {{ $initialActionType === '' ? 'border-maroon bg-peach/40 text-maroon font-semibold' : 'border-line bg-white text-muted hover:border-muted font-medium' }}" data-type="">
                                            <span>None (Read-only)</span>
                                        </div>
                                        <div class="action-type-pill flex items-center justify-center p-2.5 rounded-lg border text-xs cursor-pointer transition-all {{ $initialActionType === 'event' ? 'border-maroon bg-peach/40 text-maroon font-semibold' : 'border-line bg-white text-muted hover:border-muted font-medium' }}" data-type="event">
                                            <span>Open a WordCamp</span>
                                        </div>
                                        <div class="action-type-pill flex items-center justify-center p-2.5 rounded-lg border text-xs cursor-pointer transition-all {{ $initialActionType === 'url' ? 'border-maroon bg-peach/40 text-maroon font-semibold' : 'border-line bg-white text-muted hover:border-muted font-medium' }}" data-type="url">
                                            <span>Open web link</span>
                                        </div>
                                    </div>
                                    <select name="action_type" id="action_type" class="sr-only">
                                        <option value="" @selected($initialActionType === '')>Nothing</option>
                                        <option value="event" @selected($initialActionType === 'event')>Open a WordCamp</option>
                                        <option value="url" @selected($initialActionType === 'url')>Open a web address</option>
                                    </select>
                                </div>

                                {{-- Event Destination Details --}}
                                <div id="action_event_panel" class="space-y-3 p-3.5 rounded-lg border border-line bg-peach/15 {{ $initialActionType === 'event' ? '' : 'hidden' }}">
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <div>
                                            <label class="block text-xs font-semibold text-ink mb-1">Choose WordCamp</label>
                                            <select id="event_selector" class="{{ $input }}">
                                                <option value="">-- Select from existing events --</option>
                                                @foreach ($allEvents as $evt)
                                                    <option value="{{ $evt->slug }}" @selected($initialSlug === $evt->slug)>{{ $evt->display_name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-ink mb-1">Or enter event slug</label>
                                            <input name="action_slug" id="action_slug" value="{{ $initialSlug }}" class="{{ $input }}" placeholder="e.g. wordcamp-delhi-2026">
                                        </div>
                                    </div>
                                </div>

                                {{-- URL Destination Details --}}
                                <div id="action_url_panel" class="space-y-2 p-3.5 rounded-lg border border-line bg-peach/15 {{ $initialActionType === 'url' ? '' : 'hidden' }}">
                                    <label class="block text-xs font-semibold text-ink mb-1">Web address (must be https://)</label>
                                    <div class="flex items-center gap-2">
                                        <input name="action_url" id="action_url" value="{{ $initialUrl }}" class="{{ $input }}" placeholder="https://photos.app.goo.gl/...">
                                    </div>
                                </div>
                            </div>
                        </x-card>

                        {{-- 5. Expiration Schedule Card --}}
                        <x-card title="Auto-expiration schedule" description="Optional. The banner disappears on its own once this time arrives.">
                            <div class="space-y-3">
                                <div class="max-w-xs">
                                    <input type="datetime-local" name="until" id="until_input" value="{{ $initialUntil }}" class="{{ $input }}">
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-xs text-muted font-medium">Quick set:</span>
                                    <button type="button" class="until-preset text-xs px-2.5 py-1 rounded border border-line bg-white hover:bg-peach/30 font-medium text-ink cursor-pointer" data-hours="2">+2 Hours</button>
                                    <button type="button" class="until-preset text-xs px-2.5 py-1 rounded border border-line bg-white hover:bg-peach/30 font-medium text-ink cursor-pointer" data-hours="24">+1 Day</button>
                                    <button type="button" class="until-preset text-xs px-2.5 py-1 rounded border border-line bg-white hover:bg-peach/30 font-medium text-ink cursor-pointer" data-hours="48">+2 Days</button>
                                    <button type="button" class="until-clear text-xs px-2.5 py-1 rounded border border-line bg-white hover:bg-danger-soft font-medium text-danger cursor-pointer">Clear</button>
                                </div>
                            </div>
                        </x-card>

                        <div class="flex items-center gap-3 pt-2">
                            <x-button>Save to draft</x-button>
                        </div>
                    </div>

                    {{-- Right column: Interactive Live App Preview --}}
                    <div class="lg:col-span-5 lg:sticky lg:top-6 space-y-4">
                        <div class="rounded-2xl border-2 border-line bg-surface p-4 shadow-md">
                            <div class="flex items-center justify-between pb-3 border-b border-line mb-3">
                                <div>
                                    <h3 class="font-bold text-sm text-ink">Live Mobile Preview</h3>
                                    <p class="text-[11px] text-muted">Preview exactly how attendees see this</p>
                                </div>
                                {{-- Language preview toggle --}}
                                <div class="flex items-center rounded-lg border border-line bg-white p-0.5 text-xs font-semibold">
                                    <button type="button" id="lang-btn-en" class="px-2.5 py-1 rounded-md bg-maroon text-white transition-all cursor-pointer">English</button>
                                    <button type="button" id="lang-btn-hi" class="px-2.5 py-1 rounded-md text-muted hover:text-ink transition-all cursor-pointer">हिन्दी</button>
                                </div>
                            </div>

                            {{-- Device Screen Simulation Frame --}}
                            <div class="relative mx-auto rounded-[28px] border-4 border-ink bg-[#FFF0DF] p-3.5 shadow-inner overflow-hidden max-w-[340px] transition-all">
                                {{-- Phone Notch / Status Bar --}}
                                <div class="flex items-center justify-between px-3 pt-1 pb-2 text-[10px] font-semibold text-ink">
                                    <span>9:41</span>
                                    <div class="h-3 w-16 rounded-full bg-ink/80"></div>
                                    <div class="flex items-center gap-1">
                                        <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M17.778 8.222c-4.296-4.296-11.26-4.296-15.556 0A1 1 0 01.808 6.808c5.076-5.077 13.308-5.077 18.384 0a1 1 0 01-1.414 1.414zM14.95 11.05a7 7 0 00-9.9 0 1 1 0 01-1.414-1.414 9 9 0 0112.728 0 1 1 0 01-1.414 1.414zM12.12 13.88a3 3 0 00-4.242 0 1 1 0 01-1.415-1.415 5 5 0 017.072 0 1 1 0 01-1.415 1.415zM9 16a1 1 0 011-1h.01a1 1 0 110 2H10a1 1 0 01-1-1z" clip-rule="evenodd"/></svg>
                                        <div class="h-2 w-4 rounded-sm border border-current p-0.5"><div class="h-full w-full bg-current rounded-xs"></div></div>
                                    </div>
                                </div>

                                {{-- App Topbar Mockup --}}
                                <div class="flex items-center justify-between px-2 py-1.5 mb-3 border-b border-line/60">
                                    <div class="flex items-center gap-1.5">
                                        <img src="/media/logo-wordmark-sm.png" alt="CampBuddy" class="h-6 w-auto">
                                    </div>
                                    <div class="flex items-center gap-1 text-muted">
                                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-surface border border-line text-xs font-bold">?</span>
                                    </div>
                                </div>

                                {{-- Inactive Overlay when banner_on is false --}}
                                <div id="preview-inactive-overlay" class="absolute inset-0 bg-ink/40 backdrop-blur-[2px] z-20 flex flex-col items-center justify-center p-4 text-center transition-opacity {{ $bannerOn ? 'hidden' : '' }}">
                                    <div class="rounded-xl bg-white p-4 shadow-xl border border-line max-w-[240px]">
                                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-line/30 text-muted mb-2">
                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                                        </span>
                                        <p class="font-bold text-ink text-sm">Banner is Hidden</p>
                                        <p class="text-[11px] text-muted mt-1">Toggle "Show banner" ON to display it across attendee devices.</p>
                                    </div>
                                </div>

                                {{-- Live Rendered Banner Card --}}
                                <div id="preview-banner-card" class="relative rounded-xl border p-3.5 shadow-xs transition-all overflow-hidden bg-[#FFF5EE] border-[#F7D5C1]">
                                    {{-- Accent Stripe --}}
                                    <div id="preview-accent-stripe" class="absolute left-0 top-0 bottom-0 w-1 bg-primary"></div>

                                    <div class="space-y-2.5 pl-1.5">
                                        {{-- Top Row: Icon + Kicker Badge + Close Button (✕) --}}
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-2">
                                                <div id="preview-icon-box" class="flex h-7 w-7 items-center justify-center rounded-full bg-primary/10 text-primary">
                                                    <svg id="preview-icon-svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
                                                    </svg>
                                                </div>
                                                <span id="preview-kicker-badge" class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[10px] font-bold tracking-wider uppercase bg-primary/10 text-primary">
                                                    <span id="preview-kicker-dot" class="h-1.5 w-1.5 rounded-full bg-primary"></span>
                                                    <span id="preview-kicker-text">ANNOUNCEMENT</span>
                                                </span>
                                            </div>
                                            <span id="preview-close-btn" class="flex h-6 w-6 items-center justify-center rounded-full bg-primary/10 text-primary" title="Attendee dismiss option in App & PWA">
                                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </span>
                                        </div>

                                        {{-- Message Body --}}
                                        <p id="preview-text-body" class="text-xs font-medium text-ink leading-relaxed">
                                            {{ $initialTextEn ?: 'No message entered yet. Type above to see live preview.' }}
                                        </p>

                                        {{-- CTA Action Button --}}
                                        <div id="preview-action-row" class="flex justify-end pt-1 {{ $initialActionType === '' ? 'hidden' : '' }}">
                                            <span id="preview-action-btn" class="inline-flex items-center gap-1.5 rounded-full border border-line bg-surface px-3 py-1 text-[11px] font-bold text-primary shadow-2xs">
                                                <span id="preview-action-btn-text">View Event →</span>
                                                <svg id="preview-action-btn-icon" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                {{-- Expiry Tag under banner --}}
                                <div id="preview-expiry-chip" class="mt-2.5 text-center text-[10px] text-muted">
                                    <span id="preview-expiry-text">{{ ! empty($banner['until']) ? 'Auto-expires: '.\Illuminate\Support\Carbon::parse($banner['until'])->format('j M Y, g:ia') : 'No expiration date (always on)' }}</span>
                                </div>

                                {{-- Mock Content below banner --}}
                                <div class="mt-4 rounded-xl border border-line/60 bg-surface/70 p-3 opacity-60">
                                    <div class="h-3 w-24 rounded bg-line mb-2"></div>
                                    <div class="h-5 w-40 rounded bg-line mb-1"></div>
                                    <div class="h-3 w-32 rounded bg-line"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            <script>
            document.addEventListener('DOMContentLoaded', function () {
                const bannerOnCheckbox = document.getElementById('banner_on');
                const previewStatusPill = document.getElementById('preview-status-pill');
                const previewStatusText = document.getElementById('preview-status-text');
                const previewInactiveOverlay = document.getElementById('preview-inactive-overlay');
                const textEnInput = document.getElementById('text_en');
                const textHiInput = document.getElementById('text_hi');
                const counterEn = document.getElementById('counter_en');
                const counterHi = document.getElementById('counter_hi');
                const previewTextBody = document.getElementById('preview-text-body');
                const langBtnEn = document.getElementById('lang-btn-en');
                const langBtnHi = document.getElementById('lang-btn-hi');
                const styleSelect = document.getElementById('style_select');
                const styleCards = document.querySelectorAll('.style-card');
                const previewBannerCard = document.getElementById('preview-banner-card');
                const previewAccentStripe = document.getElementById('preview-accent-stripe');
                const previewIconBox = document.getElementById('preview-icon-box');
                const previewIconSvg = document.getElementById('preview-icon-svg');
                const previewKickerBadge = document.getElementById('preview-kicker-badge');
                const previewKickerDot = document.getElementById('preview-kicker-dot');
                const previewKickerText = document.getElementById('preview-kicker-text');
                const actionTypeSelect = document.getElementById('action_type');
                const actionTypePills = document.querySelectorAll('.action-type-pill');
                const actionEventPanel = document.getElementById('action_event_panel');
                const actionUrlPanel = document.getElementById('action_url_panel');
                const actionSlugInput = document.getElementById('action_slug');
                const eventSelector = document.getElementById('event_selector');
                const actionUrlInput = document.getElementById('action_url');
                const previewActionRow = document.getElementById('preview-action-row');
                const previewActionBtn = document.getElementById('preview-action-btn');
                const previewActionBtnText = document.getElementById('preview-action-btn-text');
                const previewActionBtnIcon = document.getElementById('preview-action-btn-icon');
                const untilInput = document.getElementById('until_input');
                const previewExpiryText = document.getElementById('preview-expiry-text');

                let currentLang = 'en';

                const styleConfigs = {
                    info: {
                        bg: '#FFF5EE',
                        border: '#F7D5C1',
                        accent: '#C33A19',
                        iconTint: '#C33A19',
                        iconBg: 'rgba(195, 58, 25, 0.12)',
                        kickerEn: 'ANNOUNCEMENT',
                        kickerHi: 'घोषणा',
                        iconPath: 'M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z'
                    },
                    warning: {
                        bg: '#FFFBEB',
                        border: '#FDE68A',
                        accent: '#D97706',
                        iconTint: '#B45309',
                        iconBg: '#FEF3C7',
                        kickerEn: 'IMPORTANT NOTICE',
                        kickerHi: 'महत्वपूर्ण सूचना',
                        iconPath: 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'
                    },
                    success: {
                        bg: '#F0FDF4',
                        border: '#BBF7D0',
                        accent: '#16A34A',
                        iconTint: '#15803D',
                        iconBg: '#DCFCE7',
                        kickerEn: 'NEW UPDATE',
                        kickerHi: 'ताज़ा अपडेट',
                        iconPath: 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z'
                    }
                };

                function updatePreview() {
                    if (!previewBannerCard) return;

                    const isOn = bannerOnCheckbox ? bannerOnCheckbox.checked : false;
                    if (previewInactiveOverlay) {
                        previewInactiveOverlay.classList.toggle('hidden', isOn);
                    }
                    if (previewStatusPill) {
                        previewStatusPill.className = 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold ' +
                            (isOn ? 'bg-[#DCFCE7] text-[#15803D]' : 'bg-line/40 text-muted');
                        const dot = previewStatusPill.querySelector('span');
                        if (dot) dot.className = 'h-1.5 w-1.5 rounded-full ' + (isOn ? 'bg-[#16A34A] animate-pulse' : 'bg-muted');
                        if (previewStatusText) previewStatusText.textContent = isOn ? 'Active on App' : 'Disabled / Hidden';
                    }

                    if (counterEn && textEnInput) counterEn.textContent = textEnInput.value.length + ' / 200';
                    if (counterHi && textHiInput) counterHi.textContent = textHiInput.value.length + ' / 200';

                    const textVal = currentLang === 'hi' ?
                        (textHiInput && textHiInput.value.trim() ? textHiInput.value : (textEnInput ? textEnInput.value : '')) :
                        (textEnInput && textEnInput.value.trim() ? textEnInput.value : '');
                    if (previewTextBody) {
                        previewTextBody.textContent = textVal || (currentLang === 'hi' ? 'कोई संदेश दर्ज नहीं किया गया। लाइव देखने के लिए ऊपर टाइप करें।' : 'No message entered yet. Type above to see live preview.');
                    }

                    const currentStyle = styleSelect ? styleSelect.value : 'info';
                    const st = styleConfigs[currentStyle] || styleConfigs.info;

                    previewBannerCard.style.backgroundColor = st.bg;
                    previewBannerCard.style.borderColor = st.border;
                    if (previewAccentStripe) previewAccentStripe.style.backgroundColor = st.accent;

                    if (previewIconBox) {
                        previewIconBox.style.backgroundColor = st.iconBg;
                        previewIconBox.style.color = st.iconTint;
                    }
                    if (previewIconSvg) {
                        previewIconSvg.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="' + st.iconPath + '" />';
                    }
                    if (previewKickerBadge) {
                        previewKickerBadge.style.backgroundColor = st.iconBg;
                        previewKickerBadge.style.color = st.iconTint;
                    }
                    if (previewKickerDot) {
                        previewKickerDot.style.backgroundColor = st.iconTint;
                    }
                    if (previewKickerText) {
                        previewKickerText.textContent = currentLang === 'hi' ? st.kickerHi : st.kickerEn;
                    }
                    const previewCloseBtn = document.getElementById('preview-close-btn');
                    if (previewCloseBtn) {
                        previewCloseBtn.style.backgroundColor = st.iconBg;
                        previewCloseBtn.style.color = st.iconTint;
                    }

                    const actType = actionTypeSelect ? actionTypeSelect.value : '';
                    if (previewActionRow) {
                        if (!actType) {
                            previewActionRow.classList.add('hidden');
                        } else {
                            previewActionRow.classList.remove('hidden');
                            if (previewActionBtn) {
                                previewActionBtn.style.color = st.iconTint;
                                previewActionBtn.style.borderColor = st.border;
                            }
                            if (actType === 'event') {
                                if (previewActionBtnText) {
                                    previewActionBtnText.textContent = currentLang === 'hi' ? 'इवेंट खोलें →' : 'View Event →';
                                }
                                if (previewActionBtnIcon) {
                                    previewActionBtnIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>';
                                }
                            } else if (actType === 'url') {
                                if (previewActionBtnText) {
                                    previewActionBtnText.textContent = currentLang === 'hi' ? 'वेबसाइट खोलें ↗' : 'Visit Link ↗';
                                }
                                if (previewActionBtnIcon) {
                                    previewActionBtnIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>';
                                }
                            }
                        }
                    }

                    if (previewExpiryText && untilInput) {
                        if (untilInput.value) {
                            const d = new Date(untilInput.value);
                            previewExpiryText.textContent = 'Auto-expires: ' + d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) + ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                        } else {
                            previewExpiryText.textContent = 'No expiration date (always on)';
                        }
                    }
                }

                if (bannerOnCheckbox) bannerOnCheckbox.addEventListener('change', updatePreview);
                if (textEnInput) textEnInput.addEventListener('input', updatePreview);
                if (textHiInput) textHiInput.addEventListener('input', updatePreview);
                if (untilInput) untilInput.addEventListener('input', updatePreview);

                if (langBtnEn && langBtnHi) {
                    langBtnEn.addEventListener('click', function () {
                        currentLang = 'en';
                        langBtnEn.className = 'px-2.5 py-1 rounded-md bg-maroon text-white transition-all cursor-pointer';
                        langBtnHi.className = 'px-2.5 py-1 rounded-md text-muted hover:text-ink transition-all cursor-pointer';
                        updatePreview();
                    });
                    langBtnHi.addEventListener('click', function () {
                        currentLang = 'hi';
                        langBtnHi.className = 'px-2.5 py-1 rounded-md bg-maroon text-white transition-all cursor-pointer';
                        langBtnEn.className = 'px-2.5 py-1 rounded-md text-muted hover:text-ink transition-all cursor-pointer';
                        updatePreview();
                    });
                }

                styleCards.forEach(function (card) {
                    card.addEventListener('click', function () {
                        const chosen = card.getAttribute('data-style');
                        if (styleSelect) styleSelect.value = chosen;
                        styleCards.forEach(function (c) {
                            const s = c.getAttribute('data-style');
                            if (s === chosen) {
                                c.className = 'style-card relative flex flex-col justify-between rounded-xl border p-3.5 cursor-pointer transition-all ' +
                                    (chosen === 'info' ? 'border-primary ring-2 ring-primary/20 bg-[#FFF5EE]' : (chosen === 'warning' ? 'border-amber-500 ring-2 ring-amber-500/20 bg-[#FFFBEB]' : 'border-emerald-500 ring-2 ring-emerald-500/20 bg-[#F0FDF4]'));
                            } else {
                                c.className = 'style-card relative flex flex-col justify-between rounded-xl border p-3.5 cursor-pointer transition-all border-line hover:border-muted bg-white';
                            }
                        });
                        updatePreview();
                    });
                });

                actionTypePills.forEach(function (pill) {
                    pill.addEventListener('click', function () {
                        const chosen = pill.getAttribute('data-type');
                        if (actionTypeSelect) actionTypeSelect.value = chosen;
                        actionTypePills.forEach(function (p) {
                            const t = p.getAttribute('data-type');
                            if (t === chosen) {
                                p.className = 'action-type-pill flex items-center justify-center p-2.5 rounded-lg border text-xs cursor-pointer transition-all border-maroon bg-peach/40 text-maroon font-semibold';
                            } else {
                                p.className = 'action-type-pill flex items-center justify-center p-2.5 rounded-lg border text-xs cursor-pointer transition-all border-line bg-white text-muted hover:border-muted font-medium';
                            }
                        });
                        if (actionEventPanel) actionEventPanel.classList.toggle('hidden', chosen !== 'event');
                        if (actionUrlPanel) actionUrlPanel.classList.toggle('hidden', chosen !== 'url');
                        updatePreview();
                    });
                });

                if (eventSelector && actionSlugInput) {
                    eventSelector.addEventListener('change', function () {
                        if (eventSelector.value) {
                            actionSlugInput.value = eventSelector.value;
                            updatePreview();
                        }
                    });
                    actionSlugInput.addEventListener('input', function () {
                        if (eventSelector) eventSelector.value = actionSlugInput.value || '';
                        updatePreview();
                    });
                }

                if (actionUrlInput) actionUrlInput.addEventListener('input', updatePreview);

                document.querySelectorAll('.preset-btn').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        const lang = btn.getAttribute('data-for');
                        const txt = btn.getAttribute('data-text');
                        if (lang === 'en' && textEnInput) {
                            textEnInput.value = txt;
                        } else if (lang === 'hi' && textHiInput) {
                            textHiInput.value = txt;
                        }
                        updatePreview();
                    });
                });

                document.querySelectorAll('.until-preset').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        const hours = parseInt(btn.getAttribute('data-hours'), 10) || 2;
                        const d = new Date(Date.now() + hours * 3600 * 1000);
                        const pad = (n) => String(n).padStart(2, '0');
                        const localIso = d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
                        if (untilInput) {
                            untilInput.value = localIso;
                            updatePreview();
                        }
                    });
                });

                const untilClearBtn = document.querySelector('.until-clear');
                if (untilClearBtn && untilInput) {
                    untilClearBtn.addEventListener('click', function () {
                        untilInput.value = '';
                        updatePreview();
                    });
                }

                updatePreview();
            });
            </script>
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
