// npm run brand
//
// CAMPBUDDY-BRAND-GUIDELINES.md §0: resources/scss/abstracts/_variables.scss is
// the single source of the brand's colours, fonts and corners. Every mirror —
// the admin Tailwind theme (resources/css/app.css), the canvas tokens (social posts, reels), the
// native app's theme defaults and the server's app-config defaults — must
// equal it. This fails, naming each drift, when one doesn't. It also fails on
// a Google Fonts link for the brand fonts (they are self-hosted) and on text
// colours the guideline marks as "never text on paper".

import { existsSync, readdirSync, readFileSync, statSync } from 'node:fs';

const read = (path) => readFileSync(new URL(`../${path}`, import.meta.url), 'utf8');
const scss = read('resources/scss/abstracts/_variables.scss');
const token = (name) => {
  const m = scss.match(new RegExp(`^\\$${name}:\\s*([^;]+);`, 'm'));
  if (!m) throw new Error(`$${name} is missing from _variables.scss`);
  return m[1].trim().toLowerCase();
};

const colours = {
  ink: token('color-ink'),
  paper: token('color-paper'),
  paperSoft: token('color-paper-soft'),
  muted: token('color-muted'),
  subtle: token('color-subtle'),
  line: token('color-line'),
  peach: token('color-peach'),
  maroon: token('color-maroon'),
  brandText: token('color-brand-text'),
  maroonDark: token('color-maroon-dark'),
  wine: token('color-wine'),
  gold: token('color-gold'),
  navy: token('color-navy'),
  teal: token('color-teal'),
  danger: token('color-danger'),
  success: token('color-success'),
};

const problems = [];
const expect = (where, what, actual, wanted) => {
  if (String(actual ?? '').toLowerCase() !== String(wanted).toLowerCase()) problems.push(`${where}: ${what} is ${actual ?? '(missing)'}, the source says ${wanted}`);
};

// Admin / manager panel: the Tailwind 4 theme in resources/css/app.css (@theme).
const tw = read('resources/css/app.css');
const where = 'resources/css/app.css @theme';
const twVar = (name) => tw.match(new RegExp(`--${name}:\s*([^;]+);`))?.[1]?.trim();
expect(where, 'ink', twVar('color-ink'), colours.ink);
expect(where, 'paper', twVar('color-paper'), colours.paper);
expect(where, 'paper-soft', twVar('color-paper-soft'), colours.paperSoft);
expect(where, 'muted', twVar('color-muted'), colours.muted);
expect(where, 'subtle', twVar('color-subtle'), colours.subtle);
expect(where, 'line', twVar('color-line'), colours.line);
expect(where, 'maroon', twVar('color-maroon'), colours.maroon);
expect(where, 'maroon-dark', twVar('color-maroon-dark'), colours.maroonDark);
expect(where, 'maroon-text', twVar('color-maroon-text'), colours.brandText);
expect(where, 'wine', twVar('color-wine'), colours.wine);
expect(where, 'gold', twVar('color-gold'), colours.gold);
expect(where, 'navy', twVar('color-navy'), colours.navy);
expect(where, 'teal', twVar('color-teal'), colours.teal);
if (!/^Poppins,/.test(twVar('font-sans') ?? '')) problems.push(`${where}: --font-sans must start with Poppins`);
if (!/^'Baloo 2'/.test(twVar('font-display') ?? '')) problems.push(`${where}: --font-display must start with 'Baloo 2'`);
expect(where, 'radius xl (cards)', twVar('radius-xl'), token('radius-card'));

// Canvas tokens (resources/js/brand.js).
const brand = read('resources/js/brand.js');
const js = (key) => brand.match(new RegExp(`\\b${key}:\\s*'(#[0-9a-fA-F]{6})'`))?.[1];
for (const key of ['maroon', 'maroonDark', 'wine', 'gold', 'ink', 'paper', 'paperSoft', 'line', 'muted', 'teal', 'navy']) expect('resources/js/brand.js', key, js(key), colours[key]);
if (!/FONT_TEXT = '"Poppins"/.test(brand)) problems.push('resources/js/brand.js: FONT_TEXT must start with "Poppins"');
if (!/FONT_DISPLAY = '"Baloo 2"/.test(brand)) problems.push('resources/js/brand.js: FONT_DISPLAY must start with "Baloo 2"');

// The Brand guidelines page's data (app/Support/BrandTokens.php) shows the real colours.
const tokensPhp = read('app/Support/BrandTokens.php');
const pageHex = (token) => tokensPhp.match(new RegExp(`'${token}', '(#[0-9A-Fa-f]{6})'`))?.[1];
const pageMap = { '--paper': 'paper', '--paper-soft': 'paperSoft', '--line': 'line', '--ink': 'ink', '--muted': 'muted', '--subtle': 'subtle', '--brand-primary': 'maroon', '--brand-accent': 'maroonDark', '--wine': 'wine', '--gold': 'gold', '--peach': 'peach', '--teal': 'teal', '--navy': 'navy', '--success': 'success', '--danger': 'danger' };
for (const [token, src] of Object.entries(pageMap)) expect('BrandTokens.php', token, pageHex(token), colours[src]);

// Native app defaults (Kotlin) and the server's app config defaults (PHP).
const kotlin = read('native-app/shared/src/commonMain/kotlin/club/campbuddy/shared/model/ConfigModels.kt');
const kt = (key) => kotlin.match(new RegExp(`val ${key}: String = "(#[0-9A-Fa-f]{6})"`))?.[1];
const php = read('app/Support/MobileAppDefaults.php');
const ph = (key) => php.match(new RegExp(`'${key}' => '(#[0-9A-Fa-f]{6})'`))?.[1];
const appMap = { primary: 'maroon', primary_text: 'brandText', accent: 'gold', background: 'paperSoft', surface: 'paper', peach: 'peach', line: 'line', ink: 'ink', muted: 'muted', danger: 'danger', success: 'success' };
for (const [key, src] of Object.entries(appMap)) {
  expect('ConfigModels.kt (ThemeConfig)', key, kt(key.replace(/_([a-z])/g, (m, c) => c.toUpperCase())), colours[src]);
  expect('MobileAppDefaults.php (theme)', key, ph(key), colours[src]);
}
expect('ConfigModels.kt (ThemeConfig)', 'radius', kotlin.match(/val radius: Int = (\d+)/)?.[1] + 'px', token('radius-card'));
expect('MobileAppDefaults.php (theme)', 'radius', php.match(/'radius' => (\d+)/)?.[1] + 'px', token('radius-card'));
const themeKt = read('native-app/shared/src/commonMain/kotlin/club/campbuddy/shared/ui/Theme.kt');
if (/inter_/.test(themeKt)) problems.push('Theme.kt: still uses Inter; the app font is Poppins + Baloo 2');
for (const f of ['poppins_regular', 'poppins_medium', 'poppins_semibold', 'poppins_bold', 'baloo2_semibold', 'baloo2_bold', 'baloo2_extrabold']) {
  if (!existsSync(new URL(`../native-app/shared/src/commonMain/composeResources/font/${f}.ttf`, import.meta.url))) problems.push(`native app: composeResources/font/${f}.ttf is missing`);
}

// Fonts are self-hosted: no Google Fonts link for the brand fonts or Inter.
const walk = (dir, out = []) => {
  for (const name of readdirSync(new URL(`../${dir}`, import.meta.url))) {
    const path = `${dir}/${name}`;
    if (statSync(new URL(`../${path}`, import.meta.url)).isDirectory()) walk(path, out);
    else if (path.endsWith('.blade.php')) out.push(path);
  }
  return out;
};
for (const view of walk('resources/views')) {
  const html = read(view);
  if (/fonts\.googleapis\.com\/css2\?[^"']*family=(Inter|Poppins|Baloo)/.test(html)) problems.push(`${view}: loads a brand font from Google Fonts (self-host it: resources/css/fonts.css)`);
}

// The guideline's own colour table (§3) says what the code says — it once kept the old palette for days.
const guide = read('.claude/skills/campbuddy-docs/brand/CAMPBUDDY-BRAND-GUIDELINES.md');
const guideHex = (name) => guide.match(new RegExp(`\\| \`--${name}\` / \`[^\`]+\` \\| \`(#[0-9A-Fa-f]{6})\``))?.[1];
for (const [name, src] of Object.entries({ paper: 'paper', 'paper-soft': 'paperSoft', line: 'line', ink: 'ink', muted: 'muted', subtle: 'subtle', 'brand-primary': 'maroon', wine: 'wine', gold: 'gold', teal: 'teal', navy: 'navy', success: 'success', danger: 'danger' })) {
  expect('CAMPBUDDY-BRAND-GUIDELINES.md §3', name, guideHex(name), colours[src]);
}

// The native gradient partner (Components.kt BRAND_WINE) is the web's wine.
const componentsKt = read('native-app/shared/src/commonMain/kotlin/club/campbuddy/shared/ui/Components.kt');
const wineKt = componentsKt.match(/val BRAND_WINE = Color\(0xFF([0-9A-Fa-f]{6})\)/)?.[1];
expect('Components.kt', 'BRAND_WINE', wineKt ? `#${wineKt}` : null, colours.wine);

// No literal of the retired palette (before Daylight 2.0) left in the web's styles, views or scripts.
// The Camp Card keeps its own badge palette and is not checked.
const retired = /#c33a19\b|#721313\b|#e39d1c\b|#231f20\b|#fffaf4\b|#eadfd6\b|#6b625e\b|rgba?\(\s*195,\s*58,\s*25\b|rgba?\(\s*35,\s*31,\s*32\b/i;
const sources = (dir, ext, out = []) => {
  for (const name of readdirSync(new URL(`../${dir}`, import.meta.url))) {
    const path = `${dir}/${name}`;
    if (statSync(new URL(`../${path}`, import.meta.url)).isDirectory()) sources(path, ext, out);
    else if (ext.test(path) && !/_camp-card(-form)?\.scss$|abstracts\/_variables\.scss$/.test(path)) out.push(path);
  }
  return out;
};
for (const file of [...sources('resources/scss', /\.scss$/), ...sources('resources/views', /\.blade\.php$/), ...sources('resources/js', /\.js$/), 'public/sw.js']) {
  const hit = read(file).match(retired);
  if (hit) problems.push(`${file}: "${hit[0]}" is from the retired palette (use its Daylight 2.0 token)`);
}

// "Light only" colours (§3): gold is never text on paper.
for (const file of ['resources/css/app.css']) {
  if (/text-gold\b/.test(read(file))) problems.push(`${file}: gold used as text (2.2:1 on paper)`);
}

if (problems.length) {
  console.error(`Brand check failed (${problems.length}):\n- ${problems.join('\n- ')}`);
  process.exit(1);
}
console.log('Brand check passed: tokens, fonts and app defaults match _variables.scss.');
