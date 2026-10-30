# CampBuddy Brand Guidelines

Read this before you design, write copy, build UI, or make a social post,
people card, reel, QR kit or store graphic for CampBuddy. Follow it to the
letter. If a request conflicts with it, follow these rules and tell the user
which rule you applied.

It is modelled on the WPSimplified guidelines ([`WPSIMPLIFIED-BRAND-GUIDELINES.md`](WPSIMPLIFIED-BRAND-GUIDELINES.md),
"Daylight"): the same rules — paper ground, ink text, **one** brand voice,
waves and dots, calm copy — in CampBuddy's own colours and logo. CampBuddy is
made by WPSimplified, but it is not dressed as WPSimplified: no WPSimplified
palette, logo or name except the small credit line.

The people-facing version, drawn with the live tokens, is the **Brand guidelines** page: `/brand-guidelines` (`resources/views/brand/guidelines.blade.php`, data in `app/Support/BrandTokens.php`).

It applies everywhere: admin panel, event-manager panel, attendee site/PWA,
Android app, iPhone app, social posts, people cards, reels, QR kit, store
listing and email.

---

## 0. Sources of truth — never duplicate, never guess

| What | Single source | Mirrors (must equal the source) |
| --- | --- | --- |
| Colours, type, space, radii, shadows, motion | `resources/scss/abstracts/_variables.scss` (→ CSS custom properties in `base/_root.scss`) | `resources/css/app.css` `@theme` (admin/manager, Tailwind 4), `native-app/.../shared/ui/Theme.kt` + server theme defaults, `resources/js/brand.js` (social posts, reels, canvas) |
| Logos | `public/media/logo-wordmark.png` (+ `-sm`), `public/media/logo.png`, `public/media/favicon.png` / `icons/*` (app icon) | Native app resources are copies of these |
| Fonts | `resources/fonts/baloo2-*.woff2`, `resources/fonts/poppins-*.woff2` + `resources/css/fonts.css` (web, bundled by Vite into `/build/`, so offline too); `composeResources/font/baloo2_*.ttf`, `poppins_*.ttf` (native) | Self-hosted. No Google Fonts, no other families (Camp Card keeps its own badge fonts, §4). |
| Icons | Line-icon sprite (`App\Support\LineIcons`, `attendee/partials/line-icons.blade.php`); admin `x-icon` | Same stroke rules in Kotlin `Icons` |

A new value goes into `_variables.scss` first, with a comment, then into every
mirror. `npm run brand` (`tools/brand-check.mjs`) fails when a mirror drifts.

## 1. Brand essence

- **Name:** CampBuddy (one word, capital C and B). Never "Camp Buddy", "Campbuddy" or "CB".
- **Line:** *Your WordCamp companion.* (The wordmark reads "WordCamp companion".)
- **What:** a free app for WordCamp attendees — schedule, My Day, people, Camp Card, quests, deals, memories — that works offline.
- **Who:** WordCamp attendees, volunteers, speakers and organisers; mostly in India, mostly on phones, many at their first ("pehla") WordCamp. Hindi, Hinglish and English.
- **Promise:** calm and useful on a busy day. Every screen answers "what do I do next?" in one glance.
- **Who it promotes:** every graphic and reel promotes three things at once — the **event**, **CampBuddy** and **WPSimplified** (who made it). The event leads; CampBuddy × WPSimplified sign every card and frame.
- **The lockup:** "CampBuddy × WPSimplified" — CampBuddy's wordmark, a thin divider, WPSimplified's mark a little smaller (`brandLockup()` in `social-kit.js`). WPSimplified is never larger than CampBuddy.

## 2. Logo

| Ground | Use | Notes |
| --- | --- | --- |
| Paper / white / light photo | `logo-wordmark.png` (header, brand bar), `logo.png` (large) | Default. |
| Ink, gradient, dark photo | Wordmark on a **paper plate** (pill, `--color-paper`) | There is no white wordmark — the wine/ochre logo never sits directly on dark. |
| App icon, favicon, avatar | `favicon.png` / `icons/*` (wine tile, cream circle) | Only square uses. |

- **Clear space:** ≈ 28% of the logo height on every side.
- **Minimum size:** wordmark 120px wide; site/app header 32px tall.
- **Plates:** centre the logo on both axes, padding = 0.35 × plate height.
- **Never:** stretch, squash, rotate, recolour, outline, add shadows/glows, retype the wordmark, put it on a busy image without a plate.
- The event's own logo may sit **next to** CampBuddy's (event pages, posts), never inside or over it.

## 3. Colour — "Daylight 2.0" (owner, 27 Oct 2026)

Warm paper ground, slate ink text, **one** coral brand voice; amber is its gradient partner and
gold its highlight. Approximate share of a screen:
paper 56% · ink 20% · coral 12% · amber 5% · gold 4% · role colours 3%.
The source of every value is `resources/scss/abstracts/_variables.scss`; `npm run brand` fails when
this table, the admin theme, the canvas tokens, the app or the server defaults say otherwise.

| Token | Hex | Use (contrast measured on paper unless noted) |
| --- | --- | --- |
| `--paper` / `paper` | `#FAFAFD` | Page ground, chips |
| `--paper-soft` / `paper-soft` | `#FFF7ED` | Soft bands, selected rows, app background |
| `--color-surface-card` | `#FFFFFF` | Cards, sheets, inputs |
| `--line` / `line` | `#E2E8F0` | Borders, dividers |
| `--ink` / `ink` | `#0F172A` | Text, deep bands (17.1:1) |
| `--muted` / `muted` | `#475569` | Secondary copy (7.3:1) |
| `--subtle` / `subtle` | `#64748B` | Meta, captions (4.6:1) |
| `--brand-primary` / `maroon` | `#FF4D2D` | Electric Fire Coral: brand, primary buttons, icons. **White label 3.3:1 and coral text 3.2:1 — below 4.5:1 for small text** (see the note below) |
| `maroon-dark` | `#E03616` | Hover / pressed primary (white label 4.5:1 at 18px+ bold only) |
| `--wine` / `wine` | `#EA580C` | Radiant Amber, the gradient partner: `--gradient-brand` = coral → amber, 135° |
| `--gold` / `gold` | `#D97706` | Selected chips (ink on gold 5.6:1), stars, highlights. Never text on paper (3.1:1). Glows use Sunny Gold `#F59E0B` |
| `--teal` / `teal` | `#2563EB` | Tech Indigo — role: sessions / schedule / "now" (5.0:1) |
| `--navy` / `navy` | `#1E293B` | Role: events / travel; alternative deep band |
| `--success` / `success` | `#10B981` | Done, saved, checked in — as a fill or icon only (2.4:1 as text) |
| `--danger` / `danger` | `#EF4444` | Errors, destructive actions, LIVE badges (3.6:1 as text) |
| `--brand-text` | `#D33417` | Coral for **small text** and small white-on-coral chips: links, the active tab, badges (4.7:1 on paper, white on it 4.9:1). One token: `$color-brand-text` → panel `--color-maroon-text` (`text-maroon-text`) → app `theme.primary_text`; `npm run brand` keeps them equal. To go back to bright coral text, change all three to `#FF4D2D` (fails AA) |
| `--brand-text-dark` | `#B0280F` | Hover / pressed state of coral text, and coral text on the peach card (5.6:1) |
| `--wine-deep` / `--gradient-button` | `#C2410C` | Primary buttons and any chip with a **white label**: deep coral `#D33417` → deep amber `#C2410C` (white 4.9–5.2:1). Panel: `--color-wine-deep`, app: `BRAND_WINE_DEEP` |
| `--danger-text` | `#DC2626` | Danger as text (required-field stars, Delete labels) |
| `--success-text` | `#047857` | Success as text ("DPDP Act 2023 Compliant", Free tags) |

**Contrast rule (30 Oct 2026 audit, 6 widths):** Daylight 2.0's coral, success and danger are bright. They stay
for borders, icons, glows and large type; **small text uses the `-text` shade and white labels sit on the deep fill** (`--gradient-button`) (same hue, deep enough
for WCAG AA 4.5:1). The app does the same by itself: `Brand.primaryText` deepens whatever primary the
server sends until it reaches 4.6:1.
The Camp Card keeps its own badge palette (`components/_camp-card.scss`, `CampCardFaces.kt`).

- Every text colour reaches **4.5:1** on its ground (3:1 for text ≥ 24px and icons). Check hover, focus, active and disabled too.
- Badges: a soft tint of the role colour (≈10%) + the role colour as text.
- Dark bands (`.u-theme-dark`, ink or the gradient): text white, `--color-primary` becomes ochre, highlights ochre.
- **Delete is never maroon.** Destructive actions use `--color-danger`, so "Delete" never looks like "Save".
- **Event colours:** an event's own colours appear only in its *content* (event logo, social posts for that event, §8) — never in CampBuddy's UI chrome. The app has one fixed palette for every event.

## 4. Typography

| Role | Family | Weight | Notes |
| --- | --- | --- | --- |
| Display, headings, numbers, stat values | **Baloo 2** | 600–800 | Never for paragraphs or form fields. Letter-spacing −2% from 32px up. |
| Body, UI, labels, buttons, tables | **Poppins** | 400–700 | Buttons 600. |
| Code, tokens, handles | system mono | — | `ui-monospace, Consolas, Menlo, monospace` |

Scale (px; fluid on the web, fixed `sp` in the app):
Display 56 (landing only) · H1 32 · H2 24 · H3 20 · H4 18 · Lead 18 · Body 16 · Small 14 · Kicker 12 (uppercase, +14% tracking) · Badge 12 (minimum size anywhere).

- Sentence case for headings. Uppercase only for kickers and badges.
- Highlight **one** phrase per headline: maroon on paper, ochre on ink.
- Lines ≤ 66 characters. Hindi uses the same families (Devanagari subsets ship).
- **Camp Card exception:** the Camp Card is a printed badge object and keeps its own serif + mono accents (`$font-serif`, `$font-mono`) inside `components/_camp-card.scss` only.

## 5. Space, shape, elevation, motion

- **Space:** 4px scale — 4, 8, 12, 16, 24, 32, 40, 48, 64, 80, 96. (12 is allowed for dense app rows; no other half steps.)
- **Radius grows with the surface:** chips 6 · inputs 10 · rows/list items 14 · cards 20 · sheets/modals/players 28 · hero stages 40 · buttons, pills, avatars round.
- **Shadows:** ink-tinted (`rgba(15,23,42,…)`), two layers, `--shadow-sm … xl`. Never black, never coloured except the ochre glow on a primary orb.
- **Motif:** smooth sine waves, a dot grid (2px dots every 26px, ≈12%), a soft ochre/maroon glow. A kicker always starts with a short wave. No zig-zags or squiggles.
- **Motion:** slow and ambient (drift 9–18s), UI transitions 150–250ms. Infinite animations only behind `prefers-reduced-motion: no-preference`; reduced motion freezes everything (native: respect the system "remove animations").
- **Effects (after WPSimplified's site, `components/_effects.scss`, admin `app.css`):**
  - *Heroes:* ochre glow top right + maroon glow bottom left, a dot grid on the right half, a soft wave swell along the foot.
  - *Section rail:* between Home's sections, a short wave (maroon → ochre → maroon, fading out both ways) drifting sideways, with a pulsing node in the middle.
  - *Titles:* a short wave under every section title; a kicker starts with a wave.
  - *Buttons:* primary = maroon → wine gradient pill with a warm lift shadow; on hover it lifts 2px, gains an ochre glow and a white sheen sweeps across once (0.8s); pressing settles it back. Outline buttons turn maroon on hover.
  - *Cards that open something:* lift 4px, `--shadow-lg`, maroon-tinted border on hover.
  - *Top bar:* frosted (translucent paper + 12px blur).
  - *Scroll reveal:* sections that start below the fold fade up as they scroll in (`reveal.js`); content in view on load is never hidden.
  - *Native:* gradient primary button with a wine lift, ink-tinted card shadows, the hero glow, and the short wave under section titles.

## 6. Voice & tone

Write like a friend who has been to WordCamp before.

| Principle | Say | Avoid |
| --- | --- | --- |
| Plain | "Tap a session to add it to My Day." | "Leverage our scheduling capabilities." |
| Friendly — one person, "you" | "Your first WordCamp? Start here." | "Users can access onboarding." |
| Calm | "A free app for WordCamp attendees." | "A revolutionary, game-changing experience!!!" |
| Correct | WordPress · WordCamp · CampBuddy · WooCommerce | Wordpress · Wordcamp · Campbuddy |

- Banned words: revolutionary, game-changer, seamless, unlock, elevate, supercharge.
- No emoji as headings, no exclamation-mark spam.
- Hindi/Hinglish: WordCamp is masculine ("pehla WordCamp", "WordCamp ka"). Event promos follow the event site's own tone.
- **Visual changes and copy changes are separate passes.** A copy pass never touches visuals, and vice versa.

## 7. Icons & UI

- Line icons on a 24 grid, stroke 1.75 (2.25 below 16px, 1.5 above 32px), round caps and joins, `currentColor`. No icon fonts, no emoji as icons.
- Brand marks (WhatsApp, LinkedIn, X…) only for links to that platform.
- Everything tappable is at least **44 × 44px** (48dp in the native app).
- Focus ring: a 2px solid maroon outline (offset 2px, 3:1 on paper) with a 3px maroon halo at 32% (`--shadow-focus`). Never removed. Hover always has a `:focus-visible` twin.
- **Attendee app:** top bar (logo left, pill shortcuts right), bottom tab bar with a soft pill behind the active tab, cards on paper.
- **Admin / manager panel:** same tokens; paper ground, white cards (radius 20), pill buttons, maroon primary, danger red for deletes, Baloo 2 page titles and stat numbers, Poppins tables.

## 8. Social posts & people cards

Made in Social media (admin / manager / native app). **Daylight** is the
default design; the older designs stay selectable but follow §8 rules 4–7.

| Kind | Size |
| --- | --- |
| Square post | 1080 × 1080 |
| Portrait post | 1080 × 1350 |
| Story / Reel cover | 1080 × 1920 |
| Open Graph | 1200 × 630 |

1. **Colours are the event's own** (from its site) when they pass 4.5:1 on their ground; otherwise fall back to the CampBuddy palette. Bases: Paper (type-led), Ink (people, place photo), Gradient (save the date, countdown).
2. **Type:** headline in the event site's heading font when we have it, else Baloo 2 800; every other line (kicker, subtitle, names, foot, brand bar) in Poppins.
3. One **brand bar** at the foot: the CampBuddy × WPSimplified lockup on a paper pill left, globe + `campbuddy.club` pill right, same height, one baseline. Never covered. The event's logo sits at the top of every card.
4. Headline: one highlighted phrase (1–3 words). ≤ 3 lines. Text ≥ 24px on a 1080 canvas; no ellipsis — shrink, never cut.
5. Faces never overlap text; full names on one line (shrink, never split). **A name is always whole** (24 Oct 2026, owner): every card, post, reel and caption line says the full name — "Save Amit Singh's talk", never "Save Amit's talk"; where a grid is too narrow the name takes a second line, it is never cut.
6. Ochre/light accents are never text on paper.
7. **Export:** PNG master at 2×, stamped 600 DPI, maximum quality; JPEG only where a platform rejects the PNG size.
8. **No "Free · no sign-up · works offline" line** on any graphic or reel (22 Oct 2026). Captions may still say it.
9. **Never an empty card:** a post with little to say lists its points (schedule numbers, what you'll find, what's next) instead of leaving the middle blank.
10. **Fifteen posts, in the order an event is promoted**, each with a tentative day counted back from the event's first day (`SocialPlan`): Save the date (6 weeks before) → Venue (5) → Speakers (4) → Sponsors (3–4) → Schedule (3) → First WordCamp? (2–3) → Contributor Day (2) → Learn with WPSimplified (2) → Get CampBuddy (10 days) → Find your people (1 week) → Countdown (7, 3, 1 day) → Day schedule (each morning) → Session spotlight (30 min before a talk) → Share your moments (during) → Thank you (the day after). The studio shows the plan as a calendar; organizers can get a phone reminder on each day.

## 9. Reels & stories

- 1080 × 1920, 9:16, 30 fps; 20–45s (60s maximum). Current reels: 30s.
- **Safe zone** (Instagram Reels + YouTube Shorts + WhatsApp status): keep text and faces out of top 250px, bottom 450px, right 180px, left 64px → safe box x 64–900, y 250–1470.
- Structure: Hook 0–2s (event name / the result, big, no logo) → Context 2–6s → Value 6–25s (one idea per cut, cut every 2–3s) → Payoff + **one** CTA 25–30s (scan / open campbuddy.club).
- **Progress border** (22 Oct 2026, replaces the progress bar): a 16 px line runs round the whole frame — from the top-left corner, clockwise, back to it on the last frame — over a faint track; main colour on light grounds, the light colour on dark ones, its glow pulsing on the beat. The step pill ("2 OF 7 · Schedule") stays under the event's logo.
- **List rows** (23 Oct 2026): white cards, one mark column per list sized to its widest mark with 28 px on both sides; a time is set as the hour large with AM / PM small under it, never touching the card's edge; titles and the meta line shrink to fit, they never run into the right edge.
- Captions burned in: Poppins Bold 64–72px, max two lines, 3–6 words per line.
- **Branding on every frame** (22 Oct 2026, replaces "logo only on the cover"): the event's logo on a white tile at the top centre of the safe zone; the CampBuddy × WPSimplified lockup on a white pill at its foot centre; the words between the two, never under them.
- **The end card** carries no event logo: CampBuddy's mark at the top centre, WPSimplified's at the foot centre, and between them one call that fits the reel's topic ("Save their talks in CampBuddy", "See their deals in CampBuddy", "Find your table in CampBuddy"; otherwise "Plan <event> in CampBuddy"), the QR and `campbuddy.club`. It may hold up to four beats longer than the others (time to scan).
- **Five templates** — Daylight, Stage, Poster, Photo, Minimal — each with its own grounds, entrance (rise, pop, slide, type on), cut on the beat (cut, flash, wipe, zoom, slide), effects (glow, dots, waves, equaliser, sparks, bands, grid) and music (mood, lead, groove, pad).
- **Every reel tells only its own topic** (24 Oct 2026, owner): the organizers' reel shows the organizers, the sponsors' reel the sponsors, a talk's spotlight that talk — never another reel's speakers, sponsors, schedule or team. Structure: hook → the topic (people one by one with photo and whole name, then **all together**; a list one point per cut under a big 01, 02 …; a big count before a long list: "12 sponsors make it happen") → **one date-and-venue slide** (dates highlighted, venue, and a **"Send this to your WordCamp buddy"** pill — sends take a reel to new people) → the end card. No date slide where the post already is the date (Venue) or the event is over (Thank you); on Coming up the date comes first, then the countdown.
- **Every reel is unique:** neighbouring reels take different templates and music (key, tempo, chords, arpeggio from its place in the list), with their own picks inside their topic.
- Cuts land on beats; lines come in on half beats; the highlighted word, big numbers, QR and effects pulse on the beat. Minimum 20 s (`MIN_SECONDS`): a short reel holds its scenes a beat longer each, in turn.
- Everything is editable per reel in the studio (template, animation, effects, hook words, colours, mood, photos) with a live, looping preview.
- Export MP4 · H.264 · AAC 48 kHz · 8–12 Mbps. Music never louder than words.

## 10. Native apps (Android & iPhone)

- Same tokens as the web (Theme.kt defaults equal `_variables.scss`); the server theme can only send values that pass the contrast rules.
- Baloo 2 + Poppins bundled (TTF); the phone's system font is never used for UI.
- Tap targets 48dp; radius as §5 (cards 20, sheets 28, buttons pill).
- Status bar on paper: dark icons. Splash: paper ground, centred `logo.png`.
- App icon: `favicon.png` artwork (wine tile, cream circle) — maskable safe zone kept.

## 11. Before you finish — checklist

- [ ] Only CampBuddy logo files, on the right ground or a paper plate, centred.
- [ ] Every colour is a token (or equals one). Contrast passes in every state.
- [ ] Baloo 2 headings, Poppins text, sizes from the scale, one highlighted phrase.
- [ ] Spacing on the 4px scale; radius matches the surface; tap targets ≥ 44px / 48dp.
- [ ] Deletes are danger red, not maroon.
- [ ] Copy is plain, friendly, calm and spelled correctly (WordPress, WordCamp, CampBuddy).
- [ ] Social/reels follow §8/§9; exports at 2×, 600 DPI.
- [ ] `npm run brand` and the test suite pass.
