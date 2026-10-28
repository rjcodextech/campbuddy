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
                    <template x-for="ev in events" :key="ev.slug">
                        <option :value="ev.slug" x-text="ev.name + (ev.dates ? ' (' + ev.dates + ')' : '')"></option>
                    </template>
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

                {{-- Reference Card: Social Links Used --}}
                <x-card title="Included Brand Channels & Links">
                    <p class="text-xs text-muted mb-3">
                        These channels are automatically inserted into every pitch proposal:
                    </p>
                    <div class="space-y-3 text-xs">
                        <div class="rounded-lg border border-line bg-paper-soft p-3">
                            <p class="font-semibold text-ink flex items-center gap-1.5 mb-1.5">
                                <span class="h-2 w-2 rounded-full bg-danger"></span>
                                <b>CampBuddy</b>
                            </p>
                            <ul class="space-y-1 text-muted">
                                <li>Website: <a href="https://campbuddy.club/" target="_blank" class="text-primary hover:underline">campbuddy.club</a></li>
                                <li>YouTube: <a href="https://www.youtube.com/@CampBuddyClub" target="_blank" class="text-primary hover:underline">@CampBuddyClub</a></li>
                                <li>Instagram: <a href="https://www.instagram.com/campbuddyclub/" target="_blank" class="text-primary hover:underline">@campbuddyclub</a></li>
                                <li>X: <a href="https://x.com/CampBuddyClub" target="_blank" class="text-primary hover:underline">@CampBuddyClub</a></li>
                            </ul>
                        </div>

                        <div class="rounded-lg border border-line bg-paper-soft p-3">
                            <p class="font-semibold text-ink flex items-center gap-1.5 mb-1.5">
                                <span class="h-2 w-2 rounded-full bg-emerald-600"></span>
                                <b>WPSimplified</b>
                            </p>
                            <ul class="space-y-1 text-muted">
                                <li>Website: <a href="https://wpsimplified.in/" target="_blank" class="text-primary hover:underline">wpsimplified.in</a></li>
                                <li>YouTube: <a href="https://www.youtube.com/@WPSimplifiedBySunil" target="_blank" class="text-primary hover:underline">@WPSimplifiedBySunil</a></li>
                                <li>Instagram: <a href="https://www.instagram.com/wpsimplified/" target="_blank" class="text-primary hover:underline">@wpsimplified</a></li>
                                <li>X: <a href="https://x.com/iwpsimplified" target="_blank" class="text-primary hover:underline">@iwpsimplified</a></li>
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
                            <textarea id="pitch-body" x-model="emailBody" rows="18"
                                      class="cb-input font-mono text-xs leading-relaxed"
                                      style="white-space: pre-wrap;"></textarea>
                        </div>
                    </div>
                </x-card>

                {{-- SEPARATE SECTION: Short Bio for WordCamp Website --}}
                <div class="rounded-xl border-2 border-emerald-600/30 bg-emerald-50/40 p-5 shadow-xs">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center rounded-full bg-emerald-700 px-2.5 py-0.5 text-xs font-semibold text-white">
                                For Event Website Listing Only
                            </span>
                            <span class="text-xs font-medium text-emerald-900">
                                (Keep separate from email pitch)
                            </span>
                        </div>

                        <button type="button" x-on:click="copyBio()"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-800 focus:outline-hidden">
                            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                            </svg>
                            <span x-text="copiedBio ? 'Copied Bio!' : 'Copy Website Bio'"></span>
                        </button>
                    </div>

                    <p class="text-xs text-emerald-950/80 mb-3 leading-normal">
                        <strong>निर्देश:</strong> यह संक्षिप्त परिचय (Short Bio) पहले ईमेल में <strong>नहीं</strong> भेजा जाता है। जब आयोजक मीडिया पार्टनरशिप स्वीकार कर लेंगे और आधिकारिक वेबसाइट/पेज पर आपका विवरण मांगेंगे, तब आप इसे यहाँ से सीधे कॉपी करके भेज सकते हैं।
                    </p>

                    <div>
                        <textarea x-model="websiteBio" rows="6"
                                  class="cb-input font-sans text-xs leading-relaxed border-emerald-200 bg-white"
                                  style="white-space: pre-wrap;"></textarea>
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
                copiedBio: false,

                get subject() {
                    return `Media Partnership Proposal: ${this.eventName} × CampBuddy & WPSimplified`;
                },

                get websiteBio() {
                    return `CampBuddy (by WPSimplified) is a free, open-source (GPL-2.0) mobile companion app crafted specifically for WordCamp attendees. Designed to help first-timers and seasoned community members navigate their day effortlessly, CampBuddy features live "happening now" schedules, digital networking cards with instant LinkedIn QR codes, sponsor quests, and Contributor Day guides. Built with love by WPSimplified, an educational platform empowering WordPress creators and developers worldwide.\n\nWebsite: https://campbuddy.club/ | https://wpsimplified.in/`;
                },

                get emailBody() {
                    const phoneLine = (this.includePhone && this.applicantPhone) ? `\nWhatsApp: ${this.applicantPhone}` : '';
                    return `Dear ${this.eventName} Organizing Team,\n\n` +
`Greetings from the team at CampBuddy and WPSimplified!\n\n` +
`We are writing to officially apply for the Media Partnership for ${this.eventName}` + (this.eventDates ? ` (${this.eventDates}` + (this.eventVenue ? `, ${this.eventVenue}` : '') + `)` : '') + `.\n\n` +
`We want to keep our proposal focused, transparent, and grounded strictly in high-value deliverables that we will genuinely execute to support your attendees, speakers, and the community.\n\n` +
`### Who We Are\n` +
`* WPSimplified (https://wpsimplified.in/): A dedicated educational platform and community simplifying WordPress for freelancers, beginners, and developers through actionable video tutorials, guides, and practical insights.\n` +
`* CampBuddy (https://campbuddy.club/): A free, open-source (GPL-2.0) mobile PWA built specifically as a digital companion for WordCamp attendees. It answers the one question every first-timer asks: "What do I do next?" — featuring live schedule guidance, digital networking cards (Camp Card), sponsor quests, and Contributor Day table navigation.\n\n` +
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
`### Official Links & Social Media Handles:\n` +
`* CampBuddy Website: https://campbuddy.club/\n` +
`* CampBuddy YouTube: https://www.youtube.com/@CampBuddyClub\n` +
`* CampBuddy Instagram: https://www.instagram.com/campbuddyclub/\n` +
`* CampBuddy X: https://x.com/CampBuddyClub\n\n` +
`* WPSimplified Website: https://wpsimplified.in/\n` +
`* WPSimplified YouTube: https://www.youtube.com/@WPSimplifiedBySunil\n` +
`* WPSimplified Instagram: https://www.instagram.com/wpsimplified/\n` +
`* WPSimplified X: https://x.com/iwpsimplified\n` +
`* WPSimplified LinkedIn: https://www.linkedin.com/company/wpsimplified/\n\n` +
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
                        subject: this.subject
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

                copyBio() {
                    navigator.clipboard.writeText(this.websiteBio);
                    this.copiedBio = true;
                    setTimeout(() => { this.copiedBio = false; }, 2000);
                }
            };
        }
    </script>
</x-app-layout>
