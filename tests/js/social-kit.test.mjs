// node --test tests/js  — the Social media page's helpers (social-kit.js) and its ZIP writer (zip-store.js).
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { crc32, zipStore } from '../../resources/js/zip-store.js';

globalThis.document = { getElementById: () => null, querySelector: () => null, querySelectorAll: () => [] };
const { contrastRatio, daysToGo, personCta, photonUrl, eventLook, mix, paletteFromSite, readable, paginate, paletteFromPixels, personHeadline, postContent, qrLink, roleColor, sessionsOfDay, shareLinks, slugify, spotlightCaption, textOn, todayCaption } = await import('../../resources/js/social-kit.js');

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

test('the palette comes from the logo: all four colours', () => {
  const px = [];
  const push = (rgb, n) => { for (let i = 0; i < n; i++) px.push(...rgb, 255); };
  push([255, 255, 255], 500); // white background: ignored
  push([230, 80, 40], 300); // orange-red: main
  push([20, 50, 140], 120); // blue: accent
  push([128, 128, 128], 200); // grey: ignored
  const palette = paletteFromPixels(new Uint8ClampedArray(px));
  assert.deepEqual(Object.keys(palette).sort(), ['ink', 'paper', 'primary', 'secondary']);
  const [r, g, b] = [1, 3, 5].map((i) => parseInt(palette.primary.slice(i, i + 2), 16));
  assert.ok(r > g && r > b, 'the main colour is the red');
  assert.equal(textOn(palette.primary, palette.ink, palette.paper), palette.paper, 'the light colour reads on it');
  assert.equal(textOn(palette.paper, palette.ink, palette.paper), palette.ink, 'the text colour reads on the light one');
  const [br, , bb] = [1, 3, 5].map((i) => parseInt(palette.secondary.slice(i, i + 2), 16));
  assert.ok(bb > br, 'the blue is the accent');
  assert.equal(paletteFromPixels(new Uint8ClampedArray([255, 255, 255, 255, 0, 0, 0, 255])), null, 'no real colour: keep the defaults');
});

test('text colour, names, share links, card headline', () => {
  assert.equal(textOn('#fff8ee'), '#0f172a', 'dark text is the brand ink (Daylight 2.0)');
  assert.equal(textOn('#0c2343'), '#ffffff');
  assert.equal(textOn('#0c2343', '#231f20', '#fff8ee'), '#fff8ee', 'light text is the brand light colour, not white');
  assert.equal(slugify('Aachal Pardeshi'), 'aachal-pardeshi');
  assert.equal(slugify('José Ñandú'), 'jose-nandu');
  const links = Object.fromEntries(shareLinks('x'.repeat(400), 'https://rajasthan.wordcamp.org/2026'));
  assert.ok(links.LinkedIn.includes(encodeURIComponent('https://rajasthan.wordcamp.org/2026')));
  assert.ok(decodeURIComponent(links.X.split('text=')[1]).length <= 270, 'X text fits');
  assert.equal(personHeadline({ roles: ['speaker'], role_labels: ['Speaker'] }, { short: 'WordCamp Delhi' }), 'Speaker at WordCamp Delhi');
  assert.equal(personHeadline({ roles: [], role_labels: [] }, { short: 'WordCamp Delhi' }), 'See you at WordCamp Delhi');
});

const kit = {
  event: { name: 'WordCamp Test 2026', qr: 'https://campbuddy.club/event/x?utm_source=campbuddy&utm_medium=social&utm_campaign=x', hashtags: '#WordCamp #WordPress #WCTest' },
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
  assert.equal(postContent({ key: 'app' }, f, kit).body.type, 'features');
  assert.equal(postContent({ key: 'contributor' }, f, kit).body.type, 'features', 'no tables: what people do on Contributor Day, never an empty card');
  assert.deepEqual(postContent({ key: 'venue', body: 'points', points: ['A', 'B'] }, f, kit).body, { type: 'features', items: ['A', 'B'] }, 'the newer posts list their own points');
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
  assert.match(spotlightCaption(kit, kit.schedule.sessions[1]), /utm_content=spotlight/);
  assert.match(spotlightCaption(kit, kit.schedule.sessions[1]), /CampBuddy/);
});

test('content that does not fit is split over cards, never cut or overlapped', () => {
  const items = Array.from({ length: 7 }, (_, i) => ({ time: '1', title: String(i) }));
  const pages = paginate({ key: 'today', body: { type: 'list', items } }, 300, 1350);
  assert.deepEqual(pages.map((p) => p.body.items.length), [2, 2, 2, 1], '108 px a row: two to a card');
  assert.deepEqual(pages.flatMap((p) => p.body.items.map((i) => i.title)), ['0', '1', '2', '3', '4', '5', '6'], 'nothing lost, nothing twice');
  const people = Array.from({ length: 10 }, (_, i) => ({ name: String(i) }));
  assert.deepEqual(paginate({ body: { type: 'photos', people } }, 430, 1350).map((p) => p.body.people.length), [8, 2], 'two rows of four');
  const logos = Array.from({ length: 10 }, (_, i) => ({ name: String(i) }));
  assert.deepEqual(paginate({ body: { type: 'logos', logos } }, 400, 1350).map((p) => p.body.logos.length), [6, 4], 'as even as the grid allows: never one lonely logo on the last card');
  assert.equal(paginate({ body: { type: 'none' } }, 10, 1350).length, 1);
  assert.equal(paginate({ body: { type: 'list', items: [] } }, 300, 1350)[0].body.type, 'none');
});

test('every card links to the event in CampBuddy, tagged with its post or role', () => {
  const url = new URL(qrLink(kit.event.qr, 'people-speaker'));
  assert.equal(url.searchParams.get('utm_source'), 'campbuddy');
  assert.equal(url.searchParams.get('utm_campaign'), 'x');
  assert.equal(url.searchParams.get('utm_content'), 'people-speaker');
});

test('each event has its own look, always the same for that event', () => {
  const a = eventLook(123);
  const b = eventLook(123);
  assert.deepEqual([a.motif, a.texture, a.style, a.design], [b.motif, b.texture, b.style, b.design]);
  const looks = new Set([1, 2, 3, 4, 5, 6, 7, 8].map((seed) => JSON.stringify(((l) => [l.motif, l.style, l.texture])(eventLook(seed)))));
  assert.ok(looks.size >= 6, 'different events, different looks');
});

test('role colours and mixing', () => {
  const colors = { primary: '#7a1f2b', secondary: '#e0a11b', ink: '#231f20', paper: '#fff8ee' };
  assert.equal(roleColor({ roles: ['organizer'] }, colors), '#7a1f2b');
  assert.equal(roleColor({ roles: ['speaker'] }, colors), '#e0a11b');
  assert.equal(roleColor({ roles: ['volunteer'] }, colors), mix('#7a1f2b', '#231f20', 0.45), 'a tone of the brand, not a new colour');
  assert.equal(mix('#000000', '#ffffff', 0.5), '#808080');
});

test('a palette from the site\'s own colours, always readable', () => {
  const site = [
    { hex: '#c33a19' }, { hex: '#e39d1c' }, { hex: '#2a1810' }, { hex: '#5c4332' }, { hex: '#faf7f0' }, { hex: '#0d2343' }, { hex: '#fbeedd' },
  ];
  const p = paletteFromSite(site);
  assert.equal(p.ink, '#2a1810', 'its darkest colour is the text');
  assert.ok(['#faf7f0', '#fbeedd'].includes(p.paper), 'its lightest tinted colour is the light one');
  assert.ok(contrastRatio(p.ink, p.paper) >= 7);
  assert.ok(contrastRatio(p.paper, p.primary) >= 4.5, 'light text reads on the main colour');
  assert.ok(Math.max(contrastRatio(p.ink, p.secondary), contrastRatio(p.paper, p.secondary)) >= 4.5, 'labels read on the accent');
  assert.equal(paletteFromSite([{ hex: '#ffffff' }, { hex: '#111111' }]), null, 'no real colours: none');
});

test('readable() fixes a palette by deepening, never by adding a colour', () => {
  const p = readable({ primary: '#f2c94c', secondary: '#f2c94c', ink: '#777777', paper: '#ffffff' });
  assert.ok(contrastRatio(p.ink, p.paper) >= 7);
  assert.ok(contrastRatio(p.paper, p.primary) >= 4.5);
  assert.ok(Math.max(contrastRatio(p.ink, p.secondary), contrastRatio(p.paper, p.secondary)) >= 4.5);
});

test('the other site colours decorate and give each role its own tone', () => {
  const p = paletteFromSite([{ hex: '#c33a19' }, { hex: '#e39d1c' }, { hex: '#0d2343' }, { hex: '#1f8a4c' }, { hex: '#2a1810' }, { hex: '#faf7f0' }]);
  assert.ok(p.extras.length >= 1, 'colours beyond the four are kept');
  assert.ok(p.extras.every((hex) => ![p.primary, p.secondary].includes(hex)));
  const colors = { ...p };
  assert.equal(roleColor({ roles: ['volunteer'] }, colors), p.extras[0], 'a volunteer takes one of the site colours');
  assert.equal(roleColor({ roles: ['organizer'] }, colors), p.primary);
});

test('the countdown counts the days at the venue when the card is drawn', () => {
  const kit = { event: { starts: '2026-12-12', timezone: 'Asia/Kolkata' } };
  assert.equal(daysToGo(kit, new Date('2026-10-10T06:00:00Z')), 63);
  assert.equal(daysToGo(kit, new Date('2026-10-10T19:00:00Z')), 62, 'after midnight in Delhi it is the next day there');
  assert.equal(daysToGo(kit, new Date('2026-12-12T06:00:00Z')), null, 'the day itself: no countdown');
  assert.equal(daysToGo({ event: {} }), null);
});

test('a people card says why to find the person, by role, with their whole name', () => {
  const kit = { short: 'WordCamp Delhi' };
  assert.equal(personCta({ name: 'Anup Kumar', roles: ['organizer'] }, kit), 'Say hi to Anup Kumar, one of the people making WordCamp Delhi happen');
  assert.equal(personCta({ name: 'Amit Singh', roles: ['speaker'], talk: 'Closing' }, kit), "Save Amit Singh's talk and get a reminder");
  assert.equal(personCta({ name: 'Asha', roles: ['volunteer'] }, kit), 'Thank Asha, a volunteer at WordCamp Delhi');
  assert.equal(personCta({ name: 'Kim  Lee ', roles: ['table_lead'] }, kit), "Join Kim Lee's table on Contributor Day");
  assert.equal(personCta({ name: 'Ravi', roles: [] }, kit), 'Meet Ravi at WordCamp Delhi');
});

test('a WordCamp picture the server could not copy comes from WordPress.com’s CDN instead (never an SVG, never another host)', () => {
  assert.equal(photonUrl('https://delhi.wordcamp.org/2026/files/2026/10/nexgi.webp'), 'https://i0.wp.com/delhi.wordcamp.org/2026/files/2026/10/nexgi.webp');
  assert.equal(photonUrl('https://i0.wp.com/canada.wordcamp.org/2026/files/a.png?fit=768%2C210&ssl=1'), 'https://i0.wp.com/canada.wordcamp.org/2026/files/a.png?fit=768%2C210&ssl=1');
  assert.equal(photonUrl('https://example.wordpress.com/files/logo.png'), 'https://i0.wp.com/example.wordpress.com/files/logo.png');
  assert.equal(photonUrl('https://delhi.wordcamp.org/2026/files/logo.svg'), null);
  assert.equal(photonUrl('https://evil.example/logo.png'), null);
  assert.equal(photonUrl('/storage/art/1.png'), null);
  assert.equal(photonUrl(null), null);
});
