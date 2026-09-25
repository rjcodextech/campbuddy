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
