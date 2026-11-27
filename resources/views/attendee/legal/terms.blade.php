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

@section('toc')
    @if ($hasCustomContent)
        @php
            preg_match_all('/<h2[^>]*>(.*?)<\/h2>/is', \App\Support\SafeHtml::clean($document->content), $tocMatches);
        @endphp
        <a class="chip" href="#legal-doc-top"><x-attendee.line-icon name="clipboard" /> <span>Overview</span></a>
        @foreach ($tocMatches[1] ?? [] as $idx => $rawHeading)
            @php($label = trim(html_entity_decode(strip_tags($rawHeading), ENT_QUOTES, 'UTF-8')))
            @if ($label !== '')
                <a class="chip" href="#legal-sec-{{ $idx + 1 }}"><x-attendee.line-icon name="shield" /> <span>{{ \Illuminate\Support\Str::limit($label, 28) }}</span></a>
            @endif
        @endforeach
        <a class="chip" href="#legal-grievance-cta"><x-attendee.line-icon name="mail" /> <span>Data Request</span></a>
    @else
        <a class="chip" href="#intro"><x-attendee.line-icon name="sparkles" /> <span>Overview</span></a>
        <a class="chip" href="#companion"><x-attendee.line-icon name="compass" /> <span>What CampBuddy is</span></a>
        <a class="chip" href="#changes"><x-attendee.line-icon name="refresh" /> <span>Info changes</span></a>
        <a class="chip" href="#sponsors"><x-attendee.line-icon name="tag" /> <span>Deals &amp; sponsors</span></a>
        <a class="chip" href="#conduct"><x-attendee.line-icon name="users" /> <span>Meeting people</span></a>
        <a class="chip" href="#usage"><x-attendee.line-icon name="shield" /> <span>Using service</span></a>
        <a class="chip" href="#liability"><x-attendee.line-icon name="clipboard" /> <span>Liability</span></a>
        <a class="chip" href="#contact"><x-attendee.line-icon name="mail" /> <span>Contact</span></a>
    @endif
@endsection

@section('content')
    <section id="legal-doc-top" class="hero-creative" aria-labelledby="terms-hero-title">
        <nav class="hero-breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">Home</a>
            <span class="hero-breadcrumb__sep" aria-hidden="true">/</span>
            <span class="hero-breadcrumb__current" aria-current="page">Terms of use</span>
        </nav>

        <div class="hero-kicker">
            <x-attendee.line-icon name="clipboard" />
            <span>Platform Terms &amp; Code of Conduct</span>
        </div>

        <h1 id="terms-hero-title" class="hero-creative__title">
            @if ($hasCustomContent)
                {{ $pageTitle }}
            @else
                Clear, community-first <span class="hero-creative__highlight">Terms of use</span>.
            @endif
        </h1>

        <p class="hero-creative__lead">
            The rules for using CampBuddy, the WordCamp companion app and website &mdash; fair, transparent, and built for the WordPress community.
        </p>

        <div class="hero-creative__pills">
            <span class="hero-pill"><x-attendee.line-icon name="calendar" /> Updated {{ $updated }}</span>
            <span class="hero-pill"><x-attendee.line-icon name="globe" /> Laws of India</span>
            <span class="hero-pill"><x-attendee.line-icon name="clock" /> ~2 min read</span>
        </div>

        <div class="hero-creative__waves" aria-hidden="true">
            <svg viewBox="0 0 1200 120" preserveAspectRatio="none">
                <path d="M0,0 C150,90 350,-40 500,40 C650,120 900,10 1200,40 L1200,120 L0,120 Z"></path>
            </svg>
        </div>
    </section>

    @if ($hasCustomContent)
        <div class="legal-card legal-custom-content">
            {!! \App\Support\SafeHtml::clean($document->content) !!}
        </div>
    @else

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
