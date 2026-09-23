# 3.13 Data controls (Export / Clear)

[← Index](00-index.md) · Previous: [3.12 Sponsors & Event Information](12-sponsors-event-info.md) · Back to: [Spec index](../../SKILL.md)

Reached contextually — from Camp Card or Explore's Event Information — not as a dedicated nav tab ([1.2](../01-project-overview.md#12-navigation--information-architecture)).

| ID | Requirement |
|---|---|
| DP1 | **Export My CampBuddy Data**: a single action that bundles all of this device's local data (onboarding answers, Quest progress, My Day bookmarks, Camp Card, met-history) into a downloadable file the attendee can keep. |
| DP2 | **Clear My CampBuddy Data**: wipes all of the above from the device. Before it runs, the app clearly states what will be removed — this is a destructive, irreversible local action and must never fire without that explanation and an explicit confirmation step. |
| DP3 | Clearing local data does **not** automatically call [3.4](04-matching.md) M6 ("Leave attendee discovery") — if the attendee has an active discovery profile, clearing warns about it separately and offers to leave discovery first, since that's a server-side action distinct from wiping the device. |
| DP4 | Local persistence uses IndexedDB for anything beyond trivial key-value data (bookmarks, Camp Card, Quest state, onboarding answers) — not `sessionStorage`, and not `localStorage` alone once data complexity goes past simple flags — so state survives page reloads, browser restarts, and temporary network loss ([4.4](../04-non-functional-requirements.md#44-offline-first)). |
