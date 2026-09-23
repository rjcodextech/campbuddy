# 5. System architecture

[← Back to index](../SKILL.md) · Previous: [4. Non-functional requirements](04-non-functional-requirements.md) · Next: [6. Technology stack →](06-technology-stack.md)

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

## 5.1 Request flow (public pages) — updated for server rendering

**Page loads** (e.g. `GET /event/wordcamp-rajasthan-2026`): Browser → CDN (cache hit fast-path for anonymous, unauthenticated views — see [5.4](#54-cdn--now-mandatory-not-optional) cache-key caveat) → Laravel resolves the event from the route, injects its logo/name (see [3.2](03-functional-requirements/02-branding.md) — color injection was later removed) directly into the Blade layout server-side, renders the page. No client-side "fetch event, then apply theme" round trip.

**AJAX calls from the rendered page** (live matching data, roster, bookmarks, push subscribe) still hit `/api/v1/*`, same stale-while-revalidate, single-flight-lock, DB-cached pattern as V1, ported to Laravel Jobs.

## 5.2 Ingestion flow

Three jobs, two different risk profiles — a one-time asset fetch, a daily REST client, and a daily scraper (see [finding 0.1–0.3](00-findings.md)):

```
On event approval (one-time, not scheduled):
  → FetchBrandingAssetsJob
      try REST site_icon_url → try homepage header logo <img> / favicon <link> →
      try /favicon.ico fallback (finding 0.6) → download + re-host on CampBuddy's own
      storage (§3.2 BR4) → admin can override via upload at any time (§3.2 BR5)

Laravel Scheduler (every 15 min, per active event):
  → FetchSpeakersSponsorsSessionsJob
      GET the event's own site: /wp-json/wp/v2/{sessions,speakers,sponsors,organizers}
      + /wp-json/wp/v2/{session_track,sponsor_level} to resolve taxonomy term names
      + a small HTML-link-extraction pass over each speaker's `content` field for
        social links not present in structured meta (finding 0.1)
      → zero dependency on the retired wpsimplified.in proxy (finding 0.5)

Laravel Scheduler (daily, per active event):
  → ParseAttendeeRosterJob
      HTML-parses the event's public Attendees page (no REST endpoint exists, finding 0.3)
      → the one recurring job in this pipeline that needs a markup-change admin alert (§3.3 IN4)

Laravel Scheduler (daily, offset from the roster job):
  → EvaluateEventLifecycleJob (added post-launch, see §5.3)
      auto-archives events whose dates have passed, auto-publishes a draft once its
      site is reachable and has ≥1 public attendee

  → writes to attendee_roster, event cache tables
  → logs to fetch_log (same table/purpose as V1), tagged per job type
```

For more than one active event, jobs run **sequentially with a cooldown between events**, never concurrently against multiple upstream sites.

## 5.3 Central event discovery

> **Current implementation note — supersedes most of this section as originally written.** The original plan below queried `api.wordpress.org/events/1.0/`, a location/radius "events near X" API with no flat global query, requiring a seeded search across dozens of cities to approximate global coverage. **What actually shipped instead:** `WordCampDiscoveryScraper` reads `events.wordpress.org`'s own upcoming in-person WordCamps listing directly — a JS-rendered filter page with no public JSON endpoint, but which embeds the complete dataset server-side as a `globalEventsPayload["eventsN"] = {...}` assignment (extracted via regex, matched generically since the `N` suffix varies per request). This is a complete, non-seeded list — a strict improvement, and the seed-location approach described below was removed entirely (`WordPressEventsClient` was deleted). Runs every 2 days (`0 3 */2 * *` — Laravel's scheduler has no native "every N days," so this is a day-of-month step, not a rolling 48-hour timer). Every discovered event still lands as an **admin approval queue** draft — nothing auto-publishes from discovery alone; auto-publishing is a separate, narrower rule (below).
>
> **Auto-publish / auto-archive (added post-launch, not in the original plan at all):** `EvaluateEventLifecycleJob` runs daily. A draft event is promoted straight to `active` once its own website responds successfully *and* the roster scrape ([3.3](03-functional-requirements/03-roster-ingestion.md)) finds at least one real, non-suppressed attendee — trusted as a signal the event is real and far enough along, without needing a human click. Separately, any `active`/`approved` event whose dates have fully passed is auto-archived; a draft that's already past and never got promoted is just left alone (never archived, since it was never live).

Original plan (kept for history): query the official `api.wordpress.org/events/1.0/` endpoint — the same API that powers the "nearby WordPress events" widget in wp-admin, returning a `type` field to filter to `wordcamp` and ignore `meetup` entries. Being location/radius-based rather than a flat global list, discovery would run as a **seeded search** across an admin-maintained list of locations, unioning results and de-duping by event URL, with a manual "add by slug" fallback for anything the seeded search missed.

## 5.4 CDN — mandatory, not optional

Origin stays shared cPanel hosting (no change in hosting budget), but **Cloudflare (or equivalent) sits in front of it in production**:

- Public API responses cached at the edge per their existing `Cache-Control`/`ETag` headers.
- Rate limiting (the ~60 req/min/IP and 5 req/min/IP admin-login limits from V1) moves to Cloudflare rules — the DB-backed `rate_limit_hits` table is dropped or kept only as an admin-login-specific secondary layer, not the primary control for public traffic.
- This is a **pre-launch blocker** ([13](13-prelaunch-blockers.md)), not a nice-to-have — without it, the origin's MySQL connection limit is the real ceiling on launch-day traffic.
- **CSRF/cacheability caveat:** cleanly CDN-caching full JSON responses is straightforward; caching full server-rendered HTML pages is not, if any Blade page embeds a CSRF token. Since attendees never log in, the fix is to keep attendee-facing forms submitting via the existing `/api/v1/*` JSON endpoints (already exempt from Laravel's session-based CSRF middleware) rather than native Blade `<form>` POSTs — the Blade pages stay cacheable, mutations go through the API.

## 5.5 Asset build — Laravel's default Vite pipeline

Vite compiles two separate entry points from one config:

- **Attendee app**: hand-written BEM/SCSS design system + vanilla JS logic, split across Blade partials instead of one `app.js` monolith where it makes sense — no framework (React/Vue) introduced.
- **Admin panel**: Breeze's default stack — Tailwind CSS + Alpine.js — kept **scoped to `/admin`**, not bled into the attendee-facing design system.

Blade templates reference built assets via `@vite([...])`, resolving to hashed filenames from Vite's manifest. Service worker precaching (`sw.js`) reads Vite's manifest at build time so it never precaches a stale hashed filename.
