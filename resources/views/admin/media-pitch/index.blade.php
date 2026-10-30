<x-app-layout title="Media Partner Pitch" subtitle="Generate, customize, and export media partnership proposals for any WordCamp event."
              :breadcrumbs="[['Events', route('admin.events.index')], ['Media Partner Pitch']]">

    <div x-data="mediaPitch({
        events: @js($events->values()),
        selected: @js($selected),
        applicant: @js($applicant),
        bios: @js($bios),
        emailTemplate: @js($emailTemplate),
        subjectTemplate: @js($subjectTemplate),
        readyLines: @js($readyLines),
    })" class="space-y-6">

        {{-- Top Bar / Event Selector & Quick Actions --}}
        <div class="flex flex-col gap-4 rounded-xl border border-line bg-surface-card p-5 shadow-xs sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-1 flex-col gap-2 sm:flex-row sm:items-center">
                <label for="event-select" class="text-sm font-semibold text-ink sm:w-auto">
                    Select Event:
                </label>
                <select id="event-select" x-model="selectedSlug" x-on:change="onEventChange()"
                        class="cb-input text-sm sm:max-w-md">
                    <option value="custom">-- Custom / Other WordCamp --</option>
                    @foreach ($events as $ev)
                        <option value="{{ $ev['slug'] }}">{{ $ev['name'] }}{{ $ev['dates'] ? ' ('.$ev['dates'].')' : '' }}</option>
                    @endforeach
                </select>
                <span x-show="selectedSlug !== 'custom'" class="text-xs text-muted">
                    Auto-filled from database
                </span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button type="button" x-on:click="copyEmail()"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white shadow-xs hover:bg-emerald-700 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/50">
                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                    </svg>
                    <span x-text="copiedEmail ? 'Copied to Clipboard!' : 'Copy Full Email'"></span>
                </button>

                <a :href="printUrl" target="_blank"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-line bg-paper-soft px-3.5 py-2 text-xs font-semibold text-ink shadow-xs hover:bg-paper focus:outline-hidden">
                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <polyline points="6 9 6 2 18 2 18 9"></polyline>
                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                        <rect x="6" y="14" width="12" height="8"></rect>
                    </svg>
                    <span>Print / PDF View</span>
                </a>

                <a :href="'mailto:' + encodeURIComponent(eventEmail) + '?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(emailBody)"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-line bg-paper-soft px-3.5 py-2 text-xs font-semibold text-ink shadow-xs hover:bg-paper focus:outline-hidden">
                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                        <polyline points="22,6 12,13 2,6"></polyline>
                    </svg>
                    <span>Open in Email App</span>
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            {{-- Left Side: Controls & Input Parameters --}}
            <div class="space-y-6 lg:col-span-5">
                <x-card title="Event & Pitch Settings">
                    <div class="space-y-4 text-sm">
                        <div>
                            <label class="cb-label" for="pitch-event-name">Event Name</label>
                            <input type="text" id="pitch-event-name" x-model="eventName" class="cb-input" placeholder="e.g. WordCamp Delhi 2026">
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label class="cb-label" for="pitch-event-dates">Event Dates</label>
                                <input type="text" id="pitch-event-dates" x-model="eventDates" class="cb-input" placeholder="e.g. 12–13 December 2026">
                            </div>
                            <div>
                                <label class="cb-label" for="pitch-event-email">Organizer Email</label>
                                <input type="email" id="pitch-event-email" x-model="eventEmail" class="cb-input" placeholder="e.g. delhi@wordcamp.org">
                            </div>
                        </div>

                        <div>
                            <label class="cb-label" for="pitch-event-venue">Venue / City</label>
                            <input type="text" id="pitch-event-venue" x-model="eventVenue" class="cb-input" placeholder="e.g. Dr. Ambedkar International Centre, New Delhi">
                        </div>

                        <div>
                            <label class="cb-label" for="pitch-event-link">Event's CampBuddy page</label>
                            <input type="url" id="pitch-event-link" x-model="eventLink" class="cb-input" placeholder="https://campbuddy.club/event/…">
                            <p class="mt-1 text-xs text-muted">Organizers can open it and try the app before they reply. Empty when the event is not public on CampBuddy yet.</p>
                        </div>

                        <hr class="border-line">

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label class="cb-label" for="pitch-applicant-name">Applicant Name</label>
                                <input type="text" id="pitch-applicant-name" x-model="applicantName" class="cb-input">
                            </div>
                            <div>
                                <label class="cb-label" for="pitch-applicant-role">Role / Designation</label>
                                <input type="text" id="pitch-applicant-role" x-model="applicantRole" class="cb-input">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label class="cb-label" for="pitch-applicant-email">Email</label>
                                <input type="email" id="pitch-applicant-email" x-model="applicantEmail" class="cb-input">
                            </div>
                            <div>
                                <label class="cb-label" for="pitch-applicant-phone">WhatsApp / Phone</label>
                                <input type="text" id="pitch-applicant-phone" x-model="applicantPhone" class="cb-input">
                            </div>
                        </div>

                        <label class="flex items-center gap-2 pt-1 text-xs text-ink cursor-pointer">
                            <input type="checkbox" x-model="includePhone" class="cb-check">
                            <span>Include WhatsApp/Phone in Email Sign-off</span>
                        </label>
                    </div>
                </x-card>

                {{-- Reference Card: Social Links & Common Logo --}}
                <x-card title="Brand Assets & Channels">
                    {{-- Common Logo Preview: the lockup with the guideline's clear space (tools/logo-clear-space.php) --}}
                    <div class="rounded-lg border border-line bg-paper-soft p-3 mb-3">
                        <p class="font-semibold text-ink text-xs flex items-center gap-1.5 mb-2">
                            <span class="h-2 w-2 rounded-full bg-emerald-600"></span>
                            Common Co-Branded Logo
                        </p>
                        <div class="rounded-lg border border-line bg-white p-2 flex items-center justify-center min-h-[64px]">
                            <img src="{{ \App\Support\MediaPitch::LOGO_SVG }}?v={{ @filemtime(public_path('media/campbuddy-wpsimplified-logo-clear.svg')) }}" alt="CampBuddy × WPSimplified" class="h-14 w-auto max-w-full object-contain">
                        </div>
                        <p class="mt-2 text-[12px] text-muted">With clear space on every side (brand guideline), so a partner's page can't crowd it.</p>
                        <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                            <a href="{{ \App\Support\MediaPitch::LOGO_PNG }}" download="CampBuddy-WPSimplified-logo.png" class="text-primary hover:underline font-medium">PNG for websites</a>
                            <a href="{{ \App\Support\MediaPitch::LOGO_PRINT_PNG }}" download="CampBuddy-WPSimplified-logo-900dpi.png" class="text-primary hover:underline font-medium">PNG 900 DPI (print)</a>
                            <a href="{{ \App\Support\MediaPitch::LOGO_SVG }}" download="CampBuddy-WPSimplified-logo.svg" class="text-primary hover:underline font-medium">SVG</a>
                            <a href="/media/campbuddy-wpsimplified-logo.figcraft" download="CampBuddy-WPSimplified-CoBranded-Logo.figcraft" class="text-emerald-700 hover:underline font-semibold">FigCraft (.figcraft)</a>
                        </div>
                        <p class="mt-1 text-[12px] text-muted">The SVG holds the same high-resolution picture (not vector paths); for print, the 900 DPI PNG is the sharper choice.</p>
                    </div>

                    <p class="text-xs text-muted mb-3">
                        Official verified channels included in proposals:
                    </p>
                    <div class="space-y-3 text-xs">
                        <div class="rounded-lg border border-line bg-paper-soft p-3">
                            <p class="font-semibold text-ink flex items-center gap-1.5 mb-1.5">
                                <span class="h-2 w-2 rounded-full bg-danger"></span>
                                <b>CampBuddy (Attendee Companion)</b>
                            </p>
                            <ul class="space-y-1 text-muted">
                                <li>Website: <a href="https://campbuddy.club/" target="_blank" class="text-primary hover:underline">campbuddy.club</a></li>
                                <li>YouTube: <a href="https://www.youtube.com/@CampBuddyClub" target="_blank" class="text-primary hover:underline">@CampBuddyClub</a></li>
                                <li>Instagram: <a href="https://www.instagram.com/campbuddyclub/" target="_blank" class="text-primary hover:underline">@campbuddyclub</a></li>
                                <li>X (Twitter): <a href="https://x.com/CampBuddyClub" target="_blank" class="text-primary hover:underline">@CampBuddyClub</a></li>
                            </ul>
                        </div>

                        <div class="rounded-lg border border-line bg-paper-soft p-3">
                            <p class="font-semibold text-ink flex items-center gap-1.5 mb-1.5">
                                <span class="h-2 w-2 rounded-full bg-emerald-600"></span>
                                <b>WPSimplified (Developer Education)</b>
                            </p>
                            <ul class="space-y-1 text-muted">
                                <li>Website: <a href="https://wpsimplified.in/" target="_blank" class="text-primary hover:underline">wpsimplified.in</a></li>
                                <li>YouTube: <a href="https://www.youtube.com/@WPSimplifiedBySunil" target="_blank" class="text-primary hover:underline">@WPSimplifiedBySunil</a></li>
                                <li>Instagram: <a href="https://www.instagram.com/wpsimplified/" target="_blank" class="text-primary hover:underline">@wpsimplified</a></li>
                                <li>X (Twitter): <a href="https://x.com/iwpsimplified" target="_blank" class="text-primary hover:underline">@iwpsimplified</a></li>
                                <li>LinkedIn: <a href="https://www.linkedin.com/company/wpsimplified/" target="_blank" class="text-primary hover:underline">company/wpsimplified</a></li>
                            </ul>
                        </div>
                    </div>
                </x-card>
            </div>

            {{-- Right Side: The Pitch Canvas --}}
            <div class="space-y-6 lg:col-span-7">
                {{-- Email Pitch Card --}}
                <x-card title="Email Pitch (Proposal Body)">
                    <x-slot:headerActions>
                        <button type="button" x-on:click="copyEmail()"
                                class="inline-flex items-center gap-1 rounded-md bg-paper-soft px-2.5 py-1 text-xs font-semibold text-ink border border-line hover:bg-paper">
                            <span x-text="copiedEmail ? 'Copied!' : 'Copy Email'"></span>
                        </button>
                    </x-slot:headerActions>

                    <div class="space-y-3">
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="cb-label mb-0" for="pitch-subject">Subject Line</label>
                                <button type="button" x-on:click="copySubject()" class="text-xs text-primary hover:underline">
                                    <span x-text="copiedSubject ? 'Copied!' : 'Copy Subject'"></span>
                                </button>
                            </div>
                            <input type="text" id="pitch-subject" x-model="subject" x-on:input="subjectEdited = true" class="cb-input font-medium text-ink">
                        </div>

                        <div>
                            <label class="cb-label" for="pitch-body">Full Email Text</label>
                            <div class="mb-1 flex items-center justify-between" x-show="emailEdited" x-cloak>
                                <span class="text-xs text-muted">You edited this text: changing the event won't overwrite it.</span>
                                <button type="button" x-on:click="regenerate(true)" class="text-xs text-primary hover:underline">Rebuild from the fields</button>
                            </div>
                            <textarea id="pitch-body" x-model="emailBody" x-on:input="emailEdited = true" rows="16"
                                      class="cb-input font-mono text-xs leading-relaxed"
                                      style="white-space: pre-wrap;"></textarea>
                        </div>
                    </div>
                </x-card>

                {{-- MULTI-BIO STUDIO: Ready-to-Publish Website & Media Bios --}}
                <div class="rounded-xl border border-emerald-500/30 bg-emerald-50/30 p-5 shadow-xs">
                    {{-- Header Row --}}
                    <div class="flex flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between mb-3 border-b border-emerald-200/70 pb-3">
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-bold text-ink">Ready-to-Publish Website Bios</h3>
                            <span class="inline-flex items-center rounded-full bg-emerald-700 px-2.5 py-0.5 text-[10.5px] font-semibold text-white tracking-wide">
                                Official Listing Copy
                            </span>
                        </div>

                        <button type="button" x-on:click="copyActiveBio()"
                                class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-emerald-700 px-3.5 py-2 text-xs font-semibold text-white shadow-xs hover:bg-emerald-800 focus:outline-hidden shrink-0 transition-colors">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                            </svg>
                            <span x-text="copiedActiveBio ? 'Copied to Clipboard!' : 'Copy Selected Bio'"></span>
                        </button>
                    </div>

                    {{-- 4-Column Equal-Width Responsive Grid Tabs --}}
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-2 mb-3">
                        <button type="button" x-on:click="selectTab('campbuddy')"
                                :class="activeTab === 'campbuddy' 
                                    ? 'border-emerald-600 bg-emerald-700 text-white shadow-xs' 
                                    : 'border-line bg-white text-ink hover:bg-emerald-50/50 hover:border-emerald-300'"
                                class="flex flex-col items-center justify-center rounded-lg border p-2.5 text-center transition-all">
                            <span class="text-xs font-bold leading-tight">CampBuddy</span>
                            <span :class="activeTab === 'campbuddy' ? 'text-emerald-100' : 'text-muted'" class="text-[10px] mt-0.5">App &amp; USPs Bio</span>
                        </button>

                        <button type="button" x-on:click="selectTab('wpsimplified')"
                                :class="activeTab === 'wpsimplified' 
                                    ? 'border-emerald-600 bg-emerald-700 text-white shadow-xs' 
                                    : 'border-line bg-white text-ink hover:bg-emerald-50/50 hover:border-emerald-300'"
                                class="flex flex-col items-center justify-center rounded-lg border p-2.5 text-center transition-all">
                            <span class="text-xs font-bold leading-tight">WPSimplified</span>
                            <span :class="activeTab === 'wpsimplified' ? 'text-emerald-100' : 'text-muted'" class="text-[10px] mt-0.5">Platform &amp; Media</span>
                        </button>

                        <button type="button" x-on:click="selectTab('sunil')"
                                :class="activeTab === 'sunil' 
                                    ? 'border-emerald-600 bg-emerald-700 text-white shadow-xs' 
                                    : 'border-line bg-white text-ink hover:bg-emerald-50/50 hover:border-emerald-300'"
                                class="flex flex-col items-center justify-center rounded-lg border p-2.5 text-center transition-all">
                            <span class="text-xs font-bold leading-tight">Sunil Kumar Sharma</span>
                            <span :class="activeTab === 'sunil' ? 'text-emerald-100' : 'text-muted'" class="text-[10px] mt-0.5">Lead Architect Bio</span>
                        </button>

                        <button type="button" x-on:click="selectTab('joint')"
                                :class="activeTab === 'joint' 
                                    ? 'border-emerald-600 bg-emerald-700 text-white shadow-xs' 
                                    : 'border-line bg-white text-ink hover:bg-emerald-50/50 hover:border-emerald-300'"
                                class="flex flex-col items-center justify-center rounded-lg border p-2.5 text-center transition-all">
                            <span class="text-xs font-bold leading-tight">CampBuddy × WPS</span>
                            <span :class="activeTab === 'joint' ? 'text-emerald-100' : 'text-muted'" class="text-[10px] mt-0.5">Joint Partner Bio</span>
                        </button>
                    </div>

                    {{-- Short (a website listing) or full (an about page) --}}
                    <div class="mb-3 inline-flex rounded-lg border border-line bg-white p-0.5 text-xs" role="group" aria-label="Bio length">
                        <button type="button" x-on:click="bioLength = 'short'" :aria-pressed="bioLength === 'short'"
                                :class="bioLength === 'short' ? 'bg-emerald-700 text-white' : 'text-ink hover:bg-emerald-50'"
                                class="rounded-md px-3 py-1.5 font-semibold transition-colors">Short (~60 words)</button>
                        <button type="button" x-on:click="bioLength = 'full'" :aria-pressed="bioLength === 'full'"
                                :class="bioLength === 'full' ? 'bg-emerald-700 text-white' : 'text-ink hover:bg-emerald-50'"
                                class="rounded-md px-3 py-1.5 font-semibold transition-colors">Full</button>
                    </div>

                    {{-- Textarea Info Bar --}}
                    <div class="flex items-center justify-between text-xs text-muted mb-1.5 px-0.5">
                        <span class="font-semibold text-emerald-950" x-text="activeTabLabel"></span>
                        <span class="text-[11px] font-medium text-emerald-800" x-text="wordCountText"></span>
                    </div>

                    {{-- Active Bio Textarea --}}
                    <div>
                        <textarea id="active-bio-textarea" x-model="bios[activeTab][bioLength]" rows="9"
                                  class="cb-input font-sans text-xs leading-relaxed border-emerald-300 bg-white p-3 shadow-2xs focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600 min-h-[190px]"
                                  style="white-space: pre-wrap;"></textarea>
                    </div>

                    {{-- 1-Click Quick Copy Toolbar --}}
                    <div class="mt-3.5 border-t border-emerald-200/70 pt-3">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-semibold text-ink text-xs flex items-center gap-1.5">
                                <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" class="text-emerald-700 shrink-0">
                                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                </svg>
                                1-Click Quick Copy:
                            </span>
                            <span class="text-[12px] text-muted" x-text="'Copies the ' + bioLength + ' version'"></span>
                        </div>
                        <div class="grid grid-cols-2 lg:grid-cols-4 gap-2">
                            <button type="button" x-on:click="copyBioByKey('campbuddy')"
                                    class="flex items-center justify-center rounded-lg border border-line bg-white px-2.5 py-2 text-xs font-medium text-ink shadow-2xs hover:bg-emerald-50 hover:border-emerald-300 hover:text-emerald-800 transition-all text-center">
                                <span x-text="copiedKeys['campbuddy'] ? '✓ Copied CampBuddy' : 'Copy CampBuddy'"></span>
                            </button>
                            <button type="button" x-on:click="copyBioByKey('wpsimplified')"
                                    class="flex items-center justify-center rounded-lg border border-line bg-white px-2.5 py-2 text-xs font-medium text-ink shadow-2xs hover:bg-emerald-50 hover:border-emerald-300 hover:text-emerald-800 transition-all text-center">
                                <span x-text="copiedKeys['wpsimplified'] ? '✓ Copied WPSimplified' : 'Copy WPSimplified'"></span>
                            </button>
                            <button type="button" x-on:click="copyBioByKey('sunil')"
                                    class="flex items-center justify-center rounded-lg border border-line bg-white px-2.5 py-2 text-xs font-medium text-ink shadow-2xs hover:bg-emerald-50 hover:border-emerald-300 hover:text-emerald-800 transition-all text-center">
                                <span x-text="copiedKeys['sunil'] ? '✓ Copied Sunil Bio' : 'Copy Sunil Bio'"></span>
                            </button>
                            <button type="button" x-on:click="copyBioByKey('joint')"
                                    class="flex items-center justify-center rounded-lg border border-line bg-white px-2.5 py-2 text-xs font-medium text-ink shadow-2xs hover:bg-emerald-50 hover:border-emerald-300 hover:text-emerald-800 transition-all text-center">
                                <span x-text="copiedKeys['joint'] ? '✓ Copied Joint Bio' : 'Copy Joint Bio'"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function mediaPitch(config) {
            const firstEvent = config.selected || config.events[0] || {};
            const originals = JSON.parse(JSON.stringify(config.bios));
            return {
                events: config.events || [],
                selectedSlug: firstEvent.slug || 'custom',
                eventName: firstEvent.name || 'WordCamp Delhi 2026',
                eventDates: firstEvent.dates || '',
                eventVenue: firstEvent.venue || '',
                eventEmail: firstEvent.email || '',
                eventLink: firstEvent.link || '',
                applicantName: config.applicant.name,
                applicantRole: config.applicant.role,
                applicantEmail: config.applicant.email,
                applicantPhone: config.applicant.phone,
                includePhone: true,
                subject: '',
                emailBody: '',
                subjectEdited: false,
                emailEdited: false,
                copiedSubject: false,
                copiedEmail: false,
                copiedActiveBio: false,
                copiedKeys: {},
                activeTab: 'campbuddy',
                bioLength: 'short',
                bios: JSON.parse(JSON.stringify(config.bios)),

                init() {
                    this.regenerate();
                    // The email follows the fields until someone edits the text itself.
                    this.$watch(() => [this.eventName, this.eventDates, this.eventVenue, this.eventLink, this.applicantName, this.applicantRole, this.applicantEmail, this.applicantPhone, this.includePhone].join('|'), () => this.regenerate());
                },

                regenerate(force = false) {
                    const details = [this.eventDates, this.eventVenue].filter((v) => v && v.trim()).join(', ');
                    const tokens = {
                        event: this.eventName || 'your WordCamp',
                        details: details ? ` (${details})` : '',
                        link: (this.eventLink || '').trim(),
                        name: this.applicantName,
                        role: this.applicantRole,
                        email: this.applicantEmail,
                        phone: this.includePhone && this.applicantPhone ? `\nWhatsApp: ${this.applicantPhone}` : '',
                    };
                    const fill = (text) => text.replace(/\{(event|details|link|name|role|email|phone)\}/g, (m, key) => tokens[key]);
                    // The event on CampBuddy: a link to try it, or the promise to set it up (MediaPitch::readyLine).
                    const ready = fill(tokens.link ? config.readyLines.link : config.readyLines.none);
                    const body = fill(config.emailTemplate).replace('{ready}', ready);
                    if (force || !this.emailEdited) {
                        this.emailBody = body;
                        this.emailEdited = false;
                    }
                    if (force || !this.subjectEdited) {
                        this.subject = fill(config.subjectTemplate);
                        this.subjectEdited = false;
                    }
                },

                get activeTabLabel() {
                    const bio = this.bios[this.activeTab];
                    return bio ? `${bio.label} (${this.bioLength === 'short' ? 'short, for a listing' : 'full, for an about page'})` : 'Website Bio';
                },

                get wordCountText() {
                    const text = (this.bios[this.activeTab]?.[this.bioLength] || '').trim();
                    return text ? `${text.split(/\s+/).length} words` : '0 words';
                },

                get printUrl() {
                    const params = new URLSearchParams({
                        event_name: this.eventName,
                        event_dates: this.eventDates,
                        event_venue: this.eventVenue,
                        event_email: this.eventEmail,
                        event_link: this.eventLink,
                        applicant_name: this.applicantName,
                        applicant_role: this.applicantRole,
                        applicant_email: this.applicantEmail,
                        applicant_phone: this.applicantPhone,
                        show_phone: this.includePhone ? '1' : '0',
                        subject: this.subject,
                    });
                    // Only bios someone changed travel in the address; the PDF has the rest.
                    for (const [key, bio] of Object.entries(this.bios)) {
                        if (bio.full !== originals[key].full) params.set(`${key}_bio`, bio.full);
                    }
                    return '{{ route('admin.media-pitch.print') }}?' + params.toString();
                },

                onEventChange() {
                    if (this.selectedSlug === 'custom') {
                        return;
                    }
                    const ev = this.events.find((e) => e.slug === this.selectedSlug);
                    if (ev) {
                        this.eventName = ev.name;
                        this.eventDates = ev.dates || '';
                        this.eventVenue = ev.venue || '';
                        this.eventEmail = ev.email || '';
                        this.eventLink = ev.link || '';
                    }
                },

                selectTab(tab) {
                    this.activeTab = tab;
                    this.$nextTick(() => {
                        const el = document.getElementById('active-bio-textarea');
                        if (el) el.scrollTop = 0;
                    });
                },

                copy(text, flag, key = null) {
                    navigator.clipboard.writeText(text);
                    if (key) {
                        this.copiedKeys[key] = true;
                        setTimeout(() => { this.copiedKeys[key] = false; }, 2000);
                        return;
                    }
                    this[flag] = true;
                    setTimeout(() => { this[flag] = false; }, 2000);
                },

                copySubject() { this.copy(this.subject, 'copiedSubject'); },
                copyEmail() { this.copy(this.emailBody, 'copiedEmail'); },
                copyActiveBio() { this.copy(this.bios[this.activeTab][this.bioLength], 'copiedActiveBio'); },
                copyBioByKey(key) { if (this.bios[key]) this.copy(this.bios[key][this.bioLength], null, key); },
            };
        }
    </script>
</x-app-layout>
