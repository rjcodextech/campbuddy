<x-app-layout title="Media Partner Pitch" subtitle="Generate, customize, and export media partnership proposals for any WordCamp event."
              :breadcrumbs="[['Events', route('admin.events.index')], ['Media Partner Pitch']]">

    @php
        $eventsJson = $events->map(function ($e) {
            $dates = '';
            if ($e->starts_on) {
                $dates = $e->starts_on->format('j M Y');
                if ($e->ends_on && ! $e->ends_on->isSameDay($e->starts_on)) {
                    $dates .= ' – ' . $e->ends_on->format('j M Y');
                }
            }
            $venue = $e->venue_name ? $e->venue_name . ($e->city ? ', ' . $e->city : '') : ($e->city ? $e->city . ($e->country ? ', ' . $e->country : '') : '');
            return [
                'id' => $e->id,
                'slug' => $e->slug,
                'name' => $e->display_name,
                'dates' => $dates,
                'venue' => $venue,
                'email' => $e->slug ? $e->slug . '@wordcamp.org' : '',
            ];
        })->values()->toJson();
    @endphp

    <div x-data="mediaPitch({
        events: {{ $eventsJson }},
        defaultEvent: '{{ $selectedEvent?->slug ?? '' }}',
        defaults: {{ json_encode($defaults) }}
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
                        @php
                            $d = '';
                            if ($ev->starts_on) {
                                $d = $ev->starts_on->format('j M Y');
                                if ($ev->ends_on && ! $ev->ends_on->isSameDay($ev->starts_on)) {
                                    $d .= ' – ' . $ev->ends_on->format('j M Y');
                                }
                            }
                        @endphp
                        <option value="{{ $ev->slug }}">
                            {{ $ev->display_name }}{{ $d ? ' (' . $d . ')' : '' }}
                        </option>
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
                    {{-- Common Logo Preview --}}
                    <div class="rounded-lg border border-line bg-paper-soft p-3 mb-3">
                        <div class="flex items-center justify-between mb-2">
                            <p class="font-semibold text-ink text-xs flex items-center gap-1.5">
                                <span class="h-2 w-2 rounded-full bg-emerald-600"></span>
                                Common Co-Branded Logo
                            </p>
                            <a href="/media/campbuddy-wpsimplified-logo.svg" target="_blank" download="campbuddy-wpsimplified-logo.svg" class="text-xs text-primary hover:underline font-medium">
                                Download SVG
                            </a>
                        </div>
                        <div class="rounded-lg border border-line bg-white p-3 flex items-center justify-center min-h-[64px]">
                            <img src="/media/campbuddy-wpsimplified-logo.svg?v={{ @filemtime(public_path('media/campbuddy-wpsimplified-logo.svg')) }}" alt="CampBuddy | WPSimplified" class="h-11 max-h-12 w-auto max-w-full object-contain">
                        </div>
                        <div class="flex items-center justify-between mt-2 text-[11px] text-muted">
                            <span>Print-Ready 900 DPI • Zero Margins</span>
                            <a href="/media/campbuddy-wpsimplified-logo-900dpi.png" target="_blank" download="campbuddy-wpsimplified-logo-900dpi.png" class="text-primary hover:underline font-medium">
                                Download 900 DPI PNG
                            </a>
                        </div>
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
                            <input type="text" id="pitch-subject" x-model="subject" class="cb-input font-medium text-ink">
                        </div>

                        <div>
                            <label class="cb-label" for="pitch-body">Full Email Text</label>
                            <textarea id="pitch-body" x-model="emailBody" rows="16"
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
                            <span class="text-xs font-bold leading-tight">CampBuddy | WPS</span>
                            <span :class="activeTab === 'joint' ? 'text-emerald-100' : 'text-muted'" class="text-[10px] mt-0.5">Joint Partner Bio</span>
                        </button>
                    </div>

                    {{-- Textarea Info Bar --}}
                    <div class="flex items-center justify-between text-xs text-muted mb-1.5 px-0.5">
                        <span class="font-semibold text-emerald-950" x-text="activeTabLabel"></span>
                        <span class="text-[11px] font-medium text-emerald-800" x-text="wordCountText"></span>
                    </div>

                    {{-- Active Bio Textarea --}}
                    <div>
                        <textarea id="active-bio-textarea" x-model="bios[activeTab]" rows="9"
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
                            <span class="text-[11px] text-muted">Directly copy any bio without switching tabs</span>
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
            return {
                events: config.events || [],
                selectedSlug: config.defaultEvent || (config.events[0]?.slug || 'custom'),
                eventName: config.defaults.event_name || 'WordCamp Delhi 2026',
                eventDates: config.defaults.event_dates || '12–13 December 2026',
                eventVenue: config.defaults.event_venue || 'Dr. Ambedkar International Centre, New Delhi',
                eventEmail: config.defaults.event_email || 'delhi@wordcamp.org',
                applicantName: config.defaults.applicant_name || 'Sunil Kumar Sharma',
                applicantRole: config.defaults.applicant_role || 'Lead — WPSimplified & CampBuddy',
                applicantEmail: config.defaults.applicant_email || 'hello@wpsimplified.in',
                applicantPhone: config.defaults.applicant_phone || '+91-7877994704',
                includePhone: true,
                copiedSubject: false,
                copiedEmail: false,
                copiedActiveBio: false,
                copiedKeys: {},
                activeTab: 'campbuddy',

                bios: {
                    campbuddy: config.defaults.campbuddy_bio || 'CampBuddy (by WPSimplified) is an intelligent, mobile companion app engineered to transform how attendees experience WordCamps and developer conferences. Built from the ground up with a "guidance over information" philosophy, CampBuddy answers the one question every first-timer and seasoned attendee asks standing in a crowded venue: "What should I do right now?" Rather than serving static timetables, CampBuddy acts as a live on-ground co-pilot. Key features include real-time multi-track schedule tracking with bookmarking and timely session alerts, digital networking Camp Cards equipped with dynamic LinkedIn QR codes for instant connections, and interest-based attendee matching to help community members find like-minded collaborators effortlessly. To elevate event participation, CampBuddy integrates gamified exploration quests that guide attendees through the venue and directly toward sponsor booths. For Contributor Day, it features a skill-based table navigator that demystifies open collaboration, helping newcomers confidently find suitable teams to contribute. Sponsors benefit from interactive, ticket-style deal showcases featuring frictionless in-app lead capture forms that deliver verified attendee leads with genuine return on investment. Designed with a strict privacy-first architecture, CampBuddy requires no account creation, no logins, and collects no personal data—storing attendee preferences locally on the device while operating reliably offline even across congested conference Wi-Fi networks. CampBuddy bridges the gap between feeling lost and feeling connected, empowering every attendee to make the most of their event experience.',
                    wpsimplified: config.defaults.wpsimplified_bio || 'WPSimplified is a premier WordPress education platform and community led by senior technical architect and community leader Sunil Kumar Sharma. Operating with an uncompromising "zero gatekeeping" ethos, the platform is on a mission to democratize modern web engineering and empower the next generation of developers, freelancers, and digital creators. WPSimplified breaks down complex enterprise web topics—custom plugin architecture, theme development, scalable systems design, API integrations, and performance optimization—into simple, practical, step-by-step learning paths that anyone can follow with complete confidence. Recognizing that language barriers often hold back exceptional talent, WPSimplified actively bridges the regional divide in tech by delivering high-impact bilingual education in Hindi and English. Through comprehensive video tutorials, hands-on architectural workshops, live developer masterclasses, and practical podcasts, the platform demystifies coding fundamentals and real-world engineering standards. Beyond technical training, WPSimplified offers actionable career coaching and freelancing guidance, helping learners transition from feeling stuck to building scalable digital solutions, launching successful agencies, and mastering client communication. Supported by a vibrant, rapidly growing community of passionate developers and creators across South Asia and worldwide, WPSimplified provides the continuous mentorship, real-world insights, and community support needed to thrive. At WPSimplified, WordPress development is made simple, practical, and profitable—ensuring top-tier technical education is accessible to everyone with zero barriers.',
                    sunil: config.defaults.sunil_bio || 'Sunil Kumar Sharma is a Senior WordPress Developer and Technical Architect with over a decade of hands-on experience designing and engineering scalable web platforms, high-traffic digital ecosystems, and custom plugins. Deeply engaged in the WordPress community, Sunil has contributed extensively across South Asia as a speaker, organizer, volunteer, and mentor at numerous regional conferences, including WordCamp Kolhapur, WordCamp Nepal, WordCamp Bhopal, WordCamp Rajasthan, WordCamp Bengaluru, WordCamp Delhi, and WordCamp Asia. As the Lead at WPSimplified and CampBuddy, Sunil combines enterprise-level engineering acumen with an authentic commitment to grassroots impact. He is passionate about bridging the language divide in technology, dedicating his work to empowering non-metro developers and creators through regional language masterclasses, actionable content creation, and developer training. Sunil operates under a straightforward guiding philosophy: "zero gatekeeping—tech sabko sikhana is the vibe." His mission is ensuring that modern web development is never locked behind complex jargon or inaccessible barriers. Whether architecting scalable backend solutions, speaking on stage at tech conferences, or mentoring young developers taking their first steps in tech, Sunil brings a genuine community-first mindset to everything he builds. His focus remains steadfast on creating intelligent tools that enhance event experiences and producing practical educational resources that enable developers to build sustainable, scalable, and profitable digital careers.',
                    joint: config.defaults.joint_bio || 'CampBuddy | WPSimplified is a collaborative initiative led by WordPress architect Sunil Kumar Sharma, created to empower the global WordPress community through intelligent event tools and accessible developer education. By bringing together on-ground utility and high-value media reach, this partnership provides comprehensive support to conference organizers, attendees, and sponsors alike. CampBuddy functions as an intelligent mobile companion app for WordCamp attendees, turning busy schedules into real-time guidance. Operating with a strict "guidance over information" philosophy, it features live multi-track session navigation, digital networking Camp Cards with instant LinkedIn QR codes, gamified exploration quests, and guided Contributor Day table onboarding. Sponsors receive high-impact visibility through interactive deal showcases and zero-friction lead capture forms, all delivered within a 100% account-free, privacy-first, offline-ready architecture. Working hand in hand, WPSimplified serves as the educational powerhouse and media amplifier. With an active following of ambitious developers, freelancers, and creators, WPSimplified provides pre-event hype campaigns, on-site attendee reaction video coverage, and post-event retrospective articles published on official channels. Operating with a "zero gatekeeping" philosophy, WPSimplified bridges regional language divides, ensuring event takeaways and developer insights reach a wider, highly engaged South Asian audience. Together, CampBuddy and WPSimplified deliver unmatched attendee utility, memorable event energy, and measurable sponsor engagement.'
                },

                get activeTabLabel() {
                    const labels = {
                        campbuddy: 'CampBuddy — Attendee Companion App Bio (All USPs Included)',
                        wpsimplified: 'WPSimplified — Developer Education Platform Bio',
                        sunil: 'Sunil Kumar Sharma — Lead Architect Bio',
                        joint: 'CampBuddy | WPSimplified — Joint Partnership Bio'
                    };
                    return labels[this.activeTab] || 'Website Bio';
                },

                get wordCountText() {
                    const text = (this.bios[this.activeTab] || '').trim();
                    if (!text) return '0 words';
                    const words = text.split(/\s+/).length;
                    return `${words} words • Ready to publish`;
                },

                get subject() {
                    return `Media Partnership Proposal: ${this.eventName} × CampBuddy & WPSimplified`;
                },

                get emailBody() {
                    const phoneLine = (this.includePhone && this.applicantPhone) ? `\nWhatsApp: ${this.applicantPhone}` : '';
                    return `Dear ${this.eventName} Organizing Team,\n\n` +
`Greetings from the team at CampBuddy and WPSimplified!\n\n` +
`We are writing to officially apply for the Media Partnership for ${this.eventName}` + (this.eventDates ? ` (${this.eventDates}` + (this.eventVenue ? `, ${this.eventVenue}` : '') + `)` : '') + `.\n\n` +
`We want to keep our proposal focused, transparent, and grounded strictly in high-value deliverables that we will genuinely execute to support your attendees, speakers, and sponsors.\n\n` +
`### Who We Are\n` +
`* CampBuddy (https://campbuddy.club/): An intelligent mobile companion app built for WordCamp attendees. Guided by a "guidance over information" philosophy, it answers the #1 first-timer question: "What should I do right now?" — featuring live multi-track session guidance, digital Camp Cards with instant LinkedIn QR networking, interest-based attendee matching, gamified sponsor quests, and guided Contributor Day table onboarding. Operates 100% offline-ready with zero accounts and complete on-device privacy.\n` +
`* WPSimplified (https://wpsimplified.in/): A premier WordPress education platform and community led by technical architect Sunil Kumar Sharma. Dedicated to zero gatekeeping in tech, it bridges regional language barriers through actionable tutorials, custom plugin/theme development, and practical developer mentorship.\n\n` +
`### Our Committed Deliverables (Clear & Focused)\n\n` +
`A. Before the Event (Awareness & Excitement):\n` +
`* Targeted Social Media Promotion: Announcement graphics, stories, and hype reels across our official channels (Instagram, X / Twitter, and YouTube Shorts/Reels) highlighting event dates, speaker lineups, ticket updates, and venue information.\n` +
`* Ready-to-Use Attendee Companion: Configuring a dedicated ${this.eventName} instance of CampBuddy with the full schedule, track details, and venue guide pre-loaded so attendees can access everything instantly on mobile without needing an app store download.\n\n` +
`B. During the Event (Attendee Utility & On-Ground Video Coverage):\n` +
`* On-Ground Interactive Companion: Attendees use CampBuddy to see live "Happening Now" sessions, save talks to "My Day", swap digital Camp Cards with LinkedIn QR codes, and navigate Contributor Day tables easily.\n` +
`* On-Site Video Bites & Reactions: We will record short video glimpses, speaker reactions, and attendee interview bites during both days to document the vibrancy and energy of the event.\n\n` +
`C. After the Event (Recap & Community Amplification):\n` +
`* YouTube Video Coverage: Curated video coverage and highlight reels published directly on our official YouTube channels (@WPSimplifiedBySunil and @CampBuddyClub).\n` +
`* Official Recap Article: A detailed ${this.eventName} retrospective article published on the official WPSimplified website (wpsimplified.in) covering highlights, session takeaways, and photos.\n\n` +
`### What We Seek from ${this.eventName}:\n` +
`* Official Listing: Recognition as an official Media Partner on the ${this.eventName} website (name, logo, short bio, and backlink).\n` +
`* Media Passes: Standard media access passes for our team to facilitate on-site video recording, coverage, and attendee assistance.\n` +
`* Companion QR Visibility: Permission to display or share a simple QR code so attendees can scan and open their digital companion app seamlessly.\n\n` +
`### Official Links & Verification Channels:\n` +
`* CampBuddy (Attendee Companion): https://campbuddy.club/ | YouTube: @CampBuddyClub | Instagram: @campbuddyclub | X: @CampBuddyClub\n` +
`* WPSimplified (Developer Education): https://wpsimplified.in/ | YouTube: @WPSimplifiedBySunil | Instagram: @wpsimplified | X: @iwpsimplified | LinkedIn: company/wpsimplified\n\n` +
`We look forward to hearing from you and partnering to make ${this.eventName} a grand success!\n\n` +
`Warm regards,\n\n` +
`${this.applicantName}\n` +
`${this.applicantRole}\n` +
`Email: ${this.applicantEmail}${phoneLine}`;
                },

                get printUrl() {
                    const params = new URLSearchParams({
                        event_name: this.eventName,
                        event_dates: this.eventDates,
                        event_venue: this.eventVenue,
                        event_email: this.eventEmail,
                        applicant_name: this.applicantName,
                        applicant_role: this.applicantRole,
                        applicant_email: this.applicantEmail,
                        applicant_phone: this.applicantPhone,
                        show_phone: this.includePhone ? '1' : '0',
                        subject: this.subject,
                        campbuddy_bio: this.bios.campbuddy,
                        wpsimplified_bio: this.bios.wpsimplified,
                        sunil_bio: this.bios.sunil
                    });
                    return '{{ route('admin.media-pitch.print') }}?' + params.toString();
                },

                onEventChange() {
                    if (this.selectedSlug === 'custom') {
                        return;
                    }
                    const ev = this.events.find(e => e.slug === this.selectedSlug);
                    if (ev) {
                        this.eventName = ev.name;
                        this.eventDates = ev.dates || '';
                        this.eventVenue = ev.venue || '';
                        this.eventEmail = ev.email || (ev.slug + '@wordcamp.org');
                    }
                },

                selectTab(tab) {
                    this.activeTab = tab;
                    this.$nextTick(() => {
                        const el = document.getElementById('active-bio-textarea');
                        if (el) el.scrollTop = 0;
                    });
                },

                copySubject() {
                    navigator.clipboard.writeText(this.subject);
                    this.copiedSubject = true;
                    setTimeout(() => { this.copiedSubject = false; }, 2000);
                },

                copyEmail() {
                    navigator.clipboard.writeText(this.emailBody);
                    this.copiedEmail = true;
                    setTimeout(() => { this.copiedEmail = false; }, 2000);
                },

                copyActiveBio() {
                    navigator.clipboard.writeText(this.bios[this.activeTab]);
                    this.copiedActiveBio = true;
                    setTimeout(() => { this.copiedActiveBio = false; }, 2000);
                },

                copyBioByKey(key) {
                    if (this.bios[key]) {
                        navigator.clipboard.writeText(this.bios[key]);
                        this.copiedKeys[key] = true;
                        setTimeout(() => { this.copiedKeys[key] = false; }, 2000);
                    }
                }
            };
        }
    </script>
</x-app-layout>
