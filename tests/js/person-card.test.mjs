// node --test tests/js  — the card made from public info for an organizer,
// speaker, volunteer or microsponsor on the attendee list (person-card.js).
import assert from 'node:assert/strict';
import { test } from 'node:test';

globalThis.window = {};
globalThis.location = { href: 'https://campbuddy.club/event/x/explore', origin: 'https://campbuddy.club', search: '' };
globalThis.document = { getElementById: () => null, querySelectorAll: () => [] };

const { cardFilename, hasPublicCard, personCardContent, qrLinkFor } = await import('../../resources/js/attendee/person-card.js');

test('only people with a role get a card from public data', () => {
  assert.equal(hasPublicCard({ roles: ['volunteer'] }), true);
  assert.equal(hasPublicCard({ roles: [] }), false);
  assert.equal(hasPublicCard({}), false);
});

test('the QR opens LinkedIn first, then a website, then X — web addresses only', () => {
  assert.deepEqual(qrLinkFor({ links: [{ type: 'twitter', url: 'https://x.com/a' }, { type: 'linkedin', url: 'https://linkedin.com/in/a' }] }), { type: 'linkedin', url: 'https://linkedin.com/in/a' });
  assert.deepEqual(qrLinkFor({ links: [{ type: 'twitter', url: 'https://x.com/a' }, { type: 'website', url: 'https://a.dev' }] }), { type: 'website', url: 'https://a.dev' });
  assert.equal(qrLinkFor({ links: [{ type: 'linkedin', url: 'javascript:alert(1)' }] }), null);
  assert.equal(qrLinkFor({}), null);
});

test('the card: name, roles as one line, a speaker\'s first talk in the bubble', () => {
  const c = personCardContent({ name: 'Rahul', roles: ['speaker', 'organizer'], talks: ['Blocks for everyone', 'Panel'], links: [{ type: 'linkedin', url: 'https://linkedin.com/in/r' }] });
  assert.equal(c.name, 'Rahul');
  assert.deepEqual(c.roleLines, ['Organizer · Speaker']);
  assert.equal(c.askMe, 'Blocks for everyone');
  assert.deepEqual(c.tags, []);
  assert.equal(c.scan, 'Scan to connect on LinkedIn');

  const v = personCardContent({ name: 'Pooja', roles: ['volunteer'] });
  assert.equal(v.askMe, '');
  assert.equal(v.scan, '', 'no link: no QR and no "Scan to …"');
});

test('file names are plain and safe', () => {
  assert.equal(cardFilename('Ravi Kumar Sharma'), 'campbuddy-card-ravi-kumar-sharma.png');
  assert.equal(cardFilename('José Ñandú'), 'campbuddy-card-jose-nandu.png');
  assert.equal(cardFilename('राम'), 'campbuddy-card-person.png');
});
