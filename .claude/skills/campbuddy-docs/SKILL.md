---
name: campbuddy-docs
description: CampBuddy V2 product and technical documentation — what the app is, who it's for, and the full spec (functional/non-functional requirements, architecture, data model, security, admin panel, API, deployment). Use when you need context on why a feature works the way it does, what a requirement ID (e.g. "H1", "BR4", "M2") means, or where something is documented before making product or architectural decisions in this repo.
---

# CampBuddy V2 documentation

This is CampBuddy's full documentation, split into small, focused files so you only load what's relevant instead of one giant document. It replaces the old root-level `ABOUT.md` and `README.md` (both now just short pointers back here — see the note at the bottom).

**Read `about/` for the product pitch** (what CampBuddy is, in plain language). **Read `spec/` for the technical spec** (what each feature must do, how the system is built). Files cross-link each other and use `§N`/`§N.N` numbering that matches the original spec's section IDs, so an old reference like "§3.2 BR4" still resolves — look up `3.2` in the table below.

Several `spec/` files carry a **"Current implementation note"** callout where the app has moved on from what was originally speced (e.g. per-event color branding was removed after launch, Deals gained lead capture, the central-discovery data source changed). Those notes are the source of truth over the original body text around them wherever the two disagree — this doc set stays in sync with the real app, not frozen at launch-planning time.

## Product overview (`about/`)

| File | Covers |
|---|---|
| [about/01-overview.md](about/01-overview.md) | What CampBuddy is, the problem it solves |
| [about/02-what-it-does.md](about/02-what-it-does.md) | Feature list, in plain language |
| [about/03-how-it-works.md](about/03-how-it-works.md) | The attendee's step-by-step flow |
| [about/04-who-its-for.md](about/04-who-its-for.md) | Audience: first-timers, returning attendees, organizers |
| [about/05-why-different.md](about/05-why-different.md) | Positioning / differentiators |

## Technical spec (`spec/`)

| § | File | Covers |
|---|---|---|
| 0 | [spec/00-findings.md](spec/00-findings.md) | Live-verified findings about WordCamp.org's data sources — read this first, it corrects assumptions made elsewhere |
| 1 | [spec/01-project-overview.md](spec/01-project-overview.md) | Product philosophy, audience, nav/IA, what's new vs. V1, objectives |
| 2 | [spec/02-scope.md](spec/02-scope.md) | Launch scope, fast-follow, explicitly out of scope |
| 3 | [spec/03-functional-requirements/00-index.md](spec/03-functional-requirements/00-index.md) | **Split further** — one file per feature (Home, Branding, Roster, Matching, Notifications, Quest, Deals, My Day, Contribute, Camp Card, Onboarding, Sponsors/Event Info, Data controls) |
| 4 | [spec/04-non-functional-requirements.md](spec/04-non-functional-requirements.md) | Accessibility, performance, error handling, offline-first, UI/UX constraints, data sync |
| 5 | [spec/05-system-architecture.md](spec/05-system-architecture.md) | Request flow, ingestion jobs, central discovery, CDN, asset build |
| 6 | [spec/06-technology-stack.md](spec/06-technology-stack.md) | The full stack, layer by layer |
| 7 | [spec/07-data-model.md](spec/07-data-model.md) | Tables added on top of V1's schema |
| 8 | [spec/08-security-privacy.md](spec/08-security-privacy.md) | Rate limiting, ingestion job safety, matching privacy architecture, roster data policy, analytics guardrail (implemented — see §22), discovery ownership model |
| 9 | [spec/09-admin-panel.md](spec/09-admin-panel.md) | Full admin CRUD surface |
| 10 | [spec/10-api-specification.md](spec/10-api-specification.md) | `/api/v1/*` endpoints |
| 11 | [spec/11-installation-setup.md](spec/11-installation-setup.md) | Local dev setup, WAMP config |
| 12 | [spec/12-deployment.md](spec/12-deployment.md) | Shared/cPanel hosting behind Cloudflare |
| 13 | [spec/13-prelaunch-blockers.md](spec/13-prelaunch-blockers.md) | Must-close-before-launch checklist |
| 14 | [spec/14-fallback-plan.md](spec/14-fallback-plan.md) | V1-as-fallback decision point |
| 15 | [spec/15-testing.md](spec/15-testing.md) | Backend testing, the 17-step acceptance journey, Definition of Done, responsive breakpoints |
| 16 | [spec/16-known-limitations.md](spec/16-known-limitations.md) | Permanent limitations vs. roadmap items |
| 17 | [spec/17-privacy.md](spec/17-privacy.md) | The attendee-facing privacy statement |
| 18 | [spec/18-credits-license.md](spec/18-credits-license.md) | Credits, GPL-2.0-or-later |
| 19 | [spec/19-build-order.md](spec/19-build-order.md) | The original 10-day build sequence |
| 20 | [spec/20-engineering-principles.md](spec/20-engineering-principles.md) | Process discipline — read before making structural changes |
| 21 | [spec/21-performance-hygiene.md](spec/21-performance-hygiene.md) | Laravel abstraction discipline, query discipline, security discipline |
| 22 | [spec/22-analytics.md](spec/22-analytics.md) | GA4 wiring, the `track()` allowlist, the full event catalogue, one-time GA-console setup |

### §3 quick lookup (functional requirements, split by feature)

| § | File |
|---|---|
| 3.1 Home | [spec/03-functional-requirements/01-home.md](spec/03-functional-requirements/01-home.md) |
| 3.2 Event branding | [spec/03-functional-requirements/02-branding.md](spec/03-functional-requirements/02-branding.md) |
| 3.3 Attendee roster ingestion | [spec/03-functional-requirements/03-roster-ingestion.md](spec/03-functional-requirements/03-roster-ingestion.md) |
| 3.4 Interest-based matching | [spec/03-functional-requirements/04-matching.md](spec/03-functional-requirements/04-matching.md) |
| 3.5 Schedule & notifications | [spec/03-functional-requirements/05-notifications.md](spec/03-functional-requirements/05-notifications.md) |
| 3.6 Quest | [spec/03-functional-requirements/06-quest.md](spec/03-functional-requirements/06-quest.md) |
| 3.7 Deals | [spec/03-functional-requirements/07-deals.md](spec/03-functional-requirements/07-deals.md) |
| 3.8 My Day & session details | [spec/03-functional-requirements/08-my-day.md](spec/03-functional-requirements/08-my-day.md) |
| 3.9 Contribute | [spec/03-functional-requirements/09-contribute.md](spec/03-functional-requirements/09-contribute.md) |
| 3.10 Camp Card | [spec/03-functional-requirements/10-camp-card.md](spec/03-functional-requirements/10-camp-card.md) |
| 3.11 Onboarding | [spec/03-functional-requirements/11-onboarding.md](spec/03-functional-requirements/11-onboarding.md) |
| 3.12 Sponsors & Event Information | [spec/03-functional-requirements/12-sponsors-event-info.md](spec/03-functional-requirements/12-sponsors-event-info.md) |
| 3.13 Data controls | [spec/03-functional-requirements/13-data-controls.md](spec/03-functional-requirements/13-data-controls.md) |

## Where this came from

This doc set is a split-up, cross-linked version of what used to be two files at the repo root: `ABOUT.md` (the product pitch, now `about/`) and `README.md` (the full spec, now `spec/`). Both original files still exist as short stubs pointing here, since `README.md` in particular is what GitHub and most tooling render as a repo's front page by default.

For **current, ground-truth information about the actual codebase** (not the original spec/plan), prefer reading the code itself over this doc set where the two might disagree — the "Current implementation note" callouts throughout `spec/` exist specifically to bridge the gap where they do, but the code is always the final authority.
