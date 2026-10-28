<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $event_name }} — Media Partnership Proposal | CampBuddy × WPSimplified</title>
    <style>
        @page {
            size: A4;
            margin: 20mm 18mm 20mm 18mm;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #231F20;
            background: #ffffff;
            line-height: 1.55;
            font-size: 13.5px;
            padding: 30px 40px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #047857;
            padding-bottom: 14px;
            margin-bottom: 20px;
        }
        .brand-title {
            font-size: 22px;
            font-weight: 700;
            color: #047857;
            letter-spacing: -0.01em;
        }
        .brand-sub {
            font-size: 13px;
            color: #5F6F69;
            margin-top: 2px;
            font-weight: 500;
        }
        .brand-meta {
            text-align: right;
            font-size: 12px;
            color: #5F6F69;
            line-height: 1.4;
        }
        .meta-box {
            background: #F6F8F4;
            border: 1px solid #EADFD6;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 20px;
            font-size: 13px;
            display: grid;
            grid-template-columns: 120px 1fr;
            gap: 6px 14px;
        }
        .meta-label {
            font-weight: 600;
            color: #43544E;
        }
        .meta-value {
            color: #231F20;
        }
        h2 {
            font-size: 15px;
            font-weight: 700;
            color: #047857;
            margin: 18px 0 8px 0;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        p {
            margin-bottom: 10px;
        }
        ul {
            margin: 0 0 12px 20px;
        }
        li {
            margin-bottom: 5px;
        }
        .deliverable-phase {
            font-weight: 700;
            color: #0B1F1A;
            margin-top: 8px;
            margin-bottom: 4px;
            display: block;
        }
        .table-links {
            width: 100%;
            border-collapse: collapse;
            margin: 14px 0 18px 0;
            font-size: 12.5px;
        }
        .table-links th {
            background: #047857;
            color: #ffffff;
            font-weight: 600;
            text-align: left;
            padding: 7px 10px;
        }
        .table-links td {
            border: 1px solid #E5E7EB;
            padding: 6px 10px;
            color: #231F20;
        }
        .table-links tr:nth-child(even) td {
            background: #F9FAFB;
        }
        .signoff {
            margin-top: 24px;
            padding-top: 14px;
            border-top: 1px solid #E5E7EB;
        }
        .signoff-name {
            font-weight: 700;
            font-size: 14px;
            color: #047857;
        }
        .signoff-role {
            font-size: 13px;
            color: #43544E;
        }
        .toolbar {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #0B1F1A;
            color: #fff;
            padding: 10px 16px;
            border-radius: 30px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.25);
            display: flex;
            gap: 12px;
            align-items: center;
            z-index: 9999;
        }
        .toolbar button {
            background: #047857;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
        }
        .toolbar button:hover {
            background: #059669;
        }
        .toolbar a {
            color: #E5E7EB;
            text-decoration: none;
            font-size: 13px;
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
            .table-links th {
                background: #047857 !important;
                color: #ffffff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

    <div class="toolbar no-print">
        <span>Ready to Export</span>
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
        <a href="javascript:window.close()">Close</a>
    </div>

    <div class="container">
        <header class="header">
            <div>
                <div class="brand-title">WordCamp Media Partnership Proposal</div>
                <div class="brand-sub">Presented by CampBuddy &times; WPSimplified</div>
            </div>
            <div class="brand-meta">
                <div>Date: {{ date('F Y') }}</div>
                <div>Status: Official Application</div>
            </div>
        </header>

        <div class="meta-box">
            <div class="meta-label">To:</div>
            <div class="meta-value">{{ $event_name }} Organizing Team ({{ $event_email }})</div>

            <div class="meta-label">Event:</div>
            <div class="meta-value">{{ $event_name }} &bull; {{ $event_dates }} &bull; {{ $event_venue }}</div>

            <div class="meta-label">From:</div>
            <div class="meta-value">{{ $applicant_name }} ({{ $applicant_role }})</div>

            <div class="meta-label">Subject:</div>
            <div class="meta-value"><strong>{{ $subject }}</strong></div>
        </div>

        <p>Dear {{ $event_name }} Organizing Team,</p>
        <p>Greetings from the team at CampBuddy and WPSimplified!</p>
        <p>We are writing to officially submit our application for the <strong>Media Partnership for {{ $event_name }}</strong> ({{ $event_dates }}, {{ $event_venue }}). We want to propose a focused, transparent, and grounded collaboration strictly centered around high-value deliverables that we will genuinely execute to support your attendees, speakers, and sponsors.</p>

        <h2>Who We Are</h2>
        <ul>
            <li><strong>WPSimplified (wpsimplified.in):</strong> A dedicated educational platform and community simplifying WordPress for freelancers, beginners, and developers through actionable video tutorials, guides, and practical insights.</li>
            <li><strong>CampBuddy (campbuddy.club):</strong> A free, open-source (GPL-2.0) mobile PWA built specifically as a digital companion for WordCamp attendees. It answers the one question every first-timer asks: <em>"What do I do next?"</em> — featuring live schedule guidance, digital networking badges (Camp Card), sponsor quests, and Contributor Day table navigation.</li>
        </ul>

        <h2>Committed Deliverables (Clear &amp; Focused)</h2>
        
        <span class="deliverable-phase">1. Before the Event (Awareness &amp; Excitement)</span>
        <ul>
            <li><strong>Targeted Social Media Campaigns:</strong> Announcement graphics, stories, and hype reels across our official channels (Instagram, X / Twitter, and YouTube Shorts/Reels) highlighting event dates, speaker lineups, ticket updates, and venue information.</li>
            <li><strong>Ready-to-Use Attendee Companion:</strong> Configuring a dedicated {{ $event_name }} instance of CampBuddy with the full schedule, track details, and venue guide pre-loaded so attendees can access everything instantly on mobile without needing an app store download.</li>
        </ul>

        <span class="deliverable-phase">2. During the Event (Attendee Utility &amp; On-Ground Video)</span>
        <ul>
            <li><strong>On-Ground Interactive Companion:</strong> Attendees use CampBuddy to see live "Happening Now" sessions, save talks to "My Day", swap digital Camp Cards with LinkedIn QR codes, and navigate Contributor Day tables easily.</li>
            <li><strong>On-Site Video Bites &amp; Reactions:</strong> We will record short video glimpses, speaker reactions, and attendee interview bites during both days to document the vibrancy and energy of the event.</li>
        </ul>

        <span class="deliverable-phase">3. After the Event (Recap &amp; Community Amplification)</span>
        <ul>
            <li><strong>YouTube Video Coverage:</strong> Curated video coverage and highlight reels published directly on our official YouTube channels (@WPSimplifiedBySunil and @CampBuddyClub).</li>
            <li><strong>Official Recap Article:</strong> A detailed retrospective article published on the official WPSimplified website (wpsimplified.in) covering highlights, session takeaways, and photos.</li>
        </ul>

        <h2>What We Seek from {{ $event_name }}</h2>
        <ul>
            <li><strong>Official Listing:</strong> Recognition as an official Media Partner on the {{ $event_name }} website (name, logo, and backlink).</li>
            <li><strong>Media Passes:</strong> Standard media access passes for our team to facilitate on-site video recording, coverage, and attendee assistance.</li>
            <li><strong>Companion QR Visibility:</strong> Permission to display or share a simple QR code so attendees can scan and open their digital companion app seamlessly.</li>
        </ul>

        <h2>Official Links &amp; Brand Channels</h2>
        <table class="table-links">
            <thead>
                <tr>
                    <th style="width: 25%;">Platform</th>
                    <th style="width: 37%;">CampBuddy</th>
                    <th style="width: 38%;">WPSimplified</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Website</strong></td>
                    <td>https://campbuddy.club/</td>
                    <td>https://wpsimplified.in/</td>
                </tr>
                <tr>
                    <td><strong>YouTube</strong></td>
                    <td>youtube.com/@CampBuddyClub</td>
                    <td>youtube.com/@WPSimplifiedBySunil</td>
                </tr>
                <tr>
                    <td><strong>Instagram</strong></td>
                    <td>instagram.com/campbuddyclub</td>
                    <td>instagram.com/wpsimplified</td>
                </tr>
                <tr>
                    <td><strong>X (Twitter)</strong></td>
                    <td>x.com/CampBuddyClub</td>
                    <td>x.com/iwpsimplified</td>
                </tr>
                <tr>
                    <td><strong>LinkedIn</strong></td>
                    <td>—</td>
                    <td>linkedin.com/company/wpsimplified/</td>
                </tr>
            </tbody>
        </table>

        <p>We look forward to partnering with you to make {{ $event_name }} a memorable experience for the entire community!</p>

        <div class="signoff">
            <div class="signoff-name">{{ $applicant_name }}</div>
            <div class="signoff-role">{{ $applicant_role }}</div>
            <div style="font-size: 12.5px; color: #5F6F69; margin-top: 3px;">
                Email: {{ $applicant_email }}
                @if($show_phone && $applicant_phone)
                    &bull; WhatsApp: {{ $applicant_phone }}
                @endif
            </div>
        </div>
    </div>

</body>
</html>
