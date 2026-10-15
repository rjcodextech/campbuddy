# 23. Data retention — nothing of an attendee's disappears while the event is on

[← Back to index](../SKILL.md) · Previous: [22. Analytics (GA4)](22-analytics.md)

**The rule (decided with the product owner, Sept 2026):** from the moment an event starts until it has ended — and for a few days after — CampBuddy's own code never deletes, hides or expires anything an attendee entered or chose, on the server or on their phone. The only ways it goes are the attendee's own doing ("Delete my data", clearing the browser's storage) or the browser's own storage eviction (see 23.3).

## 23.1 The event's real last day

Scraped events often arrive with **no end date** (in the reference data 20 of 21 events had none) or a wrong one — a 3-day event as "starts 1 Oct" with sessions on the 3rd. Anything that ends something must therefore use `EventTime::lastDay($event)`: the **latest of the end date, the start date and the day the last scheduled session ends** (in the venue's zone). A stray session date more than `EventTime::MAX_EVENT_SPAN_DAYS` (7) after the start is ignored, so a typo year can't keep an event live. The value is derived from the stored schedule and cached for 15 minutes; `DataVersion::forget()` (called on every event/data write) clears it at once.

`EventTime::retentionEnd($event)` = end of that last day **+ `RETENTION_DAYS` (30 since 12 Oct 2026; 7 from 6 Oct, 3 before)**. A month after a WordCamp people can still find the people they met (discovery profiles, shared Camp Cards), move their CampBuddy to a new phone (Explore → People), bring their profile and people met to their next WordCamp (only offered when the phone holds some) and get the day 3 / 7 / 15 pushes. The event's data is fetched from its site on a schedule only for `FETCH_DAYS` (7) after its last day (`EventTime::refreshing`), and the event stays listed as *Completed* for `LISTED_DAYS` (90). The week exists for the after-event flow: the picker lists the event as **Completed** (teal card with a "Save your WordCamp Data" button that makes the PDF right on the picker), its pages still open, and from `THANK_YOU_AFTER_DAYS` (3) the thank-you card and push ask for a rating — see [9](09-admin-panel.md) *Feedback*. If the event's time zone isn't known yet it is measured in the latest zone on Earth (UTC−12), so a guess can never cut an event short. An event with no dates at all is always retained.

## 23.2 Server side — what uses it

| What | Rule |
|---|---|
| **Archiving** (`EvaluateEventLifecycleJob`, daily) | An `active`/`approved` event is archived only when `EventTime::retained()` is false — i.e. after its retention end. (Archived events answer 404, so archiving early would take the app away mid-event.) |
| **Discovery profiles** | `expires_at` is stamped with `retentionEnd` at join. While the event is retained, `DiscoveryProfile::scopeAlive()` ignores the stored stamp altogether — profiles made before the schedule was known carry an earlier one. Used by the discovery list, waves/messages, and the roster's "Open to meet" badge. |
| **Shared Camp Cards** | Same as discovery profiles: `expires_at` = `retentionEnd` when shared, `SharedCampCard::scopeAlive()` ignores it while the event is retained. Removed only by the owner (switch off, "Clear my data"), an admin (*Remove card*), or with the list entry itself. |
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

Also kept the same way: the **saved attendee-list photos** (`campbuddy-avatars`, at most 3000, replaced not deleted) — [4.4](04-non-functional-requirements.md#44-offline-first) note 6.

**Device transfers** (`device_transfers`): a request to move someone's data to another device holds only public keys, statuses and — for at most 10 minutes, until the new device fetches it — the end-to-end encrypted payload the server can't read. Finished requests keep no payload and are deleted after 24 h; unanswered ones after 10 min (pruned every 5 min, and with the discovery profile).
## 23.4 Bring it to the next WordCamp (12 Oct 2026, owner confirmed twice)

An event's Home (while the event isn't over) carries `pastEvents` in `home-data` (`App\Support\PastEvents::forHome`: active, visible events whose last day was 1–`RETENTION_DAYS` (30) days ago, newest first, at most 8). `bring-data.js` checks what this phone holds for them — the *Meet people* answers (`kv discovery:<id>.fields`) and the people saved there (`meetings`) — and offers the newest one once ("Welcome back · Bring your Meet people profile and the N people you met from X?", *Bring it here* / *Not now*, remembered in `kv bring:<eventId>`). **Bring it here**: the answers (interests, role, who to meet, WordPress.org, a typed name — never the other event's attendee-list entry) go to `kv discoveryCarry:<eventId>` and fill the join form first (`people.js` `showJoinPrompt`); people met there who are on this event's attendee list too (same Gravatar hash, else the same full name; one-word names never) are added to *People to meet* (`r:<rosterId>`, note "You met at X." + the old note). Nothing is sent to the server; the old event's data is left as it is. GA: `bring_data_view`, `bring_data` (result done | dismissed). Tests: `BringDataTest`, `tests/js/bring-data.test.mjs`.

## 23.4 Real deletion (added 15 Oct 2026, DPDP Act s.8(7))
"Never deleted by our code" (above) holds **during the event and its 30-day retention window**. After that, what attendees shared is erased for good so it does not sit on the server forever.

- **Server:** `App\Support\PersonalDataErasure` (nightly, `campbuddy:erase-expired-data`, `--dry-run` only counts). Due when `EventTime::retentionEnd($event)` + `ERASE_AFTER_DAYS` (60) has passed — about 90 days after the event, the same time it leaves the picker. Emptied per event: discovery messages, waves, device transfers, reports, profiles; shared Camp Cards; push subscriptions and native push tokens; server-side session bookmarks; deal-form leads. Not touched: the event, the public attendee list (it has its own takedown), ratings. An event with no dates is never erased. `tests/Feature/PersonalDataErasureTest.php`.
- **Phone (native app):** when an event is over and the phone holds something of the person's for it, a sheet asks **Keep my data** or **Delete it after 90 days** (`AfterEventChoice`). The answer is stored per event (`UserData.retention`); "Delete" stores the day (last day + 90). `DataSweep` runs when the app opens and removes only that event's saved sessions, quests, people to meet and discovery/share codes (`UserData.eraseEvent`); the Camp Card and onboarding answers belong to no single event and stay. The answer can be changed any time in Your data. The web app (PWA) keeps its "our code never deletes on the phone" rule for now.
