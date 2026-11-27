// node --test tests/js
//
// Source scans that keep guarding the codebase after the adversarial pass:
// no HTML strings parsed in the attendee app, no raw data in a link, every
// JSON island safe inside <script>, infinite motion only for people who
// haven't asked for less, a keyboard twin for every hover, and the brand's
// words. Each failure names the file and line to fix.
import assert from 'node:assert/strict';
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';
import { test } from 'node:test';
import { allRules } from './helpers/scss-rules.mjs';

const ROOT = new URL('../../', import.meta.url).pathname.replace(/^\/([A-Za-z]:)/, '$1');

function filesIn(dir, ext) {
  const found = [];
  const walk = (path) => {
    for (const name of readdirSync(path)) {
      const full = join(path, name);
      if (statSync(full).isDirectory()) walk(full);
      else if (name.endsWith(ext)) found.push(full);
    }
  };
  walk(join(ROOT, dir));

  return found;
}

const rel = (file) => relative(ROOT, file).replace(/\\/g, '/');
const lines = (file) => readFileSync(file, 'utf8').split('\n');

// Files another team owns (the social studio and its canvas work) are left to them.
const NOT_OURS = /resources\/js\/(social-kit|social-reels|social-captions|social-studio|qr-kit|attendee\/camp-card-paint|attendee\/card-share)\.js$/;
const appJs = () => filesIn('resources/js', '.js').filter((f) => !NOT_OURS.test(rel(f)));
const attendeeJs = () => filesIn('resources/js/attendee', '.js').filter((f) => !NOT_OURS.test(rel(f)));
const publicViews = () => filesIn('resources/views', '.blade.php').filter((f) => !/views[\\/](admin|manager|auth|emails|components[\\/]form)/.test(f));

test('no attendee-app code hands a string to an HTML parser (innerHTML, outerHTML, insertAdjacentHTML, document.write, eval, new Function)', () => {
  const found = [];
  for (const file of appJs()) {
    lines(file).forEach((line, i) => {
      if (/^\s*(\/\/|\*)/.test(line)) return;
      if (/\.(innerHTML|outerHTML)\s*=[^=]|insertAdjacentHTML\(|document\.write(ln)?\(|\beval\(|new Function\(|setTimeout\(\s*['"`]/.test(line)) found.push(`${rel(file)}:${i + 1}: ${line.trim()}`);
    });
  }
  assert.deepEqual(found, [], 'build nodes (el.js, template.js) or set textContent instead');
});

test('no public Blade view uses Alpine x-html or v-html', () => {
  const found = publicViews().flatMap((file) => lines(file).flatMap((line, i) => (/\bx-html\b|\bv-html\b/.test(line) ? [`${rel(file)}:${i + 1}`] : [])));
  assert.deepEqual(found, []);
});

test('every JSON island printed raw into a <script> escapes "<", so a title containing </script> cannot end it', () => {
  const found = [];
  for (const file of publicViews()) {
    const source = readFileSync(file, 'utf8');
    for (const match of source.matchAll(/<script[^>]*>\s*\{!!([\s\S]*?)!!\}\s*<\/script>/g)) {
      const code = match[1];
      const safe = /JSON_HEX_TAG/.test(code) || /Seo::jsonLd\(/.test(code);
      if (!safe) found.push(`${rel(file)}: {!!${code.slice(0, 60)}…`);
    }
  }
  assert.deepEqual(found, []);
});

test('every raw {!! !!} in a public view is one of the reviewed safe kinds', () => {
  const found = [];
  for (const file of publicViews()) {
    lines(file).forEach((line, i) => {
      for (const match of line.matchAll(/\{!!\s*([^!]*)/g)) {
        const code = match[1];
        if (/^json_encode\(|^\\App\\Support\\SafeHtml::clean\(|^\\App\\Support\\Seo::jsonLd\(|^\$shape\b|^\$current\b/.test(code.trim())) continue;
        found.push(`${rel(file)}:${i + 1}: {!! ${code.trim().slice(0, 50)}`);
      }
    });
  }
  assert.deepEqual(found, [], 'escape it with {{ }} or run it through SafeHtml::clean / SafeUrl::web');
});

// The href values the attendee app may set without the web-address check: its own pages, anchors, and
// addresses it built itself. Anything else coming from data must pass webUrl() / isWeb() first.
const REVIEWED_HREFS = new Map([
  ['calendar.js', ['url']], // a blob: URL of the .ics it just built
  ['data-controls.js', ['url']], // a blob: URL of the export it just built
  ['home.js', ['urls.guide', 'urls.myDay', 'urls.quest', 'ctx.urls.myDay']], // route() addresses from the page's own JSON
  ['in-app-browser.js', ['url']], // openInAppBrowser() returns early unless isWebUrl(url)
  ['meet-sheet.js', ['googleCalendarUrl(']], // https://calendar.google.com/… built here
  ['native-bridge.js', ['storeUrl.trim()']], // checked against http(s)/market/itms-apps just above
  ['people.js', ['l.url', "options.exploreUrl ?? '#'", 'cardUrl']], // links filtered by isWeb() above; the others are our own pages
  ['plan-reminder.js', ['`${facts.myDayUrl']], // the page's own My Day address
  ['quest.js', ['navUrl(']], // our own screens
]);

test('no link in the attendee app takes an address straight from data: it is our own page or passed the web-address check', () => {
  const found = [];
  for (const file of attendeeJs()) {
    const name = rel(file).split('/').pop();
    const source = readFileSync(file, 'utf8');
    const checked = new Set([...source.matchAll(/const\s+(\w+)\s*=\s*webUrl\(/g)].map((m) => m[1]));
    lines(file).forEach((line, i) => {
      if (/^\s*(\/\/|\*)/.test(line)) return;
      const values = [
        ...[...line.matchAll(/\.href\s*=\s*([^;]+);/g)].map((m) => m[1].trim()),
        ...[...line.matchAll(/\bhref:\s*([^,}]+?)\s*(?:,|\}|$)/g)].map((m) => m[1].trim()),
      ];
      for (const value of values) {
        const ok = /^['"`](\/|#)/.test(value) // a path or anchor on this site
          || /^`#li-/.test(value)
          || /webUrl\(|isWeb\(/.test(line)
          || checked.has(value)
          || (REVIEWED_HREFS.get(name) ?? []).some((allowed) => value.startsWith(allowed));
        if (!ok) found.push(`${rel(file)}:${i + 1}: href ← ${value}`);
      }
    });
  }
  assert.deepEqual(found, [], 'wrap it in webUrl() (safe-url.js), or add it to REVIEWED_HREFS with the reason it is safe');
});

test('infinite animations run only for people who have not asked for reduced motion', () => {
  const rules = allRules();
  const stopped = new Set();
  for (const rule of rules) {
    if (!rule.media.some((m) => /prefers-reduced-motion:\s*reduce/.test(m))) continue;
    if (rule.declarations.some((d) => /^animation(-name)?$/.test(d.property) && /\bnone\b/.test(d.value))) rule.selectors.forEach((s) => stopped.add(s.replace(/\s+/g, ' ')));
  }
  const found = [];
  for (const rule of rules) {
    for (const d of rule.declarations) {
      if (!/^animation(-iteration-count)?$/.test(d.property) || !/\binfinite\b/.test(d.value)) continue;
      const gated = rule.media.some((m) => /prefers-reduced-motion:\s*no-preference/.test(m));
      const switchedOff = rule.selectors.every((s) => stopped.has(s.replace(/\s+/g, ' ')));
      if (!gated && !switchedOff) found.push(`${rule.file}:${d.line} ${rule.selectors.join(', ')}`);
    }
  }
  assert.deepEqual(found, [], 'put it inside @media (prefers-reduced-motion: no-preference) (or @include motion-safe)');
});

// Plain :hover rules on links and buttons that still lack a :focus-visible twin (the m.hover mixin now
// adds one by itself). Written down so the list can only shrink: fix one, take it off.
const HOVER_DEBT = new Set(['resources/scss/components/_about.scss .hero-breadcrumb a:hover', 'resources/scss/components/_banner.scss .announcement-banner__close:hover', 'resources/scss/components/_banner.scss .announcement-banner__cta:hover', 'resources/scss/components/_camp-card-form.scss .cc-share__result:hover', 'resources/scss/components/_event-card.scss .event-card__export:hover svg', 'resources/scss/components/_event-card.scss .event-card__memories a:hover', 'resources/scss/components/_guide.scss .guide-jump .chip:hover svg', 'resources/scss/components/_guide.scss .guide-jump .chip:hover .line-icon', 'resources/scss/components/_guide.scss .guide-jump.guide-jump--sidebar .chip:hover', 'resources/scss/components/_guide.scss .guide-jump.guide-jump--sidebar .chip:hover svg', 'resources/scss/components/_guide.scss .guide-jump.guide-jump--sidebar .chip:hover .line-icon', 'resources/scss/components/_landing.scss .landing-hero__actions .btn--hero-cta:hover', 'resources/scss/components/_landing.scss .landing-hero__actions .btn--hero-guide:hover', 'resources/scss/components/_landing.scss .hero-stage__shuffle-btn:hover', 'resources/scss/components/_landing.scss .hero-stage__view-btn:hover', 'resources/scss/components/_landing.scss .hero-stage__view-btn:hover .hero-stage__arrow', 'resources/scss/components/_landing.scss .usp-slider__arrow:hover:not(:disabled)', 'resources/scss/components/_landing.scss .usp-slider__dot:hover:not(.is-active)', 'resources/scss/components/_landing.scss .about-wps-banner__title a:hover', 'resources/scss/components/_landing.scss .footer-nav-links a:hover', 'resources/scss/components/_landing.scss .social-icon-btn:hover', 'resources/scss/components/_landing.scss .footer-copy a:hover', 'resources/scss/components/_legal.scss .legal-tab.is-active:hover', 'resources/scss/components/_legal.scss .legal-p a:hover', 'resources/scss/components/_legal.scss .legal-custom-content a:hover', 'resources/scss/components/_legal.scss .legal-contact-btn:hover', 'resources/scss/components/_legal.scss .legal-footer-links a:hover', 'resources/scss/components/_misc.scss .contrib-lead-link:hover', 'resources/scss/components/_people.scss button.roster-row__name:hover', 'resources/scss/components/_plan.scss .delay-banner__close:hover', 'resources/scss/components/_schedule-item.scss .schedule-item__star:hover', 'resources/scss/components/_thank-you.scss a.move-data__option:hover', 'resources/scss/components/_thank-you.scss .move-data__option--button:hover', 'resources/scss/layout/_topbar.scss .topbar--public .topbar__nav .topbar__text-btn:hover', 'resources/scss/layout/_topbar.scss .topbar--public .topbar__nav .topbar__text-btn:hover svg', 'resources/scss/layout/_topbar.scss .topbar--public .topbar__actions .topbar__text-btn--ghost:hover', 'resources/scss/layout/_topbar.scss .topbar--public .topbar__actions .topbar__text-btn--install:hover', 'resources/scss/layout/_topbar.scss .topbar--public .topbar__actions .topbar__text-btn--install:hover svg', 'resources/scss/layout/_wide-screens.scss .app-frame--with-nav .bottom-nav__item:hover', 'resources/scss/layout/_wide-screens.scss .app-frame--with-nav .bottom-nav__item:hover .bottom-nav__icon', 'resources/scss/layout/_wide-screens.scss .app-frame--with-nav .bottom-nav__item:hover svg', 'resources/scss/layout/_wide-screens.scss .app-frame--with-nav .bottom-nav__item--active:hover']);

test('every hover on a link or button has a :focus-visible twin (no new ones without; the old list only shrinks)', () => {
  // Classes that sit on links and buttons in the public views and the attendee app.
  const interactive = new Set();
  for (const file of [...publicViews(), ...appJs()]) {
    const source = readFileSync(file, 'utf8');
    for (const tag of source.matchAll(/<(a|button|summary|select|input|textarea)\b([^>]*)>/g)) {
      for (const cls of tag[2].matchAll(/class="([^"]*)"/g)) cls[1].split(/\s+/).forEach((c) => c && !c.includes('{') && interactive.add(c));
    }
    // Links and buttons the app builds itself: document.createElement('a'), then a className.
    for (const made of source.matchAll(/createElement\('(?:a|button)'\)[\s\S]{0,200}?className\s*=\s*'([^']+)'/g)) made[1].split(/\s+/).forEach((c) => interactive.add(c));
  }

  const rules = allRules().filter((r) => !r.file.endsWith('_mixins.scss'));
  const twins = new Set();
  for (const rule of rules) for (const s of rule.selectors) if (/:focus-visible|:focus-within/.test(s)) twins.add(s.replace(/:focus-visible|:focus-within/g, ':STATE').replace(/\s+/g, ' '));

  const missing = new Set();
  for (const rule of rules) {
    for (const s of rule.selectors) {
      if (!/:hover/.test(s) || twins.has(s.replace(/:hover/g, ':STATE').replace(/\s+/g, ' '))) continue;
      const subject = s.split(/[\s>+~]+/).find((c) => c.includes(':hover')) ?? '';
      const isControl = /^(a|button)\b/.test(subject) || [...subject.matchAll(/\.([\w-]+)/g)].some((m) => interactive.has(m[1]));
      if (isControl) missing.add(`${rule.file} ${s}`);
    }
  }

  const fresh = [...missing].filter((entry) => !HOVER_DEBT.has(entry));
  assert.deepEqual(fresh, [], 'use @include m.hover { … } (it adds the :focus-visible twin), or add `&:focus-visible` beside `&:hover`');
  const fixed = [...HOVER_DEBT].filter((entry) => !missing.has(entry));
  assert.deepEqual(fixed, [], 'fixed: take these off HOVER_DEBT');
});

// ---------------------------------------------------------------- words

const COPY_FILES = () => [
  ...publicViews(),
  ...attendeeJs(),
];

test('the attendee app never uses the banned words or misspells WordPress, WordCamp or CampBuddy', () => {
  const found = [];
  for (const file of COPY_FILES()) {
    lines(file).forEach((line, i) => {
      // Text people read: Blade text and JS strings — not class names, routes or code.
      const text = line.replace(/\{\{--.*?--\}\}|class="[^"]*"|href="[^"]*"|src="[^"]*"|route\([^)]*\)|@\w+\([^)]*\)/g, '');
      for (const word of ['revolutionary', 'game-changer', 'game changer', 'seamless', 'supercharge']) if (new RegExp(`\\b${word}`, 'i').test(text)) found.push(`${rel(file)}:${i + 1}: "${word}"`);
      if (/\b(unlock|elevate)(s|d|ing)?\b/i.test(text) && !/\b(unlock|elevate)\w*\s*[(:=]/.test(text)) found.push(`${rel(file)}:${i + 1}: unlock/elevate`);
      for (const bad of ['Wordpress', 'Wordcamp', 'Campbuddy', 'Camp Buddy', 'WORDcamp']) if (new RegExp(`\\b${bad}\\b`).test(text)) found.push(`${rel(file)}:${i + 1}: "${bad}"`);
    });
  }
  assert.deepEqual(found, []);
});

test('every <img> in the public views says what it is (alt, empty when decorative)', () => {
  const found = [];
  for (const file of publicViews()) {
    const source = readFileSync(file, 'utf8');
    // Blade expressions can hold "->" and "=>"; take them out before reading the tag.
    const plain = source.replace(/\{\{[\s\S]*?\}\}/g, 'X').replace(/@class\(\[[\s\S]*?\]\)/g, 'class="X"');
    for (const match of plain.matchAll(/<img\b[^>]*>/g)) {
      if (!/\balt=/.test(match[0])) found.push(`${rel(file)}: ${match[0].slice(0, 80)}`);
    }
  }
  assert.deepEqual(found, []);
});

test('every icon-only button or link in the attendee templates has a name a screen reader can say', () => {
  const found = [];
  for (const file of filesIn('resources/views/attendee', '.blade.php')) {
    const source = readFileSync(file, 'utf8');
    for (const match of source.matchAll(/<(button|a)\b([^>]*)>([\s\S]*?)<\/\1>/g)) {
      const [, , attrs, inner] = match;
      const text = inner.replace(/<svg[\s\S]*?<\/svg>|<x-attendee\.line-icon[^>]*\/>|<[^>]+>|\{\{--[\s\S]*?--\}\}/g, '').trim();
      const named = /aria-label(ledby)?=|title=|data-slot=/.test(attrs) || text.length > 0 || /u-visually-hidden|sr-only|\balt="[^"]+"/.test(inner);
      if (!named) found.push(`${rel(file)}: <${match[1]}${attrs.slice(0, 70)}>`);
    }
  }
  assert.deepEqual(found, []);
});
