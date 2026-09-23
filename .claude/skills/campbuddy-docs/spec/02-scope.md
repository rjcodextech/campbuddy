# 2. Scope

[← Back to index](../SKILL.md) · Previous: [1. Project overview](01-project-overview.md) · Next: [3. Functional requirements →](03-functional-requirements/00-index.md)

## 2.1 In scope for V2 — Must-have for Oct 3–4 (WordCamp Rajasthan launch)

These are the items that had to work for the Oct 3–4 ship, scoped exactly as written — no gold-plating. [1. Project overview](01-project-overview.md) describes CampBuddy's full intended depth; this list was the honest subset of that depth achievable for one event in ten days. Where a §3 requirement ID isn't listed here at all, assume it was fast-follow (§2.2), not dropped.

- **F1.** Home ([3.1](03-functional-requirements/01-home.md) H1–H6) — Happening Now / Up Next / Suggested Action / Progress, working against WCR's real schedule data.
- **F2.** WCR 2026 added manually through the admin panel's event CRUD ([9](09-admin-panel.md)) as the one live, visible event — no dependency on central discovery for launch (that was fast-follow, §2.2).
- **F3.** Speakers/sponsors/sessions ingestion for WCR via REST ([0.1](00-findings.md), [5.2](05-system-architecture.md#52-ingestion-flow-new-in-v2)) — the foundation everything else in this list reads from.
- **F4.** Attendee roster ingestion for WCR ([3.3](03-functional-requirements/03-roster-ingestion.md)) — scheduled job parses the public Attendees page, stores name/gravatar/links, updates daily.
- **F5.** My Day & session details ([3.8](03-functional-requirements/08-my-day.md) MD1–MD5): Full Schedule + My Schedule, session detail, overlap warning, offline availability.
- **F6.** Quest ([3.6](03-functional-requirements/06-quest.md) C1–C5): default + WCR-specific quests, no points/leaderboards, local progress.
- **F7.** Contribute ([3.9](03-functional-requirements/09-contribute.md) CD1–CD4) with the fixed curated team list (CD2's launch scope, not the dynamic full list).
- **F8.** Explore → People: interest-based matching with the full anonymous-discovery architecture ([3.4](03-functional-requirements/04-matching.md) M1–M7, [8.3](08-security-privacy.md#83-matching-data--the-local-by-default-shared-only-by-choice-architecture)) — this is a privacy-load-bearing feature and ships correctly-architected or not at all; there is no "simplified but less private" fallback worth building.
- **F9.** Explore → Sponsors & Deals & Event Information ([3.7](03-functional-requirements/07-deals.md), [3.12](03-functional-requirements/12-sponsors-event-info.md) EI1–EI3).
- **F10.** Camp Card v2 ([3.10](03-functional-requirements/10-camp-card.md) CC1–CC6): full field set, attendee-chosen visible fields, QR to attendee-chosen link, fullscreen display.
- **F11.** Onboarding ([3.11](03-functional-requirements/11-onboarding.md) OB1–OB4): 4-step flow, under 2 minutes, skippable.
- **F12.** Session bookmarking + in-app "starting soon" banner ([3.5](03-functional-requirements/05-notifications.md) N1–N2, N4). Web Push is attempted where supported ([finding 0.1](00-findings.md)) but the in-app banner is the guaranteed path — push was never allowed to become a launch blocker.
- **F13.** Per-event branding for WCR ([3.2](03-functional-requirements/02-branding.md) BR1–BR7): best-effort auto-fetch of logo/favicon with admin upload as the fallback/override. *(Note: per-event color theming described here was later removed app-wide in favor of one consistent default palette — see [3.2](03-functional-requirements/02-branding.md) for the current state.)*
- **F14.** Data controls ([3.13](03-functional-requirements/13-data-controls.md) DP1–DP4): Export/Clear, reachable contextually.
- **F15.** Accessibility, performance, error-handling, and empty-state floors ([4.1–4.3](04-non-functional-requirements.md)) — launch-blocking *qualities* of the features above, not a separate feature scheduled at the end.
- **F16.** Laravel backend covering all of the above, deployed behind a CDN ([5.4](05-system-architecture.md#54-cdn--now-mandatory-not-optional)).
- **F17.** Pre-launch blocker checklist closed ([13](13-prelaunch-blockers.md)): `composer audit` clean, manual OWASP pass done, the acceptance journey ([15.2](15-testing.md#152-core-user-journey-acceptance-test)) green, LICENSE committed.

## 2.2 In scope for V2 — Fast-follow (after Oct 3–4, before the next WordCamp)

- Multi-event **browsing**: the home page listing several upcoming WordCamps, not just WCR (ingestion pipeline built generically from day one per [6](06-technology-stack.md), but the UI for picking *among* events, and running ingestion for more than one event concurrently, shipped after WCR). *(Note: this has since shipped — see [Project overview](01-project-overview.md) and [5.3](05-system-architecture.md#53-central-event-discovery-fast-follow-not-required-for-launch).)*
- Central discovery job to find upcoming WordCamps, with an admin approval step before any new event goes live in the app (never auto-publish a discovered event). See [5.3](05-system-architecture.md#53-central-event-discovery-fast-follow-not-required-for-launch) for the discovery source (which changed after this section was originally written).
- Full per-event theming engine (arbitrary colors/logo per event, not just WCR's). *(Note: superseded — per-event color theming was removed entirely rather than expanded; see [3.2](03-functional-requirements/02-branding.md).)*
- True Web Push reliability improvements, notification preferences.
- Contributor Day ([3.9](03-functional-requirements/09-contribute.md) CD2) expanded to cover the full, dynamically-current list of official WordPress contributor teams rather than the fixed launch list.

## 2.3 Explicitly out of scope

- User accounts/login (standing product decision, carried from V1).
- "Scan a card" / decoding another attendee's QR into a contact list.
- Storing or displaying anything from WordCamp ticketing/registration systems beyond what the event's own public, opt-in Attendees page already shows. **CampBuddy never has access to, and never will fetch, ticket purchase data, email addresses, or any attendee data that isn't already opted into public display by the attendee themselves.**
- ~~Lead-capture forms on offers.~~ *(Note: reversed by explicit later product decision — Deals now supports an optional, per-deal Name/Email/Mobile capture form. See [3.7](03-functional-requirements/07-deals.md).)*
- Admin-managed image uploads **for CampBuddy's own app-wide brand assets** (icon, mascot — still a file-replace + redeploy, unchanged from V1). **Per-event** logo/favicon upload is in scope ([3.2](03-functional-requirements/02-branding.md) BR4/BR5) — a deliberate, narrow reversal of V1's blanket "no image uploads" limitation.
- Dark mode (declined in V1, carried forward).
- Multi-tenant support for non-WordCamp events.
