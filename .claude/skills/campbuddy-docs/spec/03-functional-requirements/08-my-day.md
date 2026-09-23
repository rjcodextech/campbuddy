# 3.8 My Day & session details

[← Index](00-index.md) · Previous: [3.7 Deals](07-deals.md) · Next: [3.9 Contribute →](09-contribute.md)

| ID | Requirement |
|---|---|
| MD1 | Two distinct views: **Full Schedule** (every session at the event) and **My Schedule** (only what the attendee bookmarked) — clearly distinguished, not blended into one filtered list. |
| MD2 | Full Schedule is browsable by track/room, searchable, and shows each session's title, speaker, track, room, and start/end time at a glance. |
| MD3 | A session detail view shows: title, speaker (name + photo, from [finding 0.1](../00-findings.md)'s REST ingestion), track, room, start/end time, description, session type, speaker bio, any useful links (slides/video), and a save/remove-from-My-Day action. |
| MD4 | Bookmarking two overlapping sessions is **allowed** — the app warns about the conflict but never blocks the save. The attendee decides, CampBuddy just makes the conflict visible before it becomes a surprise. |
| MD5 | My Schedule remains fully usable offline once the event's schedule data has been downloaded ([4.4](../04-non-functional-requirements.md#44-offline-first)) — this is one of the offline-first floor requirements, not a nice-to-have. |

> **Current implementation notes:**
> - Sessions are grouped into one card per calendar day (day headings only shown when the event spans more than one day) — MD1's schedule-separation carried further than originally speced.
> - Filters beyond track: a day selector (multi-day events only) and a session-type filter, alongside the original search.
> - MD3's session detail is **not** a popup/dialog — it's an inline accordion that expands directly under the tapped session (only one open at a time), consistent with a broader "no popups in My Day" decision. Every other dialog in the app (Contribute's team detail, the in-app browser, deal lead-capture, iOS install steps) is unaffected by this — it's scoped to My Day specifically.
