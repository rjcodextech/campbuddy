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

Deal redemption links open in an in-app browser overlay (sponsor chips on Explore → Sponsors are display-only since 29 Sep 2026 — sponsor links reach attendees through their deals) rather than a new browser tab, so tapping one doesn't fully leave CampBuddy — see [Why it's different](../../about/05-why-different.md). Many third-party sites block being framed (`X-Frame-Options`/CSP); there's no reliable way to detect that in advance, so the in-app browser always keeps a visible "open in your browser" escape hatch rather than leaving the attendee stuck.

## Default deals, richer cards and per-deal forms (29 Sep 2026)

> **Default deals.** An `offers` row with `event_id = NULL` is a default deal, managed under **Admin → Default deals** (`DefaultDealController`, `/admin/deals`). It shows at every event whose country (`EventCountry::code`) is in `offers.countries` (empty = every country), including events discovered later, after the event's own deals (`Offer::shownAt`). An event can hide one from its own Deals page (`offer_event_hidden`, "Hide here" / "Show here"). Leads keep their `event_id`, so a default deal's leads page lists them across events (with CSV). Editing an event deal is only possible from its own event (404 otherwise); a default deal only from Default deals.
>
> **Shipped India deals.** Migration `2026_10_04_090100_install_default_india_deals` runs `App\Support\DefaultDeals::install()` (skipped in unit tests): Ariham Technologies (free website health report), Hostinger (20% off, referral link), Automattic / WordPress.com (69% off select plans, affiliate link), Knit Pay (100 free transactions/month for 6 months, contact form). Idempotent (matched on link); logos ship in `public/media/deals/` and are copied into the Media Library. An event's older own deal for the same company (same host) with no leads is switched off, not deleted (WordCamp Rajasthan's plain "Hostinger" link).
>
> **Card** (`attendee/partials/deal-card.blade.php`): logo tile, company (`brand`) + `website` (falls back to the link's domain), `highlight` tag, headline (`title`), details (`description`, ≤500), `terms`, optional `coupon_code` with Copy, button text `cta_label`. `opens_in_app = false` renders a plain `target=_blank rel="noopener sponsored"` link so referral/affiliate credit survives (framed sites lose cookies, and many refuse framing). Older deals (no brand) render as before in content and open in the app.
>
> **Per-deal contact form** (`offers.lead_form`, `App\Support\DealForm`, JS `deal-form.js` + `deal-leads.js`): name / company / email / phone each off, optional or required with its own label and hint (email is always required — it is the contact and the de-duplication key per deal + event), an intro line, and an optional list of products to tick (single or multiple, optional or required, ≤12). Empty = the original form (name + email required, mobile optional). Leads store `company` and `choices`; `name` may be null. After sending, a new-tab deal shows a "done" step with a real link (no pop-up blocker issue); an in-app deal opens the in-app browser as before. Tests: `DefaultDealsTest`, `tests/js/deal-form.test.mjs`.
