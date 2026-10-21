@php
    $document = $document ?? null;
    $hasCustomContent = $document && !empty(trim($document->content ?? ''));
    $pageTitle = $document?->title ?: 'Privacy notice';
    $pageDescription = $document?->meta_description ?: 'What personal data CampBuddy keeps, why, for how long, and how to see, correct or erase it. Written under India\'s Digital Personal Data Protection Act, 2023.';
    $updated = $document?->updated_at ? $document->updated_at->format('d F Y') : config('campbuddy.legal.updated');
    $entity = config('campbuddy.legal.entity');
    $mail = config('campbuddy.legal.grievance_email');
    $days = config('campbuddy.legal.reply_days');
    $mailto = fn (string $subject) => 'mailto:'.$mail.'?subject='.rawurlencode($subject);
@endphp

@extends('attendee.legal.layout', [
    'pageTitle' => $pageTitle,
    'pageDescription' => $pageDescription,
])

@section('content')
    <header class="legal-hero">
        <div class="legal-hero__badge">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            <span>DPDP Act 2023 Compliant</span>
        </div>
        <h1 class="legal-hero__title">{{ $hasCustomContent ? $pageTitle : 'Privacy notice' }}</h1>
        <p class="legal-hero__subtitle">What personal data CampBuddy keeps, why, for how long, and how to see, correct or erase it. Built privacy-first with local data control.</p>
        <div class="legal-hero__meta">
            <span class="legal-hero__meta-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Last updated {{ $updated }}
            </span>
            <span class="legal-hero__meta-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                Written under India's Digital Personal Data Protection Act, 2023 (DPDP Act)
            </span>
            <span class="legal-hero__meta-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                ~3 min read
            </span>
        </div>
    </header>

    @if ($hasCustomContent)
        <div class="legal-card legal-custom-content">
            {!! $document->content !!}
        </div>
    @else
        <nav class="legal-toc" aria-label="Quick jump sections">
            <a href="#summary" class="legal-toc__pill">⚡ The short version</a>
            <a href="#responsible" class="legal-toc__pill">1. Who is responsible</a>
            <a href="#data-table" class="legal-toc__pill">2. What we keep</a>
            <a href="#third-parties" class="legal-toc__pill">3. Who sees it</a>
            <a href="#rights" class="legal-toc__pill">4. Your rights</a>
            <a href="#grievance" class="legal-toc__pill">5. Grievance & email</a>
            <a href="#hindi" class="legal-toc__pill">🇮🇳 सारांश (Hindi)</a>
        </nav>

        {{-- The short version card --}}
        <section id="summary" class="legal-box" aria-label="Summary">
            <div class="legal-box__header">
                <h2 class="legal-box__title">The short version</h2>
                <span class="legal-box__badge">Key Highlights</span>
            </div>
            <ul class="legal-box__list">
                <li class="legal-box__item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                    <span>You do not need an account. Your saved sessions, quests, notes, Camp Card and answers stay <strong>on your phone</strong>. We never receive them.</span>
                </li>
                <li class="legal-box__item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                    <span>Something reaches our servers only when <strong>you choose</strong> to: join attendee discovery, share your Camp Card, send a deal form, or switch on notifications.</span>
                </li>
                <li class="legal-box__item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                    <span>We do not sell your data, show ads, or build a profile of you. Nothing you type is sent to analytics.</span>
                </li>
                <li class="legal-box__item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                    <span>You can take it back at any time, inside the app or by writing to <a href="{{ $mailto('CampBuddy data request') }}">{{ $mail }}</a>.</span>
                </li>
            </ul>
        </section>

        {{-- Section 1 --}}
        <section id="responsible" class="legal-card">
            <div class="legal-section-header">
                <span class="legal-section-num">1</span>
                <h2 class="legal-section-title">Who is responsible</h2>
            </div>
            <p class="legal-p">CampBuddy is run by <strong>{{ $entity }}</strong> (the "Data Fiduciary" under the DPDP Act, "we" below). CampBuddy is an independent companion for WordCamp attendees. It is not run by WordPress.org, the WordPress Foundation or any WordCamp organizing team, although organizers can edit parts of their own event's page.</p>
            <p class="legal-p">Contact for everything in this notice: <a href="{{ $mailto('CampBuddy privacy') }}">{{ $mail }}</a>.</p>
        </section>

        {{-- Section 2 --}}
        <section id="data-table" class="legal-card">
            <div class="legal-section-header">
                <span class="legal-section-num">2</span>
                <h2 class="legal-section-title">What we keep, why, and for how long</h2>
            </div>
            <p class="legal-p">We use your data only for the reasons stated below. We ask for your consent each time you start one of the sharing features, and the app tells you what is shared before you share it.</p>

            <div class="legal-table-container">
                <table class="legal-table">
                    <thead>
                        <tr>
                            <th scope="col" style="width: 32%;">What</th>
                            <th scope="col" style="width: 30%;">Why</th>
                            <th scope="col" style="width: 38%;">Where, and how long</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td data-label="What"><strong>Your own data</strong>: saved sessions and attendance, quest ticks, notes on people you want to meet, your Camp Card, your answers to "Make CampBuddy yours", reminder choices.</td>
                            <td data-label="Why">To give you your schedule, plan and Camp Card.</td>
                            <td data-label="Where, and how long"><strong>Only on your phone.</strong> Not sent to us. Kept until you clear it (Your data &rarr; Clear my data) or uninstall the app.</td>
                        </tr>
                        <tr>
                            <td data-label="What"><strong>Attendee discovery profile</strong>, only if you tap "Join attendee discovery": the name you choose to show (or none, to stay anonymous), the interests you tick, a profession and "who I'd like to meet" line if you add them, and a random ID. If you wave at someone or message them: the wave, your first name if you give it, and up to three short messages.</td>
                            <td data-label="Why">So people with shared interests can find each other at the event.</td>
                            <td data-label="Where, and how long">Our server. Other people in discovery for the same event can see the fields you chose. Removed when you leave discovery (one tap), or <strong>deleted about 90 days after the event</strong>.</td>
                        </tr>
                        <tr>
                            <td data-label="What"><strong>Shared Camp Card</strong>, only if you switch on "Show my Camp Card on the attendee list": the fields you chose to show.</td>
                            <td data-label="Why">So people who tap your name in Explore &rarr; People can see and save your card.</td>
                            <td data-label="Where, and how long">Our server, shown to other attendees of that event. Removed when you switch it off, or <strong>deleted about 90 days after the event</strong>.</td>
                        </tr>
                        <tr>
                            <td data-label="What"><strong>Deal forms</strong>, only if you submit one: the name, company, email, mobile number and choices that form asks for.</td>
                            <td data-label="Why">So the sponsor of that offer can contact you about it. We pass your answers to that sponsor.</td>
                            <td data-label="Where, and how long">Our server and the sponsor. We <strong>delete our copy about 90 days after the event</strong>. The sponsor keeps their own copy under their own privacy policy; ask them or us to help.</td>
                        </tr>
                        <tr>
                            <td data-label="What"><strong>Notifications</strong>, only if you allow them: a notification address from Google (Firebase) or your browser, a random device ID, the sessions you asked to be reminded about, the app version, and which WordCamps you opened from three days before the event (and when).</td>
                            <td data-label="Why">To send session reminders, a few event updates and the organizers' live announcements during the event ("Lunch is served").</td>
                            <td data-label="Where, and how long">Our server. Removed when you turn notifications off (or the address stops working), or <strong>deleted about 90 days after the event</strong>.</td>
                        </tr>
                        <tr>
                            <td data-label="What"><strong>Feedback</strong>, only if you send it: a star rating and an optional comment, linked to a random code (not your name).</td>
                            <td data-label="Why">So organizers can learn what worked.</td>
                            <td data-label="Where, and how long">Our server, kept for organizers. Ask us to remove yours any time.</td>
                        </tr>
                        <tr>
                            <td data-label="What"><strong>Reports</strong>, only if you report someone in discovery: your reason and note, and a random device ID.</td>
                            <td data-label="Why">To look into misuse.</td>
                            <td data-label="Where, and how long">Our server, until the report is dealt with and the event's data is deleted.</td>
                        </tr>
                        <tr>
                            <td data-label="What"><strong>The public attendee list</strong>: name, photo and links people chose to show on their own WordCamp's Attendees page.</td>
                            <td data-label="Why">So you can find and meet people. We copy only what that page already shows publicly.</td>
                            <td data-label="Where, and how long">Our server. Anyone can ask for their entry to be hidden: write to us, or use the event's own removal page (campbuddy.club/event/&lt;event&gt;/roster-removal).</td>
                        </tr>
                        <tr>
                            <td data-label="What"><strong>Technical records</strong>: your IP address, device type and the time of requests, in our web host's and Cloudflare's logs.</td>
                            <td data-label="Why">Security, abuse prevention and keeping the service running.</td>
                            <td data-label="Where, and how long">Our host and Cloudflare, kept for a short time under their own settings.</td>
                        </tr>
                        <tr>
                            <td data-label="What"><strong>Website visit statistics</strong> (Google Analytics, website only): which pages and buttons are used. No names, no text you type, no discovery or Camp Card data, no query strings.</td>
                            <td data-label="Why">To improve CampBuddy.</td>
                            <td data-label="Where, and how long">Google Analytics. We do not load it at all if your browser sends "Do Not Track" or "Global Privacy Control". <strong>The Android and iPhone apps contain no analytics</strong>.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Section 3 --}}
        <section id="third-parties" class="legal-card">
            <div class="legal-section-header">
                <span class="legal-section-num">3</span>
                <h2 class="legal-section-title">Who else sees it</h2>
            </div>
            <ul class="legal-list">
                <li><strong>Other attendees</strong>, only what you put in discovery or on a shared Camp Card.</li>
                <li><strong>The sponsor</strong> of a deal, only if you submit that deal's form.</li>
                <li><strong>Our service providers</strong>, who process data for us: our web host, Cloudflare (network and security), Google (Firebase Cloud Messaging for notifications; Google Analytics on the website) and the browser or phone maker's push service. Some of them are outside India.</li>
                <li><strong>Authorities</strong>, if the law requires it.</li>
            </ul>
            <p class="legal-p">We do not sell personal data and we do not show advertising. We do not track or profile anyone's behaviour, and we do not target anything at children.</p>
        </section>

        {{-- Section 4 --}}
        <section id="rights" class="legal-card">
            <div class="legal-section-header">
                <span class="legal-section-num">4</span>
                <h2 class="legal-section-title">Your rights</h2>
            </div>
            <p class="legal-p">Under the DPDP Act 2023, you have full control over your personal data:</p>

            <div class="legal-rights-grid">
                <div class="legal-right-item">
                    <div class="legal-right-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    </div>
                    <div class="legal-right-text">
                        <strong>Right to Access & Know</strong>
                        <span>Know what of yours we hold and who we shared it with, and get a copy.</span>
                    </div>
                </div>

                <div class="legal-right-item">
                    <div class="legal-right-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </div>
                    <div class="legal-right-text">
                        <strong>Right to Correction</strong>
                        <span>Correct or complete inaccurate or outdated information.</span>
                    </div>
                </div>

                <div class="legal-right-item">
                    <div class="legal-right-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    </div>
                    <div class="legal-right-text">
                        <strong>Right to Erasure</strong>
                        <span>Erase and delete your personal data permanently.</span>
                    </div>
                </div>

                <div class="legal-right-item">
                    <div class="legal-right-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 5.5 5.5v0a5.5 5.5 0 0 1-5.5 5.5H11"/></svg>
                    </div>
                    <div class="legal-right-text">
                        <strong>Withdraw consent</strong>
                        <span>Withdraw consent at any time. It is as easy as giving it: leave discovery or switch off notifications.</span>
                    </div>
                </div>

                <div class="legal-right-item">
                    <div class="legal-right-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    </div>
                    <div class="legal-right-text">
                        <strong>Right to Grievance Redressal</strong>
                        <span>Complain and have it answered within <strong>{{ $days }} days</strong>.</span>
                    </div>
                </div>

                <div class="legal-right-item">
                    <div class="legal-right-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                    <div class="legal-right-text">
                        <strong>Right to Nominate</strong>
                        <span>Nominate someone to exercise these rights for you if you cannot.</span>
                    </div>
                </div>
            </div>

            <h3 class="legal-section-subtitle">How to exercise inside the app (instant)</h3>
            <ul class="legal-list">
                <li><strong>My Day &rarr; Your data</strong>: Save everything as a PDF or JSON, or clear all data from your phone.</li>
                <li><strong>Explore &rarr; People</strong>: Leave attendee discovery with one tap; switch Camp Card sharing off anytime.</li>
                <li><strong>Phone Settings</strong>: Turn notifications off whenever you want.</li>
            </ul>

            {{-- Grievance box --}}
            <div id="grievance" class="legal-contact-card">
                <div class="legal-contact-info">
                    <h3 class="legal-contact-title">By email & Grievance Redressal</h3>
                    <p class="legal-contact-sub">For anything else, write to our designated Grievance Officer. <strong>We reply within {{ $days }} days.</strong></p>
                    <p class="legal-contact-sub" style="margin-top: 6px; font-size: 0.82rem;">If we do not resolve your complaint, you can complain to the <strong>Data Protection Board of India</strong>.</p>
                </div>
                <a href="{{ $mailto('CampBuddy data request') }}" class="legal-contact-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                    <span>Contact Grievance Officer</span>
                </a>
            </div>
        </section>

        {{-- Section 5 --}}
        <section class="legal-card">
            <div class="legal-section-header">
                <span class="legal-section-num">5</span>
                <h2 class="legal-section-title">Children</h2>
            </div>
            <p class="legal-p">CampBuddy is a general guide to WordCamp events. We do not knowingly ask for personal information from anyone under 18, and we do not track their behaviour or target anything at them. The schedule, guide and quests work without sharing anything. If you are under 18, please ask a parent or guardian before using attendee discovery, Camp Card sharing, deal forms or notifications. A parent or guardian who wants a child's data erased can write to us and we will do it.</p>
        </section>

        {{-- Section 6 & 7 --}}
        <section class="legal-card">
            <div class="legal-section-header">
                <span class="legal-section-num">6</span>
                <h2 class="legal-section-title">Security & Changes</h2>
            </div>
            <p class="legal-p">Everything travels over HTTPS. Discovery and Camp Card ownership codes are stored only as hashes. Admin and organizer areas need sign-in, and we limit how fast anyone can call our servers. If a breach affecting your personal data ever happens, we will tell you and the Data Protection Board of India as the law requires.</p>
            <p class="legal-p">If this notice changes in a way that matters, we will update the date at the top and say so in the app. Older notices are not kept, but the date always shows which one you are reading.</p>
        </section>

        {{-- Section 8: Hindi Summary --}}
        <section id="hindi" class="legal-hindi-box">
            <div class="legal-hindi-title">
                <span>🇮🇳</span>
                <h2 style="font-size: 1.15rem; font-weight: 800; margin: 0;">Hindi summary (सारांश)</h2>
            </div>
            <p>CampBuddy के लिए आपको खाता नहीं बनाना पड़ता। आपके सेव किए सत्र, नोट्स, Camp Card और जवाब सिर्फ़ आपके फ़ोन में रहते हैं। हमारे सर्वर तक डेटा तभी पहुँचता है जब आप ख़ुद "Attendee discovery" जॉइन करें, Camp Card शेयर करें, किसी डील का फ़ॉर्म भरें या नोटिफ़िकेशन चालू करें। हम डेटा बेचते नहीं, विज्ञापन नहीं दिखाते। आप कभी भी अपना डेटा देख, सही करवा या मिटवा सकते हैं और सहमति वापस ले सकते हैं: ऐप के "Your data" में, या <strong>{{ $mail }}</strong> पर लिखकर। हम <strong>{{ $days }} दिन</strong> के भीतर जवाब देते हैं। कार्यक्रम के लगभग 90 दिन बाद सर्वर पर साझा किया गया सारा डेटा अपने-आप मिटा दिया जाता है।</p>
        </section>
    @endif
@endsection
