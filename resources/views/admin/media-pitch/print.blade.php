<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $event_name }} — Media Partnership Proposal | CampBuddy | WPSimplified</title>
    <style>
        @page {
            size: A4;
            margin: 16mm 16mm 16mm 16mm;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #0F172A;
            background: #FFFFFF;
            line-height: 1.5;
            font-size: 13px;
            padding: 24px 32px;
        }
        .container {
            max-width: 820px;
            margin: 0 auto;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #047857;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .logo-wrap {
            display: flex;
            align-items: center;
        }
        .logo-wrap img {
            height: 38px;
            width: auto;
            object-fit: contain;
        }
        .brand-meta {
            text-align: right;
            font-size: 11.5px;
            color: #475569;
            line-height: 1.4;
        }
        .badge-status {
            display: inline-block;
            background: #ECFDF5;
            color: #047857;
            border: 1px solid #A7F3D0;
            padding: 2px 8px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 10.5px;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            margin-bottom: 3px;
        }
        .meta-box {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-left: 4px solid #047857;
            border-radius: 8px;
            padding: 10px 14px;
            margin-bottom: 16px;
            font-size: 12.5px;
            display: grid;
            grid-template-columns: 80px 1fr;
            gap: 4px 12px;
        }
        .meta-label {
            font-weight: 700;
            color: #334155;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.03em;
        }
        .meta-value {
            color: #0F172A;
        }
        h2 {
            font-size: 13.5px;
            font-weight: 700;
            color: #047857;
            margin: 14px 0 6px 0;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border-bottom: 1px solid #E2E8F0;
            padding-bottom: 3px;
        }
        p {
            margin-bottom: 8px;
        }
        ul {
            margin: 0 0 10px 18px;
        }
        li {
            margin-bottom: 4px;
        }
        .brand-highlight-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin: 10px 0 14px 0;
        }
        .brand-card {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            padding: 10px 12px;
        }
        .brand-card-title {
            font-weight: 700;
            font-size: 13px;
            color: #047857;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .brand-card-desc {
            font-size: 12px;
            color: #334155;
            line-height: 1.45;
        }
        .deliverable-card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 6px;
            padding: 8px 12px;
            margin-bottom: 8px;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .deliverable-phase-title {
            font-weight: 700;
            font-size: 12.5px;
            color: #0F172A;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .deliverable-phase-badge {
            background: #E0F2FE;
            color: #0369A1;
            font-size: 10px;
            font-weight: 700;
            padding: 1px 6px;
            border-radius: 4px;
            text-transform: uppercase;
        }
        .channels-list {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            padding: 10px 14px;
            margin: 10px 0 14px 0;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .channel-row {
            display: flex;
            flex-wrap: wrap;
            gap: 6px 14px;
            font-size: 12px;
            padding: 4px 0;
        }
        .channel-row:not(:last-child) {
            border-bottom: 1px dashed #E2E8F0;
            margin-bottom: 4px;
            padding-bottom: 6px;
        }
        .channel-entity {
            font-weight: 700;
            color: #047857;
            min-width: 140px;
        }
        .channel-links {
            color: #334155;
            flex: 1;
        }
        .channel-links span {
            color: #94A3B8;
            margin: 0 4px;
        }
        .bios-box {
            background: #F0FDF4;
            border: 1px solid #BBF7D0;
            border-radius: 8px;
            padding: 10px 14px;
            margin: 12px 0 14px 0;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .bio-item {
            margin-bottom: 8px;
        }
        .bio-item:not(:last-child) {
            border-bottom: 1px dashed #A7F3D0;
            padding-bottom: 8px;
        }
        .bio-title {
            font-size: 11.5px;
            font-weight: 700;
            color: #065F46;
            margin-bottom: 2px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .bio-text {
            font-size: 12px;
            color: #1E293B;
            line-height: 1.45;
        }
        .signoff {
            margin-top: 16px;
            padding-top: 10px;
            border-top: 1px solid #E2E8F0;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .signoff-name {
            font-weight: 700;
            font-size: 13.5px;
            color: #047857;
        }
        .signoff-role {
            font-size: 12.5px;
            color: #475569;
        }
        .toolbar {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #0F172A;
            color: #FFFFFF;
            padding: 10px 18px;
            border-radius: 30px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.3);
            display: flex;
            gap: 12px;
            align-items: center;
            z-index: 9999;
        }
        .toolbar button {
            background: #047857;
            color: #FFFFFF;
            border: none;
            padding: 7px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12.5px;
            cursor: pointer;
        }
        .toolbar button:hover {
            background: #059669;
        }
        .toolbar a {
            color: #94A3B8;
            text-decoration: none;
            font-size: 12.5px;
        }
        .toolbar a:hover {
            color: #FFFFFF;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                padding: 0;
            }
            .header {
                border-bottom: 2px solid #047857 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .badge-status {
                background: #ECFDF5 !important;
                color: #047857 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .meta-box {
                background: #F8FAFC !important;
                border-left: 4px solid #047857 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .brand-card {
                background: #F8FAFC !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .channels-list {
                background: #F8FAFC !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .bios-box {
                background: #F0FDF4 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

    <div class="toolbar no-print">
        <span>Proposal Print Ready</span>
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
        <a href="javascript:window.close()">Close</a>
    </div>

    <div class="container">
        <header class="header">
            <div class="logo-wrap">
                <img src="/media/campbuddy-wpsimplified-logo.svg?v={{ @filemtime(public_path('media/campbuddy-wpsimplified-logo.svg')) }}" alt="CampBuddy | WPSimplified">
            </div>
            <div class="brand-meta">
                <div class="badge-status">Official Partnership Proposal</div>
                <div>Date: {{ date('F Y') }}</div>
            </div>
        </header>

        <div class="meta-box">
            <div class="meta-label">To:</div>
            <div class="meta-value">{{ $event_name }} Organizing Team ({{ $event_email }})</div>

            <div class="meta-label">Event:</div>
            <div class="meta-value"><strong>{{ $event_name }}</strong> &bull; {{ $event_dates }} &bull; {{ $event_venue }}</div>

            <div class="meta-label">From:</div>
            <div class="meta-value">{{ $applicant_name }} ({{ $applicant_role }})</div>

            <div class="meta-label">Subject:</div>
            <div class="meta-value"><strong>{{ $subject }}</strong></div>
        </div>

        <p>Dear {{ $event_name }} Organizing Team,</p>
        <p>Greetings from the team at CampBuddy and WPSimplified!</p>
        <p>We are writing to officially submit our application for the <strong>Media Partnership for {{ $event_name }}</strong> ({{ $event_dates }}, {{ $event_venue }}). We propose a transparent, high-value collaboration strictly focused on practical deliverables that genuinely support your attendees, speakers, and sponsors.</p>

        <h2>Who We Are</h2>
        <div class="brand-highlight-grid">
            <div class="brand-card">
                <div class="brand-card-title">
                    <span>CampBuddy</span>
                    <small style="font-size: 11px; font-weight: 500; color: #475569;">campbuddy.club</small>
                </div>
                <div class="brand-card-desc">
                    An intelligent mobile companion app engineered to transform how attendees experience WordCamps. Built on the <em>"guidance over information"</em> principle, it answers the #1 first-timer question: <em>"What do I do next?"</em> Features live multi-track session guidance, digital networking Camp Cards with instant LinkedIn QR codes, interest-based attendee matching, gamified sponsor quests, Contributor Day table guides, and exclusive sponsor deals. Works 100% offline with zero sign-ups and complete on-device privacy.
                </div>
            </div>

            <div class="brand-card">
                <div class="brand-card-title">
                    <span>WPSimplified</span>
                    <small style="font-size: 11px; font-weight: 500; color: #475569;">wpsimplified.in</small>
                </div>
                <div class="brand-card-desc">
                    A dedicated developer education platform and community led by WordPress architect Sunil Kumar Sharma. Committed to <em>zero gatekeeping</em> in tech, it bridges regional language barriers through actionable video tutorials, plugin architecture masterclasses, hands-on workshops, and practical career mentorship for freelancers and engineers.
                </div>
            </div>
        </div>

        <h2>Committed Deliverables (Clear &amp; Focused)</h2>
        
        <div class="deliverable-card">
            <div class="deliverable-phase-title">
                <span class="deliverable-phase-badge">Phase 1</span>
                <span>Before the Event (Awareness &amp; Excitement)</span>
            </div>
            <ul>
                <li><strong>Targeted Social Media Promotion:</strong> Announcement graphics, stories, and hype reels across our official channels (Instagram, X / Twitter, and YouTube Shorts/Reels) highlighting event dates, speaker lineups, ticket updates, and venue information.</li>
                <li><strong>Ready-to-Use Attendee Companion:</strong> Configuring a dedicated {{ $event_name }} instance of CampBuddy with the full schedule, track details, and venue guide pre-loaded so attendees can access everything instantly on mobile without an app store download.</li>
            </ul>
        </div>

        <div class="deliverable-card">
            <div class="deliverable-phase-title">
                <span class="deliverable-phase-badge">Phase 2</span>
                <span>During the Event (Attendee Utility &amp; On-Ground Video Coverage)</span>
            </div>
            <ul>
                <li><strong>On-Ground Interactive Companion:</strong> Attendees use CampBuddy to see live "Happening Now" sessions, save talks to "My Day", swap digital Camp Cards with LinkedIn QR codes, and navigate Contributor Day tables easily.</li>
                <li><strong>On-Site Video Bites &amp; Reactions:</strong> Recording short video glimpses, speaker reactions, and attendee interview bites during both days to document the vibrancy and energy of the event.</li>
            </ul>
        </div>

        <div class="deliverable-card">
            <div class="deliverable-phase-title">
                <span class="deliverable-phase-badge">Phase 3</span>
                <span>After the Event (Recap &amp; Community Amplification)</span>
            </div>
            <ul>
                <li><strong>YouTube Video Coverage:</strong> Curated video coverage and highlight reels published directly on our official YouTube channels (@WPSimplifiedBySunil and @CampBuddyClub).</li>
                <li><strong>Official Recap Article:</strong> A detailed {{ $event_name }} retrospective article published on the official WPSimplified website (wpsimplified.in) covering highlights, session takeaways, and photos.</li>
            </ul>
        </div>

        <h2>What We Seek from {{ $event_name }}</h2>
        <ul>
            <li><strong>Official Listing:</strong> Recognition as an official Media Partner on the {{ $event_name }} website (name, logo, and backlink).</li>
            <li><strong>Media Passes:</strong> Standard media access passes for our team to facilitate on-site video recording, coverage, and attendee assistance.</li>
            <li><strong>Companion QR Visibility:</strong> Permission to display or share a simple QR code so attendees can scan and open their digital companion app seamlessly.</li>
        </ul>

        <h2>Official Links &amp; Verified Channels</h2>
        <div class="channels-list">
            <div class="channel-row">
                <div class="channel-entity">CampBuddy App:</div>
                <div class="channel-links">
                    <strong>Web:</strong> https://campbuddy.club/ <span>|</span>
                    <strong>YouTube:</strong> @CampBuddyClub <span>|</span>
                    <strong>Instagram:</strong> @campbuddyclub <span>|</span>
                    <strong>X:</strong> @CampBuddyClub
                </div>
            </div>
            <div class="channel-row">
                <div class="channel-entity">WPSimplified Hub:</div>
                <div class="channel-links">
                    <strong>Web:</strong> https://wpsimplified.in/ <span>|</span>
                    <strong>YouTube:</strong> @WPSimplifiedBySunil <span>|</span>
                    <strong>Instagram:</strong> @wpsimplified <span>|</span>
                    <strong>X:</strong> @iwpsimplified <span>|</span>
                    <strong>LinkedIn:</strong> company/wpsimplified
                </div>
            </div>
        </div>

        <h2>Ready-to-Publish Website Bios (For Event Website)</h2>
        <div class="bios-box">
            <div class="bio-item">
                <div class="bio-title">1. CampBuddy (Mobile Companion App Bio)</div>
                <div class="bio-text">
                    {{ $campbuddy_bio ?: 'CampBuddy (by WPSimplified) is an intelligent, mobile companion app engineered to transform how attendees experience WordCamps and developer conferences. Built from the ground up with a "guidance over information" philosophy, CampBuddy answers the one question every first-timer and seasoned attendee asks standing in a crowded venue: "What should I do right now?" Rather than serving static timetables, CampBuddy acts as a live on-ground co-pilot. Key features include real-time multi-track schedule tracking with bookmarking and timely session alerts, digital networking Camp Cards equipped with dynamic LinkedIn QR codes for instant connections, and interest-based attendee matching to help community members find like-minded collaborators effortlessly. To elevate event participation, CampBuddy integrates gamified exploration quests that guide attendees through the venue and directly toward sponsor booths. For Contributor Day, it features a skill-based table navigator that demystifies open collaboration, helping newcomers confidently find suitable teams to contribute. Sponsors benefit from interactive, ticket-style deal showcases featuring frictionless in-app lead capture forms that deliver verified attendee leads with genuine return on investment. Designed with a strict privacy-first architecture, CampBuddy requires no account creation, no logins, and collects no personal data—storing attendee preferences locally on the device while operating reliably offline even across congested conference Wi-Fi networks. CampBuddy bridges the gap between feeling lost and feeling connected, empowering every attendee to make the most of their event experience.' }}
                </div>
            </div>

            <div class="bio-item">
                <div class="bio-title">2. WPSimplified (Developer Education Platform Bio)</div>
                <div class="bio-text">
                    {{ $wpsimplified_bio ?: 'WPSimplified is a premier WordPress education platform and community led by senior technical architect and community leader Sunil Kumar Sharma. Operating with an uncompromising "zero gatekeeping" ethos, the platform is on a mission to democratize modern web engineering and empower the next generation of developers, freelancers, and digital creators. WPSimplified breaks down complex enterprise web topics—custom plugin architecture, theme development, scalable systems design, API integrations, and performance optimization—into simple, practical, step-by-step learning paths that anyone can follow with complete confidence. Recognizing that language barriers often hold back exceptional talent, WPSimplified actively bridges the regional divide in tech by delivering high-impact bilingual education in Hindi and English. Through comprehensive video tutorials, hands-on architectural workshops, live developer masterclasses, and practical podcasts, the platform demystifies coding fundamentals and real-world engineering standards. Beyond technical training, WPSimplified offers actionable career coaching and freelancing guidance, helping learners transition from feeling stuck to building scalable digital solutions, launching successful agencies, and mastering client communication. Supported by a vibrant, rapidly growing community of passionate developers and creators across South Asia and worldwide, WPSimplified provides the continuous mentorship, real-world insights, and community support needed to thrive. At WPSimplified, WordPress development is made simple, practical, and profitable—ensuring top-tier technical education is accessible to everyone with zero barriers.' }}
                </div>
            </div>

            <div class="bio-item">
                <div class="bio-title">3. Sunil Kumar Sharma (Lead Bio)</div>
                <div class="bio-text">
                    {{ $sunil_bio ?: 'Sunil Kumar Sharma is a Senior WordPress Developer and Technical Architect with over a decade of hands-on experience designing and engineering scalable web platforms, high-traffic digital ecosystems, and custom plugins. Deeply engaged in the WordPress community, Sunil has contributed extensively across South Asia as a speaker, organizer, volunteer, and mentor at numerous regional conferences, including WordCamp Kolhapur, WordCamp Nepal, WordCamp Bhopal, WordCamp Rajasthan, WordCamp Bengaluru, WordCamp Delhi, and WordCamp Asia. As the Lead at WPSimplified and CampBuddy, Sunil combines enterprise-level engineering acumen with an authentic commitment to grassroots impact. He is passionate about bridging the language divide in technology, dedicating his work to empowering non-metro developers and creators through regional language masterclasses, actionable content creation, and developer training. Sunil operates under a straightforward guiding philosophy: "zero gatekeeping—tech sabko sikhana is the vibe." His mission is ensuring that modern web development is never locked behind complex jargon or inaccessible barriers. Whether architecting scalable backend solutions, speaking on stage at tech conferences, or mentoring young developers taking their first steps in tech, Sunil brings a genuine community-first mindset to everything he builds. His focus remains steadfast on creating intelligent tools that enhance event experiences and producing practical educational resources that enable developers to build sustainable, scalable, and profitable digital careers.' }}
                </div>
            </div>
        </div>

        <p>We look forward to partnering with you to make {{ $event_name }} a grand success for the entire community!</p>

        <div class="signoff">
            <div class="signoff-name">{{ $applicant_name }}</div>
            <div class="signoff-role">{{ $applicant_role }}</div>
            <div style="font-size: 12px; color: #475569; margin-top: 2px;">
                Email: {{ $applicant_email }}
                @if($show_phone && $applicant_phone)
                    &bull; WhatsApp: {{ $applicant_phone }}
                @endif
            </div>
        </div>
    </div>

</body>
</html>
