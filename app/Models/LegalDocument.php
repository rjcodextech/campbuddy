<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegalDocument extends Model
{
    protected $fillable = [
        'slug',
        'title',
        'meta_description',
        'content',
    ];

    /**
     * Retrieve document by slug, falling back to default configuration metadata if not yet created.
     */
    public static function forSlug(string $slug): ?self
    {
        return self::where('slug', $slug)->first();
    }

    /**
     * Default definitions for legal documents before any custom edits.
     *
     * @return array{title: string, meta_description: string, updated: string}
     */
    public static function defaultMeta(string $slug): array
    {
        $updated = config('campbuddy.legal.updated', '15 October 2026');

        if ($slug === 'privacy') {
            return [
                'title' => 'Privacy notice',
                'meta_description' => 'What personal data CampBuddy keeps, why, for how long, and how to see, correct or erase it. Written under India\'s Digital Personal Data Protection Act, 2023.',
                'updated' => $updated,
            ];
        }

        return [
            'title' => 'Terms of use',
            'meta_description' => 'The rules for using CampBuddy, the WordCamp companion app and website.',
            'updated' => $updated,
        ];
    }

    /**
     * Default rich HTML content for privacy policy and terms of use.
     */
    public static function defaultContent(string $slug): string
    {
        $entity = config('campbuddy.legal.entity', 'WPSimplified');
        $email = config('campbuddy.legal.grievance_email', 'hello@wpsimplified.in');
        $replyDays = config('campbuddy.legal.reply_days', 30);
        $privacyUrl = route('privacy');

        if ($slug === 'privacy') {
            return <<<HTML
<div class="legal__box">
    <strong>The short version</strong>
    <ul>
        <li>You do not need an account. Your saved sessions, quests, notes, Camp Card and answers stay <strong>on your phone</strong>. We never receive them.</li>
        <li>Something reaches our servers only when <strong>you choose</strong> to: join attendee discovery, share your Camp Card, send a deal form, or switch on notifications.</li>
        <li>We do not sell your data, show ads, or build a profile of you. Nothing you type is sent to analytics.</li>
        <li>You can take it back at any time, inside the app or by writing to <a href="mailto:{$email}?subject=CampBuddy%20data%20request">{$email}</a>.</li>
    </ul>
</div>

<h2>1. Who is responsible</h2>
<p>CampBuddy is run by <strong>{$entity}</strong> (the "Data Fiduciary" under India's Digital Personal Data Protection Act, 2023 [DPDP Act], referred to as "we" below). CampBuddy is an independent companion for WordCamp attendees. It is not run by WordPress.org, the WordPress Foundation, or any WordCamp organizing team, although organizers can edit parts of their own event's page.</p>
<p>Contact for everything in this notice: <a href="mailto:{$email}?subject=CampBuddy%20privacy">{$email}</a>.</p>

<h2>2. What we keep, why, and for how long</h2>
<table>
    <thead>
        <tr>
            <th>What</th>
            <th>Why</th>
            <th>Where, and how long</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td data-label="What"><strong>Your own data</strong>: saved sessions and attendance, quest ticks, notes on people you want to meet, your Camp Card, your answers to "Make CampBuddy yours", reminder choices.</td>
            <td data-label="Why">To give you your schedule, plan and Camp Card.</td>
            <td data-label="Where and how long"><strong>Only on your phone.</strong> Not sent to our servers. Kept until you clear it (Your data &rarr; Clear my data) or uninstall the app.</td>
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
            <td data-label="What"><strong>Suggest a Free Steal</strong>, only if you send one: the tool's name and link, why you like it, and your name and email if you add them.</td>
            <td data-label="Why">So we can look at the suggestion and thank you for it.</td>
            <td data-label="Where and how long">Our server. <strong>Deleted about 90 days after the event</strong>.</td>
        </tr>
        <tr>
            <td data-label="What"><strong>Sponsor deal form</strong> (for sponsors, not attendees): the contact person's name, work email and phone if given, the deal and its logo, and the IP address and browser the form was sent from.</td>
            <td data-label="Why">To check that a deal really comes from the sponsor, and to contact them about it.</td>
            <td data-label="Where and how long">Our server, and an email to our team. <strong>Deleted about 90 days after the event</strong>. An approved deal's public text and logo stay on the deal.</td>
        </tr>
        <tr>
            <td data-label="What"><strong>Consent records</strong>: when you ticked "I agree" (or sent a deal form), for which feature, which version of this notice, and the IP address and browser name.</td>
            <td data-label="Why">The DPDP Act asks us to be able to show that you agreed.</td>
            <td data-label="Where and how long">Our server. <strong>Deleted with that feature's data, about 90 days after the event</strong>.</td>
        </tr>
        <tr>
            <td data-label="What"><strong>Notifications</strong>, only if you allow them: a notification address from Google (Firebase) or your browser, a random device ID, the sessions you asked to be reminded about, the app version, and which WordCamps you opened from three days before the event (and when).</td>
            <td data-label="Why">To send session reminders, a few event updates and the organizers' live announcements during the event ("Lunch is served").</td>
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
            <td data-label="Where and how long">Google Analytics, <strong>only if you tap "Allow" when the website asks</strong>; until then it is not loaded at all, and never if your browser sends "Do Not Track" or "Global Privacy Control". You can change your answer at the end of any page ("Analytics choice"). <strong>The Android and iPhone apps contain no analytics</strong>.</td>
        </tr>
    </tbody>
</table>
<p>We use your data only for the reasons in the table. Before you join attendee discovery or share your Camp Card you tick "I agree", right where it says what is shared and with whom; a deal form says it above its Continue button. We keep a record of that consent. Nothing in the first list is shared unless you choose to.</p>

<h2>3. Who else sees it</h2>
<ul>
    <li><strong>Other attendees</strong>: only what you choose to make visible in discovery or on a shared Camp Card.</li>
    <li><strong>The sponsor</strong> of a deal: only if you submit that deal's form.</li>
    <li><strong>Our service providers</strong> who process data for us: our web host, Cloudflare (network and security), Google (Firebase Cloud Messaging for notifications; Google Analytics on the website), and the browser or phone maker's push service. Some of them operate outside India.</li>
    <li><strong>Authorities</strong>: if required by applicable law or a lawful legal order.</li>
</ul>
<p>We do not sell personal data and we do not show advertising. We do not track or profile anyone's behaviour, and we do not target anything at children.</p>

<h2>4. Your rights under the DPDP Act</h2>
<p>Under India's Digital Personal Data Protection Act, 2023, you have clear statutory rights:</p>
<ul>
    <li><strong>Right to access</strong>: Know what personal data of yours we hold, how it is processed, and who we shared it with, along with a copy.</li>
    <li><strong>Right to correction and erasure</strong>: Correct inaccurate data, complete incomplete records, or request erasure.</li>
    <li><strong>Withdraw consent</strong>: You can withdraw your consent at any time. It is as easy as giving it: leave discovery, switch off Camp Card sharing, or switch off notifications. Withdrawing consent does not affect processing done prior to withdrawal.</li>
    <li><strong>Right to grievance redressal</strong>: Raise concerns and receive an answer within our committed timeframe.</li>
    <li><strong>Right to nominate</strong>: Nominate an individual to exercise these rights on your behalf in the event of death or incapacity.</li>
</ul>

<h3>In the app and website (instant)</h3>
<ul>
    <li><strong>My Day &rarr; Your data</strong>: Save everything as a PDF or JSON export, or clear it completely from your phone.</li>
    <li><strong>Explore &rarr; People</strong>: Leave attendee discovery with a single tap; toggle Camp Card sharing off anytime on your Camp Card screen.</li>
    <li><strong>Device settings</strong>: Turn push notifications off anytime in your browser or Android/iOS notification settings.</li>
</ul>

<h3 id="grievance">By email & Grievance Redressal</h3>
<p>For any request or complaint, write to our designated grievance contact at <a href="mailto:{$email}?subject=CampBuddy%20data%20request">{$email}</a>. Because attendees do not have mandatory user accounts, please provide identifier information (such as the email used for a deal form or your discovery display name) so we can locate your record. <strong>We reply within {$replyDays} days.</strong></p>
<p>If you are not satisfied with our resolution, you have the right to escalate your complaint to the <strong>Data Protection Board of India</strong>.</p>

<h2>5. Protection of minors (Children)</h2>
<p>CampBuddy is designed for WordCamp attendees of all ages. We do not knowingly solicit personal data from individuals under 18 years of age without parental consent, nor do we track their behaviour or target advertising. All schedule viewing, personal planning, and quests work entirely offline without collecting personal data. Before attendee discovery or Camp Card sharing, the tick box asks you to confirm that you are 18 or older, or that a parent or guardian agrees. If you are under 18, please also ask them before deal forms or notifications. Parents or guardians may request the immediate erasure of a minor's information by emailing us.</p>

<h2>6. Data Security</h2>
<p>We implement robust technical and organizational security measures. All communications occur over encrypted TLS/HTTPS. Discovery tokens and Camp Card secret codes are stored using salted cryptographic hashes. Administrative endpoints require strong authentication, and rate limiting protects against abuse. In the unlikely event of a personal data breach, we will notify affected individuals and the Data Protection Board of India in accordance with applicable legal requirements.</p>

<h2>7. Updates to this Notice</h2>
<p>We may update this Privacy Notice from time to time to reflect improvements in CampBuddy or changes in applicable legislation. When updates are published, the revision date at the top will be updated. We encourage you to review this notice periodically.</p>

<h2>8. Hindi summary (सारांश)</h2>
<p>CampBuddy का उपयोग करने के लिए आपको किसी भी प्रकार का अकाउंट (खाता) बनाने की आवश्यकता नहीं है। आपके द्वारा सेव किए गए सेशन्स, नोट्स, Camp Card और उत्तर केवल आपके अपने फ़ोन पर सुरक्षित रहते हैं — हमारे सर्वर पर नहीं। डेटा हमारे पास तभी आता है जब आप स्वेच्छा से "Attendee Discovery" में शामिल होते हैं, Camp Card शेयर करते हैं, किसी स्पॉन्सर डील का फ़ॉर्म भरते हैं, या नोटिफ़िकेशन ऑन करते हैं। हम आपका डेटा किसी को नहीं बेचते और न ही कोई विज्ञापन दिखाते हैं। आप किसी भी समय अपना डेटा ऐप के "Your data" सेक्शन से देख सकते हैं, मिटा सकते हैं, या <a href="mailto:{$email}">{$email}</a> पर ईमेल भेजकर सहायता प्राप्त कर सकते हैं। कार्यक्रम समाप्त होने के लगभग 90 दिनों बाद सर्वर का साझा डेटा स्वतः पूर्णतः हटा दिया जाता है।</p>
HTML;
        }

        return <<<HTML
<div class="legal__box">
    <strong>Terms at a glance</strong>
    <ul>
        <li>CampBuddy is a free, independent companion app created for WordCamp attendees by <strong>{$entity}</strong>.</li>
        <li>It is not affiliated with, endorsed by, or managed by the WordPress Foundation or WordPress.org.</li>
        <li>Your privacy is respected: you own your data, stored locally on your device by default.</li>
        <li>Be respectful and kind: adhere to the WordCamp Code of Conduct when interacting with fellow attendees.</li>
    </ul>
</div>

<p>Welcome to CampBuddy. By accessing or using the CampBuddy website, Progressive Web App (PWA), or mobile applications (collectively, the "Service"), you agree to be bound by these Terms of Use ("Terms"). If you disagree with any part of these terms, please do not use the Service. How we handle personal data is explained in our <a href="{$privacyUrl}">Privacy Notice</a>.</p>

<h2>1. About the Service</h2>
<p>CampBuddy is an independent, mobile-first companion application developed and maintained by <strong>{$entity}</strong> to assist attendees of WordCamp conferences. CampBuddy provides event schedules, session information, speaker profiles, sponsor offers, venue navigation, and networking tools ("Attendee Discovery" and digital "Camp Cards").</p>
<p>CampBuddy is completely independent and is not officially affiliated with, endorsed by, or sponsored by WordPress.org, Automattic Inc., the WordPress Foundation, or individual WordCamp organizing committees, although volunteer organizers may contribute information regarding their respective events.</p>

<h2>2. Information Accuracy and Event Changes</h2>
<p>Event schedules, speaker lists, session topics, venue locations, and timings displayed in CampBuddy are gathered from official WordCamp public websites and information provided by volunteer organizers. While we endeavor to provide accurate and up-to-date details:</p>
<ul>
    <li>Schedules and speaker availability may change without prior notice due to unforeseen circumstances at the venue.</li>
    <li>CampBuddy does not guarantee the complete accuracy, timeliness, or reliability of any third-party schedule or speaker information.</li>
    <li>Attendees should always verify official announcements and scheduling updates through their event's official website or venue information desks.</li>
</ul>

<h2>3. Deals, Free Tools, and Sponsor Offers</h2>
<p>CampBuddy may display promotional offers, discounts, coupons, and free developer tools provided by event sponsors and community partners:</p>
<ul>
    <li>All deals, coupon codes, and third-party tools are the exclusive property and responsibility of the respective offering company or sponsor.</li>
    <li>CampBuddy does not sell, warrant, or guarantee any sponsor products or services. Any transaction, purchase, or contract you enter into is strictly between you and that third party.</li>
    <li>If you choose to submit an inquiry or claim a sponsor deal through a form on CampBuddy, your contact information is shared directly with that sponsor as disclosed in our <a href="{$privacyUrl}">Privacy Notice</a>.</li>
</ul>

<h2>4. Community Standards & Code of Conduct</h2>
<p>CampBuddy offers optional peer-to-peer networking features, including Attendee Discovery, Waves, Direct Connections, and digital Camp Card sharing. When using these features, you agree to:</p>
<ul>
    <li><strong>Respect & Kindness</strong>: Treat all community members with courtesy and respect, adhering to the official WordPress and WordCamp Codes of Conduct.</li>
    <li><strong>No Harassment or Spam</strong>: Do not engage in harassment, stalking, offensive communications, unsolicited commercial messaging, or deceptive impersonation.</li>
    <li><strong>No Bulk Marketing or Scraping</strong>: Do not harvest, collect, or scrape attendee contact information for sales leads, email marketing lists, or unsolicited recruitment.</li>
    <li><strong>Personal Safety</strong>: Arrange in-person meetings only in open, well-lit public conference venue areas. You are solely responsible for your personal safety and interactions with other attendees.</li>
</ul>
<p>CampBuddy includes in-app reporting tools allowing attendees to flag inappropriate behavior. We reserve the right to immediately suspend, moderate, or terminate access for any user violating these standards.</p>

<h2>5. Acceptable Use of the Platform</h2>
<p>You agree to use CampBuddy solely for its intended lawful purposes. You shall not:</p>
<ul>
    <li>Interfere with, disrupt, or place an unreasonable load on our infrastructure or networks.</li>
    <li>Attempt to gain unauthorized access to any accounts, computer systems, or networks connected to CampBuddy.</li>
    <li>Use any automated robots, spiders, scrapers, or other automated means to access the Service or extract data in bulk without explicit written consent.</li>
    <li>Decompile, reverse engineer, or attempt to derive the source code of proprietary components of the Service, except as permitted under applicable open-source licenses.</li>
</ul>

<h2>6. Disclaimer of Warranties</h2>
<p>The Service is provided on an "AS IS" and "AS AVAILABLE" basis, free of charge, without warranties of any kind, whether express, statutory, or implied, including but not limited to the implied warranties of merchantability, fitness for a particular purpose, non-infringement, or course of performance. {$entity} does not warrant that the Service will function uninterrupted, error-free, or be completely secure from cyber threats.</p>

<h2>7. Limitation of Liability</h2>
<p>To the fullest extent permitted by applicable law, {$entity}, its founders, contributors, and affiliates shall not be liable for any indirect, incidental, special, consequential, or punitive damages, including loss of profits, data, goodwill, or other intangible losses resulting from:</p>
<ul>
    <li>Your access to or inability to access or use the Service.</li>
    <li>Any conduct or content of any third party on the Service, including other attendees or sponsors.</li>
    <li>Any errors, omissions, or inaccuracies in schedule, speaker, or venue details.</li>
    <li>Any unauthorized access to or alteration of your transmissions or data.</li>
</ul>

<h2>8. Intellectual Property & Trademarks</h2>
<p>WordPress&reg;, WordCamp&reg;, and the WordPress logo are registered trademarks of the WordPress Foundation. All respective rights belong to their owners. CampBuddy makes nominative fair use of these trademarks solely to describe compatibility and event details for conference attendees. The CampBuddy brand, custom graphics, and software architecture are protected under applicable intellectual property laws.</p>

<h2>9. Governing Law & Dispute Resolution</h2>
<p>These Terms shall be governed by and construed in accordance with the laws of India, without regard to its conflict of law provisions. Any legal action or proceeding arising under these Terms shall be brought exclusively in the competent courts located in India.</p>

<h2>10. Modifications to Terms</h2>
<p>We reserve the right to revise or replace these Terms at our discretion. If a revision is material, we will provide reasonable notice on our website or within the application. By continuing to use the Service after revisions become effective, you agree to be bound by the revised terms.</p>

<h2>11. Contact Us</h2>
<p>If you have any questions, feedback, or grievances regarding these Terms, please contact us at:</p>
<p><strong>{$entity}</strong><br>
Email: <a href="mailto:{$email}?subject=CampBuddy%20Terms%20Inquiry">{$email}</a><br>
Website: <a href="https://wpsimplified.in" target="_blank" rel="noopener noreferrer">wpsimplified.in</a></p>
HTML;
    }
}
