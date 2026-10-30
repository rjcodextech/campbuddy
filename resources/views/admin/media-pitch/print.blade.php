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
                <img src="/media/campbuddy-wpsimplified-logo.svg?v={{ @filemtime(public_path('media/campbuddy-wpsimplified-logo.svg')) }}" alt="CampBuddy × WPSimplified">
            </div>
            <div class="brand-meta">
                <div class="badge-status">Official Partnership Proposal</div>
                <div>Date: {{ date('F Y') }}</div>
            </div>
        </header>

        <div class="meta-box">
            <div class="meta-label">To:</div>
            <div class="meta-value">{{ $event_name }} organizing team{{ $event_email ? ' ('.$event_email.')' : '' }}</div>

            <div class="meta-label">Event:</div>
            <div class="meta-value"><strong>{{ $event_name }}</strong>{{ $event_dates ? ' · '.$event_dates : '' }}{{ $event_venue ? ' · '.$event_venue : '' }}</div>

            <div class="meta-label">From:</div>
            <div class="meta-value">{{ $applicant_name }} ({{ $applicant_role }})</div>

            <div class="meta-label">Subject:</div>
            <div class="meta-value"><strong>{{ $subject }}</strong></div>
        </div>

        {{-- The words: App\Support\MediaPitch::sections(), the same as the copied email. --}}
        <p>Dear {{ $event_name }} organizing team,</p>
        @foreach ($sections['intro'] as $paragraph)
            <p>{{ $paragraph }}</p>
        @endforeach

        <h2>Who We Are</h2>
        <div class="brand-highlight-grid">
            @foreach ($sections['who'] as $who => $text)
                @php([$brand, $site] = array_pad(explode(' (', rtrim($who, ')'), 2), 2, ''))
                <div class="brand-card">
                    <div class="brand-card-title">
                        <span>{{ $brand }}</span>
                        <small style="font-size: 11px; font-weight: 500; color: #475569;">{{ preg_replace('#^https?://#', '', $site) }}</small>
                    </div>
                    <div class="brand-card-desc">{{ $text }}</div>
                </div>
            @endforeach
        </div>

        <h2>What We Will Do</h2>
        @foreach (['before' => 'Before the event', 'during' => 'During the event', 'after' => 'After the event'] as $phase => $title)
            <div class="deliverable-card">
                <div class="deliverable-phase-title">
                    <span class="deliverable-phase-badge">Phase {{ $loop->iteration }}</span>
                    <span>{{ $title }}</span>
                </div>
                <ul>
                    @foreach ($sections[$phase] as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            </div>
        @endforeach
        <p>{{ $sections['promise'] }}</p>

        <h2>What We Ask For</h2>
        <ul>
            @foreach ($sections['ask'] as $item)
                <li>{{ $item }}</li>
            @endforeach
        </ul>

        <h2>Official Links &amp; Verified Channels</h2>
        <div class="channels-list">
            <div class="channel-row">
                <div class="channel-entity">CampBuddy:</div>
                <div class="channel-links">
                    <strong>Web:</strong> https://campbuddy.club/ <span>|</span>
                    <strong>YouTube:</strong> @CampBuddyClub <span>|</span>
                    <strong>Instagram:</strong> @campbuddyclub <span>|</span>
                    <strong>X:</strong> @CampBuddyClub
                    @if ($event_link)
                        <br><strong>{{ $event_name }} on CampBuddy:</strong> {{ $event_link }}
                    @endif
                </div>
            </div>
            <div class="channel-row">
                <div class="channel-entity">WPSimplified:</div>
                <div class="channel-links">
                    <strong>Web:</strong> https://wpsimplified.in/ <span>|</span>
                    <strong>YouTube:</strong> @WPSimplifiedBySunil <span>|</span>
                    <strong>Instagram:</strong> @wpsimplified <span>|</span>
                    <strong>X:</strong> @iwpsimplified <span>|</span>
                    <strong>LinkedIn:</strong> company/wpsimplified
                </div>
            </div>
        </div>

        <h2>Bios for Your Website</h2>
        <div class="bios-box">
            @foreach ($bios as $bio)
                <div class="bio-item">
                    <div class="bio-title">{{ $loop->iteration }}. {{ $bio['label'] }}</div>
                    <div class="bio-text">
                        <p style="margin: 0 0 6px;"><em>Short:</em> {{ $bio['short'] }}</p>
                        @foreach (preg_split('/\n\s*\n/', $bio['full']) as $paragraph)
                            <p style="margin: 0 0 6px;">{{ $paragraph }}</p>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <p>{{ $sections['closing'] }}</p>

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
