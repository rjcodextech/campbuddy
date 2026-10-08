// node --test tests/js  — "Show my Camp Card on the attendee list" (card-share.js):
// what leaves the phone, and when.
import assert from 'node:assert/strict';
import { test } from 'node:test';

globalThis.window = {};
globalThis.location = { href: 'https://campbuddy.club/event/x/camp-card', origin: 'https://campbuddy.club', search: '' };
globalThis.document = { getElementById: () => null, querySelectorAll: () => [] };

const { canShare, matchNames, sharedFields } = await import('../../resources/js/attendee/card-share.js');
const { hasOwnCard, ownCardContent } = await import('../../resources/js/attendee/person-card.js');

const resolveLink = (field, value) => {
  if (!value) return null;
  if (field === 'twitter') return `https://x.com/${value.replace(/^@/, '')}`;
  return value;
};

const card = {
  name: 'Ada', role: 'Engineer', company: 'Acme', city: 'Jaipur', interests: ['Blocks', ''], askMeAbout: 'Multisite',
  linkedin: 'https://linkedin.com/in/ada', website: 'https://ada.dev', visibleFields: ['role', 'interests'],
};

test('only the fields shown on the card, plus the QR link, are shared', () => {
  assert.deepEqual(sharedFields(card, 'linkedin', resolveLink), {
    role: 'Engineer',
    interests: ['Blocks'],
    qr: { type: 'linkedin', url: 'https://linkedin.com/in/ada' },
  });
  assert.deepEqual(sharedFields({ ...card, visibleFields: [] }, 'twitter', resolveLink), {}, 'no X handle filled: no QR');
  assert.deepEqual(sharedFields({ ...card, twitter: '@ada', visibleFields: [] }, 'twitter', resolveLink), { qr: { type: 'twitter', url: 'https://x.com/ada' } });
});

test('a card needs a name and a QR link before it can be shared', () => {
  assert.equal(canShare(card, 'linkedin', resolveLink), true);
  assert.equal(canShare({ ...card, name: ' ' }, 'linkedin', resolveLink), false);
  assert.equal(canShare(card, null, resolveLink), false);
  assert.equal(canShare(null, 'linkedin', resolveLink), false);
});

test('the name search wants two letters and shows a few', () => {
  const list = Array.from({ length: 20 }, (_, i) => ({ id: i, name: `Asha ${i}` }));
  assert.deepEqual(matchNames(list, 'a'), []);
  assert.equal(matchNames(list, 'ash').length, 8);
  assert.deepEqual(matchNames(list, 'sha 1').map((e) => e.id), [1, 10, 11, 12, 13, 14, 15, 16]);
});

test('on the attendee list, their own card shows just what they shared', () => {
  const entry = { name: 'Ada Lovelace', camp_card: { role: 'Engineer', interests: ['Blocks'], qr: { type: 'website', url: 'https://ada.dev' } } };
  assert.equal(hasOwnCard(entry), true);
  assert.equal(hasOwnCard({ name: 'x', camp_card: null }), false);
  const c = ownCardContent(entry);
  assert.equal(c.name, 'Ada Lovelace');
  assert.deepEqual(c.roleLines, ['Engineer']);
  assert.deepEqual(c.tags, ['Blocks']);
  assert.equal(c.scan, 'Scan to visit my website');
});
