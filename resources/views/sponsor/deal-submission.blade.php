<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>Submit Sponsor Deal | {{ config('campbuddy.name') }}</title>
    <link rel="icon" type="image/png" sizes="32x32" href="/media/icons/favicon-32.png">
    @vite(['resources/css/fonts.css', 'resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-paper font-sans text-ink antialiased">
    <div class="min-h-screen py-10 px-4 sm:px-6 lg:px-8 max-w-6xl mx-auto"
         x-data="{
             eventId: '{{ old('event_id', $events->first()?->id ?? '') }}',
             sponsors: [],
             loadingSponsors: false,
             sponsorType: '{{ old('sponsor_custom_toggle', 'select') }}',
             sponsorName: '{{ old('sponsor_name', '') }}',
             brand: '{{ old('brand', '') }}',
             website: '{{ old('website', '') }}',
             title: '{{ old('title', '') }}',
             highlight: '{{ old('highlight', '') }}',
             description: '{{ old('description', '') }}',
             terms: '{{ old('terms', '') }}',
             couponCode: '{{ old('coupon_code', '') }}',
             ctaLabel: '{{ old('cta_label', 'Get the deal') }}',
             icon: '{{ old('icon', '🏷') }}',
             url: '{{ old('url', '') }}',
             logoUrl: '{{ old('logo_url', '') }}',
             imageError: null,

             onImageChange(e) {
                 const file = e.target.files[0];
                 this.imageError = null;
                 if (!file) return;

                 if (file.type !== 'image/png' && !file.name.toLowerCase().endsWith('.png')) {
                     this.imageError = 'Only PNG (.png) images are allowed.';
                     e.target.value = '';
                     return;
                 }

                 if (file.size > 102400) {
                     this.imageError = 'Logo image exceeds 100 KB limit (' + (file.size / 1024).toFixed(1) + ' KB). Please select a smaller PNG.';
                     e.target.value = '';
                     return;
                 }

                 const reader = new FileReader();
                 reader.onload = (evt) => {
                     this.logoUrl = evt.target.result;
                 };
                 reader.readAsDataURL(file);
             },

             async fetchSponsors(id) {
                 if (!id) {
                     this.sponsors = [];
                     return;
                 }
                 this.loadingSponsors = true;
                 try {
                     const res = await fetch(`/sponsor-deals/events/${id}/sponsors`);
                     if (res.ok) {
                         const json = await res.json();
                         this.sponsors = json.sponsors || [];
                     }
                 } catch (e) {
                     console.error(e);
                 } finally {
                     this.loadingSponsors = false;
                 }
             },

             onSponsorSelect(e) {
                 const name = e.target.value;
                 if (name === '__custom__') {
                     this.sponsorType = 'custom';
                     this.sponsorName = '';
                     return;
                 }
                 this.sponsorName = name;
                 if (!this.brand || this.brand === '') {
                     this.brand = name;
                 }
                 const match = this.sponsors.find(s => s.name === name);
                 if (match) {
                     if (match.website && (!this.website || this.website === '')) {
                         try {
                             const u = new URL(match.website.startsWith('http') ? match.website : 'https://' + match.website);
                             this.website = u.hostname.replace(/^www\./, '');
                         } catch (_) {
                             this.website = match.website;
                         }
                     }
                     if (match.logo_url) {
                         this.logoUrl = match.logo_url;
                     }
                 }
             },

             init() {
                 if (this.eventId) {
                     this.fetchSponsors(this.eventId);
                 }
             }
         }">

        {{-- Header / Brand --}}
        <header class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-line pb-6 mb-8 gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ url('/') }}" class="shrink-0">
                    <img src="/media/icons/icon-192.png" alt="{{ config('app.name') }}" class="h-10 w-10 rounded-xl shadow-xs">
                </a>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold tracking-wide text-maroon-text uppercase">{{ config('app.name') }}</span>
                        <span class="text-xs px-2 py-0.5 rounded-full bg-navy/10 text-navy font-medium">Sponsor Portal</span>
                    </div>
                    <h1 class="text-2xl font-bold tracking-tight text-ink sm:text-3xl">Submit Your WordCamp Deal</h1>
                </div>
            </div>

            <div class="text-xs text-muted max-w-xs sm:text-right">
                <span class="inline-flex items-center gap-1.5 text-teal font-medium">
                    <x-icon name="shield-check" class="h-4 w-4" /> Unlisted & Verified Portal
                </span>
                <p class="mt-0.5">Attendee deals are held in staging until verified by CampBuddy administrators.</p>
            </div>
        </header>

        {{-- Global Validation Errors --}}
        @if ($errors->any())
            <div class="mb-6 rounded-xl border border-danger/30 bg-danger-soft p-4 text-sm text-danger">
                <div class="flex items-center gap-2 font-semibold mb-2">
                    <x-icon name="exclamation-circle" class="h-5 w-5 shrink-0" />
                    <span>Please review the errors below:</span>
                </div>
                <ul class="list-disc list-inside space-y-1 text-xs">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            {{-- Form Column --}}
            <div class="lg:col-span-7 space-y-6">
                <form method="POST" action="{{ route('sponsor-deals.store') }}" enctype="multipart/form-data" class="space-y-6">
                    @csrf

                    {{-- Honeypot bot protection field (invisible to real visitors) --}}
                    <div style="display:none; position:absolute; left:-9999px;" aria-hidden="true">
                        <input type="text" name="hp_organization_token" value="" tabindex="-1" autocomplete="off" />
                        <input type="hidden" name="sponsor_custom_toggle" x-bind:value="sponsorType" />
                    </div>

                    {{-- Encrypted Time-gate Token --}}
                    <input type="hidden" name="submission_token" value="{{ $submissionToken }}" />
                    <input type="hidden" name="logo_url" x-model="logoUrl" />

                    {{-- Step 1 & 2: Event and Sponsor Selection --}}
                    <div class="rounded-xl border border-line bg-white p-6 shadow-xs space-y-5">
                        <div class="flex items-center gap-2 border-b border-line pb-3">
                            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-maroon-text text-xs font-bold text-white">1</span>
                            <h2 class="text-base font-semibold text-ink">Select WordCamp Event & Sponsor</h2>
                        </div>

                        <div>
                            <label for="event_id" class="block text-xs font-semibold uppercase tracking-wider text-muted mb-1.5">
                                Select Event <span class="text-danger">*</span>
                            </label>
                            <select id="event_id" name="event_id" required
                                    x-model="eventId"
                                    x-on:change="fetchSponsors($event.target.value); sponsorName = ''; brand = '';"
                                    class="w-full rounded-lg border border-line bg-paper-soft px-3.5 py-2.5 text-sm font-medium text-ink focus:border-maroon focus:outline-hidden focus:ring-2 focus:ring-maroon/20">
                                <option value="">-- Choose WordCamp Event --</option>
                                @foreach ($events as $event)
                                    <option value="{{ $event->id }}" @selected(old('event_id') == $event->id)>
                                        {{ $event->display_name }}
                                        @if ($event->starts_on)
                                            ({{ $event->starts_on->format('M Y') }})
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-muted">Select the specific WordCamp edition your company is sponsoring.</p>
                        </div>

                        {{-- Sponsor selection dropdown / custom name --}}
                        <div x-show="eventId" x-cloak>
                            <label for="sponsor_select" class="block text-xs font-semibold uppercase tracking-wider text-muted mb-1.5">
                                Select Your Sponsor Name <span class="text-danger">*</span>
                            </label>

                            <div class="relative">
                                <select id="sponsor_select"
                                        x-show="sponsorType === 'select'"
                                        x-on:change="onSponsorSelect($event)"
                                        class="w-full rounded-lg border border-line bg-paper-soft px-3.5 py-2.5 text-sm font-medium text-ink focus:border-maroon focus:outline-hidden focus:ring-2 focus:ring-maroon/20">
                                    <option value="">-- Select your company from event sponsors --</option>
                                    <template x-for="item in sponsors" :key="item.name">
                                        <option :value="item.name" x-text="item.name" :selected="sponsorName === item.name"></option>
                                    </template>
                                    <option value="__custom__">&plus; Other / Sponsor name not listed...</option>
                                </select>

                                <div x-show="loadingSponsors" class="text-xs text-muted mt-1 flex items-center gap-1.5">
                                    <x-icon name="arrow-path" class="h-3.5 w-3.5 animate-spin text-maroon-text" />
                                    <span>Fetching event sponsors...</span>
                                </div>
                            </div>

                            {{-- Custom sponsor name if not found in list --}}
                            <div x-show="sponsorType === 'custom'" x-cloak class="mt-3 space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-muted">Enter sponsor / company name:</span>
                                    <button type="button" x-on:click="sponsorType = 'select'" class="text-xs text-maroon-text hover:underline">
                                        &larr; Choose from ingested sponsor list
                                    </button>
                                </div>
                                <input type="text" x-model="sponsorName" name="sponsor_name"
                                       placeholder="e.g. Acme Cloud Solutions"
                                       class="w-full rounded-lg border border-line bg-paper-soft px-3.5 py-2.5 text-sm text-ink focus:border-maroon focus:outline-hidden focus:ring-2 focus:ring-maroon/20" />
                            </div>

                            {{-- Hidden input for sponsor_name if select is active --}}
                            <input type="hidden" name="sponsor_name" x-bind:value="sponsorName" x-show="sponsorType === 'select'" />
                        </div>
                    </div>

                    {{-- Step 3: Deal Details --}}
                    <div class="rounded-xl border border-line bg-white p-6 shadow-xs space-y-5">
                        <div class="flex items-center gap-2 border-b border-line pb-3">
                            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-maroon-text text-xs font-bold text-white">2</span>
                            <h2 class="text-base font-semibold text-ink">Deal Information</h2>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="brand" class="block text-xs font-semibold uppercase tracking-wider text-muted mb-1.5">
                                    Brand / Product Name
                                </label>
                                <input type="text" id="brand" name="brand" x-model="brand" maxlength="120"
                                       placeholder="e.g. Hostinger, Jetpack"
                                       class="w-full rounded-lg border border-line bg-paper-soft px-3.5 py-2 text-sm text-ink focus:border-maroon focus:outline-hidden focus:ring-2 focus:ring-maroon/20" />
                                <p class="mt-1 text-xs text-muted">Header brand title on the deal card.</p>
                            </div>

                            <div>
                                <label for="website" class="block text-xs font-semibold uppercase tracking-wider text-muted mb-1.5">
                                    Sponsor Website
                                </label>
                                <input type="text" id="website" name="website" x-model="website" maxlength="120"
                                       placeholder="e.g. hostinger.com"
                                       class="w-full rounded-lg border border-line bg-paper-soft px-3.5 py-2 text-sm text-ink focus:border-maroon focus:outline-hidden focus:ring-2 focus:ring-maroon/20" />
                                <p class="mt-1 text-xs text-muted">Domain displayed under brand name.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div class="sm:col-span-2">
                                <label for="title" class="block text-xs font-semibold uppercase tracking-wider text-muted mb-1.5">
                                    Offer Headline / Title <span class="text-danger">*</span>
                                </label>
                                <input type="text" id="title" name="title" required x-model="title" maxlength="120"
                                       placeholder="e.g. 30% off all hosting & cloud plans"
                                       class="w-full rounded-lg border border-line bg-paper-soft px-3.5 py-2 text-sm text-ink focus:border-maroon focus:outline-hidden focus:ring-2 focus:ring-maroon/20" />
                            </div>

                            <div>
                                <label for="highlight" class="block text-xs font-semibold uppercase tracking-wider text-muted mb-1.5">
                                    Highlight Tag
                                </label>
                                <input type="text" id="highlight" name="highlight" x-model="highlight" maxlength="40"
                                       placeholder="e.g. 30% OFF, FREE"
                                       class="w-full rounded-lg border border-line bg-paper-soft px-3.5 py-2 text-sm text-ink focus:border-maroon focus:outline-hidden focus:ring-2 focus:ring-maroon/20" />
                            </div>
                        </div>

                        <div>
                            <label for="description" class="block text-xs font-semibold uppercase tracking-wider text-muted mb-1.5">
                                Deal Description / What Attendees Get <span class="text-danger">*</span>
                            </label>
                            <textarea id="description" name="description" required rows="3" x-model="description" maxlength="500"
                                      placeholder="Explain what the attendee gets, how to use the coupon, and any perk details. Up to 500 characters."
                                      class="w-full rounded-lg border border-line bg-paper-soft px-3.5 py-2 text-sm text-ink focus:border-maroon focus:outline-hidden focus:ring-2 focus:ring-maroon/20"></textarea>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="url" class="block text-xs font-semibold uppercase tracking-wider text-muted mb-1.5">
                                    Deal Landing Page / Link <span class="text-danger">*</span>
                                </label>
                                <input type="url" id="url" name="url" required x-model="url" maxlength="500"
                                       placeholder="https://yourbrand.com/wordcamp-perk"
                                       class="w-full rounded-lg border border-line bg-paper-soft px-3.5 py-2 text-sm text-ink focus:border-maroon focus:outline-hidden focus:ring-2 focus:ring-maroon/20" />
                                <p class="mt-1 text-xs text-muted">Destination when attendees click the offer button.</p>
                            </div>

                            <div>
                                <label for="coupon_code" class="block text-xs font-semibold uppercase tracking-wider text-muted mb-1.5">
                                    Coupon / Promo Code
                                </label>
                                <input type="text" id="coupon_code" name="coupon_code" x-model="couponCode" maxlength="60"
                                       placeholder="e.g. WCDELHI26"
                                       class="w-full rounded-lg border border-line bg-paper-soft px-3.5 py-2 text-sm font-mono text-ink focus:border-maroon focus:outline-hidden focus:ring-2 focus:ring-maroon/20" />
                                <p class="mt-1 text-xs text-muted">Rendered with a 1-tap Copy button.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div class="sm:col-span-2">
                                <label for="terms" class="block text-xs font-semibold uppercase tracking-wider text-muted mb-1.5">
                                    Terms & Validity
                                </label>
                                <input type="text" id="terms" name="terms" x-model="terms" maxlength="255"
                                       placeholder="e.g. Valid until Nov 30, 2026. For new customers only."
                                       class="w-full rounded-lg border border-line bg-paper-soft px-3.5 py-2 text-sm text-ink focus:border-maroon focus:outline-hidden focus:ring-2 focus:ring-maroon/20" />
                            </div>

                            <div>
                                <label for="cta_label" class="block text-xs font-semibold uppercase tracking-wider text-muted mb-1.5">
                                    Button Label
                                </label>
                                <input type="text" id="cta_label" name="cta_label" x-model="ctaLabel" maxlength="40"
                                       placeholder="Get the deal"
                                       class="w-full rounded-lg border border-line bg-paper-soft px-3.5 py-2 text-sm text-ink focus:border-maroon focus:outline-hidden focus:ring-2 focus:ring-maroon/20" />
                            </div>
                        </div>

                        <div>
                            <label for="logo_image" class="block text-xs font-semibold uppercase tracking-wider text-muted mb-1.5">
                                Logo / Deal Image <span class="text-xs font-bold text-maroon-text">(PNG only, max 100 KB)</span>
                            </label>
                            <input type="file" id="logo_image" name="logo_image" accept=".png,image/png"
                                   x-on:change="onImageChange($event)"
                                   class="block w-full text-xs text-muted file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-maroon-text file:text-white hover:file:bg-maroon-text-dark file:cursor-pointer border border-line rounded-lg bg-paper-soft p-1.5 focus:outline-hidden" />
                            <p class="mt-1 text-xs text-muted">Upload a square or transparent PNG logo (maximum 100 KB). This image will appear on the deal ticket.</p>
                            <template x-if="imageError">
                                <p class="mt-1.5 text-xs text-danger font-medium flex items-center gap-1">
                                    <x-icon name="exclamation-circle" class="h-3.5 w-3.5 shrink-0" />
                                    <span x-text="imageError"></span>
                                </p>
                            </template>
                        </div>
                    </div>

                    {{-- Step 4: Submitter Verification & Authenticity --}}
                    <div class="rounded-xl border border-line bg-white p-6 shadow-xs space-y-5">
                        <div class="flex items-center gap-2 border-b border-line pb-3">
                            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-maroon-text text-xs font-bold text-white">3</span>
                            <h2 class="text-base font-semibold text-ink">Submitter Verification Details</h2>
                        </div>

                        <div class="rounded-lg bg-paper-soft p-3.5 border border-line text-xs text-muted flex items-start gap-2.5">
                            <x-icon name="information-circle" class="h-4 w-4 text-maroon-text shrink-0 mt-0.5" />
                            <span>
                                <strong>Authenticity Check:</strong> To prevent spoofed or unauthorized submissions, please provide your corporate contact details.
                                Submissions from corporate email addresses matching the sponsor brand domain are verified immediately.
                            </span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="submitter_name" class="block text-xs font-semibold uppercase tracking-wider text-muted mb-1.5">
                                    Your Full Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" id="submitter_name" name="submitter_name" required value="{{ old('submitter_name') }}" maxlength="191"
                                       placeholder="e.g. Rahul Sharma"
                                       class="w-full rounded-lg border border-line bg-paper-soft px-3.5 py-2 text-sm text-ink focus:border-maroon focus:outline-hidden focus:ring-2 focus:ring-maroon/20" />
                            </div>

                            <div>
                                <label for="submitter_email" class="block text-xs font-semibold uppercase tracking-wider text-muted mb-1.5">
                                    Work Email Address <span class="text-danger">*</span>
                                </label>
                                <input type="email" id="submitter_email" name="submitter_email" required value="{{ old('submitter_email') }}" maxlength="191"
                                       placeholder="e.g. rahul@hostinger.com"
                                       class="w-full rounded-lg border border-line bg-paper-soft px-3.5 py-2 text-sm text-ink focus:border-maroon focus:outline-hidden focus:ring-2 focus:ring-maroon/20" />
                                <p class="mt-1 text-xs text-muted">Must be an official corporate/work email.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="submitter_phone" class="block text-xs font-semibold uppercase tracking-wider text-muted mb-1.5">
                                    Phone / WhatsApp (Optional)
                                </label>
                                <input type="text" id="submitter_phone" name="submitter_phone" value="{{ old('submitter_phone') }}" maxlength="60"
                                       placeholder="+91 98765 43210"
                                       class="w-full rounded-lg border border-line bg-paper-soft px-3.5 py-2 text-sm text-ink focus:border-maroon focus:outline-hidden focus:ring-2 focus:ring-maroon/20" />
                            </div>

                            <div>
                                <label for="notes" class="block text-xs font-semibold uppercase tracking-wider text-muted mb-1.5">
                                    Note to Admin (Optional)
                                </label>
                                <input type="text" id="notes" name="notes" value="{{ old('notes') }}" maxlength="500"
                                       placeholder="e.g. Gold sponsor booth #4"
                                       class="w-full rounded-lg border border-line bg-paper-soft px-3.5 py-2 text-sm text-ink focus:border-maroon focus:outline-hidden focus:ring-2 focus:ring-maroon/20" />
                            </div>
                        </div>
                    </div>

                    {{-- Submission action --}}
                    <div class="pt-2">
                        <button type="submit"
                                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-maroon-text px-8 py-3.5 text-sm font-semibold text-white shadow-xs hover:bg-maroon-text-dark focus:outline-hidden focus-visible:ring-2 focus-visible:ring-maroon/60 transition-colors">
                            <x-icon name="paper-airplane" class="h-4 w-4" />
                            <span>Submit Deal for Review & Verification</span>
                        </button>
                        <p class="mt-2.5 text-xs text-muted">
                            By submitting, you confirm that you are authorized to offer this promotion on behalf of the sponsor.
                        </p>
                    </div>
                </form>
            </div>

            {{-- Live Deal Card Preview Column (Sticky) --}}
            <div class="lg:col-span-5 sticky top-8">
                <div class="rounded-2xl border border-line bg-white p-6 shadow-xs space-y-4">
                    <div class="flex items-center justify-between border-b border-line pb-3">
                        <div class="flex items-center gap-2">
                            <x-icon name="device-phone" class="h-5 w-5 text-muted" />
                            <h3 class="text-sm font-semibold text-ink">Live Attendee Preview</h3>
                        </div>
                        <span class="text-xs px-2 py-0.5 rounded-full bg-paper text-muted font-mono">Explore &rarr; Deals</span>
                    </div>

                    <p class="text-xs text-muted leading-relaxed">
                        This is a real-time preview of how your offer will appear inside the CampBuddy attendee web & mobile app.
                    </p>

                    {{-- CampBuddy Ticket Style Card Preview --}}
                    <div class="rounded-xl border border-line/80 bg-paper-soft p-5 shadow-xs overflow-hidden">
                        {{-- Head --}}
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white border border-line shadow-2xs overflow-hidden">
                                    <template x-if="logoUrl">
                                        <img :src="logoUrl" alt="" class="h-full w-full object-contain p-1" />
                                    </template>
                                    <template x-if="!logoUrl">
                                        <span class="text-xl" x-text="icon || '🏷'"></span>
                                    </template>
                                </div>
                                <div class="min-w-0">
                                    <span class="block truncate text-sm font-bold text-ink" x-text="brand || sponsorName || 'Your Brand Name'"></span>
                                    <span class="block truncate text-xs text-muted" x-text="website || 'sponsor.com'"></span>
                                </div>
                            </div>

                            <div x-show="highlight" x-cloak>
                                <span class="inline-block rounded-md bg-gold/20 px-2.5 py-1 text-xs font-bold uppercase tracking-wider text-ink border border-gold/40"
                                      x-text="highlight"></span>
                            </div>
                        </div>

                        {{-- Title --}}
                        <h4 class="mt-4 text-base font-bold text-ink leading-snug"
                            x-text="title || 'Your Deal Headline (e.g. 30% discount on hosting)'"></h4>

                        {{-- Perforation line --}}
                        <div class="my-3.5 border-t border-dashed border-line"></div>

                        {{-- Description --}}
                        <p class="text-xs text-muted leading-relaxed whitespace-pre-line"
                           x-text="description || 'Detailed description of the offer, perks, and how attendees can redeem it at your WordCamp booth or website.'"></p>

                        {{-- Terms --}}
                        <div x-show="terms" x-cloak class="mt-2 text-[11px] text-muted italic">
                            <span>* </span><span x-text="terms"></span>
                        </div>

                        {{-- Coupon Code if present --}}
                        <div x-show="couponCode" x-cloak class="mt-4 flex items-center justify-between gap-2 rounded-lg bg-white border border-line px-3 py-2">
                            <div class="min-w-0">
                                <span class="block text-[10px] uppercase font-semibold text-muted">Promo Code</span>
                                <code class="text-xs font-mono font-bold text-maroon-text" x-text="couponCode"></code>
                            </div>
                            <span class="text-xs font-medium px-2 py-1 rounded bg-paper text-muted border border-line">Copy</span>
                        </div>

                        {{-- CTA Button --}}
                        <div class="mt-4">
                            <div class="flex items-center justify-center gap-1.5 w-full rounded-xl bg-navy py-2.5 px-4 text-xs font-semibold text-white shadow-xs">
                                <span x-text="ctaLabel || 'Get the deal'"></span>
                                <span>&rarr;</span>
                            </div>
                        </div>
                    </div>

                    <div class="text-center pt-2">
                        <span class="text-[11px] text-muted">
                            CampBuddy ensures verified deals only. No spam.
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
