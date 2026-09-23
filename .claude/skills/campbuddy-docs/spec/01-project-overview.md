# 1. Project overview

[← Back to index](../SKILL.md) · Previous: [0. Findings](00-findings.md) · Next: [2. Scope →](02-scope.md)

CampBuddy V2 is a mobile-first, local-first companion app for WordCamp attendees, rebuilt to support **any number of WordCamps**, not just one. It launched with WordCamp Rajasthan 2026 as its first live event.

CampBuddy is **not a conference schedule app**. A schedule is data; CampBuddy's job is guidance — taking everything happening around a WordCamp and turning it into a simple answer to the one question that actually matters to someone standing in a venue: **"What should I do now?"** Every screen should be judged against that, not against "does it display the data we have."

## 1.0 Product philosophy

1. **Guidance over information.** Don't just display a fact — translate it into what the attendee should do about it. Not "Contributor Day — 9:00 AM" but *"Contributor Day starts at 9:00 AM. First time contributing? Head to the contributor tables and tell a volunteer what you're interested in — they'll help you find a team."* Not "Building Blocks at Scale — Hall A" but *"Your saved session starts in 15 minutes in Hall A — you may want to start heading there."* This principle governs [Home](03-functional-requirements/01-home.md) above all, but applies everywhere copy is written.
2. **Local by default, shared only by choice.** Personal attendee data lives on the device unless a feature explicitly needs to share it, and even then, sharing is opt-in, scoped, explained, and revocable. See [8.3 Matching data](08-security-privacy.md#83-matching-data--the-local-by-default-shared-only-by-choice-architecture) for how this applies specifically to attendee matching.
3. **The guiding test for any feature or UX decision:** *Would this make a person attending their first WordCamp feel more confident about what to do next?* If yes, it belongs in CampBuddy. If it only adds information, complexity, or another dashboard card without making the attendee's next move easier, reconsider it — including for features already speced in [3. Functional requirements](03-functional-requirements/00-index.md).

## 1.1 Primary audience

- **First-time attendees** (most important audience) — don't know what Contributor Day is, whether they can approach speakers, what sponsor booths are for, how to contribute to WordPress, who to talk to, or how to start a conversation. CampBuddy exists primarily to reduce this uncertainty.
- **Returning attendees** — still get value from session planning, reminders, networking, Contributor Day recommendations, sponsor offers, and their Camp Card, even without the first-timer hand-holding.
- **Organizers** — configure CampBuddy for their own WordCamp (branding, schedule, speakers, sponsors, quests, Contributor Day info) without anyone touching application code (see [9. Admin panel](09-admin-panel.md)).

## 1.2 Navigation & information architecture

Six primary tabs, designed to work one-handed on a phone. No secondary nav clutter — settings/export/data controls are reached contextually (from Camp Card and Explore), not as a seventh tab:

| Tab | Purpose | Spec |
|---|---|---|
| **Home** | "What's happening now, what's next, what should I do" | [3.1](03-functional-requirements/01-home.md) |
| **My Day** | Personal + full schedule, session bookmarking, reminders | [3.8](03-functional-requirements/08-my-day.md) (schedule/sessions), [3.5](03-functional-requirements/05-notifications.md) (reminders) |
| **Quest** | Small, achievable social/exploration prompts | [3.6](03-functional-requirements/06-quest.md) |
| **Contribute** | Contributor Day guidance, team matching | [3.9](03-functional-requirements/09-contribute.md) |
| **Explore** | People (matching, [3.4](03-functional-requirements/04-matching.md)) · Sponsors & Deals ([3.7](03-functional-requirements/07-deals.md), [3.12](03-functional-requirements/12-sponsors-event-info.md)) · Event Info ([3.12](03-functional-requirements/12-sponsors-event-info.md)) | — |
| **Camp Card** | Portable digital identity + QR | [3.10](03-functional-requirements/10-camp-card.md) |
| *(not a tab)* Onboarding | 4-step first-run flow, under 2 minutes | [3.11](03-functional-requirements/11-onboarding.md) |

This replaces V1's separate "Offers" and "More" top-level tabs — both now live as sub-sections inside **Explore**, and "Camp Quest"/"Contributor Day" are renamed to the shorter **Quest**/**Contribute** to fit a one-handed, five/six-item tab bar.

## 1.3 What's new vs. V1

| Area | V1 | V2 |
|---|---|---|
| Event scope | Hardcoded to one event | Multi-event: discover, ingest, and serve N WordCamps |
| App structure | Static PWA + separate Slim backend, joined by a root `.htaccess` rewrite into `/backend` | **One Laravel app, no split.** No more `/backend` mount path or rewrite trick — Laravel's `public/` is the docroot; everything (attendee pages, admin, API, cron) lives in one codebase, one deploy. |
| Frontend rendering | 100% client-side vanilla JS rendering onto a static `index.html` shell | **Blade views**, server-rendered per route (`/event/{slug}`, `/event/{slug}/my-day`, etc.) — a deliberate rewrite. |
| Backend framework | Slim Framework | **Laravel** (deliberate rewrite, per product decision) |
| Attendee data | None | Roster ingestion from each event's public Attendees page ([0.3](00-findings.md), [0.4](00-findings.md)) |
| Matching | None | Interest-based matching **among CampBuddy users only** ([3.4](03-functional-requirements/04-matching.md)) |
| Branding | Static, single theme | Per-event logo/name driven by event data (color theming was later removed in favor of one consistent default palette — see [3.2](03-functional-requirements/02-branding.md)) |
| Notifications | None | Best-effort Web Push for bookmarked sessions (Android solid, iOS 16.4+ with PWA installed; in-app banner fallback everywhere) |
| Quest (was: onboarding checklist) | Generic interests picker | A full nav tab: default + event-specific quests ([3.6](03-functional-requirements/06-quest.md)), no points/leaderboards |
| Offers | Single-event list | Event-scoped, expire when the event ends unless resubmitted |
| Hosting | Shared cPanel only | Shared cPanel **behind a mandatory CDN** ([5.4](05-system-architecture.md#54-cdn--now-mandatory-not-optional)) |

## 1.4 Objectives

1. Let anyone planning to attend *any* upcoming WordCamp get the same guided experience CampBuddy gave WCR attendees in V1.
2. Turn a static attendee list into a way to actually find people worth meeting — without pretending to know things about people who never used the app.
3. Make the app feel like the event's own official companion while staying one shared codebase.
4. Give organizers a tool they can hand to attendees with their event's name on it, day one.
5. Keep the zero-account, local-first, minimal-data-collection posture from V1 — matching is the one place V2 asks a user to voluntarily share more (their own interests), and that stays opt-in and editable/deletable.
6. Survive real WordCamp-day traffic without falling over or costing you a scramble — see [5.4 CDN](05-system-architecture.md#54-cdn--now-mandatory-not-optional) for how that's now actually true, not just asserted.
