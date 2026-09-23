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

> **Current implementation note (redesign):** the admin panel was rebuilt on one small design system instead of per-page styling, so every page looks and behaves the same:
>
> - **Tokens & classes** — brand colours live in `tailwind.config.js` (`ink`, `paper`, `line`, `maroon`, `navy`, `teal`, plus a distinct `danger` red so *Delete* never looks like the maroon *Save*); shared form/button/table classes are `.cb-*` in `resources/css/app.css`. Views must not hard-code stock Tailwind palette colours (a test enforces it).
> - **Components** (`resources/views/components/`) — `<x-button>` (variants: primary · secondary · danger · danger-outline · link), `<x-action-form>` (a one-button POST/PUT/DELETE form with optional confirm), `<x-card>`, `<x-alert>`, `<x-badge>` / `<x-event-status>`, `<x-table>` + `<x-table.empty>`, `<x-stat>`, `<x-icon>`, and `<x-form.input|textarea|select|checkbox|file|password>`, which handle labels, hints, `old()`, inline errors and `aria-*` uniformly. `<x-admin.event-nav>` is the tab strip (Details · Quests & checklist · Deals · Roster · Deal leads) shared by every event sub-page.
> - **Layout** — responsive: off-canvas drawer navigation on phones, breadcrumbs + title + actions header, skip link, flash messages and a validation-error summary rendered centrally (so pages can't forget them). `noindex` on all admin pages.
> - **Sign-in** — branded two-panel screen with show/hide password, a "signing in…" state that blocks double submits, correct autofocus after a failed attempt, and matching forgot/reset/confirm screens. Auth *mechanics* (throttling, remember-me, no public registration) are unchanged.
> - **Dashboard** — now real: event counts by status, drafts awaiting approval, roster and lead totals, recently updated events, and recent failed ingestions.
> - **Behaviour fixes made along the way** — the quest/offer forms now show validation errors (previously they failed silently); *Active* / *Require contact info* can actually be switched **off** (unticked boxes used to send nothing, so the change was ignored); new quests/deals are appended after existing ones, and both have an *Order* field.
> - **Default checklist** — the Quest editor opens pre-filled with the 9-item checklist every event starts with ([3.6](03-functional-requirements/06-quest.md)); admins edit those rows like any other quest.
>
> **Current implementation note (Event information):** the Event information card opens with what the fetch found, with a **Fetch latest** button and a last-fetched line (status + "n of 9 fields found"), and a hint under every field showing whether it's *auto-filled* or *edited by you*. The full behaviour — sources, the never-overwrite-admin-edits rule, and the schedule — is in [3.12](03-functional-requirements/12-sponsors-event-info.md).