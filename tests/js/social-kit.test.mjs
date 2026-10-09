// node --test tests/js  — the Social media page's helpers (social-kit.js) and its ZIP writer (zip-store.js).
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { crc32, zipStore } from '../../resources/js/zip-store.js';

globalThis.document = { getElementById: () => null, querySelector: () => null, querySelectorAll: () => [] };
const { fitBody, mix, paletteFromPixels, personHeadline, postContent, roleColor, sessionsOfDay, shareLinks, slugify, spotlightCaption, textOn, todayCaption } = await import('../../resources/js/social-kit.js');

test('crc32 matches the standard check value', () => {
  assert.equal(crc32(new TextEncoder().encode('123456789')), 0xcbf43926);
});

test('a stored ZIP has each file, its name and an end record pointing at the directory', () => {
  const files = [{ name: 'a.txt', data: new TextEncoder().encode('hello') }, { name: 'café.png', data: new Uint8Array([1, 2, 3]) }];
  const zip = zipStore(files);
  const view = new DataView(zip.buffer);
  assert.equal(view.getUint32(0, true), 0x04034b50, 'starts with a local header');
  const end = zip.length - 22;
  assert.equal(view.getUint32(end, true), 0x06054b50);
  assert.equal(view.getUint16(end + 10, true), 2, 'two entries');
  const dirOffset = view.getUint32(end + 16, true);
  assert.equal(view.getUint32(dirOffset, true), 0x02014b50, 'directory where the end record says');
  assert.ok(new TextDecoder().decode(zip).includes('hello'));
});

test('the palette comes from the strongest real colours, dark enough for white text', () => {
  const px = [];
  const push = (rgb, n) => { for (let i = 0; i < n; i++) px.push(...rgb, 255); };
  push([255, 255, 255], 500); // white background: ignored
  push([230, 80, 40], 300); // orange-red: main
  push([20, 50, 140], 120); // blue: accent
  push([128, 128, 128], 200); // grey: ignored
  const palette = paletteFromPixels(new Uint8ClampedArray(px));
  assert.ok(palette.primary.startsWith('#'));
  const [r, g, b] = [1, 3, 5].map((i) => parseInt(palette.primary.slice(i, i + 2), 16));
  assert.ok(r > g && r > b, 'the main colour is the red');
  assert.equal(textOn(palette.primary), '#ffffff', 'white text reads on it');
  assert.ok(palette.secondary, 'the blue is the accent');
  assert.equal(paletteFromPixels(new Uint8ClampedArray([255, 255, 255, 255, 0, 0, 0, 255])), null, 'no real colour: keep the defaults');
});

test('text colour, names, share links, card headline', () => {
  assert.equal(textOn('#fff8ee'), '#231f20');
  assert.equal(textOn('#0c2343'), '#ffffff');
  assert.equal(slugify('Aachal Pardeshi'), 'aachal-pardeshi');
  assert.equal(slugify('José Ñandú'), 'jose-nandu');
  const links = Object.fromEntries(shareLinks('x'.repeat(400), 'https://rajasthan.wordcamp.org/2026'));
  assert.ok(links.LinkedIn.includes(encodeURIComponent('https://rajasthan.wordcamp.org/2026')));
  assert.ok(decodeURIComponent(links.X.split('text=')[1]).length <= 270, 'X text fits');
  assert.equal(personHeadline({ roles: ['speaker'], role_labels: ['Speaker'] }), 'Meet our Speaker');
  assert.equal(personHeadline({ roles: [], role_labels: [] }), 'Meet me at WordCamp');
});

const kit = {
  event: { name: 'WordCamp Test 2026', app: 'https://campbuddy.club/event/x?utm_source=social', hashtags: '#WordCamp #WordPress #WCTest' },
  speakers: [{ name: 'A', photo: 'a.png' }, { name: 'B', photo: null }],
  sponsors: [{ name: 'S', logo: 'https://x.wordcamp.org/s.png' }],
  tables: [],
  schedule: {
    days: [{ key: '2026-10-03', label: 'Sat 3 Oct' }],
    sessions: [
      { id: 1, day: '2026-10-03', time: '9:00 AM', title: 'Registration', speakers: [], photos: [] },
      { id: 2, day: '2026-10-03', time: '10:00 AM', title: 'Blocks', speakers: ['Rahul'], photos: ['r.png'], track: 'Track 1' },
      { id: 3, day: '2026-10-03', time: '11:00 AM', title: 'Themes', speakers: ['Asha'], photos: [] },
      { id: 4, day: '2026-10-03', time: '12:00 PM', title: 'SEO', speakers: ['Mia'], photos: [] },
    ],
  },
};

test('the posts take their body from the kit', () => {
  const f = { headline: 'H', line: 'L', caption: '' };
  assert.equal(postContent({ key: 'speakers' }, f, kit).body.people.length, 1, 'only speakers with a photo');
  assert.equal(postContent({ key: 'app' }, f, kit).body.type, 'qr');
  assert.equal(postContent({ key: 'contributor' }, f, kit).body.type, 'none', 'no tables: no list');
  const spot = postContent({ key: 'spotlight' }, { headline: '', line: '', caption: '' }, kit, 2);
  assert.equal(spot.headline, 'Blocks');
  assert.equal(spot.line, '10:00 AM · Track 1');
  assert.equal(spot.body.type, 'session');
  const today = postContent({ key: 'today', kicker: 'x' }, f, kit, '2026-10-03');
  assert.equal(today.kicker, 'Sat 3 Oct');
  assert.deepEqual(today.body.items.map((i) => i.title), ['Blocks', 'Themes', 'SEO'], 'talks before registration when there are enough');
});

test('captions for a day and a session', () => {
  assert.match(todayCaption(kit, '2026-10-03', sessionsOfDay(kit.schedule.sessions, '2026-10-03')), /^Sat 3 Oct at WordCamp Test 2026:\n\n10:00 AM {2}Blocks \(Rahul\)/);
  assert.match(spotlightCaption(kit, kit.schedule.sessions[1]), /Up next at WordCamp Test 2026: "Blocks" with Rahul, 10:00 AM in Track 1\./);
});

test('a body too tall for its room loses rows, or goes', () => {
  const list = { type: 'list', items: Array.from({ length: 6 }, (_, i) => ({ time: '1', title: String(i) })) };
  const fitted = fitBody(list, 1350, 300);
  assert.equal(fitted.body.items.length, 2);
  assert.ok(fitted.h <= 300);
  assert.deepEqual(fitBody({ type: 'qr', url: 'x' }, 1350, 100), { body: { type: 'none' }, h: 0 });
  assert.equal(fitBody({ type: 'qr', url: 'x' }, 1350, 200).h, 200, 'a QR shrinks to fit');
});

test('role colours and mixing', () => {
  const colors = { primary: '#7a1f2b', secondary: '#e0a11b', ink: '#231f20', paper: '#fff8ee' };
  assert.equal(roleColor({ roles: ['organizer'] }, colors), '#7a1f2b');
  assert.equal(roleColor({ roles: ['speaker'] }, colors), '#e0a11b');
  assert.equal(roleColor({ roles: ['volunteer'] }, colors), '#1f8a4c');
  assert.equal(mix('#000000', '#ffffff', 0.5), '#808080');
});
