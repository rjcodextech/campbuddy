# 3.6 Quest

[← Index](00-index.md) · Previous: [3.5 Schedule & notifications](05-notifications.md) · Next: [3.7 Deals →](07-deals.md)

Quest turns the intimidating social side of a WordCamp into a set of small, achievable activities — "say hello to someone attending their first WordCamp," "meet someone from another city," "visit three sponsor booths," "introduce yourself to a speaker," "learn what one Contributor Team does," "complete your Camp Card." Playful, not childish — and deliberately not gamified beyond that: no points, currencies, or leaderboards.

| ID | Requirement |
|---|---|
| C1 | Each event has an admin-editable list of 8–10 quests, each independently markable complete. |
| C2 | Quest progress is **local-only** ([1.0 principle 2](../01-project-overview.md#10-product-philosophy)), no server sync needed — consistent with every other piece of personal progress in the app. |
| C3 | Quests come from two sources, shown together: **default CampBuddy quests** (apply to every event) and **event-specific quests** (admin-authored per event, [9](../09-admin-panel.md)). |
| C4 | No points, currency, leaderboard, or competitive ranking — completion is binary (done/not done) and private to the device. This is a deliberate constraint ([1.0](../01-project-overview.md#10-product-philosophy)), not a missing feature to add later without reconsidering the product philosophy first. |
| C5 | The Quest tab needs a real empty state for a brand-new attendee ([4.3](../04-non-functional-requirements.md#43-error-handling--empty-states)): something like *"Your WordCamp adventure starts here — pick your first Quest."* |

> **Current implementation note:** Quest now has two visually distinct sections — a "Things to do" set of 8 curated default quests (First Hello, Beyond My City, Speaker Hello, Contribution Curious, Sponsor Explore, Asked Something, Keep The Connection, Share Camp Card), each rendered as an icon+description card with a contextual action button (e.g. "View sponsors →"), plus a plain "Checklist" section for admin-authored event-specific quests. The original standalone "Contributor Day quest" source was folded into "Contribution Curious." Both sections share the same local-only completion tracking (C2/C4 above).
