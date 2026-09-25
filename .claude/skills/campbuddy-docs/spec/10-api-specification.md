# 10. API specification

[← Back to index](../SKILL.md) · Previous: [9. Admin panel](09-admin-panel.md) · Next: [11. Installation & setup →](11-installation-setup.md)

Same envelope conventions as V1 (`/api/v1/*`, public, GET-only except where noted, JSON, cached/rate-limited at the edge). New/changed endpoints:

| Endpoint | Returns |
|---|---|
| `GET /api/v1/events` | Events with `status=active` **and** `is_visible=true` ([3.2](03-functional-requirements/02-branding.md) BR6) |
| `GET /api/v1/events/{slug}` | Single event detail |
| `GET /api/v1/events/{slug}/roster` | Ingested attendee roster (name, gravatar, links) — paginated |
| `GET /api/v1/events/{slug}/discovery` | All active `discovery_profiles` for the event, `discovery_id` + chosen fields only, **never `owner_token_hash`** ([7](07-data-model.md), [8.6](08-security-privacy.md#86-discovery-api-ownership--a-real-gap-caught-and-fixed-here)) |
| `POST /api/v1/events/{slug}/discovery` | Create a new discovery profile — the "Join attendee discovery" action ([3.4](03-functional-requirements/04-matching.md) M2). Response includes the `discovery_id` **and** the one-time-shown owner token. |
| `PATCH /api/v1/events/{slug}/discovery/{discovery_id}` | Update this profile's exposed fields — requires the owner token as a bearer credential. |
| `DELETE /api/v1/events/{slug}/discovery/{discovery_id}` | "Leave attendee discovery" ([3.4](03-functional-requirements/04-matching.md) M6) — same owner-token requirement. |
| `GET /api/v1/events/{slug}/quests` | Quest items for the event — default + event-specific merged ([3.6](03-functional-requirements/06-quest.md)) |
| `GET /api/v1/events/{slug}/offers` | Active offers for that event |
| `POST /api/v1/events/{slug}/offers/{offer}/leads` *(added post-launch)* | Submit a Name/Email/Mobile lead before opening a deal with `capture_leads` enabled — one-shot public write, no owner-token lifecycle. 422 if the deal doesn't have lead capture on. See [3.7](03-functional-requirements/07-deals.md). |
| `POST /api/v1/push/subscribe` | Register a Web Push subscription for a bookmarked session |
| `POST /api/v1/events/{slug}/bookmarks` / `DELETE ...` | Bookmark a session server-side (only needed for reminder scheduling) |
| `GET /api/v1/cache-version` *(added)* | `{"version": "<ms timestamp>"}` — bumped by the admin's *Purge cache & refresh data* ([9](09-admin-panel.md)); `Cache-Control: no-store`. An open app polls it when returning to the foreground and reloads if it changed. `"0"` until the first purge. |
| `GET /api/v1/health` | Health check |

Existing V1 endpoints (`event`, `media`, `sponsors`, `agenda`) become event-scoped: `/api/v1/events/{slug}/media`, etc.

> **Current implementation note:** the mutating routes (discovery POST/PATCH/DELETE, deal leads, **push subscribe**) share the `api-writes` limiter (10/min/IP); everything under `/api/v1` also counts against `api-general` (60/min/IP) — see [8.1](08-security-privacy.md#81-rate-limiting). `push/subscribe` requires an `https` endpoint that isn't a private/internal address. `GET …/roster` returns only `http(s)` links and avatar URLs. Every response carries the security headers in [8.2a](08-security-privacy.md#82a-hardening-pass-full-review).

> **Current implementation note — shared lists are built once and validated (Sept 2026):** `roster`, `discovery` and `data-version` are the same for every visitor, so `App\Support\ConditionalJson` serves them from a short-lived built copy (roster page 60 s, discovery list 20 s, cleared at once when a profile joins/edits/leaves; `data-version` is already cached 120 s in `DataVersion`) with a weak **ETag** and `Cache-Control: public, max-age=0, s-maxage=30|15|20, stale-while-revalidate=2×`. A phone holding the current version gets an empty **304**; browsers always revalidate (`max-age=0`). `s-maxage` only matters once a CDN cache rule covers `/api/v1/events/*` (see [12](12-deployment.md)) — until then the headers are harmless. **Never shared:** `cache-version` stays `no-store` (it is the admin purge signal), and a person's own waves/messages (owner-token calls) get no `public`/`s-maxage`. The client polls `data-version` without the old `?t=` cache-buster (which defeated every cache) and with `cache: 'no-cache'`. Tests: `tests/Feature/ApiCachingTest.php`.
