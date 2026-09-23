# 13. Pre-launch blockers (must close before "production ready")

[← Back to index](../SKILL.md) · Previous: [12. Deployment](12-deployment.md) · Next: [14. Fallback plan →](14-fallback-plan.md)

Per the project decision that these are blockers, not roadmap items:

| Blocker | Definition of done |
|---|---|
| CDN live | Cloudflare in front of production, cache-status header confirms edge hits on the public API ([5.4](05-system-architecture.md#54-cdn--now-mandatory-not-optional), §12.7). |
| `composer audit` | Runs clean (no known-vulnerable dependencies) as part of the deploy checklist, re-run before every production deploy. |
| Manual OWASP pass | Walk the OWASP Top 10 against the actual deployed app once before launch — documented as a short pass/fail checklist. Explicitly confirm the [8.6](08-security-privacy.md#86-discovery-api-ownership--a-real-gap-caught-and-fixed-here) owner-token check works (try mutating another profile without the token — must fail), and spot-check [21.3](21-performance-hygiene.md#213-security-discipline-that-scales-with-the-codebase-not-just-the-traffic)'s Form Request/mass-assignment rules on the admin and discovery endpoints. |
| Code-level hygiene spot-check | A targeted pass confirming [21](21-performance-hygiene.md) was actually followed where it matters most: no file-based locking anywhere, the discovery/roster/sessions endpoints are paginated, and list-rendering pages don't have an obvious N+1. |
| Acceptance journey green | The full 17-step journey in [15.2](15-testing.md#152-core-user-journey-acceptance-test), automated where practical and manually walked through where it isn't. |
| Definition of Done checklist | The full list in [15.3](15-testing.md#153-definition-of-done) reviewed and checked off. |
| LICENSE committed | GPL-2.0-or-later, as a `LICENSE` file at the repo root. |
| Roster takedown path live | The removal-request mechanism in [8.4](08-security-privacy.md#84-ingested-roster-data--explicit-policy) exists and is reachable before any roster data is ingested — not deferrable once real people's data is involved. |
