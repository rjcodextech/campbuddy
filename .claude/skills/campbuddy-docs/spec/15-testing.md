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

## 15.5 JavaScript tests and the load test *(added Sept 2026)*

- **JS unit tests** — `npm run test:js` (`node --test "tests/js/*.test.mjs"`, Node 20+, no dependencies). The attendee modules run against a small fake browser (`tests/js/helpers/fake-browser.mjs`: document, window, storage, scripted `fetch`, in-memory Cache Storage) with mocked timers, so minutes of polling run instantly. Covers the polling schedule/back-off (`polling.js`, `data-freshness.js`) and the chunk-error reload guard (`preload-recovery.js`).
- **Load test** — `node tools/loadtest.mjs --url https://<site> --event <slug> --users 100 --duration 90 --i-own-this-site`. Read-only (GET only), each virtual user has its own device id, capped in-flight/req-per-second, auto-stops when the site struggles; reports p50/p90/p95/p99, errors, 429s and Cloudflare cache status per request type; exit code 1 on FAIL. Ramp 50 → 200 → 500 → 1000 → 2000 users at a quiet hour while watching the host's CPU/entry-process graph. All users share one IP, like a venue behind one wifi, so the per-address rate limit (`RATE_LIMIT_ADDRESS_READS`) shows up as 429s.
- **First live measurement (Sept 2026, shared host behind Cloudflare, before the caching changes):** 20 users (~5 req/s) — p95 0.2 s, no errors; 150 users (~20 req/s) — p50 0.2 s but p95 2.1 s and p99 10 s (a queue at the host's PHP workers), still no errors. Every response was `cf-cache-status: DYNAMIC`. Treat ~15–20 req/s as the uncached ceiling of that host; the caching and polling changes exist to keep far more users below it.

- **Browser tests** — `npm run test:browser` (`tests/browser/offline.e2e.mjs`): drives a real Chrome/Chromium/Edge over the DevTools protocol (no dependencies; set `CHROME_PATH` if it isn't found) against the real `public/sw.js` and real app modules with a fake site that can change its data, fail, or drop connections. About two minutes (the app waits 45 s between freshness checks). Six scenarios: save the rest of the event, browse it all offline, refresh in place without ever deleting, survive a failing server, browse offline again, and the stale-while-revalidate switch. The JS unit tests use Node's module mocking (`--experimental-test-module-mocks`, Node 22.3+).
- **Long match lists (Sept 2026):** `tests/js/list-window.test.mjs` covers the "first 3, then Show 10 more" logic ([3.4](03-functional-requirements/04-matching.md)); it was also checked in a real Chrome at 390 px against a throwaway SQLite copy of the app seeded with 40 discovery profiles (3 cards shown, the button opens ten more, Wave / I met them / + Meet work on a card that had been hidden, and the list stays open after the re-render).
