# 26 — Brand guideline: how it is wired

The rules themselves are in [`CAMPBUDDY-BRAND-GUIDELINES.md`](../brand/CAMPBUDDY-BRAND-GUIDELINES.md)
in this skill's `brand/` folder (written 22 Oct 2026, modelled on WPSimplified's "Daylight" guideline in
`../brand/WPSIMPLIFIED-BRAND-GUIDELINES.md`, but in CampBuddy's own maroon, logo and name). This page says where each
rule lives in the code, so a change lands everywhere at once.

Owner decisions (22 Oct 2026, confirmed twice): keep CampBuddy maroon `#C33A19` as the one brand
voice (logo wine `#721313` as gradient partner, gold/ochre `#E39D1C` light only); Baloo 2 + Poppins
everywhere; the guideline's radius scale (cards 20, buttons pills); roll out to admin, manager,
attendee site/PWA, Android, iOS, social posts and reels. Camp Card keeps its own badge look.

## Single source and mirrors

| Layer | File | What it holds |
| --- | --- | --- |
| Source | `resources/scss/abstracts/_variables.scss` | Colours, `$font-sans` (Poppins), `$font-display` (Baloo 2), radius scale (`$radius-chip/input/row/card/sheet/stage`; old `sm/md/lg` are aliases = 10/14/20), `$shadow-focus`, Camp Card's own `$cc-radius-*` |
| Attendee CSS vars | `resources/scss/base/_root.scss` | `--wine`, `--subtle`, `--gradient-brand`, `--font-display`, `--shadow-focus` added |
| Fonts (web) | `resources/fonts/*.woff2` + `resources/css/fonts.css` | Self-hosted @font-face, Latin / Latin Ext / Devanagari subsets; its own Vite entry, added first to every `@vite([...])` (attendee, admin, manager, guest, legal, guide, memories, welcome). Only Camp Card's Playfair + JetBrains Mono still come from Google Fonts (attendee layout). |
| Headings (web) | `base/_global.scss` (h1–h3), `base/_typography.scss` (title/number classes) | Baloo 2; list-row names stay Poppins |
| Admin / manager | `tailwind.config.js`, `resources/css/app.css` | `font-sans` Poppins, `font-display` Baloo 2 (h1–h3, `.cb-display`, stat values), radius scale overrides (md 10, lg 14, xl 20, 2xl 28, 3xl 40), `.cb-btn` pill; app tab bar uses theme colours |
| Canvas | `resources/js/brand.js` | `BRAND` hexes, `FONT_TEXT`, `FONT_DISPLAY`, `loadBrandFonts()` — used by `social-kit.js`, `social-reels.js`, `usage-post.js` |
| Native | `native-app/.../ui/Theme.kt` | `poppinsFamily()`, `balooFamily()` (headlineSmall/titleLarge in Baloo), `Radii` (chip 6, input 10, row 14, sheet 28, pill); buttons are pills; small fixed corners use `Radii.input` |
| Native fonts | `composeResources/font/poppins_*.ttf`, `baloo2_*.ttf` | Built from the web woff2 subsets with fontTools (merged Latin + Latin Ext + Devanagari; Baloo instanced at 600/700/800). Inter removed. Licence: `native-app/FONTS-OFL.txt` |
| App theme defaults | `app/Support/MobileAppDefaults.php`, `ConfigModels.kt` → `DefaultConfigJson.kt` (`php artisan campbuddy:app-config-defaults`) | Same colours; `radius` 20 (was 5). A saved config in Admin → Mobile app keeps its own radius until changed there. |

## Effects (22 Oct 2026, owner confirmed)

After the WPSimplified site's motion and backgrounds, in CampBuddy's colours:
`resources/scss/components/_effects.scss` (attendee: hero glow + dots + wave foot, section rail with node,
wave under section titles, kicker wave, gradient buttons with lift + sheen, card hover lift, frosted top bar,
`.reveal`), `resources/js/attendee/reveal.js` (marks only sections below the fold; shows anything in view or
already passed), admin `resources/css/app.css` (`.cb-btn` sheen + lift, gradient `.cb-btn-primary`,
`.cb-event-card` lift, `.cb-hero` dots + wave), native `Components.kt` (`PrimaryButton` gradient + wine shadow,
`Card` ink shadow, `SectionTitle` + `WaveLine`, `Modifier.brandGlow` on the Home hero) and the reel progress bar
(`progressBar()` in `social-reels.js`). New tokens in `_variables.scss`: `$shadow-sm…xl`, `$shadow-glow-*`,
`$color-glow*`, durations/eases, `$art-wave-line`, `$art-wave-soft`, `$art-dots`, `$space-10/12/16`.

## Brand guidelines page

`/brand-guidelines` (route `brand-guidelines`, public, stateless group; `resources/views/brand/guidelines.blade.php`,
styles `components/_brand-page.scss`, data `app/Support/BrandTokens.php`): brand, logo (grounds, clear space,
don'ts), colour (share bar, swatches, live contrast ratios), typography (specimens, scale), space and shape,
motif and motion, voice, icons and buttons (hover them), social posts, reels (safe zone, beats), apps, checklist.
`npm run brand` also checks the page's hexes; `EveryPageAndAccessTest` checks it returns 200.

## Checks

- `npm run brand` (`tools/brand-check.mjs`, also the first row of `npm run check:sync`): every
  mirror equals `_variables.scss`; Theme.kt has no Inter; the native font files exist; no view loads
  Inter/Poppins/Baloo from Google Fonts; gold is never used as text in admin CSS.

## Not changed on purpose

- Camp Card (web `_camp-card*.scss`, native `CampCardFaces.kt`): keeps its sharp 4–5px corners,
  serif and mono. Only its sans text follows Poppins (it already used `$font-sans`).
- Social post *colours* still come from the event's site (contrast-checked); only fonts and the
  reel safe zone changed.
- Copy: no wording was touched (visual and copy passes stay separate).

## Studio, fifteen posts, posting plan, reminders, reel templates (22 Oct 2026, owner confirmed)

- **Studio** (`partials/social-media.blade.php`, `resources/js/social-studio.js`): a full-screen dialog with tabs
  Posts / People cards / Reels — list left, big preview centre, edits right; `#studio-posts|people|reels` opens it.
  Per-item edits (text, own colours, artwork set or none, Daylight effects; reels: template, animation, effects,
  hook, colours, mood, photos) are kept in the browser (`localStorage` key `cb-social:<slug>:…`). Reels play a
  silent live loop that rebuilds 350 ms after an edit; "Play with music" plays once with sound.
- **Fifteen posts** (`SocialKit::posts`, promotion order, each with `when`, `date`, `date_label`): four new —
  `venue`, `first-timer`, `wpsimplified`, `moments` — and `schedule`, `meet`, `thank-you` now list points
  (`body: points` → drawn as features). "Download all posts (ZIP)" saves every card + `captions.txt` +
  `posting-plan.txt`.
- **Posting plan** (`App\Support\SocialPlan`): days counted back from the event's first day; an event added late
  spreads its pre-event posts evenly from the day it was added (`created_at`), so the plan never moves with
  today. `SocialPlan::calendar()` draws it as a Monday-first calendar on the Social media page.
- **Phone reminders**: each admin / manager turns on "Remind me on my phone when a post is due" for themselves
  (`users.social_reminders`, `event_managers.social_reminders`; routes `admin.social.reminders`,
  `manager.social.reminders`). `campbuddy:post-reminders` runs hourly and sends in the venue's morning (9–11),
  once per post and day (cache key `post-reminder:<event>:<key>:<date>`), through `NativePush::toOrganizers(...,
  socialReminders: true)` to the CampBuddy app signed in as an organizer. `--dry-run` lists what is due.
- **Reel templates** (`REEL_TEMPLATES` in `social-reels.js`): Daylight, Stage, Poster, Photo, Minimal; branding on
  every frame (`v2Brand`: event logo top centre, CampBuddy × WPSimplified lockup at the foot); end card
  (`v2End`): CampBuddy top, WPSimplified foot, QR between, no event logo. Facts per reel: a three-fact slice of the
  event's pool (`v2EventFacts`, rotated by the reel's place), never repeating the reel's own topic.
- **Lockup** `brandLockup()` (`social-kit.js`): CampBuddy × WPSimplified for every post, people card and reel.
- **Event Details page** (`admin/events/edit.blade.php`): tabs Basics / Event info / Data & refresh / Branding
  (Alpine; kept per event in `sessionStorage`, `#basics|info|data|branding`, a form with errors opens its tab).

## Readability rules (23 Oct 2026, full visual QA)

Checked by `npm run test:text` (web, 7 widths), `tools/audit-social.mjs`, `tools/audit-reels.mjs` and emulator
screenshots (411 dp, 360 dp, 1.3× system text) — see spec/15 §15.6.

- **Smallest type**: web 12 px for any label (admin `text-[11px]` → `text-xs`), the attendee tab bar 11 px (one
  line from 320 px phones; columns follow the label, `flex: 1 1 0`); native 11 sp for tab labels and badges.
  Posts and people cards 24 px, reels 34 px (step pill and kicker included).
- **Two columns → one when cramped**: My Day session rows put the time above the title under 480 px (web) /
  480 dp (native); the Explore roster stacks + Meet under the name under 360 px; Home/Memories hero logo 76 px
  under 420 px; the deal form's field table becomes one block per field below `md`; tables an admin writes on
  the legal pages stack like `.legal-table` under 680 px.
- **Never cut a word**: native tab bars and Explore tiles use `FitTabsRow` (a tab keeps the width its label —
  or its longest word — needs; very large system text wraps and the bar grows); Camp Card's Share / Download
  shrink a step instead of breaking; banner kicker no longer squeezed by a second weighted spacer.
- **Dates**: one short range everywhere an event is named in a card ("21–22 Nov 2026", `SocialKit::dates`,
  native `heroDates`).
- **Social**: QR band footer 46 px off the edge and drops hashtag, then dates, before shrinking; tile names take
  two lines rather than going under 24 px; spotlight people card role + talk on up to two lines (band grows).
- **Reels**: frame 0 already shows the event logo + lockup (no fade-in); a tinted highlight on the main colour
  only when it reaches 3:1, else white.
