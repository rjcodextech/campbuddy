# 23. Data retention — nothing of an attendee's disappears while the event is on

[← Back to index](../SKILL.md) · Previous: [22. Analytics (GA4)](22-analytics.md)

**The rule (decided with the product owner, Sept 2026):** from the moment an event starts until it has ended — and for a few days after — CampBuddy's own code never deletes, hides or expires anything an attendee entered or chose, on the server or on their phone. The only ways it goes are the attendee's own doing ("Delete my data", clearing the browser's storage) or the browser's own storage eviction (see 23.3).

## 23.1 The event's real last day

Scraped events often arrive with **no end date** (in the reference data 20 of 21 events had none) or a wrong one — a 3-day event as "starts 1 Oct" with sessions on the 3rd. Anything that ends something must therefore use `EventTime::lastDay($event)`: the **latest of the end date, the start date and the day the last scheduled session ends** (in the venue's zone). A stray session date more than `EventTime::MAX_EVENT_SPAN_DAYS` (7) after the start is ignored, so a typo year can't keep an event live. The value is derived from the stored schedule and cached for 15 minutes; `DataVersion::forget()` (called on every event/data write) clears it at once.

`EventTime::retentionEnd($event)` = end of that last day **+ `RETENTION_DAYS` (3)**. If the event's time zone isn't known yet it is measured in the latest zone on Earth (UTC−12), so a guess can never cut an event short. An event with no dates at all is always retained.

## 23.2 Server side — what uses it

| What | Rule |
|---|---|
| **Archiving** (`EvaluateEventLifecycleJob`, daily) | An `active`/`approved` event is archived only when `EventTime::retained()` is false — i.e. after its retention end. (Archived events answer 404, so archiving early would take the app away mid-event.) |
| **Discovery profiles** | `expires_at` is stamped with `retentionEnd` at join. While the event is retained, `DiscoveryProfile::scopeAlive()` ignores the stored stamp altogether — profiles made before the schedule was known carry an earlier one. Used by the discovery list, waves/messages, and the roster's "Open to meet" badge. |
| **Discovery chat** (`ChatWindow`) | A session on a later day extends the chat's days beyond a missing/short end date (within the 7-day cap). |
| **WordCamp picker** (`/`) | Lists an event until its real last day has ended at the venue (`isOver` is schedule-aware); the SQL pre-filter has 3 days of slack for this. |
| **Page facts** | `data-event-end` and `data-event-phase` on every event page use the real last day, so the app's own "is it an event day?" logic (plan reminder, meeting-date limits, update polling) is right too. |

Deliberately unchanged: the nightly roster prune removes people who left the source Attendees page — that is a privacy opt-out and wins over retention. Server rows are never bulk-deleted by a job; "expired" only means hidden after the retention window.

Tests: `tests/Feature/RetentionTest.php` (the "Sylhet" case: no end date, sessions on day 3) and the retention cases in `TimeAccuracyTest`.

## 23.3 On the phone

| What | Rule |
|---|---|
| **The attendee's own data** (IndexedDB: saved sessions, quest progress, Camp Card, meetings and notes, discovery token; localStorage flags) | Our code never deletes it. Only "Delete my data" (`db.js clearAll`, user-initiated) or the browser's own site-data clearing does. IndexedDB schema changes must be **additive only** (never drop or rewrite a store). |
| **Saved pages and files** (Cache Storage, the service worker's `campbuddy-v3`) | Never deleted by us — not on data changes, not on the admin purge, not when a new worker version activates. They are only **replaced** by a fresh copy that arrived whole (`saved-copies.js`: a plain 200 only; on any failure the old copy stays). The one deliberate removal is a signed-in `/admin` page an older worker may have stored. There is no automatic cleanup of finished events: a few hundred KB per event is cheaper than ever taking an offline page away. |
| **The saved attendee list** | Kept until replaced or cleared by "Delete my data". A person who asked to be removed disappears from a phone's copy the next time that phone is online (the list refreshes on every Explore visit). |
| **Queued requests** (`outbox:<slug>`) | Kept until sent; dropped only when they can no longer mean anything (session left the day, notifications revoked, permanent refusal, 8 failed tries). Bounded to 100 per event. |

**What can still remove it — outside our code:** the person clearing site data / uninstalling, or the browser evicting storage when the phone runs low on space. Mitigations: an installed app is exempt from Safari's 7-day script-storage cap; `navigator.storage.persist()` is requested once (`offline-warmup.js`) and installed apps normally get a yes. If it *is* lost the app behaves as before this feature: pages are saved again as they are opened (and the warm-up runs again).

Tests: `tests/js/` (`saved-copies`, `data-freshness`, `cache-version`, `sw`, `offline-warmup`) assert that no `caches.delete` / entry delete happens on any refresh path, and `tests/browser/offline.e2e.mjs` watches the real Cache Storage during a data change and a failing server.
