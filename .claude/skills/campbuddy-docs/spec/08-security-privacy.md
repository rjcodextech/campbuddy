# 8. Security & privacy

[← Back to index](../SKILL.md) · Previous: [7. Data model](07-data-model.md) · Next: [9. Admin panel →](09-admin-panel.md)

Carries forward all of V1's security posture (prepared statements, secrets handling, HTTPS, security headers, admin session hardening, Argon2id, error handling, outbound request timeouts, file exposure rules, client-side escaping) unchanged. V2-specific additions below.

## 8.1 Rate limiting
Moves to the CDN edge ([5.4](05-system-architecture.md#54-cdn--now-mandatory-not-optional)). DB-backed limiting is retained only as a secondary control on the admin login route.

## 8.2 Ingestion jobs
Treated as outbound requests to a third party — same strict timeouts, same "validate before trusting" posture. Applies to the REST client against each event's own `wp-json` ([finding 0.1](00-findings.md)), the Attendees-page HTML parser ([finding 0.3](00-findings.md), the one part of the pipeline without a stable contract, needing the markup-change alert from [3.3](03-functional-requirements/03-roster-ingestion.md) IN4), and (post-launch) the `events.wordpress.org` discovery scraper (same "no stable contract, alert on structural change" posture — see [5.3](05-system-architecture.md#53-central-event-discovery)).

## 8.3 Matching data — the "local by default, shared only by choice" architecture
Matching ([3.4](03-functional-requirements/04-matching.md)) is the one place V2 asks a user to share more than V1 ever did, so it gets a deliberate privacy boundary rather than a general opt-in checkbox:

- The attendee's full profile (onboarding answers, [3.11](03-functional-requirements/11-onboarding.md)) is **local-only** and never touches a CampBuddy server, matching or not.
- Becoming discoverable requires a **separate, explicit action** ("Join attendee discovery," [3.4](03-functional-requirements/04-matching.md) M2) — never a side effect of filling in onboarding or any other screen.
- What gets published is a **user-chosen subset** of fields under a **random, non-guessable identifier** — never the attendee's real name, device ID, or full profile.
- Leaving discovery ([3.4](03-functional-requirements/04-matching.md) M6) is **one tap**, immediate, and independent from deleting all local data ([3.13](03-functional-requirements/13-data-controls.md)).
- Discovery data **expires with the event** ([3.4](03-functional-requirements/04-matching.md) M7) — it doesn't quietly persist into next year's WordCamp.
- Discovery data is **never sent to analytics** (extends the GA4 exclusion rule in [8.5](#85-analytics-guardrail)).

## 8.4 Ingested roster data — explicit policy
This is other people's data, even though it's already public and opt-in on the source site. CampBuddy:
- Stores only what the source page itself displays (name, gravatar URL, self-provided links) — never anything CampBuddy infers or adds.
- Provides a visible, no-login-required way for anyone to request removal, honored via the `attendee_roster` suppression flag ([3.3](03-functional-requirements/03-roster-ingestion.md) IN5).
- Never re-publishes this data anywhere CampBuddy doesn't control (no export, no API for third parties).
- Purges roster data for an event once it moves to `archived` status.

## 8.5 Analytics guardrail
Any `discovery_profiles` field, any locally-stored onboarding/Camp Card/matching data, and any `attendee_roster` field are **never** passed to the `track()` wrapper — enforced by a wrapper-level allowlist, not developer memory alone.

> **Current implementation note:** GA4 is now wired up for the attendee app, and this guardrail is enforced exactly as described: `resources/js/attendee/analytics.js` exports the only `track()`, with a per-event **parameter allowlist** — an unlisted event or param is dropped, so a stray `track('x', { email })` is a no-op. Page URLs are also scrubbed of query strings before any hit leaves the browser (so e.g. the roster-removal name search never reaches Google), browsers sending GPC / Do Not Track get no tag, and the admin panel is not tracked. One thing code can't enforce: GA's *Enhanced measurement → Outbound clicks* must be switched off in the GA console, or it would report roster attendees' external links. Full detail, event catalogue and console checklist: [22](22-analytics.md).

## 8.6 Discovery API ownership — a real gap, caught and fixed here
An earlier draft had a genuine flaw: `discovery_id` is necessarily public — every client fetching the match list sees every other attendee's `discovery_id`. If that same public ID were also the sole credential required to update or delete a profile, **any attendee could silently edit or delete any other attendee's discovery profile** just by having seen it in the match list.

**Fix, reflected in [3.4](03-functional-requirements/04-matching.md) M2/M6/M8 and [7](07-data-model.md)/[10](10-api-specification.md):** creation issues two separate values — a public `discovery_id` (safe to show to everyone) and a private **owner token** (returned once at creation, stored only in the creating device's local storage, never displayed, never included in the public GET list). Every mutating call requires the owner token as a bearer credential; the public `discovery_id` alone authorizes nothing.

**Implementation note:** store only a hash of the owner token server-side (`owner_token_hash`) — never the raw token — same principle as password storage.
