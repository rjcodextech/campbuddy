// node --test tests/js  — files saved from inside the app (native-downloads.js).
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

globalThis.window = {};

const { filenameFrom, isDownloadLink } = await import('../../resources/js/attendee/native-downloads.js');

const ORIGIN = 'https://campbuddy.club';
const link = (href, attrs = {}) => ({ href, hasAttribute: (n) => n in attrs, getAttribute: (n) => attrs[n] ?? null });

test('the file name comes from the response, then the link, then a default', () => {
  assert.equal(filenameFrom('attachment; filename="deal-leads.csv"', '/x'), 'deal-leads.csv');
  assert.equal(filenameFrom("attachment; filename*=UTF-8''caf%C3%A9.csv", '/x'), 'café.csv');
  assert.equal(filenameFrom(null, '/admin/events/3/qr.png'), 'qr.png');
  assert.equal(filenameFrom(null, '/admin/events/3/deal-leads/export'), 'campbuddy');
});

test('download links are marked ones and same-site exports, nothing else', () => {
  assert.equal(isDownloadLink(link('blob:https://campbuddy.club/abc', { download: 'qr.png' }), ORIGIN), true);
  assert.equal(isDownloadLink(link(`${ORIGIN}/admin/events/3/deal-leads/export?x=1`), ORIGIN), true);
  assert.equal(isDownloadLink(link(`${ORIGIN}/event/wordcamp-x/my-day`), ORIGIN), false);
  assert.equal(isDownloadLink(link('https://elsewhere.example/export'), ORIGIN), false, 'other sites open normally');
  assert.equal(isDownloadLink(null, ORIGIN), false);
});

test('Add to calendar tries the app path before the iPhone one, and only for the app', () => {
  const source = readFileSync(new URL('../../resources/js/attendee/calendar.js', import.meta.url), 'utf8');
  const app = source.indexOf('if (isNativeApp())');
  const apple = source.indexOf('if (isAppleMobile() && navigator.onLine');
  assert.ok(app > 0 && apple > app, 'the app branch comes first');
});

test('both bundles install the download handler', () => {
  assert.match(readFileSync(new URL('../../resources/js/attendee/native-bridge.js', import.meta.url), 'utf8'), /installDownloads\(\)/);
  assert.match(readFileSync(new URL('../../resources/js/organizer-native.js', import.meta.url), 'utf8'), /installDownloads\(\)/);
});
