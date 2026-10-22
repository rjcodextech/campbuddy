// npm run brand
//
// CAMPBUDDY-BRAND-GUIDELINES.md §0: resources/scss/abstracts/_variables.scss is
// the single source of the brand's colours, fonts and corners. Every mirror —
// the admin Tailwind theme, the canvas tokens (social posts, reels), the
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

// Admin / manager panel (tailwind.config.js).
const tw = read('tailwind.config.js');
const twColour = (key) => tw.match(new RegExp(`['"]?${key}['"]?:\\s*['"](#[0-9a-fA-F]{6})['"]`))?.[1];
const twNested = (group, key) => tw.match(new RegExp(`${group}:\\s*\\{[^}]*?${key}:\\s*['"](#[0-9a-fA-F]{6})['"]`, 's'))?.[1];
expect('tailwind.config.js', 'ink', twColour('ink'), colours.ink);
expect('tailwind.config.js', 'paper', twColour('paper'), colours.paper);
expect('tailwind.config.js', 'paper-soft', twColour('paper-soft'), colours.paperSoft);
expect('tailwind.config.js', 'muted', twColour('muted'), colours.muted);
expect('tailwind.config.js', 'subtle', twColour('subtle'), colours.subtle);
expect('tailwind.config.js', 'line', twColour('line'), colours.line);
expect('tailwind.config.js', 'maroon', twNested('maroon', 'DEFAULT'), colours.maroon);
expect('tailwind.config.js', 'maroon-dark', twNested('maroon', 'dark'), colours.maroonDark);
expect('tailwind.config.js', 'wine', twColour('wine'), colours.wine);
expect('tailwind.config.js', 'gold', twColour('gold'), colours.gold);
expect('tailwind.config.js', 'navy', twColour('navy'), colours.navy);
expect('tailwind.config.js', 'teal', twColour('teal'), colours.teal);
if (!/sans:\s*\['Poppins'/.test(tw)) problems.push("tailwind.config.js: fontFamily.sans must start with 'Poppins'");
if (!/display:\s*\['"Baloo 2"'/.test(tw)) problems.push('tailwind.config.js: fontFamily.display must start with "Baloo 2"');
expect('tailwind.config.js', 'radius xl (cards)', tw.match(/xl:\s*'(\d+px)'/)?.[1], token('radius-card'));

// Canvas tokens (resources/js/brand.js).
const brand = read('resources/js/brand.js');
const js = (key) => brand.match(new RegExp(`\\b${key}:\\s*'(#[0-9a-fA-F]{6})'`))?.[1];
for (const key of ['maroon', 'maroonDark', 'wine', 'gold', 'ink', 'paper', 'paperSoft', 'line', 'muted', 'teal', 'navy']) expect('resources/js/brand.js', key, js(key), colours[key]);
if (!/FONT_TEXT = '"Poppins"/.test(brand)) problems.push('resources/js/brand.js: FONT_TEXT must start with "Poppins"');
if (!/FONT_DISPLAY = '"Baloo 2"/.test(brand)) problems.push('resources/js/brand.js: FONT_DISPLAY must start with "Baloo 2"');

// Native app defaults (Kotlin) and the server's app config defaults (PHP).
const kotlin = read('native-app/shared/src/commonMain/kotlin/club/campbuddy/shared/model/ConfigModels.kt');
const kt = (key) => kotlin.match(new RegExp(`val ${key}: String = "(#[0-9A-Fa-f]{6})"`))?.[1];
const php = read('app/Support/MobileAppDefaults.php');
const ph = (key) => php.match(new RegExp(`'${key}' => '(#[0-9A-Fa-f]{6})'`))?.[1];
const appMap = { primary: 'maroon', accent: 'gold', background: 'paperSoft', surface: 'paper', peach: 'peach', line: 'line', ink: 'ink', muted: 'muted', danger: 'danger', success: 'success' };
for (const [key, src] of Object.entries(appMap)) {
  expect('ConfigModels.kt (ThemeConfig)', key, kt(key), colours[src]);
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

// "Light only" colours (§3): gold is never text on paper.
for (const file of ['resources/css/app.css']) {
  if (/text-gold\b/.test(read(file))) problems.push(`${file}: gold used as text (2.2:1 on paper)`);
}

if (problems.length) {
  console.error(`Brand check failed (${problems.length}):\n- ${problems.join('\n- ')}`);
  process.exit(1);
}
console.log('Brand check passed: tokens, fonts and app defaults match _variables.scss.');
