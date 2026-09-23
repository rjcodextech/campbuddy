# 15. Testing

[← Back to index](../SKILL.md) · Previous: [14. Fallback plan](14-fallback-plan.md) · Next: [16. Known limitations & roadmap →](16-known-limitations.md)

## 15.1 Backend

Pest/PHPUnit, same field-mapping coverage philosophy as V1, extended to the new ingestion parsers (mock HTML fixtures, not live requests, in CI).

## 15.2 Core user journey (acceptance test)

The canonical end-to-end script — if any step feels unnatural, the feature behind it isn't finished, regardless of what its own unit tests say. Automate what Playwright can reach; walk the rest manually (notably steps 14–16, the offline/reconnect behavior).

A brand-new attendee should be able to:

1. Open CampBuddy.
2. Select their WordCamp ([3.11](03-functional-requirements/11-onboarding.md) OB2).
3. Complete or skip onboarding ([3.11](03-functional-requirements/11-onboarding.md)).
4. Understand what is currently happening ([3.1](03-functional-requirements/01-home.md) H1).
5. Browse sessions ([3.8](03-functional-requirements/08-my-day.md) MD2).
6. Save a session ([3.8](03-functional-requirements/08-my-day.md) MD3).
7. Build My Day, including seeing a bookmark-overlap warning ([3.8](03-functional-requirements/08-my-day.md) MD4).
8. Complete a Quest ([3.6](03-functional-requirements/06-quest.md)).
9. Discover what Contributor Day is ([3.9](03-functional-requirements/09-contribute.md) CD1).
10. Get a contributor-team recommendation ([3.9](03-functional-requirements/09-contribute.md) CD2).
11. Build a Camp Card ([3.10](03-functional-requirements/10-camp-card.md) CC1–CC2).
12. Display its QR code fullscreen ([3.10](03-functional-requirements/10-camp-card.md) CC3–CC4).
13. Explore sponsors and deals ([3.12](03-functional-requirements/12-sponsors-event-info.md) EI1, [3.7](03-functional-requirements/07-deals.md)).
14. Lose internet connectivity.
15. Continue using all major locally-available features while offline ([4.4](04-non-functional-requirements.md#44-offline-first)).
16. Reconnect without losing progress or being interrupted mid-screen ([4.6](04-non-functional-requirements.md#46-data-synchronization)).
17. Export or clear their information ([3.13](03-functional-requirements/13-data-controls.md)).

## 15.3 Definition of Done

CampBuddy V2 is only "production ready" when all of the following are true, not just when [13](13-prelaunch-blockers.md)'s technical blockers are closed:

- No mandatory account/signup system exists anywhere in the flow.
- Event selection works ([3.11](03-functional-requirements/11-onboarding.md) OB2).
- Home provides genuinely contextual guidance, not a static card dashboard ([3.1](03-functional-requirements/01-home.md)).
- My Day, session bookmarking, and Quest all work ([3.8](03-functional-requirements/08-my-day.md), [3.6](03-functional-requirements/06-quest.md)).
- Contribute gives a real team recommendation with plain-language explanations ([3.9](03-functional-requirements/09-contribute.md)).
- Explore (People, Sponsors, Deals, Event Info) works end to end ([3.4](03-functional-requirements/04-matching.md), [3.7](03-functional-requirements/07-deals.md), [3.12](03-functional-requirements/12-sponsors-event-info.md)).
- Camp Card and its QR generation work ([3.10](03-functional-requirements/10-camp-card.md)).
- All personal state persists locally and survives reload/restart ([3.13](03-functional-requirements/13-data-controls.md) DP4).
- Privacy boundaries are respected: attendee discovery is explicit opt-in with a working leave path ([8.3](08-security-privacy.md#83-matching-data--the-local-by-default-shared-only-by-choice-architecture), [3.4](03-functional-requirements/04-matching.md) M2/M6).
- Core features work fully offline per the [4.4](04-non-functional-requirements.md#44-offline-first) floor.
- PWA install and standalone-mode behavior work on both Android and iOS.
- The app is usable across the breakpoints in [15.4](#154-responsive-testing), with no horizontal scroll, no overlapping/clipped controls, and the Camp Card QR still scannable at every size.
- Accessibility practices from [4.1](04-non-functional-requirements.md#41-accessibility) are implemented, not deferred.
- Errors and empty states are handled per [4.3](04-non-functional-requirements.md#43-error-handling--empty-states) everywhere.
- Event data is not hard-coded in the frontend — adding another WordCamp is a matter of configuring event data ([7](07-data-model.md), [9](09-admin-panel.md)), not editing application code.
- No existing, working CampBuddy functionality has been broken in the process ([20](20-engineering-principles.md)).

## 15.4 Responsive testing

At minimum: mobile at ~360px, ~390px, and ~430px widths; tablet in both portrait and landscape; desktop (mobile stays the design priority, but desktop must still work correctly, per [4.5](04-non-functional-requirements.md#45-uiux-constraints)). Check specifically for: no horizontal scroll, no overlapping components, no clipped controls, a navigation bar that stays usable at every width, long session/speaker names wrapping gracefully rather than overflowing, and the Camp Card QR remaining scannable at every tested size.
