@extends('attendee.legal.layout', [
    'pageTitle' => 'Privacy notice',
    'pageDescription' => 'What personal data CampBuddy keeps, why, for how long, and how to see, correct or erase it. Written under India\'s Digital Personal Data Protection Act, 2023.',
])

@php
    $entity = config('campbuddy.legal.entity');
    $mail = config('campbuddy.legal.grievance_email');
    $days = config('campbuddy.legal.reply_days');
    $mailto = fn (string $subject) => 'mailto:'.$mail.'?subject='.rawurlencode($subject);
@endphp

@section('content')
    <h1>Privacy notice</h1>
    <p class="legal__meta">Last updated {{ config('campbuddy.legal.updated') }} &middot; Written under India's Digital Personal Data Protection Act, 2023 (DPDP Act)</p>

    <div class="legal__box">
        <strong>The short version</strong>
        <ul>
            <li>You do not need an account. Your saved sessions, quests, notes, Camp Card and answers stay <strong>on your phone</strong>. We never receive them.</li>
            <li>Something reaches our servers only when <strong>you choose</strong> to: join attendee discovery, share your Camp Card, send a deal form, or switch on notifications.</li>
            <li>We do not sell your data, show ads, or build a profile of you. Nothing you type is sent to analytics.</li>
            <li>You can take it back at any time, inside the app or by writing to <a href="{{ $mailto('CampBuddy data request') }}">{{ $mail }}</a>.</li>
        </ul>
    </div>

    <h2>1. Who is responsible</h2>
    <p>CampBuddy is run by <strong>{{ $entity }}</strong> (the "Data Fiduciary" under the DPDP Act, "we" below). CampBuddy is an independent companion for WordCamp attendees. It is not run by WordPress.org, the WordPress Foundation or any WordCamp organizing team, although organizers can edit parts of their own event's page.</p>
    <p>Contact for everything in this notice: <a href="{{ $mailto('CampBuddy privacy') }}">{{ $mail }}</a>.</p>

    <h2>2. What we keep, why, and for how long</h2>
    <table>
        <thead><tr><th>What</th><th>Why</th><th>Where, and how long</th></tr></thead>
        <tbody>
            <tr>
                <td data-label="What"><strong>Your own data</strong>: saved sessions and attendance, quest ticks, notes on people you want to meet, your Camp Card, your answers to "Make CampBuddy yours", reminder choices.</td>
                <td data-label="Why">To give you your schedule, plan and Camp Card.</td>
                <td data-label="Where and how long"><strong>Only on your phone.</strong> Not sent to us. Kept until you clear it (Your data &rarr; Clear my data) or uninstall the app.</td>
            </tr>
            <tr>
                <td data-label="What"><strong>Attendee discovery profile</strong>, only if you tap "Join attendee discovery": the name you choose to show (or none, to stay anonymous), the interests you tick, a profession and "who I'd like to meet" line if you add them, and a random ID. If you wave at someone or message them: the wave, your first name if you give it, and up to three short messages.</td>
                <td data-label="Why">So people with shared interests can find each other at the event.</td>
                <td data-label="Where and how long">Our server. Other people in discovery for the same event can see the fields you chose. Removed when you leave discovery (one tap), or <strong>deleted about 90 days after the event</strong>.</td>
            </tr>
            <tr>
                <td data-label="What"><strong>Shared Camp Card</strong>, only if you switch on "Show my Camp Card on the attendee list": the fields you chose to show.</td>
                <td data-label="Why">So people who tap your name in Explore &rarr; People can see and save your card.</td>
                <td data-label="Where and how long">Our server, shown to other attendees of that event. Removed when you switch it off, or <strong>deleted about 90 days after the event</strong>.</td>
            </tr>
            <tr>
                <td data-label="What"><strong>Deal forms</strong>, only if you submit one: the name, company, email, mobile number and choices that form asks for.</td>
                <td data-label="Why">So the sponsor of that offer can contact you about it. We pass your answers to that sponsor.</td>
                <td data-label="Where and how long">Our server and the sponsor. We <strong>delete our copy about 90 days after the event</strong>. The sponsor keeps their own copy under their own privacy policy; ask them or us to help.</td>
            </tr>
            <tr>
                <td data-label="What"><strong>Notifications</strong>, only if you allow them: a notification address from Google (Firebase) or your browser, a random device ID, the sessions you asked to be reminded about, and the app version.</td>
                <td data-label="Why">To send session reminders and a few event updates.</td>
                <td data-label="Where and how long">Our server. Removed when you turn notifications off (or the address stops working), or <strong>deleted about 90 days after the event</strong>.</td>
            </tr>
            <tr>
                <td data-label="What"><strong>Feedback</strong>, only if you send it: a star rating and an optional comment, linked to a random code (not your name).</td>
                <td data-label="Why">So organizers can learn what worked.</td>
                <td data-label="Where and how long">Our server, kept for organizers. Ask us to remove yours any time.</td>
            </tr>
            <tr>
                <td data-label="What"><strong>Reports</strong>, only if you report someone in discovery: your reason and note, and a random device ID.</td>
                <td data-label="Why">To look into misuse.</td>
                <td data-label="Where and how long">Our server, until the report is dealt with and the event's data is deleted.</td>
            </tr>
            <tr>
                <td data-label="What"><strong>The public attendee list</strong>: name, photo and links people chose to show on their own WordCamp's Attendees page.</td>
                <td data-label="Why">So you can find and meet people. We copy only what that page already shows publicly.</td>
                <td data-label="Where and how long">Our server. Anyone can ask for their entry to be hidden: write to us, or use the event's own removal page (campbuddy.club/event/&lt;event&gt;/roster-removal).</td>
            </tr>
            <tr>
                <td data-label="What"><strong>Technical records</strong>: your IP address, device type and the time of requests, in our web host's and Cloudflare's logs.</td>
                <td data-label="Why">Security, abuse prevention and keeping the service running.</td>
                <td data-label="Where and how long">Our host and Cloudflare, kept for a short time under their own settings.</td>
            </tr>
            <tr>
                <td data-label="What"><strong>Website visit statistics</strong> (Google Analytics, website only): which pages and buttons are used. No names, no text you type, no discovery or Camp Card data, no query strings.</td>
                <td data-label="Why">To improve CampBuddy.</td>
                <td data-label="Where and how long">Google Analytics. We do not load it at all if your browser sends "Do Not Track" or "Global Privacy Control". <strong>The Android and iPhone apps contain no analytics</strong>.</td>
            </tr>
        </tbody>
    </table>
    <p>We use your data only for the reasons in the table. We ask for your consent each time you start one of the sharing features, and the app tells you what is shared before you share it. Nothing in the first list is shared unless you choose to.</p>

    <h2>3. Who else sees it</h2>
    <ul>
        <li><strong>Other attendees</strong>, only what you put in discovery or on a shared Camp Card.</li>
        <li><strong>The sponsor</strong> of a deal, only if you submit that deal's form.</li>
        <li><strong>Our service providers</strong>, who process data for us: our web host, Cloudflare (network and security), Google (Firebase Cloud Messaging for notifications; Google Analytics on the website) and the browser or phone maker's push service. Some of them are outside India.</li>
        <li><strong>Authorities</strong>, if the law requires it.</li>
    </ul>
    <p>We do not sell personal data and we do not show advertising. We do not track or profile anyone's behaviour, and we do not target anything at children.</p>

    <h2>4. Your rights</h2>
    <p>Under the DPDP Act you can:</p>
    <ul>
        <li><strong>Know</strong> what of yours we hold and who we shared it with, and get a copy.</li>
        <li><strong>Correct or complete</strong> it.</li>
        <li><strong>Erase</strong> it.</li>
        <li><strong>Withdraw consent</strong> at any time. It is as easy as giving it: leave discovery, switch off Camp Card sharing, switch off notifications. Withdrawing does not affect what was done before.</li>
        <li><strong>Complain</strong> and have it answered.</li>
        <li><strong>Nominate</strong> someone to exercise these rights for you if you die or cannot.</li>
    </ul>
    <h3>In the app and website (instant)</h3>
    <ul>
        <li>My Day &rarr; Your data: save everything as a PDF or JSON, or clear it from your phone.</li>
        <li>Explore &rarr; People: leave attendee discovery; switch Camp Card sharing off on the Camp Card page.</li>
        <li>Your phone's settings: turn notifications off.</li>
    </ul>
    <h3 id="grievance">By email</h3>
    <p>For anything else, or if you cannot use the tools above, write to <a href="{{ $mailto('CampBuddy data request') }}">{{ $mail }}</a> and say what you want. To find your data we need something that identifies it, for example the email you used on a deal form, or the name you showed in discovery. Because attendees have no account, we cannot find data from your name alone, and we may ask you to prove it is yours. <strong>We reply within {{ $days }} days.</strong></p>
    <p>If we do not resolve your complaint, you can complain to the <strong>Data Protection Board of India</strong>. Please write to us first.</p>

    <h2>5. Children</h2>
    <p>CampBuddy is a general guide to WordCamp events. We do not knowingly ask for personal information from anyone under 18, and we do not track their behaviour or target anything at them. The schedule, guide and quests work without sharing anything. If you are under 18, please ask a parent or guardian before using attendee discovery, Camp Card sharing, deal forms or notifications. A parent or guardian who wants a child's data erased can write to us and we will do it.</p>

    <h2>6. Security</h2>
    <p>Everything travels over HTTPS. Discovery and Camp Card ownership codes are stored only as hashes. Admin and organizer areas need sign-in, and we limit how fast anyone can call our servers. If a breach affecting your personal data ever happens, we will tell you and the Data Protection Board of India as the law requires.</p>

    <h2>7. Changes</h2>
    <p>If this notice changes in a way that matters, we will update the date at the top and say so in the app. Older notices are not kept, but the date always shows which one you are reading.</p>

    <h2>8. Hindi summary (सारांश)</h2>
    <p>CampBuddy के लिए आपको खाता नहीं बनाना पड़ता। आपके सेव किए सत्र, नोट्स, Camp Card और जवाब सिर्फ़ आपके फ़ोन में रहते हैं। हमारे सर्वर तक डेटा तभी पहुँचता है जब आप ख़ुद "Attendee discovery" जॉइन करें, Camp Card शेयर करें, किसी डील का फ़ॉर्म भरें या नोटिफ़िकेशन चालू करें। हम डेटा बेचते नहीं, विज्ञापन नहीं दिखाते। आप कभी भी अपना डेटा देख, सही करवा या मिटवा सकते हैं और सहमति वापस ले सकते हैं: ऐप के "Your data" में, या {{ $mail }} पर लिखकर। हम {{ $days }} दिन के भीतर जवाब देते हैं। कार्यक्रम के लगभग 90 दिन बाद सर्वर पर साझा किया गया सारा डेटा अपने-आप मिटा दिया जाता है।</p>
@endsection
