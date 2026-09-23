# 3.12 Explore — Sponsors & Event Information

[← Index](00-index.md) · Previous: [3.11 Onboarding](11-onboarding.md) · Next: [3.13 Data controls →](13-data-controls.md)

| ID | Requirement |
|---|---|
| EI1 | **Sponsors**: name, description, booth location (where organizers provide it), website, and a link into any active Deals ([3.7](07-deals.md)) from that sponsor — sourced from the REST ingestion in [finding 0.1](../00-findings.md). |
| EI2 | **Event Information**: venue, important links, wifi details (only if organizers supply them — never fabricated), social event info, registration info, Contributor Day location, code of conduct, emergency/contact info, and any other organizer-supplied nearby-venue info. This is where V1's "More" tab content lives now ([1.2](../01-project-overview.md#12-navigation--information-architecture)) — folded into Explore rather than kept as its own top-level tab. |
| EI3 | Every field in EI2 is admin-editable per event ([9](../09-admin-panel.md)) and simply omitted from display when an organizer hasn't supplied it — never a placeholder or a broken-looking blank field. |

> **Current implementation note:** EI2's fields render as plain text rows, not dead links — only Code of Conduct/Important Links (which have real URLs) and Emergency Contact (auto-detected as `tel:`/`mailto:` when the value looks like a phone number or email) are actually tappable. Sponsor listings (EI1) also show the event's own logo/name at the top of this panel — see [3.2](02-branding.md)'s current-implementation note.
