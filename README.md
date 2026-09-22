# CampBuddy — WordCamp Rajasthan 2026

A tiny, local-first, mobile-first PWA prototype for first-time WordCamp attendees.

## Included in v1

- First-time attendee onboarding
- Event countdown and context-aware "Right now" panel
- Pre-WordCamp checklist
- "I have free time" micro networking missions
- Camp Quest / social nudges
- Contributor Day team finder
- Camp Card with a branded, downloadable and shareable QR code
- Explore — latest WordCamp Rajasthan videos from the WPSimplified YouTube channel
- Sponsor list and official social links
- My Day / saved schedule anchors
- Useful venue and official event links
- Local-only storage
- Export/reset controls
- Offline service worker
- Installable PWA manifest
- No backend and no user account

## Run locally

Because service workers require HTTP(S), do not open `index.html` with `file://` if you want offline/PWA behavior.

Python:

    python3 -m http.server 8080

Then visit:

    http://localhost:8080

## Deploy free

This folder is suitable for a static host such as Cloudflare Pages, GitHub Pages, Netlify or Vercel.

No build step is required **to deploy** — `styles.css` is committed and served as-is. A build step is only needed if you're *editing styles*; see "Styling (SCSS + BEM)" below.

## Event data

The current event metadata is at the top of `app.js` in `EVENT`.

The prototype intentionally does **not** invent unverified speaker/session times. The included My Day entries are either:
- verified event anchors from the organizer site, or
- clearly labeled CampBuddy suggestions.

Replace `DEMO_SCHEDULE` with official session data when a stable source is available.

## Privacy

Checklist, quests, interests and Camp Card data are saved to browser `localStorage`.

CampBuddy itself has no backend.

The Camp Card QR code is generated fully client-side (via the `qrcode-generator` library, loaded from a CDN) with the WordCamp Rajasthan mark drawn into the center — nothing is uploaded to render it. Download saves a PNG; Share uses the Web Share API where supported (falling back to download).

### Analytics

CampBuddy sends anonymous, aggregate usage events to Google Analytics 4 (`G-1YHQ19XV0P`) — route views, feature usage (checklist/quest toggles, QR generated/downloaded/shared, mission views, onboarding funnel, outbound link clicks), install prompt outcomes. See `track()` calls throughout `app.js` for the full event list.

**Hard rule: Camp Card field values (name, role, "ask me about," "meet," link) are never sent to analytics.** Those are user-typed personal info, stay in `localStorage` only, and only leave the device if the person explicitly taps Export. This isn't just a privacy courtesy — Google Analytics' own terms of service prohibit sending PII, so this is a requirement for using GA at all, not an optional nicety. If you add new `track()` calls, don't pass raw user-input field values as params.

## WordCamp Rajasthan branding

Official brand assets live in `wcr/` (source originals) and `wcr/web/` (resized, web-ready derivatives used by the app). Regenerate the web versions with:

    python scripts/build-wcr-assets.py

The CSS palette (`--maroon`, `--navy`, `--gold`, `--teal`, `--pink`, in `scss/abstracts/_variables.scss`) is sampled from the official WordCamp Rajasthan mark. CampBuddy's own logo/favicon (`logo.png`, `favicon.png`) remain the app's primary identity — WCR branding is applied through color, the Camp Card, the QR mark and the onboarding mascot rather than replacing CampBuddy's own logo.

## Styling (SCSS + BEM)

Styles are authored in `scss/` and compiled to `styles.css` (the file `index.html` actually loads). Don't hand-edit `styles.css` — it's a build artifact and will be overwritten.

    npm install        # once, installs the sass compiler (dev-only, not shipped)
    npm run watch       # recompiles styles.css on every save while you work
    npm run build       # one-off compressed build (commit the resulting styles.css)

Source layout:
- `scss/abstracts/` — design tokens (`:root` custom properties) and responsive mixins, no CSS output of their own.
- `scss/base/` — the reset/normalize layer.
- `scss/layout/` — page chrome: app shell, topbar, bottom nav.
- `scss/components/` — one file per BEM block (e.g. `_camp-card.scss` owns `.camp-card` and all its `__element`/`--modifier` rules), including that block's own responsive overrides nested inline rather than in a separate global media-query file.
- `scss/utilities/` — plain `u-*` helper classes (`u-row`, `u-muted`, `u-section`, …). These are a deliberate exception to BEM: generic spacing/flex/typography helpers with no component meaning of their own. Every other class in the project is `block`, `block__element`, or `block--modifier`.

Example from `_camp-card.scss`:

```scss
.camp-card {
  // block-level styles
  &__name { /* .camp-card__name */ }
  &__label {
    // element styles
    &:first-child { margin-top: 0; }
  }
}
```

## Live data (WPSimplified API)

Explore videos and event details are fetched at runtime from:
- `https://wpsimplified.in/wp-json/wpsimplified/v1/media`
- `https://wpsimplified.in/wp-json/wpsimplified/v1/events?slug=wordcamp-rajasthan-2026`

Both endpoints are public (Origin/Referer-checked on the WordPress side, no secret key sent from the client — a static app has nowhere safe to keep one; a key embedded in `app.js` would be readable by anyone via view-source). If a request ever fails (offline, endpoint down, shape changes), the app falls back to the bundled static data, so nothing breaks.

Confirmed response shapes (`app.js` parses these exactly, no field-name guessing):
- `/media` → `{items:[{id, title, thumbnail, youtube_link, type}], total, total_pages, current_page}`. `youtubeIdFromLink()` pulls the real YouTube id out of `youtube_link` (handles both `/shorts/` and `watch?v=` links).
- `/events?slug=...` → `{events:[{title, event_tagline, event_start_date, event_end_date, event_venue_name, event_venue_address, event_hashtag, event_home_url, event_tickets_url, event_venue_directions_url, event_email, event_social:[{label,url}], event_ticket_types:[{name,price,status}], event_sponsors:[{tier,name,logo,url}], event_agenda:[{day,time,title,description}], …many more}]}`. `buildEventPatch()` maps the fields CampBuddy actually displays; `sponsorsFromLive()` and `agendaFromLive()` derive the Sponsors list (with real logos, grouped by the organizer's own Nahargarh Fort/Hawa Mahal/Jal Mahal tiers → platinum/silver/bronze) and the My Day schedule (all 38 real sessions, grouped by day) respectively.

`EXPLORE_VIDEOS_FALLBACK` and `SPONSORS_FALLBACK` in `app.js` are the hand-curated backups (same shapes as the live-derived data) used only if the API is unreachable; `DEMO_SCHEDULE` is the My Day fallback. Update them occasionally so they stay reasonably fresh.

Responses are cached in `localStorage` for 6 hours (`campbuddy-media-v1`, `campbuddy-event-v1`, `campbuddy-sponsors-v1`, `campbuddy-agenda-v1`) to reduce API calls and survive brief connectivity drops. The service worker deliberately excludes `/wp-json/` requests from its own cache (so data never goes stale behind a cached response) while still caching the image assets served from the same host (thumbnails, sponsor logos) normally.

## Sources used for the Rajasthan prototype

Organizer website:
- https://rajasthan.wordcamp.org/2026/
- https://rajasthan.wordcamp.org/2026/schedule/
- https://rajasthan.wordcamp.org/2026/contribute-learn-connect/
- https://rajasthan.wordcamp.org/2026/contact/
- https://rajasthan.wordcamp.org/2026/tickets/
- https://rajasthan.wordcamp.org/2026/sponsors/
- https://rajasthan.wordcamp.org/2026/code-of-conduct/

Verified details incorporated:
- 3–4 October 2026
- Contributor Day on 3 October
- Conference Day on 4 October
- Rajasthan International Centre, Jhalana Doongri, Jaipur
- two parallel tracks
- 20 talks & workshops
- Sponsor Hall open through Conference Day
- lunch listed for 11:30 AM–1:30 PM in Convention Hall
- Contributor Day welcomes different kinds of contributors and recommends bringing a laptop
- Ticket price ₹1,000 (both days) / ₹1,400 with after-party
- Sponsor list and tiers (Platinum/Silver/Bronze)
- Official social links (X, Instagram, Facebook, LinkedIn — all `@wprajasthan`)
- Hashtag `#WordCampRajasthan`

## Next recommended engineering step

Move the event configuration into `/events/rajasthan-2026.json`, then add an importer for WordCamp.org public data so another camp can be added without editing application code.

## License suggestion

GPL-2.0-or-later, to align naturally with the WordPress ecosystem.
