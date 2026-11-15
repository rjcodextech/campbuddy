// node --experimental-test-module-mocks --test tests/js
//
// DOM XSS, attacked through the real modules: hostile names, titles, bios and
// links (from organizers, scraped WordCamp pages, or an old copy on the phone)
// are pushed through the real renderers and the real Blade <template>
// markup, then the result is searched for anything that would run. Text must
// land as text; a link or frame may only ever point at a web page.
import assert from 'node:assert/strict';
import { afterEach, mock, test } from 'node:test';
import { freshImport } from './helpers/fake-browser.mjs';
import { htmlWrites, installMiniDom, parseHtml, scriptUrls, templatesFrom } from './helpers/mini-dom.mjs';
import { webUrl } from '../../resources/js/attendee/safe-url.js';

const DIR = new URL('../../resources/js/attendee/', import.meta.url).href;

// Every trick for getting script into an href or src.
const SCRIPT_URLS = [
  'javascript:alert(1)',
  'JaVaScRiPt:alert(1)',
  ' javascript:alert(1)',
  '\tjavascript:alert(1)',
  'java\nscript:alert(1)',
  'javascript://%0aalert(1)',
  'data:text/html,<script>alert(1)</script>',
  'vbscript:msgbox(1)',
  '//evil.example/x',
  'https://ok.example/"><img src=x onerror=alert(1)>',
  'https://ok.example/ onmouseover=alert(1)',
];
const MARKUP = '"><img src=x onerror=alert(1)></script><script>alert(2)</script>';

let dom;
afterEach(() => {
  dom?.restore();
  dom = null;
  mock.restoreAll();
  mock.timers.reset();
});

// ---------------------------------------------------------------- the allow-list itself

test('only plain web addresses pass the link allow-list', () => {
  for (const url of SCRIPT_URLS) assert.equal(webUrl(url), null, JSON.stringify(url));
  for (const value of [null, undefined, 42, {}, ['https://a.b'], '', 'mailto:a@b.c', 'tel:123', '/relative']) assert.equal(webUrl(value), null, JSON.stringify(value));

  assert.equal(webUrl('https://wordpress.tv/2026/talk/'), 'https://wordpress.tv/2026/talk/');
  assert.equal(webUrl('  http://example.org/a?b=c#d  '), 'http://example.org/a?b=c#d', 'trimmed');
  assert.equal(webUrl(`https://a.example/${'x'.repeat(3000)}`), null, 'absurdly long');
});

// ---------------------------------------------------------------- Contribute: table leads

test('a table lead with a script link, a markup name and a script photo renders as text, with only real links', async () => {
  dom = installMiniDom({ templates: templatesFrom('attendee/templates/contribute.blade.php') });
  const { renderLeadCard } = await freshImport(`${DIR}contribute.js`);

  for (const bad of SCRIPT_URLS) {
    const card = renderLeadCard({
      name: MARKUP,
      designation: MARKUP,
      bio: MARKUP,
      avatar: bad,
      url: bad,
      links: [{ type: 'twitter', url: bad }, { type: MARKUP, url: 'https://profiles.wordpress.org/asha/' }, null, 'x', { url: 42 }],
    });

    assert.deepEqual(scriptUrls(card), [], JSON.stringify(bad));
    assert.equal(card.querySelector('[data-slot="name"]').textContent, MARKUP, 'the name is shown exactly as typed');
    assert.equal(card.querySelector('[data-slot="bio"]').textContent, MARKUP);
    assert.equal(card.querySelector('img'), null, 'no photo from a non-web address');
    const links = card.querySelectorAll('a');
    assert.deepEqual(links.map((a) => a.href), ['https://profiles.wordpress.org/asha/'], 'only the real link is kept');
    assert.equal(links[0].rel, 'noopener');
  }
  assert.deepEqual(htmlWrites, [], 'nothing went through an HTML parser');
});

test('a table lead with nothing but bad links shows no links box at all', async () => {
  dom = installMiniDom({ templates: templatesFrom('attendee/templates/contribute.blade.php') });
  const { renderLeadCard } = await freshImport(`${DIR}contribute.js`);

  const card = renderLeadCard({ name: 'Asha', url: 'javascript:alert(1)', links: 'not a list' });

  assert.equal(card.querySelector('[data-slot="links"]'), null);
  assert.equal(card.querySelector('[data-slot="initial"]').textContent, 'A');
});

// ---------------------------------------------------------------- My Day: session details

test("a session's slides and video links only ever point at web pages; its text stays text", async () => {
  dom = installMiniDom({ templates: templatesFrom('attendee/templates/my-day.blade.php') });
  globalThis.window = { addEventListener() {}, location: { origin: 'https://campbuddy.test' } };
  globalThis.location = { origin: 'https://campbuddy.test', pathname: '/event/wc' };
  const { sessionDetail } = await freshImport(`${DIR}my-day.js`);

  for (const bad of SCRIPT_URLS) {
    const speakers = new Map([[9, { id: 9, name: MARKUP, bio_text: MARKUP, avatar_url: bad }]]);
    const detail = sessionDetail({ id: 1, title: MARKUP, description: MARKUP, starts_at: null, slides_url: bad, video_url: bad, speaker_ids: [9], category_names: [MARKUP] }, speakers, false);

    assert.deepEqual(scriptUrls(detail), [], JSON.stringify(bad));
    assert.equal(detail.querySelector('[data-slot="links"]'), null, 'no links row when neither link is a web page');
    assert.equal(detail.querySelector('.schedule-item-detail__desc').textContent, MARKUP);
    assert.equal(detail.querySelector('.schedule-item-detail__speaker-name').textContent, MARKUP);
    assert.equal(detail.querySelector('img'), null, "no photo from a non-web address: the speaker's initial shows");
  }

  const good = sessionDetail({ id: 2, title: 'Talk', slides_url: 'javascript:alert(1)', video_url: 'https://wordpress.tv/x/', speaker_ids: [] }, new Map(), true);
  const links = good.querySelectorAll('a');
  assert.deepEqual(links.map((a) => [a.textContent, a.href]), [['Video', 'https://wordpress.tv/x/']]);
  assert.deepEqual(htmlWrites, []);
  delete globalThis.window;
  delete globalThis.location;
});

// ---------------------------------------------------------------- the picker's spotlight

function spotlightPage(items) {
  const ids = ['hero-spotlight-card', 'hero-stage-event-logo', 'hero-stage-event-name', 'hero-spotlight-event', 'hero-spotlight-badge', 'hero-spotlight-title', 'hero-spotlight-meta', 'hero-spotlight-avatar', 'hero-spotlight-link', 'hero-stage-event-eyebrow', 'hero-stage-live-dot', 'hero-spotlight-action'];
  const markup = ids.map((id) => (id === 'hero-spotlight-link' ? `<a id="${id}" href="#find-your-camp"></a>` : id === 'hero-stage-event-logo' ? `<img id="${id}" src="/media/icons/icon-192.png">` : `<span id="${id}"></span>`)).join('')
    + '<script type="application/json" id="hero-spotlights-data"></script>';
  dom = installMiniDom();
  dom.document.body.replaceChildren(...parseHtml(markup).childNodes);
  dom.document.getElementById('hero-spotlights-data').textContent = JSON.stringify(items);
  globalThis.window = { addEventListener() {} };
  globalThis.localStorage = { getItem: () => null };

  return dom.document;
}

test('a spotlight with a hostile icon name, link, logo and text cannot inject markup or a script link', async () => {
  const doc = spotlightPage([{ icon: 'x"><img src=x onerror=alert(1)>', url: 'javascript:alert(1)', event_logo: 'javascript:alert(2)', title: MARKUP, event_name: MARKUP, meta: MARKUP, badge: MARKUP }]);
  const { initHeroSpotlight } = await freshImport(`${DIR}hero-spotlight.js`);

  initHeroSpotlight();

  assert.deepEqual(htmlWrites, [], 'the icon is built as nodes, never parsed');
  assert.deepEqual(scriptUrls(doc.body), []);
  assert.equal(doc.getElementById('hero-spotlight-link').href, '#find-your-camp', 'a script link leaves the link where it was');
  assert.equal(doc.getElementById('hero-stage-event-logo').src, '/media/icons/icon-192.png');
  assert.equal(doc.getElementById('hero-spotlight-title').textContent, MARKUP);
  assert.equal(doc.getElementById('hero-spotlight-avatar').querySelector('img'), null);
  delete globalThis.window;
  delete globalThis.localStorage;
});

test('a spotlight with a real icon and link shows the line icon and the link; with no initials it shows an icon, never a text star', async () => {
  const doc = spotlightPage([{ icon: 'camera', url: 'https://campbuddy.test/event/wc/memories', event_name: 'WordCamp Pune 2026', title: 'Photos' }]);
  const { initHeroSpotlight } = await freshImport(`${DIR}hero-spotlight.js`);

  initHeroSpotlight();

  assert.equal(doc.getElementById('hero-spotlight-avatar').querySelector('use').getAttribute('href'), '#li-camera');
  assert.equal(doc.getElementById('hero-spotlight-link').href, 'https://campbuddy.test/event/wc/memories');

  const blank = spotlightPage([{ title: '   ', event_name: '' }]);
  (await freshImport(`${DIR}hero-spotlight.js`)).initHeroSpotlight();
  const avatar = blank.getElementById('hero-spotlight-avatar');
  assert.doesNotMatch(avatar.textContent, /★/);
  assert.equal(avatar.querySelector('use').getAttribute('href'), '#li-sparkles');
  delete globalThis.window;
  delete globalThis.localStorage;
});

// ---------------------------------------------------------------- "Looking to meet" chips

test('a "looking to meet" tag full of markup is a chip of text, and its remove button is a labelled icon, not a parsed ×', async () => {
  dom = installMiniDom();
  const root = parseHtml('<div><div class="who-tags"><div class="who-tags__items"></div><input class="who-tags__input"><input type="hidden" value=""></div></div>').firstElementChild;
  const { initWhoToMeetInput } = await freshImport(`${DIR}who-to-meet.js`);
  const hidden = root.querySelector('input[type="hidden"]');
  hidden.value = '<img src=x onerror=alert(1)>';

  initWhoToMeetInput(root);

  const chip = root.querySelector('.who-tag-chip');
  assert.equal(chip.firstChild.textContent, '<img src=x onerror=alert(1)>');
  const remove = chip.querySelector('button');
  assert.equal(remove.getAttribute('aria-label'), 'Remove <img src=x onerror=alert(1)>');
  assert.equal(remove.querySelector('use').getAttribute('href'), '#li-x');
  assert.deepEqual(htmlWrites, []);
});

// ---------------------------------------------------------------- deals and the in-app browser

test('the in-app browser never frames or links a script address; a web page it does', async () => {
  dom = installMiniDom({ templates: templatesFrom('attendee/templates/explore.blade.php') });
  mock.timers.enable({ apis: ['setTimeout'] }); // the "couldn't be shown here" check waits 3 s; not needed here
  const { openInAppBrowser } = await freshImport(`${DIR}in-app-browser.js`);

  // (Quotes or spaces inside a real https address are harmless here: attributes are set, never parsed.)
  for (const bad of SCRIPT_URLS.filter((url) => !/^https:/.test(url))) openInAppBrowser(bad, MARKUP);
  assert.equal(dom.document.body.querySelector('dialog'), null, 'nothing opened');

  openInAppBrowser('https://sponsor.example/deal', MARKUP);
  const dialog = dom.document.body.querySelector('dialog');
  assert.equal(dialog.querySelector('iframe').getAttribute('src'), 'https://sponsor.example/deal');
  assert.equal(dialog.querySelector('[data-slot="title"]').textContent, MARKUP);
  assert.deepEqual(scriptUrls(dialog), []);
});
