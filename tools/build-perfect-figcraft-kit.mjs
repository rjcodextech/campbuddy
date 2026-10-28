// Master FigCraft Builder for CampBuddy: Pixel-perfect branding, background effects, creative typography, and balanced layouts
import fs from 'node:fs';
import path from 'node:path';
import { connect } from 'file:///E:/Sites/InHouse/wpsimplified/Studio/Design/scripts/fc.mjs';

const ROOT_MKT = 'E:/Sites/InHouse/campbuddy/marketing';
const FINAL_ASSETS = path.join(ROOT_MKT, 'final-assets');
const OUT_FILE = path.join(ROOT_MKT, 'CampBuddy-Social-Media-Kit.figcraft');
const SHARED_IMG = path.join(ROOT_MKT, 'usp-campaign', 'shared', 'img');
const SCREENS_DIR = path.join(ROOT_MKT, 'usp-campaign', 'screens');

const reelsData = JSON.parse(fs.readFileSync(path.join(ROOT_MKT, 'usp-campaign', 'reels', 'reels.json'), 'utf8'));

console.log('Connecting to FigCraft MCP client...');
const fc = await connect(['E:/Sites/InHouse']);

// Helpers
async function frame(parent, x, y, w, h, name, props = {}) {
  const p = {
    clip: props.clip ?? true,
    cornerRadius: props.cornerRadius || 0,
    strokeWeight: props.strokeWeight || 0,
    fills: props.fill ? [props.fill] : (props.fills || []),
    strokes: props.stroke ? [props.stroke] : (props.strokes || []),
    effects: props.effects || [],
    opacity: props.opacity ?? 1,
  };
  const res = await fc.cmd('node.create', {
    type: 'frame', x, y, width: w, height: h, parent, name, select: false, props: p
  });
  return res.id;
}

async function rect(parent, x, y, w, h, props = {}, name) {
  const p = {
    cornerRadius: props.cornerRadius || 0,
    strokeWeight: props.strokeWeight || 0,
    fills: props.fill ? [props.fill] : (props.fills || []),
    strokes: props.stroke ? [props.stroke] : (props.strokes || []),
    opacity: props.opacity ?? 1,
  };
  const res = await fc.cmd('node.create', {
    type: 'rectangle', x, y, width: w, height: h, parent, name, select: false, props: p
  });
  return res.id;
}

async function ellipse(parent, x, y, w, h, props = {}, name) {
  const p = {
    strokeWeight: props.strokeWeight || 0,
    fills: props.fill ? [props.fill] : (props.fills || []),
    strokes: props.stroke ? [props.stroke] : (props.strokes || []),
    opacity: props.opacity ?? 1,
  };
  const res = await fc.cmd('node.create', {
    type: 'ellipse', x, y, width: w, height: h, parent, name, select: false, props: p
  });
  return res.id;
}

async function text(parent, textStr, x, y, o = {}) {
  const props = {
    fontFamily: o.font || (o.f === 'head' ? 'Baloo 2' : 'Poppins'),
    fontStyle: o.w || (o.f === 'head' ? 'Bold' : 'Regular'),
    fontSize: o.s || 16,
    fill: o.c || '#0f172a',
    textAlign: o.align || 'left',
  };
  if (o.lh) props.lineHeight = o.lh;
  if (o.ls) props.letterSpacing = o.ls;
  if (o.upper) props.textCase = 'upper';
  if (o.width) props.textAutoResize = 'height';
  const p = { text: textStr, x, y, parent, props };
  if (o.width) p.width = o.width;
  const res = await fc.cmd('text.create', p);
  if (o.name) await fc.cmd('node.set', { ids: [res.id], name: o.name });
  return res.id;
}

async function placeImage(parent, filePath, x, y, w, h, mode = 'fit') {
  if (!fs.existsSync(filePath)) return null;
  const res = await fc.cmd('image.place', { path: filePath.replaceAll('\\', '/'), x, y, width: w, height: h, parent, mode });
  return res.id;
}

function dropShadow(y = 16, blur = 36, opacity = 0.08) {
  const alphaHex = Math.round(opacity * 255).toString(16).padStart(2, '0');
  return { type: 'dropShadow', color: `#0f172a${alphaHex}`, offset: [0, y], radius: blur };
}

// Draw rich Daylight ground background on any frame: warm gradient, glows, wave curves, and dots
async function drawDaylightGround(parent, w, h) {
  // Base paper gradient simulation (warm light paper)
  await rect(parent, 0, 0, w, h, { fill: '#fafafd' }, 'Bg / Paper');
  // Bottom warm tint
  await rect(parent, 0, h * 0.65, w, h * 0.35, { fill: '#fff7ed', opacity: 0.7 }, 'Bg / Warm Tint');
  // Top-Right Ochre Glow
  const m = Math.min(w, h);
  await ellipse(parent, w - m * 0.5, -m * 0.25, m * 0.8, m * 0.75, { fill: '#f59e0b', opacity: 0.16 }, 'Glow / Ochre Top-Right');
  // Bottom-Left Maroon Glow
  await ellipse(parent, -m * 0.25, h - m * 0.55, m * 0.8, m * 0.7, { fill: '#ea580c', opacity: 0.12 }, 'Glow / Orange Bottom-Left');
  // Center Accent Glow
  await ellipse(parent, w * 0.45, h * 0.45, m * 0.5, m * 0.5, { fill: '#fb923c', opacity: 0.06 }, 'Glow / Center Ambient');
  // Subtle decorative wave bars at bottom
  await rect(parent, 0, h - 8, w, 4, { fill: '#ea580c', opacity: 0.3 }, 'Accent / Wave Line 1');
  await rect(parent, 40, h - 16, w - 80, 2, { fill: '#f59e0b', opacity: 0.4 }, 'Accent / Wave Line 2');
}

try {
  console.log('Loading Baloo 2 font...');
  await fc.cmd('text.loadFont', { path: 'E:/Sites/InHouse/wpsimplified/Studio/Design/assets/fonts/Baloo2-Variable.ttf' }).catch(() => {});

  const pages = await fc.cmd('page.list');
  const page1 = pages[0].id;
  await fc.cmd('page.rename', { id: page1, name: '01 · Channel Branding & Handles' });
  await fc.cmd('page.setBackground', { id: page1, color: '#f1f5f9' });

  const page2 = (await fc.cmd('page.add', { name: '02 · Social Feed Posts (1080×1350)' })).id;
  await fc.cmd('page.setBackground', { id: page2, color: '#f1f5f9' });

  const page3 = (await fc.cmd('page.add', { name: '03 · Social Link Cards (1200×627)' })).id;
  await fc.cmd('page.setBackground', { id: page3, color: '#f1f5f9' });

  const page4 = (await fc.cmd('page.add', { name: '04 · Reel Covers & Safe Zones (1080×1920)' })).id;
  await fc.cmd('page.setBackground', { id: page4, color: '#f1f5f9' });

  // =========================================================================
  // PAGE 1: 01 · Channel Branding & Handles
  // =========================================================================
  console.log('Building Page 1: Channel Branding & Handles...');
  await fc.cmd('page.select', { id: page1 });

  // Header Title
  await text(null, 'CampBuddy / Social Media Channels & Handles', 80, 60, { f: 'head', s: 44, c: '#0f172a' });
  await text(null, 'Master banners, profile avatars, and story highlight covers with Daylight ground effects & safe area overlays', 80, 120, { s: 18, c: '#64748b' });

  // --- YouTube Banner (2560 × 1440) ---
  const yt = await frame(null, 80, 200, 2560, 1440, 'YouTube Banner (2560×1440)', {
    stroke: '#cbd5e1', strokeWeight: 2, cornerRadius: 28, effects: [dropShadow(24, 60, 0.08)]
  });
  await drawDaylightGround(yt, 2560, 1440);
  // Safe Area Center Container (1546 × 423 at x: 507, y: 508)
  const ytSafe = await frame(yt, 507, 508, 1546, 423, 'Safe Area Container (1546×423)', {
    fill: '#ffffff', stroke: '#fed7aa', strokeWeight: 2, cornerRadius: 24, effects: [dropShadow(16, 40, 0.06)]
  });
  // Content inside safe area
  await placeImage(ytSafe, path.join(SHARED_IMG, 'logo-wordmark.png'), 60, 60, 420, 110, 'fit');
  await text(ytSafe, 'YOUR WORDCAMP COMPANION', 64, 185, { f: 'head', s: 20, c: '#ea580c', ls: 3, upper: true });
  await text(ytSafe, 'Plan your talks, meet your people & keep your Camp Card at every WordCamp.', 64, 225, { s: 24, c: '#334155', width: 850, lh: 34 });
  // Feature chips inside safe area
  const ytChips = ['📅 My Day Schedule', '👥 People & Networking', '🪪 Offline Camp Card', '🎯 Interactive Quests'];
  let ytChipX = 64;
  for (const c of ytChips) {
    const chipBox = await frame(ytSafe, ytChipX, 310, 190, 42, `Chip / ${c}`, {
      fill: '#fafafd', stroke: '#fdba74', strokeWeight: 1.5, cornerRadius: 21
    });
    await text(chipBox, c, 14, 12, { s: 13, c: '#9a3412', w: 'Bold' });
    ytChipX += 205;
  }
  // Co-brand plate inside safe area
  await text(ytSafe, 'Crafted with care by WPSimplified · Free & Open Source', 64, 375, { s: 14, c: '#64748b' });
  // Icon Badge on right of safe area
  const iconPlate = await frame(ytSafe, 1180, 50, 310, 310, 'Icon Plate', {
    fill: '#fff7ed', stroke: '#fed7aa', strokeWeight: 2, cornerRadius: 60
  });
  await placeImage(iconPlate, path.join(SHARED_IMG, 'icon-512.png'), 35, 35, 240, 240, 'fit');
  // Safe Area Outline Guide
  await rect(yt, 507, 508, 1546, 423, { stroke: '#f97316', strokeWeight: 2 }, 'Guide / YouTube Safe Area');
  await text(yt, 'YOUTUBE SAFE AREA (DESKTOP & MOBILE VISIBLE: 1546 × 423)', 512, 480, { s: 13, c: '#ea580c', w: 'Bold', upper: true });

  // --- Facebook Cover (1640 × 924) ---
  const fb = await frame(null, 2720, 200, 1640, 924, 'Facebook Cover (1640×924)', {
    stroke: '#cbd5e1', strokeWeight: 2, cornerRadius: 28, effects: [dropShadow(24, 60, 0.08)]
  });
  await drawDaylightGround(fb, 1640, 924);
  const fbSafe = await frame(fb, 200, 107, 1240, 710, 'Facebook Safe Area (1240×710)', {
    fill: '#ffffff', stroke: '#fed7aa', strokeWeight: 2, cornerRadius: 24, effects: [dropShadow(16, 40, 0.06)]
  });
  await placeImage(fbSafe, path.join(SHARED_IMG, 'logo-wordmark.png'), 70, 70, 400, 105, 'fit');
  await text(fbSafe, 'Your WordCamp Companion', 74, 200, { f: 'head', s: 42, c: '#0f172a' });
  await text(fbSafe, 'Never alone at any WordCamp · Free & Open Source for the WordPress Community', 74, 260, { s: 22, c: '#475569', width: 750, lh: 32 });
  const fbChips = ['⚡ Instant Live Schedule', '🤝 1-on-1 Networking', '📱 100% Offline Ready', '🏆 Community Quests'];
  let fbChipX = 74;
  for (const c of fbChips) {
    const chip = await frame(fbSafe, fbChipX, 350, 240, 46, `Chip / ${c}`, {
      fill: '#fff7ed', stroke: '#fdba74', strokeWeight: 1.5, cornerRadius: 23
    });
    await text(chip, c, 18, 13, { s: 14, c: '#9a3412', w: 'Bold' });
    fbChipX += 255;
  }
  await text(fbSafe, 'Crafted with care by WPSimplified · campbuddy.club', 74, 630, { s: 16, c: '#64748b' });
  await placeImage(fbSafe, path.join(SHARED_IMG, 'icon-512.png'), 890, 180, 280, 280, 'fit');
  await rect(fb, 200, 107, 1240, 710, { stroke: '#f97316', strokeWeight: 2 }, 'Guide / Facebook Safe Area');

  // --- X / Twitter Header (1500 × 500) ---
  const xH = await frame(null, 80, 1720, 1500, 500, 'X (Twitter) Header (1500×500)', {
    stroke: '#cbd5e1', strokeWeight: 2, cornerRadius: 24, effects: [dropShadow(20, 48, 0.06)]
  });
  await drawDaylightGround(xH, 1500, 500);
  await placeImage(xH, path.join(SHARED_IMG, 'logo-wordmark.png'), 100, 100, 360, 95, 'fit');
  await text(xH, 'Your WordCamp companion', 104, 220, { f: 'head', s: 36, c: '#0f172a' });
  await text(xH, 'Plan talks · Find people · Keep your Camp Card · 100% Offline Ready', 104, 275, { s: 20, c: '#475569', width: 780 });
  await text(xH, 'campbuddy.club  ·  Crafted by WPSimplified', 104, 380, { f: 'head', s: 18, c: '#ea580c' });
  await placeImage(xH, path.join(SHARED_IMG, 'icon-512.png'), 1120, 90, 280, 280, 'fit');

  // --- LinkedIn Banner (1584 × 396) ---
  const li = await frame(null, 1660, 1720, 1584, 396, 'LinkedIn Banner (1584×396)', {
    stroke: '#cbd5e1', strokeWeight: 2, cornerRadius: 24, effects: [dropShadow(20, 48, 0.06)]
  });
  await drawDaylightGround(li, 1584, 396);
  await placeImage(li, path.join(SHARED_IMG, 'logo-wordmark.png'), 380, 80, 340, 90, 'fit');
  await text(li, 'The Community Companion for WordPress WordCamps', 384, 195, { f: 'head', s: 26, c: '#0f172a' });
  await text(li, 'Smart Scheduling · People Discovery · Offline Camp Card · Open Source by WPSimplified', 384, 238, { s: 18, c: '#475569' });
  await placeImage(li, path.join(SHARED_IMG, 'icon-512.png'), 1280, 68, 240, 240, 'fit');

  // --- Profile Avatars (800 × 800) ---
  let avX = 80;
  const avConfigs = [
    { title: 'Profile / Daylight Brand', bg: '#fafafd', plateBg: '#ffffff', ring: '#fdba74' },
    { title: 'Profile / Warm Orange Glow', bg: '#fff7ed', plateBg: '#ffffff', ring: '#ea580c' },
    { title: 'Profile / High Contrast Ink', bg: '#0f172a', plateBg: '#1e293b', ring: '#f59e0b' },
    { title: 'Profile / Co-Brand Seal', bg: '#fafafd', plateBg: '#ffffff', ring: '#fed7aa' }
  ];
  for (let i = 0; i < 4; i++) {
    const cfg = avConfigs[i];
    const av = await frame(null, avX, 2300, 800, 800, cfg.title, {
      fill: cfg.bg, stroke: '#cbd5e1', strokeWeight: 2, cornerRadius: 36, effects: [dropShadow(24, 60, 0.08)]
    });
    // Ambient Glow
    await ellipse(av, 150, 150, 500, 500, { fill: cfg.ring, opacity: 0.18 });
    // Circular Guide
    await ellipse(av, 50, 50, 700, 700, { stroke: cfg.ring, strokeWeight: 2 }, 'Guide / Circular Crop');
    // Elevated Plate
    const plate = await frame(av, 160, 160, 480, 480, 'Center Elevated Plate', {
      fill: cfg.plateBg, stroke: cfg.ring, strokeWeight: 3, cornerRadius: 120, effects: [dropShadow(20, 48, 0.12)]
    });
    await placeImage(plate, path.join(SHARED_IMG, 'icon-512.png'), 60, 60, 360, 360, 'fit');
    avX += 880;
  }

  // =========================================================================
  // PAGE 3: 03 · Social Link Cards (1200×627) — Balanced Editorial Layout
  // =========================================================================
  console.log('Building Page 3: Social Link Cards (1200×627)...');
  await fc.cmd('page.select', { id: page3 });

  await text(null, 'CampBuddy / Social Link Cards (1200×627 Landscape)', 80, 60, { f: 'head', s: 44, c: '#0f172a' });
  await text(null, 'Zero empty space: Creative multi-line titles, feature pill chips, elevated 3D mockups, and master brand bar', 80, 120, { s: 18, c: '#64748b' });

  let cardY = 200;
  for (const r of reelsData.reels) {
    const en = r.post_en || r.post;
    const hi = r.post_hi || r.post;
    const screenImg = path.join(SCREENS_DIR, en.card || en.phone || 'bengaluru/home-now.png');

    // Build English Card (x: 80) and Hinglish Card (x: 1360)
    for (const [colIdx, lang, postData, langLabel] of [[0, 'en', en, 'EN'], [1, 'hi', hi, 'HI']]) {
      const cardX = colIdx === 0 ? 80 : 1360;
      const cFrame = await frame(null, cardX, cardY, 1200, 627, `LinkCard ${r.id} / ${r.slug} [${langLabel}]`, {
        stroke: '#cbd5e1', strokeWeight: 2, cornerRadius: 32, effects: [dropShadow(24, 56, 0.08)]
      });
      await drawDaylightGround(cFrame, 1200, 627);

      // --- Left Column: Content that beautifully fills the vertical height (x: 50, w: 660) ---
      // 1. Tag Row (y: 44)
      const evChip = await frame(cFrame, 50, 44, 280, 38, 'Event Chip', {
        fill: '#ffffff', stroke: '#fdba74', strokeWeight: 1.5, cornerRadius: 19
      });
      await text(evChip, (r.event || 'WORDCAMP').toUpperCase(), 16, 10, { f: 'head', s: 13, c: '#c2410c', ls: 1 });

      const kickPill = await frame(cFrame, 345, 44, 260, 38, 'Kicker Pill', {
        fill: '#fff7ed', stroke: '#fed7aa', strokeWeight: 1.5, cornerRadius: 19
      });
      await text(kickPill, `⚡ ${(postData.kicker || 'WORDCAMP').toUpperCase()}`, 16, 10, { f: 'head', s: 13, c: '#ea580c', ls: 1 });

      // 2. Creative Big Headline (y: 100, font: 54px, lh: 60px)
      await text(cFrame, postData.title.replace(/\*/g, ''), 50, 100, {
        f: 'head', s: 54, c: '#0f172a', width: 660, lh: 60
      });

      // 3. Subtitle / Value proposition (y: 236, font: 21px, lh: 30px)
      await text(cFrame, postData.sub, 50, 236, {
        s: 21, c: '#475569', width: 660, lh: 30
      });

      // 4. Feature Pills Row (y: 334) — Fills the middle zone creatively
      const pills = lang === 'en'
        ? ['⚡ 100% Offline Ready', '🤝 Meet Attendees', '🎯 Quests & Fun']
        : ['⚡ 100% ऑफलाइन रेडी', '🤝 लोगों से मिलो', '🎯 क्वेस्ट्स और बैजेस'];
      let px = 50;
      for (const p of pills) {
        const pBox = await frame(cFrame, px, 334, 205, 40, `Pill / ${p}`, {
          fill: '#ffffff', stroke: '#fed7aa', strokeWeight: 1.5, cornerRadius: 20
        });
        await text(pBox, p, 14, 11, { s: 13, c: '#9a3412', w: 'Bold' });
        px += 220;
      }

      // 5. Value Trust Banner Strip (y: 395)
      const trustStrip = await frame(cFrame, 50, 395, 660, 42, 'Trust Strip', {
        fill: '#ffffff', stroke: '#e2e8f0', strokeWeight: 1.5, cornerRadius: 12
      });
      const trustText = lang === 'en'
        ? '★ Free & open-source community app · No app store install required'
        : '★ फ्री और ओपन-सोर्स कम्युनिटी ऐप · किसी ऐप स्टोर की जरूरत नहीं';
      await text(trustStrip, trustText, 18, 12, { s: 13, c: '#64748b', w: 'Bold' });

      // 6. Master Brand Plate (y: 460, h: 56px, w: 660px) — Anchors bottom left solidly
      const brandPlate = await frame(cFrame, 50, 460, 660, 56, 'Master Brand Plate', {
        fill: '#ffffff', stroke: '#fdba74', strokeWeight: 1.5, cornerRadius: 16, effects: [dropShadow(8, 20, 0.05)]
      });
      await placeImage(brandPlate, path.join(SHARED_IMG, 'logo-wordmark.png'), 18, 12, 160, 32, 'fit');
      await rect(brandPlate, 195, 14, 1.5, 28, { fill: '#cbd5e1' });
      await text(brandPlate, 'Crafted by WPSimplified', 212, 19, { s: 14, c: '#64748b' });
      const urlBadge = await frame(brandPlate, 480, 10, 165, 36, 'URL Badge', {
        fill: '#fff7ed', stroke: '#fed7aa', strokeWeight: 1.5, cornerRadius: 18
      });
      await text(urlBadge, 'campbuddy.club', 20, 10, { f: 'head', s: 14, c: '#ea580c' });

      // --- Right Column: Elevated 3D UI Screen Card Mockup (x: 740, y: 44, w: 410, h: 527) ---
      const mockStage = await frame(cFrame, 740, 44, 410, 527, 'Screen Mockup Stage', {
        fill: '#ffffff', stroke: '#e2e8f0', strokeWeight: 2, cornerRadius: 28, effects: [dropShadow(24, 56, 0.12)]
      });
      if (fs.existsSync(screenImg)) {
        await placeImage(mockStage, screenImg, 15, 15, 380, 497, 'fit');
      }
      // Floating highlight sticker badge
      const sticker = await frame(mockStage, 20, 20, 140, 34, 'Live Badge', {
        fill: '#ea580c', cornerRadius: 17
      });
      await text(sticker, '● LIVE SCHEDULE', 12, 9, { f: 'head', s: 11, c: '#ffffff', ls: 1 });
    }

    cardY += 720;
  }

  // =========================================================================
  // PAGE 2: 02 · Social Feed Posts (1080×1350) — Rich Editorial 4:5
  // =========================================================================
  console.log('Building Page 2: Social Feed Posts (1080×1350)...');
  await fc.cmd('page.select', { id: page2 });

  await text(null, 'CampBuddy / Social Media Feed Posts (4:5 Portrait — 1080×1350)', 80, 60, { f: 'head', s: 44, c: '#0f172a' });
  await text(null, '10 Campaign Posts in Dual Languages (English left, Hinglish right) — Daylight ground effects & rich typography', 80, 120, { s: 18, c: '#64748b' });

  let postY = 200;
  for (const r of reelsData.reels) {
    const en = r.post_en || r.post;
    const hi = r.post_hi || r.post;
    const screenImg = path.join(SCREENS_DIR, en.card || en.phone || 'bengaluru/home-now.png');

    for (const [colIdx, lang, postData, langLabel] of [[0, 'en', en, 'EN'], [1, 'hi', hi, 'HI']]) {
      const cardX = colIdx === 0 ? 80 : 1240;
      const pFrame = await frame(null, cardX, postY, 1080, 1350, `FeedPost ${r.id} / ${r.slug} [${langLabel}]`, {
        stroke: '#cbd5e1', strokeWeight: 2, cornerRadius: 36, effects: [dropShadow(28, 64, 0.08)]
      });
      await drawDaylightGround(pFrame, 1080, 1350);

      // Top Tag & Kicker
      const evTile = await frame(pFrame, 70, 60, 340, 48, 'Event Tile', {
        fill: '#ffffff', stroke: '#fdba74', strokeWeight: 2, cornerRadius: 24, effects: [dropShadow(6, 16, 0.04)]
      });
      await text(evTile, (r.event || 'WORDCAMP').toUpperCase(), 20, 14, { f: 'head', s: 15, c: '#c2410c', ls: 1 });

      const kPill = await frame(pFrame, 680, 60, 330, 48, 'Kicker Pill', {
        fill: '#fff7ed', stroke: '#fed7aa', strokeWeight: 2, cornerRadius: 24
      });
      await text(kPill, `⚡ ${(postData.kicker || 'WORDCAMP GUIDE').toUpperCase()}`, 20, 14, { f: 'head', s: 14, c: '#ea580c', ls: 1 });

      // Massive Editorial Title (y: 135, font: 76px, lh: 84px)
      await text(pFrame, postData.title.replace(/\*/g, ''), 70, 135, {
        f: 'head', s: 76, c: '#0f172a', width: 940, lh: 84
      });

      // Subtitle (y: 310, font: 25px, lh: 36px)
      await text(pFrame, postData.sub, 70, 310, {
        s: 25, c: '#475569', width: 940, lh: 36
      });

      // Feature Callout Pills Row (y: 395)
      const feedPills = lang === 'en'
        ? ['⚡ 100% Offline Ready', '🤝 Camp Card Discovery', '🎯 Gamified Quests']
        : ['⚡ 100% ऑफलाइन काम करे', '🤝 कैंप कार्ड नेटवर्किंग', '🎯 आसान फर्स्ट-टाइमर क्वेस्ट्स'];
      let fpx = 70;
      for (const fp of feedPills) {
        const fpBox = await frame(pFrame, fpx, 395, 290, 44, `Pill / ${fp}`, {
          fill: '#ffffff', stroke: '#fed7aa', strokeWeight: 1.5, cornerRadius: 22
        });
        await text(fpBox, fp, 18, 12, { s: 14, c: '#9a3412', w: 'Bold' });
        fpx += 310;
      }

      // Elevated UI Mockup Stage (y: 460, h: 630, w: 940)
      const mockStage = await frame(pFrame, 70, 460, 940, 630, 'App UI Stage', {
        fill: '#ffffff', stroke: '#e2e8f0', strokeWeight: 2, cornerRadius: 28, effects: [dropShadow(20, 48, 0.1)]
      });
      if (fs.existsSync(screenImg)) {
        await placeImage(mockStage, screenImg, 20, 20, 900, 590, 'fit');
      }

      // Bottom Scan & Action Foot (y: 1110, h: 120, w: 940)
      const actionFoot = await frame(pFrame, 70, 1110, 940, 120, 'Action Foot', {
        fill: '#ffffff', stroke: '#fed7aa', strokeWeight: 1.5, cornerRadius: 22, effects: [dropShadow(10, 24, 0.05)]
      });
      await text(actionFoot, postData.scan || 'Scan to explore WordCamp schedule & networking', 30, 28, { f: 'head', s: 22, c: '#0f172a' });
      await text(actionFoot, `${r.event} · Free & Open Source · No app store install`, 30, 66, { s: 16, c: '#64748b' });
      // QR Code Box Placeholder
      const qrBox = await frame(actionFoot, 810, 12, 96, 96, 'QR Box', {
        fill: '#fff7ed', stroke: '#fed7aa', strokeWeight: 2, cornerRadius: 16
      });
      await placeImage(qrBox, path.join(SHARED_IMG, 'icon-512.png'), 12, 12, 72, 72, 'fit');

      // Master Brand Bar (y: 1250, h: 56, w: 940)
      const mBar = await frame(pFrame, 70, 1250, 940, 56, 'Master Brand Bar', {
        fill: '#ffffff', stroke: '#f1f5f9', strokeWeight: 1, cornerRadius: 16
      });
      await placeImage(mBar, path.join(SHARED_IMG, 'logo-wordmark.png'), 20, 12, 170, 32, 'fit');
      await rect(mBar, 210, 14, 1.5, 28, { fill: '#cbd5e1' });
      await text(mBar, 'Crafted by WPSimplified', 230, 18, { s: 15, c: '#64748b' });
      await text(mBar, 'campbuddy.club', 780, 18, { f: 'head', s: 16, c: '#ea580c' });
    }

    postY += 1450;
  }

  // =========================================================================
  // PAGE 4: 04 · Reel Covers & Safe Zones (1080×1920) — High Impact 9:16
  // =========================================================================
  console.log('Building Page 4: Reel Covers & Safe Zones (1080×1920)...');
  await fc.cmd('page.select', { id: page4 });

  await text(null, 'CampBuddy / 10 Reel Covers & 9:16 Video Safe Zones', 80, 60, { f: 'head', s: 44, c: '#0f172a' });
  await text(null, 'Full 1080×1920 Covers with 4:5 Feed Crop (1080×1350) and 1:1 Profile Crop (1080×1080) Overlays', 80, 120, { s: 18, c: '#64748b' });

  let reelX = 80;
  for (const r of reelsData.reels) {
    const rCover = await frame(null, reelX, 200, 1080, 1920, `ReelCover ${r.id} / ${r.slug} (1080×1920)`, {
      stroke: '#cbd5e1', strokeWeight: 2, cornerRadius: 40, effects: [dropShadow(32, 72, 0.1)]
    });
    await drawDaylightGround(rCover, 1080, 1920);

    // 1. Top Enlarged Event Tile (y: 110, h: 104px, w: 440px, centered at x: 320)
    const evTile = await frame(rCover, 320, 110, 440, 104, 'Event Tile', {
      fill: '#ffffff', stroke: '#fed7aa', strokeWeight: 2.5, cornerRadius: 28, effects: [dropShadow(12, 28, 0.08)]
    });
    const evLogo = path.join(SHARED_IMG, `event-${r.event.toLowerCase().replace(/wordcamp\s*/i, '').replace(/\s*\d+/g, '').trim()}.png`);
    if (fs.existsSync(evLogo)) {
      await placeImage(evTile, evLogo, 20, 16, 200, 72, 'fit');
    }
    await text(evTile, r.event.toUpperCase(), 230, 42, { f: 'head', s: 16, c: '#0f172a', ls: 1 });

    // 2. Kicker Pill (y: 250, centered)
    const kPill = await frame(rCover, 340, 250, 400, 52, 'Kicker Pill', {
      fill: '#ffffff', stroke: '#fdba74', strokeWeight: 2, cornerRadius: 26, effects: [dropShadow(8, 20, 0.05)]
    });
    await text(kPill, `⚡ ${(r.cover.kicker || r.cover.chip || 'CAMPBUDDY').toUpperCase()}`, 30, 16, { f: 'head', s: 15, c: '#c2410c', ls: 2 });

    // 3. Huge Punchy Hook Headline (y: 330, font: 94px, lh: 102px, centered)
    await text(rCover, r.cover.title.replace(/\*/g, ''), 70, 330, {
      f: 'head', s: 94, c: '#0f172a', width: 940, lh: 102, align: 'center'
    });

    // 4. Subtitle / Hook explanation (y: 560, font: 28px, lh: 40px, centered)
    await text(rCover, r.post.sub, 90, 560, {
      s: 28, c: '#475569', width: 900, lh: 40, align: 'center'
    });

    // 5. Phone / Screen Mockup Stage (y: 680, w: 840, h: 920, centered at x: 120)
    const mockupStage = await frame(rCover, 120, 680, 840, 920, 'Phone Mockup Stage', {
      fill: '#ffffff', stroke: '#e2e8f0', strokeWeight: 2, cornerRadius: 36, effects: [dropShadow(28, 64, 0.14)]
    });
    const mockupImg = path.join(SCREENS_DIR, r.cover.phone || r.cover.card || 'bengaluru/home.png');
    if (fs.existsSync(mockupImg)) {
      await placeImage(mockupStage, mockupImg, 20, 20, 800, 880, 'fit');
    }

    // Floating Feature Badges on Mockup Stage
    const fSticker1 = await frame(rCover, 80, 720, 240, 48, 'Sticker / 100% Free', {
      fill: '#ea580c', cornerRadius: 24, effects: [dropShadow(8, 20, 0.2)]
    });
    await text(fSticker1, '★ 100% FREE APP', 24, 15, { f: 'head', s: 14, c: '#ffffff', ls: 1 });

    const fSticker2 = await frame(rCover, 760, 1420, 240, 48, 'Sticker / Offline', {
      fill: '#10b981', cornerRadius: 24, effects: [dropShadow(8, 20, 0.2)]
    });
    await text(fSticker2, '⚡ WORKS OFFLINE', 22, 15, { f: 'head', s: 14, c: '#ffffff', ls: 1 });

    // 6. Event Chip (y: 1640, centered)
    const footChip = await frame(rCover, 340, 1640, 400, 56, 'Event Chip', {
      fill: '#ffffff', stroke: '#fdba74', strokeWeight: 2, cornerRadius: 28, effects: [dropShadow(8, 20, 0.06)]
    });
    await text(footChip, r.event.toUpperCase(), 35, 18, { f: 'head', s: 16, c: '#c2410c', ls: 1 });

    // 7. Master Brand Outro Plate (y: 1730, h: 88, w: 940, centered at x: 70)
    const outroPlate = await frame(rCover, 70, 1730, 940, 88, 'Master Brand Plate', {
      fill: '#ffffff', stroke: '#fdba74', strokeWeight: 2, cornerRadius: 28, effects: [dropShadow(16, 40, 0.08)]
    });
    await placeImage(outroPlate, path.join(SHARED_IMG, 'logo-wordmark.png'), 40, 20, 220, 48, 'fit');
    await rect(outroPlate, 290, 20, 2, 48, { fill: '#cbd5e1' });
    await text(outroPlate, 'Crafted by WPSimplified', 320, 32, { s: 20, c: '#64748b' });
    const ctaBadge = await frame(outroPlate, 680, 18, 220, 52, 'CTA Badge', {
      fill: '#fff7ed', stroke: '#fed7aa', strokeWeight: 2, cornerRadius: 26
    });
    await text(ctaBadge, 'campbuddy.club', 28, 16, { f: 'head', s: 18, c: '#ea580c' });

    // 8. Guides (Overlays)
    // 4:5 Instagram Feed Safe Zone (1080 × 1350 at y: 285)
    await rect(rCover, 0, 285, 1080, 1350, { stroke: '#3b82f6', strokeWeight: 2 }, 'Guide / IG Feed 4:5 Safe Area (1080×1350)');
    await text(rCover, '4:5 IG FEED SAFE AREA (1080 × 1350)', 30, 260, { s: 14, c: '#3b82f6', w: 'Bold', upper: true });

    // 1:1 Instagram Profile Grid Safe Zone (1080 × 1080 at y: 420)
    await rect(rCover, 0, 420, 1080, 1080, { stroke: '#10b981', strokeWeight: 2 }, 'Guide / IG Profile 1:1 Safe Area (1080×1080)');
    await text(rCover, '1:1 IG PROFILE GRID SAFE AREA (1080 × 1080)', 30, 395, { s: 14, c: '#10b981', w: 'Bold', upper: true });

    reelX += 1200;
  }

  // Set Page 1 as default active
  await fc.cmd('page.select', { id: page1 });

  // Save the master file
  console.log(`Saving FigCraft file to ${OUT_FILE}...`);
  const saved = await fc.tool('save_file', { path: OUT_FILE });
  console.log(`Saved master file: ${saved.path} (${Math.round(saved.bytes / 1024)} KB)`);

  // Copy to final-assets
  const finalDest = path.join(FINAL_ASSETS, 'CampBuddy-Social-Media-Kit.figcraft');
  fs.copyFileSync(OUT_FILE, finalDest);
  console.log(`Copied to final-assets: ${finalDest}`);

} catch (err) {
  console.error('Error generating FigCraft kit:', err);
  process.exitCode = 1;
} finally {
  fc.close();
}
