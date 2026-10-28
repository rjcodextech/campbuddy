// Script to generate the comprehensive master FigCraft design file: CampBuddy-Social-Media-Kit.figcraft
import fs from 'node:fs';
import path from 'node:path';
import { connect } from 'file:///E:/Sites/InHouse/wpsimplified/Studio/Design/scripts/fc.mjs';

const ROOT_DESIGN = 'E:/Sites/InHouse/campbuddy/marketing';
const FINAL_ASSETS = 'E:/Sites/InHouse/campbuddy/marketing/final-assets';
const OUT_FILE = path.join(ROOT_DESIGN, 'CampBuddy-Social-Media-Kit.figcraft');
const SHARED_IMG = path.join(ROOT_DESIGN, 'usp-campaign', 'shared', 'img');
const SCREENS_DIR = path.join(ROOT_DESIGN, 'usp-campaign', 'screens');

const reelsData = JSON.parse(fs.readFileSync(path.join(ROOT_DESIGN, 'usp-campaign', 'reels', 'reels.json'), 'utf8'));
const channelData = JSON.parse(fs.readFileSync(path.join(ROOT_DESIGN, 'usp-campaign', 'design', 'channel.json'), 'utf8'));

console.log('Connecting to FigCraft MCP client...');
const fc = await connect(['E:/Sites/InHouse']);

// Helper functions
async function frame(parent, x, y, w, h, name, props = {}) {
  const p = {
    clip: props.clip ?? true,
    cornerRadius: props.cornerRadius || 0,
    strokeWeight: props.strokeWeight || 0,
    fills: props.fill ? [props.fill] : (props.fills || []),
    strokes: props.stroke ? [props.stroke] : (props.strokes || []),
    effects: props.effects || [],
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
  if (!fs.existsSync(filePath)) {
    console.warn(`File not found: ${filePath}`);
    return null;
  }
  const res = await fc.cmd('image.place', { path: filePath.replaceAll('\\', '/'), x, y, width: w, height: h, parent, mode });
  return res.id;
}

function dropShadow(y = 12, blur = 32, opacity = 0.08) {
  const alphaHex = Math.round(opacity * 255).toString(16).padStart(2, '0');
  return { type: 'dropShadow', color: `#0f172a${alphaHex}`, offset: [0, y], radius: blur };
}

try {
  console.log('Loading Baloo 2 font...');
  await fc.cmd('text.loadFont', { path: 'E:/Sites/InHouse/wpsimplified/Studio/Design/assets/fonts/Baloo2-Variable.ttf' }).catch(() => {});

  const pages = await fc.cmd('page.list');
  const page1 = pages[0].id;
  await fc.cmd('page.rename', { id: page1, name: '01 · Channel Branding & Handles' });
  await fc.cmd('page.setBackground', { id: page1, color: '#f8fafc' });

  const page2 = (await fc.cmd('page.add', { name: '02 · Social Feed Posts (1080×1350)' })).id;
  await fc.cmd('page.setBackground', { id: page2, color: '#f8fafc' });

  const page3 = (await fc.cmd('page.add', { name: '03 · Social Link Cards (1200×627)' })).id;
  await fc.cmd('page.setBackground', { id: page3, color: '#f8fafc' });

  const page4 = (await fc.cmd('page.add', { name: '04 · Reel Covers & Safe Zones' })).id;
  await fc.cmd('page.setBackground', { id: page4, color: '#f8fafc' });

  // =========================================================================
  // PAGE 1: 01 · Channel Branding & Handles
  // =========================================================================
  console.log('Building Page 1: Channel Branding & Handles...');
  await fc.cmd('page.select', { id: page1 });

  // Header Title
  await text(null, 'CampBuddy / Social Media Channels & Handles', 80, 60, { f: 'head', s: 44, c: '#0f172a' });
  await text(null, 'Master banners, profile avatars, and story highlight covers for YouTube, X, Facebook, LinkedIn, GitHub & Discord', 80, 120, { s: 18, c: '#64748b' });

  // YouTube Banner (2560 × 1440)
  const ytFrame = await frame(null, 80, 200, 2560, 1440, 'YouTube Banner (2560×1440)', {
    fill: '#fafafd', stroke: '#e2e8f0', strokeWeight: 2, cornerRadius: 24, effects: [dropShadow(20, 48, 0.06)]
  });
  // Banner Background Elements
  await rect(ytFrame, 0, 0, 2560, 1440, { fill: '#ffffff' });
  await ellipse(ytFrame, 1280 - 450, 720 - 450, 900, 900, { fill: '#ffedd5' }); // warm glow
  await placeImage(ytFrame, path.join(SHARED_IMG, 'logo-wordmark.png'), 507 + 120, 508 + 80, 440, 120, 'fit');
  await text(ytFrame, 'YOUR WORDFUN COMPANION', 507 + 124, 508 + 220, { f: 'head', s: 22, c: '#ea580c', ls: 3, upper: true });
  await text(ytFrame, 'Plan your talks, meet your people & keep your Camp Card', 507 + 124, 508 + 260, { s: 26, c: '#334155', width: 780 });
  await placeImage(ytFrame, path.join(SHARED_IMG, 'icon-512.png'), 507 + 980, 508 + 70, 280, 280, 'fit');
  // Safe Area Outline Guide
  await rect(ytFrame, 507, 508, 1546, 423, { stroke: '#f97316', strokeWeight: 2 }, 'Guide / YouTube Safe Area (1546×423)');
  await text(ytFrame, 'SAFE AREA (DESKTOP & MOBILE CROP: 1546 × 423)', 507 + 12, 508 - 24, { s: 12, c: '#ea580c', upper: true });

  // Facebook Cover (1640 × 924)
  const fbFrame = await frame(null, 2700, 200, 1640, 924, 'Facebook Cover (1640×924)', {
    fill: '#ffffff', stroke: '#e2e8f0', strokeWeight: 2, cornerRadius: 24, effects: [dropShadow(20, 48, 0.06)]
  });
  await ellipse(fbFrame, 820 - 300, 462 - 300, 600, 600, { fill: '#ffedd5' });
  await placeImage(fbFrame, path.join(SHARED_IMG, 'logo-wordmark.png'), 200 + 100, 200, 420, 115, 'fit');
  await text(fbFrame, 'Your WordCamp companion', 200 + 104, 340, { f: 'head', s: 36, c: '#0f172a' });
  await text(fbFrame, 'Never alone at any WordCamp · Free & Open Source', 200 + 104, 395, { s: 22, c: '#64748b' });
  await placeImage(fbFrame, path.join(SHARED_IMG, 'icon-512.png'), 1080, 220, 260, 260, 'fit');
  await rect(fbFrame, 200, 107, 1240, 710, { stroke: '#f97316', strokeWeight: 2 }, 'Guide / Facebook Safe Area');

  // X / Twitter Header (1500 × 500)
  const xFrame = await frame(null, 80, 1720, 1500, 500, 'X (Twitter) Header (1500×500)', {
    fill: '#ffffff', stroke: '#e2e8f0', strokeWeight: 2, cornerRadius: 20, effects: [dropShadow(20, 48, 0.06)]
  });
  await placeImage(xFrame, path.join(SHARED_IMG, 'logo-wordmark.png'), 160, 160, 380, 104, 'fit');
  await text(xFrame, 'Your WordCamp companion', 160, 290, { f: 'head', s: 30, c: '#0f172a' });
  await text(xFrame, 'Plan talks · Find people · Offline ready · campbuddy.club', 160, 335, { s: 19, c: '#64748b' });
  await placeImage(xFrame, path.join(SHARED_IMG, 'icon-512.png'), 1120, 110, 260, 260, 'fit');

  // LinkedIn Banner (1584 × 396)
  const liFrame = await frame(null, 1640, 1720, 1584, 396, 'LinkedIn Banner (1584×396)', {
    fill: '#ffffff', stroke: '#e2e8f0', strokeWeight: 2, cornerRadius: 20, effects: [dropShadow(20, 48, 0.06)]
  });
  await placeImage(liFrame, path.join(SHARED_IMG, 'logo-wordmark.png'), 360, 120, 360, 98, 'fit');
  await text(liFrame, 'The Community Companion for WordPress WordCamps', 360, 240, { f: 'head', s: 24, c: '#0f172a' });
  await text(liFrame, 'Crafted with care by WPSimplified · Open source', 360, 276, { s: 17, c: '#64748b' });
  await placeImage(liFrame, path.join(SHARED_IMG, 'icon-512.png'), 1280, 78, 220, 220, 'fit');

  // Profile Pictures / Avatars (800 × 800)
  let avX = 80;
  const avTitles = [
    'Profile / Daylight Brand',
    'Profile / Warm Daylight',
    'Profile / High Contrast Ink',
    'Profile / Co-Brand Lockup'
  ];
  const avBgs = ['#ffffff', '#fff7ed', '#0f172a', '#fafafd'];

  for (let i = 0; i < 4; i++) {
    const avFrame = await frame(null, avX, 2300, 800, 800, avTitles[i], {
      fill: avBgs[i], stroke: '#e2e8f0', strokeWeight: 2, cornerRadius: 32, effects: [dropShadow(20, 48, 0.06)]
    });
    // Circular Guide
    await ellipse(avFrame, 40, 40, 720, 720, { stroke: '#fdba74', strokeWeight: 2 }, 'Guide / Circle Crop (720px)');
    // Elevated Center Plate
    const plateFill = (i === 2) ? '#1e293b' : '#ffffff';
    await rect(avFrame, 160, 160, 480, 480, { fill: plateFill, cornerRadius: 120, stroke: '#fed7aa', strokeWeight: 3 });
    await placeImage(avFrame, path.join(SHARED_IMG, 'icon-512.png'), 220, 220, 360, 360, 'fit');
    avX += 880;
  }

  // =========================================================================
  // PAGE 2: 02 · Social Feed Posts (1080×1350)
  // =========================================================================
  console.log('Building Page 2: Social Feed Posts (1080×1350)...');
  await fc.cmd('page.select', { id: page2 });

  await text(null, 'CampBuddy / Social Media Feed Posts (4:5 Portrait — 1080×1350)', 80, 60, { f: 'head', s: 44, c: '#0f172a' });
  await text(null, '10 Campaign Posts in Dual Languages (English left, Hinglish right) — Editable Typography & Layouts', 80, 120, { s: 18, c: '#64748b' });

  let postY = 200;
  for (const r of reelsData.reels) {
    const en = r.post_en || r.post;
    const hi = r.post_hi || r.post;

    // --- English Card (x: 80) ---
    const enCard = await frame(null, 80, postY, 1080, 1350, `Post ${r.id} / ${r.slug} [EN]`, {
      fill: '#fafafd', stroke: '#e2e8f0', strokeWeight: 2, cornerRadius: 36, effects: [dropShadow(24, 60, 0.08)]
    });
    // Top Event Tag Chip
    await rect(enCard, 80, 70, 320, 44, { fill: '#ffffff', cornerRadius: 22, stroke: '#fdba74', strokeWeight: 2 });
    await text(enCard, (r.event || 'WORDCAMP').toUpperCase(), 98, 83, { f: 'head', s: 14, c: '#c2410c', ls: 1 });
    // Kicker & Headline
    await text(enCard, (en.kicker || 'WordCamp Guide').toUpperCase(), 80, 140, { f: 'head', s: 20, c: '#ea580c', ls: 2, upper: true });
    await text(enCard, en.title.replace(/\*/g, ''), 80, 180, { f: 'head', s: 50, c: '#0f172a', width: 920, lh: 58 });
    await text(enCard, en.sub, 80, 300, { s: 24, c: '#475569', width: 920, lh: 36 });
    // UI Screen Card Mockup
    const screenImg = path.join(SCREENS_DIR, en.card || en.phone || 'bengaluru/home-now.png');
    await rect(enCard, 80, 430, 920, 740, { fill: '#ffffff', cornerRadius: 24, stroke: '#e2e8f0', strokeWeight: 2 });
    if (fs.existsSync(screenImg)) {
      await placeImage(enCard, screenImg, 110, 460, 860, 680, 'fit');
    }
    // Bottom Brand Plate
    await rect(enCard, 80, 1220, 920, 64, { fill: '#ffffff', cornerRadius: 16, stroke: '#f1f5f9', strokeWeight: 1 });
    await placeImage(enCard, path.join(SHARED_IMG, 'logo-wordmark.png'), 100, 1234, 180, 36, 'fit');
    await text(enCard, 'Crafted by WPSimplified', 300, 1242, { s: 15, c: '#64748b' });
    await text(enCard, 'campbuddy.club', 840, 1242, { f: 'head', s: 15, c: '#ea580c' });

    // --- Hinglish Card (x: 1240) ---
    const hiCard = await frame(null, 1240, postY, 1080, 1350, `Post ${r.id} / ${r.slug} [HI]`, {
      fill: '#fafafd', stroke: '#e2e8f0', strokeWeight: 2, cornerRadius: 36, effects: [dropShadow(24, 60, 0.08)]
    });
    // Top Event Tag Chip
    await rect(hiCard, 80, 70, 320, 44, { fill: '#ffffff', cornerRadius: 22, stroke: '#fdba74', strokeWeight: 2 });
    await text(hiCard, (r.event || 'WORDCAMP').toUpperCase(), 98, 83, { f: 'head', s: 14, c: '#c2410c', ls: 1 });
    // Kicker & Headline
    await text(hiCard, (hi.kicker || 'WordCamp Guide').toUpperCase(), 80, 140, { f: 'head', s: 20, c: '#ea580c', ls: 2, upper: true });
    await text(hiCard, hi.title.replace(/\*/g, ''), 80, 180, { f: 'head', s: 50, c: '#0f172a', width: 920, lh: 58 });
    await text(hiCard, hi.sub, 80, 300, { s: 24, c: '#475569', width: 920, lh: 36 });
    // UI Screen Card Mockup
    await rect(hiCard, 80, 430, 920, 740, { fill: '#ffffff', cornerRadius: 24, stroke: '#e2e8f0', strokeWeight: 2 });
    if (fs.existsSync(screenImg)) {
      await placeImage(hiCard, screenImg, 110, 460, 860, 680, 'fit');
    }
    // Bottom Brand Plate
    await rect(hiCard, 80, 1220, 920, 64, { fill: '#ffffff', cornerRadius: 16, stroke: '#f1f5f9', strokeWeight: 1 });
    await placeImage(hiCard, path.join(SHARED_IMG, 'logo-wordmark.png'), 100, 1234, 180, 36, 'fit');
    await text(hiCard, 'Crafted by WPSimplified', 300, 1242, { s: 15, c: '#64748b' });
    await text(hiCard, 'campbuddy.club', 840, 1242, { f: 'head', s: 15, c: '#ea580c' });

    postY += 1450;
  }

  // =========================================================================
  // PAGE 3: 03 · Social Link Cards (1200×627)
  // =========================================================================
  console.log('Building Page 3: Social Link Cards (1200×627)...');
  await fc.cmd('page.select', { id: page3 });

  await text(null, 'CampBuddy / Landscape Social Link Cards (1200×627)', 80, 60, { f: 'head', s: 44, c: '#0f172a' });
  await text(null, 'Optimized for X Large Summary Cards, LinkedIn Links, and Facebook Feed Share Previews', 80, 120, { s: 18, c: '#64748b' });

  let cardY = 200;
  for (const r of reelsData.reels) {
    const en = r.post_en || r.post;
    const hi = r.post_hi || r.post;
    const screenImg = path.join(SCREENS_DIR, en.card || en.phone || 'bengaluru/home-now.png');

    // EN Landscape Card (x: 80)
    const enLCard = await frame(null, 80, cardY, 1200, 627, `LinkCard ${r.id} [EN]`, {
      fill: '#ffffff', stroke: '#e2e8f0', strokeWeight: 2, cornerRadius: 28, effects: [dropShadow(20, 48, 0.06)]
    });
    // Left Content Column
    await text(enLCard, (en.kicker || 'CAMPBUDDY').toUpperCase(), 60, 60, { f: 'head', s: 16, c: '#ea580c', ls: 2, upper: true });
    await text(enLCard, en.title.replace(/\*/g, ''), 60, 95, { f: 'head', s: 40, c: '#0f172a', width: 560, lh: 46 });
    await text(enLCard, en.sub, 60, 200, { s: 19, c: '#475569', width: 560, lh: 28 });
    await placeImage(enLCard, path.join(SHARED_IMG, 'logo-wordmark.png'), 60, 520, 180, 36, 'fit');
    await text(enLCard, '·  campbuddy.club', 250, 528, { s: 16, c: '#64748b' });
    // Right Screen Mockup
    await rect(enLCard, 660, 50, 490, 527, { fill: '#fafafd', cornerRadius: 20, stroke: '#e2e8f0', strokeWeight: 2 });
    if (fs.existsSync(screenImg)) {
      await placeImage(enLCard, screenImg, 680, 70, 450, 487, 'fit');
    }

    // HI Landscape Card (x: 1360)
    const hiLCard = await frame(null, 1360, cardY, 1200, 627, `LinkCard ${r.id} [HI]`, {
      fill: '#ffffff', stroke: '#e2e8f0', strokeWeight: 2, cornerRadius: 28, effects: [dropShadow(20, 48, 0.06)]
    });
    await text(hiLCard, (hi.kicker || 'CAMPBUDDY').toUpperCase(), 60, 60, { f: 'head', s: 16, c: '#ea580c', ls: 2, upper: true });
    await text(hiLCard, hi.title.replace(/\*/g, ''), 60, 95, { f: 'head', s: 40, c: '#0f172a', width: 560, lh: 46 });
    await text(hiLCard, hi.sub, 60, 200, { s: 19, c: '#475569', width: 560, lh: 28 });
    await placeImage(hiLCard, path.join(SHARED_IMG, 'logo-wordmark.png'), 60, 520, 180, 36, 'fit');
    await text(hiLCard, '·  campbuddy.club', 250, 528, { s: 16, c: '#64748b' });
    await rect(hiLCard, 660, 50, 490, 527, { fill: '#fafafd', cornerRadius: 20, stroke: '#e2e8f0', strokeWeight: 2 });
    if (fs.existsSync(screenImg)) {
      await placeImage(hiLCard, screenImg, 680, 70, 450, 487, 'fit');
    }

    cardY += 720;
  }

  // =========================================================================
  // PAGE 4: 04 · Reel Covers & Safe Zones (1080×1920)
  // =========================================================================
  console.log('Building Page 4: Reel Covers & Safe Zones (1080×1920)...');
  await fc.cmd('page.select', { id: page4 });

  await text(null, 'CampBuddy / 10 Reel Covers & 9:16 Video Safe Zones', 80, 60, { f: 'head', s: 44, c: '#0f172a' });
  await text(null, 'Full 1080×1920 Reels with 4:5 Instagram Feed Safe Zone (1080×1350) and 1:1 Profile Safe Zone (1080×1080) Guides', 80, 120, { s: 18, c: '#64748b' });

  let reelX = 80;
  for (const r of reelsData.reels) {
    const rCover = await frame(null, reelX, 200, 1080, 1920, `Reel ${r.id} / ${r.slug} (1080×1920)`, {
      fill: '#fafafd', stroke: '#e2e8f0', strokeWeight: 2, cornerRadius: 40, effects: [dropShadow(24, 60, 0.08)]
    });

    // Top Event Tile
    await rect(rCover, 80, 160, 920, 124, { fill: '#ffffff', cornerRadius: 24, stroke: '#fed7aa', strokeWeight: 2 });
    const evLogo = path.join(SHARED_IMG, `event-${r.event.toLowerCase().replace(/wordcamp\s*/i, '').replace(/\s*\d+/g, '').trim()}.png`);
    if (fs.existsSync(evLogo)) {
      await placeImage(rCover, evLogo, 100, 176, 260, 92, 'fit');
    }
    await text(rCover, r.event.toUpperCase(), 380, 206, { f: 'head', s: 24, c: '#0f172a', ls: 1 });

    // Hook Headline
    await text(rCover, (r.cover.chip || 'CAMPBUDDY').toUpperCase(), 80, 340, { f: 'head', s: 22, c: '#c2410c', ls: 2 });
    await text(rCover, r.cover.title.replace(/\*/g, ''), 80, 390, { f: 'head', s: 62, c: '#0f172a', width: 920, lh: 72 });

    // Phone / Card Mockup Stage
    const mockupImg = path.join(SCREENS_DIR, r.cover.phone || r.cover.card || 'bengaluru/home.png');
    await rect(rCover, 140, 680, 800, 900, { fill: '#ffffff', cornerRadius: 36, stroke: '#e2e8f0', strokeWeight: 2 });
    if (fs.existsSync(mockupImg)) {
      await placeImage(rCover, mockupImg, 160, 700, 760, 860, 'fit');
    }

    // Master Brand Outro Plate
    await rect(rCover, 80, 1660, 920, 100, { fill: '#ffffff', cornerRadius: 28, stroke: '#fdba74', strokeWeight: 2 });
    await placeImage(rCover, path.join(SHARED_IMG, 'logo-wordmark.png'), 110, 1686, 240, 48, 'fit');
    await rect(rCover, 380, 1680, 2, 60, { fill: '#cbd5e1' });
    await text(rCover, 'Crafted by WPSimplified', 410, 1696, { s: 22, c: '#64748b' });
    await text(rCover, 'campbuddy.club', 750, 1696, { f: 'head', s: 22, c: '#ea580c' });

    // Overlay Guides: 4:5 Feed Safe Area (1080 × 1350, y: 285)
    await rect(rCover, 0, 285, 1080, 1350, { stroke: '#3b82f6', strokeWeight: 2 }, 'Guide / IG Feed Safe Area (1080×1350)');
    await text(rCover, '4:5 IG FEED SAFE AREA (1080 × 1350)', 20, 260, { s: 14, c: '#3b82f6', upper: true });

    // Overlay Guides: 1:1 Profile Safe Area (1080 × 1080, y: 420)
    await rect(rCover, 0, 420, 1080, 1080, { stroke: '#10b981', strokeWeight: 2 }, 'Guide / IG Profile Safe Area (1080×1080)');
    await text(rCover, '1:1 IG PROFILE GRID SAFE AREA (1080 × 1080)', 20, 395, { s: 14, c: '#10b981', upper: true });

    reelX += 1200;
  }

  // Select Page 1 as default active
  await fc.cmd('page.select', { id: page1 });

  // Save the master file
  console.log(`Saving FigCraft file to ${OUT_FILE}...`);
  const saved = await fc.tool('save_file', { path: OUT_FILE });
  console.log(`Saved master file: ${saved.path} (${Math.round(saved.bytes / 1024)} KB)`);

  // Copy to final-assets as well
  const finalDest = path.join(FINAL_ASSETS, 'CampBuddy-Social-Media-Kit.figcraft');
  fs.copyFileSync(OUT_FILE, finalDest);
  console.log(`Copied to final-assets: ${finalDest}`);

} catch (err) {
  console.error('Error generating FigCraft kit:', err);
  process.exitCode = 1;
} finally {
  fc.close();
}
