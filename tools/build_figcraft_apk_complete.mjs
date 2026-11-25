import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const rootDir = path.resolve(__dirname, '..');

const assets = {
  cbWordmark: path.resolve(rootDir, 'docs/designs/assets/campbuddy_wordmark.png'),
  cbIcon: path.resolve(rootDir, 'docs/designs/assets/campbuddy_icon.png'),
  delhiLogo: path.resolve(rootDir, 'docs/designs/assets/delhi_logo.png'),
  delhiFavicon: path.resolve(rootDir, 'docs/designs/assets/delhi_favicon.png'),
  qrSample: path.resolve(rootDir, 'docs/designs/assets/qr_sample.png'),
  pankaj: path.resolve(rootDir, 'docs/designs/assets/pankaj_kumar.webp'),
  radhika: path.resolve(rootDir, 'docs/designs/assets/radhika_bhama.jpg'),
  amit: path.resolve(rootDir, 'docs/designs/assets/amit_singh.webp'),
  shammi: path.resolve(rootDir, 'docs/designs/assets/shammi_proothi.webp'),
  abhay: path.resolve(rootDir, 'docs/designs/assets/abhay_kulkarni.webp'),
  om: path.resolve(rootDir, 'docs/designs/assets/om_asnani.webp'),
  shreya: path.resolve(rootDir, 'docs/designs/assets/shreya_agarwal.webp'),
  aditya: path.resolve(rootDir, 'docs/designs/assets/aditya_singh.webp'),
  indiaGate: path.resolve(rootDir, 'docs/designs/assets/india_gate.jpg'),
  skyline: path.resolve(rootDir, 'docs/designs/assets/delhi_skyline.webp'),
  sponsor_elementor: path.resolve(rootDir, 'docs/designs/assets/sponsor_elementor.webp'),
  sponsor_wpfusion: path.resolve(rootDir, 'docs/designs/assets/sponsor_wpfusion.webp'),
  sponsor_cookieadmin: path.resolve(rootDir, 'docs/designs/assets/sponsor_cookieadmin.webp'),
  sponsor_nexgi: path.resolve(rootDir, 'docs/designs/assets/sponsor_nexgi.webp'),
  sponsor_housewise: path.resolve(rootDir, 'docs/designs/assets/sponsor_housewise.webp'),
  sponsor_zuptek: path.resolve(rootDir, 'docs/designs/assets/sponsor_zuptek.webp'),
};

// Official CampBuddy Lucide Line Icons - crisp stroke vectors
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
  qr: 'M4 3h5a1 1 0 0 1 1 1v5a1 1 0 0 1 -1 1h-5a1 1 0 0 1 -1 -1v-5a1 1 0 0 1 1 -1zM15 3h5a1 1 0 0 1 1 1v5a1 1 0 0 1 -1 1h-5a1 1 0 0 1 -1 -1v-5a1 1 0 0 1 1 -1zM4 14h5a1 1 0 0 1 1 1v5a1 1 0 0 1 -1 1h-5a1 1 0 0 1 -1 -1v-5a1 1 0 0 1 1 -1zM17 14v3M14 17h3',
  search: 'M4 11a7 7 0 1 0 14 0 7 7 0 1 0 -14 0zM21 21l-5-5',
  bell: 'M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10.3 21a1.94 1.94 0 0 0 3.4 0',
  shield: 'M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1zM9 12l 2 2 4-4',
  heart: 'M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7z',
  sparkles: 'M12 3l1.9 5.8L20 11l-6.1 2.2L12 19l-1.9-5.8L4 11l6.1-2.2zM19 3v4M17 5h4',
  ticket: 'M3 9a3 3 0 0 0 0 6v3a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1v-3a3 3 0 0 0 0-6V6a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1zM13 5v2M13 11v2M13 17v2',
  phone: 'M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z',
  plus: 'M12 5v14M5 12h14',
  check: 'M20 6 9 17l-5-5',
  award: 'M6 8a6 6 0 1 0 12 0 6 6 0 1 0 -12 0zM15.48 12.89l 2.52 9.11-6-4-6 4 2.52-9.11',
  chat: 'M7.9 20A9 9 0 1 0 4 16.1L2 22z',
  megaphone: 'M3 11l 18-5v12L3 14v-3zM11.6 16.8a3 3 0 1 1-5.8-1.6',
  camera: 'M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3zM9 13a3 3 0 1 0 6 0 3 3 0 1 0 -6 0z',
  lock: 'M6 11h12a2 2 0 0 1 2 2v6a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-6a2 2 0 0 1 2 -2zM8 11V7a4 4 0 0 1 8 0v4',
  crown: 'M2 4l 3 12h14l3-12-6 7-4-7-4 7-6-7zM5 20h14',
  chevronLeft: 'M15 18l-6-6 6-6',
  chevronRight: 'M9 18l 6-6-6-6',
  switchEvent: 'M16 3l4 4-4 4M20 7H4M8 21l-4-4 4-4M4 17h16',
  help: 'M2 12a10 10 0 1 0 20 0 10 10 0 1 0 -20 0zM9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3M12 17h.01',
  alert: 'M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1zM12 8v4M12 16h.01',
  activity: 'M22 12h-4l-3 9L9 3l-3 9H2',
  wrench: 'M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z',
  download: 'M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l 5 5 5-5M12 15V3',
  share: 'M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8M16 6l-4-4-4 4M12 2v13',
  filter: 'M4 6h16M7 12h10M10 18h4',
  coffee: 'M17 8h1a4 4 0 1 1 0 8h-1M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4zM6 2v2M10 2v2M14 2v2',
  pencil: 'M17 3l 4 4L7.5 20.5 2 22l1.5-5.5z',
  lifeBuoy: 'M2 12a10 10 0 1 0 20 0 10 10 0 1 0 -20 0zM8 12a4 4 0 1 0 8 0 4 4 0 1 0 -8 0zM4.93 4.93l 4.24 4.24M14.83 9.17l4.24-4.24M14.83 14.83l4.24 4.24M9.17 14.83l-4.24 4.24',
  target: 'M2 12a10 10 0 1 0 20 0 10 10 0 1 0 -20 0zM6 12a6 6 0 1 0 12 0 6 6 0 1 0 -12 0zM10 12a2 2 0 1 0 4 0 2 2 0 1 0 -4 0z',
  columns: 'M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2zM9 3v18M15 3v18',
};

const ICON_DIMS = {
  chevronLeft: [6, 12],
  chevronRight: [6, 12],
  check: [16, 11],
  crown: [20, 16],
  award: [12, 20],
  mapPin: [14, 18],
  alert: [16, 19],
  shield: [16, 19],
  heart: [20, 18],
  activity: [20, 18],
  filter: [16, 12],
  megaphone: [18, 12],
  chat: [20, 20],
  lock: [16, 18],
};

class CleanApkBuilder {
  constructor() {
    this.commands = [];
    this.nextId = 2;
    this.commands.push({ command: 'page.rename', params: { name: 'Mobile APK - 32 Refined Clean Screens' } });
    this.idx = 0;
  }

  coords() {
    const col = this.idx % 8;
    const row = Math.floor(this.idx / 8);
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

  // Clipped Circular Avatar
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

  roundedBanner(filePath, x, y, width, height, radius, parent) {
    const wrap = this.subFrame('BannerClip', x, y, width, height, { cornerRadius: radius, clip: true }, parent);
    this.image(filePath, 0, 0, width, height, wrap, 'BannerImg', 'fill');
    return wrap;
  }

  // Clean Logo Tile: matches card background cleanly without clunky borders
  logoTile(filePath, x, y, width, height, parent, radius = 12, padding = 6, fill = '#FFFFFF', stroke = null) {
    const props = { fill, cornerRadius: radius, clip: true };
    if (stroke) {
      props.stroke = stroke;
      props.strokeWeight = 1;
    }
    const box = this.subFrame('LogoTile', x, y, width, height, props, parent);
    this.image(filePath, padding, padding, width - padding * 2, height - padding * 2, box, 'Logo', 'fit');
    return box;
  }

  // Clean Minimal Status Bar matching specimen.html
  statusBar(parent, dark = false, centerText = 'Offline Ready') {
    const color = dark ? '#F8FAFC' : '#0F172A';
    this.text('10:30 AM', 20, 14, 70, { fontSize: 11, fontWeight: 700, fill: color }, parent);
    if (centerText) {
      this.ellipse('Dot', 224, 18, 6, 6, { fill: '#10B981' }, parent);
      this.text(centerText, 234, 14, 86, { fontSize: 10, fontWeight: 700, fill: '#10B981' }, parent);
    }
    this.rect('Battery', 324, 15, 18, 9, { fill: color, cornerRadius: 2.5 }, parent);
  }

  // Root Tab Header (NO redundant Back button — clean bold title like specimen.html A03/A06/A08!)
  rootHeader(parent, title, rightIcon = null, dark = false) {
    this.text(title, 18, 44, 220, { fontSize: 20, fontWeight: 800, fill: dark ? '#FFFFFF' : '#0F172A' }, parent);
    if (rightIcon) {
      this.ellipse('HdrActBG', 274, 41, 30, 30, { fill: '#FFEDD5' }, parent);
      this.vector(rightIcon, 281, 48, 16, '#EA580C', parent, 'HdrActIco');
    }
    // Subtle CampBuddy mark directly placed (no clunky white box)
    this.image(assets.cbIcon, 314, 41, 30, 30, parent, 'CBMark', 'fit');
  }

  // Drill-down Sub-screen Header (with Back chevron)
  subHeader(parent, title, dark = false) {
    this.ellipse('BackBtn', 16, 41, 30, 30, { fill: dark ? '#1E293B' : '#FFEDD5' }, parent);
    this.vector('chevronLeft', 22, 47, 18, dark ? '#FFFFFF' : '#EA580C', parent, 'BackIco');
    this.text(title, 56, 45, 240, { fontSize: 17, fontWeight: 800, fill: dark ? '#FFFFFF' : '#0F172A' }, parent);
    this.image(assets.cbIcon, 314, 41, 30, 30, parent, 'CBMark', 'fit');
  }

  // Branded Home / Picker Header (native borderless CampBuddy Wordmark on #FFF7ED!)
  brandHeader(parent, showStaffPill = true, showActions = true) {
    this.image(assets.cbWordmark, 16, 40, 128, 34, parent, 'CBWordmark', 'fit');
    if (showStaffPill) {
      const rx = showActions ? 198 : 254;
      const rw = showActions ? 70 : 90;
      this.rect('StaffPill', rx, 44, rw, 26, { fill: '#FFEDD5', cornerRadius: 13 }, parent);
      this.vector('crown', rx + 8, 50, 13, '#FF4D2D', parent, 'CrownIco');
      this.text(showActions ? 'Staff' : 'Staff Sign In', rx + 24, 50, rw - 28, { fontSize: 10, fontWeight: 700, fill: '#FF4D2D' }, parent);
    }
    if (showActions) {
      this.ellipse('GuideBtn', 276, 42, 30, 30, { fill: '#FFEDD5' }, parent);
      this.vector('help', 283, 49, 16, '#EA580C', parent, 'HelpIco');
      this.ellipse('SwitchBtn', 314, 42, 30, 30, { fill: '#FFEDD5' }, parent);
      this.vector('switchEvent', 321, 49, 16, '#EA580C', parent, 'SwitchIco');
    }
  }

  staffHeader(parent, title, backLabel = 'Events', rightText = null) {
    this.text(`< ${backLabel}`, 18, 46, 94, { fontSize: 11.5, fontWeight: 700, fill: '#94A3B8' }, parent);
    this.text(title, 102, 45, 168, { fontSize: 15, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, parent);
    if (rightText) {
      this.rect('RBtn', 274, 43, 70, 24, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 12 }, parent);
      this.text(rightText, 276, 48, 66, { fontSize: 9.5, fontWeight: 700, fill: '#F59E0B', textAlign: 'center' }, parent);
    } else {
      this.image(assets.cbIcon, 316, 41, 28, 28, parent, 'CBMark', 'fit');
    }
  }

  // Clean 5-Tab Bottom Navigation Bar
  bottomNav(parent, activeTab = 'home') {
    const y = 700;
    this.rect('NavBG', 0, y, 360, 60, { fill: '#FFFFFF' }, parent);
    this.line('NavDiv', 0, y, 360, { stroke: '#E2E8F0', strokeWeight: 1 }, parent);
    const tabs = [
      { key: 'home', label: 'Home', icon: 'home', x: 8 },
      { key: 'my-day', label: 'My Day', icon: 'calendar', x: 78 },
      { key: 'explore', label: 'Explore', icon: 'compass', x: 148 },
      { key: 'quest', label: 'Quests', icon: 'star', x: 218 },
      { key: 'camp-card', label: 'CampCard', icon: 'card', x: 288 },
    ];
    for (const t of tabs) {
      const on = t.key === activeTab;
      if (on) {
        this.rect('ActivePill', t.x + 8, y + 7, 48, 24, { fill: '#FFEDD5', cornerRadius: 12 }, parent);
      }
      this.vector(t.icon, t.x + 24, y + 11, 16, on ? '#EA580C' : '#64748B', parent, `NavIco_${t.key}`);
      this.text(t.label, t.x, y + 36, 64, { fontSize: 9.5, fontWeight: on ? 800 : 500, fill: on ? '#EA580C' : '#64748B', textAlign: 'center' }, parent);
    }
  }

  adminBottomNav(parent, activeTab = 'dashboard') {
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

  // Spacious 5-Tab Explore Bar (matches web/PWA explore.blade.php: People, Sponsors, Deals, Steals, Info!)
  exploreHeader(parent, activeKey = 'people') {
    this.rootHeader(parent, 'Explore');
    const tiles = [
      { key: 'people', label: 'People', icon: 'users', x: 16 },
      { key: 'sponsors', label: 'Sponsors', icon: 'heart', x: 83 },
      { key: 'deals', label: 'Deals', icon: 'gift', x: 150 },
      { key: 'steals', label: 'Steals', icon: 'sparkles', x: 217 },
      { key: 'info', label: 'Info', icon: 'ticket', x: 284 },
    ];
    for (const t of tiles) {
      const on = t.key === activeKey;
      this.rect(`ETile_${t.key}`, t.x, 84, 60, 52, {
        fill: on ? '#FFEDD5' : '#FFFFFF',
        stroke: on ? '#FB923C' : '#E2E8F0',
        strokeWeight: 1,
        cornerRadius: 12
      }, parent);
      this.vector(t.icon, t.x + 22, 92, 16, on ? '#EA580C' : '#475569', parent, `EIco_${t.key}`);
      this.text(t.label, t.x + 2, 115, 56, { fontSize: 9.5, fontWeight: on ? 800 : 600, fill: on ? '#EA580C' : '#0F172A', textAlign: 'center' }, parent);
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

  buildAll32Screens() {
    // =========================================================================
    // GROUP 1: LAUNCH, PICKER & ONBOARDING (M01 - M04)
    // =========================================================================

    // M01: Splash & App Update (Clean Dark Hero matching specimen A01!)
    {
      const f = this.screenFrame('M01 · Splash & App Update', 'CampBuddy Wordmark · Delhi Seal · Offline Sync · Optional Update', '#0F172A');
      this.statusBar(f, true, 'Offline Ready');

      // Slim, refined CampBuddy wordmark pill
      this.logoTile(assets.cbWordmark, 96, 86, 168, 44, f, 14, 6, '#FFFFFF');

      // WordCamp Delhi Roundel Seal inside soft dark-glass tile
      this.rect('SealGlass', 136, 168, 88, 88, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 24 }, f);
      this.logoTile(assets.delhiFavicon, 148, 180, 64, 64, f, 16, 4, '#FFFFFF');

      this.text('WordCamp Delhi', 30, 276, 300, { fontSize: 24, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.text('2026 Edition · Dec 12–13', 30, 308, 300, { fontSize: 12.5, fontWeight: 600, fill: '#F59E0B', textAlign: 'center' }, f);
      this.text('"Dil se Dilli, code se community"', 30, 332, 300, { fontSize: 11.5, fontWeight: 400, fill: '#94A3B8', textAlign: 'center' }, f);

      // Clean Offline Ready Status Card
      this.rect('SyncBox', 24, 396, 312, 82, { fill: '#1E293B', cornerRadius: 16 }, f);
      this.text('Works 100% Offline at DAIC', 40, 412, 280, { fontSize: 11.5, fontWeight: 700, fill: '#FFFFFF' }, f);
      this.rect('BarBG', 40, 436, 280, 6, { fill: '#0F172A', cornerRadius: 3 }, f);
      this.rect('BarFill', 40, 436, 280, 6, { fill: '#10B981', cornerRadius: 3 }, f);
      this.text('22 talks, 11 speakers & venue maps synced', 40, 452, 280, { fontSize: 10, fontWeight: 500, fill: '#10B981' }, f);

      // Subtle Optional Update Pill
      this.rect('UpdCard', 24, 496, 312, 62, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 14 }, f);
      this.vector('sparkles', 38, 518, 18, '#F59E0B', f, 'UpdIco');
      this.text('App update ready (v2.4.1)', 64, 510, 170, { fontSize: 11, fontWeight: 700, fill: '#FFFFFF' }, f);
      this.text('Faster badge scan & venue maps', 64, 528, 170, { fontSize: 9.5, fontWeight: 400, fill: '#94A3B8' }, f);
      this.rect('UpdBtn', 248, 513, 74, 28, { fill: '#D97706', cornerRadius: 14 }, f);
      this.text('Install', 250, 520, 70, { fontSize: 10, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);

      // Primary CTA
      this.rect('StartBtn', 24, 626, 312, 48, { fill: '#FF4D2D', cornerRadius: 24 }, f);
      this.text('Enter Experience', 24, 642, 312, { fontSize: 13, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);
    }

    // M02: WordCamp Picker (Clean, Airy Event Directory + Desktop Sync Row!)
    {
      const f = this.screenFrame('M02 · WordCamp Picker & Directory', 'Clean Wordmark · Country Filter · Featured Delhi Card · Sync Link');
      this.statusBar(f, false, 'Offline Ready');
      this.brandHeader(f, true, false);

      // Crisp Page Heading
      this.text('Choose your WordCamp', 18, 94, 324, { fontSize: 21, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('No sign-up needed · Schedules work offline at the venue.', 18, 124, 324, { fontSize: 10.5, fontWeight: 400, fill: '#64748B' }, f);

      // Country Filter Chips
      this.pill(f, 'All (26)', 16, 156, 68, true, '#0F172A');
      this.pill(f, 'India (4)', 90, 156, 74, false);
      this.pill(f, 'Greece (1)', 170, 156, 82, false);
      this.pill(f, 'Kenya (1)', 258, 156, 80, false);

      // Featured Card: WordCamp Delhi 2026 (Calm hairline highlight, crisp roundel seal)
      this.rect('EvtDelhi', 16, 200, 328, 156, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 18 }, f);
      this.logoTile(assets.delhiFavicon, 30, 216, 62, 62, f, 14, 2, '#FFFFFF', '#F1F5F9');
      this.rect('SavBadge', 238, 216, 92, 20, { fill: '#ECFDF5', cornerRadius: 10 }, f);
      this.text('Saved Offline', 240, 220, 88, { fontSize: 8.5, fontWeight: 700, fill: '#10B981', textAlign: 'center' }, f);
      this.text('WordCamp Delhi 2026', 104, 220, 140, { fontSize: 15, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Dec 12–13 · DAIC, New Delhi', 104, 260, 220, { fontSize: 10.5, fontWeight: 600, fill: '#EA580C' }, f);
      this.line('DelSep', 30, 294, 300, { stroke: '#F1F5F9', strokeWeight: 1 }, f);
      this.rect('GuideBtn', 30, 308, 138, 34, { fill: '#FFF7ED', stroke: '#FED7AA', strokeWeight: 1, cornerRadius: 17 }, f);
      this.text('First-Timer Guide', 32, 318, 134, { fontSize: 10, fontWeight: 700, fill: '#EA580C', textAlign: 'center' }, f);
      this.rect('OpenBtn', 178, 308, 152, 34, { fill: '#FF4D2D', cornerRadius: 17 }, f);
      this.text('Open Event →', 180, 318, 148, { fontSize: 10.5, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);

      // Event 2: Athens
      this.rect('EvtAthens', 16, 372, 328, 86, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('WordCamp Athens 2026', 30, 388, 200, { fontSize: 14, fontWeight: 700, fill: '#0F172A' }, f);
      this.text('Dec 11–12 · Megaron Athens, Greece', 30, 410, 210, { fontSize: 10, fontWeight: 500, fill: '#64748B' }, f);
      this.text('24 Sessions · Contributor Day', 30, 428, 200, { fontSize: 9.5, fontWeight: 600, fill: '#EA580C' }, f);
      this.rect('Open2', 256, 400, 74, 30, { fill: '#F8FAFC', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 15 }, f);
      this.text('Open', 258, 408, 70, { fontSize: 10, fontWeight: 700, fill: '#0F172A', textAlign: 'center' }, f);

      // Event 3: Pune
      this.rect('EvtPune', 16, 472, 328, 82, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('WordCamp Pune 2027', 30, 488, 200, { fontSize: 14, fontWeight: 700, fill: '#0F172A' }, f);
      this.text('Feb 2027 · Pune, India', 30, 510, 200, { fontSize: 10, fontWeight: 500, fill: '#64748B' }, f);
      this.rect('Open3', 256, 498, 74, 30, { fill: '#F8FAFC', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 15 }, f);
      this.text('Open', 258, 506, 70, { fontSize: 10, fontWeight: 700, fill: '#0F172A', textAlign: 'center' }, f);

      // Clean Single-Row Desktop Transfer Trigger (replaces bulky dark pin block!)
      this.rect('PinRow', 16, 574, 328, 66, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.rect('PinIcoBG', 28, 589, 36, 36, { fill: '#FFEDD5', cornerRadius: 10 }, f);
      this.vector('qr', 37, 598, 18, '#EA580C', f, 'PinIco');
      this.text('Move plan from desktop?', 74, 588, 180, { fontSize: 11.5, fontWeight: 700, fill: '#0F172A' }, f);
      this.text('Enter 6-digit sync code', 74, 608, 180, { fontSize: 9.5, fontWeight: 500, fill: '#64748B' }, f);
      this.rect('SyncPill', 260, 593, 70, 28, { fill: '#0F172A', cornerRadius: 14 }, f);
      this.text('Sync', 262, 600, 66, { fontSize: 10, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);

      this.text('Privacy Notice · Terms of Use · Data Controls', 16, 664, 328, { fontSize: 9.5, fontWeight: 600, fill: '#64748B', textAlign: 'center' }, f);
    }

    // M03: Onboarding Interests Wizard (Clean Hairline Borders, Airy Spacing!)
    {
      const f = this.screenFrame('M03 · Personalize Interests', '5-Step Onboarding · Soft Active Selection · Alert Opt-In');
      this.statusBar(f, false, 'Offline Ready');
      this.subHeader(f, 'Personalize');

      for (let i = 0; i < 5; i++) {
        this.rect(`Step_${i}`, 18 + i * 66, 86, 58, 5, { fill: i <= 1 ? '#FF4D2D' : '#E2E8F0', cornerRadius: 2.5 }, f);
      }

      this.text('What brings you to WordCamp Delhi?', 18, 108, 324, { fontSize: 19, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Step 2 of 5 · Saved on this device to highlight matching talks.', 18, 160, 324, { fontSize: 10.5, fontWeight: 400, fill: '#64748B' }, f);

      const opts = [
        { t: 'Building WordPress Products & AI', s: 'Plugin/Theme creators & agency founders', on: true, y: 198 },
        { t: 'SEO, Content & Growth Marketing', s: 'AI search, Local SEO & editorial tools', on: true, y: 276 },
        { t: 'First-Time Contributor & Open Source', s: 'Core, Documentation & Translation tables', on: false, y: 354 },
        { t: 'Networking & Finding Collaborators', s: 'Meet speakers, sponsors & peers', on: false, y: 432 },
      ];
      for (const o of opts) {
        this.rect(`Opt_${o.t}`, 16, o.y, 328, 66, {
          fill: o.on ? '#FFF7ED' : '#FFFFFF', stroke: o.on ? '#FB923C' : '#E2E8F0', strokeWeight: 1, cornerRadius: 16
        }, f);
        this.text(o.t, 32, o.y + 13, 256, { fontSize: 12, fontWeight: 700, fill: '#0F172A' }, f);
        this.text(o.s, 32, o.y + 35, 256, { fontSize: 10, fontWeight: 400, fill: '#64748B' }, f);
        this.ellipse('Circle', 306, o.y + 22, 22, 22, { fill: o.on ? '#FF4D2D' : '#F1F5F9' }, f);
        if (o.on) this.vector('check', 310, o.y + 26, 14, '#FFFFFF', f, 'Chk');
      }

      // Slim Alert Toggle Card
      this.rect('NotifBox', 16, 514, 328, 68, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('10-Min Session Reminders', 32, 528, 220, { fontSize: 12, fontWeight: 700, fill: '#0F172A' }, f);
      this.text('Gentle alert before starred talks start', 32, 548, 220, { fontSize: 9.5, fontWeight: 400, fill: '#64748B' }, f);
      this.toggle(f, 286, 536, true);

      this.rect('ContBtn', 16, 616, 220, 46, { fill: '#0F172A', cornerRadius: 23 }, f);
      this.text('Continue (2 selected)', 18, 631, 216, { fontSize: 11, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('SkipBtn', 246, 616, 98, 46, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 23 }, f);
      this.text('Skip', 248, 631, 94, { fontSize: 11, fontWeight: 600, fill: '#475569', textAlign: 'center' }, f);
    }

    // M04: Live Notice & Organizer Update Prompt (Cohesive Light Cards!)
    {
      const f = this.screenFrame('M04 · Live Notice & Alert Prompts', 'Organizer Notice Banner · +15m Delay Chip · Alert Consent · Guide Card');
      this.statusBar(f, false, 'Offline Ready');
      this.brandHeader(f, true, true);

      // Organizer Live Notice Banner (Soft Emerald Tint, hairline border)
      this.rect('NoticeBG', 16, 90, 328, 102, { fill: '#ECFDF5', stroke: '#A7F3D0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.ellipse('NoticeIcoBG', 30, 106, 36, 36, { fill: '#10B981' }, f);
      this.vector('megaphone', 39, 115, 18, '#FFFFFF', f, 'MegIco');
      this.text('FROM THE ORGANIZERS', 76, 102, 200, { fontSize: 9, fontWeight: 800, fill: '#047857' }, f);
      this.text('Lunch is Ready in Courtyard!', 76, 118, 214, { fontSize: 13.5, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Visit Dining Courtyard now. Elementor & WP Fusion booths have swag.', 76, 140, 240, { fontSize: 10, fontWeight: 400, fill: '#475569' }, f);
      this.text('✕', 318, 100, 16, { fontSize: 12, fontWeight: 600, fill: '#64748B' }, f);

      // Running Late Alert Strip
      this.rect('DelayBG', 16, 208, 328, 66, { fill: '#FEF3C7', stroke: '#FDE68A', strokeWeight: 1, cornerRadius: 14 }, f);
      this.vector('clock', 30, 231, 18, '#B45309', f, 'ClkIco');
      this.text('BHIM Auditorium Running +15m Late', 58, 222, 270, { fontSize: 11.5, fontWeight: 700, fill: '#92400E' }, f);
      this.text('Your Schedule & My Day times updated automatically.', 58, 242, 270, { fontSize: 9.5, fontWeight: 500, fill: '#78350F' }, f);

      // Live Ask Card
      this.rect('AskBG', 16, 290, 328, 152, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 18 }, f);
      this.rect('BellBG', 30, 306, 34, 34, { fill: '#FFEDD5', cornerRadius: 10 }, f);
      this.vector('bell', 38, 314, 18, '#EA580C', f, 'BellAsk');
      this.text('Get live organizer updates', 74, 314, 240, { fontSize: 13.5, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('"Keynote starting soon" or "Lunch served" — delivered cleanly even when the app is closed.', 30, 350, 300, { fontSize: 10, fontWeight: 400, fill: '#64748B' }, f);
      this.rect('TurnOn', 30, 392, 146, 34, { fill: '#FF4D2D', cornerRadius: 17 }, f);
      this.text('Turn On Alerts', 32, 402, 142, { fontSize: 10, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('NotNow', 186, 392, 146, 34, { fill: '#F8FAFC', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 17 }, f);
      this.text('Not Now', 188, 402, 142, { fontSize: 10, fontWeight: 600, fill: '#475569', textAlign: 'center' }, f);

      // Cohesive Light First-Timer Card (NO jarring dark block!)
      this.rect('FirstBG', 16, 458, 328, 114, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 18 }, f);
      this.rect('FtTag', 30, 472, 108, 20, { fill: '#FFEDD5', cornerRadius: 10 }, f);
      this.text('First-Timer Guide', 32, 476, 104, { fontSize: 8.5, fontWeight: 700, fill: '#EA580C', textAlign: 'center' }, f);
      this.text('First time at WordCamp?', 30, 500, 220, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('5-min guide to talks, Contributor Day & Delhi Metro.', 30, 522, 210, { fontSize: 10, fontWeight: 400, fill: '#64748B' }, f);
      this.rect('StartGuide', 248, 508, 82, 34, { fill: '#0F172A', cornerRadius: 17 }, f);
      this.text('Read →', 250, 518, 78, { fontSize: 10, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);

      this.bottomNav(f, 'home');
    }

    // =========================================================================
    // GROUP 2: CORE ATTENDEE EXPERIENCE (M05 - M11)
    // =========================================================================

    // M05: Tactile Live Home Dashboard (Clean Flagship matching specimen.html A02!)
    {
      const f = this.screenFrame('M05 · Tactile Live Home Dashboard', 'Flagship Home · Clock Hero · My People · Quick Grid · SOS Bar');
      this.statusBar(f, false, 'Offline Ready');
      this.brandHeader(f, true, true);

      // Signature Navy Hero Card
      this.rect('HeroBG', 16, 86, 328, 154, { fill: '#0F172A', cornerRadius: 20 }, f);
      this.text('GOOD MORNING, SUNIL', 32, 100, 200, { fontSize: 9.5, fontWeight: 700, fill: '#94A3B8' }, f);
      this.text('10:30 AM', 32, 116, 170, { fontSize: 28, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('Day 1 • Dec 12 • DAIC New Delhi', 32, 150, 220, { fontSize: 10.5, fontWeight: 500, fill: '#CBD5E1' }, f);

      // Crisp Delhi Roundel Favicon in soft white circle
      this.logoTile(assets.delhiFavicon, 276, 102, 50, 50, f, 25, 2, '#FFFFFF');

      // Glassmorphic Next Up Sub-Card
      this.rect('NextBox', 28, 182, 304, 44, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 14 }, f);
      this.text('Next: Local SEO in the AI Era', 40, 190, 206, { fontSize: 10.5, fontWeight: 700, fill: '#FFFFFF' }, f);
      this.text('Pankaj Kumar · BHIM Hall · in 15m', 40, 206, 206, { fontSize: 9.5, fontWeight: 500, fill: '#CBD5E1' }, f);
      this.rect('ViewBtn', 268, 192, 54, 24, { fill: '#D97706', cornerRadius: 12 }, f);
      this.text('View', 270, 197, 50, { fontSize: 9.5, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);

      // My People & Matches Strip
      this.text('My People & Matches', 16, 254, 180, { fontSize: 12.5, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('All peers →', 244, 255, 100, { fontSize: 10, fontWeight: 700, fill: '#EA580C', textAlign: 'right' }, f);

      this.ellipse('WaveCircle', 16, 278, 48, 48, { fill: '#FFEDD5', stroke: '#FB923C', strokeWeight: 1.5 }, f);
      this.vector('plus', 31, 293, 18, '#EA580C', f, 'WavePlus');
      this.text('Wave', 14, 330, 52, { fontSize: 9.5, fontWeight: 600, fill: '#0F172A', textAlign: 'center' }, f);

      const ppl = [
        { img: assets.pankaj, name: 'Pankaj' },
        { img: assets.radhika, name: 'Radhika' },
        { img: assets.amit, name: 'Amit' },
        { img: assets.shammi, name: 'Shammi' },
      ];
      for (let i = 0; i < ppl.length; i++) {
        const px = 80 + i * 66;
        this.avatar(ppl[i].img, px, 278, 48, f, '#FFFFFF', true);
        this.text(ppl[i].name, px - 2, 330, 52, { fontSize: 9.5, fontWeight: 600, fill: '#0F172A', textAlign: 'center' }, f);
      }

      // Quick Actions 2x3 Clean Grid
      this.text('Quick Actions', 16, 354, 160, { fontSize: 12.5, fontWeight: 800, fill: '#0F172A' }, f);
      const hubs = [
        { t: 'Schedule', s: '22 talks · 3 tracks', icon: 'calendar', x: 16, y: 376 },
        { t: 'My Day', s: '4 saved · 0 clashes', icon: 'clock', x: 184, y: 376 },
        { t: 'Sponsors & Steals', s: 'Elementor & 16 more', icon: 'gift', x: 16, y: 444 },
        { t: 'Contributor XP', s: '6 tables · Earn badges', icon: 'award', x: 184, y: 444 },
        { t: 'Venue Map', s: 'DAIC Janpath Rd', icon: 'mapPin', x: 16, y: 512 },
        { t: 'CampCard QR', s: 'Share digital badge', icon: 'qr', x: 184, y: 512 },
      ];
      for (const h of hubs) {
        this.rect(`Hub_${h.t}`, h.x, h.y, 160, 60, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 14 }, f);
        this.rect(`IcoBG_${h.t}`, h.x + 10, h.y + 12, 32, 32, { fill: '#FFEDD5', cornerRadius: 10 }, f);
        this.vector(h.icon, h.x + 18, h.y + 20, 16, '#EA580C', f, `HubIco_${h.t}`);
        this.text(h.t, h.x + 48, h.y + 14, 106, { fontSize: 11, fontWeight: 700, fill: '#0F172A' }, f);
        this.text(h.s, h.x + 48, h.y + 32, 106, { fontSize: 9, fontWeight: 400, fill: '#64748B' }, f);
      }

      // Clean Safety & SOS Hotline Bar matching specimen A02!
      this.rect('SOSBar', 16, 586, 328, 48, { fill: '#FFF5F5', stroke: '#FCA5A5', strokeWeight: 1, cornerRadius: 14 }, f);
      this.vector('shield', 28, 602, 16, '#DC2626', f, 'SosSh');
      this.text('Safety & Help Desk', 50, 594, 180, { fontSize: 11, fontWeight: 700, fill: '#DC2626' }, f);
      this.text('DAIC on-site medical & conduct support', 50, 610, 190, { fontSize: 9, fontWeight: 500, fill: '#64748B' }, f);
      this.rect('HelpPill', 252, 598, 80, 24, { fill: '#FF4D2D', cornerRadius: 12 }, f);
      this.text('Emergency', 254, 603, 76, { fontSize: 9, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);

      this.text('Privacy Policy · Terms · Data Controls', 16, 650, 328, { fontSize: 9.5, fontWeight: 600, fill: '#64748B', textAlign: 'center' }, f);
      this.bottomNav(f, 'home');
    }

    // M06: First-Timer Guide & Venue (Clean Airy Spacing!)
    {
      const f = this.screenFrame('M06 · First-Timer Guide & Venue', 'India Gate Hero · 2-Day Flow · Venue & Metro · FAQ Accordion');
      this.statusBar(f, false, 'Offline Ready');
      this.subHeader(f, 'First-Timer Guide');

      // Full-bleed rounded banner image + dark gradient scrim card
      this.roundedBanner(assets.indiaGate, 16, 84, 328, 114, 16, f);
      this.rect('Scrim', 16, 144, 328, 54, { fill: '#0F172A', cornerRadius: 14 }, f);
      this.text('Dil se Dilli — Welcome to WordCamp Delhi!', 28, 152, 304, { fontSize: 12, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('Dec 12–13 · Dr. Ambedkar Intl. Centre', 28, 172, 304, { fontSize: 9.5, fontWeight: 500, fill: '#F59E0B' }, f);

      this.rect('StepBox', 16, 212, 328, 102, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('2-Day Conference Flow', 30, 224, 240, { fontSize: 12, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('1. Dec 12 · Contributor Day (08:00 AM)', 30, 248, 290, { fontSize: 10, fontWeight: 700, fill: '#EA580C' }, f);
      this.text('2. Dec 13 · Main Conference (BHIM Hall)', 30, 268, 290, { fontSize: 10, fontWeight: 600, fill: '#0F172A' }, f);
      this.text('3. Dec 13 · Community After Party (WP Fusion)', 30, 288, 290, { fontSize: 10, fontWeight: 400, fill: '#64748B' }, f);

      this.rect('VenueBox', 16, 328, 328, 114, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Venue & Metro Directions', 30, 340, 240, { fontSize: 12, fontWeight: 800, fill: '#0F172A' }, f);
      this.vector('mapPin', 30, 364, 14, '#FF4D2D', f, 'PinIco');
      this.text('15, Janpath Rd, Windsor Place, New Delhi', 50, 364, 280, { fontSize: 10, fontWeight: 500, fill: '#475569' }, f);
      this.vector('compass', 30, 386, 14, '#0F172A', f, 'MetIco');
      this.text('Metro: Janpath (Violet) / Patel Chowk (Yellow)', 50, 386, 280, { fontSize: 10, fontWeight: 500, fill: '#475569' }, f);
      this.vector('phone', 30, 408, 14, '#DC2626', f, 'EmgIco');
      this.text('Helpdesk: delhi@wordcamp.org', 50, 408, 280, { fontSize: 10, fontWeight: 700, fill: '#DC2626' }, f);

      this.rect('FaqOpen', 16, 456, 328, 84, { fill: '#FFFFFF', stroke: '#FB923C', strokeWeight: 1, cornerRadius: 14 }, f);
      this.text('▾ What should I bring to Contributor Day?', 30, 468, 290, { fontSize: 11, fontWeight: 700, fill: '#0F172A' }, f);
      this.text('Bring your laptop, charger, and a WordPress.org account. Lunch & Wi-Fi are provided.', 30, 490, 296, { fontSize: 10, fontWeight: 400, fill: '#475569' }, f);

      this.rect('Faq2', 16, 554, 328, 42, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 12 }, f);
      this.text('▸ Is the venue wheelchair accessible?', 30, 567, 290, { fontSize: 11, fontWeight: 600, fill: '#0F172A' }, f);

      this.rect('Faq3', 16, 608, 328, 42, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 12 }, f);
      this.text('▸ Where are quiet & nursing rooms?', 30, 621, 290, { fontSize: 11, fontWeight: 600, fill: '#0F172A' }, f);

      this.bottomNav(f, 'home');
    }

    // M07: Full Schedule & Filters (Matches specimen.html A03 cleanliness!)
    {
      const f = this.screenFrame('M07 · Full Schedule & Filters', 'Root Schedule Header · Soft Search Pill · Day & Hall Chips · Speaker Avatars');
      this.statusBar(f, false, 'Offline Ready');
      this.rootHeader(f, 'Schedule', 'filter');

      // Day Segmented Switch (matching specimen.html A03)
      this.rect('DaySeg', 16, 86, 328, 36, { fill: '#E2E8F0', cornerRadius: 18 }, f);
      this.text('Day 1 (Dec 12)', 20, 96, 156, { fontSize: 10.5, fontWeight: 600, fill: '#475569', textAlign: 'center' }, f);
      this.rect('Day2On', 180, 90, 160, 28, { fill: '#FFFFFF', cornerRadius: 14 }, f);
      this.text('Day 2 (Dec 13)', 182, 97, 156, { fontSize: 10.5, fontWeight: 700, fill: '#0F172A', textAlign: 'center' }, f);

      // Crisp Soft Search Input (Hairline #E2E8F0 — NO harsh black outline!)
      this.rect('SearchBox', 16, 134, 328, 38, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 19 }, f);
      this.vector('search', 28, 144, 16, '#94A3B8', f, 'SrchIco');
      this.text('Search talks or speakers...', 52, 146, 270, { fontSize: 10.5, fontWeight: 400, fill: '#94A3B8' }, f);

      // Room Filter Chips
      this.pill(f, 'All Halls', 16, 184, 78, true, '#0F172A');
      this.pill(f, 'BHIM Auditorium', 100, 184, 114, false);
      this.pill(f, 'Workshop', 220, 184, 78, false);
      this.pill(f, 'Lounge', 304, 184, 54, false);

      // Spacious Session Cards with Speaker Avatars (matching specimen A03!)
      const talks = [
        { time: '11:00 AM · 20m', hall: 'BHIM Auditorium', live: true, title: 'Generative Engine Optimization (GEO) for WordPress', spk: 'Mayank Kumar · Search Expert', img: assets.abhay, starred: true, y: 226 },
        { time: '11:20 AM · 20m', hall: 'BHIM Auditorium', live: false, title: '10 Things to Boost Local SEO in the AI Era', spk: 'Pankaj Kumar · Pankaj Kumar SEO', img: assets.pankaj, starred: true, y: 374 },
        { time: '02:00 PM · 30m', hall: 'BHIM Auditorium', live: false, title: 'Cited but Not Recommended in AI Search', spk: 'Shammi Proothi · Digital Strategist', img: assets.shammi, starred: false, y: 522 },
      ];
      for (const s of talks) {
        this.rect(`Talk_${s.time}`, 16, s.y, 328, 134, {
          fill: '#FFFFFF', stroke: s.live ? '#FB923C' : '#E2E8F0', strokeWeight: 1, cornerRadius: 16
        }, f);
        this.rect(`TB_${s.time}`, 30, s.y + 14, 114, 22, { fill: s.live ? '#FFEDD5' : '#F8FAFC', cornerRadius: 11 }, f);
        this.text(s.live ? `Live · ${s.time}` : s.time, 32, s.y + 19, 110, { fontSize: 9, fontWeight: 700, fill: '#EA580C', textAlign: 'center' }, f);
        this.text(s.hall, 200, s.y + 19, 130, { fontSize: 9.5, fontWeight: 600, fill: '#64748B', textAlign: 'right' }, f);

        this.text(s.title, 30, s.y + 46, 296, { fontSize: 13.5, fontWeight: 700, fill: '#0F172A' }, f);

        this.avatar(s.img, 30, s.y + 92, 26, f, '#FFFFFF', false);
        this.text(s.spk, 64, s.y + 98, 220, { fontSize: 10.5, fontWeight: 600, fill: '#475569' }, f);
        this.vector('star', 308, s.y + 94, 20, s.starred ? '#D97706' : '#CBD5E1', f, `Star_${s.time}`);
      }

      this.bottomNav(f, 'my-day');
    }

    // M08: Session Detail & 10-Min Reminder (Airy, Clean Hierarchy!)
    {
      const f = this.screenFrame('M08 · Session Detail & Reminder', 'Speaker Card · Talk Overview · Saved Action · 10m Alert Toggle');
      this.statusBar(f, false, 'Offline Ready');
      this.subHeader(f, 'Session');

      // Speaker Bio Card
      this.rect('SpkBox', 16, 86, 328, 88, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.avatar(assets.pankaj, 30, 99, 60, f, '#FFFFFF', true);
      this.text('Pankaj Kumar', 104, 102, 224, { fontSize: 15, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Founder @ Pankaj Kumar SEO', 104, 122, 224, { fontSize: 11, fontWeight: 600, fill: '#EA580C' }, f);
      this.text('Local SEO & AI Visibility Strategist', 104, 140, 224, { fontSize: 10, fontWeight: 400, fill: '#64748B' }, f);

      // Session Card
      this.rect('TalkBox', 16, 188, 328, 250, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 18 }, f);
      this.rect('TimeP', 30, 202, 152, 22, { fill: '#FFEDD5', cornerRadius: 11 }, f);
      this.text('Sun, Dec 13 · 11:20 AM', 32, 207, 148, { fontSize: 9.5, fontWeight: 700, fill: '#EA580C', textAlign: 'center' }, f);
      this.text('BHIM Auditorium', 216, 207, 114, { fontSize: 10, fontWeight: 700, fill: '#10B981', textAlign: 'right' }, f);

      this.text('10 Things to Do to Boost Local SEO in the AI Era', 30, 236, 300, { fontSize: 16, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('AI search engines are changing how local businesses get discovered. Walk away with 10 practical schema, review, and entity strategies for WordPress.', 30, 290, 300, { fontSize: 11, fontWeight: 400, fill: '#475569' }, f);

      this.rect('StarredBtn', 30, 380, 146, 40, { fill: '#D97706', cornerRadius: 20 }, f);
      this.text('★ Saved to My Day', 32, 392, 142, { fontSize: 10, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('SlidesBtn', 186, 380, 144, 40, { fill: '#F8FAFC', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 20 }, f);
      this.text('Open Slides', 188, 392, 140, { fontSize: 10, fontWeight: 700, fill: '#0F172A', textAlign: 'center' }, f);

      // 10-Min Reminder Card
      this.rect('RemBox', 16, 454, 328, 66, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Remind me 10 mins before', 30, 468, 220, { fontSize: 12, fontWeight: 700, fill: '#0F172A' }, f);
      this.text('Works offline at venue', 30, 488, 220, { fontSize: 10, fontWeight: 500, fill: '#10B981' }, f);
      this.toggle(f, 286, 476, true);

      // Venue Room Pin
      this.rect('LocBox', 16, 534, 328, 66, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.vector('mapPin', 30, 558, 18, '#FF4D2D', f, 'LocPin');
      this.text('Ground Floor · BHIM Auditorium', 56, 548, 260, { fontSize: 11.5, fontWeight: 700, fill: '#0F172A' }, f);
      this.text('2 min walk from Main Registration Desk', 56, 568, 260, { fontSize: 10, fontWeight: 400, fill: '#64748B' }, f);

      this.bottomNav(f, 'my-day');
    }

    // M09: My Schedule & People Plan (De-crowded Clean Itinerary!)
    {
      const f = this.screenFrame('M09 · My Schedule & People Plan', 'Plan Progress · Conflict Banner · Attended Toggle · People to Meet');
      this.statusBar(f, false, 'Offline Ready');
      this.rootHeader(f, 'My Day');

      // Switch: Full vs My Schedule
      this.rect('MySeg', 16, 86, 328, 36, { fill: '#E2E8F0', cornerRadius: 18 }, f);
      this.text('Full schedule', 20, 96, 156, { fontSize: 10.5, fontWeight: 600, fill: '#475569', textAlign: 'center' }, f);
      this.rect('MyOn', 180, 90, 160, 28, { fill: '#FFFFFF', cornerRadius: 14 }, f);
      this.text('My schedule (5)', 182, 97, 156, { fontSize: 10.5, fontWeight: 700, fill: '#EA580C', textAlign: 'center' }, f);

      // Compact Navy Plan Summary Card
      this.rect('PlanSum', 16, 134, 328, 68, { fill: '#0F172A', cornerRadius: 16 }, f);
      this.text('Your Plan · 2 of 5 Done', 30, 146, 200, { fontSize: 12.5, fontWeight: 700, fill: '#FFFFFF' }, f);
      this.rect('AddCal', 238, 144, 92, 24, { fill: '#D97706', cornerRadius: 12 }, f);
      this.text('Add Calendar', 240, 149, 88, { fontSize: 9, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('PBarBG', 30, 178, 300, 6, { fill: '#334155', cornerRadius: 3 }, f);
      this.rect('PBarFill', 30, 178, 120, 6, { fill: '#10B981', cornerRadius: 3 }, f);

      // 4 Balanced Filter Chips
      this.pill(f, 'All (5)', 16, 214, 72, true, '#0F172A');
      this.pill(f, 'To do (3)', 94, 214, 80, false);
      this.pill(f, 'Done (2)', 180, 214, 78, false);
      this.pill(f, 'Hidden', 264, 214, 76, false);

      // Slim Conflict Alert
      this.rect('ClashCard', 16, 254, 328, 56, { fill: '#FFF5F5', stroke: '#FCA5A5', strokeWeight: 1, cornerRadius: 14 }, f);
      this.vector('alert', 28, 274, 16, '#DC2626', f, 'ClashIco');
      this.text('11:00 AM Overlap: GEO Talk vs Workshop', 50, 266, 210, { fontSize: 10, fontWeight: 700, fill: '#DC2626' }, f);
      this.text('Two saved talks run together', 50, 284, 200, { fontSize: 9, fontWeight: 400, fill: '#64748B' }, f);
      this.rect('RemBtn', 260, 269, 72, 26, { fill: '#FFFFFF', stroke: '#FCA5A5', strokeWeight: 1, cornerRadius: 13 }, f);
      this.text('Resolve', 262, 275, 68, { fontSize: 9.5, fontWeight: 700, fill: '#DC2626', textAlign: 'center' }, f);

      // Saved Session Card
      this.rect('SessSaved', 16, 324, 328, 100, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('10:00 AM · BHIM Auditorium', 30, 336, 220, { fontSize: 9.5, fontWeight: 700, fill: '#EA580C' }, f);
      this.text('From Feature to Ecosystem', 30, 355, 290, { fontSize: 13.5, fontWeight: 700, fill: '#0F172A' }, f);
      this.rect('AttBtn', 30, 384, 96, 28, { fill: '#ECFDF5', stroke: '#10B981', strokeWeight: 1, cornerRadius: 14 }, f);
      this.text('✓ Attended', 32, 391, 92, { fontSize: 9.5, fontWeight: 700, fill: '#10B981', textAlign: 'center' }, f);
      this.rect('MissBtn', 136, 384, 86, 28, { fill: '#F8FAFC', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 14 }, f);
      this.text('✗ Missed', 138, 391, 82, { fontSize: 9.5, fontWeight: 600, fill: '#64748B', textAlign: 'center' }, f);

      // People to Meet Card
      this.text('People to Meet', 16, 442, 200, { fontSize: 13, fontWeight: 800, fill: '#0F172A' }, f);
      this.rect('MeetCard', 16, 468, 328, 128, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.avatar(assets.radhika, 30, 482, 44, f, '#FFFFFF', true);
      this.text('Radhika Bhama', 84, 482, 180, { fontSize: 13.5, fontWeight: 700, fill: '#0F172A' }, f);
      this.text('01:30 PM · Dining Courtyard Coffee', 84, 502, 240, { fontSize: 10, fontWeight: 600, fill: '#EA580C' }, f);
      this.text('Note: Discuss WordPress product partnerships.', 30, 534, 300, { fontSize: 10, fontWeight: 400, fill: '#64748B' }, f);
      this.rect('MetBtn', 30, 558, 92, 28, { fill: '#ECFDF5', stroke: '#10B981', strokeWeight: 1, cornerRadius: 14 }, f);
      this.text('✓ Met', 32, 565, 88, { fontSize: 9.5, fontWeight: 700, fill: '#10B981', textAlign: 'center' }, f);
      this.rect('MissP', 130, 558, 92, 28, { fill: '#F8FAFC', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 14 }, f);
      this.text('Missed', 132, 565, 88, { fontSize: 9.5, fontWeight: 600, fill: '#64748B', textAlign: 'center' }, f);
      this.rect('SkipP', 230, 558, 92, 28, { fill: '#F8FAFC', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 14 }, f);
      this.text('Skip', 232, 565, 88, { fontSize: 9.5, fontWeight: 600, fill: '#64748B', textAlign: 'center' }, f);

      this.bottomNav(f, 'my-day');
    }

    // M10: Post-Event Memories & Data Choice (Clean Hairline Style!)
    {
      const f = this.screenFrame('M10 · Memories & Post-Event Choice', 'Event Recap · Photo Album · WordPress.tv · Data Retention');
      this.statusBar(f, false, 'Offline Ready');
      this.subHeader(f, 'Memories');

      this.rect('MemHdr', 16, 86, 328, 86, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.logoTile(assets.delhiFavicon, 30, 99, 60, 60, f, 14, 2, '#FFFFFF', '#F1F5F9');
      this.text('MEMORIES ARCHIVE', 104, 100, 150, { fontSize: 9, fontWeight: 800, fill: '#FF4D2D' }, f);
      this.text('WordCamp Delhi 2026', 104, 116, 220, { fontSize: 15, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Dec 12–13, 2026 · DAIC', 104, 138, 220, { fontSize: 10.5, fontWeight: 400, fill: '#64748B' }, f);

      this.rect('AlbumCard', 16, 186, 328, 66, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 14 }, f);
      this.vector('camera', 30, 208, 20, '#FF4D2D', f, 'CamIco');
      this.text('Official Photo Album', 62, 198, 220, { fontSize: 12.5, fontWeight: 700, fill: '#0F172A' }, f);
      this.text('Browse event photos — find yourself', 62, 218, 240, { fontSize: 10, fontWeight: 400, fill: '#64748B' }, f);
      this.vector('chevronRight', 318, 212, 16, '#94A3B8', f, 'Ar1');

      this.rect('WptvCard', 16, 264, 328, 66, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 14 }, f);
      this.vector('activity', 30, 286, 20, '#D97706', f, 'VidIco');
      this.text('Talks on WordPress.tv', 62, 276, 220, { fontSize: 12.5, fontWeight: 700, fill: '#0F172A' }, f);
      this.text('Watch recorded sessions anytime', 62, 296, 240, { fontSize: 10, fontWeight: 400, fill: '#64748B' }, f);
      this.vector('chevronRight', 318, 290, 16, '#94A3B8', f, 'Ar2');

      // Clean Hairline Data Retention Card
      this.rect('SweepBox', 16, 346, 328, 248, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 18 }, f);
      this.text('Keep or clear your event data?', 30, 362, 300, { fontSize: 13.5, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Scheduled cleanup on Dec 28, 2026', 30, 384, 280, { fontSize: 10, fontWeight: 600, fill: '#EA580C' }, f);

      this.rect('OptKeep', 30, 412, 300, 74, { fill: '#FFF7ED', stroke: '#FB923C', strokeWeight: 1, cornerRadius: 14 }, f);
      this.text('● Keep My People & CampCard', 44, 424, 260, { fontSize: 11.5, fontWeight: 700, fill: '#0F172A' }, f);
      this.text('Save scanned contacts & digital card for next WordCamps.', 44, 444, 270, { fontSize: 9.5, fontWeight: 400, fill: '#475569' }, f);

      this.rect('OptSweep', 30, 496, 300, 74, { fill: '#F8FAFC', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 14 }, f);
      this.text('○ Clean up automatically after 14 days', 44, 508, 260, { fontSize: 11.5, fontWeight: 600, fill: '#0F172A' }, f);
      this.text('Erase schedule bookmarks, waves & event notes.', 44, 528, 270, { fontSize: 9.5, fontWeight: 400, fill: '#64748B' }, f);

      this.rect('SaveChoice', 16, 610, 328, 46, { fill: '#0F172A', cornerRadius: 23 }, f);
      this.text('Save Preference', 16, 625, 328, { fontSize: 11.5, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);

      this.bottomNav(f, 'home');
    }

    // M11: Help Desk & Confidential Safety Report (Spacious, Zero Jamming!)
    {
      const f = this.screenFrame('M11 · Help Desk, Legal & Safety Report', 'Organizer Dialer · Support Rooms · Confidential Report Form');
      this.statusBar(f, false, 'Offline Ready');
      this.subHeader(f, 'Support & Safety');

      this.pill(f, 'Help Desk', 16, 86, 104, true, '#DC2626');
      this.pill(f, 'Privacy', 128, 86, 104, false);
      this.pill(f, 'Safety Code', 240, 86, 104, false);

      // 1-Tap Organizer Call Card
      this.rect('SOSBox', 16, 128, 328, 120, { fill: '#DC2626', cornerRadius: 18 }, f);
      this.vector('shield', 30, 144, 20, '#FFFFFF', f, 'ShIco');
      this.text('Need Immediate Help?', 58, 143, 240, { fontSize: 15, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('Connect directly with the DAIC Venue Safety & Info Desk anytime.', 30, 170, 300, { fontSize: 10, fontWeight: 400, fill: '#FEE2E2' }, f);
      this.rect('CallBtn', 30, 202, 300, 34, { fill: '#FFFFFF', cornerRadius: 17 }, f);
      this.vector('phone', 78, 211, 16, '#DC2626', f, 'PhBtn');
      this.text('Call Organizer Desk', 100, 211, 180, { fontSize: 10.5, fontWeight: 700, fill: '#DC2626' }, f);

      // Venue Support Rooms
      this.rect('FacBox', 16, 264, 328, 96, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('On-Site Rooms (DAIC)', 30, 278, 260, { fontSize: 12, fontWeight: 800, fill: '#0F172A' }, f);
      this.vector('heart', 30, 304, 14, '#DC2626', f, 'AidIco');
      this.text('First Aid Room: Ground Floor Lobby', 52, 303, 270, { fontSize: 10, fontWeight: 500, fill: '#475569' }, f);
      this.vector('bell', 30, 328, 14, '#D97706', f, 'QtIco');
      this.text('Quiet & Nursing Room: 1st Floor, Rm 104', 52, 327, 270, { fontSize: 10, fontWeight: 500, fill: '#475569' }, f);

      // Confidential Report Form
      this.rect('FormBox', 16, 376, 328, 290, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 18 }, f);
      this.text('File Confidential Report', 30, 392, 260, { fontSize: 13.5, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Safety, harassment, or accessibility concern', 30, 414, 290, { fontSize: 10, fontWeight: 500, fill: '#64748B' }, f);
      this.rect('TextBox', 30, 440, 300, 102, { fill: '#F8FAFC', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 12 }, f);
      this.text('Describe what happened and where...', 42, 454, 276, { fontSize: 10.5, fontWeight: 400, fill: '#94A3B8' }, f);
      this.text('☑ Keep my report anonymous', 30, 554, 260, { fontSize: 10.5, fontWeight: 600, fill: '#0F172A' }, f);
      this.rect('SubRep', 30, 586, 300, 42, { fill: '#0F172A', cornerRadius: 21 }, f);
      this.text('Submit Report', 32, 600, 296, { fontSize: 11, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.text('Works offline — sends when connected', 30, 638, 300, { fontSize: 9.5, fontWeight: 500, fill: '#10B981', textAlign: 'center' }, f);
    }

    // =========================================================================
    // GROUP 3: CAMPCARD, QR SCANNER & DISCOVERY (M12 - M16)
    // =========================================================================

    // M12: CampCard Digital Badge (Clean Focus matching specimen.html A06!)
    {
      const f = this.screenFrame('M12 · CampCard Carousel & Real QR', 'Style Carousel · Sharp Delhi Seal · Scannable QR · Share/Download');
      this.statusBar(f, false, 'Offline Ready');
      this.rootHeader(f, 'My CampCard', 'camera');

      // Compact Layout Switcher
      this.pill(f, 'Ticket', 16, 86, 56, false);
      this.pill(f, 'Classic', 76, 86, 62, true, '#0F172A');
      this.pill(f, 'Minimal', 142, 86, 62, false);
      this.pill(f, 'Bold', 208, 86, 48, false);
      this.pill(f, 'Split', 260, 86, 46, false);
      this.pill(f, 'Pass', 310, 86, 42, false);

      // Classic Navy Badge Card
      this.rect('CardFace', 22, 128, 316, 392, { fill: '#0F172A', stroke: '#D97706', strokeWeight: 1.5, cornerRadius: 22 }, f);
      this.logoTile(assets.delhiFavicon, 38, 144, 42, 42, f, 21, 2, '#FFFFFF');
      this.rect('RoleTag', 232, 150, 90, 22, { fill: '#33291A', stroke: '#D97706', strokeWeight: 1, cornerRadius: 11 }, f);
      this.text('SPEAKER', 234, 155, 86, { fontSize: 9, fontWeight: 800, fill: '#F59E0B', textAlign: 'center' }, f);

      this.avatar(assets.amit, 140, 162, 80, f, '#D97706', false);
      this.text('Amit Kumar Singh', 36, 252, 288, { fontSize: 18, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.text('Managing Partner · WPoets, Pune', 36, 278, 288, { fontSize: 10.5, fontWeight: 500, fill: '#CBD5E1', textAlign: 'center' }, f);

      this.rect('QrPlate', 110, 306, 140, 140, { fill: '#FFFFFF', cornerRadius: 14 }, f);
      this.image(assets.qrSample, 118, 314, 124, 124, f, 'RealQR');
      this.text('Hold near camera or scan to save contact', 36, 460, 288, { fontSize: 10, fontWeight: 500, fill: '#CBD5E1', textAlign: 'center' }, f);
      this.text('campbuddy.club · Offline QR', 36, 480, 288, { fontSize: 9, fontWeight: 700, fill: '#F59E0B', textAlign: 'center' }, f);

      // Share & Download Pill Row
      this.rect('SharePng', 22, 536, 152, 40, { fill: '#FF4D2D', cornerRadius: 20 }, f);
      this.vector('share', 52, 548, 16, '#FFFFFF', f, 'ShIco');
      this.text('Share', 74, 550, 60, { fontSize: 11, fontWeight: 700, fill: '#FFFFFF' }, f);

      this.rect('SavePng', 186, 536, 152, 40, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 20 }, f);
      this.vector('download', 210, 548, 16, '#0F172A', f, 'DlIco');
      this.text('Download', 232, 550, 74, { fontSize: 11, fontWeight: 700, fill: '#0F172A' }, f);

      // Clean Roster Verified Footer Bar
      this.rect('ClaimDone', 22, 594, 316, 46, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 14 }, f);
      this.vector('award', 36, 608, 18, '#10B981', f, 'VerIco');
      this.text('Verified on WordCamp Delhi Roster', 62, 609, 200, { fontSize: 10.5, fontWeight: 700, fill: '#0F172A' }, f);
      this.text('Edit', 284, 609, 40, { fontSize: 10.5, fontWeight: 700, fill: '#EA580C', textAlign: 'right' }, f);

      this.bottomNav(f, 'camp-card');
    }

    // M13: Edit CampCard & Claim Profile (Zero Dev Jargon, Calm Borders!)
    {
      const f = this.screenFrame('M13 · Edit CampCard & Claim Profile', 'Roster Profile Link · Card Fields · Interest Chips · Privacy Toggles');
      this.statusBar(f, false, 'Saved On Phone');
      this.subHeader(f, 'Edit CampCard');

      // Roster Claim Card (Clean soft peach fill, no loud borders)
      this.rect('ClaimBox', 16, 86, 328, 126, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Link Attendee Listing', 30, 100, 240, { fontSize: 12.5, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Match your name on the WordCamp Delhi roster:', 30, 120, 290, { fontSize: 9.5, fontWeight: 400, fill: '#64748B' }, f);
      this.rect('MatchRow', 30, 144, 300, 52, { fill: '#ECFDF5', stroke: '#A7F3D0', strokeWeight: 1, cornerRadius: 12 }, f);
      this.text('Amit Kumar Singh · Ticket #482', 44, 153, 210, { fontSize: 11, fontWeight: 700, fill: '#0F172A' }, f);
      this.text('Linked · Shown on attendees directory', 44, 171, 210, { fontSize: 9.5, fontWeight: 500, fill: '#047857' }, f);
      this.text('Change', 274, 162, 46, { fontSize: 10, fontWeight: 700, fill: '#047857', textAlign: 'right' }, f);

      // About You Form
      this.rect('FormBG', 16, 226, 328, 198, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('About You (On this phone)', 30, 240, 280, { fontSize: 12.5, fontWeight: 800, fill: '#0F172A' }, f);
      this.rect('F1', 30, 268, 300, 36, { fill: '#F8FAFC', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 10 }, f);
      this.text('Amit Kumar Singh', 42, 279, 270, { fontSize: 11, fontWeight: 600, fill: '#0F172A' }, f);
      this.rect('F2', 30, 312, 300, 36, { fill: '#F8FAFC', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 10 }, f);
      this.text('Managing Partner · WPoets', 42, 323, 270, { fontSize: 10.5, fontWeight: 500, fill: '#475569' }, f);

      this.text('Interests', 30, 360, 200, { fontSize: 10, fontWeight: 700, fill: '#475569' }, f);
      this.pill(f, 'AI & WordPress ×', 30, 382, 108, true, '#0F172A');
      this.pill(f, 'Enterprise ×', 144, 382, 86, true, '#0F172A');
      this.pill(f, '+ Add', 236, 382, 64, false);

      // Visibility Switches
      this.rect('VisBox', 16, 438, 328, 124, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Privacy Controls', 30, 452, 240, { fontSize: 12, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Show Role & Company on QR', 30, 480, 220, { fontSize: 10.5, fontWeight: 600, fill: '#0F172A' }, f);
      this.toggle(f, 286, 476, true);
      this.text('Show my CampCard in Attendees', 30, 514, 220, { fontSize: 10.5, fontWeight: 600, fill: '#0F172A' }, f);
      this.toggle(f, 286, 510, true);

      this.rect('SaveCard', 16, 582, 328, 46, { fill: '#FF4D2D', cornerRadius: 23 }, f);
      this.text('Save CampCard', 16, 597, 328, { fontSize: 12, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);

      this.bottomNav(f, 'camp-card');
    }

    // M14: QR Scanner & Detected Contact Sheet (Clean Camera UI matching specimen A07!)
    {
      const f = this.screenFrame('M14 · Fast QR Scanner & Contact Sheet', 'Live Camera Viewfinder · Flash Toggle · Detected Contact Sheet', '#000000');
      this.statusBar(f, true, 'Camera Active');
      this.text('< Back', 20, 48, 80, { fontSize: 13, fontWeight: 700, fill: '#FFFFFF' }, f);
      this.rect('Torch', 268, 44, 72, 26, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 13 }, f);
      this.vector('sparkles', 278, 50, 14, '#F59E0B', f, 'TrchIco');
      this.text('Flash', 296, 50, 40, { fontSize: 10, fontWeight: 700, fill: '#F59E0B' }, f);

      this.rect('FrameBox', 62, 118, 236, 236, { fill: '#0F172A', stroke: '#FF4D2D', strokeWeight: 2, cornerRadius: 24 }, f);
      this.rect('QrTarget', 102, 158, 156, 156, { fill: '#FFFFFF', cornerRadius: 14 }, f);
      this.image(assets.qrSample, 110, 166, 140, 140, f, 'TargetQR');
      this.line('Laser', 76, 236, 208, { stroke: '#FF4D2D', strokeWeight: 2.5 }, f);

      this.text('Align attendee CampCard QR inside frame', 30, 374, 300, { fontSize: 11, fontWeight: 500, fill: '#CBD5E1', textAlign: 'center' }, f);

      // Clean Bottom Contact Sheet
      this.rect('DetSheet', 0, 452, 360, 308, { fill: '#1E293B', cornerRadius: 26 }, f);
      this.rect('Handle', 156, 466, 48, 4, { fill: '#475569', cornerRadius: 2 }, f);
      this.avatar(assets.radhika, 24, 492, 58, f, '#10B981', true);
      this.text('Radhika Bhama', 94, 494, 220, { fontSize: 16, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('VP Product · Speaker · WordCamp Delhi', 94, 516, 240, { fontSize: 11, fontWeight: 500, fill: '#CBD5E1' }, f);
      this.text('Shared: LinkedIn, X · Saved locally on phone', 94, 536, 240, { fontSize: 9.5, fontWeight: 600, fill: '#10B981' }, f);

      this.rect('SaveBtn', 24, 578, 312, 46, { fill: '#D97706', cornerRadius: 23 }, f);
      this.text('Save Contact to My People', 24, 593, 312, { fontSize: 12, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('NextBtn', 24, 636, 312, 42, { fill: '#334155', cornerRadius: 21 }, f);
      this.text('Scan Another Code', 24, 650, 312, { fontSize: 11, fontWeight: 600, fill: '#FFFFFF', textAlign: 'center' }, f);
    }

    // M15: Explore -> People Discovery & Roster (Calm, Spacious Cards!)
    {
      const f = this.screenFrame('M15 · Explore: Discovery & Roster', '5 Explore Tabs · Join Banner · Discovery Match · Attendee Roster');
      this.statusBar(f, false, 'Offline Ready');
      this.exploreHeader(f, 'people');

      // Discovery Banner
      this.rect('JoinBanner', 16, 152, 328, 74, { fill: '#0F172A', cornerRadius: 16 }, f);
      this.text('Meet People Sharing Interests', 30, 165, 210, { fontSize: 12.5, fontWeight: 700, fill: '#FFFFFF' }, f);
      this.text('Create a private card & wave at matches.', 30, 186, 210, { fontSize: 9.5, fontWeight: 400, fill: '#CBD5E1' }, f);
      this.rect('JoinBtn', 252, 173, 78, 32, { fill: '#FF4D2D', cornerRadius: 16 }, f);
      this.text('Join', 254, 182, 74, { fontSize: 10.5, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);

      // Single Focus Match Card (Abhay Kulkarni)
      this.rect('M1', 16, 240, 328, 122, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.avatar(assets.abhay, 30, 254, 48, f, '#FFFFFF', true);
      this.text('Abhay Kulkarni', 88, 254, 166, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);
      this.rect('Match92', 260, 254, 70, 20, { fill: '#ECFDF5', cornerRadius: 10 }, f);
      this.text('92% Match', 262, 258, 66, { fontSize: 9, fontWeight: 700, fill: '#10B981', textAlign: 'center' }, f);
      this.text('n8n Ambassador · AI Workflows & Automation', 88, 274, 240, { fontSize: 10, fontWeight: 400, fill: '#64748B' }, f);
      this.rect('WaveB', 30, 316, 146, 32, { fill: '#FF4D2D', cornerRadius: 16 }, f);
      this.text('Wave Hello', 32, 325, 142, { fontSize: 10, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('MeetB', 184, 316, 146, 32, { fill: '#F8FAFC', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('+ Plan Meet', 186, 325, 142, { fontSize: 10, fontWeight: 600, fill: '#0F172A', textAlign: 'center' }, f);

      // Mutual Match Row (Compact)
      this.rect('M2', 16, 376, 328, 80, { fill: '#FFFFFF', stroke: '#A7F3D0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.avatar(assets.om, 30, 393, 46, f, '#10B981', true);
      this.text('Omprakash Asnani', 86, 392, 160, { fontSize: 13, fontWeight: 700, fill: '#0F172A' }, f);
      this.text('AI Workflows Lead · Mutual Match ✓', 86, 412, 180, { fontSize: 9.5, fontWeight: 600, fill: '#10B981' }, f);
      this.rect('ChatB', 262, 400, 68, 30, { fill: '#0F172A', cornerRadius: 15 }, f);
      this.text('Chat', 264, 408, 64, { fontSize: 10, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);

      // Attendee Roster Filter + Single Row
      this.text('Attendee Roster (420)', 16, 474, 200, { fontSize: 13, fontWeight: 800, fill: '#0F172A' }, f);
      this.pill(f, 'All', 16, 502, 50, true, '#0F172A');
      this.pill(f, 'Speakers 11', 72, 502, 84, false);
      this.pill(f, 'Organizers 13', 162, 502, 92, false);
      this.pill(f, 'Leads', 260, 502, 68, false);

      this.rect('Ros1', 16, 544, 328, 84, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.avatar(assets.shreya, 30, 563, 46, f, '#FFFFFF', false);
      this.text('Shreya Agarwal', 86, 560, 160, { fontSize: 13, fontWeight: 700, fill: '#0F172A' }, f);
      this.rect('BadgeSpk', 86, 582, 74, 20, { fill: '#FEF3C7', cornerRadius: 10 }, f);
      this.text('Table Lead', 88, 586, 70, { fontSize: 8.5, fontWeight: 700, fill: '#B45309', textAlign: 'center' }, f);
      this.rect('MeetRos', 264, 571, 66, 30, { fill: '#FFEDD5', cornerRadius: 15 }, f);
      this.text('+ Meet', 266, 579, 62, { fontSize: 10, fontWeight: 700, fill: '#EA580C', textAlign: 'center' }, f);

      this.bottomNav(f, 'explore');
    }

    // M16: Mutual Match Chat & Meet Planner (Replaced 3-stacked cards with 1 focused Chat & Meet screen!)
    {
      const f = this.screenFrame('M16 · Mutual Match Chat & Meet', 'Focused Light Chat · Suggested Meet Card · Quick Message Composer');
      this.statusBar(f, false, 'Mutual Match');

      // Custom Chat TopBar with Om's Avatar & Meet Action
      this.ellipse('BackBtn', 16, 40, 30, 30, { fill: '#FFEDD5' }, f);
      this.vector('chevronLeft', 22, 46, 18, '#EA580C', f, 'BackIco');
      this.avatar(assets.om, 54, 38, 34, f, '#10B981', true);
      this.text('Omprakash Asnani', 96, 39, 160, { fontSize: 13, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Mutual match · AI Workflows', 96, 56, 160, { fontSize: 9.5, fontWeight: 600, fill: '#10B981' }, f);
      this.rect('PlanMeetHdr', 264, 42, 80, 26, { fill: '#FFEDD5', cornerRadius: 13 }, f);
      this.text('Plan Meet', 266, 48, 76, { fontSize: 9.5, fontWeight: 700, fill: '#EA580C', textAlign: 'center' }, f);

      this.line('ChatDiv', 0, 82, 360, { stroke: '#E2E8F0', strokeWeight: 1 }, f);

      // Privacy Intro Pill
      this.rect('PrivHint', 36, 98, 288, 30, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 15 }, f);
      this.text('Both of you waved · Up to 3 short intro messages', 40, 107, 280, { fontSize: 9, fontWeight: 500, fill: '#64748B', textAlign: 'center' }, f);

      // Incoming Message Bubble (Light Clean White)
      this.rect('Msg1', 16, 148, 244, 62, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Hey! Saw we share WordPress + n8n interests. Catch up after the AI panel?', 30, 160, 216, { fontSize: 11, fontWeight: 500, fill: '#0F172A' }, f);
      this.text('11:04 AM', 30, 194, 80, { fontSize: 8.5, fontWeight: 500, fill: '#94A3B8' }, f);

      // Outgoing Message Bubble (Warm Coral)
      this.rect('Msg2', 94, 226, 250, 62, { fill: '#FF4D2D', cornerRadius: 16 }, f);
      this.text('Definitely! Let’s grab coffee at the Dining Courtyard around 1:30 PM.', 108, 238, 222, { fontSize: 11, fontWeight: 500, fill: '#FFFFFF' }, f);
      this.text('11:06 AM · Sent', 238, 272, 92, { fontSize: 8.5, fontWeight: 500, fill: '#FEE2E2', textAlign: 'right' }, f);

      // Clean Suggested Meet Card inside Chat (replaces separate stacked MeetSheet!)
      this.rect('MeetCardChat', 16, 314, 328, 134, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 18 }, f);
      this.rect('MCIco', 30, 328, 34, 34, { fill: '#FFEDD5', cornerRadius: 10 }, f);
      this.vector('calendar', 39, 337, 16, '#EA580C', f, 'MCCal');
      this.text('Add Meet to My Day', 74, 328, 200, { fontSize: 12.5, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('01:30 PM · Dining Courtyard Coffee', 74, 347, 230, { fontSize: 10, fontWeight: 600, fill: '#EA580C' }, f);
      this.text('Saved on this phone only.', 30, 376, 280, { fontSize: 9.5, fontWeight: 400, fill: '#64748B' }, f);
      this.rect('AddPlanB', 30, 400, 146, 32, { fill: '#0F172A', cornerRadius: 16 }, f);
      this.text('Save to My Schedule', 32, 409, 142, { fontSize: 9.5, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('SkipMB', 184, 400, 146, 32, { fill: '#F8FAFC', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Edit Time', 186, 409, 142, { fontSize: 9.5, fontWeight: 600, fill: '#475569', textAlign: 'center' }, f);

      // Pinned Composer Bar above Bottom Nav
      this.rect('CompBar', 16, 636, 328, 48, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 24 }, f);
      this.text('Write a short message...', 34, 653, 210, { fontSize: 10.5, fontWeight: 400, fill: '#94A3B8' }, f);
      this.rect('SendC', 274, 643, 62, 34, { fill: '#10B981', cornerRadius: 17 }, f);
      this.text('Send', 276, 653, 58, { fontSize: 10, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);

      this.bottomNav(f, 'explore');
    }

    // =========================================================================
    // GROUP 4: EXPLORE TABS & CONTRIBUTOR EXPERIENCE (M17 - M22)
    // =========================================================================

    // M17: Explore -> Sponsors Tiered Grid (Seamless Logo Blending, Zero Nested Box!)
    {
      const f = this.screenFrame('M17 · Explore: Sponsors Tiered Grid', 'Seamless Sponsor Logos · Bronze, Startup & After Party Tiers');
      this.statusBar(f, false, 'Offline Ready');
      this.exploreHeader(f, 'sponsors');

      this.text('Visit sponsor booths for live demos, swag & careers.', 16, 152, 328, { fontSize: 10.5, fontWeight: 400, fill: '#64748B' }, f);

      // Bronze & Platform Sponsors (#FDFBF7 blends seamlessly with webp backgrounds!)
      this.text('BRONZE & PLATFORM SPONSORS', 16, 184, 260, { fontSize: 9.5, fontWeight: 800, fill: '#EA580C' }, f);
      this.logoTile(assets.sponsor_elementor, 16, 206, 158, 74, f, 14, 14, '#FDFBF7', '#E2E8F0');
      this.logoTile(assets.sponsor_nexgi, 186, 206, 158, 74, f, 14, 14, '#FDFBF7', '#E2E8F0');
      this.logoTile(assets.sponsor_cookieadmin, 16, 292, 158, 74, f, 14, 14, '#FDFBF7', '#E2E8F0');

      // Startup & Community
      this.text('STARTUP & COMMUNITY SPONSORS', 16, 386, 260, { fontSize: 9.5, fontWeight: 800, fill: '#0F172A' }, f);
      this.logoTile(assets.sponsor_housewise, 16, 408, 158, 74, f, 14, 14, '#FDFBF7', '#E2E8F0');
      this.logoTile(assets.sponsor_zuptek, 186, 408, 158, 74, f, 14, 14, '#FDFBF7', '#E2E8F0');

      // After Party Sponsor
      this.text('AFTER PARTY SPONSOR', 16, 502, 260, { fontSize: 9.5, fontWeight: 800, fill: '#D97706' }, f);
      this.logoTile(assets.sponsor_wpfusion, 16, 524, 328, 86, f, 16, 18, '#FDFBF7', '#E2E8F0');

      this.bottomNav(f, 'explore');
    }

    // M18: Explore -> Deals & Clean White Lead Sheet (Harmonious Modal Sheet!)
    {
      const f = this.screenFrame('M18 · Explore: Deals & Lead Sheet', 'Tear-Line Coupon Card · Clean Elevated Lead Form Bottom Sheet', '#EDE8DF');
      this.statusBar(f, false, 'Offline Ready');
      this.exploreHeader(f, 'deals');

      // Dimmed Top Coupon Card
      this.rect('Deal1', 16, 152, 328, 180, { fill: '#F8FAFC', stroke: '#CBD5E1', strokeWeight: 1, cornerRadius: 16 }, f);
      this.logoTile(assets.sponsor_elementor, 28, 164, 48, 48, f, 10, 6, '#FDFBF7', '#E2E8F0');
      this.text('Elementor Ltd', 86, 168, 150, { fontSize: 12.5, fontWeight: 700, fill: '#475569' }, f);
      this.text('3 Months Elementor One Free', 28, 224, 280, { fontSize: 13, fontWeight: 700, fill: '#475569' }, f);
      this.line('TearLine', 28, 260, 304, { stroke: '#CBD5E1', strokeWeight: 1.5, dashes: [6, 4] }, f);
      this.rect('CodeBox', 28, 276, 304, 34, { fill: '#EFE6D8', cornerRadius: 10 }, f);
      this.text('Code: WCDELHI26', 40, 286, 200, { fontSize: 11, fontWeight: 700, fill: '#475569' }, f);

      // Clean Elevated White Bottom Sheet (NO jarring dark block or dev jargon!)
      this.rect('LeadSheet', 0, 340, 360, 360, { fill: '#FFFFFF', cornerRadius: 26 }, f);
      this.rect('Grab', 156, 354, 48, 4, { fill: '#CBD5E1', cornerRadius: 2 }, f);

      this.logoTile(assets.sponsor_wpfusion, 24, 376, 56, 38, f, 10, 6, '#FDFBF7', '#E2E8F0');
      this.text('Claim 30% Off · WP Fusion', 90, 376, 240, { fontSize: 15, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Share your details to unlock this deal:', 90, 398, 240, { fontSize: 10, fontWeight: 400, fill: '#64748B' }, f);

      this.text('Work Email *', 24, 434, 200, { fontSize: 10, fontWeight: 700, fill: '#475569' }, f);
      this.rect('LF1', 24, 452, 312, 38, { fill: '#F8FAFC', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 10 }, f);
      this.text('sunil@wpsimplified.com', 38, 464, 270, { fontSize: 11, fontWeight: 600, fill: '#0F172A' }, f);

      this.text('Agency Team Size', 24, 502, 200, { fontSize: 10, fontWeight: 700, fill: '#475569' }, f);
      this.rect('LF2', 24, 520, 312, 38, { fill: '#F8FAFC', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 10 }, f);
      this.text('10–25 WordPress Builders', 38, 532, 270, { fontSize: 11, fontWeight: 500, fill: '#0F172A' }, f);

      this.text('Shared only with WP Fusion to issue your coupon.', 24, 572, 312, { fontSize: 9.5, fontWeight: 400, fill: '#64748B' }, f);

      this.rect('SubLead', 24, 602, 198, 42, { fill: '#FF4D2D', cornerRadius: 21 }, f);
      this.text('Send & Open Deal →', 26, 615, 194, { fontSize: 10.5, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('CanLead', 232, 602, 104, 42, { fill: '#F8FAFC', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 21 }, f);
      this.text('Cancel', 234, 615, 100, { fontSize: 10.5, fontWeight: 600, fill: '#475569', textAlign: 'center' }, f);

      this.bottomNav(f, 'explore');
    }

    // M19: Explore -> Free Steals & Clean Sheet
    {
      const f = this.screenFrame('M19 · Explore: Free Steals & Sheet', 'Free Tools List · Made by Attendee Badge · Tool Sheet', '#EDE8DF');
      this.statusBar(f, false, 'Offline Ready');
      this.exploreHeader(f, 'steals');

      this.text('Free WordPress plugins picked by CampBuddy.', 16, 150, 328, { fontSize: 10, fontWeight: 500, fill: '#64748B' }, f);

      const steals = [
        { img: assets.sponsor_cookieadmin, name: 'CookieAdmin DPDP Shield', maker: 'by CookieAdmin Team', feat: true, y: 176 },
        { img: assets.sponsor_wpfusion, name: 'WP Fusion Lite Sync', maker: 'by VeryGoodPlugins', feat: false, y: 254 },
      ];
      for (const s of steals) {
        this.rect(`St_${s.name}`, 16, s.y, 328, 68, { fill: '#F8FAFC', stroke: '#CBD5E1', strokeWeight: 1, cornerRadius: 14 }, f);
        this.logoTile(s.img, 26, s.y + 10, 48, 48, f, 10, 6, '#FDFBF7', '#E2E8F0');
        this.text(s.name, 84, s.y + 15, 166, { fontSize: 12, fontWeight: 700, fill: '#475569' }, f);
        this.text(s.maker, 84, s.y + 35, 166, { fontSize: 9.5, fontWeight: 400, fill: '#64748B' }, f);
        if (s.feat) {
          this.rect('Feat', 252, s.y + 23, 66, 20, { fill: '#FFF7ED', stroke: '#FB923C', strokeWeight: 1, cornerRadius: 10 }, f);
          this.text('Featured', 254, s.y + 27, 62, { fontSize: 8.5, fontWeight: 700, fill: '#EA580C', textAlign: 'center' }, f);
        }
      }

      // Elevated Bottom Sheet
      this.rect('StealSheet', 0, 340, 360, 360, { fill: '#FFFFFF', cornerRadius: 26 }, f);
      this.rect('Grab', 156, 354, 48, 4, { fill: '#CBD5E1', cornerRadius: 2 }, f);
      this.logoTile(assets.sponsor_cookieadmin, 24, 376, 62, 62, f, 14, 8, '#FDFBF7', '#E2E8F0');
      this.text('CookieAdmin DPDP Shield', 98, 382, 236, { fontSize: 16, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('by CookieAdmin Team', 98, 406, 236, { fontSize: 11, fontWeight: 500, fill: '#64748B' }, f);

      this.rect('MadeHere', 24, 456, 266, 26, { fill: '#ECFDF5', cornerRadius: 13 }, f);
      this.vector('mapPin', 34, 462, 14, '#10B981', f, 'PinMade');
      this.text('Made by someone attending Delhi 2026', 54, 463, 224, { fontSize: 9.5, fontWeight: 700, fill: '#047857' }, f);

      this.text('Lightweight consent banner & privacy preference manager built for Indian & global WordPress websites.', 24, 498, 312, { fontSize: 11, fontWeight: 400, fill: '#475569' }, f);

      this.rect('GetFree', 24, 574, 312, 46, { fill: '#FF4D2D', cornerRadius: 23 }, f);
      this.text('Get It Free →', 24, 589, 312, { fontSize: 12, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.text('Opens wordpress.org/plugins', 24, 632, 312, { fontSize: 10, fontWeight: 500, fill: '#64748B', textAlign: 'center' }, f);

      this.bottomNav(f, 'explore');
    }

    // M20: Contributor Day & Table Leads (Cohesive Clean Light UI!)
    {
      const f = this.screenFrame('M20 · Contributor Day & Table Leads', 'Light Header Card · Attendance Toggle · Conversation Starter · Table Leads');
      this.statusBar(f, false, 'Offline Ready');
      this.subHeader(f, 'Contributor Day');

      // Cohesive Light Intro Card
      this.rect('ContHero', 16, 86, 328, 86, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Where could you help?', 30, 100, 260, { fontSize: 16, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Writers, designers, translators & developers all have a table — beginners welcome.', 30, 124, 300, { fontSize: 10, fontWeight: 400, fill: '#64748B' }, f);

      // Attendance Selector
      this.rect('GoingBox', 16, 186, 328, 82, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Going to Contributor Day?', 30, 198, 240, { fontSize: 12, fontWeight: 700, fill: '#0F172A' }, f);
      this.pill(f, 'Yes', 30, 226, 66, true, '#0F172A');
      this.pill(f, 'Not sure yet', 104, 226, 100, false);
      this.pill(f, 'Not this time', 212, 226, 100, false);

      // Warm Soft Opener Card
      this.rect('OpenLine', 16, 282, 328, 66, { fill: '#FFF7ED', stroke: '#FED7AA', strokeWeight: 1, cornerRadius: 14 }, f);
      this.text('Conversation starter:', 30, 293, 200, { fontSize: 9.5, fontWeight: 700, fill: '#EA580C' }, f);
      this.text('"Hi, this is my first Contributor Day. Can you help me get started?"', 30, 311, 300, { fontSize: 10.5, fontWeight: 600, fill: '#0F172A' }, f);

      // Core & Gutenberg Table Card
      this.rect('TableBox', 16, 362, 328, 178, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Core & Gutenberg Table', 30, 376, 196, { fontSize: 13.5, fontWeight: 800, fill: '#0F172A' }, f);
      this.pill(f, 'Hall B · Table 1', 238, 372, 92, false);
      this.text('Block theme testing & beginner core patches.', 30, 400, 300, { fontSize: 10, fontWeight: 400, fill: '#64748B' }, f);

      this.line('TblSep', 30, 426, 300, { stroke: '#F1F5F9', strokeWeight: 1 }, f);
      this.avatar(assets.amit, 30, 438, 44, f, '#FFFFFF', true);
      this.text('Amit Kumar Singh', 84, 438, 148, { fontSize: 12.5, fontWeight: 700, fill: '#0F172A' }, f);
      this.rect('TLBadge', 240, 438, 90, 20, { fill: '#FEF3C7', cornerRadius: 10 }, f);
      this.text('Table Lead', 242, 442, 86, { fontSize: 8.5, fontWeight: 700, fill: '#B45309', textAlign: 'center' }, f);
      this.text('Managing Partner · WPoets', 84, 458, 200, { fontSize: 9.5, fontWeight: 500, fill: '#EA580C' }, f);
      this.pill(f, 'Speaker Profile ↗', 30, 496, 114, false);
      this.pill(f, 'LinkedIn ↗', 152, 496, 86, false);

      // Table 2 Row
      this.rect('DocTable', 16, 554, 328, 70, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.avatar(assets.shreya, 30, 568, 42, f, '#FFFFFF', false);
      this.text('Documentation & HelpHub', 82, 568, 220, { fontSize: 12.5, fontWeight: 700, fill: '#0F172A' }, f);
      this.text('Lead: Shreya Agarwal · Table 2 (No coding)', 82, 588, 240, { fontSize: 9.5, fontWeight: 500, fill: '#64748B' }, f);

      this.bottomNav(f, 'quest');
    }

    // M21: Quests, Checklist & Badges (Matches specimen.html A09 Hero XP Card!)
    {
      const f = this.screenFrame('M21 · Quests, Checklist & Badges', 'Navy Level Hero · Checklist · Unlocked Icon Badges');
      this.statusBar(f, false, 'Offline Ready');
      this.rootHeader(f, 'Quests');

      // Navy XP Progress Hero matching specimen.html A09
      this.rect('XPHero', 16, 86, 328, 86, { fill: '#0F172A', cornerRadius: 18 }, f);
      this.text('Level 2 Contributor', 32, 100, 180, { fontSize: 12.5, fontWeight: 800, fill: '#F59E0B' }, f);
      this.text('450 / 600 XP · 3 of 5 done', 200, 102, 130, { fontSize: 10, fontWeight: 600, fill: '#CBD5E1', textAlign: 'right' }, f);
      this.rect('QBarBG', 32, 128, 296, 6, { fill: '#334155', cornerRadius: 3 }, f);
      this.rect('QBarFill', 32, 128, 210, 6, { fill: '#F59E0B', cornerRadius: 3 }, f);
      this.text('Next unlock: "Community Pioneer" badge', 32, 144, 296, { fontSize: 9.5, fontWeight: 400, fill: '#94A3B8' }, f);

      // Action Quest Card
      this.rect('Q1', 16, 186, 328, 96, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.rect('QIco1', 30, 198, 38, 38, { fill: '#FFEDD5', cornerRadius: 10 }, f);
      this.vector('card', 39, 207, 20, '#EA580C', f, 'Q1Ico');
      this.text('Build & Share Your CampCard', 78, 198, 230, { fontSize: 12.5, fontWeight: 700, fill: '#0F172A' }, f);
      this.text('Generate QR badge & scan 3 attendees', 78, 218, 230, { fontSize: 9.5, fontWeight: 400, fill: '#64748B' }, f);
      this.rect('Jump1', 78, 244, 114, 26, { fill: '#F8FAFC', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 13 }, f);
      this.text('Open CampCard', 80, 250, 110, { fontSize: 9, fontWeight: 700, fill: '#0F172A', textAlign: 'center' }, f);
      this.rect('Done1', 200, 244, 88, 26, { fill: '#ECFDF5', stroke: '#A7F3D0', strokeWeight: 1, cornerRadius: 13 }, f);
      this.text('✓ Done', 202, 250, 84, { fontSize: 9.5, fontWeight: 700, fill: '#10B981', textAlign: 'center' }, f);

      // Clean Checklist
      this.text('Checklist', 16, 298, 160, { fontSize: 12.5, fontWeight: 800, fill: '#0F172A' }, f);
      const checks = [
        { t: 'Visit 3 Sponsor Booths', s: 'Elementor, WP Fusion & CookieAdmin', on: true, y: 324 },
        { t: 'Star 3 Sessions in My Day', s: 'Build your personal timetable', on: true, y: 386 },
        { t: 'Say Hi at a Contributor Table', s: 'Core, Docs, or Polyglots on Dec 12', on: false, y: 448 },
      ];
      for (const c of checks) {
        this.rect(`Chk_${c.t}`, 16, c.y, 328, 54, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 14 }, f);
        this.rect(`Box_${c.t}`, 30, c.y + 18, 18, 18, { fill: c.on ? '#FF4D2D' : '#FFFFFF', stroke: c.on ? '#FF4D2D' : '#CBD5E1', strokeWeight: 1.5, cornerRadius: 5 }, f);
        if (c.on) this.vector('check', 32, c.y + 20, 14, '#FFFFFF', f, `CkV_${c.t}`);
        this.text(c.t, 58, c.y + 10, 260, { fontSize: 11.5, fontWeight: 700, fill: '#0F172A' }, f);
        this.text(c.s, 58, c.y + 30, 260, { fontSize: 9.5, fontWeight: 400, fill: '#64748B' }, f);
      }

      // Badges Row
      this.text('Badges', 16, 516, 200, { fontSize: 12.5, fontWeight: 800, fill: '#0F172A' }, f);
      const badges = [
        { name: 'Early Bird', ico: 'clock', unlocked: true },
        { name: 'Core Helper', ico: 'wrench', unlocked: true },
        { name: 'Swag Hunter', ico: 'gift', unlocked: true },
        { name: 'Connector', ico: 'users', unlocked: false },
      ];
      for (let i = 0; i < 4; i++) {
        const bx = 16 + i * 84;
        const b = badges[i];
        this.rect(`Bdg_${i}`, bx, 542, 76, 82, {
          fill: b.unlocked ? '#FFFFFF' : '#F8FAFC', stroke: b.unlocked ? '#FDE68A' : '#E2E8F0', strokeWeight: 1, cornerRadius: 14
        }, f);
        this.ellipse(`BIcoBG_${i}`, bx + 20, 554, 36, 36, { fill: b.unlocked ? '#FEF3C7' : '#E2E8F0' }, f);
        this.vector(b.ico, bx + 29, 563, 18, b.unlocked ? '#D97706' : '#94A3B8', f, `BIco_${i}`);
        this.text(b.name, bx + 4, 598, 68, { fontSize: 9, fontWeight: 600, fill: b.unlocked ? '#0F172A' : '#94A3B8', textAlign: 'center' }, f);
      }

      this.bottomNav(f, 'quest');
    }

    // M22: Explore -> Event Info & Data Controls
    {
      const f = this.screenFrame('M22 · Event Info & Data Controls', 'Event Details · Venue & Wi-Fi Copy · Notification Toggles · Data Controls');
      this.statusBar(f, false, 'Offline Ready');
      this.exploreHeader(f, 'info');

      this.rect('InfoHdr', 16, 152, 328, 72, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.logoTile(assets.delhiFavicon, 30, 163, 48, 48, f, 12, 2, '#FFFFFF', '#F1F5F9');
      this.text('WordCamp Delhi 2026', 88, 166, 230, { fontSize: 14.5, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Sat 12 – Sun 13 Dec 2026', 88, 188, 230, { fontSize: 10.5, fontWeight: 500, fill: '#64748B' }, f);

      this.rect('InfoList', 16, 238, 328, 162, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.vector('mapPin', 30, 254, 16, '#FF4D2D', f, 'I1');
      this.text('DAIC, 15 Janpath Rd, New Delhi', 54, 254, 200, { fontSize: 10.5, fontWeight: 600, fill: '#0F172A' }, f);
      this.pill(f, 'Open Map', 260, 248, 72, false);

      this.line('Sep1', 30, 290, 300, { stroke: '#F1F5F9', strokeWeight: 1 }, f);
      this.vector('shield', 30, 304, 16, '#DC2626', f, 'I2');
      this.text('delhi@wordcamp.org', 54, 304, 200, { fontSize: 10.5, fontWeight: 600, fill: '#0F172A' }, f);
      this.pill(f, 'Copy', 276, 298, 56, false);

      this.line('Sep2', 30, 340, 300, { stroke: '#F1F5F9', strokeWeight: 1 }, f);
      this.vector('ticket', 30, 354, 16, '#0F172A', f, 'I3');
      this.text('Wi-Fi: WCDELHI-GUEST', 54, 354, 200, { fontSize: 10.5, fontWeight: 600, fill: '#0F172A' }, f);
      this.pill(f, 'Copy', 276, 348, 56, false);

      this.rect('NotifCard', 16, 414, 328, 108, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Notification Choices', 30, 428, 240, { fontSize: 12, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('10-min Session Reminders', 30, 456, 200, { fontSize: 10.5, fontWeight: 500, fill: '#0F172A' }, f);
      this.toggle(f, 286, 452, true);
      this.text('Organizer Live Updates', 30, 488, 200, { fontSize: 10.5, fontWeight: 500, fill: '#0F172A' }, f);
      this.toggle(f, 286, 484, true);

      this.rect('DataCard', 16, 536, 328, 102, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Your Device Data', 30, 550, 240, { fontSize: 12, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Saved bookmarks & contacts stay on this phone.', 30, 570, 300, { fontSize: 9.5, fontWeight: 400, fill: '#64748B' }, f);
      this.rect('ExpD', 30, 594, 144, 32, { fill: '#F8FAFC', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Download Data', 32, 603, 140, { fontSize: 10, fontWeight: 600, fill: '#0F172A', textAlign: 'center' }, f);
      this.rect('DelD', 184, 594, 144, 32, { fill: '#FFF5F5', stroke: '#FCA5A5', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Clear Event Data', 186, 603, 140, { fontSize: 10, fontWeight: 700, fill: '#DC2626', textAlign: 'center' }, f);

      this.bottomNav(f, 'explore');
    }

    // =========================================================================
    // GROUP 5: IN-APP ORGANIZER & SUPERADMIN CONSOLE (M23 - M32)
    // =========================================================================

    // M23: Staff Sign In (Clean Single-Purpose Console Entry!)
    {
      const f = this.screenFrame('M23 · Staff Sign In', 'Dedicated Organizer & Admin Login · Clean Dark Card', '#0F172A');
      this.statusBar(f, true, 'Staff Portal');
      this.staffHeader(f, 'Staff Sign In', 'Attendee');

      this.logoTile(assets.cbWordmark, 96, 114, 168, 44, f, 14, 6, '#FFFFFF');
      this.text('Organizer & Event Lead Console', 30, 174, 300, { fontSize: 12, fontWeight: 500, fill: '#94A3B8', textAlign: 'center' }, f);

      this.rect('LoginBox', 20, 214, 320, 290, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 20 }, f);
      this.text('Sign in with your staff email', 36, 234, 260, { fontSize: 14, fontWeight: 800, fill: '#FFFFFF' }, f);

      this.text('Email', 36, 268, 120, { fontSize: 10, fontWeight: 600, fill: '#CBD5E1' }, f);
      this.rect('InEm', 36, 286, 288, 40, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 10 }, f);
      this.text('organizer@wordcampdelhi.org', 48, 299, 260, { fontSize: 11, fontWeight: 500, fill: '#FFFFFF' }, f);

      this.text('Password', 36, 338, 120, { fontSize: 10, fontWeight: 600, fill: '#CBD5E1' }, f);
      this.rect('InPw', 36, 356, 288, 40, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 10 }, f);
      this.text('••••••••••••', 48, 369, 260, { fontSize: 12, fontWeight: 700, fill: '#94A3B8' }, f);

      this.rect('SignBtn', 36, 418, 288, 44, { fill: '#FF4D2D', cornerRadius: 22 }, f);
      this.text('Sign In', 36, 432, 288, { fontSize: 12, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.text('Role detected automatically (Manager or Admin)', 36, 474, 288, { fontSize: 9.5, fontWeight: 500, fill: '#10B981', textAlign: 'center' }, f);

      this.text('Need organizer access? Contact hello@campbuddy.club', 24, 540, 312, { fontSize: 10, fontWeight: 400, fill: '#64748B', textAlign: 'center' }, f);
    }

    // M24: Staff Event Overview (Zero Dev Labels, Clean KPI + Quick Tools Grid!)
    {
      const f = this.screenFrame('M24 · Staff Event Overview', 'Event Tabs · Live KPIs · Active Event Card · Organizer Tools', '#0B1120');
      this.statusBar(f, true, 'Event Manager');
      this.staffHeader(f, 'WordCamp Delhi', 'Events', 'Preview');

      // Clean Horizontal Tabs
      const orgPills = [
        { lbl: 'Overview', x: 16, w: 80, on: true },
        { lbl: 'Delays', x: 102, w: 70, on: false },
        { lbl: 'Announce', x: 178, w: 84, on: false },
        { lbl: 'Details', x: 268, w: 76, on: false },
      ];
      for (const op of orgPills) {
        this.rect(`OP_${op.lbl}`, op.x, 86, op.w, 28, {
          fill: op.on ? '#FF4D2D' : '#1E293B', stroke: op.on ? '#FF4D2D' : '#334155', strokeWeight: 1, cornerRadius: 14
        }, f);
        this.text(op.lbl, op.x + 4, 93, op.w - 8, { fontSize: 10, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);
      }

      // 2 Clean KPI Tiles
      this.rect('K1', 16, 130, 158, 78, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Checked In', 30, 142, 130, { fontSize: 10, fontWeight: 600, fill: '#94A3B8' }, f);
      this.text('584', 30, 160, 130, { fontSize: 22, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('92% of ticketed', 30, 188, 130, { fontSize: 9.5, fontWeight: 600, fill: '#10B981' }, f);

      this.rect('K2', 186, 130, 158, 78, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Open Reports', 200, 142, 130, { fontSize: 10, fontWeight: 600, fill: '#94A3B8' }, f);
      this.text('2', 200, 160, 130, { fontSize: 22, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('Tap to review', 200, 188, 130, { fontSize: 9.5, fontWeight: 600, fill: '#F59E0B' }, f);

      // Active WordCamp Card
      this.rect('EvtCard', 16, 224, 328, 92, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 16 }, f);
      this.logoTile(assets.delhiFavicon, 30, 239, 58, 58, f, 14, 2, '#FFFFFF');
      this.text('WordCamp Delhi 2026', 100, 240, 220, { fontSize: 14.5, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('Dec 12–13 · Visible in public app', 100, 262, 220, { fontSize: 10, fontWeight: 500, fill: '#10B981' }, f);
      this.text('Synced 4 mins ago', 100, 280, 220, { fontSize: 9.5, fontWeight: 400, fill: '#94A3B8' }, f);

      // Organizer 2x2 Quick Tools Grid
      this.text('Event Tools', 16, 334, 180, { fontSize: 12.5, fontWeight: 800, fill: '#FFFFFF' }, f);
      const tools = [
        { t: 'Running Late', s: 'Shift track times', ico: 'clock', c: '#F59E0B', x: 16, y: 360 },
        { t: 'Live Broadcast', s: 'Send alert to phones', ico: 'megaphone', c: '#FF4D2D', x: 184, y: 360 },
        { t: 'Roster & Reports', s: 'Roles & safety queue', ico: 'users', c: '#38BDF8', x: 16, y: 440 },
        { t: 'Social Studio', s: 'Speaker & reel cards', ico: 'camera', c: '#10B981', x: 184, y: 440 },
        { t: 'Tables & Quests', s: 'Contributor Day setup', ico: 'wrench', c: '#A78BFA', x: 16, y: 520 },
        { t: 'Memories Archive', s: 'Photos & WordPress.tv', ico: 'award', c: '#F59E0B', x: 184, y: 520 },
      ];
      for (const tl of tools) {
        this.rect(`OT_${tl.t}`, tl.x, tl.y, 160, 68, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 14 }, f);
        this.vector(tl.ico, tl.x + 14, tl.y + 14, 16, tl.c, f, `OTI_${tl.t}`);
        this.text(tl.t, tl.x + 14, tl.y + 36, 134, { fontSize: 11, fontWeight: 700, fill: '#FFFFFF' }, f);
        this.text(tl.s, tl.x + 14, tl.y + 52, 134, { fontSize: 8.5, fontWeight: 400, fill: '#94A3B8' }, f);
      }

      this.rect('EditDet', 16, 610, 328, 44, { fill: '#334155', cornerRadius: 22 }, f);
      this.text('Edit Event Details & Venue Info', 16, 624, 328, { fontSize: 11, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);
    }

    // M25: Staff Running Late Delays (Clean Dark UI!)
    {
      const f = this.screenFrame('M25 · Staff Running Late Delays', 'Hall Selector · +15m Offset · Shifted Talk Preview · Broadcast', '#0B1120');
      this.statusBar(f, true, 'Event Manager');
      this.staffHeader(f, 'Running Late', 'Overview');

      this.pill(f, 'BHIM Auditorium', 16, 86, 130, true, '#FF4D2D', null, true);
      this.pill(f, 'Contrib Lounge', 154, 86, 114, false, '#0F172A', null, true);
      this.pill(f, 'All Halls', 274, 86, 70, false, '#0F172A', null, true);

      this.rect('OffCard', 16, 132, 328, 144, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Active Delay: +15 Minutes', 30, 148, 260, { fontSize: 16, fontWeight: 800, fill: '#F59E0B' }, f);
      this.text('Shifts 5 upcoming talks in BHIM Auditorium.', 30, 174, 290, { fontSize: 10, fontWeight: 400, fill: '#CBD5E1' }, f);
      const mins = ['+5m', '+10m', '+15m', '+30m'];
      for (let i = 0; i < 4; i++) {
        const bx = 30 + i * 76;
        const on = i === 2;
        this.rect(`Min_${i}`, bx, 212, 68, 40, { fill: on ? '#FF4D2D' : '#0F172A', stroke: on ? '#FF4D2D' : '#334155', strokeWeight: 1, cornerRadius: 12 }, f);
        this.text(mins[i], bx, 224, 68, { fontSize: 11.5, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);
      }

      this.rect('CasBox', 16, 294, 328, 158, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Updated Schedule Preview', 30, 310, 240, { fontSize: 12.5, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('• Local SEO (Pankaj): 11:20 AM → 11:35 AM', 30, 338, 296, { fontSize: 10.5, fontWeight: 600, fill: '#F59E0B' }, f);
      this.text('• AI Citations (Shammi): 02:00 PM → 02:15 PM', 30, 362, 296, { fontSize: 10.5, fontWeight: 600, fill: '#F59E0B' }, f);
      this.text('• Value Shift Panel: 04:00 PM → 04:15 PM', 30, 386, 296, { fontSize: 10.5, fontWeight: 600, fill: '#F59E0B' }, f);
      this.text('Sends instant update to 584 attendee phones.', 30, 420, 296, { fontSize: 10, fontWeight: 500, fill: '#10B981' }, f);

      this.rect('BrcBtn', 16, 474, 328, 46, { fill: '#FF4D2D', cornerRadius: 23 }, f);
      this.text('Broadcast +15m Delay', 16, 489, 328, { fontSize: 12, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('ClrBtn', 16, 534, 328, 42, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 21 }, f);
      this.text('Clear Delay (Back on Schedule)', 16, 548, 328, { fontSize: 10.5, fontWeight: 600, fill: '#CBD5E1', textAlign: 'center' }, f);
    }

    // M26: Staff Announce & Sent History (Zero Dev Labels!)
    {
      const f = this.screenFrame('M26 · Staff Announce & Sent Log', 'Tone Pills · Announcement Composer · Recent Broadcast History', '#0B1120');
      this.statusBar(f, true, 'Event Manager');
      this.staffHeader(f, 'Announce & Log', 'Overview');

      this.rect('CompBox', 16, 86, 328, 256, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Send Live Announcement', 30, 102, 260, { fontSize: 13.5, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.pill(f, 'Notice', 30, 130, 72, false, '#0F172A', null, true);
      this.pill(f, 'Break / Food', 108, 130, 96, true, '#D97706', null, true);
      this.pill(f, 'Together', 210, 130, 82, false, '#0F172A', null, true);

      this.rect('TitleIn', 30, 170, 300, 36, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 10 }, f);
      this.text('Lunch is Served in Courtyard!', 42, 181, 276, { fontSize: 11, fontWeight: 600, fill: '#FFFFFF' }, f);
      this.rect('BodyIn', 30, 214, 300, 64, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 10 }, f);
      this.text('Hot buffet open near Hall B. Stop by sponsor booths on your way!', 42, 224, 276, { fontSize: 10, fontWeight: 400, fill: '#CBD5E1' }, f);

      this.text('Reaches 584 phones + banner', 30, 296, 180, { fontSize: 9.5, fontWeight: 500, fill: '#10B981' }, f);
      this.rect('SendAnn', 218, 289, 112, 34, { fill: '#FF4D2D', cornerRadius: 17 }, f);
      this.text('Send Now', 220, 299, 108, { fontSize: 10.5, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);

      this.text('Recent Broadcasts', 18, 364, 240, { fontSize: 12, fontWeight: 800, fill: '#CBD5E1' }, f);
      const sent = [
        { t: 'BHIM Hall +15m Delay', m: '10:29 AM · Delivered to 584 phones', y: 392 },
        { t: 'Opening Ceremony in 10 mins', m: '08:50 AM · Delivered to 412 phones', y: 468 },
        { t: 'Registration Desk Open', m: '08:00 AM · Delivered to 320 phones', y: 544 },
      ];
      for (const s of sent) {
        this.rect(`S_${s.t}`, 16, s.y, 328, 64, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 14 }, f);
        this.text(s.t, 30, s.y + 13, 280, { fontSize: 12, fontWeight: 700, fill: '#FFFFFF' }, f);
        this.text(s.m, 30, s.y + 36, 280, { fontSize: 9.5, fontWeight: 500, fill: '#10B981' }, f);
      }
    }

    // M27: Staff Safety Reports & Roster Roles (Zero Dev Labels!)
    {
      const f = this.screenFrame('M27 · Staff Reports & Roster Roles', 'Safety Report Triage · Hide/Dismiss · Roster Role Assignment', '#0B1120');
      this.statusBar(f, true, '2 Open Reports');
      this.staffHeader(f, 'Reports & Roster', 'Overview');

      this.text('Open Safety Reports (2)', 18, 86, 200, { fontSize: 11.5, fontWeight: 800, fill: '#FCA5A5' }, f);
      this.rect('Rep1', 16, 112, 328, 138, { fill: '#1E293B', stroke: '#7F1D1D', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Flagged Profile · Spam Links', 30, 126, 210, { fontSize: 12, fontWeight: 700, fill: '#FFFFFF' }, f);
      this.rect('OpenP', 274, 124, 56, 20, { fill: '#FEF3C7', cornerRadius: 10 }, f);
      this.text('Open', 276, 128, 52, { fontSize: 8.5, fontWeight: 700, fill: '#B45309', textAlign: 'center' }, f);
      this.text('Attendee report: "Card promotes off-topic external links."', 30, 152, 298, { fontSize: 10, fontWeight: 400, fill: '#CBD5E1' }, f);
      this.rect('HideBtn', 30, 200, 144, 34, { fill: '#EF4444', cornerRadius: 17 }, f);
      this.text('Hide Profile', 32, 210, 140, { fontSize: 10, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('ResBtn', 186, 200, 144, 34, { fill: '#334155', cornerRadius: 17 }, f);
      this.text('Dismiss', 188, 210, 140, { fontSize: 10, fontWeight: 600, fill: '#FFFFFF', textAlign: 'center' }, f);

      this.text('Attendee Roster & Roles', 18, 272, 240, { fontSize: 12, fontWeight: 800, fill: '#CBD5E1' }, f);
      this.rect('RosSearch', 16, 300, 328, 38, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 19 }, f);
      this.vector('search', 30, 310, 16, '#94A3B8', f, 'RSrch');
      this.text('Search attendee name or role...', 54, 312, 250, { fontSize: 10.5, fontWeight: 400, fill: '#94A3B8' }, f);

      const roster = [
        { img: assets.pankaj, name: 'Pankaj Kumar', role: 'Speaker · Claimed Card', y: 354 },
        { img: assets.shreya, name: 'Shreya Agarwal', role: 'Table Lead · Documentation', y: 446 },
        { img: assets.amit, name: 'Amit Kumar Singh', role: 'Speaker · Core Table Lead', y: 538 },
      ];
      for (const r of roster) {
        this.rect(`Ros_${r.name}`, 16, r.y, 328, 78, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 16 }, f);
        this.avatar(r.img, 30, r.y + 16, 46, f, '#334155', false);
        this.text(r.name, 86, r.y + 18, 174, { fontSize: 13, fontWeight: 700, fill: '#FFFFFF' }, f);
        this.text(r.role, 86, r.y + 38, 174, { fontSize: 10, fontWeight: 500, fill: '#10B981' }, f);
        this.rect(`EdR_${r.name}`, 262, r.y + 24, 68, 28, { fill: '#334155', cornerRadius: 14 }, f);
        this.text('Edit Role', 264, r.y + 31, 64, { fontSize: 9.5, fontWeight: 600, fill: '#FFFFFF', textAlign: 'center' }, f);
      }
    }

    // M28: Staff Tables, Quests & Memories Publisher
    {
      const f = this.screenFrame('M28 · Staff Tables, Quests & Memories', 'Contributor Tables · Active Quests · Publish Album & WordPress.tv', '#0B1120');
      this.statusBar(f, true, 'Event Setup');
      this.staffHeader(f, 'Tables & Memories', 'Overview');

      this.rect('TabEd', 16, 86, 328, 182, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Contributor Tables & Quests', 30, 102, 204, { fontSize: 13, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.rect('AddTb', 240, 98, 90, 26, { fill: '#FF4D2D', cornerRadius: 13 }, f);
      this.text('+ Add Table', 242, 104, 86, { fontSize: 9.5, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);

      this.text('• Core & Gutenberg · Hall B · Amit Singh', 30, 138, 300, { fontSize: 10, fontWeight: 500, fill: '#CBD5E1' }, f);
      this.text('• Documentation · Hall B · Shreya Agarwal', 30, 160, 300, { fontSize: 10, fontWeight: 500, fill: '#CBD5E1' }, f);
      this.text('• Polyglots Hindi & Urdu · Table 3', 30, 182, 300, { fontSize: 10, fontWeight: 500, fill: '#CBD5E1' }, f);

      this.rect('EdQ', 30, 216, 300, 36, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 10 }, f);
      this.text('5 Active Quests Configured · Edit Quests', 42, 227, 276, { fontSize: 10, fontWeight: 600, fill: '#F59E0B' }, f);

      this.rect('MemOrg', 16, 286, 328, 286, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 18 }, f);
      this.text('Post-Event Memories', 30, 304, 280, { fontSize: 13.5, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('Official Photo Album URL', 30, 334, 260, { fontSize: 10, fontWeight: 600, fill: '#94A3B8' }, f);
      this.rect('Url1', 30, 354, 300, 36, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 10 }, f);
      this.text('https://photos.app.goo.gl/wcdelhi2026', 42, 365, 276, { fontSize: 10, fontWeight: 500, fill: '#FFFFFF' }, f);

      this.text('WordPress.tv Event URL', 30, 404, 260, { fontSize: 10, fontWeight: 600, fill: '#94A3B8' }, f);
      this.rect('Url2', 30, 424, 300, 36, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 10 }, f);
      this.text('https://wordpress.tv/event/wordcamp-delhi-2026/', 42, 435, 276, { fontSize: 10, fontWeight: 500, fill: '#FFFFFF' }, f);

      this.text('☑ Notify attendees that photos & talks are live', 30, 474, 300, { fontSize: 10, fontWeight: 500, fill: '#10B981' }, f);
      this.rect('PubMem', 30, 508, 300, 44, { fill: '#10B981', cornerRadius: 22 }, f);
      this.text('Save & Publish Memories', 32, 522, 296, { fontSize: 11, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);
    }

    // M29: Staff Social Kit & Reels (Sharp Delhi Seal + Clean CampBuddy Icon!)
    {
      const f = this.screenFrame('M29 · Staff Social Kit & Reels', 'Speaker Card Studio · Sharp Delhi Seal · Circular Portrait · Share', '#0B1120');
      this.statusBar(f, true, 'Social Studio');
      this.staffHeader(f, 'Social Card Studio', 'Overview');

      this.pill(f, 'Speaker Card', 16, 86, 108, true, '#FF4D2D', null, true);
      this.pill(f, 'Daylight Card', 130, 86, 108, false, '#0F172A', null, true);
      this.pill(f, 'Schedule Reel', 244, 86, 100, false, '#0F172A', null, true);

      this.rect('SocCanvas', 28, 130, 304, 396, { fill: '#0F172A', stroke: '#D97706', strokeWeight: 1.5, cornerRadius: 22 }, f);
      this.logoTile(assets.delhiFavicon, 46, 146, 42, 42, f, 21, 2, '#FFFFFF');
      this.text('WORDCAMP DELHI 2026', 98, 160, 166, { fontSize: 10, fontWeight: 800, fill: '#F59E0B' }, f);
      this.image(assets.cbIcon, 278, 148, 36, 36, f, 'CBMark', 'fit');

      this.avatar(assets.pankaj, 118, 206, 124, f, '#FF4D2D', false);

      this.text('Pankaj Kumar', 44, 348, 272, { fontSize: 20, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.text('10 Things to Do to Boost Local SEO in the AI Era', 44, 380, 272, { fontSize: 12, fontWeight: 600, fill: '#FF4D2D', textAlign: 'center' }, f);
      this.text('Sun, Dec 13 · 11:20 AM · BHIM Auditorium', 44, 428, 272, { fontSize: 10.5, fontWeight: 500, fill: '#CBD5E1', textAlign: 'center' }, f);
      this.text('campbuddy.club/delhi-2026', 44, 476, 272, { fontSize: 10, fontWeight: 700, fill: '#10B981', textAlign: 'center' }, f);

      this.rect('ExpSoc', 28, 544, 304, 44, { fill: '#FF4D2D', cornerRadius: 22 }, f);
      this.text('Share Social Image', 28, 558, 304, { fontSize: 11.5, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('SwTheme', 28, 600, 304, 42, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 21 }, f);
      this.text('Switch to Daylight Paper Theme', 28, 614, 304, { fontSize: 10.5, fontWeight: 600, fill: '#CBD5E1', textAlign: 'center' }, f);
    }

    // M30: SuperAdmin Global Dashboard (Airy 2x3 KPI Grid instead of cramped 3x3!)
    {
      const f = this.screenFrame('M30 · SuperAdmin Global Dashboard', 'System Health · 2x3 Key Metrics · Featured Delhi · Maintenance', '#0B1120');
      this.statusBar(f, true, 'Super Admin');
      this.staffHeader(f, 'Global Dashboard', 'Sign Out');

      // Slim Health Banner
      this.rect('HealthOk', 16, 86, 328, 44, { fill: '#064E3B', stroke: '#10B981', strokeWeight: 1, cornerRadius: 12 }, f);
      this.vector('check', 28, 99, 16, '#10B981', f, 'HChk');
      this.text('All systems healthy · 0 errors in 7 days', 52, 100, 276, { fontSize: 11, fontWeight: 600, fill: '#FFFFFF' }, f);

      // Clean Spacious 2x3 Stat Grid
      const stats = [
        { t: 'WordCamps', v: '26 · 6 Live', ico: 'calendar', c: '#FF4D2D', x: 16, y: 146 },
        { t: 'Attendees', v: '4.2k', ico: 'users', c: '#38BDF8', x: 184, y: 146 },
        { t: 'Deal Leads', v: '184', ico: 'gift', c: '#F59E0B', x: 16, y: 222 },
        { t: 'Event Managers', v: '18', ico: 'shield', c: '#10B981', x: 184, y: 222 },
        { t: 'Avg Feedback', v: '4.9 ★ (96)', ico: 'heart', c: '#EC4899', x: 16, y: 298 },
        { t: 'System Errors', v: '0', ico: 'activity', c: '#10B981', x: 184, y: 298 },
      ];
      for (const s of stats) {
        this.rect(`SG_${s.t}`, s.x, s.y, 160, 66, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 14 }, f);
        this.vector(s.ico, s.x + 14, s.y + 14, 15, s.c, f, `SGI_${s.t}`);
        this.text(s.t, s.x + 36, s.y + 13, 114, { fontSize: 10, fontWeight: 600, fill: '#94A3B8' }, f);
        this.text(s.v, s.x + 14, s.y + 36, 134, { fontSize: 16, fontWeight: 800, fill: '#FFFFFF' }, f);
      }

      // Featured Next WordCamp
      this.text('Up Next', 16, 382, 200, { fontSize: 12, fontWeight: 800, fill: '#CBD5E1' }, f);
      this.rect('Next1', 16, 408, 328, 72, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 16 }, f);
      this.logoTile(assets.delhiFavicon, 30, 420, 48, 48, f, 12, 2, '#FFFFFF');
      this.text('WordCamp Delhi 2026', 90, 422, 180, { fontSize: 13.5, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('Dec 12–13 · 17 Sponsors Ready', 90, 444, 210, { fontSize: 10, fontWeight: 500, fill: '#10B981' }, f);

      // Platform Maintenance Card
      this.rect('MaintBox', 16, 496, 328, 118, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Platform Maintenance', 30, 512, 220, { fontSize: 13, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('Last sync: Today 10:25 AM (Success)', 30, 534, 280, { fontSize: 10, fontWeight: 400, fill: '#94A3B8' }, f);
      this.rect('RefBtn', 30, 562, 144, 36, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 18 }, f);
      this.text('Refresh Data', 32, 572, 140, { fontSize: 10, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('PurBtn', 186, 562, 144, 36, { fill: '#EF4444', cornerRadius: 18 }, f);
      this.text('Clear Cache', 188, 572, 140, { fontSize: 10, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);

      this.adminBottomNav(f, 'dashboard');
    }

    // M31: SuperAdmin Event Managers (Clean Focused List, NO jammed Nested Sheet!)
    {
      const f = this.screenFrame('M31 · SuperAdmin Event Managers', 'Add Manager CTA · Status Filters · Clean Manager Roster', '#0B1120');
      this.statusBar(f, true, 'Super Admin');
      this.staffHeader(f, 'Event Managers', 'Dashboard');

      this.rect('AddMgr', 16, 86, 328, 42, { fill: '#FF4D2D', cornerRadius: 21 }, f);
      this.text('+ Add Event Manager', 16, 99, 328, { fontSize: 11.5, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);

      this.pill(f, 'All (18)', 16, 142, 74, true, '#F59E0B', '#0F172A', true);
      this.pill(f, 'Active (16)', 96, 142, 88, false, '#0F172A', null, true);
      this.pill(f, 'Paused (2)', 190, 142, 88, false, '#0F172A', null, true);

      const mgrs = [
        { name: 'Rohan Verma', email: 'rohan@wordcampdelhi.org', ev1: 'WordCamp Delhi 2026', ev2: 'Rajasthan 2026', on: true, y: 186 },
        { name: 'Neha Kapoor', email: 'neha@wcdelhi.org', ev1: 'WordCamp Delhi 2026', ev2: 'Pune 2027', on: true, y: 332 },
        { name: 'Arjun Mehta', email: 'arjun@wpindia.org', ev1: 'Kerala 2026', ev2: null, on: false, y: 478 },
      ];
      for (const m of mgrs) {
        this.rect(`MC_${m.name}`, 16, m.y, 328, 130, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 16 }, f);
        this.text(m.name, 30, m.y + 14, 190, { fontSize: 14, fontWeight: 800, fill: '#FFFFFF' }, f);
        this.rect(`St_${m.name}`, 266, m.y + 14, 62, 22, { fill: m.on ? '#064E3B' : '#334155', cornerRadius: 11 }, f);
        this.text(m.on ? 'Active' : 'Paused', 268, m.y + 18, 58, { fontSize: 9, fontWeight: 700, fill: m.on ? '#10B981' : '#94A3B8', textAlign: 'center' }, f);

        this.text(m.email, 30, m.y + 38, 280, { fontSize: 10, fontWeight: 400, fill: '#94A3B8' }, f);
        this.pill(f, m.ev1, 30, m.y + 64, 146, true, '#0F172A');
        if (m.ev2) {
          this.pill(f, m.ev2, 184, m.y + 64, 114, true, '#0F172A');
        }
        this.line(`MSep_${m.name}`, 30, m.y + 100, 298, { stroke: '#334155', strokeWeight: 1 }, f);
        this.text('Tap card to edit assignments & sign-in access →', 30, m.y + 108, 298, { fontSize: 9, fontWeight: 500, fill: '#64748B' }, f);
      }

      this.adminBottomNav(f, 'managers');
    }

    // M32: SuperAdmin More: Analytics, Deals & Lead Exports
    {
      const f = this.screenFrame('M32 · SuperAdmin Analytics & Leads', 'Visitor Analytics · System Health · Feedback · Deal Lead Exports', '#0B1120');
      this.statusBar(f, true, 'Super Admin');
      this.staffHeader(f, 'Analytics & Leads', 'Dashboard');

      const rows = [
        { ico: 'activity', t: 'Visitor Analytics', s: '4,820 active attendees · Top: Delhi 2026', y: 86 },
        { ico: 'shield', t: 'System Health & Fetches', s: 'All feeds healthy · 0 failed checks', y: 156 },
        { ico: 'heart', t: 'Attendee Feedback', s: '4.9 ★ avg rating from Thank-You card', y: 226 },
      ];
      for (const r of rows) {
        this.rect(`MR_${r.t}`, 16, r.y, 328, 58, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 14 }, f);
        this.ellipse(`MI_${r.t}`, 28, r.y + 11, 36, 36, { fill: '#0F172A' }, f);
        this.vector(r.ico, 37, r.y + 20, 18, '#F59E0B', f, `MVI_${r.t}`);
        this.text(r.t, 74, r.y + 12, 220, { fontSize: 12.5, fontWeight: 700, fill: '#FFFFFF' }, f);
        this.text(r.s, 74, r.y + 32, 230, { fontSize: 9.5, fontWeight: 400, fill: '#94A3B8' }, f);
        this.vector('chevronRight', 320, r.y + 22, 14, '#64748B', f, `MA_${r.t}`);
      }

      // Sponsor Deal Leads Card
      this.text('Sponsor Deals & Leads', 16, 304, 240, { fontSize: 12.5, fontWeight: 800, fill: '#F59E0B' }, f);
      this.rect('LeadsBox', 16, 330, 328, 286, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 18 }, f);
      this.logoTile(assets.sponsor_wpfusion, 30, 346, 68, 38, f, 8, 6, '#FDFBF7');
      this.text('WP Fusion · 64 Leads', 108, 348, 216, { fontSize: 13.5, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('30% Off CRM Deal · Active', 108, 368, 216, { fontSize: 9.5, fontWeight: 600, fill: '#10B981' }, f);

      this.line('LSep', 30, 398, 298, { stroke: '#334155', strokeWeight: 1 }, f);
      this.text('Latest Opt-In Leads:', 30, 412, 260, { fontSize: 10, fontWeight: 700, fill: '#94A3B8' }, f);
      this.text('1. sunil@wpsimplified.com · Agency (10–25)', 30, 436, 298, { fontSize: 10, fontWeight: 500, fill: '#FFFFFF' }, f);
      this.text('2. priya@delhiwp.in · Freelancer', 30, 458, 298, { fontSize: 10, fontWeight: 500, fill: '#FFFFFF' }, f);
      this.text('3. rahul@wpindia.org · Enterprise', 30, 480, 298, { fontSize: 10, fontWeight: 500, fill: '#FFFFFF' }, f);

      this.rect('ExpLeads', 30, 516, 298, 40, { fill: '#D97706', cornerRadius: 20 }, f);
      this.text('Export Captured Leads (CSV)', 32, 529, 294, { fontSize: 10.5, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('AddDeal', 30, 566, 298, 36, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 18 }, f);
      this.text('+ Add Sponsor Deal', 32, 577, 294, { fontSize: 10, fontWeight: 600, fill: '#FFFFFF', textAlign: 'center' }, f);

      this.adminBottomNav(f, 'more');
    }
  }
}

const builder = new CleanApkBuilder();
builder.buildAll32Screens();

const scriptPath = path.resolve(rootDir, 'scratch/figcraft_apk_32_script.json');
fs.writeFileSync(scriptPath, JSON.stringify(builder.commands, null, 2), 'utf8');
console.log(`Generated ${builder.commands.length} refined FigCraft commands for ALL 32 Mobile APK screens.`);
console.log(`Saved script to ${scriptPath}`);
