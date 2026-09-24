# 3.9 Contribute

[← Index](00-index.md) · Previous: [3.8 My Day & session details](08-my-day.md) · Next: [3.10 Camp Card →](10-camp-card.md)

Contributor Day is confusing to many first-timers — this section exists to make contributing to WordPress approachable without assuming any community terminology up front.

| ID | Requirement |
|---|---|
| CD1 | A short question flow (not a long questionnaire): what kind of work the attendee enjoys — technical or non-technical, writing, design, testing, support, documentation, development, community work, translation, organizing. Every question is skippable. |
| CD2 | Based on answers, CampBuddy suggests suitable **WordPress Contributor Teams**. Launch shipped with a fixed, curated team list (Core, Docs, Support, Photography, Testing, Polyglots, Design, Training, Accessibility, and similar) — expanding this to the full, dynamically-current official team list is fast-follow, not a launch blocker, since the underlying team roster changes rarely enough that a periodically-updated static list is an acceptable trade-off. |
| CD3 | For each suggested team, explain in plain language: what the team does, who it tends to suit, whether technical knowledge is required, one or two examples of a beginner-friendly task, and concretely what to do when the attendee reaches that team's table (who to look for, what to say). No unexplained community jargon — a first-timer shouldn't need to already know what "Trac" or "Polyglots" means to get value from this screen. |
| CD4 | Contribute integrates with Quest ([3.6](06-quest.md) C3) — engaging with this section can surface a Contributor Day-flavored quest (see "Contribution Curious" in [3.6](06-quest.md)'s current-implementation note). |

> **Current implementation note (CD4 was silently broken, now fixed):** opening a team's detail is supposed to tick the **"Contribution Curious"** quest, but `EventPageController::contribute()` looked for a quest with `source = 'contributor_day'` — and the seeder has only ever created it as a `default`. So no quest id reached the browser and nothing was ticked. It now finds the default quest by its stable title (`Quest::CONTRIBUTION_CURIOUS`, also the `THINGS_TO_DO_META` key) — or an admin-made `contributor_day` one — and `HardeningTest` guards it. The page heading uses the shared `.u-page-title` (24px) instead of the browser's default 32px `h1`.
