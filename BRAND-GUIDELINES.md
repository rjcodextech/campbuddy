# WPSimplified Brand Guidelines — for AI agents

Read this before you design, write copy, build UI, or make a thumbnail, reel,
social image or document for WPSimplified. Follow it to the letter. If a
request conflicts with it, follow these rules and tell the user which rule
you applied.

The people-facing version of the same rules is the **Brand Guidelines** page
(`resources/views/page-templates/page-brand-guidelines.blade.php`, `/brand-guidelines/`)
and the FigCraft file `E:\Sites\InHouse\wpsimplified\design\WPSimplified-Design.figcraft`.
Code rules live in [`DESIGN-SYSTEM.md`](DESIGN-SYSTEM.md), [`STANDARDS.md`](STANDARDS.md)
and [`DESIGN-GUIDELINES.md`](DESIGN-GUIDELINES.md).

---

## 0. Sources of truth — never duplicate, never guess

| What | Single source | Rule |
| --- | --- | --- |
| Colours, type sizes, space, radii, shadows, motion | `resources/styles/abstracts/_tokens.scss` | Use the token. A new value goes into this file first, with a comment. |
| Logos | `resources/images/logo-dark.png`, `resources/images/logo-white.png` | The only two logo files. Nothing else. |
| Fonts | `resources/fonts/baloo2-*.woff2`, `resources/fonts/poppins-*.woff2` | Self-hosted. No Google Fonts, no other families. |
| Icons | `resources/icons/icons.json` → `wpsc_icon( 'name' )` | Only names in the manifest. No icon fonts. |
| Thumbnails | wpsbs Thumbnail Studio (`plugins/wpsbs/assets/js/thumbnail-studio.js`) and FigCraft (`design/`) | Use the templates; do not invent a new layout. |

Hard-coding a colour outside `_tokens.scss` is allowed only where tokens
cannot reach (Thumbnail Studio SVG, FigCraft builder) — and there the value
**must equal** the token. `yarn brand` fails the build otherwise.

## 1. Brand essence

- **Name:** WPSimplified (one word, capital W, P and S). The logo reads "WP SIMPLIFIED".
- **Tagline:** *Simplifying WordPress, One Step at a Time.*
- **Who:** Sunil Kumar Sharma's home for learning WordPress — video tutorials, podcasts, shorts, community events (WordCamps, meetups, Campus Connect) and training.
- **Audience:** beginners, students, freelancers and site owners, mostly in India, mostly on phones. Hindi and English.
- **Promise:** clear, calm, practical. Every piece of content leaves the reader able to do one more thing in WordPress than before.

## 2. Logo

| Ground | File | Notes |
| --- | --- | --- |
| Light (paper, white, light photos) | `logo-dark.png` | Ink wordmark, gradient WP mark. Default. |
| Dark (ink bands, video stage, dark thumbnails, the brand gradient) | `logo-white.png` | White wordmark, gradient WP mark. |

- **Clear space:** x ≈ 28% of the logo height on every side. Nothing enters it.
- **Minimum size:** 120px wide on screen. Site header: 32px tall (≈ 90px wide). Thumbnails: ≈ 6% of the short edge, inside the brand bar.
- **Plates:** when the logo sits on a pill or circle plate, centre it on both axes with equal padding (Thumbnail Studio: padding = 0.35 × plate height on each side).
- **Never:** stretch, squash, rotate, recolour, outline, add shadows/glows, redraw or retype the wordmark, put the dark logo on a dark ground, or place it on a busy image without a plate.

## 3. Colour — v2 "Daylight"

Paper ground, ink text, **one** emerald brand voice. Approximate share of a page:
paper 56% · ink 20% · emerald 12% · teal 5% · mint 4% · media voices 3%.

| Token | Hex | Use |
| --- | --- | --- |
| `--color-bg` / `paper` | `#F6F8F4` | Page ground |
| `--color-surface-card` | `#FFFFFF` | Cards |
| `--color-text` / `ink` | `#0B1F1A` | Text, deep bands |
| `--color-text-muted` | `#43544E` | Secondary copy |
| `--color-text-subtle` | `#5F6F69` | Meta, captions |
| `--color-primary` | `#047857` | Brand, links, primary fills (white label) |
| `--color-teal` | `#0E7490` | Gradient partner: `--gradient-brand` = emerald → teal |
| `--color-mint` | `#04D98B` | **Light only** — glow, waves, highlights on ink. Never text on paper (1.9:1). |
| `--color-video` | `#047857` | Videos |
| `--color-podcast` | `#7C3AED` | Podcasts |
| `--color-short` | `#C81A40` | Shorts |
| `--color-error` | `#CA2323` | Errors, LIVE badges |

- Every text colour must reach **4.5:1** on its background (3:1 for text ≥ 24px and for icons). Check states too (hover, focus, active).
- Badges: `*-soft` background + the role colour.
- Dark bands use `.u-theme-dark`; inside it `--color-primary` becomes mint and text becomes white.
- **Thumbnail palettes** (Studio and FigCraft), each tied to a token: Emerald = primary · Violet = podcast · Rose = short · Ocean = cat-blue `#245FE2` · Sunset = cat-orange `#C2410C` · Ink mono = ink. Default per kind: tutorial/live/post/page Emerald, podcast Violet, short Rose, event Ocean.

## 4. Typography

| Role | Family | Weight | Notes |
| --- | --- | --- | --- |
| Display, headings, numerals | **Baloo 2** | 600–800 | Never for paragraphs. Letter-spacing −2% on big sizes. |
| Body, UI, labels, captions | **Poppins** | 400–700 | Buttons SemiBold 600. |
| Code, token names | system mono | — | `ui-monospace, Consolas…` |

Scale (desktop end of each fluid clamp): Display 112 · H1 68 · H2 56 · H3 48 · H4 36 · H5 30 · H6 24 · Lead 20 · Body 16 · Small 14 · Kicker 13 (uppercase, +14%) · Badge 12 (minimum size).

- Sentence case for headings. Uppercase only for kickers and badges.
- Highlight **one** phrase per headline: emerald on paper, mint on ink.
- Lines ≤ 66 characters. Hindi uses the same families (Devanagari subsets ship with the theme).

## 5. Space, shape, elevation, motion

- **Space:** 4px scale only — 4, 8, 16, 24, 32, 40, 48, 64, 80, 96 (`--space-1 … 24`). No half steps. Section rhythm: `--space-section-sm/md/lg`.
- **Radius grows with the surface:** chips 6 · inputs 10 · rows 14 · cards 20 · players 28 · stages 40 · buttons and chips are pills.
- **Shadows:** ink-tinted, two layers (`--shadow-sm … xl`). Never black, never coloured except the mint glow on play orbs.
- **Motif:** sine waves (`--art-wave-line`, `wave-fill`, `wave-soft`), dot texture (`--art-dots`), soft mint glow. Waves are smooth sine strokes — never zig-zags or hand-drawn squiggles. A kicker always starts with a short wave.
- **Motion:** slow and ambient (drift 9–18s). Infinite animations only inside `mixins.motion-safe`; reduced motion freezes everything.

## 6. Voice & tone

Write like a helpful WordPress friend.

| Principle | Say | Avoid |
| --- | --- | --- |
| Plain | "Open Settings → Permalinks and pick 'Post name'." | "Leverage optimal permalink structures for SEO synergy." |
| Friendly — talk to one person ("you") | "You'll have your first block theme running in 20 minutes." | "Users are enabled to deploy themes rapidly." |
| Calm | "A free app for WordCamp attendees." | "A revolutionary, game-changing experience!!!" |
| Correct | WordPress · WordCamp · WooCommerce | Wordpress · Wordcamp · woocommerce |

Banned words: revolutionary, game-changer, seamless, unlock, elevate, supercharge. No emoji as headings. No exclamation-mark spam.

## 7. Icons & UI

- Line icons on a 24 grid, stroke 1.75 (2.25 below 16px, 1.5 above 32px), round caps and joins.
- Everything tappable is at least **44 × 44px**.
- Focus ring: 3px emerald at 32% (`--shadow-focus`). Never remove it.
- Hover has a keyboard twin (`:focus-visible` / `:focus-within`).
- Header: floating frosted capsule. Mobile: logo left, round subscribe bell + ink "Menu" pill right; the menu is a full-screen paper sheet. Desktop: pill link bar, ink pill for the current page.

### 7.1 Icon library

Use only these icons. Render with `wpsc_icon( 'name' )` (PHP/Blade) or `icon( 'name' )` (theme JS);
an alias works too and resolves to the canonical name. Line icons come from Lucide (stroke icons,
weight set by `--icon-stroke`); brand marks are filled platform logos — use them only for links to
that platform, never as decoration. Need a new icon? Add it to `resources/icons/icons.json` first
(`lucide:<name>`, `simple:<name>` or `custom:<name>`), then list it here — `yarn brand` fails if
this list and the manifest disagree. The Brand Guidelines page shows the same library live, with
usage computed from the code (`wpsc_icon_usage()`).

<!-- icon-library:start -->
97 icons (88 line icons + 9 brand marks); **64 in use** (●), the rest approved and available (○).

**Media & content**

| Icon | Aliases | Status |
| --- | --- | --- |
| `play` | — | ● in use |
| `circle-play` | — | ○ available |
| `pause` | — | ○ available |
| `video` | — | ● in use |
| `clapperboard` | — | ● in use |
| `mic` | `microphone`, `microphone-lines` | ● in use |
| `radio` | `tower-broadcast` | ○ available |
| `tv` | — | ● in use |
| `monitor` | `display` | ● in use |
| `camera` | `camera-retro` | ○ available |
| `image` | — | ● in use |
| `newspaper` | — | ● in use |
| `book` | — | ○ available |
| `book-open` | — | ○ available |
| `file-slides` | `file-powerpoint` | ○ available |
| `presentation` | `chalkboard-user` | ○ available |
| `list` | — | ● in use |
| `list-ordered` | `list-ol` | ● in use |
| `align-left` | — | ● in use |

**Navigation & actions**

| Icon | Aliases | Status |
| --- | --- | --- |
| `arrow-right` | — | ● in use |
| `arrow-up-right` | — | ● in use |
| `chevron-down` | — | ● in use |
| `chevron-left` | — | ● in use |
| `chevron-right` | — | ● in use |
| `external-link` | `arrow-up-right-from-square`, `external-link-alt` | ● in use |
| `link` | — | ● in use |
| `share` | `share-nodes` | ● in use |
| `search` | `magnifying-glass` | ● in use |
| `menu` | — | ○ available |
| `x` | — | ● in use |
| `check` | — | ○ available |
| `house` | — | ● in use |
| `compass` | — | ● in use |
| `globe` | — | ● in use |

**Learning & building**

| Icon | Aliases | Status |
| --- | --- | --- |
| `graduation-cap` | — | ○ available |
| `user-graduate` | — | ○ available |
| `code` | — | ○ available |
| `laptop-code` | — | ○ available |
| `terminal` | — | ○ available |
| `keyboard` | — | ● in use |
| `lightbulb` | — | ● in use |
| `flask` | — | ● in use |
| `pen` | — | ● in use |
| `pen-tool` | `pen-nib` | ○ available |
| `toolbox` | — | ● in use |
| `plug` | — | ● in use |
| `network` | `sitemap` | ● in use |
| `rocket` | — | ● in use |
| `gauge` | — | ● in use |
| `chart` | — | ○ available |
| `trending-up` | — | ○ available |
| `sprout` | `seedling` | ● in use |

**Events, time & places**

| Icon | Aliases | Status |
| --- | --- | --- |
| `calendar` | — | ● in use |
| `calendar-check` | — | ● in use |
| `calendar-days` | `calendar-alt` | ● in use |
| `clock` | — | ● in use |
| `timer` | `stopwatch` | ● in use |
| `map-pin` | `location-dot` | ● in use |
| `ticket` | — | ● in use |
| `landmark` | `building-columns` | ○ available |
| `building` | — | ○ available |
| `bus` | — | ○ available |
| `train` | `train-subway` | ● in use |
| `truck` | `truck-front` | ● in use |
| `plane` | — | ○ available |
| `parking` | `square-parking` | ○ available |
| `coffee` | `mug-hot` | ○ available |

**People & community**

| Icon | Aliases | Status |
| --- | --- | --- |
| `user` | — | ● in use |
| `user-circle` | — | ● in use |
| `user-cog` | `user-gear` | ● in use |
| `users` | `people-group` | ● in use |
| `handshake` | — | ● in use |
| `hand-helping` | `hands-helping` | ○ available |
| `messages` | `comments` | ○ available |
| `mail` | `envelope` | ● in use |
| `bell` | — | ● in use |
| `accessibility` | `wheelchair` | ○ available |

**Status & recognition**

| Icon | Aliases | Status |
| --- | --- | --- |
| `award` | — | ● in use |
| `trophy` | — | ○ available |
| `crown` | — | ● in use |
| `star` | — | ● in use |
| `zap` | `bolt` | ● in use |
| `piggy-bank` | — | ● in use |
| `cart` | `cart-shopping` | ○ available |
| `tags` | — | ● in use |
| `lock-open` | `unlock` | ○ available |
| `info` | `circle-info` | ○ available |
| `loader` | `loader-2` | ● in use |

**Brand marks (filled)**

| Icon | Aliases | Status |
| --- | --- | --- |
| `brand-facebook` | `facebook`, `facebook-f` | ● in use |
| `brand-github` | `github` | ○ available |
| `brand-instagram` | `instagram` | ○ available |
| `brand-linkedin` | `linkedin`, `linkedin-in` | ● in use |
| `brand-slack` | `slack` | ● in use |
| `brand-whatsapp` | `whatsapp` | ● in use |
| `brand-wordpress` | `wordpress` | ● in use |
| `brand-x` | `x-twitter`, `twitter` | ● in use |
| `brand-youtube` | `youtube` | ● in use |
<!-- icon-library:end -->

**Outside the sprite** (same line style, stroke 1.75):

- wpsbs `WPS_Utils::icon()` inline set: `graduation-cap`, `mail`, `keyboard`, `check`, `close-sm` — plugin admin/front notices only; prefer `wpsc_icon()` in new code.
- CSS mask icons (`global/_icons.scss`, via `mixins.icon-mask()`): `--icon-check`, `--icon-chevron-right`, `--icon-chevron-down` — list ticks, link arrows, select carets.

## 8. Thumbnails

Make them with the **Thumbnail Studio** (WP Admin → WPSimplified → Thumbnail Studio) or **FigCraft** (`design/content.json` → `render.cmd` renders one entry into every template). Do not design thumbnails from scratch.

| Kind | Size | Default palette |
| --- | --- | --- |
| Tutorial video, Podcast, Live session | 1280 × 720 | Emerald / Violet / Emerald |
| Short / Reel cover | 1080 × 1920 | Rose |
| Community event, Blog post, Page (Open Graph) | 1200 × 630 | Ocean / Emerald / Emerald |

Rules every thumbnail follows:

1. One **brand bar** on the bottom edge — logo plate left (logo centred in it), `wpsimplified.in` pill right. Never covered.
2. Title in two parts: an ink (or white on dark) line and **one** highlighted line in the palette colour.
3. 3–6 words. The thumbnail is not the video title. Text ≥ 72px on a 1280-wide thumbnail.
4. Faces never overlap text. Photos bright, face looking towards the title.
5. Light shades (mint, the palette's light colour) are never text on paper.
6. **Export quality:** every file is stamped **600 DPI** at maximum encoder quality. Default 2× (2560 × 1440 for 1280 × 720). Use PNG for master files; 1× JPEG only if a platform rejects files over 2 MB (YouTube's thumbnail limit).

## 9. Reels & Shorts

- Canvas 1080 × 1920, 9:16, 30 fps (60 for screen recordings). 20–45s; 60s hard maximum.
- **Safe zone** (combined Instagram Reels + YouTube Shorts): keep text and faces out of top 250px, bottom 450px, right 180px, left 64px → safe area 836 × 1220.
- Structure (30s): Hook 0–2s (result or problem, big text, no logo) → Context 2–6s → Value 6–25s (one idea per cut, cut every 2–3s, step counter top-left) → Payoff + one CTA 25–30s.
- Captions always on and burned in: Poppins Bold 64–72px, max two lines, 3–6 words per line.
- Audio: voice −14 LUFS; music ducked to −28 LUFS under speech.
- Logo only on the cover and the end card. Cover = the "Short / Reel cover" thumbnail; its centre square must read alone (Instagram grid crop).
- Export MP4 · H.264 · AAC 48 kHz · 8–12 Mbps.

## 10. Before you finish — checklist

- [ ] Only `logo-dark.png` / `logo-white.png`, on the right ground, centred in any plate.
- [ ] Every colour is a token (or equals one, outside the theme). Contrast passes in every state.
- [ ] Baloo 2 headings, Poppins text, sizes from the scale, one highlighted phrase.
- [ ] Spacing on the 4px scale; radius matches the surface; tap targets ≥ 44px.
- [ ] Copy is plain, friendly, calm and spelled correctly (WordPress, WordCamp).
- [ ] Thumbnails/reels follow §8/§9 and export at 600 DPI.
- [ ] Theme work: `yarn run check` passes (includes `yarn brand` and `yarn guidelines`).

## 11. Commands

| Command | What it checks |
| --- | --- |
| `yarn brand` | Logos (only the two files), palettes and fonts across theme, wpsbs plugin and FigCraft builder. |
| `yarn guidelines` | Contrast, mint-as-text, states, motion, markup (DESIGN-GUIDELINES.md). |
| `yarn run check` | Every gate, including the two above. `yarn build` runs it first. |
| `design\render.cmd` | Renders `design/content.json` into every FigCraft template → `design/output/<slug>/` (2×, 600 DPI). |
| `design\build.cmd` | Rebuilds the FigCraft design file from `design/scripts/`. |
