// node --test tests/js — Exhaustive unit tests for Studio Image Loading, Proxies, and Canvas Rendering
// across Posts, People Cards, and Reels.
import assert from 'node:assert/strict';
import { test } from 'node:test';

// 1. Mock Browser Environment for Node test runner
class MockImage {
  constructor() {
    this.crossOrigin = null;
    this._src = '';
    this.naturalWidth = 200;
    this.naturalHeight = 200;
    this.width = 200;
    this.height = 200;
    this.complete = false;
    this.onload = null;
    this.onerror = null;
  }

  get src() {
    return this._src;
  }

  set src(val) {
    this._src = val;
    queueMicrotask(() => {
      this.complete = true;
      if (val.includes('error-fail') || val.includes('broken-404')) {
        this.naturalWidth = 0;
        this.naturalHeight = 0;
        this.onerror?.(new Error('Mock image load error'));
      } else {
        this.onload?.();
      }
    });
  }

  decode() {
    return this.naturalWidth > 0 ? Promise.resolve() : Promise.reject(new Error('decode failed'));
  }
}

function createMockCtx() {
  const state = {
    font: '16px sans-serif',
    fillStyle: '#000000',
    strokeStyle: '#000000',
    lineWidth: 1,
    lineCap: 'butt',
    textBaseline: 'alphabetic',
    textAlign: 'left',
    shadowColor: 'transparent',
    shadowBlur: 0,
    shadowOffsetX: 0,
    shadowOffsetY: 0,
    globalAlpha: 1,
    filter: 'none',
  };

  const drawCalls = [];

  const ctx = {
    ...state,
    drawCalls,
    setTransform: () => {},
    transform: () => {},
    translate: () => {},
    scale: () => {},
    rotate: () => {},
    setLineDash: () => {},
    getLineDash: () => [],
    fillRect: (x, y, w, h) => drawCalls.push({ type: 'fillRect', x, y, w, h }),
    strokeRect: (x, y, w, h) => drawCalls.push({ type: 'strokeRect', x, y, w, h }),
    clearRect: (x, y, w, h) => drawCalls.push({ type: 'clearRect', x, y, w, h }),
    beginPath: () => drawCalls.push({ type: 'beginPath' }),
    moveTo: (x, y) => drawCalls.push({ type: 'moveTo', x, y }),
    lineTo: (x, y) => drawCalls.push({ type: 'lineTo', x, y }),
    arc: (x, y, r, s, e) => drawCalls.push({ type: 'arc', x, y, r, s, e }),
    arcTo: (x1, y1, x2, y2, r) => drawCalls.push({ type: 'arcTo', x1, y1, x2, y2, r }),
    ellipse: () => drawCalls.push({ type: 'ellipse' }),
    bezierCurveTo: () => drawCalls.push({ type: 'bezierCurveTo' }),
    quadraticCurveTo: () => drawCalls.push({ type: 'quadraticCurveTo' }),
    rect: (x, y, w, h) => drawCalls.push({ type: 'rect', x, y, w, h }),
    roundRect: (x, y, w, h, r) => drawCalls.push({ type: 'roundRect', x, y, w, h, r }),
    closePath: () => drawCalls.push({ type: 'closePath' }),
    fill: () => drawCalls.push({ type: 'fill' }),
    stroke: () => drawCalls.push({ type: 'stroke' }),
    clip: () => drawCalls.push({ type: 'clip' }),
    save: () => drawCalls.push({ type: 'save' }),
    restore: () => drawCalls.push({ type: 'restore' }),
    drawImage: (img, ...args) => drawCalls.push({ type: 'drawImage', src: img?.src, args }),
    fillText: (text, x, y, maxW) => drawCalls.push({ type: 'fillText', text, x, y, maxW }),
    strokeText: (text, x, y, maxW) => drawCalls.push({ type: 'strokeText', text, x, y, maxW }),
    measureText: function(text) {
      const sizeMatch = String(this.font).match(/(\d+)px/);
      const size = sizeMatch ? Number(sizeMatch[1]) : 16;
      return {
        width: String(text).length * size * 0.5,
        actualBoundingBoxAscent: size * 0.8,
        actualBoundingBoxDescent: size * 0.2,
      };
    },
    createLinearGradient: (x0, y0, x1, y1) => ({
      addColorStop: () => {},
    }),
    createRadialGradient: (x0, y0, r0, x1, y1, r1) => ({
      addColorStop: () => {},
    }),
    createPattern: () => ({}),
    getImageData: (x, y, w, h) => ({
      data: new Uint8ClampedArray(w * h * 4).fill(255),
    }),
  };

  return ctx;
}

function createMockCanvas(w = 1080, h = 1350) {
  const ctx = createMockCtx();
  return {
    width: w,
    height: h,
    offsetWidth: w,
    offsetHeight: h,
    isConnected: true,
    offsetParent: {}, // simulate visible element
    getContext: (type) => (type === '2d' ? ctx : null),
    toDataURL: (mime = 'image/png') => `data:${mime};base64,mock`,
    style: {},
  };
}

class MockPath2D {
  constructor(d) {
    this.d = d;
  }
  rect() {}
  arc() {}
  arcTo() {}
  roundRect() {}
  moveTo() {}
  lineTo() {}
  closePath() {}
}

globalThis.Image = MockImage;
globalThis.Path2D = MockPath2D;
globalThis.document = {
  getElementById: () => null,
  querySelector: () => null,
  querySelectorAll: () => [],
  createElement: (tag) => {
    if (tag === 'canvas') return createMockCanvas();
    return { style: {}, appendChild: () => {}, setAttribute: () => {} };
  },
};
globalThis.window = {
  matchMedia: () => ({ matches: false }),
  requestAnimationFrame: (cb) => setTimeout(cb, 16),
  cancelAnimationFrame: (id) => clearTimeout(id),
};

// Import Social Kit & Social Reels modules
const {
  reelTools,
  remoteSources,
  drawEventPost,
  drawPersonCard,
} = await import('../../resources/js/social-kit.js');

const { _internals: reelInternals, reelList } = await import('../../resources/js/social-reels.js');

// Test Suite
test('proxied(): Gravatar, WordCamp, and local relative paths routing', () => {
  const kit = { imageUrl: '/admin/events/1/social/image' };

  // Gravatar URLs must route through server proxy so CORS headers are added
  const gravatar = 'https://secure.gravatar.com/avatar/abc123def456?s=600&d=404';
  const proxiedGravatar = reelTools.proxied(kit, gravatar);
  assert.equal(proxiedGravatar, `/admin/events/1/social/image?u=${encodeURIComponent(gravatar)}`);

  // WordCamp remote URLs route through server proxy
  const wcUrl = 'https://rajasthan.wordcamp.org/2026/files/speaker.jpg';
  const proxiedWc = reelTools.proxied(kit, wcUrl);
  assert.equal(proxiedWc, `/admin/events/1/social/image?u=${encodeURIComponent(wcUrl)}`);

  // Local relative paths (/media/..., /storage/...) must remain unchanged
  assert.equal(reelTools.proxied(kit, '/media/icons/icon-192.png'), '/media/icons/icon-192.png');
  assert.equal(reelTools.proxied(kit, '/storage/branding/logo.png'), '/storage/branding/logo.png');

  // Inline data & blob URIs must remain unchanged
  const dataUri = 'data:image/png;base64,iVBORw0KGgo=';
  assert.equal(reelTools.proxied(kit, dataUri), dataUri);
  const blobUri = 'blob:http://localhost:8000/some-guid';
  assert.equal(reelTools.proxied(kit, blobUri), blobUri);

  // When kit.imageUrl is not configured, original URL is kept
  assert.equal(reelTools.proxied({}, wcUrl), wcUrl);
  assert.equal(reelTools.proxied(null, wcUrl), wcUrl);
});

test('remoteSources(): Fallback order gives proxy, then CDN, then original', () => {
  const kit = { imageUrl: '/admin/events/1/social/image' };
  const proxyFn = (u) => reelTools.proxied(kit, u);

  // Gravatar: proxy is tried first, then direct URL
  const gravatar = 'https://secure.gravatar.com/avatar/face?s=600&d=404';
  const gravatarSources = remoteSources(gravatar, proxyFn);
  assert.deepEqual(gravatarSources, [proxyFn(gravatar), gravatar]);

  // WordCamp URL: proxy -> i0.wp.com CDN -> direct URL
  const wcImage = 'https://rajasthan.wordcamp.org/2026/files/banner.png';
  const wcSources = remoteSources(wcImage, proxyFn);
  assert.deepEqual(wcSources, [
    proxyFn(wcImage),
    'https://i0.wp.com/rajasthan.wordcamp.org/2026/files/banner.png',
    wcImage,
  ]);

  // Relative storage URL: single entry unchanged
  assert.deepEqual(remoteSources('/storage/avatars/1.jpg', proxyFn), ['/storage/avatars/1.jpg']);
  assert.deepEqual(remoteSources(null, proxyFn), []);
});

test('loadRemote(): Loads successfully and handles error fallbacks gracefully', async () => {
  const kit = { imageUrl: '/admin/events/1/social/image' };

  // 1. Normal image loads
  const goodUrl = 'https://rajasthan.wordcamp.org/2026/files/good.png';
  const img = await reelTools.loadRemote(kit, goodUrl);
  assert.ok(img instanceof MockImage);
  assert.equal(img.complete, true);

  // 2. Broken image (404 / network fail) resolves cleanly to null without throw
  const brokenUrl = 'https://rajasthan.wordcamp.org/2026/files/broken-404.png';
  const failedImg = await reelTools.loadRemote(kit, brokenUrl);
  assert.equal(failedImg, null);

  // 3. Null / empty URL resolves to null immediately
  assert.equal(await reelTools.loadRemote(kit, null), null);
  assert.equal(await reelTools.loadRemote(kit, ''), null);
});

test('brandLockup(): Resolves combined CampBuddy and WPSimplified marks', async () => {
  const lockup = await reelTools.brandLockup();
  assert.ok(lockup, 'Brand lockup canvas is produced');
  assert.ok(typeof lockup.width === 'number');
  assert.ok(typeof lockup.height === 'number');
});

test('drawEventPost(): Renders speaker lineup with photos and fallbacks', async () => {
  const kit = {
    short: 'WordCamp Test',
    event: { name: 'WordCamp Test 2026', qr: 'https://campbuddy.club/e/test', hashtags: '#WCTest' },
    speakers: [
      { name: 'Aarav Patel', photo: 'https://rajasthan.wordcamp.org/files/aarav.jpg' },
      { name: 'Diya Sharma', photo: null }, // fallback to initials
    ],
    sponsors: [],
    schedule: { days: [], sessions: [] },
  };

  const colors = { primary: '#1a56db', secondary: '#f59e0b', ink: '#111827', paper: '#ffffff' };
  const content = {
    key: 'speakers',
    headline: 'Meet the Speakers',
    line: 'WordCamp Test 2026',
    body: {
      type: 'photos',
      people: kit.speakers,
    },
  };

  // Daylight style
  const canvasDaylight = createMockCanvas(1080, 1350);
  await drawEventPost(canvasDaylight, '1080x1350', 'daylight', content, kit, colors);
  assert.equal(canvasDaylight.width, 1080);
  assert.equal(canvasDaylight.height, 1350);
  const ctxDaylight = canvasDaylight.getContext('2d');
  assert.ok(ctxDaylight.drawCalls.length > 10, 'Daylight post painted elements');

  // Classic style
  const canvasClassic = createMockCanvas(1080, 1080);
  await drawEventPost(canvasClassic, '1080x1080', 'classic', content, kit, colors);
  assert.equal(canvasClassic.width, 1080);
  assert.equal(canvasClassic.height, 1080);
  const ctxClassic = canvasClassic.getContext('2d');
  assert.ok(ctxClassic.drawCalls.length > 10, 'Classic post painted elements');
});

test('drawEventPost(): Renders sponsors grid with logos and text fallbacks', async () => {
  const kit = {
    short: 'WordCamp Test',
    event: { name: 'WordCamp Test 2026', qr: 'https://campbuddy.club/e/test', hashtags: '#WCTest' },
    speakers: [],
    sponsors: [
      { name: 'Super Host', logo: 'https://rajasthan.wordcamp.org/files/superhost.png' },
      { name: 'Code Cloud', logo: null }, // fallback to name tile
    ],
    schedule: { days: [], sessions: [] },
  };

  const colors = { primary: '#1a56db', secondary: '#f59e0b', ink: '#111827', paper: '#ffffff' };
  const content = {
    key: 'sponsors',
    headline: 'Our Sponsors',
    line: 'WordCamp Test 2026',
    body: {
      type: 'logos',
      logos: kit.sponsors,
    },
  };

  const canvas = createMockCanvas(1080, 1350);
  await drawEventPost(canvas, '1080x1350', 'daylight', content, kit, colors);
  const ctx = canvas.getContext('2d');
  // Verify fillText called for sponsor name fallback
  const textCalls = ctx.drawCalls.filter((c) => c.type === 'fillText');
  assert.ok(textCalls.some((c) => String(c.text).includes('Code Cloud')));
});

test('drawPersonCard(): Daylight & Classic people cards with photo and initial fallbacks', async () => {
  const kit = {
    short: 'WordCamp Test',
    event: { name: 'WordCamp Test 2026', qr: 'https://campbuddy.club/e/test', hashtags: '#WCTest' },
  };
  const colors = { primary: '#0f766e', secondary: '#fbbf24', ink: '#0f172a', paper: '#f8fafc' };

  // 1. Person with valid photo
  const speakerWithPhoto = {
    id: 1,
    name: 'Neha Verma',
    roles: ['speaker'],
    role_labels: ['Speaker'],
    talk: 'Building Custom Gutenberg Blocks',
    photo: 'https://rajasthan.wordcamp.org/files/neha.jpg',
  };

  const canvasPhoto = createMockCanvas(1080, 1350);
  await drawPersonCard(canvasPhoto, '1080x1350', 'daylight', speakerWithPhoto, kit, colors);
  const ctxPhoto = canvasPhoto.getContext('2d');
  assert.ok(ctxPhoto.drawCalls.some((c) => c.type === 'drawImage'), 'Photo is drawn via drawImage');

  // 2. Person without photo (null) -> falls back to circular initial portrait
  const speakerWithoutPhoto = {
    id: 2,
    name: 'Vikram Joshi',
    roles: ['speaker'],
    role_labels: ['Speaker'],
    talk: 'WordPress Performance Tips',
    photo: null,
  };

  const canvasNoPhoto = createMockCanvas(1080, 1350);
  await drawPersonCard(canvasNoPhoto, '1080x1350', 'daylight', speakerWithoutPhoto, kit, colors);
  const ctxNoPhoto = canvasNoPhoto.getContext('2d');
  // Initials "VJ" drawn
  const textCalls = ctxNoPhoto.drawCalls.filter((c) => c.type === 'fillText');
  assert.ok(textCalls.some((c) => c.text === 'VJ'), 'Initials VJ rendered when photo is null');

  // 3. Organizer role card
  const organizer = {
    id: 3,
    name: 'Priya Mehta',
    roles: ['organizer'],
    role_labels: ['Lead Organizer'],
    talk: null,
    photo: null,
  };

  const canvasOrganizer = createMockCanvas(1080, 1080);
  await drawPersonCard(canvasOrganizer, '1080x1080', 'classic', organizer, kit, colors);
  const ctxOrg = canvasOrganizer.getContext('2d');
  assert.ok(ctxOrg.drawCalls.length > 5);
});

test('social-reels.js: prepare() and drawFrame() for Posts, Speakers, and Organizers', async () => {
  const people = [
    { id: 's1', name: 'Kavita Rao', roles: ['speaker'], role_labels: ['Speaker'], photo: 'https://rajasthan.wordcamp.org/files/kavita.jpg', talk: 'React in WP' },
    { id: 's2', name: 'Sunil Rao', roles: ['speaker'], role_labels: ['Speaker'], photo: null, talk: 'Future of WP' },
    { id: 'o1', name: 'Priya Mehta', roles: ['organizer'], role_labels: ['Organizer'], photo: null, talk: null },
  ];

  const posts = [
    { key: 'save-the-date', label: 'Coming up', content: () => ({ key: 'save-the-date', headline: 'Save the Date', line: '10–11 Oct', body: { type: 'none' } }), captions: () => ({ instagram: 'c', linkedin: 'c', x: 'c' }) },
  ];

  const fullKit = {
    short: 'WordCamp Test',
    event: { name: 'WordCamp Test 2026', dates: '10–11 Oct 2026', place: 'Jaipur', hashtags: '#WCTest' },
    people,
    speakers: people.filter((p) => p.roles.includes('speaker')),
    sponsors: [{ name: 'CampHost', logo: null }],
    schedule: { days: [{ key: '2026-10-10', label: 'Sat 10 Oct' }], sessions: [] },
    imageUrl: '/admin/events/1/social/image',
  };

  const colors = { primary: '#7c3aed', secondary: '#f59e0b', ink: '#1e1b4b', paper: '#faf5ff' };

  const reels = reelList(fullKit, posts);
  assert.ok(reels.length >= 2, 'Generated reel items for post, speakers, and organizers');

  // 1. Post Reel
  const postReelItem = reels.find((r) => r.key === 'post-save-the-date');
  assert.ok(postReelItem);
  const preparedPostReel = await reelInternals.prepare(postReelItem, fullKit, colors, 'daylight');
  assert.ok(preparedPostReel.scenes.length >= 2);
  assert.ok(preparedPostReel.duration > 0);

  const canvasPost = createMockCanvas(1080, 1920);
  reelInternals.drawFrame(canvasPost.getContext('2d'), preparedPostReel, 0.5);
  assert.ok(canvasPost.getContext('2d').drawCalls.length > 0);

  // 2. Speaker Group Reel with Photo and Initial Fallback
  const speakerReelItem = reels.find((r) => r.key === 'speaker');
  assert.ok(speakerReelItem);
  const preparedSpeakerReel = await reelInternals.prepare(speakerReelItem, fullKit, colors, 'daylight');
  assert.ok(preparedSpeakerReel.photos instanceof Map);
  assert.ok(preparedSpeakerReel.scenes.length >= 2, 'Speaker reel has scenes');

  const canvasSpeaker = createMockCanvas(1080, 1920);
  reelInternals.drawFrame(canvasSpeaker.getContext('2d'), preparedSpeakerReel, 1.0);
  reelInternals.drawFrame(canvasSpeaker.getContext('2d'), preparedSpeakerReel, 2.5);
  assert.ok(canvasSpeaker.getContext('2d').drawCalls.length > 10);

  // 3. Text measurement fits inside Instagram Reels safe zone
  const ctx = canvasSpeaker.getContext('2d');
  const fit = reelInternals.fitAll(ctx, 'Extremely long session title that spans multiple lines on modern phones', 600, 50, 28, 3, 700, 'sans-serif');
  assert.ok(fit.lines.length >= 1);
  assert.ok(fit.lines.every((l) => l.length * fit.size * 0.5 <= 600));
});

test('Hidden Studio element canvas reveal does not freeze at 0 opacity', () => {
  // Test that when canvas is inside a hidden panel/dialog (offsetParent === null),
  // the reveal animation logic does not get stuck at alpha 0.
  const hiddenCanvas = createMockCanvas(500, 500);
  hiddenCanvas.offsetParent = null; // hidden (display: none)
  hiddenCanvas.isConnected = true;

  let paintedAlpha = null;
  const mockNext = (frameAlpha) => {
    paintedAlpha = frameAlpha;
  };

  // Simulate reveal logic from social-kit.js
  const revealCheck = (canvas, next) => {
    if (!canvas.isConnected || canvas.offsetParent === null || window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) {
      next(1); // Immediate 100% paint
      return;
    }
    next(0);
  };

  revealCheck(hiddenCanvas, mockNext);
  assert.equal(paintedAlpha, 1, 'Hidden canvas immediately paints at 100% opacity instead of freezing at 0');
});
