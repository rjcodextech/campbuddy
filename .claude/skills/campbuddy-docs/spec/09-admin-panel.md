# 9. Admin panel

[← Back to index](../SKILL.md) · Previous: [8. Security & privacy](08-security-privacy.md) · Next: [10. API specification →](10-api-specification.md)

Full CRUD on events, not just approve/view — an admin is never limited to what an automated job discovered:

| Section | Capability |
|---|---|
| Events — **Create** | Add a new event manually (slug + source site URL), independent of central discovery ([5.3](05-system-architecture.md#53-central-event-discovery)). |
| Events — **Read** | List all events with status (draft/approved/active/archived) and visibility (enabled/disabled, [3.2](03-functional-requirements/02-branding.md) BR6), filterable/searchable. |
| Events — **Update** | Edit slug, display name, source site URL, lifecycle status, logo/favicon upload (overrides auto-fetch per [3.2](03-functional-requirements/02-branding.md) BR5), and the **frontend visibility toggle** (default on, BR6). *(Color fields described in earlier drafts were removed — see [3.2](03-functional-requirements/02-branding.md).)* |
| Events — **Delete** | Hard delete is allowed only for `draft` events with no ingested data yet. An `approved`/`active`/`archived` event can only be **archived**, not hard-deleted; archiving already purges roster data per [8.4](08-security-privacy.md#84-ingested-roster-data--explicit-policy). |
| Ingestion status | Per-event last roster-fetch time/status, last speaker/sponsor-fetch time/status, manual "Refresh now" per event, manual "Re-fetch branding assets" separate from the daily data refresh. |
| Roster suppression | View/search the ingested roster for an event, manually suppress an entry ([8.4](08-security-privacy.md#84-ingested-roster-data--explicit-policy) takedown path). |
| Quest editor | Full CRUD on event-specific quests per event ([3.6](03-functional-requirements/06-quest.md) C1, C3) — add, edit, reorder, delete. Default/cross-event quests are seeded, not per-event admin content. |
| Offers | Full CRUD, filtered/scoped by event, plus (post-launch) a per-deal `capture_leads` toggle — see [3.7](03-functional-requirements/07-deals.md). |
| Deal Leads *(added post-launch)* | Per-event, filterable by deal and date range, with a CSV export — captured Name/Email/Mobile submissions from deals with lead capture enabled. See [3.7](03-functional-requirements/07-deals.md). |
