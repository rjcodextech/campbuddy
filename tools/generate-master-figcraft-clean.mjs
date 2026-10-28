// Master FigCraft Builder for CampBuddy:
// 1. Channel branding strictly matching user's 3 attached images (YouTube, X, Instagram)
// 2. No city names at top (only logo)
// 3. WPSimplified logo at bottom
// 4. Topic-specific screen grabs for each reel
// 5. No guideline lines or texts
// 6. No open-source / app-store claim lines
// 7. Pure Hinglish in Latin script
import fs from 'node:fs';
import path from 'node:path';
import { connect } from 'file:///E:/Sites/InHouse/wpsimplified/Studio/Design/scripts/fc.mjs';

const ROOT_MKT = 'E:/Sites/InHouse/campbuddy/marketing';
const OUT_FILE = path.join(ROOT_MKT, 'CampBuddy-Social-Media-Kit.figcraft');
const FINAL_FILE = path.join(ROOT_MKT, 'final-assets', 'CampBuddy-Social-Media-Kit.figcraft');
const SHARED_IMG = path.join(ROOT_MKT, 'usp-campaign', 'shared', 'img');
const SCREENS_DIR = path.join(ROOT_MKT, 'usp-campaign', 'screens');

const reelsData = JSON.parse(fs.readFileSync(path.join(ROOT_MKT, 'usp-campaign', 'reels', 'reels.json'), 'utf8'));

// Topic screen mapping
const topicScreens = {
  r01: 'bengaluru/guide.png',
  r02: 'delhi/myday-mine.png',
  r03: 'asia/explore.png',
  r04: 'bengaluru/camp-card-full.png',
  r05: 'delhi/contribute-matches.png',
  r06: 'asia/offline-home.png',
  r07: 'rajasthan/memories.png',
  r08: 'delhi-late/admin-delay.png',
  r09: 'delhi-late/banner.png',
  r10: 'bengaluru/deals.png'
};

console.log('Connecting to FigCraft MCP client...');
const fc = await connect(['E:/Sites/InHouse']);

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

async function drawDaylightGround(parent, w, h) {
  await rect(parent, 0, 0, w, h, { fill: '#fafafd' }, 'Bg / Paper');
  await rect(parent, 0, h * 0.65, w, h * 0.35, { fill: '#fff7ed', opacity: 0.7 }, 'Bg / Warm Tint');
  const m = Math.min(w, h);
  await ellipse(parent, w - m * 0.5, -m * 0.25, m * 0.8, m * 0.75, { fill: '#f59e0b', opacity: 0.16 }, 'Glow / Ochre Top-Right');
  await ellipse(parent, -m * 0.25, h - m * 0.55, m * 0.8, m * 0.7, { fill: '#ea580c', opacity: 0.12 }, 'Glow / Orange Bottom-Left');
  // Bottom flowing wave accent ribbon lines (matching attached references)
  await rect(parent, 0, h - 12, w, 4, { fill: '#ea580c', opacity: 0.35 }, 'Accent / Wave Line 1');
  await rect(parent, 30, h - 22, w - 60, 2.5, { fill: '#f59e0b', opacity: 0.45 }, 'Accent / Wave Line 2');
  await rect(parent, 60, h - 30, w - 120, 1.5, { fill: '#fb7185', opacity: 0.3 }, 'Accent / Wave Line 3');
}

try {
  console.log('Loading Baloo 2 font...');
  await fc.cmd('text.loadFont', { path: 'E:/Sites/InHouse/wpsimplified/Studio/Design/assets/fonts/Baloo2-Variable.ttf' }).catch(() => {});

  const pages = await fc.cmd('page.list');
  const page1 = pages[0].id;
  await fc.cmd('page.rename', { id: page1, name: '01 · Channel Branding (YouTube, X, Instagram)' });
  await fc.cmd('page.setBackground', { id: page1, color: '#f8fafc' });

  const page2 = (await fc.cmd('page.add', { name: '02 · Social Feed Posts (1080×1350)' })).id;
  await fc.cmd('page.setBackground', { id: page2, color: '#f8fafc' });

  const page3 = (await fc.cmd('page.add', { name: '03 · Social Link Cards (1200×627)' })).id;
  await fc.cmd('page.setBackground', { id: page3, color: '#f8fafc' });

  const page4 = (await fc.cmd('page.add', { name: '04 · Reel Covers (1080×1920)' })).id;
  await fc.cmd('page.setBackground', { id: page4, color: '#f8fafc' });

  // =========================================================================
  // PAGE 1: 01 · Channel Branding (Only YouTube, X, Instagram)
  // Matching user's 3 attached images 100%
  // =========================================================================
  console.log('Building Page 1: Channel Branding (YouTube, X, Instagram)...');
  await fc.cmd('page.select', { id: page1 });

  await text(null, 'CampBuddy / Channel Branding (YouTube · X · Instagram)', 80, 60, { f: 'head', s: 44, c: '#0f172a' });
  await text(null, 'Pristine channel artwork matching user reference lockup: Dual brand tiles, vertical divider, tagline & 5 feature chips', 80, 120, { s: 18, c: '#64748b' });

  // --- 1. YouTube Banner (2560 × 1440) ---
  const yt = await frame(null, 80, 200, 2560, 1440, 'YouTube Banner (2560×1440)', {
    stroke: '#e2e8f0', strokeWeight: 2, cornerRadius: 28, effects: [dropShadow(24, 60, 0.08)]
  });
  await drawDaylightGround(yt, 2560, 1440);

  // Center Content Stage (Safe Area: 1546 × 423 at x: 507, y: 508)
  const ytCenter = await frame(yt, 507, 508, 1546, 423, 'YouTube Center Lockup', { clip: false });

  // Left Dual Brand Cards
  // CampBuddy Column
  const ytCbCard = await frame(ytCenter, 20, 110, 210, 100, 'CampBuddy Card', {
    fill: '#ffffff', stroke: '#fed7aa', strokeWeight: 1.5, cornerRadius: 18, effects: [dropShadow(8, 20, 0.06)]
  });
  await placeImage(ytCbCard, path.join(SHARED_IMG, 'logo-wordmark.png'), 18, 16, 174, 68, 'fit');
  const ytCbPill = await frame(ytCenter, 20, 222, 210, 38, 'campbuddy.club Pill', {
    fill: '#ffffff', stroke: '#fed7aa', strokeWeight: 1.5, cornerRadius: 19
  });
  await text(ytCbPill, '🌐 campbuddy.club', 26, 10, { f: 'head', s: 13, c: '#ea580c' });

  // WPSimplified Column
  const ytWpsCard = await frame(ytCenter, 255, 110, 210, 100, 'WPSimplified Card', {
    fill: '#ffffff', stroke: '#fed7aa', strokeWeight: 1.5, cornerRadius: 18, effects: [dropShadow(8, 20, 0.06)]
  });
  await placeImage(ytWpsCard, path.join(SHARED_IMG, 'wpsimplified-logo.png'), 18, 16, 174, 68, 'fit');
  const ytWpsPill = await frame(ytCenter, 255, 222, 210, 38, 'wpsimplified.in Pill', {
    fill: '#ffffff', stroke: '#fed7aa', strokeWeight: 1.5, cornerRadius: 19
  });
  await text(ytWpsPill, '🌐 wpsimplified.in', 26, 10, { f: 'head', s: 13, c: '#ea580c' });

  // Slim vertical divider line
  await rect(ytCenter, 495, 95, 2, 175, { fill: '#cbd5e1' }, 'Center Divider');

  // Right Message Block
  // Kicker
  await text(ytCenter, '〰  MADE FOR WORDCAMP ATTENDEES AND ORGANIZERS', 530, 95, {
    f: 'head', s: 17, c: '#ea580c', ls: 2, upper: true
  });
  // Big Headline: "Your WordCamp companion"
  await text(ytCenter, 'Your WordCamp companion', 530, 130, {
    f: 'head', s: 66, c: '#0f172a'
  });
  // Orange underline accent under companion
  await rect(ytCenter, 920, 202, 280, 5, { fill: '#ea580c', cornerRadius: 2 }, 'Wave Underline');

  // 5 Feature Chips Row
  const channelChips = [
    '📅 My Day',
    '👥 People',
    '🪪 Camp Card',
    '🎯 Quests',
    '🔧 Contribute'
  ];
  let cx = 530;
  for (const chipText of channelChips) {
    const chipPill = await frame(ytCenter, cx, 222, 170, 38, `Chip / ${chipText}`, {
      fill: '#ffffff', stroke: '#fed7aa', strokeWeight: 1.5, cornerRadius: 19
    });
    await text(chipPill, chipText, 16, 10, { s: 13, c: '#475569', w: 'Bold' });
    cx += 185;
  }

  // YouTube Profile Picture (800 × 800)
  const ytAv = await frame(null, 2700, 200, 800, 800, 'YouTube Profile Picture (800×800)', {
    stroke: '#e2e8f0', strokeWeight: 2, cornerRadius: 36, effects: [dropShadow(24, 60, 0.08)]
  });
  await drawDaylightGround(ytAv, 800, 800);
  const ytAvPlate = await frame(ytAv, 160, 160, 480, 480, 'White Plate', {
    fill: '#ffffff', stroke: '#fed7aa', strokeWeight: 3, cornerRadius: 120, effects: [dropShadow(20, 48, 0.12)]
  });
  await placeImage(ytAvPlate, path.join(SHARED_IMG, 'icon-512.png'), 60, 60, 360, 360, 'fit');

  // --- 2. X / Twitter Header (1500 × 500) ---
  // Matching user's attached reference 3 (media_1793095464249_8509025f.png)
  const xH = await frame(null, 80, 1720, 1500, 500, 'X (Twitter) Header (1500×500)', {
    stroke: '#e2e8f0', strokeWeight: 2, cornerRadius: 24, effects: [dropShadow(20, 48, 0.06)]
  });
  await drawDaylightGround(xH, 1500, 500);

  const xCenter = await frame(xH, 40, 60, 1420, 380, 'X Center Lockup', { clip: false });

  // Left Dual Brand Cards
  const xCbCard = await frame(xCenter, 20, 100, 190, 90, 'CampBuddy Card', {
    fill: '#ffffff', stroke: '#fed7aa', strokeWeight: 1.5, cornerRadius: 16, effects: [dropShadow(8, 20, 0.06)]
  });
  await placeImage(xCbCard, path.join(SHARED_IMG, 'logo-wordmark.png'), 15, 14, 160, 62, 'fit');
  const xCbPill = await frame(xCenter, 20, 200, 190, 36, 'campbuddy.club Pill', {
    fill: '#ffffff', stroke: '#fed7aa', strokeWeight: 1.5, cornerRadius: 18
  });
  await text(xCbPill, '🌐 campbuddy.club', 22, 9, { f: 'head', s: 12, c: '#ea580c' });

  const xWpsCard = await frame(xCenter, 230, 100, 190, 90, 'WPSimplified Card', {
    fill: '#ffffff', stroke: '#fed7aa', strokeWeight: 1.5, cornerRadius: 16, effects: [dropShadow(8, 20, 0.06)]
  });
  await placeImage(xWpsCard, path.join(SHARED_IMG, 'wpsimplified-logo.png'), 15, 14, 160, 62, 'fit');
  const xWpsPill = await frame(xCenter, 230, 200, 190, 36, 'wpsimplified.in Pill', {
    fill: '#ffffff', stroke: '#fed7aa', strokeWeight: 1.5, cornerRadius: 18
  });
  await text(xWpsPill, '🌐 wpsimplified.in', 22, 9, { f: 'head', s: 12, c: '#ea580c' });

  await rect(xCenter, 445, 90, 2, 155, { fill: '#cbd5e1' }, 'Center Divider');

  // Right Message Block
  await text(xCenter, '〰  MADE FOR WORDCAMP ATTENDEES AND ORGANIZERS', 475, 85, {
    f: 'head', s: 15, c: '#ea580c', ls: 2, upper: true
  });
  await text(xCenter, 'Your WordCamp companion', 475, 115, {
    f: 'head', s: 56, c: '#0f172a'
  });
  await rect(xCenter, 805, 178, 240, 4.5, { fill: '#ea580c', cornerRadius: 2 }, 'Wave Underline');

  let xcx = 475;
  for (const chipText of channelChips) {
    const chipPill = await frame(xCenter, xcx, 198, 160, 36, `Chip / ${chipText}`, {
      fill: '#ffffff', stroke: '#fed7aa', strokeWeight: 1.5, cornerRadius: 18
    });
    await text(chipPill, chipText, 14, 9, { s: 12, c: '#475569', w: 'Bold' });
    xcx += 172;
  }

  // X Profile Picture (400 × 400)
  const xAv = await frame(null, 1640, 1720, 400, 400, 'X Profile Picture (400×400)', {
    stroke: '#e2e8f0', strokeWeight: 2, cornerRadius: 24, effects: [dropShadow(20, 48, 0.06)]
  });
  await drawDaylightGround(xAv, 400, 400);
  const xAvPlate = await frame(xAv, 70, 70, 260, 260, 'White Plate', {
    fill: '#ffffff', stroke: '#fed7aa', strokeWeight: 2, cornerRadius: 65, effects: [dropShadow(16, 36, 0.1)]
  });
  await placeImage(xAvPlate, path.join(SHARED_IMG, 'icon-512.png'), 35, 35, 190, 190, 'fit');

  // --- 3. Instagram Branding ---
  // Instagram Profile Picture (1080 × 1080)
  const igAv = await frame(null, 2100, 1720, 1080, 1080, 'Instagram Profile Picture (1080×1080)', {
    stroke: '#e2e8f0', strokeWeight: 2, cornerRadius: 36, effects: [dropShadow(24, 60, 0.08)]
  });
  await drawDaylightGround(igAv, 1080, 1080);
  const igPlate = await frame(igAv, 240, 240, 600, 600, 'White Plate', {
    fill: '#ffffff', stroke: '#fed7aa', strokeWeight: 4, cornerRadius: 150, effects: [dropShadow(24, 56, 0.12)]
  });
  await placeImage(igPlate, path.join(SHARED_IMG, 'icon-512.png'), 75, 75, 450, 450, 'fit');

  // =========================================================================
  // PAGE 3: 03 · Social Link Cards (1200×627)
  // Clean, no city names, no guidelines, no open-source text, topic-matched screens
  // =========================================================================
  console.log('Building Page 3: Social Link Cards (1200×627)...');
  await fc.cmd('page.select', { id: page3 });

  await text(null, 'CampBuddy / Social Link Cards (1200×627 Landscape)', 80, 60, { f: 'head', s: 44, c: '#0f172a' });
  await text(null, 'Pristine cards: Only logo at top, WPSimplified at bottom, topic-specific app mockups, zero empty space', 80, 120, { s: 18, c: '#64748b' });

  let cardY = 200;
  for (const r of reelsData.reels) {
    const en = r.post_en || r.post;
    const hi = r.post_hi || r.post;
    const topicImg = path.join(SCREENS_DIR, topicScreens[r.id] || 'bengaluru/home-now.png');
    const evLogoPath = path.join(SHARED_IMG, `event-${r.event.toLowerCase().replace(/wordcamp\s*/i, '').replace(/\s*\d+/g, '').trim()}.png`);

    for (const [colIdx, lang, postData, langLabel] of [[0, 'en', en, 'EN'], [1, 'hi', hi, 'HI']]) {
      const cardX = colIdx === 0 ? 80 : 1360;
      const cFrame = await frame(null, cardX, cardY, 1200, 627, `LinkCard ${r.id} / ${r.slug} [${langLabel}]`, {
        stroke: '#e2e8f0', strokeWeight: 2, cornerRadius: 32, effects: [dropShadow(24, 56, 0.08)]
      });
      await drawDaylightGround(cFrame, 1200, 627);

      // Top Row (y: 44): ONLY WordCamp Logo (NO city text!)
      const evTile = await frame(cFrame, 50, 44, 200, 44, 'Event Logo Only', {
        fill: '#ffffff', stroke: '#fed7aa', strokeWeight: 1.5, cornerRadius: 22
      });
      if (fs.existsSync(evLogoPath)) {
        await placeImage(evTile, evLogoPath, 15, 8, 170, 28, 'fit');
      }

      // Kicker Pill (y: 44, x: 265)
      const kickPill = await frame(cFrame, 265, 44, 260, 44, 'Kicker Pill', {
        fill: '#fff7ed', stroke: '#fed7aa', strokeWeight: 1.5, cornerRadius: 22
      });
      await text(kickPill, `⚡ ${(postData.kicker || 'WORDCAMP').toUpperCase()}`, 16, 12, { f: 'head', s: 14, c: '#ea580c', ls: 1 });

      // Creative Big Headline (y: 110, font: 56px, lh: 62px)
      await text(cFrame, postData.title.replace(/\*/g, ''), 50, 110, {
        f: 'head', s: 56, c: '#0f172a', width: 660, lh: 62
      });

      // Subtitle (y: 250, font: 21px, lh: 30px) — pure Hinglish in Latin script
      await text(cFrame, postData.sub, 50, 250, {
        s: 21, c: '#475569', width: 660, lh: 30
      });

      // Feature Pills Row (y: 350)
      const pills = lang === 'en'
        ? ['⚡ 100% Offline Ready', '🤝 Meet Attendees', '🎯 Quests & Fun']
        : ['⚡ 100% Offline Ready', '🤝 Logon Se Milo', '🎯 Quests Aur Badges'];
      let px = 50;
      for (const p of pills) {
        const pBox = await frame(cFrame, px, 350, 205, 40, `Pill / ${p}`, {
          fill: '#ffffff', stroke: '#fed7aa', strokeWeight: 1.5, cornerRadius: 20
        });
        await text(pBox, p, 14, 11, { s: 13, c: '#9a3412', w: 'Bold' });
        px += 220;
      }

      // Master Brand Plate (y: 470, h: 64px, w: 660px) with WPSimplified Logo replacing city name!
      const brandPlate = await frame(cFrame, 50, 470, 660, 64, 'Master Brand Plate', {
        fill: '#ffffff', stroke: '#fed7aa', strokeWeight: 1.5, cornerRadius: 18, effects: [dropShadow(8, 20, 0.05)]
      });
      await placeImage(brandPlate, path.join(SHARED_IMG, 'logo-wordmark.png'), 18, 14, 170, 36, 'fit');
      await rect(brandPlate, 205, 14, 1.5, 36, { fill: '#cbd5e1' });
      // WPSimplified logo placed prominently
      await placeImage(brandPlate, path.join(SHARED_IMG, 'wpsimplified-logo.png'), 225, 14, 170, 36, 'fit');
      // campbuddy.club badge on right
      const urlBadge = await frame(brandPlate, 480, 12, 165, 40, 'URL Badge', {
        fill: '#fff7ed', stroke: '#fed7aa', strokeWeight: 1.5, cornerRadius: 20
      });
      await text(urlBadge, 'campbuddy.club', 20, 11, { f: 'head', s: 14, c: '#ea580c' });

      // Right Column: Topic-matched UI Screen Card Mockup (x: 740, y: 44, w: 410, h: 527)
      const mockStage = await frame(cFrame, 740, 44, 410, 527, 'Screen Mockup Stage', {
        fill: '#ffffff', stroke: '#e2e8f0', strokeWeight: 2, cornerRadius: 28, effects: [dropShadow(24, 56, 0.12)]
      });
      if (fs.existsSync(topicImg)) {
        await placeImage(mockStage, topicImg, 15, 15, 380, 497, 'fit');
      }
    }

    cardY += 720;
  }

  // =========================================================================
  // PAGE 2: 02 · Social Feed Posts (1080×1350)
  // Clean, no city names, no guidelines, topic-matched screens
  // =========================================================================
  console.log('Building Page 2: Social Feed Posts (1080×1350)...');
  await fc.cmd('page.select', { id: page2 });

  await text(null, 'CampBuddy / Social Media Feed Posts (4:5 Portrait — 1080×1350)', 80, 60, { f: 'head', s: 44, c: '#0f172a' });
  await text(null, 'Clean 4:5 Posts: Only event logo at top, WPSimplified logo at bottom, topic-matched screens, no guidelines', 80, 120, { s: 18, c: '#64748b' });

  let postY = 200;
  for (const r of reelsData.reels) {
    const en = r.post_en || r.post;
    const hi = r.post_hi || r.post;
    const topicImg = path.join(SCREENS_DIR, topicScreens[r.id] || 'bengaluru/home-now.png');
    const evLogoPath = path.join(SHARED_IMG, `event-${r.event.toLowerCase().replace(/wordcamp\s*/i, '').replace(/\s*\d+/g, '').trim()}.png`);

    for (const [colIdx, lang, postData, langLabel] of [[0, 'en', en, 'EN'], [1, 'hi', hi, 'HI']]) {
      const cardX = colIdx === 0 ? 80 : 1240;
      const pFrame = await frame(null, cardX, postY, 1080, 1350, `FeedPost ${r.id} / ${r.slug} [${langLabel}]`, {
        stroke: '#e2e8f0', strokeWeight: 2, cornerRadius: 36, effects: [dropShadow(28, 64, 0.08)]
      });
      await drawDaylightGround(pFrame, 1080, 1350);

      // Top: Event Logo Only (NO city text!)
      const evTile = await frame(pFrame, 70, 60, 240, 52, 'Event Logo Only', {
        fill: '#ffffff', stroke: '#fed7aa', strokeWeight: 2, cornerRadius: 26, effects: [dropShadow(6, 16, 0.04)]
      });
      if (fs.existsSync(evLogoPath)) {
        await placeImage(evTile, evLogoPath, 15, 10, 210, 32, 'fit');
      }

      // Kicker Pill
      const kPill = await frame(pFrame, 680, 60, 330, 52, 'Kicker Pill', {
        fill: '#fff7ed', stroke: '#fed7aa', strokeWeight: 2, cornerRadius: 26
      });
      await text(kPill, `⚡ ${(postData.kicker || 'WORDCAMP GUIDE').toUpperCase()}`, 20, 16, { f: 'head', s: 14, c: '#ea580c', ls: 1 });

      // Big Editorial Title (y: 145, font: 78px, lh: 86px)
      await text(pFrame, postData.title.replace(/\*/g, ''), 70, 145, {
        f: 'head', s: 78, c: '#0f172a', width: 940, lh: 86
      });

      // Subtitle (y: 330, font: 25px, lh: 36px) — clean Hinglish
      await text(pFrame, postData.sub, 70, 330, {
        s: 25, c: '#475569', width: 940, lh: 36
      });

      // Feature Callout Pills Row (y: 420)
      const feedPills = lang === 'en'
        ? ['⚡ 100% Offline Ready', '🤝 Camp Card Discovery', '🎯 Gamified Quests']
        : ['⚡ 100% Offline Ready', '🤝 Camp Card Networking', '🎯 Easy Quests'];
      let fpx = 70;
      for (const fp of feedPills) {
        const fpBox = await frame(pFrame, fpx, 420, 290, 46, `Pill / ${fp}`, {
          fill: '#ffffff', stroke: '#fed7aa', strokeWeight: 1.5, cornerRadius: 23
        });
        await text(fpBox, fp, 18, 13, { s: 14, c: '#9a3412', w: 'Bold' });
        fpx += 310;
      }

      // Topic-matched UI Screen Mockup Stage (y: 490, h: 630, w: 940)
      const mockStage = await frame(pFrame, 70, 490, 940, 630, 'App UI Stage', {
        fill: '#ffffff', stroke: '#e2e8f0', strokeWeight: 2, cornerRadius: 28, effects: [dropShadow(20, 48, 0.1)]
      });
      if (fs.existsSync(topicImg)) {
        await placeImage(mockStage, topicImg, 20, 20, 900, 590, 'fit');
      }

      // Bottom Action Foot (y: 1150, h: 130, w: 940): WPSimplified logo replaces city name!
      const actionFoot = await frame(pFrame, 70, 1150, 940, 130, 'Action Foot', {
        fill: '#ffffff', stroke: '#fed7aa', strokeWeight: 1.5, cornerRadius: 24, effects: [dropShadow(10, 24, 0.05)]
      });
      await placeImage(actionFoot, path.join(SHARED_IMG, 'logo-wordmark.png'), 30, 20, 190, 40, 'fit');
      await rect(actionFoot, 235, 20, 1.5, 40, { fill: '#cbd5e1' });
      await placeImage(actionFoot, path.join(SHARED_IMG, 'wpsimplified-logo.png'), 255, 20, 190, 40, 'fit');
      await text(actionFoot, postData.scan || 'Scan to explore live schedule & networking', 30, 80, { f: 'head', s: 18, c: '#0f172a' });

      const urlBadge = await frame(actionFoot, 730, 40, 180, 48, 'URL Badge', {
        fill: '#fff7ed', stroke: '#fed7aa', strokeWeight: 1.5, cornerRadius: 24
      });
      await text(urlBadge, 'campbuddy.club', 20, 14, { f: 'head', s: 16, c: '#ea580c' });
    }

    postY += 1450;
  }

  // =========================================================================
  // PAGE 4: 04 · Reel Covers (1080×1920)
  // Clean, no city names, no guidelines, topic-matched screens
  // =========================================================================
  console.log('Building Page 4: Reel Covers (1080×1920)...');
  await fc.cmd('page.select', { id: page4 });

  await text(null, 'CampBuddy / 10 Reel Covers (1080×1920)', 80, 60, { f: 'head', s: 44, c: '#0f172a' });
  await text(null, 'Pristine 9:16 Covers: Logo only at top, WPSimplified at bottom, topic-specific screen grabs, zero guidelines', 80, 120, { s: 18, c: '#64748b' });

  let reelX = 80;
  for (const r of reelsData.reels) {
    const topicImg = path.join(SCREENS_DIR, topicScreens[r.id] || 'bengaluru/home-now.png');
    const evLogoPath = path.join(SHARED_IMG, `event-${r.event.toLowerCase().replace(/wordcamp\s*/i, '').replace(/\s*\d+/g, '').trim()}.png`);

    const rCover = await frame(null, reelX, 200, 1080, 1920, `ReelCover ${r.id} / ${r.slug} (1080×1920)`, {
      stroke: '#e2e8f0', strokeWeight: 2, cornerRadius: 40, effects: [dropShadow(32, 72, 0.1)]
    });
    await drawDaylightGround(rCover, 1080, 1920);

    // 1. Top Event Logo Only (y: 110, centered at x: 380, NO city text!)
    const evTile = await frame(rCover, 380, 110, 320, 90, 'Event Logo Only', {
      fill: '#ffffff', stroke: '#fed7aa', strokeWeight: 2, cornerRadius: 26, effects: [dropShadow(12, 28, 0.08)]
    });
    if (fs.existsSync(evLogoPath)) {
      await placeImage(evTile, evLogoPath, 20, 15, 280, 60, 'fit');
    }

    // 2. Kicker Pill (y: 235, centered at x: 340)
    const kPill = await frame(rCover, 340, 235, 400, 52, 'Kicker Pill', {
      fill: '#ffffff', stroke: '#fdba74', strokeWeight: 2, cornerRadius: 26, effects: [dropShadow(8, 20, 0.05)]
    });
    await text(kPill, `⚡ ${(r.cover.kicker || r.cover.chip || 'CAMPBUDDY').toUpperCase()}`, 30, 16, { f: 'head', s: 15, c: '#c2410c', ls: 2 });

    // 3. Huge Punchy Hook Headline (y: 315, font: 96px, lh: 104px, centered)
    await text(rCover, r.cover.title.replace(/\*/g, ''), 70, 315, {
      f: 'head', s: 96, c: '#0f172a', width: 940, lh: 104, align: 'center'
    });

    // 4. Subtitle (y: 555, font: 28px, lh: 40px, centered)
    await text(rCover, r.post.sub, 90, 555, {
      s: 28, c: '#475569', width: 900, lh: 40, align: 'center'
    });

    // 5. Phone / Screen Mockup Stage (y: 670, w: 840, h: 940, centered at x: 120) with topic-matched screenshot!
    const mockupStage = await frame(rCover, 120, 670, 840, 940, 'Topic Screen Mockup', {
      fill: '#ffffff', stroke: '#e2e8f0', strokeWeight: 2, cornerRadius: 36, effects: [dropShadow(28, 64, 0.14)]
    });
    if (fs.existsSync(topicImg)) {
      await placeImage(mockupStage, topicImg, 20, 20, 800, 900, 'fit');
    }

    // 6. Master Brand Outro Plate (y: 1720, h: 90, w: 940, centered at x: 70) — WPSimplified logo replaces city name!
    const outroPlate = await frame(rCover, 70, 1720, 940, 90, 'Master Brand Plate', {
      fill: '#ffffff', stroke: '#fed7aa', strokeWeight: 2, cornerRadius: 28, effects: [dropShadow(16, 40, 0.08)]
    });
    await placeImage(outroPlate, path.join(SHARED_IMG, 'logo-wordmark.png'), 40, 22, 220, 48, 'fit');
    await rect(outroPlate, 280, 20, 2, 50, { fill: '#cbd5e1' });
    await placeImage(outroPlate, path.join(SHARED_IMG, 'wpsimplified-logo.png'), 300, 22, 220, 48, 'fit');

    const ctaBadge = await frame(outroPlate, 680, 19, 220, 52, 'CTA Badge', {
      fill: '#fff7ed', stroke: '#fed7aa', strokeWeight: 2, cornerRadius: 26
    });
    await text(ctaBadge, 'campbuddy.club', 28, 16, { f: 'head', s: 18, c: '#ea580c' });

    reelX += 1200;
  }

  // Set Page 1 as default active
  await fc.cmd('page.select', { id: page1 });

  // Save the master file directly at marketing root
  console.log(`Saving FigCraft file directly to ${OUT_FILE}...`);
  const saved = await fc.tool('save_file', { path: OUT_FILE });
  console.log(`Saved master file: ${saved.path} (${Math.round(saved.bytes / 1024)} KB)`);

  // Also copy to final-assets
  fs.copyFileSync(OUT_FILE, FINAL_FILE);
  console.log(`Copied to final-assets: ${FINAL_FILE}`);

} catch (err) {
  console.error('Error generating clean FigCraft kit:', err);
  process.exitCode = 1;
} finally {
  fc.close();
}
