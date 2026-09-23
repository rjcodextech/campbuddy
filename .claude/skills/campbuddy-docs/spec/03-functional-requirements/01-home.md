# 3.1 Home

[← Index](00-index.md) · Next: [3.2 Event branding →](02-branding.md)

Home is the most important screen in CampBuddy and must not become a generic card dashboard — its one job is answering "what should I do right now," and its content changes based on the selected event, current date/time, saved sessions, completed quests, onboarding preferences, and Contributor Day status.

| ID | Requirement |
|---|---|
| H1 | **Happening Now**: what's currently underway — registration open, keynote in progress, lunch started, Contributor Day tables open, networking hour — derived from the event's schedule data ([3.3](03-roster-ingestion.md), [3.8](08-my-day.md)) compared against current time, not manually authored per-event unless an organizer overrides it. |
| H2 | **Up Next**: upcoming activities, prioritized in this order — (1) the attendee's own saved sessions ([3.8](08-my-day.md)), (2) important event-wide activities (keynote, lunch, closing), (3) sessions matching the attendee's stated interests from onboarding ([3.11](11-onboarding.md)). |
| H3 | **Suggested Action**: an occasional, single, simple nudge — "visit the sponsor area," "introduce yourself to someone from another city," "add your LinkedIn to your Camp Card," "bookmark your afternoon sessions." Rotates from a mix of default CampBuddy suggestions and unfinished Quests ([3.6](06-quest.md)). Tone must read as helpful, not nagging — one suggestion at a time, not a list. |
| H4 | **Progress**: a compact, non-gamified indicator of Quest completion and saved-session count — small enough that WordCamp itself, not the app, stays the main event ([1.0 principle 1](../01-project-overview.md#10-product-philosophy)). |
| H5 | All copy on this screen follows the "guidance over information" translation pattern ([1.0](../01-project-overview.md#10-product-philosophy)) — a raw schedule fact is never shown without also saying what the attendee might do about it. |
| H6 | Home renders from already-downloaded event data ([5.1](../05-system-architecture.md#51-request-flow-public-pages--updated-for-server-rendering), [4.4 offline-first](../04-non-functional-requirements.md#44-offline-first)) — no blocking network call between opening the app and seeing a populated Home screen. |

> **Current implementation note:** Home also carries a hero section (event logo/name/dates) and a "Find people who match your interests" entry point above Happening Now — see [What CampBuddy does](../../about/02-what-it-does.md) and [3.4 Matching](04-matching.md).
