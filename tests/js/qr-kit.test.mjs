// node --test tests/js  — the organizer QR kit (resources/js/qr-kit.js): the codes it draws.
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { makeQr, qrSvg } from '../../resources/js/qr-kit.js';

const URL_ = 'https://campbuddy.club/event/wordcamp-delhi-2026?utm_source=id-card&utm_medium=print&utm_campaign=wordcamp-delhi-2026';

test('the SVG is the whole code on white, with the 4-module quiet zone', () => {
  const count = makeQr(URL_).getModuleCount();
  const svg = qrSvg(URL_);
  assert.match(svg, new RegExp(`viewBox="0 0 ${count + 8} ${count + 8}"`));
  assert.match(svg, /<rect [^>]*fill="#fff"/);
  const dark = (svg.match(/h1v1h-1z/g) ?? []).length;
  let expected = 0;
  const qr = makeQr(URL_);
  for (let r = 0; r < count; r++) for (let c = 0; c < count; c++) if (qr.isDark(r, c)) expected++;
  assert.equal(dark, expected, 'one square per dark module');
});

test('a different place gives a different code', () => {
  assert.notEqual(qrSvg(URL_), qrSvg(URL_.replace('id-card', 'standee')));
});
