# 3.11 Onboarding

[← Index](00-index.md) · Previous: [3.10 Camp Card](10-camp-card.md) · Next: [3.12 Sponsors & Event Information →](12-sponsors-event-info.md)

No traditional account/signup, ever ([2.3](../02-scope.md#23-explicitly-out-of-scope)). Four steps, skippable where noted, aiming for **well under two minutes** end to end.

| ID | Requirement |
|---|---|
| OB1 | **Welcome** — one or two sentences explaining what CampBuddy is. No slideshow, no feature tour. |
| OB2 | **Select WordCamp** — choose the event being attended. |
| OB3 | **Tell CampBuddy why you're here** — a small number of skippable questions: first WordCamp or returning, role, interests, why attending, what they want to learn, who they'd like to meet, whether attending Contributor Day. This is the data that later powers Home's Up Next ([3.1](01-home.md) H2) and Contribute's suggestions ([3.9](09-contribute.md) CD1) — but per [8.3](../08-security-privacy.md#83-matching-data--the-local-by-default-shared-only-by-choice-architecture), none of it becomes shareable without the separate, explicit matching opt-in. |
| OB4 | **Ready** — goes straight to Home. No forced tutorial overlay. |

> **Current implementation note:** the actual step order is OB1 → OB3 → OB2 → OB4 — "tell us about yourself" now happens on the WordCamp-picker page itself, **before** an event is selected, since the profile it collects isn't event-specific anyway (it's a single device-wide profile, not one per event). This also means OB1/OB3's copy is generic rather than referencing a specific event by name.

> **Current implementation note (why the section can be "missing"):** the *Tell us a little about you* card on the picker page (`#onboarding-welcome`) is rendered `hidden` by the server and revealed by `onboarding.js` **only on a device that hasn't completed it yet** — both **Skip** and **Continue** save `onboarding.completedAt` to the device's IndexedDB `kv` store, after which the card never shows again on that browser. So on a browser where it has been tapped once (including during development) it's absent by design, not broken. To see it again: **Explore → Event Info → Clear my data** (or the same panel at the bottom of Camp Card), which empties every IndexedDB store, then reload — or delete the site's IndexedDB in browser DevTools → Application. A fresh browser profile always shows it.