<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>New Sponsor Deal Submission</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f6f5f3; color: #1e293b; padding: 24px; margin: 0; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; border: 1px solid #e2e8f0; overflow: hidden; }
        .header { background: #1a2238; color: #ffffff; padding: 20px 24px; }
        .header h1 { margin: 0; font-size: 20px; font-weight: 600; }
        .header p { margin: 4px 0 0; font-size: 13px; color: #cbd5e1; }
        .body { padding: 24px; }
        .badge { display: inline-block; padding: 3px 8px; font-size: 11px; font-weight: 600; text-transform: uppercase; border-radius: 4px; background: #fef3c7; color: #92400e; }
        .section-title { font-size: 12px; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em; margin: 18px 0 8px; }
        .card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px 16px; margin-bottom: 16px; }
        .row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px; }
        .row:last-child { margin-bottom: 0; }
        .label { color: #64748b; font-weight: 500; }
        .value { color: #0f172a; font-weight: 600; text-align: right; }
        .cta-btn { display: inline-block; background: #7c2d37; color: #ffffff !important; text-decoration: none; padding: 10px 20px; border-radius: 6px; font-weight: 600; font-size: 14px; margin-top: 16px; }
        .footer { padding: 16px 24px; background: #f8fafc; border-top: 1px solid #e2e8f0; font-size: 12px; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>New Sponsor Deal Submitted</h1>
            <p>{{ $submission->event?->display_name }} &bull; {{ $submission->sponsor_name }}</p>
        </div>
        <div class="body">
            <p style="margin-top: 0; font-size: 14px; line-height: 1.5;">
                A sponsor has submitted a deal for review. It is currently held in temporary draft status pending your approval.
            </p>

            <div class="section-title">Deal Details</div>
            <div class="card">
                <div class="row">
                    <span class="label">Event:</span>
                    <span class="value">{{ $submission->event?->display_name }}</span>
                </div>
                <div class="row">
                    <span class="label">Sponsor / Brand:</span>
                    <span class="value">{{ $submission->displayBrand() }}</span>
                </div>
                <div class="row">
                    <span class="label">Offer Title:</span>
                    <span class="value">{{ $submission->title }}</span>
                </div>
                @if ($submission->highlight)
                    <div class="row">
                        <span class="label">Highlight / Tag:</span>
                        <span class="value">{{ $submission->highlight }}</span>
                    </div>
                @endif
                @if ($submission->coupon_code)
                    <div class="row">
                        <span class="label">Coupon Code:</span>
                        <span class="value"><code style="background: #e2e8f0; padding: 2px 6px; border-radius: 4px;">{{ $submission->coupon_code }}</code></span>
                    </div>
                @endif
                <div class="row">
                    <span class="label">Deal Link:</span>
                    <span class="value"><a href="{{ $submission->url }}" target="_blank" style="color: #7c2d37;">{{ $submission->url }}</a></span>
                </div>
                <div style="margin-top: 12px; font-size: 13px; color: #334155; line-height: 1.4; border-top: 1px dashed #cbd5e1; padding-top: 10px;">
                    <strong>Description:</strong><br>
                    {{ $submission->description }}
                </div>
                @if ($submission->terms)
                    <div style="margin-top: 8px; font-size: 12px; color: #64748b;">
                        <strong>Terms:</strong> {{ $submission->terms }}
                    </div>
                @endif
            </div>

            <div class="section-title">Submitter Information</div>
            <div class="card">
                <div class="row">
                    <span class="label">Submitted by:</span>
                    <span class="value">{{ $submission->submitter_name }}</span>
                </div>
                <div class="row">
                    <span class="label">Work Email:</span>
                    <span class="value"><a href="mailto:{{ $submission->submitter_email }}">{{ $submission->submitter_email }}</a></span>
                </div>
                @if ($submission->submitter_phone)
                    <div class="row">
                        <span class="label">Phone:</span>
                        <span class="value">{{ $submission->submitter_phone }}</span>
                    </div>
                @endif
                @if ($submission->notes)
                    <div style="margin-top: 10px; font-size: 13px; color: #475569; border-top: 1px dashed #cbd5e1; padding-top: 8px;">
                        <strong>Submitter Notes:</strong><br>
                        {{ $submission->notes }}
                    </div>
                @endif
            </div>

            <div style="text-align: center;">
                <a href="{{ route('admin.deal-submissions.index') }}" class="cta-btn">Review & Approve in Admin &rarr;</a>
            </div>
        </div>
        <div class="footer">
            CampBuddy Deal Review Notification &bull; This email was automatically generated upon submission.
        </div>
    </div>
</body>
</html>
