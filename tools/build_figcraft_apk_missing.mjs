import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const rootDir = path.resolve(__dirname, '..');

const assets = {
  cbWordmark: path.resolve(rootDir, 'docs/designs/assets/campbuddy_wordmark.png'),
  cbIcon: path.resolve(rootDir, 'docs/designs/assets/campbuddy_icon.png'),
  delhiFavicon: path.resolve(rootDir, 'docs/designs/assets/delhi_favicon.png'),
  pankaj: path.resolve(rootDir, 'docs/designs/assets/pankaj_kumar.webp'),
  radhika: path.resolve(rootDir, 'docs/designs/assets/radhika_bhama.jpg'),
  amit: path.resolve(rootDir, 'docs/designs/assets/amit_singh.webp'),
  shammi: path.resolve(rootDir, 'docs/designs/assets/shammi_proothi.webp'),
  abhay: path.resolve(rootDir, 'docs/designs/assets/abhay_kulkarni.webp'),
  shreya: path.resolve(rootDir, 'docs/designs/assets/shreya_agarwal.webp'),
  sponsor_wpfusion: path.resolve(rootDir, 'docs/designs/assets/sponsor_wpfusion.webp'),
  sponsor_elementor: path.resolve(rootDir, 'docs/designs/assets/sponsor_elementor.webp'),
};

const ICONS = {
  home: 'M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2zM9 22V12h6v10',
  calendar: 'M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2zM16 2v4M8 2v4M3 10h18',
  clock: 'M2 12a10 10 0 1 0 20 0 10 10 0 1 0 -20 0zM12 6v6l4 2',
  card: 'M5 4h14a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-12a2 2 0 0 1 2 -2zM7 10a2 2 0 1 0 4 0 2 2 0 1 0 -4 0zM15 8h2M15 12h2M7 16h6',
  compass: 'M2 12a10 10 0 1 0 20 0 10 10 0 1 0 -20 0zM16.24 7.76l-2.12 6.36-6.36 2.12 2.12-6.36z',
  users: 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M5 7a4 4 0 1 0 8 0 4 4 0 1 0 -8 0zM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75',
  gift: 'M4 8h16a1 1 0 0 1 1 1v2a1 1 0 0 1 -1 1h-16a1 1 0 0 1 -1 -1v-2a1 1 0 0 1 1 -1zM12 8v13M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7M7.5 8a2.5 2.5 0 0 1 0-5A4.8 4.8 0 0 1 12 8a4.8 4.8 0 0 1 4.5-5 2.5 2.5 0 0 1 0 5',
  star: 'M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z',
  mapPin: 'M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11zM9.5 10a2.5 2.5 0 1 0 5 0 2.5 2.5 0 1 0 -5 0z',
  search: 'M4 11a7 7 0 1 0 14 0 7 7 0 1 0 -14 0zM21 21l-5-5',
  bell: 'M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10.3 21a1.94 1.94 0 0 0 3.4 0',
  shield: 'M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1zM9 12l 2 2 4-4',
  heart: 'M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7z',
  sparkles: 'M12 3l1.9 5.8L20 11l-6.1 2.2L12 19l-1.9-5.8L4 11l6.1-2.2zM19 3v4M17 5h4',
  plus: 'M12 5v14M5 12h14',
  check: 'M20 6 9 17l-5-5',
  award: 'M6 8a6 6 0 1 0 12 0 6 6 0 1 0 -12 0zM15.48 12.89l 2.52 9.11-6-4-6 4 2.52-9.11',
  megaphone: 'M3 11l 18-5v12L3 14v-3zM11.6 16.8a3 3 0 1 1-5.8-1.6',
  camera: 'M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3zM9 13a3 3 0 1 0 6 0 3 3 0 1 0 -6 0z',
  lock: 'M6 11h12a2 2 0 0 1 2 2v6a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-6a2 2 0 0 1 2 -2zM8 11V7a4 4 0 0 1 8 0v4',
  chevronLeft: 'M15 18l-6-6 6-6',
  chevronRight: 'M9 18l 6-6-6-6',
  alert: 'M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1zM12 8v4M12 16h.01',
  activity: 'M22 12h-4l-3 9L9 3l-3 9H2',
  wrench: 'M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z',
  pencil: 'M17 3l 4 4L7.5 20.5 2 22l1.5-5.5z',
  columns: 'M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2zM9 3v18M15 3v18',
  trash: 'M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 11v6M14 11v6',
  refresh: 'M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8M21 3v5h-5M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16M8 16H3v5',
  x: 'M18 6 6 18M6 6l12 12',
  filter: 'M4 6h16M7 12h10M10 18h4',
};

const ICON_DIMS = {
  chevronLeft: [6, 12],
  chevronRight: [6, 12],
  check: [16, 11],
  award: [12, 20],
  mapPin: [14, 18],
  alert: [16, 19],
  shield: [16, 19],
  heart: [20, 18],
  activity: [20, 18],
  filter: [16, 12],
  megaphone: [18, 12],
  lock: [16, 18],
};

class MissingApkBuilder {
  constructor() {
    this.commands = [];
    this.nextId = 2;
    this.commands.push({ command: 'page.rename', params: { name: 'Mobile APK - 18 Missing Sub-Screens (M33-M50)' } });
    this.idx = 0;
  }

  coords() {
    const col = this.idx % 6;
    const row = Math.floor(this.idx / 6);
    this.idx++;
    return { x: 60 + col * 430, y: 120 + row * 880 };
  }

  screenFrame(title, statesNote, fill = '#FFF7ED') {
    const pos = this.coords();
    this.text(title, pos.x, pos.y - 54, 360, { fontSize: 15, fontWeight: 800, fill: '#0F172A' }, null, `Lbl ${title}`);
    this.text(statesNote, pos.x, pos.y - 32, 360, { fontSize: 10, fontWeight: 600, fill: '#64748B' }, null, `Sub ${title}`);
    const id = this.nextId++;
    this.commands.push({
      command: 'frame.create',
      params: { name: title, x: pos.x, y: pos.y, width: 360, height: 760, props: { fill, cornerRadius: 36, clip: true } }
    });
    return id;
  }

  subFrame(name, x, y, width, height, props = {}, parent = null) {
    const id = this.nextId++;
    const params = { name, x, y, width, height, props };
    if (parent) params.parent = parent;
    this.commands.push({ command: 'frame.create', params });
    return id;
  }

  rect(name, x, y, width, height, props = {}, parent = null) {
    const id = this.nextId++;
    const params = { name, x, y, width, height, props };
    if (parent) params.parent = parent;
    this.commands.push({ command: 'shape.rectangle', params });
    return id;
  }

  ellipse(name, x, y, width, height, props = {}, parent = null) {
    const id = this.nextId++;
    const params = { name, x, y, width, height, props };
    if (parent) params.parent = parent;
    this.commands.push({ command: 'shape.ellipse', params });
    return id;
  }

  line(name, x, y, width, props = {}, parent = null) {
    const id = this.nextId++;
    const params = { name, x, y, width, props };
    if (parent) params.parent = parent;
    this.commands.push({ command: 'shape.line', params });
    return id;
  }

  text(text, x, y, width, props = {}, parent = null, name = null) {
    const id = this.nextId++;
    const params = { text: String(text), x, y, width, props };
    if (parent) params.parent = parent;
    if (name) params.name = name;
    this.commands.push({ command: 'text.create', params });
    return id;
  }

  vector(pathKey, x, y, size, color, parent, name = 'Icon') {
    const svgPath = ICONS[pathKey] || ICONS.star;
    const [natW, natH] = ICON_DIMS[pathKey] || [20, 20];
    const scale = size / Math.max(natW, natH);
    const rw = Number((natW * scale).toFixed(2));
    const rh = Number((natH * scale).toFixed(2));
    const cx = Number((x + (size - rw) / 2).toFixed(2));
    const cy = Number((y + (size - rh) / 2).toFixed(2));

    const id = this.nextId++;
    this.commands.push({
      command: 'vector.create',
      params: {
        path: svgPath, x: 0, y: 0, width: rw, height: rh,
        props: { fill: null, stroke: color, strokeWeight: 2, strokeCap: 'round', strokeJoin: 'round' },
        name, parent
      }
    });
    this.commands.push({ command: 'node.set', params: { id, x: cx, y: cy } });
    return id;
  }

  image(filePath, x, y, width, height, parent, name = 'Image', mode = 'fit') {
    if (!filePath || !fs.existsSync(filePath)) {
      return this.rect(name, x, y, width, height, { fill: '#E2E8F0', cornerRadius: 8 }, parent);
    }
    const id = this.nextId++;
    this.commands.push({
      command: 'image.place',
      params: { path: filePath, x, y, width, height, parent, name, mode }
    });
    return id;
  }

  avatar(filePath, x, y, size, parent, borderColor = '#FFFFFF', onlineDot = false) {
    const wrap = this.subFrame('AvatarClip', x, y, size, size, {
      fill: '#FFEDD5', cornerRadius: size / 2, clip: true, stroke: borderColor, strokeWeight: 2
    }, parent);
    this.image(filePath, 0, 0, size, size, wrap, 'Photo', 'fill');
    if (onlineDot) {
      this.ellipse('Online', x + size - 10, y + size - 10, 10, 10, { fill: '#10B981', stroke: '#FFFFFF', strokeWeight: 2 }, parent);
    }
    return wrap;
  }

  logoTile(filePath, x, y, width, height, parent, radius = 12, padding = 6, fill = '#FFFFFF', stroke = null) {
    const props = { fill, cornerRadius: radius, clip: true };
    if (stroke) { props.stroke = stroke; props.strokeWeight = 1; }
    const box = this.subFrame('LogoTile', x, y, width, height, props, parent);
    this.image(filePath, padding, padding, width - padding * 2, height - padding * 2, box, 'Logo', 'fit');
    return box;
  }

  statusBar(parent, dark = false, centerText = 'Offline Ready') {
    const color = dark ? '#F8FAFC' : '#0F172A';
    this.text('10:30 AM', 20, 14, 70, { fontSize: 11, fontWeight: 700, fill: color }, parent);
    if (centerText) {
      this.ellipse('Dot', 224, 18, 6, 6, { fill: '#10B981' }, parent);
      this.text(centerText, 234, 14, 86, { fontSize: 10, fontWeight: 700, fill: '#10B981' }, parent);
    }
    this.rect('Battery', 324, 15, 18, 9, { fill: color, cornerRadius: 2.5 }, parent);
  }

  subHeader(parent, title, dark = false) {
    this.ellipse('BackBtn', 16, 41, 30, 30, { fill: dark ? '#1E293B' : '#FFEDD5' }, parent);
    this.vector('chevronLeft', 22, 47, 18, dark ? '#FFFFFF' : '#EA580C', parent, 'BackIco');
    this.text(title, 56, 45, 240, { fontSize: 17, fontWeight: 800, fill: dark ? '#FFFFFF' : '#0F172A' }, parent);
    this.image(assets.cbIcon, 314, 41, 30, 30, parent, 'CBMark', 'fit');
  }

  staffHeader(parent, title, backLabel = 'Overview', rightText = null) {
    this.text(`< ${backLabel}`, 18, 46, 94, { fontSize: 11.5, fontWeight: 700, fill: '#94A3B8' }, parent);
    this.text(title, 102, 45, 168, { fontSize: 15, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, parent);
    if (rightText) {
      this.rect('RBtn', 274, 43, 70, 24, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 12 }, parent);
      this.text(rightText, 276, 48, 66, { fontSize: 9.5, fontWeight: 700, fill: '#F59E0B', textAlign: 'center' }, parent);
    } else {
      this.image(assets.cbIcon, 316, 41, 28, 28, parent, 'CBMark', 'fit');
    }
  }

  adminBottomNav(parent, activeTab = 'more') {
    const y = 700;
    this.rect('AdmNavBG', 0, y, 360, 60, { fill: '#0F172A' }, parent);
    this.line('AdmDiv', 0, y, 360, { stroke: '#1E293B', strokeWeight: 1 }, parent);
    const tabs = [
      { key: 'dashboard', label: 'Dashboard', icon: 'home', x: 16 },
      { key: 'events', label: 'Events', icon: 'calendar', x: 102 },
      { key: 'managers', label: 'Managers', icon: 'users', x: 188 },
      { key: 'more', label: 'More', icon: 'columns', x: 274 },
    ];
    for (const t of tabs) {
      const on = t.key === activeTab;
      if (on) {
        this.rect('AdmActive', t.x + 12, y + 7, 50, 24, { fill: '#1E293B', cornerRadius: 12 }, parent);
      }
      this.vector(t.icon, t.x + 29, y + 11, 16, on ? '#F59E0B' : '#94A3B8', parent, `ANavIco_${t.key}`);
      this.text(t.label, t.x, y + 36, 74, { fontSize: 9.5, fontWeight: on ? 800 : 500, fill: on ? '#F59E0B' : '#94A3B8', textAlign: 'center' }, parent);
    }
  }

  pill(parent, label, x, y, w, active = false, color = '#0F172A', textColor = null, darkSurface = false) {
    const inFill = darkSurface ? '#1E293B' : '#FFFFFF';
    const inStroke = darkSurface ? '#334155' : '#E2E8F0';
    const inText = darkSurface ? '#CBD5E1' : '#475569';
    this.rect(`Pill_${label}`, x, y, w, 28, {
      fill: active ? color : inFill, stroke: active ? color : inStroke, strokeWeight: 1, cornerRadius: 14
    }, parent);
    this.text(label, x + 4, y + 8, w - 8, {
      fontSize: 9.5, fontWeight: active ? 700 : 600, fill: active ? (textColor || '#FFFFFF') : inText, textAlign: 'center'
    }, parent);
  }

  toggle(parent, x, y, on = true) {
    this.rect('SwTrack', x, y, 42, 22, { fill: on ? '#10B981' : '#CBD5E1', cornerRadius: 11 }, parent);
    this.ellipse('SwKnob', on ? x + 23 : x + 3, y + 3, 16, 16, { fill: '#FFFFFF' }, parent);
  }

  buildMissing18Screens() {
    // =========================================================================
    // GROUP 6: ATTENDEE ONBOARDING & MODALS (M33 - M38)
    // =========================================================================

    // M33: Onboarding Intro & Step 1 (First WordCamp?)
    {
      const f = this.screenFrame('M33 · Onboarding Intro & First-Timer', 'Optional Intro Card · Step 1 First WordCamp Binary Choice', '#FFF7ED');
      this.statusBar(f, false);
      this.subHeader(f, 'Personalize');

      // Intro Prompt Card
      this.rect('IntroCard', 16, 86, 328, 178, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 18 }, f);
      this.rect('IntroTopBar', 16, 86, 328, 4, { fill: '#FF4D2D', cornerRadius: 2 }, f);
      this.rect('OptBadge', 32, 102, 134, 22, { fill: '#FFEDD5', cornerRadius: 11 }, f);
      this.text('Optional · 30 seconds', 36, 107, 126, { fontSize: 9.5, fontWeight: 700, fill: '#EA580C', textAlign: 'center' }, f);
      this.text('Make CampBuddy yours', 32, 134, 296, { fontSize: 16, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Four quick answers flag beginner talks, Contributor Day teams and people you may want to meet. Stays on this phone.', 32, 158, 296, { fontSize: 10.5, fontWeight: 400, fill: '#64748B' }, f);
      this.rect('NotNow', 32, 206, 140, 40, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 20 }, f);
      this.text('Not now', 36, 219, 132, { fontSize: 11, fontWeight: 700, fill: '#0F172A', textAlign: 'center' }, f);
      this.rect('StartBtn', 184, 206, 144, 40, { fill: '#FF4D2D', cornerRadius: 20 }, f);
      this.text('Start →', 188, 219, 136, { fontSize: 11, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);

      // Step 1 Card
      this.rect('Step1Card', 16, 282, 328, 434, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 18 }, f);
      this.rect('Bar1', 32, 300, 40, 5, { fill: '#FF4D2D', cornerRadius: 2.5 }, f);
      for (let i = 1; i < 5; i++) this.rect(`BarN${i}`, 32 + i * 45, 300, 40, 5, { fill: '#E2E8F0', cornerRadius: 2.5 }, f);
      this.text('Step 1 of 5', 260, 294, 68, { fontSize: 10, fontWeight: 700, fill: '#64748B', textAlign: 'right' }, f);

      this.ellipse('S1IcoBg', 32, 324, 44, 44, { fill: '#FFEDD5' }, f);
      this.vector('sparkles', 44, 336, 20, '#EA580C', f);
      this.text('Is this your first WordCamp?', 32, 380, 296, { fontSize: 17, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('First-timers get beginner-friendly talks flagged and a short start-here guide.', 32, 408, 296, { fontSize: 10.5, fontWeight: 400, fill: '#64748B' }, f);

      // Binary option cards
      this.rect('YesCard', 32, 460, 140, 120, { fill: '#FFF7ED', stroke: '#FF4D2D', strokeWeight: 1.5, cornerRadius: 16 }, f);
      this.vector('star', 90, 486, 24, '#FF4D2D', f);
      this.text('Yes, first one!', 40, 524, 124, { fontSize: 12, fontWeight: 800, fill: '#0F172A', textAlign: 'center' }, f);

      this.rect('NoCard', 188, 460, 140, 120, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.vector('compass', 246, 486, 24, '#64748B', f);
      this.text("No, I've been before", 196, 520, 124, { fontSize: 11.5, fontWeight: 700, fill: '#0F172A', textAlign: 'center' }, f);

      this.text('Skip', 36, 650, 60, { fontSize: 11.5, fontWeight: 600, fill: '#64748B' }, f);
      this.rect('Next1', 208, 638, 120, 42, { fill: '#0F172A', cornerRadius: 21 }, f);
      this.text('Continue →', 212, 652, 112, { fontSize: 11.5, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);
    }

    // M34: Onboarding Steps 3-5 & Finish Summary
    {
      const f = this.screenFrame('M34 · Onboarding Contributor & Finish', 'Steps 3-5 Who to Meet & WordPress.org · Set Completion Card', '#FFF7ED');
      this.statusBar(f, false);
      this.subHeader(f, 'Personalize');

      // Step 4-5 Inputs Card
      this.rect('S4Card', 16, 86, 328, 304, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 18 }, f);
      for (let i = 0; i < 4; i++) this.rect(`P4_${i}`, 32 + i * 45, 102, 40, 5, { fill: '#FF4D2D', cornerRadius: 2.5 }, f);
      this.rect('P4_4', 212, 102, 40, 5, { fill: '#E2E8F0', cornerRadius: 2.5 }, f);
      this.text('Step 4 of 5', 260, 96, 68, { fontSize: 10, fontWeight: 700, fill: '#64748B', textAlign: 'right' }, f);

      this.text('Who would you like to meet?', 32, 124, 296, { fontSize: 16, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Pre-fills your CampCard & discovery suggestions privately.', 32, 148, 296, { fontSize: 10, fontWeight: 400, fill: '#64748B' }, f);

      this.rect('F1', 32, 180, 296, 44, { fill: '#F8FAFC', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 12 }, f);
      this.text('Plugin founders & AI developers', 46, 195, 268, { fontSize: 11, fontWeight: 600, fill: '#0F172A' }, f);
      this.rect('F2', 32, 234, 296, 44, { fill: '#F8FAFC', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 12 }, f);
      this.text('WordPress Product Engineer', 46, 249, 268, { fontSize: 11, fontWeight: 600, fill: '#0F172A' }, f);
      this.rect('F3', 32, 288, 296, 44, { fill: '#F8FAFC', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 12 }, f);
      this.text('WordPress.org: @sunilsharma', 46, 303, 268, { fontSize: 11, fontWeight: 600, fill: '#0F172A' }, f);

      this.rect('FinBtn', 208, 342, 120, 36, { fill: '#0F172A', cornerRadius: 18 }, f);
      this.text('Finish →', 212, 353, 112, { fontSize: 11, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);

      // Completion Summary Card
      this.rect('DoneCard', 16, 408, 328, 316, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 20 }, f);
      this.ellipse('DoneIco', 32, 428, 44, 44, { fill: '#D97706' }, f);
      this.vector('award', 44, 440, 20, '#FFFFFF', f);
      this.text('You are all set!', 88, 434, 230, { fontSize: 18, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('Saved privately on this phone', 88, 458, 230, { fontSize: 10, fontWeight: 600, fill: '#10B981' }, f);

      const bullets = [
        '• Beginner-friendly & AI talks highlighted in Schedule',
        '• Contributor Day tables matched to Core & Docs',
        '• CampCard pre-filled with @sunilsharma',
      ];
      bullets.forEach((b, i) => {
        this.text(b, 32, 496 + i * 34, 296, { fontSize: 11, fontWeight: 500, fill: '#CBD5E1' }, f);
      });

      this.rect('EnterBtn', 32, 650, 296, 46, { fill: '#FF4D2D', cornerRadius: 23 }, f);
      this.text('Choose Your WordCamp →', 36, 666, 288, { fontSize: 12.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
    }

    // M35: Legal Reader Sheet (Privacy Notice & Terms of Use)
    {
      const f = this.screenFrame('M35 · Legal Notice & Terms Reader', 'Full Privacy Policy · Data Categories Table · DPDP Rights · Terms of Use', '#FFF7ED');
      this.statusBar(f, false);
      this.subHeader(f, 'Privacy & Legal');

      this.pill(f, 'Privacy Notice', 16, 86, 158, true, '#0F172A');
      this.pill(f, 'Terms of Use', 184, 86, 160, false, '#0F172A');

      // Section 1 Card
      this.rect('Leg1', 16, 128, 328, 170, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('1. What Stays on Your Phone', 32, 144, 296, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Schedule bookmarks, onboarding preferences, quest checkmarks, and draft CampCards live strictly on this phone. Nothing leaves your device unless you choose to join Discovery.', 32, 168, 296, { fontSize: 10.5, fontWeight: 400, fill: '#475569' }, f);
      this.rect('CatRow', 32, 232, 296, 48, { fill: '#F8FAFC', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 10 }, f);
      this.text('Personal Plan & Card  ·  Device-Only  ·  Cleared anytime', 42, 249, 276, { fontSize: 9.5, fontWeight: 600, fill: '#0F172A' }, f);

      // DPDP Rights Cards
      this.rect('Leg2', 16, 312, 328, 180, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('2. Your DPDP Act 2023 Rights', 32, 328, 296, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);
      const rights = [
        { t: 'Export Complete Data (JSON)', s: 'Download everything saved on your phone.' },
        { t: 'Leave Discovery Anytime', s: 'Immediately removes your public card & waves.' },
        { t: 'Automatic 90-Day Event Cleanup', s: 'Shared event records auto-expire after WordCamp.' },
      ];
      rights.forEach((r, idx) => {
        const y = 360 + idx * 42;
        this.vector('check', 32, y + 4, 14, '#10B981', f);
        this.text(r.t, 52, y, 276, { fontSize: 11, fontWeight: 700, fill: '#0F172A' }, f);
        this.text(r.s, 52, y + 16, 276, { fontSize: 9.5, fontWeight: 400, fill: '#64748B' }, f);
      });

      // Grievance Card
      this.rect('Leg3', 16, 506, 328, 130, { fill: '#FFEDD5', stroke: '#FB923C', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Grievance & Data Officer', 32, 522, 296, { fontSize: 13.5, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('WPSimplified Community Support · hello@wpsimplified.in\nWe respond to all access & erasure requests within 48 hours.', 32, 546, 296, { fontSize: 10.5, fontWeight: 500, fill: '#7C2D12' }, f);
      this.rect('EmailGriev', 32, 592, 296, 34, { fill: '#0F172A', cornerRadius: 17 }, f);
      this.text('Send Data Request Email →', 36, 603, 288, { fontSize: 10.5, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);

      this.rect('LegDone', 16, 690, 328, 44, { fill: '#FF4D2D', cornerRadius: 22 }, f);
      this.text('I Understand & Close', 20, 705, 320, { fontSize: 12, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
    }

    // M36: Meet Sheet & Schedule Picker
    {
      const f = this.screenFrame('M36 · Plan Meet & Schedule Pickers', 'Add People to Meet · Note · Date/Time Picker · ICS / Google Calendar', '#FFF7ED');
      this.statusBar(f, false);
      this.subHeader(f, 'Explore');
      // Dimmed backdrop feel + Elevated sheet
      this.rect('SheetBG', 0, 126, 360, 634, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 28 }, f);
      this.rect('Grab', 156, 138, 48, 5, { fill: '#CBD5E1', cornerRadius: 3 }, f);

      this.avatar(assets.radhika, 20, 160, 50, f, '#10B981', true);
      this.text('ADD TO PEOPLE TO MEET', 82, 158, 220, { fontSize: 9.5, fontWeight: 800, fill: '#FF4D2D' }, f);
      this.text('Radhika Bhama', 82, 174, 220, { fontSize: 17, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('VP Product & Community Leader', 82, 196, 220, { fontSize: 10.5, fontWeight: 500, fill: '#64748B' }, f);
      this.vector('x', 318, 162, 18, '#64748B', f);

      this.text('Your private note', 20, 228, 260, { fontSize: 11.5, fontWeight: 700, fill: '#0F172A' }, f);
      this.rect('NoteBox', 20, 250, 320, 76, { fill: '#F8FAFC', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 12 }, f);
      this.text('Discuss WordPress product ecosystem & enterprise scaling after the AI keynote.', 34, 264, 292, { fontSize: 11, fontWeight: 500, fill: '#0F172A' }, f);

      this.text('When to meet', 20, 344, 260, { fontSize: 11.5, fontWeight: 700, fill: '#0F172A' }, f);
      this.pill(f, 'Any time at event', 20, 368, 148, false);
      this.pill(f, 'At a set time ✓', 178, 368, 162, true, '#0F172A');

      // Date & Time Selector Tiles
      this.rect('DayPick', 20, 412, 154, 48, { fill: '#FFF7ED', stroke: '#FB923C', strokeWeight: 1, cornerRadius: 12 }, f);
      this.vector('calendar', 32, 427, 16, '#EA580C', f);
      this.text('Dec 12, 2026', 56, 429, 110, { fontSize: 11.5, fontWeight: 700, fill: '#0F172A' }, f);

      this.rect('TimePick', 186, 412, 154, 48, { fill: '#FFF7ED', stroke: '#FB923C', strokeWeight: 1, cornerRadius: 12 }, f);
      this.vector('clock', 198, 427, 16, '#EA580C', f);
      this.text('01:30 PM', 222, 429, 110, { fontSize: 11.5, fontWeight: 700, fill: '#0F172A' }, f);

      // Calendar Export Row
      this.pill(f, 'Add to Calendar (.ics)', 20, 476, 154, false);
      this.pill(f, 'Google Calendar ↗', 186, 476, 154, false);

      this.vector('lock', 20, 526, 14, '#10B981', f);
      this.text("Saved on this phone only. They aren't told.", 40, 526, 290, { fontSize: 10.5, fontWeight: 500, fill: '#047857' }, f);

      this.rect('RmBtn', 20, 680, 120, 44, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 22 }, f);
      this.text('Cancel', 24, 695, 112, { fontSize: 11.5, fontWeight: 700, fill: '#0F172A', textAlign: 'center' }, f);
      this.rect('SvBtn', 150, 680, 190, 44, { fill: '#FF4D2D', cornerRadius: 22 }, f);
      this.text('Save to My Day', 154, 695, 182, { fontSize: 12, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
    }

    // M37: Join Discovery Form & Wave Sheet
    {
      const f = this.screenFrame('M37 · Join Discovery & Wave Sheets', 'Identity Selector · Interests & Who to Meet · DPDP Consent · Wave Note', '#FFF7ED');
      this.statusBar(f, false);
      this.subHeader(f, 'Join Discovery');

      this.rect('FormCard', 16, 86, 328, 428, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 18 }, f);
      this.text('How should you appear?', 32, 102, 296, { fontSize: 13.5, fontWeight: 800, fill: '#0F172A' }, f);
      this.pill(f, 'Pick my name', 32, 128, 98, true, '#0F172A');
      this.pill(f, 'Type name', 136, 128, 92, false);
      this.pill(f, 'Anonymous', 234, 128, 94, false);

      this.rect('RosterPick', 32, 168, 296, 40, { fill: '#ECFDF5', stroke: '#10B981', strokeWeight: 1, cornerRadius: 12 }, f);
      this.text('Picked: Sunil Kumar Sharma ✓', 44, 182, 268, { fontSize: 11, fontWeight: 700, fill: '#047857' }, f);

      this.text('What describes you? (up to 5)', 32, 222, 296, { fontSize: 12, fontWeight: 700, fill: '#0F172A' }, f);
      this.pill(f, 'Plugins', 32, 246, 80, true, '#FF4D2D');
      this.pill(f, 'AI & Tools', 118, 246, 88, true, '#FF4D2D');
      this.pill(f, 'Agencies', 212, 246, 88, false);

      this.text('Who would you like to meet?', 32, 288, 296, { fontSize: 12, fontWeight: 700, fill: '#0F172A' }, f);
      this.rect('MeetChips', 32, 312, 296, 40, { fill: '#F8FAFC', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 12 }, f);
      this.text('Founders, Agency Leads, Core Contributors', 44, 325, 272, { fontSize: 10.5, fontWeight: 500, fill: '#0F172A' }, f);

      // DPDP Consent
      this.rect('ConsentBox', 32, 366, 296, 56, { fill: '#FFF7ED', stroke: '#FED7AA', strokeWeight: 1, cornerRadius: 12 }, f);
      this.rect('Chk', 44, 384, 18, 18, { fill: '#10B981', cornerRadius: 4 }, f);
      this.vector('check', 47, 387, 12, '#FFFFFF', f);
      this.text('I consent to share what I filled in above with WordCamp Delhi attendees (auto-deleted in 90 days).', 70, 376, 246, { fontSize: 9.5, fontWeight: 500, fill: '#0F172A' }, f);

      this.rect('SaveDisc', 32, 438, 296, 44, { fill: '#FF4D2D', cornerRadius: 22 }, f);
      this.text('Save & View Matches', 36, 453, 288, { fontSize: 12, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);

      // Compact Wave Preview Strip
      this.rect('WaveMini', 16, 532, 328, 190, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 18 }, f);
      this.text('Send a Private Wave (Preview)', 32, 548, 260, { fontSize: 13, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('They only see that a match wants to say hi until they wave back.', 32, 568, 296, { fontSize: 10, fontWeight: 400, fill: '#64748B' }, f);
      this.rect('WaveTxt', 32, 598, 296, 48, { fill: '#F8FAFC', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 12 }, f);
      this.text('Optional hello note: "Loved your SEO session!"', 44, 615, 272, { fontSize: 10.5, fontWeight: 500, fill: '#475569' }, f);
      this.rect('WaveBtn', 32, 660, 296, 40, { fill: '#0F172A', cornerRadius: 20 }, f);
      this.text('Send Wave', 36, 673, 288, { fontSize: 11.5, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);
    }

    // M38: Contributor Team Sheet & Data Erase Confirm
    {
      const f = this.screenFrame('M38 · Contributor Quiz & Data Erase', 'What Kind of Work Quiz · Team Detail Sheet · Confirm Data Erase Modal', '#FFF7ED');
      this.statusBar(f, false);
      this.subHeader(f, 'Contributor Teams');

      // Quiz Card
      this.rect('QuizCard', 16, 86, 328, 188, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 18 }, f);
      this.text('What kind of work do you enjoy?', 32, 102, 296, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);
      this.pill(f, 'Code & Bugs', 32, 134, 98, true, '#0F172A');
      this.pill(f, 'Writing Docs', 136, 134, 98, true, '#0F172A');
      this.pill(f, 'Design', 240, 134, 88, false);
      this.pill(f, 'Translating', 32, 170, 96, false);
      this.pill(f, 'Testing', 136, 170, 88, false);
      this.rect('SeeMatches', 32, 214, 296, 42, { fill: '#FF4D2D', cornerRadius: 21 }, f);
      this.text('See My Matching Tables →', 36, 228, 288, { fontSize: 11.5, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);

      // Team Detail Sheet Preview
      this.rect('TeamCard', 16, 290, 328, 216, { fill: '#FFFFFF', stroke: '#FB923C', strokeWeight: 1, cornerRadius: 18 }, f);
      this.text('NO TECHNICAL BACKGROUND NEEDED', 32, 306, 296, { fontSize: 9, fontWeight: 800, fill: '#EA580C' }, f);
      this.text('Documentation & HelpHub Team', 32, 322, 296, { fontSize: 15, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Beginner Task: Review 2 onboarding articles & fix broken screenshots with Shreya Agarwal at Table 2.', 32, 348, 296, { fontSize: 10.5, fontWeight: 400, fill: '#475569' }, f);
      this.rect('AtTbl', 32, 396, 296, 46, { fill: '#FFF7ED', cornerRadius: 12 }, f);
      this.text('Table 2 · Hall B · Lead: Shreya Agarwal', 44, 412, 272, { fontSize: 10.5, fontWeight: 700, fill: '#0F172A' }, f);
      this.rect('GotIt', 32, 454, 296, 36, { fill: '#0F172A', cornerRadius: 18 }, f);
      this.text('Got it', 36, 465, 288, { fontSize: 11, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);

      // Data Erase Confirmation Sheet Preview
      this.rect('EraseCard', 16, 522, 328, 200, { fill: '#FFF5F5', stroke: '#FCA5A5', strokeWeight: 1.5, cornerRadius: 18 }, f);
      this.vector('trash', 32, 540, 20, '#DC2626', f);
      this.text('Clear everything on this phone?', 60, 540, 250, { fontSize: 14, fontWeight: 800, fill: '#991B1B' }, f);
      this.text('Removes your saved sessions, quests, CampCard and discovery answers permanently.', 32, 570, 296, { fontSize: 10.5, fontWeight: 400, fill: '#7F1D1D' }, f);
      this.rect('KeepBtn', 32, 650, 140, 42, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 21 }, f);
      this.text('Keep It', 36, 664, 132, { fontSize: 11.5, fontWeight: 700, fill: '#0F172A', textAlign: 'center' }, f);
      this.rect('ClrBtn', 184, 650, 144, 42, { fill: '#EF4444', cornerRadius: 21 }, f);
      this.text('Clear Data', 188, 664, 136, { fontSize: 11.5, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);
    }

    // =========================================================================
    // GROUP 7: ORGANIZER DIRECTORY, EDITORS & CRUD MODALS (M39 - M44)
    // =========================================================================

    // M39: Staff Events Directory & Filters
    {
      const f = this.screenFrame('M39 · Staff Events Directory', 'Search WordCamps · Time & Status Filters · Manage & Attendee View', '#0B1120');
      this.statusBar(f, true, 'Super Admin');
      this.staffHeader(f, 'All WordCamps', 'Dashboard', '+ New');

      // Search & filter
      this.rect('OrgSearch', 16, 84, 280, 40, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 12 }, f);
      this.vector('search', 28, 96, 16, '#94A3B8', f);
      this.text('Search by name or slug...', 52, 97, 220, { fontSize: 11, fontWeight: 400, fill: '#94A3B8' }, f);
      this.rect('FltBtn', 304, 84, 40, 40, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 12 }, f);
      this.vector('filter', 316, 96, 16, '#FF4D2D', f);

      this.pill(f, 'All (26)', 16, 136, 74, true, '#D97706', '#FFFFFF', true);
      this.pill(f, 'Upcoming', 96, 136, 86, false, '#D97706', null, true);
      this.pill(f, 'Past', 188, 136, 68, false, '#D97706', null, true);
      this.pill(f, 'Drafts', 262, 136, 82, false, '#D97706', null, true);

      // Event Card 1: WordCamp Delhi 2026
      this.rect('EvC1', 16, 180, 328, 186, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 18 }, f);
      this.logoTile(assets.delhiFavicon, 30, 196, 46, 46, f, 12, 4, '#FFFFFF');
      this.text('WordCamp Delhi 2026', 86, 198, 220, { fontSize: 15, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('Dec 12–13, 2026 · DAIC New Delhi', 86, 220, 220, { fontSize: 10.5, fontWeight: 500, fill: '#94A3B8' }, f);
      this.rect('B_Act', 30, 256, 68, 22, { fill: '#064E3B', cornerRadius: 11 }, f);
      this.text('Active', 34, 261, 60, { fontSize: 9.5, fontWeight: 700, fill: '#10B981', textAlign: 'center' }, f);
      this.rect('B_Ros', 106, 256, 108, 22, { fill: '#0F172A', cornerRadius: 11 }, f);
      this.text('420 attendees', 110, 261, 100, { fontSize: 9.5, fontWeight: 600, fill: '#CBD5E1', textAlign: 'center' }, f);
      this.rect('B_Rep', 222, 256, 94, 22, { fill: '#450A0A', cornerRadius: 11 }, f);
      this.text('2 reports', 226, 261, 86, { fontSize: 9.5, fontWeight: 700, fill: '#FCA5A5', textAlign: 'center' }, f);

      this.rect('Manage1', 30, 300, 144, 44, { fill: '#FF4D2D', cornerRadius: 22 }, f);
      this.text('Manage', 34, 315, 136, { fontSize: 11.5, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('Prev1', 184, 300, 144, 44, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 22 }, f);
      this.text('Attendee view', 188, 315, 136, { fontSize: 11.5, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);

      // Event Card 2
      this.rect('EvC2', 16, 382, 328, 146, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 18 }, f);
      this.text('WordCamp Athens 2026', 30, 398, 260, { fontSize: 14.5, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('Dec 11–12, 2026 · Megaron Athens', 30, 420, 260, { fontSize: 10.5, fontWeight: 500, fill: '#94A3B8' }, f);
      this.rect('Manage2', 30, 466, 144, 42, { fill: '#FF4D2D', cornerRadius: 21 }, f);
      this.text('Manage', 34, 480, 136, { fontSize: 11, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('Prev2', 184, 466, 144, 42, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 21 }, f);
      this.text('Attendee view', 188, 480, 136, { fontSize: 11, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);

      // Admin action bar
      this.rect('DiscBtn', 16, 546, 328, 44, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 22 }, f);
      this.text('Discover WordCamps from Central', 20, 561, 320, { fontSize: 11, fontWeight: 700, fill: '#F59E0B', textAlign: 'center' }, f);

      this.adminBottomNav(f, 'events');
    }

    // M40: Staff Event Details & Data Sync
    {
      const f = this.screenFrame('M40 · Staff Event Details & Data Sync', 'Event Configuration · Dates & Timezone · Live Re-Fetch Controls', '#0B1120');
      this.statusBar(f, true, 'Event Manager');
      this.staffHeader(f, 'Event Details', 'Overview', 'Preview');

      this.rect('DetCard', 16, 86, 328, 344, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 18 }, f);
      this.text('Display Name & Slug', 30, 102, 260, { fontSize: 13, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.rect('DIn1', 30, 128, 298, 40, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 10 }, f);
      this.text('WordCamp Delhi 2026', 42, 141, 270, { fontSize: 11, fontWeight: 600, fill: '#FFFFFF' }, f);
      this.rect('DIn2', 30, 178, 298, 40, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 10 }, f);
      this.text('campbuddy.club/event/wordcamp-delhi-2026', 42, 191, 270, { fontSize: 10.5, fontWeight: 500, fill: '#94A3B8' }, f);

      this.rect('Dt1', 30, 228, 144, 42, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 10 }, f);
      this.text('Dec 12, 2026', 42, 242, 120, { fontSize: 11, fontWeight: 600, fill: '#FFFFFF' }, f);
      this.rect('Dt2', 184, 228, 144, 42, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 10 }, f);
      this.text('Dec 13, 2026', 196, 242, 120, { fontSize: 11, fontWeight: 600, fill: '#FFFFFF' }, f);

      this.text('Visible in Public Directory', 30, 290, 220, { fontSize: 12, fontWeight: 600, fill: '#FFFFFF' }, f);
      this.toggle(f, 286, 286, true);

      this.pill(f, 'Active', 30, 326, 86, true, '#10B981', '#FFFFFF', true);
      this.pill(f, 'Approved', 124, 326, 94, false, '#D97706', null, true);
      this.pill(f, 'Draft', 226, 326, 82, false, '#D97706', null, true);

      this.rect('SaveDet', 30, 372, 298, 42, { fill: '#FF4D2D', cornerRadius: 21 }, f);
      this.text('Save Event Details', 34, 386, 290, { fontSize: 11.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);

      // Data Refresh Card
      this.rect('SyncBox', 16, 446, 328, 250, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 18 }, f);
      this.text('Data from WordCamp Site', 30, 462, 280, { fontSize: 13.5, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('22 Sessions · 11 Speakers · 17 Sponsors', 30, 484, 280, { fontSize: 10.5, fontWeight: 500, fill: '#10B981' }, f);

      const jobs = [
        { lbl: 'Refresh Schedule & Speakers', y: 516 },
        { lbl: 'Fetch Venue & Wi-Fi Info', y: 564 },
        { lbl: 'Re-fetch Branding & Seals', y: 612 },
      ];
      jobs.forEach(j => {
        this.rect(`J_${j.lbl}`, 30, j.y, 298, 40, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 20 }, f);
        this.vector('refresh', 44, j.y + 12, 16, '#F59E0B', f);
        this.text(j.lbl, 70, j.y + 13, 240, { fontSize: 11, fontWeight: 700, fill: '#FFFFFF' }, f);
      });
    }

    // M41: Staff Venue Info Editor & Danger Zone
    {
      const f = this.screenFrame('M41 · Staff Venue Info & Danger Zone', 'Edit Venue, Wi-Fi & Helpdesk · Type-to-Confirm Delete Event', '#0B1120');
      this.statusBar(f, true, 'Event Manager');
      this.staffHeader(f, 'Venue Information', 'Overview');

      this.rect('InfCard', 16, 86, 328, 396, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 18 }, f);
      const fields = [
        { lbl: 'Venue Address', val: 'DAIC, 15 Janpath Rd, New Delhi', y: 102 },
        { lbl: 'Guest Wi-Fi & Passkey', val: 'WCDELHI-GUEST · Pass: CodeIsPoetry26', y: 176 },
        { lbl: 'Registration Desk', val: 'Ground Floor Main Atrium · Opens 8 AM', y: 250 },
        { lbl: 'Emergency & Safety Contact', val: 'delhi@wordcamp.org · +91 98XXX-XXXXX', y: 324 },
      ];
      fields.forEach(fi => {
        this.text(fi.lbl, 30, fi.y, 260, { fontSize: 10, fontWeight: 700, fill: '#94A3B8' }, f);
        this.rect(`IB_${fi.lbl}`, 30, fi.y + 18, 298, 40, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 10 }, f);
        this.text(fi.val, 42, fi.y + 31, 274, { fontSize: 11, fontWeight: 500, fill: '#FFFFFF' }, f);
      });
      this.rect('SvInf', 30, 422, 298, 42, { fill: '#10B981', cornerRadius: 21 }, f);
      this.text('Save Event Information', 34, 436, 290, { fontSize: 11.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);

      // Danger Zone Card
      this.rect('DangerBox', 16, 502, 328, 210, { fill: '#450A0A', stroke: '#EF4444', strokeWeight: 1.5, cornerRadius: 18 }, f);
      this.vector('trash', 30, 520, 18, '#FCA5A5', f);
      this.text('Danger Zone · Delete Event', 56, 520, 260, { fontSize: 14, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('Permanently removes this WordCamp, its quests, deals, tables, and server attendee lists.', 30, 548, 298, { fontSize: 10.5, fontWeight: 400, fill: '#FECACA' }, f);
      this.rect('SlugConf', 30, 594, 298, 38, { fill: '#0B1120', stroke: '#EF4444', strokeWeight: 1, cornerRadius: 10 }, f);
      this.text('Type "wordcamp-delhi-2026" to confirm', 42, 606, 274, { fontSize: 10, fontWeight: 500, fill: '#FCA5A5' }, f);
      this.rect('DelEvBtn', 30, 646, 298, 42, { fill: '#EF4444', cornerRadius: 21 }, f);
      this.text('Delete Event Permanently', 34, 660, 290, { fontSize: 11.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
    }

    // M42: Staff CRUD Modals (Roles, Quests & Deals Sheets)
    {
      const f = this.screenFrame('M42 · Staff CRUD Sheets (Roles & Deals)', 'Assign Roster Roles · Create Quest Sheet · Sponsor Deal Editor', '#0B1120');
      this.statusBar(f, true, 'Event Manager');
      this.staffHeader(f, 'Roles & Deals', 'Overview');

      // Elevated Sheet 1: Assign Attendee Roles
      this.rect('RoleSheet', 16, 86, 328, 280, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 20 }, f);
      this.avatar(assets.amit, 30, 102, 42, f, '#10B981');
      this.text('Amit Kumar Singh', 82, 104, 210, { fontSize: 14.5, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('Managing Partner · WPoets', 82, 124, 210, { fontSize: 10, fontWeight: 500, fill: '#10B981' }, f);
      this.text('Assigned Roles', 30, 160, 240, { fontSize: 11.5, fontWeight: 700, fill: '#FFFFFF' }, f);
      this.pill(f, 'Speaker ✓', 30, 186, 94, true, '#FF4D2D', '#FFFFFF', true);
      this.pill(f, 'Table Lead ✓', 132, 186, 102, true, '#FF4D2D', '#FFFFFF', true);
      this.pill(f, 'Organizer', 242, 186, 86, false, '#FF4D2D', null, true);
      this.pill(f, 'Volunteer', 30, 224, 92, false, '#FF4D2D', null, true);
      this.pill(f, 'Sponsor', 130, 224, 88, false, '#FF4D2D', null, true);
      this.rect('SaveRole', 30, 306, 298, 42, { fill: '#FF4D2D', cornerRadius: 21 }, f);
      this.text('Save Roster Roles', 34, 320, 290, { fontSize: 11.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);

      // Sheet 2 Preview: Add Sponsor Deal Modal
      this.rect('DealSheet', 16, 384, 328, 340, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 20 }, f);
      this.text('Edit Sponsor Deal', 30, 402, 240, { fontSize: 14.5, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.logoTile(assets.sponsor_wpfusion, 258, 396, 70, 32, f, 8, 4, '#FDFBF7');
      this.rect('DL1', 30, 440, 298, 38, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 10 }, f);
      this.text('WP Fusion · 30% Off WordPress CRM', 42, 453, 274, { fontSize: 11, fontWeight: 600, fill: '#FFFFFF' }, f);
      this.rect('DL2', 30, 488, 144, 38, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 10 }, f);
      this.text('Code: WCDELHI30', 42, 501, 120, { fontSize: 10.5, fontWeight: 600, fill: '#F59E0B' }, f);
      this.rect('DL3', 184, 488, 144, 38, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 10 }, f);
      this.text('Lead Form: Active', 196, 501, 120, { fontSize: 10.5, fontWeight: 600, fill: '#10B981' }, f);
      this.text('Capture email & agency size before redirect', 30, 542, 220, { fontSize: 10.5, fontWeight: 500, fill: '#CBD5E1' }, f);
      this.toggle(f, 286, 540, true);
      this.rect('SvDeal', 30, 662, 298, 42, { fill: '#D97706', cornerRadius: 21 }, f);
      this.text('Save Sponsor Deal', 34, 676, 290, { fontSize: 11.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
    }

    // M43: Staff Notifications & Event Activity Log
    {
      const f = this.screenFrame('M43 · Staff Notifications & Event Log', 'Trigger Photo/Video Alerts · Per-Event Manager Audit Timeline', '#0B1120');
      this.statusBar(f, true, 'Event Manager');
      this.staffHeader(f, 'Alerts & Activity', 'Overview');

      // Notifications Card
      this.rect('NotCard', 16, 86, 328, 226, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 18 }, f);
      this.text('Event Push Notifications', 30, 102, 260, { fontSize: 14, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('584 attendee devices subscribed to live alerts', 30, 124, 290, { fontSize: 10, fontWeight: 500, fill: '#10B981' }, f);

      this.rect('NRow1', 30, 152, 298, 66, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 12 }, f);
      this.text('Official Photo Album Live', 42, 164, 190, { fontSize: 12, fontWeight: 700, fill: '#FFFFFF' }, f);
      this.text('Ready to notify attendees', 42, 184, 190, { fontSize: 9.5, fontWeight: 500, fill: '#94A3B8' }, f);
      this.rect('SendN1', 240, 170, 74, 30, { fill: '#FF4D2D', cornerRadius: 15 }, f);
      this.text('Send', 244, 179, 66, { fontSize: 10, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);

      this.rect('NRow2', 30, 228, 298, 66, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 12 }, f);
      this.text('Talks on WordPress.tv', 42, 240, 190, { fontSize: 12, fontWeight: 700, fill: '#FFFFFF' }, f);
      this.text('Sent Dec 18, 2026 · 4:10 PM', 42, 260, 190, { fontSize: 9.5, fontWeight: 500, fill: '#10B981' }, f);
      this.rect('SendN2', 240, 246, 74, 30, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 15 }, f);
      this.text('Resend', 244, 255, 66, { fontSize: 10, fontWeight: 600, fill: '#CBD5E1', textAlign: 'center' }, f);

      // Event Activity Feed
      this.text('Event Activity Timeline', 16, 330, 240, { fontSize: 13.5, fontWeight: 800, fill: '#F59E0B' }, f);
      const logs = [
        { who: 'Rohan Verma', act: 'Broadcasted "Lunch is Served in Courtyard"', when: 'Today, 12:30 PM', y: 360 },
        { who: 'Neha Kapoor', act: 'Set +15m Running Late on BHIM Auditorium', when: 'Today, 11:05 AM', y: 440 },
        { who: 'Rohan Verma', act: 'Assigned Table Lead role to Amit Singh', when: 'Yesterday, 6:40 PM', y: 520 },
        { who: 'System Check', act: 'Synced 22 sessions & 17 sponsor logos', when: 'Yesterday, 6:00 PM', y: 600 },
      ];
      logs.forEach(l => {
        this.rect(`Log_${l.when}`, 16, l.y, 328, 68, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 14 }, f);
        this.text(l.who, 30, l.y + 12, 160, { fontSize: 10.5, fontWeight: 800, fill: '#FF4D2D' }, f);
        this.text(l.when, 180, l.y + 12, 150, { fontSize: 9.5, fontWeight: 500, fill: '#64748B', textAlign: 'right' }, f);
        this.text(l.act, 30, l.y + 34, 298, { fontSize: 11, fontWeight: 500, fill: '#FFFFFF' }, f);
      });
    }

    // M44: Admin New Event & Profile Report Sheet
    {
      const f = this.screenFrame('M44 · Admin New Event & Report Sheet', 'Create New WordCamp Modal · Flagged Profile Safety Review Sheet', '#0B1120');
      this.statusBar(f, true, 'Super Admin');
      this.staffHeader(f, 'New WordCamp', 'Events');

      this.rect('NewEvCard', 16, 86, 328, 346, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 20 }, f);
      this.text('Add New WordCamp', 30, 102, 240, { fontSize: 15, fontWeight: 800, fill: '#FFFFFF' }, f);
      const neFields = [
        { lbl: 'WordCamp Name', val: 'WordCamp Bengaluru 2027', y: 134 },
        { lbl: 'Official WordCamp URL', val: 'https://bengaluru.wordcamp.org/2027/', y: 204 },
        { lbl: 'URL Slug', val: 'wordcamp-bengaluru-2027', y: 274 },
      ];
      neFields.forEach(fi => {
        this.text(fi.lbl, 30, fi.y, 260, { fontSize: 10, fontWeight: 700, fill: '#94A3B8' }, f);
        this.rect(`NE_${fi.lbl}`, 30, fi.y + 18, 298, 40, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 10 }, f);
        this.text(fi.val, 42, fi.y + 31, 274, { fontSize: 11, fontWeight: 500, fill: '#FFFFFF' }, f);
      });
      this.rect('CreateEvBtn', 30, 368, 298, 44, { fill: '#FF4D2D', cornerRadius: 22 }, f);
      this.text('Create & Fetch Schedule', 34, 383, 290, { fontSize: 12, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);

      // Profile Report Detail Sheet Preview
      this.rect('RepSheet', 16, 452, 328, 268, { fill: '#1E293B', stroke: '#7F1D1D', strokeWeight: 1.5, cornerRadius: 20 }, f);
      this.vector('alert', 30, 470, 20, '#EF4444', f);
      this.text('Review Profile Report #41', 58, 470, 240, { fontSize: 14.5, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('Reason: Promotional / Spam external links', 30, 502, 298, { fontSize: 11, fontWeight: 700, fill: '#FCA5A5' }, f);
      this.text('Reporter Note: "Discovery bio pushes commercial affiliate links instead of WordCamp interests."', 30, 526, 298, { fontSize: 10.5, fontWeight: 400, fill: '#CBD5E1' }, f);
      this.rect('HideP', 30, 594, 298, 42, { fill: '#EF4444', cornerRadius: 21 }, f);
      this.text('Hide Profile from Discovery', 34, 608, 290, { fontSize: 11.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('DismissP', 30, 646, 298, 40, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 20 }, f);
      this.text('Dismiss Report', 34, 659, 290, { fontSize: 11, fontWeight: 600, fill: '#CBD5E1', textAlign: 'center' }, f);
    }

    // =========================================================================
    // GROUP 8: SUPERADMIN MORE HUB & SUB-PAGES (M45 - M50)
    // =========================================================================

    // M45: SuperAdmin More Menu & Platform Maintenance
    {
      const f = this.screenFrame('M45 · SuperAdmin More & Maintenance', 'More Navigation Hub · Global Refresh & Cache Purge Confirmation', '#0B1120');
      this.statusBar(f, true, 'Super Admin');
      this.staffHeader(f, 'Analytics & More', 'Dashboard');

      const menu = [
        { ico: 'activity', t: 'Analytics', s: 'Visitors, WordCamps & device split', y: 86 },
        { ico: 'alert', t: 'Errors', s: 'System checks & failed feed fetches', y: 158 },
        { ico: 'heart', t: 'Feedback', s: 'Ratings & thank-you card comments', y: 230 },
        { ico: 'users', t: 'Manager activity', s: 'Every organizer change cross-event', y: 302 },
      ];
      menu.forEach(m => {
        this.rect(`MM_${m.t}`, 16, m.y, 328, 60, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 16 }, f);
        this.ellipse(`MI_${m.t}`, 28, m.y + 12, 36, 36, { fill: '#0F172A' }, f);
        this.vector(m.ico, 37, m.y + 21, 18, '#FF4D2D', f);
        this.text(m.t, 76, m.y + 13, 210, { fontSize: 13.5, fontWeight: 700, fill: '#FFFFFF' }, f);
        this.text(m.s, 76, m.y + 33, 220, { fontSize: 10, fontWeight: 400, fill: '#94A3B8' }, f);
        this.vector('chevronRight', 320, m.y + 22, 14, '#64748B', f);
      });

      // Maintenance Box
      this.rect('MaintBox', 16, 382, 328, 290, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 18 }, f);
      this.text('Platform Maintenance', 30, 398, 260, { fontSize: 14.5, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('Last sync: Today 10:25 AM (WordCamp Delhi 2026)', 30, 422, 298, { fontSize: 10, fontWeight: 500, fill: '#10B981' }, f);
      this.text('Last cache clear: Dec 10, 2026 · Sunil Sharma', 30, 442, 298, { fontSize: 10, fontWeight: 400, fill: '#94A3B8' }, f);

      this.rect('RefAll', 30, 480, 298, 44, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 22 }, f);
      this.text('Refresh All Live Event Data', 34, 495, 290, { fontSize: 11.5, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('PurgeAll', 30, 536, 298, 44, { fill: '#450A0A', stroke: '#EF4444', strokeWeight: 1, cornerRadius: 22 }, f);
      this.text('Clear Platform Cache', 34, 551, 290, { fontSize: 11.5, fontWeight: 700, fill: '#FCA5A5', textAlign: 'center' }, f);

      this.text('Desktop-only settings (media uploads, reels) remain on the Admin Web Console.', 30, 600, 298, { fontSize: 9.5, fontWeight: 400, fill: '#64748B' }, f);

      this.adminBottomNav(f, 'more');
    }

    // M46: SuperAdmin System Errors & Fetch Log
    {
      const f = this.screenFrame('M46 · SuperAdmin System Errors', '7d/30d Filters · Job Breakdown · Feed Retry & System Checks', '#0B1120');
      this.statusBar(f, true, 'Super Admin');
      this.staffHeader(f, 'Errors', 'More');

      this.pill(f, 'Last 7 days', 16, 84, 100, true, '#D97706', '#FFFFFF', true);
      this.pill(f, 'Last 30 days', 124, 84, 108, false, '#D97706', null, true);
      this.pill(f, 'Failed Fetch', 240, 84, 104, false, '#D97706', null, true);

      // Health Hero
      this.rect('ErrHero', 16, 128, 328, 72, { fill: '#064E3B', stroke: '#10B981', strokeWeight: 1, cornerRadius: 16 }, f);
      this.vector('shield', 32, 152, 22, '#10B981', f);
      this.text('0 Critical Issues · 2 Warnings', 64, 146, 260, { fontSize: 13.5, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('WordCamp Delhi 2026 · 100% Healthy', 64, 168, 260, { fontSize: 10, fontWeight: 500, fill: '#A7F3D0' }, f);

      // Warning Row 1
      this.rect('Err1', 16, 218, 328, 136, { fill: '#1E293B', stroke: '#F59E0B', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('WARNING · WORDCAMP ATHENS 2026', 30, 234, 240, { fontSize: 9.5, fontWeight: 800, fill: '#F59E0B' }, f);
      this.text('Favicon media timeout (retrying)', 30, 254, 298, { fontSize: 13, fontWeight: 700, fill: '#FFFFFF' }, f);
      this.text('Upstream WordCamp site returned HTTP 504 on sponsor logo check.', 30, 276, 298, { fontSize: 10, fontWeight: 400, fill: '#94A3B8' }, f);
      this.rect('Retry1', 30, 310, 140, 32, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Re-fetch Now', 34, 319, 132, { fontSize: 10, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);

      // OK Checks Card
      this.rect('ErrOk', 16, 370, 328, 200, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Recent Automated Checks', 30, 386, 260, { fontSize: 13, fontWeight: 800, fill: '#FFFFFF' }, f);
      const checks = [
        '✓  Delhi Schedule Sync · 22 sessions verified',
        '✓  Sponsor Feed Check · 17/17 logos HTTP 200',
        '✓  Speaker Portraits · 11/11 cached cleanly',
      ];
      checks.forEach((c, idx) => {
        this.text(c, 30, 422 + idx * 42, 298, { fontSize: 11, fontWeight: 500, fill: '#10B981' }, f);
      });

      this.adminBottomNav(f, 'more');
    }

    // M47: SuperAdmin Attendee Feedback
    {
      const f = this.screenFrame('M47 · SuperAdmin Attendee Feedback', 'Ratings Summary · Distribution · Attendee Thank-You Comments', '#0B1120');
      this.statusBar(f, true, 'Super Admin');
      this.staffHeader(f, 'Feedback', 'More');

      // Rating Hero
      this.rect('FBRating', 16, 86, 328, 128, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 18 }, f);
      this.text('4.9 ★', 32, 104, 120, { fontSize: 32, fontWeight: 800, fill: '#F59E0B' }, f);
      this.text('96 responses', 32, 148, 120, { fontSize: 10.5, fontWeight: 600, fill: '#94A3B8' }, f);
      this.text('From attendee Thank-You cards', 32, 168, 260, { fontSize: 10, fontWeight: 500, fill: '#10B981' }, f);

      const bars = [
        { s: '5★', w: 128, y: 104 },
        { s: '4★', w: 34, y: 124 },
        { s: '3★', w: 12, y: 144 },
      ];
      bars.forEach(b => {
        this.text(b.s, 174, b.y, 24, { fontSize: 9.5, fontWeight: 700, fill: '#CBD5E1' }, f);
        this.rect(`BT_${b.s}`, 202, b.y + 4, 128, 7, { fill: '#0F172A', cornerRadius: 3.5 }, f);
        this.rect(`BF_${b.s}`, 202, b.y + 4, b.w, 7, { fill: '#F59E0B', cornerRadius: 3.5 }, f);
      });

      // Feedback Comments
      const fb = [
        { star: '★★★★★', msg: '"Offline schedule and my QR CampCard worked seamlessly even inside the basement hall at DAIC!"', ev: 'WordCamp Delhi 2026 · Dec 13', y: 232 },
        { star: '★★★★★', msg: '"Loved the Contributor Day beginner tips and quick mutual wave chat."', ev: 'WordCamp Delhi 2026 · Dec 12', y: 374 },
        { star: '★★★★★', msg: '"Clean, fast app with zero bloat or login friction."', ev: 'WordCamp Kerala 2026 · Nov 28', y: 504 },
      ];
      fb.forEach(c => {
        this.rect(`FBC_${c.y}`, 16, c.y, 328, c.msg.length > 85 ? 124 : 112, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 16 }, f);
        this.text(c.star, 30, c.y + 14, 140, { fontSize: 12, fontWeight: 700, fill: '#F59E0B' }, f);
        this.text(c.msg, 30, c.y + 40, 298, { fontSize: 11, fontWeight: 500, fill: '#FFFFFF' }, f);
        this.text(c.ev, 30, c.y + (c.msg.length > 85 ? 96 : 84), 298, { fontSize: 9.5, fontWeight: 500, fill: '#94A3B8' }, f);
      });

      this.adminBottomNav(f, 'more');
    }

    // M48: SuperAdmin Global Activity Log
    {
      const f = this.screenFrame('M48 · SuperAdmin Global Activity Log', 'Cross-Event Audit Feed · Filter by Manager & WordCamp', '#0B1120');
      this.statusBar(f, true, 'Super Admin');
      this.staffHeader(f, 'Manager Activity', 'More');

      this.pill(f, 'All Actions', 16, 84, 96, true, '#D97706', '#FFFFFF', true);
      this.pill(f, 'Rohan V.', 120, 84, 88, false, '#D97706', null, true);
      this.pill(f, 'Neha K.', 216, 84, 84, false, '#D97706', null, true);

      const events = [
        { mgr: 'Rohan Verma', ev: 'WordCamp Delhi 2026', act: 'Sent live broadcast: "Lunch is Served!"', at: 'Today · 12:30 PM', y: 128 },
        { mgr: 'Neha Kapoor', ev: 'WordCamp Delhi 2026', act: 'Set +15m Running Late on Track 1', at: 'Today · 11:05 AM', y: 228 },
        { mgr: 'Sunil Sharma', ev: 'Global Platform', act: 'Verified 17 sponsor logos & cleared cache', at: 'Yesterday · 9:15 PM', y: 328 },
        { mgr: 'Arjun Mehta', ev: 'WordCamp Pune 2027', act: 'Updated Contributor Day table leads', at: 'Dec 10 · 4:20 PM', y: 428 },
        { mgr: 'Neha Kapoor', ev: 'WordCamp Delhi 2026', act: 'Added 2 sponsor deals from Elementor & WP Fusion', at: 'Dec 09 · 2:10 PM', y: 528 },
      ];
      events.forEach(e => {
        this.rect(`Act_${e.y}`, 16, e.y, 328, 86, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 16 }, f);
        this.text(e.mgr, 30, e.y + 12, 160, { fontSize: 11.5, fontWeight: 800, fill: '#FFFFFF' }, f);
        this.text(e.at, 180, e.y + 13, 148, { fontSize: 9.5, fontWeight: 500, fill: '#94A3B8', textAlign: 'right' }, f);
        this.text(e.ev, 30, e.y + 32, 260, { fontSize: 9.5, fontWeight: 700, fill: '#10B981' }, f);
        this.text(e.act, 30, e.y + 52, 298, { fontSize: 10.5, fontWeight: 500, fill: '#CBD5E1' }, f);
      });

      this.adminBottomNav(f, 'more');
    }

    // M49: SuperAdmin Analytics Breakdown
    {
      const f = this.screenFrame('M49 · SuperAdmin Analytics', 'Date Range Filters · Top WordCamps · Device & Engagement Split', '#0B1120');
      this.statusBar(f, true, 'Super Admin');
      this.staffHeader(f, 'Analytics', 'More');

      this.pill(f, 'Last 7 days', 16, 84, 98, true, '#FF4D2D', '#FFFFFF', true);
      this.pill(f, 'Last 30 days', 122, 84, 108, false, '#FF4D2D', null, true);
      this.pill(f, 'Delhi 2026', 238, 84, 106, false, '#FF4D2D', null, true);

      // 2-Col KPI
      this.rect('AN1', 16, 128, 158, 76, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Active Visitors', 30, 142, 130, { fontSize: 10, fontWeight: 600, fill: '#94A3B8' }, f);
      this.text('4,820', 30, 164, 130, { fontSize: 22, fontWeight: 800, fill: '#FFFFFF' }, f);

      this.rect('AN2', 186, 128, 158, 76, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Avg Engagement', 200, 142, 130, { fontSize: 10, fontWeight: 600, fill: '#94A3B8' }, f);
      this.text('84%', 200, 164, 130, { fontSize: 22, fontWeight: 800, fill: '#10B981' }, f);

      // Top WordCamps Table Card
      this.rect('AN_Ev', 16, 222, 328, 210, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 18 }, f);
      this.text('Top WordCamps by Attendees', 30, 238, 280, { fontSize: 13.5, fontWeight: 800, fill: '#FFFFFF' }, f);
      const topRows = [
        { name: 'WordCamp Delhi 2026', count: '2,840', y: 274 },
        { name: 'WordCamp Athens 2026', count: '940', y: 320 },
        { name: 'WordCamp Kerala 2026', count: '680', y: 366 },
      ];
      topRows.forEach(r => {
        this.rect(`TR_${r.name}`, 30, r.y, 298, 36, { fill: '#0F172A', cornerRadius: 10 }, f);
        this.text(r.name, 42, r.y + 11, 210, { fontSize: 11, fontWeight: 600, fill: '#FFFFFF' }, f);
        this.text(r.count, 260, r.y + 11, 56, { fontSize: 11, fontWeight: 800, fill: '#F59E0B', textAlign: 'right' }, f);
      });

      // Device Split Card
      this.rect('AN_Dev', 16, 450, 328, 178, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 18 }, f);
      this.text('Platform Breakdown', 30, 466, 260, { fontSize: 13.5, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('Android APK & Mobile Web  ·  68%', 30, 500, 298, { fontSize: 11, fontWeight: 600, fill: '#CBD5E1' }, f);
      this.rect('BarA', 30, 520, 298, 8, { fill: '#0F172A', cornerRadius: 4 }, f);
      this.rect('BarAF', 30, 520, 202, 8, { fill: '#FF4D2D', cornerRadius: 4 }, f);
      this.text('Desktop Web & PWA  ·  32%', 30, 548, 298, { fontSize: 11, fontWeight: 600, fill: '#CBD5E1' }, f);
      this.rect('BarW', 30, 568, 298, 8, { fill: '#0F172A', cornerRadius: 4 }, f);
      this.rect('BarWF', 30, 568, 96, 8, { fill: '#10B981', cornerRadius: 4 }, f);

      this.adminBottomNav(f, 'more');
    }

    // M50: SuperAdmin Add / Edit Event Manager Sheet
    {
      const f = this.screenFrame('M50 · SuperAdmin Manager Sheet', 'Edit Event Manager Profile · Sign-In Toggle · Multi-Event Assignment', '#0B1120');
      this.statusBar(f, true, 'Super Admin');
      this.staffHeader(f, 'Edit Manager', 'Managers');

      this.rect('MgrCard', 16, 86, 328, 636, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 20 }, f);
      this.text('Rohan Verma', 30, 104, 240, { fontSize: 17, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('Lead Organizer · WordCamp Delhi', 30, 128, 240, { fontSize: 10.5, fontWeight: 500, fill: '#10B981' }, f);

      const mFields = [
        { lbl: 'E-mail (Sign-in)', val: 'rohan@wordcampdelhi.org', y: 158 },
        { lbl: 'Phone Number', val: '+91 98110-XXXXX', y: 224 },
      ];
      mFields.forEach(mf => {
        this.text(mf.lbl, 30, mf.y, 260, { fontSize: 10, fontWeight: 700, fill: '#94A3B8' }, f);
        this.rect(`MF_${mf.lbl}`, 30, mf.y + 18, 298, 38, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 10 }, f);
        this.text(mf.val, 42, mf.y + 30, 274, { fontSize: 11, fontWeight: 500, fill: '#FFFFFF' }, f);
      });

      this.text('Can Sign In', 30, 304, 200, { fontSize: 12.5, fontWeight: 700, fill: '#FFFFFF' }, f);
      this.text('Active organizer session access', 30, 322, 220, { fontSize: 9.5, fontWeight: 400, fill: '#94A3B8' }, f);
      this.toggle(f, 286, 306, true);

      this.line('MSep', 30, 354, 298, { stroke: '#334155', strokeWeight: 1 }, f);
      this.text('Assigned WordCamps (2)', 30, 368, 240, { fontSize: 12, fontWeight: 800, fill: '#FFFFFF' }, f);

      this.pill(f, 'WordCamp Delhi 2026 ✓', 30, 398, 174, true, '#D97706', '#FFFFFF', true);
      this.pill(f, 'Rajasthan 2026 ✓', 212, 398, 116, true, '#D97706', '#FFFFFF', true);
      this.pill(f, 'Pune 2027', 30, 434, 96, false, '#D97706', null, true);
      this.pill(f, 'Kerala 2026', 134, 434, 102, false, '#D97706', null, true);
      this.pill(f, 'Bengaluru 2027', 244, 434, 84, false, '#D97706', null, true);

      this.rect('SaveMgr', 30, 600, 298, 44, { fill: '#FF4D2D', cornerRadius: 22 }, f);
      this.text('Save Manager Access', 34, 615, 290, { fontSize: 12, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('DelMgr', 30, 656, 298, 40, { fill: '#450A0A', stroke: '#EF4444', strokeWeight: 1, cornerRadius: 20 }, f);
      this.text('Remove Manager Account', 34, 669, 290, { fontSize: 11, fontWeight: 700, fill: '#FCA5A5', textAlign: 'center' }, f);
    }
  }
}

const builder = new MissingApkBuilder();
builder.buildMissing18Screens();

const scriptPath = path.resolve(rootDir, 'scratch/figcraft_apk_missing_script.json');
fs.writeFileSync(scriptPath, JSON.stringify(builder.commands, null, 2), 'utf8');
console.log(`Generated ${builder.commands.length} FigCraft commands for 18 Missing APK sub-screens (M33-M50).`);
console.log(`Saved script to ${scriptPath}`);
