// Build Master FigCraft File for CampBuddy | WPSimplified Co-Branded Logo
import fs from 'node:fs';
import path from 'node:path';
import { connect } from 'file:///E:/Sites/InHouse/wpsimplified/Studio/Design/scripts/fc.mjs';

const OUT_DIR_MEDIA = 'E:/Sites/InHouse/campbuddy/V2/public/media';
const OUT_DIR_MKT = 'E:/Sites/InHouse/campbuddy/marketing/final-assets';

const OUT_FILE_MEDIA = path.join(OUT_DIR_MEDIA, 'campbuddy-wpsimplified-logo.figcraft');
const OUT_FILE_MKT = path.join(OUT_DIR_MKT, 'CampBuddy-WPSimplified-CoBranded-Logo.figcraft');

const CB_CLEAN = 'E:/Sites/InHouse/campbuddy/V2/public/media/campbuddy-mark-clean.png';
const WP_CLEAN = 'E:/Sites/InHouse/campbuddy/V2/public/media/wpsimplified-mark-clean.png';
const WP_WHITE_CLEAN = 'E:/Sites/InHouse/campbuddy/V2/public/media/wpsimplified-mark-white-clean.png';
const COMBO_WEB = 'E:/Sites/InHouse/campbuddy/V2/public/media/campbuddy-wpsimplified-logo-web.png';

console.log('Connecting to FigCraft MCP...');
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
  const res = await fc.cmd('image.place', {
    path: filePath.replaceAll('\\', '/'),
    x, y, width: w, height: h, parent, mode
  });
  return res.id;
}

function dropShadow(y = 12, blur = 32, opacity = 0.08) {
  const alphaHex = Math.round(opacity * 255).toString(16).padStart(2, '0');
  return { type: 'dropShadow', color: `#0f172a${alphaHex}`, offset: [0, y], radius: blur };
}

try {
  console.log('Loading fonts...');
  await fc.cmd('text.loadFont', { path: 'E:/Sites/InHouse/wpsimplified/Studio/Design/assets/fonts/Baloo2-Variable.ttf' }).catch(() => {});

  const pages = await fc.cmd('page.list');
  const page1 = pages[0].id;
  await fc.cmd('page.rename', { id: page1, name: '01 · Master Co-Branded Lockup' });
  await fc.cmd('page.setBackground', { id: page1, color: '#f8fafc' });

  const page2 = (await fc.cmd('page.add', { name: '02 · Brand Marks & Geometry Specs' })).id;
  await fc.cmd('page.setBackground', { id: page2, color: '#f8fafc' });

  // =========================================================================
  // PAGE 1: 01 · Master Co-Branded Lockup
  // =========================================================================
  console.log('Building Page 1: Master Co-Branded Lockup...');
  await fc.cmd('page.select', { id: page1 });

  // Header
  await text(null, 'CampBuddy | WPSimplified — Co-Branded Logo Master Kit', 80, 60, { f: 'head', s: 40, c: '#0f172a' });
  await text(null, 'Print-Ready 900 DPI • Equal Heights • Vertically & Horizontally Center-Aligned • Zero Outer Margins', 80, 115, { s: 16, c: '#64748b' });

  // Badge: Ready to Export
  const headerBadge = await frame(null, 1380, 65, 220, 38, 'Badge / Status', {
    fill: '#ecfdf5', stroke: '#a7f3d0', strokeWeight: 1, cornerRadius: 19
  });
  await text(headerBadge, '✓ 900 DPI PRINT READY', 26, 11, { s: 12, c: '#047857', w: 'Bold' });

  // --- 1. PRIMARY LIGHT LOCKUP (2200 × 520) ---
  const frameLight = await frame(null, 80, 180, 2200, 520, 'Master Lockup / Light Background', {
    fill: '#ffffff', stroke: '#e2e8f0', strokeWeight: 2, cornerRadius: 20, effects: [dropShadow(16, 40, 0.06)]
  });

  // Top Label inside frame
  await rect(frameLight, 32, 28, 260, 32, { fill: '#f1f5f9', cornerRadius: 16 }, 'Pill / Label');
  await text(frameLight, 'PRIMARY LOCKUP · LIGHT', 50, 36, { s: 11, c: '#475569', w: 'Bold', upper: true });

  // Inside frameLight: place logos and pipe
  // Target logo height inside this card: 220 px
  // CB aspect ratio: 6281 / 1799 = 3.49138 -> cb_w = 220 * 3.49138 = 768 px
  // WP aspect ratio: 3604 / 1224 = 2.94444 -> wp_w = 220 * 2.94444 = 648 px
  // Gap = 64 px
  // Pipe width = 6 px, height = 220 * 0.70 = 154 px
  // Total lockup width = 768 + 64 + 6 + 64 + 648 = 1550 px
  // Start X = (2200 - 1550) / 2 = 325 px
  // Start Y = (520 - 220) / 2 + 10 = 160 px
  const lockupH = 220;
  const cbW = Math.round(lockupH * (6281 / 1799)); // 768
  const wpW = Math.round(lockupH * (3604 / 1224)); // 648
  const pipeW = 6;
  const pipeH = Math.round(lockupH * 0.70); // 154
  const gap = 64;
  const totalLockupW = cbW + gap + pipeW + gap + wpW; // 1550
  const startX = Math.round((2200 - totalLockupW) / 2); // 325
  const startY = 170;

  // Place CampBuddy Logo
  await placeImage(frameLight, CB_CLEAN, startX, startY, cbW, lockupH, 'fit');

  // Draw Pipe Divider '|' (#94A3B8, rounded, centered vertically)
  const pipeX = startX + cbW + gap;
  const pipeY = startY + Math.round((lockupH - pipeH) / 2);
  await rect(frameLight, pipeX, pipeY, pipeW, pipeH, { fill: '#94a3b8', cornerRadius: Math.round(pipeW / 2) }, 'Divider / Pipe (|)');

  // Place WPSimplified Logo (Dark)
  const wpX = pipeX + pipeW + gap;
  await placeImage(frameLight, WP_CLEAN, wpX, startY, wpW, lockupH, 'fit');

  // Spec footer note inside frame
  await text(frameLight, 'CampBuddy Height: 220px  |  Pipe Divider: #94A3B8 (70% H)  |  WPSimplified Height: 220px  |  Center-Aligned', 80, 465, { s: 12, c: '#94a3b8' });

  // --- 2. DARK LOCKUP (2200 × 520) ---
  const frameDark = await frame(null, 80, 740, 2200, 520, 'Master Lockup / Dark Background', {
    fill: '#0f172a', stroke: '#1e293b', strokeWeight: 2, cornerRadius: 20, effects: [dropShadow(20, 48, 0.25)]
  });

  // Top Label inside dark frame
  await rect(frameDark, 32, 28, 270, 32, { fill: '#1e293b', cornerRadius: 16 }, 'Pill / Label Dark');
  await text(frameDark, 'INVERTED LOCKUP · DARK BG', 48, 36, { s: 11, c: '#94a3b8', w: 'Bold', upper: true });

  // Place CampBuddy Logo in Dark Frame
  await placeImage(frameDark, CB_CLEAN, startX, startY, cbW, lockupH, 'fit');

  // Draw Pipe Divider '|' (#64748B subtle slate for dark mode)
  await rect(frameDark, pipeX, pipeY, pipeW, pipeH, { fill: '#64748b', cornerRadius: Math.round(pipeW / 2) }, 'Divider / Pipe Dark (|)');

  // Place WPSimplified White Logo
  await placeImage(frameDark, WP_WHITE_CLEAN, wpX, startY, wpW, lockupH, 'fit');

  await text(frameDark, 'Optimized for Dark Backgrounds, WordCamp Projector Displays, Keynote Presentations & Video Overlays', 80, 465, { s: 12, c: '#64748b' });

  // --- 3. TWO SIDE-BY-SIDE PRESETS (Web & Proposal Card) ---
  // Web Header Card (1070 × 340)
  const frameWeb = await frame(null, 80, 1300, 1070, 340, 'Web Header Preset (1070×340)', {
    fill: '#ffffff', stroke: '#e2e8f0', strokeWeight: 2, cornerRadius: 18, effects: [dropShadow(12, 30, 0.05)]
  });
  await text(frameWeb, 'PRESET · WEB & LISTING HEADER', 32, 26, { s: 11, c: '#64748b', w: 'Bold', upper: true });

  const webScale = 0.55;
  const webH = Math.round(lockupH * webScale); // 121
  const webCbW = Math.round(cbW * webScale);
  const webWpW = Math.round(wpW * webScale);
  const webPipeW = 4;
  const webPipeH = Math.round(pipeH * webScale);
  const webGap = Math.round(gap * webScale);
  const webTotalW = webCbW + webGap + webPipeW + webGap + webWpW;
  const webStartX = Math.round((1070 - webTotalW) / 2);
  const webStartY = 110;

  await placeImage(frameWeb, CB_CLEAN, webStartX, webStartY, webCbW, webH, 'fit');
  await rect(frameWeb, webStartX + webCbW + webGap, webStartY + Math.round((webH - webPipeH) / 2), webPipeW, webPipeH, { fill: '#94a3b8', cornerRadius: 2 }, 'Pipe');
  await placeImage(frameWeb, WP_CLEAN, webStartX + webCbW + webGap + webPipeW + webGap, webStartY, webWpW, webH, 'fit');

  await text(frameWeb, 'Exact 1:1 Aspect ratio matching public/media/campbuddy-wpsimplified-logo.svg', 32, 290, { s: 11, c: '#94a3b8' });

  // Print Proposal Lockup (1070 × 340)
  const framePrint = await frame(null, 1210, 1300, 1070, 340, 'Print Proposal Card (1070×340)', {
    fill: '#f8fafc', stroke: '#047857', strokeWeight: 2, cornerRadius: 18, effects: [dropShadow(12, 30, 0.05)]
  });
  await text(framePrint, 'PRESET · OFFICIAL PROPOSAL EMBED', 32, 26, { s: 11, c: '#047857', w: 'Bold', upper: true });

  await placeImage(framePrint, CB_CLEAN, webStartX, webStartY, webCbW, webH, 'fit');
  await rect(framePrint, webStartX + webCbW + webGap, webStartY + Math.round((webH - webPipeH) / 2), webPipeW, webPipeH, { fill: '#94a3b8', cornerRadius: 2 }, 'Pipe');
  await placeImage(framePrint, WP_CLEAN, webStartX + webCbW + webGap + webPipeW + webGap, webStartY, webWpW, webH, 'fit');

  await text(framePrint, 'Embedded directly into /admin/media-pitch/print proposal executive document', 32, 290, { s: 11, c: '#64748b' });


  // =========================================================================
  // PAGE 2: 02 · Brand Marks & Geometry Specs
  // =========================================================================
  console.log('Building Page 2: Brand Marks & Geometry Specs...');
  await fc.cmd('page.select', { id: page2 });

  await text(null, 'Brand Marks, Color Tokens & Divider Specifications', 80, 60, { f: 'head', s: 40, c: '#0f172a' });
  await text(null, 'Component-level assets, color swatches, typography notes, and mathematical spacing rules', 80, 115, { s: 16, c: '#64748b' });

  // --- CampBuddy Standalone Mark (1070 × 500) ---
  const cbCard = await frame(null, 80, 180, 1070, 500, 'Component / CampBuddy Master', {
    fill: '#ffffff', stroke: '#e2e8f0', strokeWeight: 2, cornerRadius: 20, effects: [dropShadow(14, 36, 0.06)]
  });
  await text(cbCard, 'CAMPBUDDY · ATTENDEE COMPANION', 32, 30, { s: 12, c: '#ea580c', w: 'Bold', upper: true });
  await placeImage(cbCard, CB_CLEAN, 80, 90, 700, 200, 'fit');

  // Swatches for CampBuddy
  await rect(cbCard, 80, 330, 48, 48, { fill: '#ea580c', cornerRadius: 8 }, 'Swatch / Orange');
  await text(cbCard, 'Brand Orange\n#EA580C', 140, 336, { s: 11, c: '#334155', lh: 16 });

  await rect(cbCard, 280, 330, 48, 48, { fill: '#451a03', cornerRadius: 8 }, 'Swatch / Deep Brown');
  await text(cbCard, 'Deep Brown\n#451A03', 340, 336, { s: 11, c: '#334155', lh: 16 });

  await rect(cbCard, 480, 330, 48, 48, { fill: '#f59e0b', cornerRadius: 8 }, 'Swatch / Amber');
  await text(cbCard, 'Warm Amber\n#F59E0B', 540, 336, { s: 11, c: '#334155', lh: 16 });

  await text(cbCard, 'Official URL: campbuddy.club  •  Aspect Ratio: 3.491 : 1  •  Source: 6281 × 1799 px', 80, 440, { s: 12, c: '#64748b' });

  // --- WPSimplified Standalone Mark (1070 × 500) ---
  const wpCard = await frame(null, 1210, 180, 1070, 500, 'Component / WPSimplified Master', {
    fill: '#ffffff', stroke: '#e2e8f0', strokeWeight: 2, cornerRadius: 20, effects: [dropShadow(14, 36, 0.06)]
  });
  await text(wpCard, 'WPSIMPLIFIED · DEVELOPER EDUCATION', 32, 30, { s: 12, c: '#0d9488', w: 'Bold', upper: true });
  await placeImage(wpCard, WP_CLEAN, 120, 90, 590, 200, 'fit');

  // Swatches for WPSimplified
  await rect(wpCard, 80, 330, 48, 48, { fill: '#0d9488', cornerRadius: 8 }, 'Swatch / Teal');
  await text(wpCard, 'Tech Teal\n#0D9488', 140, 336, { s: 11, c: '#334155', lh: 16 });

  await rect(wpCard, 280, 330, 48, 48, { fill: '#06b6d4', cornerRadius: 8 }, 'Swatch / Cyan');
  await text(wpCard, 'Accent Cyan\n#06B6D4', 340, 336, { s: 11, c: '#334155', lh: 16 });

  await rect(wpCard, 480, 330, 48, 48, { fill: '#0f172a', cornerRadius: 8 }, 'Swatch / Slate');
  await text(wpCard, 'Ink Black\n#0F172A', 540, 336, { s: 11, c: '#334155', lh: 16 });

  await text(wpCard, 'Official URL: wpsimplified.in  •  Aspect Ratio: 2.944 : 1  •  Source: 3604 × 1224 px', 80, 440, { s: 12, c: '#64748b' });

  // --- Mathematical Geometry & Rules Card (2200 × 440) ---
  const specCard = await frame(null, 80, 720, 2200, 440, 'Specifications & Usage Guidelines', {
    fill: '#ffffff', stroke: '#e2e8f0', strokeWeight: 2, cornerRadius: 20, effects: [dropShadow(14, 36, 0.06)]
  });
  await text(specCard, 'MANDATORY CO-BRANDING GUIDELINES & SPECIFICATIONS', 32, 30, { s: 12, c: '#0f172a', w: 'Bold', upper: true });

  const bulletY = 90;
  const col1X = 60;
  const col2X = 1140;

  // Column 1
  await text(specCard, '1. Divider Rule (Pipe Sign Only):', col1X, bulletY, { s: 15, c: '#0f172a', w: 'Bold' });
  await text(specCard, '• Never use an "X" or cross mark between CampBuddy and WPSimplified.\n• Always use a vertical pipe sign ("|") with rounded caps.\n• Divider tone is soft neutral slate #94A3B8 (light mode) or #64748B (dark mode).\n• Divider height is fixed to 70% of the active logo height.', col1X, bulletY + 30, { s: 13, c: '#475569', lh: 22 });

  await text(specCard, '2. Vertical & Horizontal Centering:', col1X, bulletY + 160, { s: 15, c: '#0f172a', w: 'Bold' });
  await text(specCard, '• Both logos MUST share the exact same height regardless of scale.\n• Width is auto-calculated based on native aspect ratios (CB: 3.491, WP: 2.944).\n• Center Y of CampBuddy, Pipe Divider, and WPSimplified MUST be strictly identical.\n• Distance from CampBuddy to Pipe equals distance from Pipe to WPSimplified.', col1X, bulletY + 190, { s: 13, c: '#475569', lh: 22 });

  // Column 2
  await text(specCard, '3. Zero Whitespace / Tight Bounding Box:', col2X, bulletY, { s: 15, c: '#0f172a', w: 'Bold' });
  await text(specCard, '• Master exports contain zero surrounding transparent or white padding.\n• Outer bounds strictly touch the visual boundaries of the logo marks.\n• Ensures consistent alignment in WordCamp sponsor grids and print brochures.', col2X, bulletY + 30, { s: 13, c: '#475569', lh: 22 });

  await text(specCard, '4. Print & Export Standards:', col2X, bulletY + 160, { s: 15, c: '#0f172a', w: 'Bold' });
  await text(specCard, '• Print Standard: 900 DPI PNG at 12,200 × 1,800 px (campbuddy-wpsimplified-logo-900dpi.png).\n• Web Standard: Vector SVG with embedded high-res artwork (campbuddy-wpsimplified-logo.svg).\n• Design Studio: Editable native FigCraft file (.figcraft) with layer hierarchy.', col2X, bulletY + 190, { s: 13, c: '#475569', lh: 22 });

  // Set Page 1 as default active
  await fc.cmd('page.select', { id: page1 });

  // Save the master FigCraft file to both locations
  console.log(`Saving FigCraft file to ${OUT_FILE_MEDIA}...`);
  const savedMedia = await fc.tool('save_file', { path: OUT_FILE_MEDIA });
  console.log(`Saved media file: ${savedMedia.path} (${Math.round(savedMedia.bytes / 1024)} KB)`);

  console.log(`Saving FigCraft file to ${OUT_FILE_MKT}...`);
  fs.copyFileSync(OUT_FILE_MEDIA, OUT_FILE_MKT);
  console.log(`Copied to marketing assets: ${OUT_FILE_MKT}`);

  // Take a preview screenshot of Page 1 Master Light Frame
  console.log('Taking preview screenshot of Master Lockup...');
  const shotPath = path.join(OUT_DIR_MEDIA, 'figcraft-preview.png');
  await fc.shot(shotPath, { id: frameLight });
  console.log(`Screenshot saved: ${shotPath}`);

} catch (err) {
  console.error('Error generating FigCraft kit:', err);
  process.exitCode = 1;
} finally {
  fc.close();
}
