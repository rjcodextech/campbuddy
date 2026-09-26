# 8. Security & privacy

[← Back to index](../SKILL.md) · Previous: [7. Data model](07-data-model.md) · Next: [9. Admin panel →](09-admin-panel.md)

Carries forward all of V1's security posture (prepared statements, secrets handling, HTTPS, security headers, admin session hardening, Argon2id, error handling, outbound request timeouts, file exposure rules, client-side escaping) unchanged. V2-specific additions below.

## 8.1 Rate limiting
Moves to the CDN edge ([5.4](05-system-architecture.md#54-cdn--now-mandatory-not-optional)). DB-backed limiting is retained only as a secondary control on the admin login route.

> **Current implementation note (app-level limits):** the API throttle now uses two **named** limiters (`AppServiceProvider`) — `api-general` (60 requests/min/IP, everything under `/api/v1`) and `api-writes` (10/min/IP: discovery POST/PATCH/DELETE, deal leads, **push subscribe**). They used to be two plain `throttle:N,M` middleware, which share *one* counter per IP — every write counted twice and ate the read budget, so "10 writes a minute" was really about five. Behind venue wifi many attendees share one IP: if writes get refused at a busy event, raise `api-writes` (or move the primary limit to the Cloudflare edge as above).

## 8.2 Ingestion jobs
Treated as outbound requests to a third party — same strict timeouts, same "validate before trusting" posture. Applies to the REST client against each event's own `wp-json` ([finding 0.1](00-findings.md)), the Attendees-page HTML parser ([finding 0.3](00-findings.md), the one part of the pipeline without a stable contract, needing the markup-change alert from [3.3](03-functional-requirements/03-roster-ingestion.md) IN4), and (post-launch) the `events.wordpress.org` discovery scraper (same "no stable contract, alert on structural change" posture — see [5.3](05-system-architecture.md#53-central-event-discovery)).

## 8.2a Hardening pass (full review)

What a full security/performance review added or fixed, in one place:

- **Response headers** (`App\Http\Middleware\SecurityHeaders`, on every response): `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN` (clickjacking — the admin panel especially), `Referrer-Policy: strict-origin-when-cross-origin`, and a `Permissions-Policy` that switches off camera/mic/geolocation/payment/usb. **Deliberately not set here:** a Content-Security-Policy (the pages carry inline analytics/styles — write and test one against a real deployment) and HSTS (best set once at Cloudflare, where the https decision is made).
- **Push endpoint SSRF.** `POST …/push/subscribe` stores an address the server later POSTs to, so the endpoint must be `https` and pass `NotPrivateNetworkUrl` (no loopback/private/link-local/metadata addresses). It also moved into the 10/min write limiter.
- **CSV formula injection.** The admin's deal-leads export writes what anonymous visitors typed; a cell starting with `= + - @` (or tab/CR) is prefixed with `'` so a spreadsheet can't run it. Phone-style values like `+91 98765 43210` are left alone.
- **Only web addresses become links.** `App\Support\SafeUrl::web()` (http/https only): the roster scraper, the roster API output (so rows stored earlier are covered too) and the roster JS all drop `javascript:`/`data:` hrefs and avatars — attendees type those links themselves. Deal and event-site URLs are validated `url:http,https`.
- **SVG uploads.** The media library now runs `SvgGuard` (as event logos already did) — an SVG with a `<script>` is refused; the storage route additionally serves SVGs with a sandboxing CSP.
- **In-app browser.** `openInAppBrowser()` refuses non-http(s) URLs and the iframe is `sandbox`ed **without** `allow-top-navigation`, so a framed sponsor page can't redirect the app itself.
- **No sessions or cookies for attendees.** Attendee pages, the manifest and `/storage/*` run without `StartSession`/cookies/CSRF (`Route::withoutMiddleware` in `routes/web.php`) — before, every page view wrote a session row and sent two `Set-Cookie` headers to people who never log in. Only the roster-takedown form (CSRF + flash message) and the admin panel keep sessions. The unused `csrf-token` meta tag is gone from the attendee layout.
- **Accepted, by design:** the roster takedown (`POST …/roster-removal/{entry}`) is public and unauthenticated on purpose (a takedown that needs an account defeats its purpose); it is throttled 20/min/IP, and the roster it edits is already public. Mass abuse is limited to hiding entries, which an admin can undo (`unsuppress`).

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

> **Current implementation note (8.3, names in discovery):** a discovery profile may now carry a name — but only by the attendee's explicit choice when joining: either the public attendee-list entry they pick as themselves (whose name, Gravatar and links are already public on the WordCamp's own Attendees page) or a name they type. Anonymous stays available. Owner tokens, IDs and profile contents are still never sent to analytics. Impersonation is limited by one-claim-per-entry, suppression unlinking, and the admin **Unlink** action — see [3.4](03-functional-requirements/04-matching.md).

## 8.7 Event manager accounts *(added Sept 2026)*
A second kind of signed-in person: someone an admin lets edit three sections of specific events ([9](09-admin-panel.md)). Tried to break, in `EventManagerSecurityTest` (64 tests), `EventManagerJourneyTest` and a three-person real-Chrome run; the security guards were also checked by mutation (break the control, the tests must fail). The risks and how each is closed:

- **Privilege:** every admin route and policy treats any `User` as an admin, so managers are a separate model and a separate session guard (`manager`) — never rows in `users`. No policy or `auth` middleware can see one as an admin; tests sign a manager in and walk every admin URL, and check that every `manager.*` route sits in the `web` group (sessions + CSRF) behind `EnsureEventManager`, and that every route that changes data is throttled.
- **Forged requests (CSRF):** every state-changing manager route, the sign-in and sign-out included, refuses a request without the token (419) — tested with the framework's check switched on, and from a real browser.
- **Other people's events:** no route-model binding on `/manager/*`; every lookup goes through the manager's own events (404 otherwise, even before validation), ids that are not plain numbers are a 404, and a quest is only reachable through its own event; the shared "Things to do" cards are not reachable at all.
- **Event status and other columns:** not an input anywhere in the manager area (no validation rule, no form field); a forged field — status, logo, timestamps, ids, `info_fetched`, `timezone_locked` — is ignored.
- **Accounts:** created only by an admin (no registration route); passwords hashed (bcrypt, 8–72 characters); a new session id at sign-in (no fixation); "no such account", "wrong password" and "switched off" get the same answer *and the same amount of work* (a hash is computed for the first and last, so the time taken doesn't tell an address that has an account from one that hasn't) and count toward the same limit; sign-in is limited to 30 a minute per address plus 5 wrong tries per email + address, so a locked-out address doesn't lock out the other organizers on the same wifi; the password is never sent back in a page or kept in the session; switching a manager off, or changing their password, takes effect on their next request (session fingerprint) and rotates the remember-me token; a sign-in never follows an address the visitor supplies.
- **Text that reaches ten thousand attendees:** every manager-typed string is printed escaped (`{{ }}`), placed in JSON blocks with `JSON_HEX_TAG | JSON_HEX_AMP | …`, and only `http(s)` addresses, `tel:` and `mailto:` become links — verified with script / markup / template-engine payloads in every event text field and checklist item across every attendee page, in `EventManagerSecurityTest` and in a real browser (nothing ran, no dialog, no `javascript:` link). Names must be plain single-line text (no `< > [ ]`, no control characters).
- **What the app fetches:** a manager can only point an event's *source site URL* at a `wordcamp.org` address (https, no login details, standard port); the private-network check applies on top, for admins too.
- **Abuse of saving:** 60 saves a minute per manager; a time-zone change re-queues the schedule fetch at most once in five minutes; every save is written to the activity log, which the admin reads.
- **Browsers and proxies:** manager pages are `Cache-Control: no-store, private`; the site-wide security headers apply; the Cloudflare cache rule covers only `/event/*` and must never be widened to `/manager` or `/admin`.
- **Not covered / deliberate:** no manager self-service password reset (mail is log-only on the live host; the owner chose admin-only); no per-email lockout across *different* addresses (it would let anyone lock a manager out); a manager may change the slug of their own events (the form warns); `llms.txt` builds Markdown from event names without escaping (existing code — admin-typed names could break its formatting; managers can't type the characters that matter).
