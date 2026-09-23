# 3.3 Attendee roster ingestion

[← Index](00-index.md) · Previous: [3.2 Event branding](02-branding.md) · Next: [3.4 Interest-based matching →](04-matching.md)

| ID | Requirement |
|---|---|
| IN1 | A scheduled job parses one event's public Attendees page HTML and upserts (name, gravatar URL, optional links) keyed by a stable hash of name+links (no upstream ID exists — see risk in [8.4](../08-security-privacy.md#84-ingested-roster-data--explicit-policy)). |
| IN2 | Ingestion runs **once daily per active event**, not more — this is a scrape of someone else's site, not an API with a documented rate limit. |
| IN3 | Ingestion for multiple events is batched with a delay between events (sequential with a cooldown), never parallel-hammering multiple WordCamp sites at once. |
| IN4 | If the page structure changes and parsing fails, the job logs and alerts (via the admin dashboard's fetch-log, same pattern as V1's `fetch_log`) rather than silently ingesting garbage. |
| IN5 | An attendee can request removal from CampBuddy's roster ([8.4](../08-security-privacy.md#84-ingested-roster-data--explicit-policy)) — the ingestion job must respect a local suppression list on every re-run. |
