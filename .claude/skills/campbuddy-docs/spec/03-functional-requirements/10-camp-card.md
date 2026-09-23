# 3.10 Camp Card

[← Index](00-index.md) · Previous: [3.9 Contribute](09-contribute.md) · Next: [3.11 Onboarding →](11-onboarding.md)

The attendee's portable digital identity — should read as a modern conference badge, not a form.

| ID | Requirement |
|---|---|
| CC1 | Fields: profile image, name, role/title, company/community, city, WordPress interests, "ask me about," LinkedIn, personal website, WordPress.org profile, and a small set of other explicitly supported social links. All optional except name. |
| CC2 | The attendee explicitly chooses which filled-in fields actually appear on the card — filling a field in doesn't automatically put it on the visible card. Onboarding answers ([3.11](11-onboarding.md)) are never surfaced here automatically ([8.3](../08-security-privacy.md#83-matching-data--the-local-by-default-shared-only-by-choice-architecture)'s privacy boundary applies to Camp Card too, not only to matching). |
| CC3 | A QR code is generated from the field the attendee designates as primary — LinkedIn is the obvious default suggestion, but the attendee can point it at any of their chosen links. |
| CC4 | The QR/card must be easy to hand to someone else to scan, without extra steps. |
| CC5 | Camp Card data (drafts and published state) is local-only ([1.0](../01-project-overview.md#10-product-philosophy)), same as onboarding — nothing about it reaches a server except what the attendee separately chooses to expose via Explore → People matching ([3.4](04-matching.md)), which is a distinct, separately-opted-into action. |
| CC6 | A real empty state for a Camp Card that's not filled in yet ([4.3](../04-non-functional-requirements.md#43-error-handling--empty-states)): *"Your Camp Card is almost ready — add your name and a link you'd like people to scan."* |

> **Current implementation notes:**
> - CC6's empty state was replaced with an always-visible **sample preview** (placeholder name/role/tags) shown until the attendee has real data, so they can see how the card looks before filling anything in.
> - All **6 layouts** (Classic, Minimal, Bold, Split, Badge, Pass) render at once as a **horizontally scrollable gallery** (`#camp-card-scroll`), each with its own Share/Download buttons — there is no single-layout picker or "active" layout to select or persist; same underlying data (CC1), 6 simultaneous visual treatments.
> - **Pass** (the 6th layout) is modeled on a physical lanyard pass: diagonal two-tone ribbon corners, a lanyard-hole cutout, the event's **full logo** (`Event::logoUrl()`, falling back to `/media/logo.png`) instead of a small icon, CampBuddy's **full wordmark** (`/media/logo.svg`) as a footer watermark instead of its square icon, and a static **"Code is Poetry"** signature band (WordPress's own tagline, italic serif) along the bottom edge.
> - **Role and company only ever appear once.** They have their own dedicated line above the tags (shown only if the attendee actually chose "Role"/"Company" in "Show on my visible card" — CC2's opt-in applies to them too, not just the other fields) and are explicitly excluded from the tag-pill list below, which previously could double-show "Role" as both the dedicated line's text and a separate pill.
> - The other 5 layouts show the **event's own icon** (`Event::faviconUrl()`, falling back to the app's shared default favicon) near the top in place of a generic "Camp Card" label, and **CampBuddy's own icon mark** (`public/media/icon.svg`) as a small watermark in the footer next to the QR. All logos sit in a small white chip (`.camp-card__event-mark` / `.camp-card__brand-mark`, or the `-full` variants for Pass) so they stay legible against any layout's background without per-layout color overrides.
> - The QR embeds the **event's icon in its center** at error-correction level `H` (~30% recoverable, safe headroom for the small covered area) and renders at 320px internally for crispness — both Share and Download capture the full card via `html2canvas`.
> - The QR frame (112px canvas) and footer layout (centered column: QR, then the brand mark below it) are now **identical across all 6 layouts** — previously each layout had its own QR size (72–120px) and footer direction (row vs column), which was both an inconsistency and the main reason card heights varied so much in the gallery. Unifying it closed most of that gap (was ~118px between shortest/tallest, now ~50px) and reads as one consistent card family with 6 skins, matching the file's own "same structure, different --modifier" design principle.
> - Every card has a visible **3px charcoal border** (`.camp-card` base rule) so it reads as a distinct object against the page background regardless of the card's own fill color.
> - **Share** (Web Share API, with a file-sharing check and download fallback for browsers without it) and **Download** are the only two actions — fullscreen and print were removed.
> - The "X / Twitter" field (CC1) asks for just a **handle**, not a full URL — resolved to `https://x.com/handle` when building the QR/visible link.
