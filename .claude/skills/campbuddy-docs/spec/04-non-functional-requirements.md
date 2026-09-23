# 4. Non-functional requirements

[← Back to index](../SKILL.md) · Previous: [3. Functional requirements](03-functional-requirements/00-index.md) · Next: [5. System architecture →](05-system-architecture.md)

| Category | Requirement |
|---|---|
| **Performance** | Same caching posture as V1 (`Cache-Control`/`ETag`), fronted by a CDN ([5.4](05-system-architecture.md#54-cdn--now-mandatory-not-optional)) so these headers are actually honored at the edge, not just at origin. See [4.2](#42-performance) for specifics. |
| **Scalability** | Origin is shared cPanel hosting with real connection limits. Scale headroom comes from the **CDN absorbing repeat reads**, not from the origin being infinitely horizontal. Rate limiting moves to the CDN edge ([5.4](05-system-architecture.md#54-cdn--now-mandatory-not-optional)) so it doesn't compete with real traffic for DB connections. |
| **Availability** | Same stale-while-revalidate posture as V1: reads never block on upstream or ingestion jobs being slow. |
| **Security** | See [8](08-security-privacy.md). |
| **Offline-first** | See [4.4](#44-offline-first) for the specific floor of what must keep working with no connection. |
| **Privacy** | No account required for attendees; "local by default, shared only by choice" ([1.0](01-project-overview.md#10-product-philosophy)) — see [8.3](08-security-privacy.md#83-matching-data--the-local-by-default-shared-only-by-choice-architecture) for the matching-specific architecture. |
| **Accessibility** | See [4.1](#41-accessibility). Not an optional polish pass — WordCamp is an inclusive community event. |
| **Browser/device support** | Modern mobile/desktop browsers, installable PWA on Android and iOS; also functions correctly as a plain website if not installed. |
| **Maintainability** | Laravel conventions (Eloquent, Jobs/Queues for ingestion, Scheduler for cron) replace V1's hand-rolled Slim actions. |

## 4.1 Accessibility

Sensible WCAG practices, at minimum: sufficient color contrast, full keyboard accessibility, semantic HTML, labeled form fields, accessible buttons (not bare clickable `<div>`s), alt text on meaningful images, visible focus states, screen-reader-friendly document structure, touch targets sized for real thumbs on a real phone, and `prefers-reduced-motion` respected on any hover/transition animation.

## 4.2 Performance

Optimize aggressively — this app gets used on congested venue wifi by many people at once:

- Small initial payload; lazy-load anything non-critical.
- Optimize images (this is why branding assets are downloaded and re-hosted rather than hotlinked, [3.2](03-functional-requirements/02-branding.md) BR4 — CampBuddy controls their size/format, not the source site).
- Minimize API calls; cache event data client-side and work from the cached copy rather than re-fetching unchanged information (this is the entire point of the stale-while-revalidate + CDN design in [5.4](05-system-architecture.md#54-cdn--now-mandatory-not-optional)).
- Avoid large JS dependencies for trivial functionality — vanilla JS stays the default ([5.5](05-system-architecture.md#55-asset-build--laravels-default-vite-pipeline)); a library is justified per-case, not by default.
- Navigation between tabs should feel immediate — no visible loading spinner for data that's already been downloaded this session.

## 4.3 Error handling & empty states

**Errors:** user-facing messages are always friendly and actionable — never a stack trace, raw PHP warning, JSON error body, SQL message, or a technical network exception string. Translate, don't expose: not *"FetchError ECONNREFUSED"* but *"We couldn't refresh the latest event information. Your saved CampBuddy data is still available."* This is the same "guidance over information" principle ([1.0](01-project-overview.md#10-product-philosophy)) applied to failure states, not just happy-path content.

**Empty states:** every major screen needs one — a blank screen is never acceptable. Examples already speced inline: My Day ([3.8](03-functional-requirements/08-my-day.md)), Camp Card ([3.10](03-functional-requirements/10-camp-card.md) CC6), Quest ([3.6](03-functional-requirements/06-quest.md) C5). The same standard applies to Explore's People/Sponsors/Deals tabs and Contribute before the attendee has answered anything.

## 4.4 Offline-first

Once an event's data has been downloaded, these must keep working with zero connection: Home's guidance ([3.1](03-functional-requirements/01-home.md), from already-downloaded data), the event schedule and My Day ([3.8](03-functional-requirements/08-my-day.md)), Quest ([3.6](03-functional-requirements/06-quest.md)), Contribute's team info ([3.9](03-functional-requirements/09-contribute.md)), Camp Card ([3.10](03-functional-requirements/10-camp-card.md)), the attendee's own onboarding preferences ([3.11](03-functional-requirements/11-onboarding.md)), and any sponsor information already downloaded ([3.12](03-functional-requirements/12-sponsors-event-info.md) EI1).

Network-dependent features degrade gracefully instead of breaking: if Explore → People ([3.4](03-functional-requirements/04-matching.md)) can't reach `/api/v1/events/{slug}/discovery`, the app doesn't show a raw error — it shows something like *"You're offline. Your CampBuddy still works — attendee discovery will refresh when you're connected again."*

## 4.5 UI/UX constraints

Mobile-first, fast, clean, friendly, modern, easy to scan, touch-friendly, visually polished — designed for someone walking around a venue with one hand on their phone. Explicitly avoid: cramped layouts, a desktop-style admin-panel look bleeding into the attendee-facing app (that look is fine for `/admin` itself, [5.5](05-system-architecture.md#55-asset-build--laravels-default-vite-pipeline) — never for the attendee side), walls of text, excessive modals, tiny tap targets, settings nobody asked for, excessive animation, or an overly corporate tone. Use progressive disclosure — show what's needed when it's needed, not everything at once.

> **Current implementation note:** the attendee app is **full width (100%) at every viewport size** — no centered phone-column/card frame and no separate desktop layout (earlier versions capped it at 428px, then gave desktop a 1040px frame with a left-sidebar nav; both were dropped). The tab bar stays pinned to the bottom of the frame at every width. The "/" WordCamp picker sits in the same `.app-frame` as the event pages (`layout/_app-frame.scss`, `layout/_bottom-nav.scss`). Because the app is designed for a phone, laptop/desktop visitors — a viewport ≥1024px with a hovering, precise pointer, so not tablets or narrow windows — get a dismissible "CampBuddy is designed for your phone" notice with a QR code of the current page at the top of every page (`partials/desktop-notice.blade.php`, `components/_desktop-notice.scss`, `js/attendee/desktop-notice.js`); dismissal is remembered in `localStorage`.

## 4.6 Data synchronization

When connectivity returns: refresh outdated event data, sync explicitly-shared discovery data where applicable ([3.4](03-functional-requirements/04-matching.md)), and never touch local Quest/My Day/onboarding state beyond what the attendee changed themselves. Reconnecting must never interrupt whatever screen the attendee is currently on — no forced reload, no lost scroll position.
