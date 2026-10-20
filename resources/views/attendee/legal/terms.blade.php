@php
    $document = $document ?? null;
    $hasCustomContent = $document && !empty(trim($document->content ?? ''));
    $pageTitle = $document?->title ?: 'Terms of use';
    $pageDescription = $document?->meta_description ?: 'The rules for using CampBuddy, the WordCamp companion app and website.';
    $updated = $document?->updated_at ? $document->updated_at->format('d F Y') : config('campbuddy.legal.updated');
    $entity = config('campbuddy.legal.entity');
    $mail = config('campbuddy.legal.grievance_email');
@endphp

@extends('attendee.legal.layout', [
    'pageTitle' => $pageTitle,
    'pageDescription' => $pageDescription,
])

@section('content')
    <header class="legal-hero">
        <div class="legal-hero__badge legal-hero__badge--terms">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            <span>Platform Terms & Code of Conduct</span>
        </div>
        <h1 class="legal-hero__title">{{ $hasCustomContent ? $pageTitle : 'Terms of use' }}</h1>
        <p class="legal-hero__subtitle">The rules for using CampBuddy, the WordCamp companion app and website. Clear, fair, and community-first.</p>
        <div class="legal-hero__meta">
            <span class="legal-hero__meta-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Last updated {{ $updated }}
            </span>
            <span class="legal-hero__meta-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-3"/></svg>
                Governed by laws of India
            </span>
            <span class="legal-hero__meta-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                ~2 min read
            </span>
        </div>
    </header>

    @if ($hasCustomContent)
        <div class="legal-card legal-custom-content">
            {!! $document->content !!}
        </div>
    @else
        <nav class="legal-toc" aria-label="Quick jump sections">
            <a href="#intro" class="legal-toc__pill">✨ Overview</a>
            <a href="#companion" class="legal-toc__pill">1. What CampBuddy is</a>
            <a href="#changes" class="legal-toc__pill">2. Info changes</a>
            <a href="#sponsors" class="legal-toc__pill">3. Deals & sponsors</a>
            <a href="#conduct" class="legal-toc__pill">4. Meeting people</a>
            <a href="#usage" class="legal-toc__pill">5. Using service</a>
            <a href="#liability" class="legal-toc__pill">6. Liability</a>
            <a href="#contact" class="legal-toc__pill">8. Contact</a>
        </nav>

        {{-- Intro card --}}
        <section id="intro" class="legal-box" aria-label="Overview">
            <div class="legal-box__header">
                <h2 class="legal-box__title">Welcome to CampBuddy</h2>
                <span class="legal-box__badge">Agreement</span>
            </div>
            <p class="legal-p" style="margin-bottom: 10px;">CampBuddy is a free companion for WordCamp attendees, run by <strong>{{ $entity }}</strong>. By using the website or the app you agree to these terms.</p>
            <p class="legal-p" style="margin-bottom: 0;">How we handle personal data is explained in our <a href="{{ route('privacy') }}">privacy notice</a>.</p>
        </section>

        {{-- Section 1 --}}
        <section id="companion" class="legal-card">
            <div class="legal-section-header">
                <span class="legal-section-num">1</span>
                <h2 class="legal-section-title">What CampBuddy is</h2>
            </div>
            <p class="legal-p">CampBuddy shows schedules, speakers, sponsors, deals and other details of WordCamp events, and helps you plan your day and meet other attendees. Most of it comes from each WordCamp's own website and from the event's organizers. CampBuddy is independent. It is not run or endorsed by WordPress.org, the WordPress Foundation or any WordCamp team.</p>
        </section>

        {{-- Section 2 --}}
        <section id="changes" class="legal-card">
            <div class="legal-section-header">
                <span class="legal-section-num">2</span>
                <h2 class="legal-section-title">Information can change</h2>
            </div>
            <p class="legal-p">Times, rooms, speakers and venue details are taken from the event and can change. We try to keep them current, but we do not promise that they are complete or correct. Check with the event's organizers for anything important, such as travel or tickets.</p>
        </section>

        {{-- Section 3 --}}
        <section id="sponsors" class="legal-card">
            <div class="legal-section-header">
                <span class="legal-section-num">3</span>
                <h2 class="legal-section-title">Deals, free tools and sponsors</h2>
            </div>
            <p class="legal-p">Offers, coupon codes, free tools and sponsor links belong to the companies that make them. We do not sell or guarantee them, and what you agree with a company is between you and that company. If you send a deal form, your answers go to that company as explained in the privacy notice.</p>
        </section>

        {{-- Section 4 --}}
        <section id="conduct" class="legal-card">
            <div class="legal-section-header">
                <span class="legal-section-num">4</span>
                <h2 class="legal-section-title">Meeting people & Code of Conduct</h2>
            </div>
            <p class="legal-p">Attendee discovery, waves, messages and shared Camp Cards are optional. Use them kindly and honestly:</p>
            <ul class="legal-list">
                <li><strong>Do not harass, spam, impersonate or deceive anyone</strong>, and do not share other people's information.</li>
                <li><strong>Do not use CampBuddy to collect people's details for marketing.</strong></li>
                <li><strong>Meet in public places at the event</strong> and follow the WordCamp code of conduct. You are responsible for your own safety and choices.</li>
            </ul>
            <p class="legal-p">You can report a profile inside the app. We may hide a profile or remove access that breaks these rules.</p>
        </section>

        {{-- Section 5 --}}
        <section id="usage" class="legal-card">
            <div class="legal-section-header">
                <span class="legal-section-num">5</span>
                <h2 class="legal-section-title">Using the service</h2>
            </div>
            <p class="legal-p">Do not try to break, overload or reach parts of CampBuddy that are not yours, and do not copy it in bulk by automated means. We may change, pause or stop any part of CampBuddy.</p>
        </section>

        {{-- Section 6 --}}
        <section id="liability" class="legal-card">
            <div class="legal-section-header">
                <span class="legal-section-num">6</span>
                <h2 class="legal-section-title">No warranty, limited liability</h2>
            </div>
            <p class="legal-p">CampBuddy is provided "as is" and free of charge. To the extent the law allows, we are not liable for loss or damage from using it or from relying on information in it, or from what other attendees or companies do.</p>
        </section>

        {{-- Section 7 --}}
        <section id="law" class="legal-card">
            <div class="legal-section-header">
                <span class="legal-section-num">7</span>
                <h2 class="legal-section-title">Law</h2>
            </div>
            <p class="legal-p">These terms are governed by the laws of India.</p>
        </section>

        {{-- Section 8 --}}
        <section id="contact" class="legal-card">
            <div class="legal-section-header">
                <span class="legal-section-num">8</span>
                <h2 class="legal-section-title">Contact</h2>
            </div>
            <div class="legal-contact-card" style="margin-top: 0;">
                <div class="legal-contact-info">
                    <h3 class="legal-contact-title">Questions or complaints</h3>
                    <p class="legal-contact-sub">Have any questions about these terms or community guidelines? Reach out directly.</p>
                </div>
                <a href="mailto:{{ $mail }}?subject=CampBuddy" class="legal-contact-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                    <span>{{ $mail }}</span>
                </a>
            </div>
        </section>
    @endif
@endsection
