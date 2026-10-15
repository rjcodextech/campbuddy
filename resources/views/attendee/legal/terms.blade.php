@extends('attendee.legal.layout', [
    'pageTitle' => 'Terms of use',
    'pageDescription' => 'The rules for using CampBuddy, the WordCamp companion app and website.',
])

@php
    $entity = config('campbuddy.legal.entity');
    $mail = config('campbuddy.legal.grievance_email');
@endphp

@section('content')
    <h1>Terms of use</h1>
    <p class="legal__meta">Last updated {{ config('campbuddy.legal.updated') }}</p>

    <p>CampBuddy is a free companion for WordCamp attendees, run by <strong>{{ $entity }}</strong>. By using the website or the app you agree to these terms. How we handle personal data is explained in the <a href="{{ route('privacy') }}">privacy notice</a>.</p>

    <h2>1. What CampBuddy is</h2>
    <p>CampBuddy shows schedules, speakers, sponsors, deals and other details of WordCamp events, and helps you plan your day and meet other attendees. Most of it comes from each WordCamp's own website and from the event's organizers. CampBuddy is independent. It is not run or endorsed by WordPress.org, the WordPress Foundation or any WordCamp team.</p>

    <h2>2. Information can change</h2>
    <p>Times, rooms, speakers and venue details are taken from the event and can change. We try to keep them current, but we do not promise that they are complete or correct. Check with the event's organizers for anything important, such as travel or tickets.</p>

    <h2>3. Deals, free tools and sponsors</h2>
    <p>Offers, coupon codes, free tools and sponsor links belong to the companies that make them. We do not sell or guarantee them, and what you agree with a company is between you and that company. If you send a deal form, your answers go to that company as explained in the privacy notice.</p>

    <h2>4. Meeting people</h2>
    <p>Attendee discovery, waves, messages and shared Camp Cards are optional. Use them kindly and honestly:</p>
    <ul>
        <li>Do not harass, spam, impersonate or deceive anyone, and do not share other people's information.</li>
        <li>Do not use CampBuddy to collect people's details for marketing.</li>
        <li>Meet in public places at the event and follow the WordCamp code of conduct. You are responsible for your own safety and choices.</li>
    </ul>
    <p>You can report a profile inside the app. We may hide a profile or remove access that breaks these rules.</p>

    <h2>5. Using the service</h2>
    <p>Do not try to break, overload or reach parts of CampBuddy that are not yours, and do not copy it in bulk by automated means. We may change, pause or stop any part of CampBuddy.</p>

    <h2>6. No warranty, limited liability</h2>
    <p>CampBuddy is provided "as is" and free of charge. To the extent the law allows, we are not liable for loss or damage from using it or from relying on information in it, or from what other attendees or companies do.</p>

    <h2>7. Law</h2>
    <p>These terms are governed by the laws of India.</p>

    <h2>8. Contact</h2>
    <p>Questions or complaints: <a href="mailto:{{ $mail }}?subject=CampBuddy">{{ $mail }}</a>.</p>
@endsection
