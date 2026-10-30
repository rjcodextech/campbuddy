// node --test tests/js  — the Social media studio's captions (social-captions.js): one each for Instagram,
// LinkedIn and X, inside each network's limits, and put together exactly like App\Support\SocialCaptions.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

globalThis.document = { getElementById: () => null, querySelector: () => null, querySelectorAll: () => [] };
const { captionStats, captionTags, captionsText, forReel, LIMITS, makeCaptions, MAX_TAGS, names, ownTag, tagCount, xLength } = await import('../../resources/js/social-captions.js');

// Written by App\Support\SocialCaptions (tests/Unit/SocialCaptionsTest.php reads the same file).
const cases = JSON.parse(readFileSync(new URL('../Fixtures/social-captions.json', import.meta.url), 'utf8'));

/** Every network's limits, and no address in an Instagram caption (it wouldn't open). */
function assertInLimits(captions, label) {
  assert.ok(xLength(captions.x) <= LIMITS.x, `${label}: X ${xLength(captions.x)} > 280`);
  assert.ok([...captions.instagram].length <= LIMITS.instagram, `${label}: Instagram too long`);
  assert.ok([...captions.linkedin].length <= LIMITS.linkedin, `${label}: LinkedIn too long`);
  assert.ok(tagCount(captions.instagram) <= MAX_TAGS.instagram, `${label}: Instagram has ${tagCount(captions.instagram)} hashtags`);
  assert.ok(tagCount(captions.linkedin) <= MAX_TAGS.linkedin, `${label}: LinkedIn has ${tagCount(captions.linkedin)} hashtags`);
  assert.ok(!/https?:\/\//.test(captions.instagram), `${label}: a link in the Instagram caption`);
  for (const [key, text] of Object.entries(captions)) {
    assert.ok(!captionStats(key, text).over, `${label}: ${key} over its limit`);
    assert.match(text, /#CampBuddy/, `${label}: ${key} has #CampBuddy`);
  }
}

test('the browser puts captions together exactly like the server', () => {
  assert.ok(cases.length >= 7);
  for (const c of cases) assert.deepEqual(makeCaptions(c.parts, c.hashtags), c.captions, c.name);
});

test('every caption is inside its network’s limits', () => {
  for (const c of cases) assertInLimits(c.captions, c.name);
  // A huge one: a long event name, 300 talks, 500 names, the tagline.
  const long = makeCaptions({
    opening: 'Dil se Dilli, code se community.',
    hook: `Sat 3 Oct at ${'WordCamp Very Long City Name '.repeat(4)}2026:`,
    lines: Array.from({ length: 300 }, (_, i) => `${i}:00 PM  A talk with a rather long title number ${i} (Someone Long-Named and Another Person)`),
    body: 'Save your picks and get a reminder before each talk, even offline.',
    extra: `${names(Array.from({ length: 500 }, (_, i) => `Person Number ${i}`))}.`,
    cta: 'Plan your day in CampBuddy',
    link: 'https://campbuddy.club/event/x?utm_content=today',
  }, '#WordCamp #WordPress #WCVeryLong #WCVeryLong2026');
  assertInLimits(long, 'huge');
  assert.match(long.linkedin, /^Dil se Dilli/, 'the long ones keep the tagline');
});

test('Instagram: 5 hashtags in all, its own counted too; the QR instead of a link', () => {
  const moments = cases.find((c) => c.name === 'the event hashtag in the words').captions;
  assert.equal(tagCount(moments.instagram), 5);
  assert.equal(moments.instagram.match(/#WCTest/g).length, 1, 'not said twice');
  assert.match(cases[0].captions.instagram, /Plan your days in CampBuddy: scan the QR on the card, or open campbuddy\.club\./);
  const reel = cases.find((c) => c.name === 'a reel with a web address').captions;
  assert.match(reel.instagram, /scan the QR at the end of the reel/);
  assert.match(reel.instagram, / wpsimplified\.in\n/, 'an address written out, without https://');
  assert.match(reel.linkedin, /https:\/\/wpsimplified\.in/, 'LinkedIn keeps the link');
});

test('X: the hashtags come first; a fuller first line only when it fits with them; lines while they fit', () => {
  const speaker = cases.find((c) => c.name === 'a speaker with a long talk').captions;
  assert.match(speaker.x, /^Aachal Pardeshi Venkataraman is speaking at WordCamp Test 2026\.\n\nGet a reminder/, 'the whole name, the talk left out rather than cut');
  assert.match(speaker.x, /#WCTest #CampBuddy #WPSimplified$/);
  assert.match(speaker.linkedin, /Talk: "Performance budgets/, 'the long captions keep the talk');
  const day = cases.find((c) => c.name === 'a day of talks').captions;
  assert.ok(day.x.includes('Talk number 1 ') && !day.x.includes('Talk number 14'), 'as many talks as fit, in order');
  assert.ok(day.linkedin.includes('Talk number 14'), 'LinkedIn has them all');
  assert.ok(!/…/.test(Object.values(day).join('')), 'nothing cut');
  const tagline = cases[0].captions;
  assert.match(tagline.x, /^Padharo mhare des\.\n\nSave the date/, 'the tagline on X too, when there is room');
});

test('X counts like X: a link 23 (with or without https://), an emoji 2, a dash 1', () => {
  assert.equal(xLength('https://campbuddy.club/event/a-very-long-slug?utm_source=campbuddy&utm_content=x'), 23);
  assert.equal(xLength('wpsimplified.in'), 23);
  assert.equal(xLength('see campbuddy.club now'), 4 + 23 + 4);
  assert.equal(xLength('🎉'), 2);
  assert.equal(xLength('3–4 Oct'), 7);
  assert.equal(xLength('नमस्ते'), 6, 'Devanagari is 1 a character');
  assert.ok(captionStats('x', 'x'.repeat(281)).over);
  assert.ok(!captionStats('x', 'x'.repeat(280)).over);
  assert.ok(captionStats('instagram', '#a #b #c #d #e #f').over, 'six hashtags is too many for Instagram');
  assert.ok(!captionStats('linkedin', '#a #b #c #d #e #f').over, 'LinkedIn has no such cap; only its length');
});

test('names are whole and joined like a sentence; tags from the event’s own', () => {
  assert.equal(names(['Rahul Sharma']), 'Rahul Sharma');
  assert.equal(names(['Rahul Sharma', 'Asha Rao']), 'Rahul Sharma and Asha Rao');
  assert.equal(names(['A', ' B ', '', 'C']), 'A, B and C');
  assert.equal(ownTag('#WordCamp #WordPress #WCRajasthan #WCRajasthan2026'), '#WCRajasthan');
  assert.deepEqual(captionTags('#WordCamp #WordPress #WCRajasthan #WCRajasthan2026'), ['#WCRajasthan', '#WordCamp', '#WordPress', '#CampBuddy', '#WPSimplified']);
  assert.deepEqual(captionTags(''), ['#WordCamp', '#WordPress', '#CampBuddy', '#WPSimplified']);
});

test('a post’s captions for its reel, and the captions file', () => {
  const reel = forReel(cases[0].captions);
  assert.match(reel.instagram, /scan the QR at the end of the reel/);
  assert.equal(reel.x, cases[0].captions.x);
  const text = captionsText({ instagram: 'I', linkedin: 'L', x: 'X' });
  assert.equal(text, '--- Instagram ---\nI\n\n--- LinkedIn ---\nL\n\n--- X ---\nX');
});
