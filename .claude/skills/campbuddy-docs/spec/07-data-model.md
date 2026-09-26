# 7. Data model

[← Back to index](../SKILL.md) · Previous: [6. Technology stack](06-technology-stack.md) · Next: [8. Security & privacy →](08-security-privacy.md)

Builds on V1's 8 tables, adding:

| Table | Purpose |
|---|---|
| `events` | One row per WordCamp: slug, display name, source site URL, lifecycle `status` (`draft` / `approved` / `active` / `archived`), and a separate `is_visible` boolean (**default `true`**, [3.2](03-functional-requirements/02-branding.md) BR6) controlling public display independent of `status`. ~~branding fields (colors, `logo_path`, `favicon_path`)~~ — `primary_color`/`accent_color` were later dropped from the schema entirely (see [3.2](03-functional-requirements/02-branding.md)); `logo_path`/`favicon_path` remain, re-hosted local files, never hotlinked. |
| `attendee_roster` | Ingested from each event's public Attendees page: name, gravatar URL, links, `event_id`, `content_hash` (for change detection), suppression flag ([3.3](03-functional-requirements/03-roster-ingestion.md) IN5). |
| `discovery_profiles` | The **only** server-side table for matching ([3.4](03-functional-requirements/04-matching.md), [8.3](08-security-privacy.md#83-matching-data--the-local-by-default-shared-only-by-choice-architecture)) — one row per attendee who explicitly took the "Join attendee discovery" action (M2): a random, non-guessable **public** `discovery_id`, a hashed **`owner_token_hash`** ([8.6](08-security-privacy.md#86-discovery-api-ownership--a-real-gap-caught-and-fixed-here) — the raw token is never stored), `event_id`, the chosen-to-expose fields as JSON, `expires_at` (defaults to the event's end). The attendee's full onboarding profile and "met" history **never reach this table or any other server table** — both stay local-only on the device (IndexedDB, [3.13](03-functional-requirements/13-data-controls.md)). |
| `session_bookmarks` | Locally-synced bookmark list per device profile, `event_id`-scoped. |
| `push_subscriptions` | Web Push subscription endpoints, tied to bookmarked sessions, pruned on unsubscribe/expiry. |
| `quests` | Per-event admin-editable quest text ([3.6](03-functional-requirements/06-quest.md) C1, C3) — `event_id` nullable for default/cross-event quests. |
| `event_managers` *(added Sept 2026)* | Accounts an admin creates for people who may edit a few sections of specific events: `name`, unique lower-cased `email`, `phone`, hashed `password`, `is_active`, `last_login_at`, remember token. **Not** in `users` — see [9](09-admin-panel.md). |
| `event_event_manager` *(pivot)* | Which events a manager may edit: `event_id` + `event_manager_id` (composite primary key), both `cascadeOnDelete` — deleting an event or a manager only removes the link. |
| `event_manager_changes` *(added Sept 2026)* | What event managers changed, for the admin ([9](09-admin-panel.md)): `event_manager_id` (nullable, `nullOnDelete`) + a copy of `manager_name`, `event_id` (`cascadeOnDelete`), `section` (details / information / quests), `action`, a one-line `summary`, `created_at`; indexed by event, by manager and by time. Kept 90 days. |
| `offer_leads` *(added post-launch)* | Name/Email/Mobile submissions captured before an attendee opens a deal with `capture_leads` enabled — `event_id` + `offer_id`, no owner-token/edit lifecycle (a one-shot public write, not user-editable after submission). See [3.7](03-functional-requirements/07-deals.md). |

`offers` gains an `event_id` foreign key (was implicitly single-event in V1) and, post-launch, a `capture_leads` boolean (see [3.7](03-functional-requirements/07-deals.md)). `fetch_log` gains a `job_type` column to distinguish ingestion jobs from the existing event/media refresh, and (post-launch) a `lifecycle` job type for `EvaluateEventLifecycleJob` (see [5.2](05-system-architecture.md#52-ingestion-flow)).

Sessions/speakers/sponsors/organizers continue to live in a TTL-cached JSON blob per event (`cache_store`, same single-flight-lock pattern) — only the *source* of that data changes, from the `wpsimplified.in` proxy to a direct `wp-json` REST fetch per event ([finding 0.1](00-findings.md), [finding 0.5](00-findings.md)). No table needed for this data; `events.id` is the cache key.
