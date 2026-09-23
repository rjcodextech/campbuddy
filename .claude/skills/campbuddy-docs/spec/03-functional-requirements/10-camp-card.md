# 3.10 Camp Card

[← Index](00-index.md) · Previous: [3.9 Contribute](09-contribute.md) · Next: [3.11 Onboarding →](11-onboarding.md)

The attendee's portable digital identity — should read as a modern conference badge, not a form.

| ID | Requirement |
|---|---|
| CC1 | Fields: profile image, name, role/title, company/community, city, WordPress interests, "ask me about," LinkedIn, personal website, WordPress.org profile, and a small set of other explicitly supported social links. All optional except name. |
| CC2 | The attendee explicitly chooses which filled-in fields actually appear on the card — filling a field in doesn't automatically put it on the visible card. Onboarding answers ([3.11](11-onboarding.md)) are never surfaced here automatically ([8.3](../08-security-privacy.md#83-matching-data--the-local-by-default-shared-only-by-choice-architecture)'s privacy boundary applies to Camp Card too, not only to matching). |
| CC3 | A QR code is generated from the field the attendee designates as primary — LinkedIn is the obvious default suggestion, but the attendee can point it at any of their chosen links. |
| CC4 | The card must be easy to bring to fullscreen with one tap, for the literal moment of handing a phone to someone else to scan. |
| CC5 | Camp Card data (drafts and published state) is local-only ([1.0](../01-project-overview.md#10-product-philosophy)), same as onboarding — nothing about it reaches a server except what the attendee separately chooses to expose via Explore → People matching ([3.4](04-matching.md)), which is a distinct, separately-opted-into action. |
| CC6 | A real empty state for a Camp Card that's not filled in yet ([4.3](../04-non-functional-requirements.md#43-error-handling--empty-states)): *"Your Camp Card is almost ready — add your name and a link you'd like people to scan."* |

> **Current implementation notes:**
> - CC6's empty state was replaced with an always-visible **sample preview** (placeholder name/role/tags) shown until the attendee has real data, so they can see how the card looks before filling anything in.
> - The attendee can pick from **5 selectable layouts** (Classic, Minimal, Bold, Split, Badge) — same fields (CC1), different visual treatment, applied and saved instantly.
> - The "X / Twitter" field (CC1) asks for just a **handle**, not a full URL — resolved to `https://x.com/handle` when building the QR/visible link.
