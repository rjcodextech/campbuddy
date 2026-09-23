# 3.7 Deals (Explore → Deals, offers carried from V1, event-scoped)

[← Index](00-index.md) · Previous: [3.6 Quest](06-quest.md) · Next: [3.8 My Day & session details →](08-my-day.md)

A deal is only shown while its event is active, and does not roll over to the next event unless the sponsor re-submits it (admin re-activates it against the new event). Each deal displays: the sponsor, a description of the offer, coupon code (where applicable), expiry, a redemption link, and terms. CampBuddy is not an advertising platform — a deal only belongs here if it's a genuine attendee benefit, not a generic ad; this is an editorial judgment call for whoever manages Offers in the admin panel, not something the software enforces.

## Lead capture (added after launch — reverses [2.3](../02-scope.md#23-explicitly-out-of-scope)'s original "no lead-capture forms" exclusion)

Each deal has an admin-configurable **per-deal** toggle (`capture_leads`), off by default:

- When **off**, tapping a deal behaves as originally speced above — opens straight to the redemption link (in the in-app browser, see [5.5](../05-system-architecture.md#55-asset-build--laravels-default-vite-pipeline)/UI notes below).
- When **on**, tapping the deal first shows a short form — **Name** (required), **Email** (required), **Mobile** (optional) — with a one-line disclosure that the details are shared with the sponsor to process the deal. Submitting stores the lead server-side (event-scoped, tied to the specific deal) and only then opens the deal.
- Captured leads are visible to admins per event, filterable by deal and date range, and exportable as CSV from the admin panel.
- Lead data is excluded from analytics by the same PII-exclusion posture as [8.5](../08-security-privacy.md#85-analytics-guardrail).

## In-app browsing

Both sponsor links and deal redemption links open in an in-app browser overlay rather than a new browser tab, so tapping one doesn't fully leave CampBuddy — see [Why it's different](../../about/05-why-different.md). Many third-party sites block being framed (`X-Frame-Options`/CSP); there's no reliable way to detect that in advance, so the in-app browser always keeps a visible "open in your browser" escape hatch rather than leaving the attendee stuck.
