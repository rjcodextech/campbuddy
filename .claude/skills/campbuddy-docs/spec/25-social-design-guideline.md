# 25 — Social posts and reels: design guideline

Written 20 Oct 2026 from an analysis of 11 community Figma files the owner
collected in `E:\Sites\InHouse\campbuddy\marketing\Social` (Instagram post and
carousel kits, LinkedIn carousels, an event collection, three Instagram/Reels UI
mock-ups). The files were decoded (fig-kiwi → node tree) and 290 artboards were
measured: size, margins, type sizes, line height, colours, where the headline
sits. The numbers below are those measurements scaled to a 1080 px wide card.

Everything CampBuddy draws for social media follows this guideline:
`resources/js/social-kit.js` (event posts, people cards), `resources/js/social-reels.js`
(reels) and `resources/js/usage-post.js` (the "CampBuddy at {WordCamp}" post).

## What the templates have in common

| Measure (at 1080 px wide) | Templates (median, p25–p75) | Rule for CampBuddy |
|---|---|---|
| Feed format | 4:5 in 3 of 6 post kits, 1:1 in the rest | 1080 × 1350 default; 1080 × 1080 and 1080 × 1920 offered |
| Side margin | 80–100 px (p25 75, p75 120) | **80 px** minimum for any word, QR or logo |
| Headline size | 80–90 px (p25 64–72, p75 110–120) | 4:5: 84–104 px; 1:1: 64–84 px; 9:16: 96–120 px |
| Headline length | 3 words (p75 7), 2 lines | ≤ 3 lines; shrink before a 4th line |
| Headline line height | 1.09 (1.0–1.22) | 1.04–1.08 |
| Headline position | top edge at 21 % of the height (15–32 %) | anchored under the header, the same place on every card of a post; never floated to the middle |
| Alignment | left on 62 %, centred on 33 % | left for posts; centred only for a single big number or a name under a photo |
| Second line (sub) | 32–40 px | 32–40 px, 70–80 % strength of the text colour |
| Chrome (handle, label, page no.) | 28–36 px, labels uppercase, tracked 3–6 % | 24–30 px, uppercase kicker tracked 6 %; **nothing on a card under 24 px** |
| Text blocks per card | 3 | kicker + headline + one line above the footer, plus the body (list, faces, logos) |
| Colours per card | 2 + tints | the event's brand colours only (main, accent, text, light) and mixes of them |

## Layout rules

1. **One message per card.** A post that needs more is split over several cards
   (1/3, 2/3…), each with the same header and footer.
2. **Fixed grid.** Header row (event logo left, kicker pill right), headline
   anchored under it, body, footer block. The headline starts at the same y on
   every card of a post, so a carousel reads as one piece.
3. **Art never under words.** Decoration (the site's skyline, pattern, a shape)
   bleeds off an edge or corner and stays out of the text column; where it must
   sit behind a text area it is faint (≤ 10 %), and words are placed above the
   skyline, not on it.
4. **Pictures in their own box** (rounded 24–30 px, or full-bleed), words below,
   never on a face.
5. **Footer is the CTA**: the reason to scan (1–2 lines, 34–40 px), the event
   line (24–28 px), the QR with CampBuddy's mark. About 20–24 % of the height.
6. **Carousel cue**: the first card of a post with more than one card says
   "Swipe" next to its page count.
7. **Contrast**: text on light ≥ 7:1, light on main ≥ 4.5:1 (already enforced by `readable()`).

## Reels (1080 × 1920)

Measured from the three Reels/Instagram UI mock-ups (`Instagram Reels (Mockup +
Prototype)`, `Instagram UI Mockup 2.0`, `Instgram_Profile_Story_Reel_UI Design`):

- **Safe zone**: Instagram's own UI covers the top ~12 % (status bar, "Reels",
  camera), the bottom ~20 % (name, caption, audio, nav) and a right rail of
  ~14 % width (like, comment, share, from 50 % to 92 % height). Keep every word,
  QR and logo inside **x 64–900, y 250–1470** (22 Oct 2026: the combined
  Instagram Reels + YouTube Shorts zone of the brand guideline §9; was
  x 80–920, y 220–1500). Backgrounds, art and particles may fill the whole frame.
- **Grid crop**: the profile grid shows the middle 3:4 (y 240–1680); the intro
  puts the event's name inside it.
- **First frame is not empty**: frame 0 already shows the logo and the event's
  name (it is the default cover and what people see while it loads).
- **Type**: titles 96–124 px, lines 44–56 px, nothing under 34 px.
- **Pacing**: scenes on whole beats, 2–4 s each; text on screen at least
  1 s + 0.3 s per word.
- **End card**: the QR (≥ 480 px), "Scan, or open campbuddy.club" and the
  WPSimplified credit, all inside the safe zone.

## Artwork from the WordCamp site

The designs lean on the site's own artwork (skyline, pattern, illustration,
photo of the place), read by `App\Services\SiteThemeScraper` / `App\Support\SiteTheme`
on the first visit and on **Read the site again** (never on a schedule). Since
20 Oct 2026 a reading also:

- reads the site's own stylesheet — WordCamp's Remote CSS or the custom CSS
  (`stylesheets()`, same host only, ≤ 1 MB) — for colours, fonts and background art;
- takes each `<img>`'s real size from its srcset (widest copy) or width/height;
- searches the media library for `skyline`, `pattern`, `illustration`, `motif`
  (art uploaded before the newest 200 pictures);
- opens the first 64 KB of up to 8 pieces (`probe()`, one parallel pool) for
  their real size and whether they are cut-outs: a transparent piece ranks
  higher and is never a photo; a solid PNG with no art words ranks lower;
- knows regional crafts (rangoli, kolam, alpana, paisley, ajrak, ikat,
  block print, kalamkari, phulkari, arabesque → pattern; Madhubani, Warli,
  frieze, monument → illustration);
- skips website banners and exported PDF pages, ranks background-remover
  previews (`…-removebg-preview`) and last edition's files lower, and counts a
  preview and its full file (`_` or `-`) as one piece.

Extra requests per reading: 1 stylesheet + 4 searches + up to 8 probes.
See [09-admin-panel.md](09-admin-panel.md) → Social media for the admin side.

## Daylight (21 Oct 2026, owner confirmed twice) — the default design

From the owner's thumbnail guideline (only its rules; no WPSimplified name, logo or palette).
`Daylight` is the first and default post and people-card design in `resources/js/social-kit.js`
(`drawDaylightPost`, `drawDaylightPerson`); the older designs stay selectable.

- **Base**: Paper (off-white to white) for type-led posts; Ink (the main colour almost black)
  for people and the place's photo; Gradient (main to deep, 135°) for save-the-date and the
  countdown when white reads on the main colour. Colours are the event's own; every text colour
  is checked to 4.5:1 against its ground (`readableOn`).
- **Texture and light**: a dot grid (2 px dots every 26 px, 12 %) on the right half (all of it on
  dark), a soft glow top-right and bottom-left, four thin waves above the foot fading upward.
- **Words**: kicker UPPERCASE, tracked 0.16em, in the accent, after a small wave mark; headline in
  the site's heading font, 800, line height 1.04, ≤ 3 lines; 1–3 words highlighted (`*marked*` in
  the headline field, else the last word) in the main-to-deep gradient on Paper, the light accent
  on dark; subtitle muted.
- **People**: circles with a ring (white, or the light accent on dark), a soft shadow; one person =
  one hero portrait; two or three = cards tilted ±3°; four to eight = an even grid; the full name
  under each on one line (shrunk, never split, never cut) and the role muted. No photo = a gradient
  circle with initials. People per card: 3 square, 6 portrait, 8 story.
- **Place photo** (save the date, meet, thank you): full-bleed under a strong colour veil and a
  6 px blur; the words sit on it.
- **Foot, the same on every card**: a rounded card (34 px) with the reason to scan (≤ 2 lines),
  "Free · no sign-up · works offline", the event · dates, and the QR; below it the brand bar —
  two pills of one height on one baseline, CampBuddy's wordmark left, a globe and
  `campbuddy.club` right (light version on every base), nothing else in that strip.
- **No "…" anywhere** (all designs): `fitText` shrinks (to 60 % of its minimum) and then wraps;
  `ellipsize` now shrinks the font instead of cutting. People-card names are fitted on one line in
  every design.
- **Reels** (`social-reels.js`) with the Daylight design picked: every frame stands on the Daylight
  ground (paper to white, two drifting glows placed by the reel's look so reels still differ, the dot
  grid, four waves rolling at the foot); the intro's event name ends on a highlighted word; the end
  card washes in the 135° gradient and ends on the brand bar (CampBuddy · campbuddy.club pills)
  above the WPSimplified credit. Another design picked keeps the earlier reel look.
- **Native app** (`OrganizerSocialDaylight.kt`, 21 Oct 2026): the Social media tab draws Daylight too, as its
  default for posts and people cards (same bases, dot grid, glows, waves, wave-mark kicker, highlighted
  headline, ringed portraits with whole names via auto-size text, QR card and brand bar); Daylight people
  posts hold 3 / 6 / 8 faces like the website.

## Brand fonts (22 Oct 2026)

Posts, people cards, reels and the usage post follow [spec/26](26-brand-guideline.md): every
line in Poppins, headlines/names/big numbers in Baloo 2 800 (`resources/js/brand.js` →
`FONT_TEXT`, `FONT_DISPLAY`, `loadBrandFonts()` before the first draw), unless "Use the site's
fonts" is ticked and the WordCamp site's heading font loaded. The native Daylight posts
(`OrganizerSocialDaylight.kt` `ts()`) use the same pair. The reel "Ready in a minute" scene
shrinks its step height so the steps and the pill stay inside the new safe box.
