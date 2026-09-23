# 17. Privacy

[← Back to index](../SKILL.md) · Previous: [16. Known limitations & roadmap](16-known-limitations.md) · Next: [18. Credits & license →](18-credits-license.md)

Carries forward all of V1's privacy posture, plus: the formal "local by default, shared only by choice" principle ([1.0](01-project-overview.md#10-product-philosophy)), the anonymous-discovery-identifier matching architecture ([3.4](03-functional-requirements/04-matching.md), [8.3](08-security-privacy.md#83-matching-data--the-local-by-default-shared-only-by-choice-architecture)), ingested roster data policy ([8.4](08-security-privacy.md#84-ingested-roster-data--explicit-policy)), and the hardened analytics guardrail ([8.5](08-security-privacy.md#85-analytics-guardrail)) are part of the app's public-facing privacy statement, not just internal engineering discipline.

This is the plain-language version worth putting in front of an attendee: *your onboarding answers, Camp Card drafts, and Quest progress stay on your phone; the only thing that ever leaves it is what you explicitly choose to publish for matching, under a random ID, and you can take it back with one tap, at any time.*

> **Current implementation note:** Deals lead capture ([3.7](03-functional-requirements/07-deals.md)) is a deliberate, narrow, later exception to this posture — an attendee who chooses to open a deal that has lead capture enabled shares Name/Email/Mobile with that specific sponsor, on a per-deal opt-in basis (never required to use the rest of the app). See [3.7](03-functional-requirements/07-deals.md) for exactly how it's scoped.
