# 16. Known limitations & roadmap

[← Back to index](../SKILL.md) · Previous: [15. Testing](15-testing.md) · Next: [17. Privacy →](17-privacy.md)

- Matching only works among CampBuddy users ([finding 0.4](00-findings.md), [3.4](03-functional-requirements/04-matching.md)) — this is a permanent architectural fact, not a limitation to "fix" later. Worth stating clearly in-app so users understand why not every roster entry shows as a potential match.
- ~~Multi-event browsing, central auto-discovery, and full per-event theming are fast-follow.~~ Multi-event browsing and central auto-discovery have since shipped (see [3.2](03-functional-requirements/02-branding.md), [5.3](05-system-architecture.md#53-central-event-discovery)); full per-event theming was superseded by removing per-event color theming entirely instead of expanding it.
- Card PNG export at 2×, "scan a card," multi-tenant beyond WordCamp — carried forward from V1 as explicitly out of scope ([2.3](02-scope.md#23-explicitly-out-of-scope)).
- No formal third-party security audit — the manual OWASP pass ([13](13-prelaunch-blockers.md)) is the accepted bar for this launch; revisit for a real audit once the app has a second event under its belt.
