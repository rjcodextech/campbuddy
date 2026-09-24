# 16. Known limitations & roadmap

[← Back to index](../SKILL.md) · Previous: [15. Testing](15-testing.md) · Next: [17. Privacy →](17-privacy.md)

- Matching only works among CampBuddy users ([finding 0.4](00-findings.md), [3.4](03-functional-requirements/04-matching.md)) — this is a permanent architectural fact, not a limitation to "fix" later. Worth stating clearly in-app so users understand why not every roster entry shows as a potential match.
- ~~Multi-event browsing, central auto-discovery, and full per-event theming are fast-follow.~~ Multi-event browsing and central auto-discovery have since shipped (see [3.2](03-functional-requirements/02-branding.md), [5.3](05-system-architecture.md#53-central-event-discovery)); full per-event theming was superseded by removing per-event color theming entirely instead of expanding it.
- Card PNG export at 2×, "scan a card," multi-tenant beyond WordCamp — carried forward from V1 as explicitly out of scope ([2.3](02-scope.md#23-explicitly-out-of-scope)).
- No formal third-party security audit — the manual OWASP pass ([13](13-prelaunch-blockers.md)) is the accepted bar for this launch; revisit for a real audit once the app has a second event under its belt.

- **Trusting all proxies.** `trustProxies(at: '*')` is right when the origin is only reachable through Cloudflare; if it is also reachable directly, a client could spoof `X-Forwarded-For` to dodge per-IP rate limits. Restrict the origin firewall to Cloudflare's ranges if that matters.
- **Shared IP at a venue.** The API limits are per IP (60/min general, 10/min writes — [8.1](08-security-privacy.md#81-rate-limiting)). Hundreds of attendees on one venue wifi share one public IP, so a rush of "Join attendee discovery" taps can be refused (the app says "Couldn't save — check your connection"). Raise `api-writes` in `AppServiceProvider` for big events.
- **No CSP / HSTS from the app.** See [8.2a](08-security-privacy.md#82a-hardening-pass-full-review) — set HSTS at Cloudflare; a CSP needs testing against the real deployment first.
- **Roster takedown is open by design** — anyone can hide any roster entry (throttled; reversible in admin). See [8.2a](08-security-privacy.md#82a-hardening-pass-full-review).
- **Purge-cache is bounded, not exhaustive.** It refreshes events synchronously for ~30 s and queues the rest (ready within a minute or two once the cron runs); it doesn't re-scrape the attendee roster (once-a-day rule) and can't reach a device that is offline until it next opens the app.
- **The scheduler is the single point of failure for ingestion** — if the one cPanel cron entry stops, no data is refreshed (the last good data keeps showing for up to 14 days). Check *Dashboard → Ingestion problems* and the last-fetched times after any hosting change.
