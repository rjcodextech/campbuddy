// node --test tests/js  — the Social media page's helpers (social-kit.js) and its ZIP writer (zip-store.js).
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { crc32, zipStore } from '../../resources/js/zip-store.js';

globalThis.document = { getElementById: () => null, querySelector: () => null, querySelectorAll: () => [] };
const { paletteFromPixels, personHeadline, shareLinks, slugify, textOn } = await import('../../resources/js/social-kit.js');

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
