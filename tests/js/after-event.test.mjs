// node --test tests/js  — after a WordCamp: what goes into "Save my day as
// PDF", who gets the thank-you card, and when it opens.
import assert from 'node:assert/strict';
import { test } from 'node:test';

globalThis.window = {};
globalThis.location = { href: 'https://campbuddy.club/event/x', origin: 'https://campbuddy.club', search: '' };
globalThis.document = { getElementById: () => null, querySelectorAll: () => [] };

const { pdfModel, pdfSafe, hasEventData, needsPictures } = await import('../../resources/js/attendee/export-pdf.js');
const { shouldOpen } = await import('../../resources/js/attendee/thank-you.js');

const dump = {
  bookmarks: [
    { eventId: 7, sessionId: 2, title: 'Closing Remarks', startMs: 2000, status: null },
    { eventId: 7, sessionId: 1, title: 'YouTube + WordPress in the Age of AI', startMs: 1000, status: 'attended' },
    { eventId: 9, sessionId: 5, title: 'Another WordCamp talk', startMs: 500 },
  ],
  meetings: [
    { eventId: 7, personKey: 'r1', name: 'Asha Rao', sub: 'Developer', status: 'met', note: 'Ask about Polyglots', at: '2026-10-03T09:00:00.000Z', links: [{ url: 'https://www.linkedin.com/in/asha/', type: 'linkedin' }, 'https://profiles.wordpress.org/asha/', 'javascript:alert(1)'] },
    { eventId: 7, personKey: 'r4', name: 'Ravi', status: null },
    { eventId: 7, personKey: 'r2', name: 'Hidden Person', status: 'skipped' },
    { eventId: 7, personKey: 'r3', name: 'Old Copy', mergedInto: 'r1' },
  ],
  questProgress: [{ eventId: 7, questId: 3 }, { eventId: 7, questId: 99 }],
  metHistory: [],
  kv: { campCard: { name: 'Sunil', role: 'Builder', photo: 'data:image/png;base64,xx', visibleFields: ['name'], interests: ['AI', 'Blocks'] } },
};

test('the PDF has this WordCamp only: sessions in time order with status, people, quests, Camp Card', () => {
  const model = pdfModel(dump, { id: '7', name: 'WordCamp Rajasthan 2026', questTitles: { 3: 'First Hello' } }, (ms) => `t${ms}`);

  assert.equal(model.title, 'My WordCamp Rajasthan 2026');
  assert.deepEqual(model.sections.map((s) => s.heading), ['Sessions I saved (2)', 'People I planned to meet (2)', 'Quests I completed (2)', 'My Camp Card']);
  assert.deepEqual(model.sections[0].rows, ['t1000  ·  YouTube + WordPress in the Age of AI  ·  Attended', 't2000  ·  Closing Remarks']);
  // Each person's note, planned time and links sit under their name (one row, extra lines).
  assert.deepEqual(model.sections[1].rows, [
    `Asha Rao  ·  Developer  ·  Met
Note: Ask about Polyglots
Planned: t${Date.parse('2026-10-03T09:00:00.000Z')}
Links: linkedin.com/in/asha, profiles.wordpress.org/asha`,
    'Ravi',
  ]);
  assert.deepEqual(model.sections[2].rows, ['First Hello', 'Quest #99']);
  assert.deepEqual(model.sections[3].rows, ['Name: Sunil', 'Role: Builder', 'Interests: AI, Blocks']);
});

test('nothing saved means no PDF sections', () => {
  assert.deepEqual(pdfModel({ kv: {} }, { id: 1, name: 'X' }, String).sections, []);
});

test('PDF text keeps Latin letters and accents, drops what the built-in font cannot draw', () => {
  assert.equal(pdfSafe('Pink City – Jaipur’s “AI” future…'), 'Pink City - Jaipur\'s "AI" future...');
  assert.equal(pdfSafe('José 🎉 সিলেট Café'), 'José Café');
});

test('the thank-you card is only for phones with something for that event', () => {
  assert.equal(hasEventData(dump, 7), true);
  assert.equal(hasEventData(dump, 9), true);
  assert.equal(hasEventData(dump, 12), false);
  assert.equal(hasEventData({ kv: { 'discovery:12': { discoveryId: 'x' } } }, 12), true);
});

test('the card opens from day 3, once, or at once from the push', () => {
  const base = { daysSince: 3, fromDay: 3, forced: false, seen: false, hasData: true };
  assert.equal(shouldOpen(base), true);
  assert.equal(shouldOpen({ ...base, daysSince: 2 }), false);
  assert.equal(shouldOpen({ ...base, daysSince: NaN }), false);
  assert.equal(shouldOpen({ ...base, seen: true }), false);
  assert.equal(shouldOpen({ ...base, hasData: false }), false);
  assert.equal(shouldOpen({ ...base, daysSince: 1, forced: true, hasData: false }), true);
  assert.equal(shouldOpen({ ...base, forced: true, seen: true }), false);
});

test('Latin-only data stays a text PDF; any other script switches to the page-picture PDF', () => {
  const latin = { title: 'My WordCamp Rajasthan 2026', sections: [{ heading: 'Sessions I saved (1)', rows: ['Sat · “AI” – José’s talk…'] }] };
  assert.equal(needsPictures(latin), false);
  for (const text of ['मिलना है – Polyglots', 'বাংলা নোট', 'اردو نوٹ', 'தமிழ்', '中文', 'Great talk 🎉']) {
    assert.equal(needsPictures({ ...latin, sections: [{ heading: 'People', rows: [`Asha\nNote: ${text}`] }] }), true, text);
  }
});
