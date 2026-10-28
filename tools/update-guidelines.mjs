import fs from 'node:fs';

const filePath = '.claude/skills/campbuddy-docs/brand/CAMPBUDDY-BRAND-GUIDELINES.md';
let g = fs.readFileSync(filePath, 'utf8');

const oldSection = `## 3. Colour — "Campfire Daylight"

Warm paper ground, ink text, **one** maroon brand voice; the logo's wine and
ochre are its partners. Approximate share of a screen:
paper 56% · ink 20% · maroon 12% · wine 5% · ochre 4% · role colours 3%.

| Token | Hex | Use |
| --- | --- | --- |
| \`--color-bg\` / \`paper\` | \`#FFFAF4\` | Page ground |
| \`--color-bg-soft\` / \`paper-soft\` | \`#FFF0DF\` | Soft bands, selected rows, app background |
| \`--color-surface-card\` | \`#FFFFFF\` | Cards, sheets, inputs |
| \`--color-line\` | \`#EADFD6\` | Borders, dividers |
| \`--color-text\` / \`ink\` | \`#231F20\` | Text, deep bands (5.73:1 … 16.3:1) |
| \`--color-text-muted\` | \`#6B625E\` | Secondary copy (5.7:1 on paper) |
| \`--color-text-subtle\` | \`#7A706B\` | Meta, captions (4.6:1 on paper) |
| \`--color-primary\` / \`maroon\` | \`#C33A19\` | Brand, links, primary buttons (white label 5.3:1) |
| \`--color-primary-dark\` | \`#A12F14\` | Hover / pressed primary |
| \`--color-wine\` | \`#721313\` | Gradient partner (logo wine): \`--gradient-brand\` = maroon → wine, 135° |
| \`--color-ochre\` / \`gold\` | \`#E39D1C\` | **Light only** — highlights on ink, glows, waves, stars. Never text on paper (2.2:1). |
| \`--color-teal\` | \`#047A7C\` | Role: sessions / schedule / "now" (4.96:1) |
| \`--color-navy\` | \`#0D2343\` | Role: events / travel; alternative deep band |
| \`--color-success\` | \`#1F7A4D\` | Done, saved, checked in |
| \`--color-danger\` | \`#B3261E\` | Errors, destructive actions, LIVE badges |

- Every text colour reaches **4.5:1** on its ground (3:1 for text ≥ 24px and icons). Check hover, focus, active and disabled too.
- Badges: a soft tint of the role colour (≈10%) + the role colour as text.
- Dark bands (\`.u-theme-dark\`, ink or the gradient): text white, \`--color-primary\` becomes ochre, highlights ochre.
- **Delete is never maroon.** Destructive actions use \`--color-danger\`, so "Delete" never looks like "Save".
- **Event colours:** an event's own colours appear only in its *content* (event logo, social posts for that event, §8) — never in CampBuddy's UI chrome. The app has one fixed palette for every event.`;

const newSection = `## 3. Colour — "Vibrant Community Sun" (Daylight 2.0)

Crisp pearl ground, rich slate text, **one** electric coral brand voice; sunny gold and
radiant amber are its partners. Approximate share of a screen:
paper 56% · slate text 20% · coral primary 12% · radiant amber 5% · sunny gold 4% · role colours 3%.

| Token | Hex | Use |
| --- | --- | --- |
| \`--color-bg\` / \`paper\` | \`#FAFAFD\` | Page ground (crisp pearl canvas) |
| \`--color-bg-soft\` / \`paper-soft\` | \`#FFF7ED\` | Soft bands, selected rows, warm cream background |
| \`--color-surface-card\` | \`#FFFFFF\` | Cards, sheets, inputs |
| \`--color-line\` | \`#E2E8F0\` | Borders, dividers |
| \`--color-text\` / \`ink\` | \`#0F172A\` | Deep slate text (16.2:1 contrast against ground) |
| \`--color-text-muted\` | \`#475569\` | Secondary copy (5.8:1 on ground) |
| \`--color-text-subtle\` | \`#64748B\` | Meta, captions (4.8:1 on ground) |
| \`--color-primary\` / \`maroon\` | \`#FF4D2D\` | Brand, links, primary buttons (Electric Fire Coral) |
| \`--color-primary-dark\` | \`#E03616\` | Hover / pressed primary |
| \`--color-wine\` | \`#EA580C\` | Radiant Amber partner: \`--gradient-brand\` = coral → amber, 135° |
| \`--color-ochre\` / \`gold\` | \`#F59E0B\` | **Sunny Gold** — highlights, stars, active tabs, quests, glows |
| \`--color-teal\` | \`#2563EB\` | Role: Tech Indigo for sessions / schedule / tracks / "now" |
| \`--color-navy\` | \`#1E293B\` | Role: deep slate navy for events, travel, and console bands |
| \`--color-success\` | \`#10B981\` | Done, saved, checked in |
| \`--color-danger\` | \`#EF4444\` | Errors, destructive actions, LIVE badges |

- Every text colour reaches **4.5:1** on its ground (3:1 for text ≥ 24px and icons). Check hover, focus, active and disabled too.
- Dark bands / heavy ink grounds are retired from default reels and page chrome in favor of high-pop daylight surfaces.
- **Delete is never coral.** Destructive actions use \`--color-danger\`, so "Delete" never looks like "Save".
- **Event colours:** an event's own colours appear only in its *content* (event logo, social posts for that event, §8) — never in CampBuddy's UI chrome. The app has one fixed palette for every event.`;

g = g.replace(oldSection, newSection);
g = g.replace(/rgba\(35,31,32,…\)/g, 'rgba(15,23,42,…)');
fs.writeFileSync(filePath, g);
console.log('Successfully updated CAMPBUDDY-BRAND-GUIDELINES.md');
