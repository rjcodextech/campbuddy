# 26 — Brand guideline: how it is wired

The rules themselves are in [`CAMPBUDDY-BRAND-GUIDELINES.md`](../../../../CAMPBUDDY-BRAND-GUIDELINES.md)
at the repo root (written 22 Oct 2026, modelled on WPSimplified's "Daylight" guideline in
`BRAND-GUIDELINES.md`, but in CampBuddy's own maroon, logo and name). This page says where each
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
