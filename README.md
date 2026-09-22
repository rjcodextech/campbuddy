# CampBuddy V2 — Software Requirements & Project Documentation

**Project:** CampBuddy V2 — multi-WordCamp companion app, launching with WordCamp Rajasthan 2026
**Type:** Progressive Web App (PWA) with a Laravel backend
**Status:** Rewrite in progress — target event **3–4 Oct 2026** (10 days from doc date)
**Document version:** 2.1.0 — 23 September 2026
**Supersedes:** V1 (`../V1/README.md`), a working single-event app on Slim Framework. V1 stays deployable as a fallback — see §14.

This document is the authoritative reference for what CampBuddy V2 is, what it does, how it's built, and how to ship it. Read §0 first — it records findings that overrule assumptions made earlier in the project's planning.

---

## 0. Findings that shape this spec (read first)

These were verified live against `rajasthan.wordcamp.org`, `bengaluru.wordcamp.org`, `central.wordcamp.org`, and `api.wordpress.org` on 2026-09-23, not assumed. Finding #1 below **corrects** an earlier version of this document, which checked only a summarized route *index* (100+ generic Jetpack/WordPress routes buried the WordCamp-specific ones) rather than hitting the actual endpoints directly.

1. **Sessions, speakers, sponsors, and organizers ARE exposed via standard, structured `wp-json` REST endpoints — on every WordCamp site, with the same schema.** All WordCamp sites run the same open-source plugin (the shared `WordCamp.org` codebase), which registers real custom post types with REST enabled:
   - `GET /wp-json/wp/v2/sessions` — `session_date_time` (date/time), `session_track` (numeric taxonomy term IDs), `session_speakers`
   - `GET /wp-json/wp/v2/speakers` — bio in `content`, photo in `avatar_urls`
   - `GET /wp-json/wp/v2/sponsors` — website in `meta._wcpt_sponsor_website`, tier via the `sponsor_level` taxonomy
   - `GET /wp-json/wp/v2/organizers`
   - Taxonomy term names (track/tier) are resolved by joining against their own REST endpoints, e.g. `GET /wp-json/wp/v2/session_track` returns `{id: 37, name: "Track 1", slug: "track-1"}` — the numeric IDs on a session/sponsor are meaningless without this join.
   - Verified on two independent sites: Rajasthan returns populated data on all of the above; Bengaluru's endpoints exist and return valid (currently empty) JSON at the same paths — confirming this is a consistent, site-independent schema, not a Rajasthan-specific quirk.
   - **One real gap:** a speaker's social links (Twitter, personal site) are not in structured `meta` — they're embedded as `<a>` tags inside the rendered bio `content` HTML. Getting those requires a small, targeted HTML-link-extraction step (scan `content` for known social-domain hrefs) alongside an otherwise-clean REST client — not a full-page scraper.
2. **There IS a real, official, documented JSON API for discovering WordCamps: `api.wordpress.org/events/1.0/`.** Verified live — querying it returns a `type` field (`"wordcamp"` vs `"meetup"`) plus title/url/date/location. It correctly surfaced WordCamp Rajasthan 2026 (Oct 3, Jaipur) and WordCamp Delhi 2026 (Dec 12, New Delhi) in one query. This replaces the "scrape central.wordcamp.org's HTML schedule page" approach originally planned in §5.3 — use this endpoint for discovery instead. Caveat: it's a location/radius-based "events near X" API (built for the wp-admin events widget), not a flat global list — see §5.3 for how this shapes the discovery design.
3. **The one thing that genuinely has no REST endpoint is the public Attendees page** — confirmed no route exists for it on either site tested. It's rendered by CampTix (the ticketing plugin) as a plain template, not a custom post type. Ingesting it requires real HTML parsing — this is the one place in the whole pipeline that's a scraper rather than an API client.
4. **The public Attendees page is opt-in and contains no interest/bio data.** Each entry is: gravatar image, name, and optionally a personal site / Twitter / LinkedIn URL — whatever the attendee chose to enter at checkout. There is nothing to match interests *against* upstream. This means:
   - **Interest-based matching can only ever work between two people who both used CampBuddy** and filled in their own profile (interests, "who I want to meet"). The scraped Attendees page supplies a *roster* (so CampBuddy knows Amit Panchal is attending and has his gravatar/LinkedIn), not a *matchable profile*.
   - The product framing must be: "See who's attending" (roster, from WordCamp) + "See who on CampBuddy matches your interests" (matching, from CampBuddy's own users) as two distinct, clearly-labeled sections — not one blended list. Conflating them will overpromise a feature that can't exist.
5. **Speakers/sponsors/sessions were the data V1 already fetched, via a WPSimplified-run proxy (`wpsimplified.in`) rather than each site's own REST API directly.** Now that finding #1 confirms every site exposes this natively and consistently, there's no reason to keep depending on that proxy. **Firm decision for V2: zero runtime dependency on `wpsimplified.in`.** Every data source is either the event's own site (REST or the two narrow HTML-parse jobs) or an official WordPress.org endpoint (`api.wordpress.org/events/1.0/`) — nothing routes through a third party CampBuddy doesn't control.
6. **No reliable REST field exists for an event's logo or favicon, but both are still fetchable.** Checked the WP REST root (`site_icon_url`) on Rajasthan's site — absent, because most WordCamp organizers never set WordPress's "Site Icon" setting even though they do upload a visual logo. What *is* reliably present: the site's actual logo rendered as an `<img>` in the homepage header markup (confirmed live — `rajasthan.wordcamp.org/2026/files/2026/05/logo.png`), and a working favicon at the network-standard `/favicon.ico` path (confirmed live, though this may be the shared WordCamp.org network icon rather than an event-specific one, since it resolves at the root domain, not the event's own `/2026/` path). This means branding-asset discovery is a **best-effort, one-time HTML-parse job** (§3.2 BR4), not a REST field — same risk class as the Attendees-page parser (§0.3), and needs the same admin-override fallback.
7. **Scraping other people's data, even opt-in-public data, is still handling other people's PII.** §8 treats this with data minimization and a takedown path, not just "it's public so it's fine." This applies narrowly to attendee-roster ingestion (finding #3), not to speakers/sponsors/sessions/branding assets, which are the event's own official published content.

---

## 1. Project overview

CampBuddy V2 is a mobile-first, local-first companion app for WordCamp attendees, rebuilt to support **any number of WordCamps**, not just one. It launches with WordCamp Rajasthan 2026 as its first live event.

CampBuddy is **not a conference schedule app**. A schedule is data; CampBuddy's job is guidance — taking everything happening around a WordCamp and turning it into a simple answer to the one question that actually matters to someone standing in a venue: **"What should I do now?"** Every screen should be judged against that, not against "does it display the data we have."

### 1.0 Product philosophy

1. **Guidance over information.** Don't just display a fact — translate it into what the attendee should do about it. Not "Contributor Day — 9:00 AM" but *"Contributor Day starts at 9:00 AM. First time contributing? Head to the contributor tables and tell a volunteer what you're interested in — they'll help you find a team."* Not "Building Blocks at Scale — Hall A" but *"Your saved session starts in 15 minutes in Hall A — you may want to start heading there."* This principle governs Home (§3.1) above all, but applies everywhere copy is written.
2. **Local by default, shared only by choice.** Personal attendee data lives on the device unless a feature explicitly needs to share it, and even then, sharing is opt-in, scoped, explained, and revocable. See §8.3 for how this applies specifically to attendee matching.
3. **The guiding test for any feature or UX decision:** *Would this make a person attending their first WordCamp feel more confident about what to do next?* If yes, it belongs in CampBuddy. If it only adds information, complexity, or another dashboard card without making the attendee's next move easier, reconsider it — including for features already speced below.

### 1.1 Primary audience

- **First-time attendees** (most important audience) — don't know what Contributor Day is, whether they can approach speakers, what sponsor booths are for, how to contribute to WordPress, who to talk to, or how to start a conversation. CampBuddy exists primarily to reduce this uncertainty.
- **Returning attendees** — still get value from session planning, reminders, networking, Contributor Day recommendations, sponsor offers, and their Camp Card, even without the first-timer hand-holding.
- **Organizers** — configure CampBuddy for their own WordCamp (branding, schedule, speakers, sponsors, quests, Contributor Day info) without anyone touching application code (§9 admin panel).

### 1.2 Navigation & information architecture

Six primary tabs, designed to work one-handed on a phone. No secondary nav clutter — settings/export/data controls are reached contextually (from Camp Card and Explore), not as a seventh tab:

| Tab | Purpose | Spec |
|---|---|---|
| **Home** | "What's happening now, what's next, what should I do" | §3.1 |
| **My Day** | Personal + full schedule, session bookmarking, reminders | §3.8 (schedule/sessions), §3.5 (reminders) |
| **Quest** | Small, achievable social/exploration prompts | §3.6 |
| **Contribute** | Contributor Day guidance, team matching | §3.9 |
| **Explore** | People (matching, §3.4) · Sponsors & Deals (§3.7, §3.12) · Event Info (§3.12) | — |
| **Camp Card** | Portable digital identity + QR | §3.10 |
| *(not a tab)* Onboarding | 4-step first-run flow, under 2 minutes | §3.11 |

This replaces V1's separate "Offers" and "More" top-level tabs — both now live as sub-sections inside **Explore**, and "Camp Quest"/"Contributor Day" are renamed to the shorter **Quest**/**Contribute** to fit a one-handed, five/six-item tab bar.

### 1.3 What's new vs. V1

| Area | V1 | V2 |
|---|---|---|
| Event scope | Hardcoded to one event | Multi-event: discover, ingest, and serve N WordCamps |
| App structure | Static PWA + separate Slim backend, joined by a root `.htaccess` rewrite into `/backend` | **One Laravel app, no split.** No more `/backend` mount path or rewrite trick — Laravel's `public/` is the docroot; everything (attendee pages, admin, API, cron) lives in one codebase, one deploy. |
| Frontend rendering | 100% client-side vanilla JS rendering onto a static `index.html` shell | **Blade views**, server-rendered per route (`/event/{slug}`, `/event/{slug}/my-day`, etc.) — a deliberate rewrite, accepted as in-scope for the Oct 3–4 launch despite the timeline risk (§2.1 scopes this tightly, see §5.5). |
| Backend framework | Slim Framework | **Laravel** (deliberate rewrite, per product decision) |
| Attendee data | None | Roster ingestion from each event's public Attendees page (§0.3, §0.4) |
| Matching | None | Interest-based matching **among CampBuddy users only** (§3.4) |
| Branding | Static, single theme | Per-event theme (colors/logo/name) driven by event data |
| Notifications | None | Best-effort Web Push for bookmarked sessions (Android solid, iOS 16.4+ with PWA installed; in-app banner fallback everywhere) |
| Quest (was: onboarding checklist) | Generic interests picker | A full nav tab: default + event-specific + Contributor Day quests (§3.6), 8–10 configurable prompts per event, no points/leaderboards |
| Offers | Single-event list | Event-scoped, expire when the event ends unless resubmitted |
| Hosting | Shared cPanel only | Shared cPanel **behind a mandatory CDN** (§5.4) |

### 1.4 Objectives

1. Let anyone planning to attend *any* upcoming WordCamp get the same guided experience CampBuddy gave WCR attendees in V1.
2. Turn a static attendee list into a way to actually find people worth meeting — without pretending to know things about people who never used the app.
3. Make the app feel like the event's own official companion (per-event branding) while staying one shared codebase.
4. Give organizers a tool they can hand to attendees with their event's name and colors on it, day one.
5. Keep the zero-account, local-first, minimal-data-collection posture from V1 — matching is the one place V2 asks a user to voluntarily share more (their own interests), and that stays opt-in and editable/deletable.
6. Survive real WordCamp-day traffic without falling over or costing you a scramble — see §5.4 for how that's now actually true, not just asserted.

---

## 2. Scope

### 2.1 In scope for V2 — Must-have for Oct 3–4 (WordCamp Rajasthan launch)

These are the items that must work for the Oct 3–4 ship. Everything here is achievable in 10 days **only if scoped exactly as written** — no gold-plating. The product brief in §1 describes CampBuddy's full intended depth; this list is the honest subset of that depth achievable for one event in ten days. Where a §3 requirement ID isn't listed here at all, assume it's fast-follow (§2.2), not dropped.

- **F1.** Home (§3.1 H1–H6) — Happening Now / Up Next / Suggested Action / Progress, working against WCR's real schedule data.
- **F2.** WCR 2026 added manually through the admin panel's event CRUD (§9) as the one live, visible event — no dependency on central discovery for launch (that's fast-follow, §2.2).
- **F3.** Speakers/sponsors/sessions ingestion for WCR via REST (§0.1, §5.2) — the foundation everything else in this list reads from.
- **F4.** Attendee roster ingestion for WCR (§3.3) — scheduled job parses the public Attendees page, stores name/gravatar/links, updates daily.
- **F5.** My Day & session details (§3.8 MD1–MD5): Full Schedule + My Schedule, session detail page, overlap warning, offline availability.
- **F6.** Quest (§3.6 C1–C5): default + WCR-specific quests, no points/leaderboards, local progress.
- **F7.** Contribute (§3.9 CD1–CD4) with the fixed curated team list (CD2's launch scope, not the dynamic full list).
- **F8.** Explore → People: interest-based matching with the full anonymous-discovery architecture (§3.4 M1–M7, §8.3) — this is a privacy-load-bearing feature and ships correctly-architected or not at all; there is no "simplified but less private" fallback worth building.
- **F9.** Explore → Sponsors & Deals & Event Information (§3.7, §3.12 EI1–EI3).
- **F10.** Camp Card v2 (§3.10 CC1–CC6): full field set, attendee-chosen visible fields, QR to attendee-chosen link, fullscreen display.
- **F11.** Onboarding (§3.11 OB1–OB4): 4-step flow, under 2 minutes, skippable.
- **F12.** Session bookmarking + in-app "starting soon" banner (§3.5 N1–N2, N4). Web Push is attempted where supported (§0 finding on iOS limits) but the in-app banner is the guaranteed path — **do not let push become a launch blocker**.
- **F13.** Per-event branding for WCR (§3.2 BR1–BR7): best-effort auto-fetch of logo/favicon with admin upload as the fallback/override. Doesn't need to be a full visual theming *engine* for launch, just WCR's palette applied correctly and within the accessibility guardrail (BR7).
- **F14.** Data controls (§3.13 DP1–DP4): Export/Clear, reachable contextually.
- **F15.** Accessibility, performance, error-handling, and empty-state floors (§4.1–§4.3) — these are launch-blocking *qualities* of the features above, not a separate feature to schedule at the end.
- **F16.** Laravel backend covering all of the above, deployed behind a CDN (§5.4).
- **F17.** Pre-launch blocker checklist closed (§13): `composer audit` clean, manual OWASP pass done, the acceptance journey (§15.2) green, LICENSE committed.

### 2.2 In scope for V2 — Fast-follow (after Oct 3–4, before the next WordCamp)

- Multi-event **browsing**: the home hero listing several upcoming WordCamps, not just WCR (ingestion pipeline built generically from day one per §6, but the UI for picking *among* events, and running ingestion for more than one event concurrently, ships after WCR).
- Central discovery job that queries the official `api.wordpress.org/events/1.0/` endpoint (§0.2) to find upcoming WordCamps, with an admin approval step before any new event goes live in the app (never auto-publish a discovered event). Known near-term events already confirmed live on this API: WordCamp Rajasthan 2026 (Oct 3–4, Jaipur — launch event), WordCamp Delhi 2026 (Dec 12, New Delhi). WordCamp Bengaluru 2026's site is live (`bengaluru.wordcamp.org/2026`) but wasn't returned by the Jaipur-centered query tested — confirms the endpoint is location-radius-based, not a global list (see §5.3).
- Full per-event theming engine (arbitrary colors/logo per event, not just WCR's).
- True Web Push reliability improvements, notification preferences.
- Contributor Day (§3.9 CD2) expanded to cover the full, dynamically-current list of official WordPress contributor teams rather than the fixed launch list.

### 2.3 Explicitly out of scope

- User accounts/login (standing product decision, carried from V1).
- "Scan a card" / decoding another attendee's QR into a contact list.
- Storing or displaying anything from WordCamp ticketing/registration systems beyond what the event's own public, opt-in Attendees page already shows. **CampBuddy never has access to, and never will fetch, ticket purchase data, email addresses, or any attendee data that isn't already opted into public display by the attendee themselves.**
- Lead-capture forms on offers.
- Admin-managed image uploads **for CampBuddy's own app-wide brand assets** (icon, mascot — still a file-replace + redeploy, unchanged from V1). **Per-event** logo/favicon upload is now in scope (§3.2 BR4/BR5) — this is a deliberate, narrow reversal of V1's blanket "no image uploads" limitation.
- Dark mode (declined in V1, carried forward).
- Multi-tenant support for non-WordCamp events.

---

## 3. Functional requirements

### 3.1 Home

Home is the most important screen in CampBuddy and must not become a generic card dashboard — its one job is answering "what should I do right now," and its content changes based on the selected event, current date/time, saved sessions, completed quests, onboarding preferences, and Contributor Day status.

| ID | Requirement |
|---|---|
| H1 | **Happening Now**: what's currently underway — registration open, keynote in progress, lunch started, Contributor Day tables open, networking hour — derived from the event's schedule data (§3.3, §3.8) compared against current time, not manually authored per-event unless an organizer overrides it. |
| H2 | **Up Next**: upcoming activities, prioritized in this order — (1) the attendee's own saved sessions (§3.8), (2) important event-wide activities (keynote, lunch, closing), (3) sessions matching the attendee's stated interests from onboarding (§3.11). |
| H3 | **Suggested Action**: an occasional, single, simple nudge — "visit the sponsor area," "introduce yourself to someone from another city," "add your LinkedIn to your Camp Card," "bookmark your afternoon sessions." Rotates from a mix of default CampBuddy suggestions and unfinished Quests (§3.6). Tone must read as helpful, not nagging — one suggestion at a time, not a list. |
| H4 | **Progress**: a compact, non-gamified indicator of Quest completion and saved-session count — small enough that WordCamp itself, not the app, stays the main event (§1.0 principle 1 / product philosophy). |
| H5 | All copy on this screen follows the "guidance over information" translation pattern (§1.0) — a raw schedule fact is never shown without also saying what the attendee might do about it. |
| H6 | Home renders from already-downloaded event data (§5.1, §4.4 offline-first) — no blocking network call between opening the app and seeing a populated Home screen. |

### 3.2 Event branding

| ID | Requirement |
|---|---|
| BR1 | Every event record carries: display name, short name/hashtag, primary/accent color, logo asset, favicon asset. |
| BR2 | The active event's branding is applied to CSS custom properties at load time (no rebuild needed to reskin). |
| BR3 | Fallback: if an event has no branding set, the app uses CampBuddy's default palette/logo/favicon — never a broken/unstyled state. |
| BR4 | **Best-effort auto-fetch**, run once when an event is approved (§0.6, not on the daily schedule): try the event site's REST `site_icon_url` first (cheap, works when set); if absent, parse the homepage `<head>`/header markup for the site logo `<img>` and a favicon `<link rel="icon">`; if that also fails, fall back to `/favicon.ico` at the site's domain. Whatever is found is **downloaded and re-hosted on CampBuddy's own storage** — never hotlinked — so the event's own site going down later doesn't break CampBuddy's branding. |
| BR5 | **Admin can always upload/replace the logo and favicon manually**, overriding whatever auto-fetch found (or filling the gap if auto-fetch found nothing). This is a deliberate reversal of V1's "brand assets aren't admin-manageable" limitation (V1 §2.2) — scoped specifically to per-event logo/favicon, not CampBuddy's own app-wide icon/mascot, which stays a file-replace + redeploy as in V1. |
| BR6 | **Frontend visibility toggle**, independent of the event's lifecycle status (draft/approved/active/archived, §7): a simple enabled/disabled switch controlling whether the event appears in the public app at all. **Default: enabled** — every approved event is visible unless an admin explicitly turns it off. This lets an admin approve/ingest an event without immediately publishing it, or temporarily hide one without touching its data or lifecycle state. |
| BR7 | Event branding is bounded, not unlimited: accent colors, logo, event name, and subtle visual accents only. Branding must never be allowed to drop text/background contrast below WCAG AA (§4.1), make buttons inconsistent, or change navigation structure. CampBuddy's own layout, type scale, and component shapes stay constant across every event — an attendee should always recognize it as CampBuddy first, the specific WordCamp second. In practice: the admin branding form only exposes a primary/accent color pair (validated against a minimum-contrast check before saving) and the logo/favicon upload — not arbitrary CSS. |

### 3.3 Attendee roster ingestion

| ID | Requirement |
|---|---|
| IN1 | A scheduled job parses one event's public Attendees page HTML and upserts (name, gravatar URL, optional links) keyed by a stable hash of name+links (no upstream ID exists — see risk in §8.4). |
| IN2 | Ingestion runs **once daily per active event**, not more — this is a scrape of someone else's site, not an API with a documented rate limit. |
| IN3 | Ingestion for multiple events (fast-follow, §2.2) is batched with a delay between events (e.g. sequential with a cooldown), never parallel-hammering multiple WordCamp sites at once. |
| IN4 | If the page structure changes and parsing fails, the job logs and alerts (via the admin dashboard's fetch-log, same pattern as V1's `fetch_log`) rather than silently ingesting garbage. |
| IN5 | An attendee can request removal from CampBuddy's roster (§8.4) — the ingestion job must respect a local suppression list on every re-run. |

### 3.4 Interest-based matching (Explore → People)

The architecture note in §8.3 governs this section: onboarding answers (§3.11) are never automatically public. A user must take a distinct, explicit action to become discoverable, and that action must be reversible.

| ID | Requirement |
|---|---|
| M1 | The attendee's **primary profile** — everything from onboarding (§3.11): interests, why they're attending, who they want to meet — lives locally on the device by default, same as any other onboarding answer. Filling it in does **not**, by itself, make the attendee discoverable to anyone. |
| M2 | A separate, explicit **"Join attendee discovery"** action is what actually publishes anything. Tapping it: (a) generates a random, non-guessable **public** `discovery_id` for this device (not tied to name/email/any real identifier) *and*, separately, a random **owner token** (§8.6) that only this device ever holds, (b) walks the attendee through choosing exactly which fields to expose (profession, interest tags, "who I want to meet" — not the full onboarding answer set by default), (c) publishes only that chosen subset under the public `discovery_id`, scoped to the current event. |
| M3 | Matching runs **only** between attendees who've both taken the M2 action — never against the scraped roster's bare name/gravatar entries (§3.3), which have no interest data and never opted into CampBuddy discovery at all (§0.4). A fixed, curated tag set (e.g. "content creator," "developer," "blogger") drives the match, not free text. |
| M4 | A matched profile shows only the fields that attendee chose to expose in M2, plus a photo if they added one — never their full local onboarding answers. If that same person separately appears in the ingested roster (§3.3) under a matching name, their WordCamp-listed social links may be shown as a clearly separated "also found publicly" section, distinct from what they chose to expose via CampBuddy. |
| M5 | "I met them" marks a match as met; met people drop out of the primary match list but remain visible in a "people you've met" history (local to the device that marked it). |
| M6 | A visible, one-tap **"Leave attendee discovery"** action immediately un-publishes the discovery profile — distinct from, and simpler than, deleting all local data (§3.13). Leaving discovery keeps the attendee's local onboarding answers and Quest/My Day progress untouched; it only removes them from other attendees' match results. Leaving (and any later update to the published profile) requires the owner token from M2, not just the public `discovery_id` — see §8.6 for why this distinction is load-bearing, not pedantic. |
| M7 | Discovery profiles carry an **optional expiry** tied to the event's end — once an event moves to `archived` (§7), its discovery profiles are purged automatically, same as the roster-purge policy in §8.4. Nobody's matching data outlives the WordCamp it was created for unless they re-opt-in at a future event. |
| M8 | The owner token is never displayed, never logged, never sent to analytics, and never recoverable if the device's local storage is cleared without first leaving discovery (§3.13 DP3 already prompts for this) — losing it means the profile has to sit until its M7 expiry rather than being explicitly withdrawable. This tradeoff is accepted because the alternative (an account system to recover it) violates the no-accounts product decision (§2.3). |

### 3.5 Schedule & notifications

| ID | Requirement |
|---|---|
| N1 | Bookmarking a session stores it locally. Notification permission is **never requested on page load or app open** — it's asked right after a bookmark, at the moment the benefit is obvious: *"Want CampBuddy to remind you before this session starts?"* If granted, a scheduled Web Push registers for 5–10 minutes before start, including track/hall. |
| N2 | Every device also gets an in-app "starting soon" banner/badge for bookmarked sessions regardless of push support — this is the guaranteed path, push is a bonus. |
| N3 | iOS: push requires iOS 16.4+ **and** the PWA installed to the home screen first; the app explicitly detects this and shows an iOS-specific instruction instead of a silently-failing permission prompt. |
| N4 | A denied permission is **respected permanently** — CampBuddy never re-prompts automatically. Re-enabling push is only ever a deliberate action the attendee takes from a settings surface, never a repeated browser prompt. |

### 3.6 Quest

Quest turns the intimidating social side of a WordCamp into a set of small, achievable activities — "say hello to someone attending their first WordCamp," "meet someone from another city," "visit three sponsor booths," "introduce yourself to a speaker," "learn what one Contributor Team does," "complete your Camp Card." Playful, not childish — and deliberately not gamified beyond that: no points, currencies, or leaderboards.

| ID | Requirement |
|---|---|
| C1 | Each event has an admin-editable list of 8–10 quests (renders the same underlying list previously called the "onboarding checklist" — this is that feature, given its full product treatment as a primary nav tab), each independently markable complete. |
| C2 | Quest progress is **local-only** (§1.0 principle 2), no server sync needed — consistent with every other piece of personal progress in the app. |
| C3 | Quests come from three sources, shown together in one list: **default CampBuddy quests** (apply to every event, e.g. "complete your Camp Card"), **event-specific quests** (admin-authored per event, §9), and **Contributor Day quests** (contextual, surfaced once the attendee engages with Contribute, §3.9 — e.g. "learn what one Contributor Team does"). |
| C4 | No points, currency, leaderboard, or competitive ranking — completion is binary (done/not done) and private to the device. This is a deliberate constraint (§1.0), not a missing feature to add later without reconsidering the product philosophy first. |
| C5 | The Quest tab needs a real empty state for a brand-new attendee (§4.3): something like *"Your WordCamp adventure starts here — pick your first Quest."* |

### 3.7 Deals (Explore → Deals, offers carried from V1, event-scoped)

Same as V1 §3.2 B7/B8, with `event_id` added: a deal is only shown while its event is active, and does not roll over to the next event unless the sponsor re-submits it (admin re-activates it against the new event). Each deal displays: the sponsor, a description of the offer, coupon code (where applicable), expiry, a redemption link, and terms. CampBuddy is not an advertising platform — a deal only belongs here if it's a genuine attendee benefit, not a generic ad; this is an editorial judgment call for whoever manages Offers in the admin panel, not something the software enforces.

### 3.8 My Day & session details

| ID | Requirement |
|---|---|
| MD1 | Two distinct views: **Full Schedule** (every session at the event) and **My Schedule** (only what the attendee bookmarked) — clearly distinguished, not blended into one filtered list. |
| MD2 | Full Schedule is browsable by track/room, searchable, and shows each session's title, speaker, track, room, and start/end time at a glance. |
| MD3 | A session detail page shows: title, speaker (name + photo, from §0.1's REST ingestion), track, room, start/end time, description, session type, speaker bio, any useful links (slides/video, from the `meta` fields in §0.1), and a save/remove-from-My-Day action. |
| MD4 | Bookmarking two overlapping sessions is **allowed** — the app warns about the conflict but never blocks the save. The attendee decides, CampBuddy just makes the conflict visible before it becomes a surprise. |
| MD5 | My Schedule remains fully usable offline once the event's schedule data has been downloaded (§4.4) — this is one of the offline-first floor requirements, not a nice-to-have. |

### 3.9 Contribute

Contributor Day is confusing to many first-timers — this section exists to make contributing to WordPress approachable without assuming any community terminology up front.

| ID | Requirement |
|---|---|
| CD1 | A short question flow (not a long questionnaire): what kind of work the attendee enjoys — technical or non-technical, writing, design, testing, support, documentation, development, community work, translation, organizing. Every question is skippable. |
| CD2 | Based on answers, CampBuddy suggests suitable **WordPress Contributor Teams**. Launch ships with the same fixed, curated team list V1 used (Core, Docs, Support, Photography, Testing, Polyglots, Design, Training, Accessibility, and similar) — expanding this to the full, dynamically-current official team list is fast-follow (§2.2), not a launch blocker, since the underlying team roster changes rarely enough that a periodically-updated static list is an acceptable launch trade-off. |
| CD3 | For each suggested team, explain in plain language: what the team does, who it tends to suit, whether technical knowledge is required, one or two examples of a beginner-friendly task, and concretely what to do when the attendee reaches that team's table (who to look for, what to say). No unexplained community jargon — a first-timer shouldn't need to already know what "Trac" or "Polyglots" means to get value from this screen. |
| CD4 | Contribute integrates with Quest (§3.6 C3) — engaging with this section can surface a Contributor Day quest ("learn what one Contributor Team does"). |

### 3.10 Camp Card

The attendee's portable digital identity — should read as a modern conference badge, not a form.

| ID | Requirement |
|---|---|
| CC1 | Fields: profile image, name, role/title, company/community, city, WordPress interests, "ask me about," LinkedIn, personal website, WordPress.org profile, and a small set of other explicitly supported social links. All optional except name. |
| CC2 | The attendee explicitly chooses which filled-in fields actually appear on the card — filling a field in doesn't automatically put it on the visible card. Onboarding answers (§3.11) are never surfaced here automatically (§8.3's privacy boundary applies to Camp Card too, not only to matching). |
| CC3 | A QR code is generated from the field the attendee designates as primary — LinkedIn is the obvious default suggestion, but the attendee can point it at any of their chosen links. |
| CC4 | The card must be easy to bring to fullscreen with one tap, for the literal moment of handing a phone to someone else to scan. |
| CC5 | Camp Card data (drafts and published state) is local-only (§1.0), same as onboarding — nothing about it reaches a server except what the attendee separately chooses to expose via Explore → People matching (§3.4), which is a distinct, separately-opted-into action. |
| CC6 | A real empty state for a Camp Card that's not filled in yet (§4.3): *"Your Camp Card is almost ready — add your name and a link you'd like people to scan."* |

### 3.11 Onboarding

No traditional account/signup, ever (§2.3). Four steps, skippable where noted, aiming for **well under two minutes** end to end.

| ID | Requirement |
|---|---|
| OB1 | **Welcome** — one or two sentences explaining what CampBuddy is. No slideshow, no feature tour. |
| OB2 | **Select WordCamp** — choose the event being attended; the interface adopts that event's branding (§3.2) immediately after selection. |
| OB3 | **Tell CampBuddy why you're here** — a small number of skippable questions: first WordCamp or returning, role, interests, why attending, what they want to learn, who they'd like to meet, whether attending Contributor Day. This is the data that later powers Home's Up Next (§3.1 H2) and Contribute's suggestions (§3.9 CD1) — but per §8.3, none of it becomes shareable without the separate, explicit matching opt-in. |
| OB4 | **Ready** — goes straight to Home. No forced tutorial overlay. |

### 3.12 Explore — Sponsors & Event Information

| ID | Requirement |
|---|---|
| EI1 | **Sponsors**: name, description, booth location (where organizers provide it), website, and a link into any active Deals (§3.7) from that sponsor — sourced from the REST ingestion in §0.1. |
| EI2 | **Event Information**: venue, important links, wifi details (only if organizers supply them — never fabricated), social event info, registration info, Contributor Day location, code of conduct, emergency/contact info, and any other organizer-supplied nearby-venue info. This is where V1's "More" tab content lives now (§1.2) — folded into Explore rather than kept as its own top-level tab. |
| EI3 | Every field in EI2 is admin-editable per event (§9) and simply omitted from display when an organizer hasn't supplied it — never a placeholder or a broken-looking blank field. |

### 3.13 Data controls (Export / Clear)

Reached contextually — from Camp Card or Explore's Event Information — not as a dedicated nav tab (§1.2).

| ID | Requirement |
|---|---|
| DP1 | **Export My CampBuddy Data**: a single action that bundles all of this device's local data (onboarding answers, Quest progress, My Day bookmarks, Camp Card, met-history) into a downloadable file the attendee can keep. |
| DP2 | **Clear My CampBuddy Data**: wipes all of the above from the device. Before it runs, the app clearly states what will be removed — this is a destructive, irreversible local action and must never fire without that explanation and an explicit confirmation step. |
| DP3 | Clearing local data does **not** automatically call §3.4 M6 ("Leave attendee discovery") — if the attendee has an active discovery profile, clearing warns about it separately and offers to leave discovery first, since that's a server-side action distinct from wiping the device. |
| DP4 | Local persistence uses IndexedDB for anything beyond trivial key-value data (bookmarks, Camp Card, Quest state, onboarding answers) — not `sessionStorage`, and not `localStorage` alone once data complexity goes past simple flags — so state survives page reloads, browser restarts, and temporary network loss (§4.4). |

---

## 4. Non-functional requirements

| Category | Requirement |
|---|---|
| **Performance** | Same caching posture as V1 (`Cache-Control`/`ETag`), now fronted by a CDN (§5.4) so these headers are actually honored at the edge, not just at origin. See §4.2 for specifics. |
| **Scalability** | Honest framing this time: origin is shared cPanel hosting with real connection limits. Scale headroom comes from the **CDN absorbing repeat reads**, not from the origin being infinitely horizontal. Rate limiting moves to the CDN edge (§5.4) so it doesn't compete with real traffic for DB connections. The app may be used by hundreds or thousands of attendees simultaneously on poor venue wifi — see §4.2. |
| **Availability** | Same stale-while-revalidate posture as V1: reads never block on upstream or ingestion jobs being slow. |
| **Security** | See §8. |
| **Offline-first** | See §4.4 for the specific floor of what must keep working with no connection. |
| **Privacy** | No account required for attendees; "local by default, shared only by choice" (§1.0) — see §8.3 for the matching-specific architecture. |
| **Accessibility** | See §4.1. Not an optional polish pass — WordCamp is an inclusive community event. |
| **Browser/device support** | Same as V1: modern mobile/desktop browsers, installable PWA on Android and iOS; also functions correctly as a plain website if not installed. |
| **Maintainability** | Laravel conventions (Eloquent, Jobs/Queues for ingestion, Scheduler for cron) replace V1's hand-rolled Slim actions — this is the whole point of the framework switch: less custom plumbing to maintain long-term. |

### 4.1 Accessibility

Sensible WCAG practices, at minimum: sufficient color contrast (also enforced structurally on event branding, §3.2 BR7), full keyboard accessibility, semantic HTML, labeled form fields, accessible buttons (not bare clickable `<div>`s), alt text on meaningful images, visible focus states, screen-reader-friendly document structure, touch targets sized for real thumbs on a real phone, and `prefers-reduced-motion` respected on any hover/transition animation (V1 already did this for hover effects — carry it forward, apply it consistently to anything new).

### 4.2 Performance

Optimize aggressively — this app gets used on congested venue wifi by many people at once:

- Small initial payload; lazy-load anything non-critical.
- Optimize images (this is why branding assets are downloaded and re-hosted rather than hotlinked, §3.2 BR4 — CampBuddy controls their size/format, not the source site).
- Minimize API calls; cache event data client-side and work from the cached copy rather than re-fetching unchanged information (this is the entire point of the stale-while-revalidate + CDN design in §5.4).
- Avoid large JS dependencies for trivial functionality — vanilla JS stays the default (§5.5); a library is justified per-case, not by default.
- Navigation between tabs should feel immediate — no visible loading spinner for data that's already been downloaded this session.

### 4.3 Error handling & empty states

**Errors:** user-facing messages are always friendly and actionable — never a stack trace, raw PHP warning, JSON error body, SQL message, or a technical network exception string. Translate, don't expose: not *"FetchError ECONNREFUSED"* but *"We couldn't refresh the latest event information. Your saved CampBuddy data is still available."* This is the same "guidance over information" principle (§1.0) applied to failure states, not just happy-path content.

**Empty states:** every major screen needs one — a blank screen is never acceptable. Examples already speced inline: My Day (§3.8), Camp Card (§3.10 CC6), Quest (§3.6 C5). The same standard applies to Explore's People/Sponsors/Deals tabs and Contribute before the attendee has answered anything.

### 4.4 Offline-first

Once an event's data has been downloaded, these must keep working with zero connection: Home's guidance (§3.1, from already-downloaded data), the event schedule and My Day (§3.8), Quest (§3.6), Contribute's team info (§3.9), Camp Card (§3.10), the attendee's own onboarding preferences (§3.11), and any sponsor information already downloaded (§3.12 EI1).

Network-dependent features degrade gracefully instead of breaking: if Explore → People (§3.4) can't reach `/api/v1/events/{slug}/discovery`, the app doesn't show a raw error — it shows something like *"You're offline. Your CampBuddy still works — attendee discovery will refresh when you're connected again."*

### 4.5 UI/UX constraints

Mobile-first, fast, clean, friendly, modern, easy to scan, touch-friendly, visually polished — designed for someone walking around a venue with one hand on their phone. Explicitly avoid: cramped layouts, a desktop-style admin-panel look bleeding into the attendee-facing app (that look is fine for `/admin` itself, §5.5 — never for the attendee side), walls of text, excessive modals, tiny tap targets, settings nobody asked for, excessive animation, or an overly corporate tone. Use progressive disclosure — show what's needed when it's needed, not everything at once.

### 4.6 Data synchronization

When connectivity returns: refresh outdated event data, sync explicitly-shared discovery data where applicable (§3.4), and never touch local Quest/My Day/onboarding state beyond what the attendee changed themselves. Reconnecting must never interrupt whatever screen the attendee is currently on — no forced reload, no lost scroll position.

---

## 5. System architecture

One Laravel app, one deploy — no more static-PWA-plus-separate-backend split:

```
/                        Laravel app root (repo root = Laravel app root)
  /public/               Docroot. Vite-built, hashed assets (app.css, app.js bundles),
                          manifest.webmanifest, sw.js, icons. On shared hosting, this is
                          what the domain actually points at (§12 documents the standard
                          Laravel-on-shared-hosting docroot workaround).
  /resources/views/      Blade views — attendee pages (Home, My Day, Camp Quest,
                          Contributor Day, Camp Card, Explore, Offers, More) rendered
                          per event route, plus Breeze's auth views for /admin.
  /resources/js|scss/    Source assets, bundled by Vite (§5.5) — separate entry points
                          for the attendee app (existing BEM/SCSS design system) and the
                          Breeze-scaffolded admin panel (Tailwind + Alpine, admin-only).
  /routes/web.php        Attendee page routes + /admin routes (Breeze)
  /routes/api.php        Public, read-only, CDN-cached JSON API at /api/v1/*
                          (still needed for client-side calls the Blade shell makes after
                          load: live matching data, roster, push subscribe, bookmarks)
  /app/                  Models, Jobs (ingestion), Controllers, Breeze auth scaffolding
```

### 5.1 Request flow (public pages) — updated for server rendering

**Page loads** (e.g. `GET /event/wordcamp-rajasthan-2026`): Browser → CDN (cache hit fast-path for anonymous, unauthenticated views — see §5.4 cache-key caveat) → Laravel resolves the event from the route, injects its branding (colors/name/logo/favicon, §3.2) directly into the Blade layout's CSS custom properties and `<head>` server-side, renders the page. No client-side "fetch event, then apply theme" round trip — this closes a flash-of-unstyled-content gap the original client-rendered design had, and is one real upside of the Blade rewrite.

**AJAX calls from the rendered page** (live matching data, roster, bookmarks, push subscribe) still hit `/api/v1/*`, same stale-while-revalidate, single-flight-lock, DB-cached pattern as V1, ported to Laravel Jobs.

### 5.2 Ingestion flow (new in V2)

Three jobs, two different risk profiles — a one-time asset fetch, a daily REST client, and a daily scraper (§0.1–§0.3):

```
On event approval (one-time, not scheduled):
  → FetchBrandingAssetsJob
      try REST site_icon_url → try homepage header logo <img> / favicon <link> →
      try /favicon.ico fallback (§0.6) → download + re-host on CampBuddy's own
      storage (§3.2 BR4) → admin can override via upload at any time (§3.2 BR5)

Laravel Scheduler (daily, per active event):
  → FetchSpeakersSponsorsSessionsJob
      GET the event's own site: /wp-json/wp/v2/{sessions,speakers,sponsors,organizers}
      + /wp-json/wp/v2/{session_track,sponsor_level} to resolve taxonomy term names
      + a small HTML-link-extraction pass over each speaker's `content` field for
        social links not present in structured meta (§0.1)
      → zero dependency on the retired wpsimplified.in proxy (§0.5)

  → ParseAttendeeRosterJob
      HTML-parses the event's public Attendees page (no REST endpoint exists, §0.3)
      → the one recurring job in this pipeline that needs a markup-change admin alert (§3.3 IN4)

  → writes to attendee_roster, event cache tables
  → logs to fetch_log (same table/purpose as V1), tagged per job type
```

For more than one active event (fast-follow), jobs run **sequentially with a cooldown between events**, never concurrently against multiple upstream sites.

### 5.3 Central event discovery (fast-follow, §2.2 — not required for launch)

Queries the official `api.wordpress.org/events/1.0/` endpoint (verified live, §0.2) rather than scraping HTML — this is the same API that powers the "nearby WordPress events" widget in wp-admin, and it returns a `type` field that lets the query filter to `wordcamp` and ignore `meetup` entries, exactly as needed.

Design consequence of it being location/radius-based, not a flat global list: discovery runs as a **seeded search** — the admin maintains a short list of search locations (e.g. major Indian cities, or wherever CampBuddy's audience is) and the job unions the results, de-duping by event URL. This is why WordCamp Bengaluru didn't appear in a Jaipur-centered test query even though its site is live — the radius simply didn't reach it. A manual "add by slug" fallback in the admin panel (extending V1's existing admin-editable-slug pattern, §9) stays available for any event the seeded search misses.

Every discovered event still lands in an **admin approval queue** — nothing goes live in the app without a human clicking "approve," since the API gives you existence/metadata but nothing about whether the site is far enough along to actually ingest (a freshly-created WordCamp site may have no Attendees/Speakers pages populated yet).

### 5.4 CDN — now mandatory, not optional

Origin stays shared cPanel hosting (no change in hosting budget), but **Cloudflare (or equivalent) sits in front of it in production**, closing the scale/hosting mismatch flagged earlier in this project's planning:

- Public API responses cached at the edge per their existing `Cache-Control`/`ETag` headers.
- Rate limiting (the ~60 req/min/IP and 5 req/min/IP admin-login limits from V1 §8) moves to Cloudflare rules — the DB-backed `rate_limit_hits` table is dropped or kept only as an admin-login-specific secondary layer, not the primary control for public traffic.
- This is a **pre-launch blocker** (§13), not a nice-to-have — without it, the origin's MySQL connection limit is the real ceiling on Oct 3–4 traffic.
- **Caveat introduced by the Blade rewrite:** cleanly CDN-caching full JSON responses (V1's model) is straightforward; caching full server-rendered HTML pages is not, if any Blade page embeds a CSRF token (Laravel does this by default for any page with a form — e.g. the Camp Card profile form, matching profile form). A cached page would serve a stale/mismatched token to every visitor after the first. Since attendees never log in (standing product decision), the fix is to keep attendee-facing forms submitting via the existing `/api/v1/*` JSON endpoints (which are already exempt from Laravel's session-based CSRF middleware the way V1's own CSRF handling worked) rather than native Blade `<form>` POSTs — the Blade pages stay cacheable, mutations go through the API. This must be decided at implementation time, not discovered after the CDN is already misbehaving.

### 5.5 Asset build — Laravel's default Vite pipeline

Replaces V1's standalone `npm run build` (sass-only) script. Vite compiles two separate entry points from one config:

- **Attendee app**: the existing hand-written BEM/SCSS design system (ported as-is, same tokens/components) + the existing vanilla JS logic, now split across Blade partials instead of one `app.js` monolith where it makes sense — no framework (React/Vue) introduced, still vanilla JS, just bundled by Vite instead of hand-invoked `sass`.
- **Admin panel**: Breeze's default stack — Tailwind CSS + Alpine.js — kept **scoped to `/admin`**, not bled into the attendee-facing design system. Two design languages in one repo is an accepted trade-off of using Breeze's scaffolding as-is rather than reskinning Breeze to match the BEM system.

Blade templates reference built assets via `@vite([...])`, which resolves to the hashed filenames from Vite's manifest — this replaces V1's manual CSS/JS cache-busting version bump (V1's B9 "purge cache" admin action) with Vite's own content-hashing. The admin "purge cache" action now only needs to clear the *data* cache (`cache_store`/event tables), not asset versions.

Service worker precaching (`sw.js`) must read Vite's manifest at build time to precache the current hashed filenames — a static precache list (V1's approach) would go stale the moment Vite re-hashes an asset.

---

## 6. Technology stack

| Layer | Technology |
|---|---|
| App structure | **One Laravel app** — no separate frontend/backend repos or mount paths (§5) |
| Templating | **Blade** — attendee pages rendered server-side, per-event branding injected at render time (§5.1) |
| Frontend assets | Vite (§5.5) — attendee app keeps vanilla JS + the existing hand-written BEM/SCSS design system; admin panel uses Breeze's Tailwind + Alpine, scoped to `/admin` only |
| Frontend libraries | `qrcode-generator` (unchanged); Web Push client via the browser's native Push API |
| Backend framework | **Laravel 12** (replaces Slim — deliberate rewrite) |
| Auth scaffolding | **Laravel Breeze** for `/admin` — session-based, Laravel's default Argon2id-capable hasher, replaces V1's hand-rolled login/lockout code |
| Backend language | PHP 8.2+ |
| Queues/Scheduler | Laravel's built-in Queue (database driver, no Redis dependency added) + Scheduler for cron-equivalent jobs |
| REST ingestion | Laravel's HTTP client (Guzzle under the hood) against each event's own `/wp-json/wp/v2/{sessions,speakers,sponsors,organizers,session_track,sponsor_level}` — the primary, structured ingestion path (§0.1) |
| HTML parsing | `symfony/dom-crawler` (or equivalent) — scoped narrowly to the Attendees-page scraper (§0.3) and the speaker-bio social-link extraction (§0.1), not general-purpose scraping |
| Web Push | `minishlink/web-push` (VAPID) |
| Database | MySQL/MariaDB (utf8mb4) — same as V1 |
| Web server | Apache 2.4 + `mod_rewrite`, same shared-hosting target as V1, now behind Cloudflare (§5.4). See §12 for the Laravel-on-shared-hosting docroot workaround (no more `/backend` mount path to configure). |
| Local dev | WAMP (Apache + MySQL + PHP), same stack V1 already runs on — vhost `DocumentRoot` points at Laravel's `public/` folder (§11.4) |
| Testing | Pest (Laravel's modern default) + a minimal Playwright smoke suite (frontend) — see §13 |
| CDN | Cloudflare (or equivalent) — new, mandatory |
| Analytics | GA4, PII-excluded (same posture as V1, hardened — see §8.5) |

---

## 7. Data model

Builds on V1's 8 tables (§7 of V1's doc), adding:

| Table | Purpose |
|---|---|
| `events` | Replaces V1's single-slug `app_settings` entry — one row per WordCamp: slug, display name, branding fields (colors, `logo_path`, `favicon_path` — both re-hosted local files per §3.2 BR4/BR5, never hotlinked), source site URL, lifecycle `status` (`draft` / `approved` / `active` / `archived`), and a separate `is_visible` boolean (**default `true`**, §3.2 BR6) controlling public display independent of `status`. |
| `attendee_roster` | Ingested from each event's public Attendees page: name, gravatar URL, links, `event_id`, `content_hash` (for change detection), suppression flag (§3.3 IN5). |
| `discovery_profiles` | The **only** server-side table for matching (§3.4, §8.3) — one row per attendee who explicitly took the "Join attendee discovery" action (M2): a random, non-guessable **public** `discovery_id` (not derived from anything identifying), a hashed **`owner_token_hash`** (§8.6 — the raw token is never stored, only its hash, and is required to authorize any update/delete on this row), `event_id`, the chosen-to-expose fields (tags, "who I want to meet," optional photo) as JSON, `expires_at` (defaults to the event's end, §3.4 M7). The attendee's full onboarding profile (§3.11) and "met" history (§3.4 M5/M6) **never reach this table or any other server table** — both stay local-only on the device (IndexedDB, §3.13), consistent with "local by default" (§1.0). The app fetches the current event's `discovery_profiles` list and computes tag-overlap matching **client-side**, so a user never has to transmit their own full interest profile just to browse matches — only their own chosen subset, and only once they Join. |
| `session_bookmarks` | Locally-synced bookmark list per device profile, `event_id`-scoped (server-side only if push scheduling needs it; otherwise stays local like Quest progress). |
| `push_subscriptions` | Web Push subscription endpoints, tied to bookmarked sessions, pruned on unsubscribe/expiry. |
| `quests` | Per-event admin-editable quest text (§3.6 C1, C3) — `event_id` nullable for default/cross-event quests. |

`offers` gains an `event_id` foreign key (was implicitly single-event in V1). `fetch_log` gains a `job_type` column to distinguish ingestion jobs from the existing event/media refresh.

Sessions/speakers/sponsors/organizers continue to live in V1's `cache_store` (TTL-cached JSON blob per event, same single-flight-lock pattern) — only the *source* of that data changes, from the `wpsimplified.in` proxy to a direct `wp-json` REST fetch per event (§0.1, §0.5). No new table needed for this data; `events.id` replaces the old single-slug key.

---

## 8. Security & privacy

Carries forward all of V1 §8 (prepared statements, secrets handling, HTTPS, security headers, admin session hardening, Argon2id, error handling, outbound request timeouts, file exposure rules, client-side escaping) unchanged. V2-specific additions:

### 8.1 Rate limiting
Moves to the CDN edge (§5.4). DB-backed limiting is retained only as a secondary control on the admin login route.

### 8.2 Ingestion jobs
Treated as outbound requests to a third party (§8 of V1) — same strict timeouts, same "validate before trusting" posture. Applies to both ingestion mechanisms: the REST client against each event's own `wp-json` (§0.1, the majority of ingested data) and the Attendees-page HTML parser (§0.3, the one part of the pipeline without a stable contract, so it also needs the markup-change alert from §3.3 IN4).

### 8.3 Matching data — the "local by default, shared only by choice" architecture
Matching (§3.4) is the one place V2 asks a user to share more than V1 ever did, so it gets a deliberate privacy boundary rather than a general opt-in checkbox:

- The attendee's full profile (onboarding answers, §3.11) is **local-only** and never touches a CampBuddy server, matching or not.
- Becoming discoverable requires a **separate, explicit action** ("Join attendee discovery," §3.4 M2) — never a side effect of filling in onboarding or any other screen.
- What gets published is a **user-chosen subset** of fields under a **random, non-guessable identifier** — never the attendee's real name, device ID, or full profile.
- Leaving discovery (§3.4 M6) is **one tap**, immediate, and independent from deleting all local data (§3.13) — an attendee can stop being discoverable without losing their Camp Card, Quest progress, or saved sessions.
- Discovery data **expires with the event** (§3.4 M7) — it doesn't quietly persist into next year's WordCamp.
- Discovery data is **never sent to analytics** (extends the GA4 exclusion rule in §8.5).

### 8.4 Ingested roster data — explicit policy
This is other people's data, even though it's already public and opt-in on the source site. CampBuddy:
- Stores only what the source page itself displays (name, gravatar URL, self-provided links) — never anything CampBuddy infers or adds.
- Provides a visible, no-login-required way for anyone to request removal (an email link or simple form), honored via the `attendee_roster` suppression flag (§3.3 IN5) — this is a real gap if skipped, since these people never consented to *CampBuddy* specifically, only to their own event's site.
- Never re-publishes this data anywhere CampBuddy doesn't control (no export, no API for third parties).
- Purges roster data for an event once it moves to `archived` status (past events don't need attendee data sitting around indefinitely).

### 8.5 Analytics guardrail
V1's PII exclusion from GA4 was "enforced by convention" only. V2 hardens this: any `discovery_profiles` field, any locally-stored onboarding/Camp Card/matching data, and any `attendee_roster` field are **never** passed to the `track()` wrapper — enforced by a wrapper-level allowlist (the tracking function only accepts a fixed set of non-PII event names/params, not arbitrary payloads), not developer memory alone.

### 8.6 Discovery API ownership — a real gap, caught and fixed here
An earlier draft of §3.4/§10 had a genuine flaw: `discovery_id` is necessarily public — every client fetching the match list sees every other attendee's `discovery_id`, because that's how matching UI and "met" tracking reference a specific profile at all. If that same public ID were also the sole credential required to update or delete a profile (as originally speced), **any attendee could silently edit or delete any other attendee's discovery profile** just by having seen it in the match list. That's not a hypothetical — it's exploitable by anyone using the app normally, no special access needed.

**Fix, now reflected in §3.4 M2/M6/M8 and §7/§10:** creation issues two separate values — a public `discovery_id` (safe to show to everyone, used for display and matching) and a private **owner token** (a second random value, returned once at creation, stored only in the creating device's local storage, never displayed anywhere, never included in the public GET list). Every mutating call (update, delete) requires the owner token as a bearer credential; the public `discovery_id` alone authorizes nothing. This is the standard pattern for anonymous-but-editable public records (the same shape as a Pastebin delete-code or a webhook secret) and needs no account system to implement, consistent with §2.3's no-accounts decision.

**Implementation note:** store only a hash of the owner token server-side (`owner_token_hash`, §7) — never the raw token — so a database read alone can't be used to forge ownership, same principle as password storage (§8, carried from V1).

---

## 9. Admin panel

Extends V1 §9 with:

Full CRUD on events, not just approve/view — an admin is never limited to what an automated job discovered:

| Section | Capability |
|---|---|
| Events — **Create** | Add a new event manually (slug + source site URL), independent of central discovery (§5.3) — needed for any event the seeded discovery search misses, or before that fast-follow feature ships at all. |
| Events — **Read** | List all events with status (draft/approved/active/archived) and visibility (enabled/disabled, §3.2 BR6), filterable/searchable. |
| Events — **Update** | Edit slug, display name, source site URL, lifecycle status, branding (colors, logo/favicon upload — overrides auto-fetch per §3.2 BR5), and the **frontend visibility toggle** (default on, §3.2 BR6) — approving a centrally-discovered event (§5.3, fast-follow) is one specific case of this. |
| Events — **Delete** | Hard delete is allowed only for `draft` events with no ingested data yet (nothing real to lose). An `approved`/`active`/`archived` event — one that's had real roster/offers/quest data tied to it — can only be **archived**, not hard-deleted, so a slip of the mouse can't destroy a past event's history; archiving already purges roster data per §8.4. |
| Ingestion status | Per-event last roster-fetch time/status, last speaker/sponsor-fetch time/status (extends V1's dashboard fetch-log display), manual "Refresh now" per event, manual "Re-fetch branding assets" (§3.2 BR4) separate from the daily data refresh. |
| Roster suppression | View/search the ingested roster for an event, manually suppress an entry (§8.4 takedown path). |
| Quest editor | Full CRUD on the 8–10 event-specific quests per event (§3.6 C1, C3) — add, edit, reorder, delete, not just edit existing ones. Default/cross-event quests are seeded, not per-event admin content. |
| Offers | Same as V1 (full CRUD), now filtered/scoped by event. |

---

## 10. API specification

Same envelope conventions as V1 (`/api/v1/*`, public, GET-only, JSON, cached/rate-limited at the edge). New/changed endpoints:

| Endpoint | Returns |
|---|---|
| `GET /api/v1/events` | Events with `status=active` **and** `is_visible=true` (§3.2 BR6) — launch: just WCR |
| `GET /api/v1/events/{slug}` | Single event detail, incl. branding fields |
| `GET /api/v1/events/{slug}/roster` | Ingested attendee roster (name, gravatar, links) — paginated |
| `GET /api/v1/events/{slug}/discovery` | All active `discovery_profiles` for the event, `discovery_id` + chosen fields only, **never `owner_token_hash`** (§7, §8.6) — the app fetches this list and matches client-side (§3.4 M2) |
| `POST /api/v1/events/{slug}/discovery` | Create a new discovery profile — the "Join attendee discovery" action (§3.4 M2). Response includes the `discovery_id` **and** the one-time-shown owner token (§8.6); the client stores both locally and never gets the token again. |
| `PATCH /api/v1/events/{slug}/discovery/{discovery_id}` | Update this profile's exposed fields — requires the owner token as a bearer credential (§8.6); a request without it, or with the wrong one, gets a generic 403, not a hint about which part was wrong. |
| `DELETE /api/v1/events/{slug}/discovery/{discovery_id}` | "Leave attendee discovery" (§3.4 M6) — same owner-token requirement as the `PATCH` above. |
| `GET /api/v1/events/{slug}/quests` | Quest items for the event — default + event-specific + Contributor Day quests merged (§3.6) |
| `GET /api/v1/events/{slug}/offers` | Active offers for that event |
| `POST /api/v1/push/subscribe` | Register a Web Push subscription for a bookmarked session |
| `GET /api/v1/health` | Unchanged from V1 |

Existing V1 endpoints (`event`, `media`, `sponsors`, `agenda`) become event-scoped: `/api/v1/events/{slug}/media`, etc.

---

## 11. Installation & setup

One Laravel app — no more "frontend-only" mode without the backend (V1's ability to run the static PWA standalone via `python3 -m http.server` no longer applies, since pages are now Blade-rendered).

### 11.1 Prerequisites

PHP 8.2+, Composer, Node.js (for Vite), MySQL/MariaDB, Apache with `mod_rewrite` + `AllowOverride All`.

### 11.2 First-time setup

```bash
composer install
composer require laravel/breeze --dev
php artisan breeze:install blade      # scaffolds /admin auth views (Tailwind + Alpine)
cp .env.example .env
php artisan key:generate
npm install
npm run build                          # or `npm run dev` while developing (Vite HMR)
php artisan migrate
php artisan db:seed --class=AdminUserSeeder   # creates the first admin login
php artisan campbuddy:ingest wordcamp-rajasthan-2026   # bootstraps one event: runs
                                                          # FetchBrandingAssetsJob once,
                                                          # then FetchSpeakersSponsorsSessionsJob
                                                          # and ParseAttendeeRosterJob (§5.2)
```

`.env` additions specific to V2: VAPID keypair for Web Push (§3.5), no `BACKEND_MOUNT_PATH` needed anymore (V1 had this — obsolete now that there's no separate `/backend` mount).

### 11.3 Styling & assets

Don't hand-edit built files in `public/build/` — they're Vite output. During development, `npm run dev` watches and hot-reloads both the attendee SCSS/JS entry point and the admin Tailwind entry point (§5.5). `npm run build` produces the hashed production bundle Blade's `@vite()` directive resolves against.

### 11.4 Local WAMP setup

Same pattern as V1, with one change — the vhost `DocumentRoot` now points at Laravel's `public/` folder, not the repo root:

```apache
<VirtualHost *:80>
    ServerName campbuddy.test
    DocumentRoot "path/to/campbuddy/V2/public"
    <Directory "path/to/campbuddy/V2/public">
        AllowOverride All
        Require local
    </Directory>
</VirtualHost>
```

Visit `http://campbuddy.test/` for the attendee app and `http://campbuddy.test/admin` for the admin panel (Breeze-scaffolded login).

---

## 12. Deployment (shared/cPanel hosting, behind Cloudflare)

**Docroot workaround (the one real complication of "just Laravel" on shared hosting):** Laravel expects `public/` to *be* the docroot, but many cPanel accounts can't point a domain's document root at a subfolder. Check first — some hosts allow changing it under cPanel → Domains. If not, use Laravel's standard workaround: upload the whole app **above** the public web root (e.g. one level up from `public_html`), then copy `public/index.php` and `public/.htaccess` into `public_html` and edit the two `require`/`bootstrap` paths in `index.php` to point up to the real `vendor/autoload.php` and `bootstrap/app.php`. This keeps `.env`, `app/`, `database/`, etc. outside the web-servable directory entirely — a stronger security posture than V1's dotfile-deny-rules approach, since the files aren't reachable at all rather than reachable-but-denied.

1. Provision Cloudflare (or equivalent) in front of the domain — **do this before go-live, not after** (§5.4).
2. Upload the app per the docroot approach decided above.
3. `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build` (Vite production bundle, §5.5).
4. Configure `.env`: DB credentials, fresh `APP_KEY` (`php artisan key:generate`), VAPID keypair for push, `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://yourdomain`.
5. `php artisan migrate --force`, seed the admin user, then `php artisan campbuddy:ingest wordcamp-rajasthan-2026` (same bootstrap command as §11.2) to pull in branding, speakers/sponsors/sessions, and the attendee roster for the first time.
6. cPanel Cron needs **exactly one entry**, running every minute — Laravel's scheduler then dispatches each job on its own configured cadence internally (`FetchSpeakersSponsorsSessionsJob` every 15 minutes, matching V1's old refresh cadence; `ParseAttendeeRosterJob` once daily per §3.3 IN2; `FetchBrandingAssetsJob` only on-demand, not on this schedule at all, per §5.2):
   ```
   * * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
   ```
7. Confirm Cloudflare is actually caching page and API responses (check `cf-cache-status` header) before calling this done — a misconfigured CDN that passes everything through provides zero protection. Re-check the CSRF/cacheability caveat from §5.4 while doing this.
8. Confirm `.env`, `composer.json`, `*.sql`, and everything outside `public/` returns 404/403 (or isn't reachable at all, if using the docroot workaround); `/api/v1/health` returns OK.

---

## 13. Pre-launch blockers (must close before "production ready")

Per the project decision that these are blockers, not roadmap items:

| Blocker | Definition of done |
|---|---|
| CDN live | Cloudflare in front of production, cache-status header confirms edge hits on the public API (§5.4, §12.7). |
| `composer audit` | Runs clean (no known-vulnerable dependencies) as part of the deploy checklist, re-run before every production deploy. |
| Manual OWASP pass | You (or a reviewer) walk the OWASP Top 10 against the actual deployed app once before Oct 3 — documented as a short pass/fail checklist, not a formal external audit. Explicitly confirm the §8.6 owner-token check works (try mutating another profile without the token — must fail), and spot-check §21.3's Form Request/mass-assignment rules on the admin and discovery endpoints. |
| Code-level hygiene spot-check | Not a full audit — a targeted pass confirming §21 was actually followed where it matters most: no file-based locking anywhere (§21.1), the discovery/roster/sessions endpoints are paginated (§21.2), and list-rendering pages don't have an obvious N+1 (check the query count on Home and My Day with Laravel's query log/Debugbar in dev). |
| Acceptance journey green | The full 17-step journey in §15.2, automated where practical (Playwright) and manually walked through where it isn't — including the offline/reconnect steps. Green before launch. |
| Definition of Done checklist | The full list in §15.3 reviewed and checked off, not just the items above. |
| LICENSE committed | GPL-2.0-or-later, as a `LICENSE` file at the repo root — finalized, not a suggestion in prose (all current dependencies — Laravel, Guzzle-equivalents, `qrcode-generator` — are MIT/permissive and compatible). |
| Roster takedown path live | The removal-request mechanism in §8.4 exists and is reachable before any roster data is ingested for WCR — this isn't deferrable once real people's data is involved. |

---

## 14. Fallback plan

V1 remains fully functional and deployable independently. If any of §13's blockers can't close in time, or the Laravel rewrite hits a wall before Oct 3, **V1 ships for WordCamp Rajasthan 2026 instead of an unfinished V2** — this is a live decision point to revisit a few days out, not a theoretical option. V2 continuing past the event date has no deadline pressure either way (per the project decision to decouple V2's completion from any single event once WCR is covered one way or the other).

---

## 15. Testing

### 15.1 Backend

Pest/PHPUnit, same field-mapping coverage philosophy as V1 (§13 of V1's doc), extended to the new ingestion parsers (mock HTML fixtures, not live requests, in CI).

### 15.2 Core user journey (acceptance test)

The canonical end-to-end script — if any step feels unnatural, the feature behind it isn't finished, regardless of what its own unit tests say. Automate what Playwright can reach; walk the rest manually (notably steps 14–16, the offline/reconnect behavior).

A brand-new attendee should be able to:

1. Open CampBuddy.
2. Select their WordCamp (§3.11 OB2).
3. Complete or skip onboarding (§3.11).
4. Understand what is currently happening (§3.1 H1).
5. Browse sessions (§3.8 MD2).
6. Save a session (§3.8 MD3).
7. Build My Day, including seeing a bookmark-overlap warning (§3.8 MD4).
8. Complete a Quest (§3.6).
9. Discover what Contributor Day is (§3.9 CD1).
10. Get a contributor-team recommendation (§3.9 CD2).
11. Build a Camp Card (§3.10 CC1–CC2).
12. Display its QR code fullscreen (§3.10 CC3–CC4).
13. Explore sponsors and deals (§3.12 EI1, §3.7).
14. Lose internet connectivity.
15. Continue using all major locally-available features while offline (§4.4).
16. Reconnect without losing progress or being interrupted mid-screen (§4.6).
17. Export or clear their information (§3.13).

### 15.3 Definition of Done

CampBuddy V2 is only "production ready" when all of the following are true, not just when §13's technical blockers are closed:

- No mandatory account/signup system exists anywhere in the flow.
- Event selection and event-specific branding both work (§3.11 OB2, §3.2).
- Home provides genuinely contextual guidance, not a static card dashboard (§3.1).
- My Day, session bookmarking, and Quest all work (§3.8, §3.6).
- Contribute gives a real team recommendation with plain-language explanations (§3.9).
- Explore (People, Sponsors, Deals, Event Info) works end to end (§3.4, §3.7, §3.12).
- Camp Card and its QR generation work (§3.10).
- All personal state persists locally and survives reload/restart (§3.13 DP4).
- Privacy boundaries are respected: attendee discovery is explicit opt-in with a working leave path (§8.3, §3.4 M2/M6).
- Core features work fully offline per the §4.4 floor.
- PWA install and standalone-mode behavior work on both Android and iOS.
- The app is usable across the breakpoints in §15.4, with no horizontal scroll, no overlapping/clipped controls, and the Camp Card QR still scannable at every size.
- Accessibility practices from §4.1 are implemented, not deferred.
- Errors and empty states are handled per §4.3 everywhere, not just on the screens explicitly listed there.
- Event data is not hard-coded in the frontend — adding another WordCamp is a matter of configuring event data (§7, §9), not editing application code.
- No existing, working CampBuddy functionality has been broken in the process (§20 engineering principles).

### 15.4 Responsive testing

At minimum: mobile at ~360px, ~390px, and ~430px widths; tablet in both portrait and landscape; desktop (mobile stays the design priority, but desktop must still work correctly, per §4.5). Check specifically for: no horizontal scroll, no overlapping components, no clipped controls, a navigation bar that stays usable at every width, long session/speaker names wrapping gracefully rather than overflowing, and the Camp Card QR remaining scannable at every tested size.

---

## 16. Known limitations & roadmap

- Matching only works among CampBuddy users (§0.4, §3.4) — this is a permanent architectural fact, not a v1-of-V2 limitation to "fix" later. Worth stating clearly in-app so users understand why not every roster entry shows as a potential match.
- Multi-event browsing, central auto-discovery, and full per-event theming are fast-follow (§2.2), explicitly not blocking the Oct 3–4 launch.
- Card PNG export at 2×, "scan a card," multi-tenant beyond WordCamp — carried forward from V1 as explicitly out of scope (§2.3).
- No formal third-party security audit — the manual OWASP pass (§13) is the accepted bar for this launch; revisit for a real audit once the app has a second event under its belt.

---

## 17. Privacy

Carries forward all of V1 §15 unchanged, plus: the formal "local by default, shared only by choice" principle (§1.0), the anonymous-discovery-identifier matching architecture (§3.4, §8.3), ingested roster data policy (§8.4), and the hardened analytics guardrail (§8.5) are now part of the app's public-facing privacy statement, not just internal engineering discipline. This is the plain-language version worth putting in front of an attendee: *your onboarding answers, Camp Card drafts, and Quest progress stay on your phone; the only thing that ever leaves it is what you explicitly choose to publish for matching, under a random ID, and you can take it back with one tap, at any time.*

---

## 18. Credits & license

Crafted by WPSimplified with love of the WordPress Community.

**License: GPL-2.0-or-later** — finalized per §13 (`LICENSE` file committed at repo root before launch).

---

## 19. Suggested build order (10-day window)

Sequenced so that something demoable exists early, and every day ends with the app in a working (if incomplete) state rather than a pile of half-wired pieces. Each phase assumes the previous one is functional, not polished. Fallbacks are built before enhancements throughout (in-app banner before push, empty states alongside their screens, not after).

1. **Foundation.** Fresh Laravel 12 app, migrations for §7's tables, Breeze admin auth (§6), WAMP vhost pointed at `public/` (§11.4). Done when: you can log into `/admin` locally.
2. **Event CRUD + first data pull.** Admin panel's Events Create/Read/Update (§9), then `FetchSpeakersSponsorsSessionsJob` (§0.1, §5.2) wired to WCR. Done when: WCR exists as an event record with real sessions/speakers/sponsors visible in the admin dashboard's ingestion status.
3. **Branding.** `FetchBrandingAssetsJob` (§3.2 BR4) + manual upload override (BR5) + the visibility toggle (BR6, default on) + the contrast guardrail (BR7). Done when: WCR's actual logo/colors render on a test page and pass the contrast check.
4. **Onboarding + Home.** The 4-step flow (§3.11) feeding Home's Happening Now/Up Next (§3.1 H1–H2) against WCR's real schedule data. Done when: a fresh device can complete onboarding and land on a Home screen that's actually contextual, not placeholder text.
5. **My Day + session details.** Full Schedule, My Schedule, session detail page, overlap warning (§3.8). Done when: a first-timer can browse WCR's schedule and build a personal day end to end.
6. **Camp Card + Quest.** F10 and F6 (§3.10, §3.6) — both largely self-contained and don't depend on anything built after this point. Done when: a QR-scannable Camp Card generates correctly and a quest can be marked complete.
7. **Contribute.** The question flow + fixed team list + per-team explanations (§3.9). Done when: answering the questions produces a real, correctly-worded team recommendation.
8. **Explore — Sponsors, Deals, Event Info.** §3.7, §3.12 — mostly a display layer over data already ingested in step 2. Done when: WCR's actual sponsors and event info render correctly.
9. **Attendee roster + People matching.** `ParseAttendeeRosterJob` (§3.3) first, then the full discovery architecture — Join/Leave, anonymous IDs, client-side matching (§3.4, §8.3). Built last among the data features deliberately: it's the riskiest ingestion job (§0.3) and the most privacy-load-bearing feature, so it shouldn't be rushed to fit around other work.
10. **Sessions bookmarking + notifications.** In-app banner first (§3.5 N2, guaranteed path), Web Push second (N1, best-effort, permission requested only after a bookmark per N1/N4).
11. **Data controls.** Export/Clear (§3.13) — needs everything above to exist first, since it bundles all of it.
12. **Cross-cutting passes, run throughout rather than saved for the end:** empty states (§4.3) alongside each screen as it's built, accessibility (§4.1) checked per-screen not as one big audit, responsive testing (§15.4) after each major screen lands.
13. **Pre-launch blockers (§13), run in parallel with steps 9–11 where possible:** Cloudflare live, `composer audit`, OWASP pass, the full acceptance journey (§15.2), LICENSE file, roster takedown path.
14. **Buffer + go/no-go.** Whatever's left unfinished a day or two out triggers the fallback decision in §14 — decide deliberately, don't let the calendar decide by default.

---

## 20. Engineering principles

Process discipline for whoever (or whichever agent) implements this spec, carried over verbatim from the product brief because they matter as much as the feature list:

1. Inspect and understand the existing project structure before adding to it.
2. Reuse existing components where appropriate — don't rewrite stable, working functionality unnecessarily.
3. Don't change unrelated functionality while implementing a specific requirement.
4. Keep components modular; avoid giant files mixing unrelated logic.
5. Separate concerns: presentation, business logic, event data, persistence, and API communication each stay in their own layer (Laravel's own conventions — Models, Jobs, Controllers, Blade views, §6 — already push in this direction; don't fight them).
6. Maintain backwards compatibility with existing CampBuddy functionality — don't remove a feature unless a requirement explicitly replaces it.
7. Code should be readable, appropriately named, documented where the *why* isn't obvious from the code itself, and free of unnecessary duplication.
8. Don't add a dependency when a small amount of native functionality solves the problem cleanly (§4.2, §5.5 — vanilla JS stays the default).
9. Don't present placeholder implementations as finished features — an empty state (§4.3) is a deliberate design choice; a silently-broken feature dressed up to look done is not.
10. Test responsive behavior (§15.4) before considering any UI feature finished, not as a separate pass at the very end.

---

## 21. Performance & scalability hygiene (code-level, hosting-independent)

§5.4 and the conversation that led to this section were explicit: don't build shared-hosting-scale CampBuddy as if it needs to survive a million concurrent users today — that's solving a problem that doesn't exist yet, at the cost of the deadline that does. This section is the other half of that argument: there's a set of things that cost **almost nothing to do correctly the first time**, entirely independent of what infrastructure they run on, and skipping them is what actually causes a painful rewrite later. None of this requires more servers, Redis, or a bigger hosting plan now — it requires writing code against Laravel's existing abstractions instead of around them.

### 21.1 Use Laravel's swappable abstractions, never a hand-rolled equivalent

The entire point of these is that scaling later becomes a **config change**, not a rewrite, if they're used correctly from day one:

| Concern | Rule | Why it matters at scale |
|---|---|---|
| Caching | Always `Cache::` facade (event data, discovery lists, ingestion results) — never a hand-rolled file cache or static variable. | Driver flips from `file`/`database` to Redis via `.env` alone if traffic ever justifies it. |
| Background work | Everything from §5.2's ingestion jobs to future notification sends goes through Laravel Jobs/Queue — never a synchronous blocking call inside a web request. | Queue driver flips from `database` to Redis/SQS the same way; code doesn't change. |
| Single-flight locking | The stale-while-revalidate lock (inherited from V1, §5.1) **must** use `Cache::lock()`, never a file-based lock (`flock`, a lock file on disk). | A file lock only works on one server. It fails silently — not loudly — the moment there's a second app server, producing duplicate ingestion runs or race conditions that are miserable to debug after the fact. `Cache::lock()` is correct on day one *and* correct if the cache driver later becomes distributed. |
| File storage | Branding assets (§3.2 BR4), Camp Card exports (§3.13 DP1), any file CampBuddy itself writes — always the `Storage::disk()` facade, never a hardcoded local path. | Migrating to S3-compatible storage later is a disk config, not a search-and-replace across the codebase. |
| Sessions | Admin auth (§6, Breeze) stays behind Laravel's session abstraction — no raw `$_SESSION` access anywhere. | Same swap-without-rewrite story if the admin panel ever needs a distributed session store. |

### 21.2 Database query discipline

Cheap to do right the first time, expensive to retrofit once tables have real data in them:

- **Eager-load relationships explicitly** everywhere a list is rendered (roster, discovery matches, sessions with speakers) — an N+1 query pattern is invisible with 10 test rows and a real incident with 10,000.
- **Index what gets queried, not just primary keys**: every `event_id` foreign key, `discovery_id`, `slug` columns, `content_hash` (§3.3 IN1) — add these in the migrations that create the tables (§7), not as an emergency patch later.
- **Paginate every list-returning endpoint** — roster (§10), discovery (§10), sessions — never return an unbounded result set, even though today's numbers are small enough that it wouldn't show up as a problem.
- **Chunk, don't load-all-then-loop**, in the ingestion jobs (§5.2) — `chunk()`/`lazy()` when writing or reading many rows, so memory use doesn't scale linearly with attendee/session count.
- Select only the columns a query actually needs on hot paths (Home's data load, §3.1 H6) — not a strict rule everywhere, but worth applying where a query runs on every page load.

### 21.3 Security discipline that scales with the codebase, not just the traffic

Most of this is already covered in §8 for the specific features that exist today; these are the general-purpose rules that keep applying as more admin forms and API endpoints get added over time:

- **Laravel Form Requests for all incoming data** — every admin form (§9) and every mutating API call (the discovery `POST`/`PATCH` in §10, above all) validates through a dedicated Form Request class, not ad-hoc `$request->input()` checks scattered in a controller. Centralizing this is what makes it tractable to audit later.
- **Explicit `$fillable` on every Eloquent model** — never `$guarded = []`. Mass-assignment protection is a one-line decision per model that prevents an entire class of "a form field the frontend didn't mean to expose just became writable" bugs.
- **Never `{!! !!}` on anything that isn't hand-authored by CampBuddy itself.** Ingested speaker bio `content` (§0.1) comes from someone else's WordPress install — treat it as untrusted and sanitize before rendering, exactly like any other user-supplied string (§8's V1-carried rule), even though it's "official" event content.
- **App-level rate limiting as defense-in-depth**, via Laravel's `throttle` middleware, on top of the CDN-edge limiting in §5.4/§8.1 — specifically on the discovery `POST`/`PATCH`/`DELETE` endpoints (§8.6) and admin login. If the CDN is ever misconfigured or bypassed, these routes shouldn't be wide open as a result.
- **Laravel Policies for admin authorization**, not a flat "is this user logged in" check scattered across controllers — costs the same to write now, and means adding a second admin role later (e.g. an offers-only sponsor-liaison account) is a policy change, not a rearchitect of every admin controller.

None of §21.1–21.3 changes the shared-hosting deployment target in §12, the CDN-first scaling story in §5.4, or the 10-day timeline in §19 — it's the difference between "this code happens to work on one server" and "this code works on one server today and doesn't have to be rewritten if that ever changes."
