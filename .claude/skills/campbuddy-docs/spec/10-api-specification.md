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
| `GET /api/v1/health` | Health check |

Existing V1 endpoints (`event`, `media`, `sponsors`, `agenda`) become event-scoped: `/api/v1/events/{slug}/media`, etc.
