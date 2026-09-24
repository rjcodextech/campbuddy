# 21. Performance & scalability hygiene (code-level, hosting-independent)

[← Back to index](../SKILL.md) · Previous: [20. Engineering principles](20-engineering-principles.md) · Next: [22. Analytics (GA4) →](22-analytics.md)

[5.4](05-system-architecture.md#54-cdn--now-mandatory-not-optional) was explicit: don't build shared-hosting-scale CampBuddy as if it needs to survive a million concurrent users today. This section is the other half of that argument: a set of things that cost **almost nothing to do correctly the first time**, and skipping them is what actually causes a painful rewrite later.

## 21.1 Use Laravel's swappable abstractions, never a hand-rolled equivalent

| Concern | Rule | Why it matters at scale |
|---|---|---|
| Caching | Always `Cache::` facade — never a hand-rolled file cache or static variable. | Driver flips from `file`/`database` to Redis via `.env` alone if traffic ever justifies it. |
| Background work | Everything from [5.2](05-system-architecture.md#52-ingestion-flow)'s ingestion jobs to notification sends goes through Laravel Jobs/Queue — never a synchronous blocking call inside a web request. | Queue driver flips the same way; code doesn't change. |
| Single-flight locking | The stale-while-revalidate lock (inherited from V1, [5.1](05-system-architecture.md#51-request-flow-public-pages--updated-for-server-rendering)) **must** use `Cache::lock()`, never a file-based lock. | A file lock only works on one server, and fails silently the moment there's a second app server. |
| File storage | Branding assets, Camp Card exports, any file CampBuddy itself writes — always the `Storage::disk()` facade, never a hardcoded local path. | Migrating to S3-compatible storage later is a disk config, not a search-and-replace. |
| Sessions | Admin auth ([6](06-technology-stack.md), Breeze) stays behind Laravel's session abstraction — no raw `$_SESSION` access anywhere. | Same swap-without-rewrite story if the admin panel ever needs a distributed session store. |

## 21.2 Database query discipline

- **Eager-load relationships explicitly** everywhere a list is rendered (roster, discovery matches, sessions with speakers) — an N+1 pattern is invisible with 10 test rows and a real incident with 10,000.
- **Index what gets queried, not just primary keys**: every `event_id` foreign key, `discovery_id`, `slug` columns, `content_hash` ([3.3](03-functional-requirements/03-roster-ingestion.md) IN1).
- **Paginate every list-returning endpoint** — roster ([10](10-api-specification.md)), discovery ([10](10-api-specification.md)), sessions.
- **Chunk, don't load-all-then-loop**, in the ingestion jobs ([5.2](05-system-architecture.md#52-ingestion-flow)).
- Select only the columns a query actually needs on hot paths (Home's data load, [3.1](03-functional-requirements/01-home.md) H6).

> **Current implementation note (measured, then fixed):** a review on emulated phones found — **My Day's page was 272 KB** because every speaker's bio travelled as raw WordPress block markup (197 KB of it); it now sends plain-text `bio_text` (the browser only ever showed text), ~62 KB, and the text drops the speaker page's own heading and social-button labels. **Attendee pages no longer start a session** (no DB write, no cookies per view — [8.2a](08-security-privacy.md#82a-hardening-pass-full-review)). **`public/.htaccess`** gained guarded `mod_deflate` (text/JSON/SVG) and `Cache-Control` for `/build/assets/*` (`immutable`, one year — content-hashed names) and `/media/*` (one day). Roster and speaker avatars are `loading="lazy" decoding="async"` with their real size (Explore → People used to fire ~200 image requests at once). Home's "now" is the **device clock** (it used the server render time, which is stale on a page served offline from the service worker). Measured on the local build afterwards: 15–52 requests and 184–434 KB uncompressed per page, LCP ≈ 0.4–0.7 s, CLS ≈ 0.

## 21.3 Security discipline that scales with the codebase, not just the traffic

- **Laravel Form Requests for all incoming data** — every admin form ([9](09-admin-panel.md)) and every mutating API call (the discovery `POST`/`PATCH` in [10](10-api-specification.md), above all) validates through a dedicated Form Request class.
- **Explicit `$fillable` on every Eloquent model** — never `$guarded = []`.
- **Never `{!! !!}` on anything that isn't hand-authored by CampBuddy itself.** Ingested speaker bio `content` ([finding 0.1](00-findings.md)) comes from someone else's WordPress install — treat it as untrusted and sanitize before rendering.
- **App-level rate limiting as defense-in-depth**, via Laravel's `throttle` middleware, on top of the CDN-edge limiting in [5.4](05-system-architecture.md#54-cdn--now-mandatory-not-optional)/[8.1](08-security-privacy.md#81-rate-limiting) — specifically on the discovery `POST`/`PATCH`/`DELETE` endpoints ([8.6](08-security-privacy.md#86-discovery-api-ownership--a-real-gap-caught-and-fixed-here)) and admin login.
- **Laravel Policies for admin authorization**, not a flat "is this user logged in" check scattered across controllers.

None of §21.1–21.3 changes the shared-hosting deployment target in [12](12-deployment.md), the CDN-first scaling story in [5.4](05-system-architecture.md#54-cdn--now-mandatory-not-optional), or the timeline in [19](19-build-order.md) — it's the difference between "this code happens to work on one server" and "this code works on one server today and doesn't have to be rewritten if that ever changes."
