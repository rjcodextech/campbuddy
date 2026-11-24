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

// Official CampBuddy Lucide Line Icons (from LineIconData.kt) - crisp stroke vectors!
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
  settings: 'M9 12a3 3 0 1 0 6 0 3 3 0 1 0 -6 0zM19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z',
  activity: 'M22 12h-4l-3 9L9 3l-3 9H2',
  wrench: 'M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z',
  eye: 'M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8zM9 12a3 3 0 1 0 6 0 3 3 0 1 0 -6 0z',
  download: 'M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l 5 5 5-5M12 15V3',
  share: 'M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8M16 6l-4-4-4 4M12 2v13',
  filter: 'M4 6h16M7 12h10M10 18h4',
  coffee: 'M17 8h1a4 4 0 1 1 0 8h-1M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4zM6 2v2M10 2v2M14 2v2',
  hand: 'M18 11V6a2 2 0 0 0-4 0v5M14 10V4a2 2 0 0 0-4 0v2M10 10.5V6a2 2 0 0 0-4 0v8M18 8a2 2 0 1 1 4 0v6a8 8 0 0 1-8 8h-2c-2.8 0-4.5-.86-5.99-2.34l-3.6-3.6a2 2 0 0 1 2.83-2.82L7 15',
  pencil: 'M17 3l 4 4L7.5 20.5 2 22l1.5-5.5z',
  lifeBuoy: 'M2 12a10 10 0 1 0 20 0 10 10 0 1 0 -20 0zM8 12a4 4 0 1 0 8 0 4 4 0 1 0 -8 0zM4.93 4.93l 4.24 4.24M14.83 9.17l4.24-4.24M14.83 14.83l4.24 4.24M9.17 14.83l-4.24 4.24',
  target: 'M2 12a10 10 0 1 0 20 0 10 10 0 1 0 -20 0zM6 12a6 6 0 1 0 12 0 6 6 0 1 0 -12 0zM10 12a2 2 0 1 0 4 0 2 2 0 1 0 -4 0z',
  refresh: 'M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8M21 3v5h-5M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16M8 16H3v5',
  trash: 'M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 11v6M14 11v6',
  wifi: 'M5 12.55a11 11 0 0 1 14.08 0M1.42 9a16 16 0 0 1 21.16 0M8.53 16.11a6 6 0 0 1 6.95 0M12 20h.01',
  columns: 'M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2zM9 3v18M15 3v18',
};

// Natural SVG bounding box aspect ratios (w / h) for 100% proportional un-stretched centering
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

class AuditedApkBuilder {
  constructor() {
    this.commands = [];
    this.nextId = 2;
    this.commands.push({ command: 'page.rename', params: { name: 'Mobile APK - All 32 Audited Screens' } });
    this.idx = 0;
  }

  coords() {
    const col = this.idx % 8;
    const row = Math.floor(this.idx / 8);
    this.idx++;
    return { x: 60 + col * 430, y: 120 + row * 880 };
  }

  // Creates clean screen frame + places metadata label cleanly OUTSIDE above the frame!
  screenFrame(title, statesNote, fill = '#FFF7ED') {
    const pos = this.coords();
    // External canvas label above screen (outside the phone!)
    this.text(title, pos.x, pos.y - 54, 360, { fontSize: 15, fontWeight: 800, fill: '#0F172A' }, null, `Lbl ${title}`);
    this.text(`States: ${statesNote}`, pos.x, pos.y - 32, 360, { fontSize: 10, fontWeight: 600, fill: '#64748B' }, null, `Sub ${title}`);
    // Actual Phone Frame
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
    // node.set places exact bounding box top-left without unscaled SVG path offset!
    this.commands.push({
      command: 'node.set',
      params: { id, x: cx, y: cy }
    });
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

  // Clipped Circular Avatar (ZERO square corners sticking out!)
  avatar(filePath, x, y, size, parent, borderColor = '#E2E8F0', onlineDot = false) {
    const wrap = this.subFrame('AvatarClip', x, y, size, size, {
      fill: '#FFEDD5', cornerRadius: size / 2, clip: true, stroke: borderColor, strokeWeight: 2
    }, parent);
    this.image(filePath, 0, 0, size, size, wrap, 'Photo', 'fill');
    if (onlineDot) {
      this.ellipse('Online', x + size - 11, y + size - 11, 11, 11, { fill: '#10B981', stroke: '#FFFFFF', strokeWeight: 2 }, parent);
    }
    return wrap;
  }

  // Clipped Rounded Banner Image
  roundedBanner(filePath, x, y, width, height, radius, parent) {
    const wrap = this.subFrame('BannerClip', x, y, width, height, { cornerRadius: radius, clip: true }, parent);
    this.image(filePath, 0, 0, width, height, wrap, 'BannerImg', 'fill');
    return wrap;
  }

  // Universal Logo Box Standard: Works cleanly for dark, light, transparent, or colored logos on ANY surface!
  logoBox(filePath, x, y, width, height, parent, radius = 10, padding = 4) {
    this.rect('LogoPlate', x, y, width, height, {
      fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: radius
    }, parent);
    this.image(filePath, x + padding, y + padding, width - padding * 2, height - padding * 2, parent, 'Logo', 'fit');
  }

  statusBar(parent, dark = false, statusText = 'Offline Ready') {
    const color = dark ? '#FFFFFF' : '#0F172A';
    this.text('10:30 AM', 22, 14, 70, { fontSize: 11, fontWeight: 700, fill: color }, parent);
    this.ellipse('Dot', 210, 18, 6, 6, { fill: '#10B981' }, parent);
    this.text(statusText, 220, 14, 100, { fontSize: 10, fontWeight: 700, fill: '#10B981' }, parent);
    this.rect('Battery', 326, 15, 18, 9, { fill: color, cornerRadius: 2 }, parent);
  }

  // Unified CampBuddy TopBar on Attendee Screens
  attendeeTopBar(parent, title = null, back = false, rightBadge = null) {
    if (back) {
      this.ellipse('BackBtn', 16, 40, 32, 32, { fill: '#FFEDD5' }, parent);
      this.vector('chevronLeft', 23, 47, 18, '#EA580C', parent, 'BackIco');
      this.text(title || '', 56, 46, 200, { fontSize: 16, fontWeight: 800, fill: '#0F172A' }, parent);
      this.logoBox(assets.cbIcon, 314, 40, 32, 32, parent, 8, 3);
    } else {
      // CampBuddy official wordmark + optional Staff/Badge
      this.logoBox(assets.cbWordmark, 16, 40, 134, 34, parent, 10, 4);
      if (rightBadge) {
        this.rect('RightPill', 180, 44, 88, 26, { fill: '#FFEDD5', stroke: '#FF4D2D', strokeWeight: 1, cornerRadius: 13 }, parent);
        this.vector('crown', 190, 50, 14, '#FF4D2D', parent, 'CrownIco');
        this.text(rightBadge, 210, 50, 52, { fontSize: 10, fontWeight: 800, fill: '#FF4D2D' }, parent);
      }
      this.ellipse('GuideBtn', 278, 42, 30, 30, { fill: '#FFEDD5' }, parent);
      this.vector('help', 285, 49, 16, '#EA580C', parent, 'HelpIco');
      this.ellipse('SwitchBtn', 314, 42, 30, 30, { fill: '#FFEDD5' }, parent);
      this.vector('switchEvent', 321, 49, 16, '#EA580C', parent, 'SwitchIco');
    }
  }

  staffTopBar(parent, title, backLabel = 'Staff HQ', rightText = null) {
    this.text(`< ${backLabel}`, 18, 46, 100, { fontSize: 12, fontWeight: 700, fill: '#94A3B8' }, parent);
    this.text(title, 100, 45, 175, { fontSize: 15, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, parent);
    if (rightText) {
      this.rect('RBtn', 278, 43, 66, 24, { fill: '#1E293B', stroke: '#F59E0B', strokeWeight: 1, cornerRadius: 12 }, parent);
      this.text(rightText, 280, 48, 62, { fontSize: 9.5, fontWeight: 700, fill: '#F59E0B', textAlign: 'center' }, parent);
    } else {
      this.logoBox(assets.cbIcon, 314, 40, 30, 30, parent, 8, 3);
    }
  }

  // Real Vector Bottom Navigation Bar (5 real tabs from app config)
  bottomNav(parent, activeTab = 'home') {
    const y = 700;
    this.rect('NavBG', 0, y, 360, 60, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 }, parent);
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
    this.rect('AdmNavBG', 0, y, 360, 60, { fill: '#0F172A', stroke: '#1E293B', strokeWeight: 1 }, parent);
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

  exploreHeader(parent, activeKey = 'people') {
    this.attendeeTopBar(parent, 'Explore', true);
    const tiles = [
      { key: 'people', label: 'People', icon: 'users', x: 14 },
      { key: 'sponsors', label: 'Sponsors', icon: 'heart', x: 70 },
      { key: 'deals', label: 'Deals', icon: 'gift', x: 126 },
      { key: 'steals', label: 'Steals', icon: 'sparkles', x: 182 },
      { key: 'quests', label: 'Quests', icon: 'star', x: 238 },
      { key: 'info', label: 'Info', icon: 'ticket', x: 294 },
    ];
    for (const t of tiles) {
      const on = t.key === activeKey;
      this.rect(`ETile_${t.key}`, t.x, 84, 52, 54, {
        fill: on ? '#FFEDD5' : '#FFFFFF',
        stroke: on ? '#FF4D2D' : '#E2E8F0',
        strokeWeight: on ? 1.5 : 1,
        cornerRadius: 10
      }, parent);
      this.vector(t.icon, t.x + 17, 92, 18, on ? '#EA580C' : '#0F172A', parent, `EIco_${t.key}`);
      this.text(t.label, t.x + 2, 117, 48, { fontSize: 9, fontWeight: on ? 800 : 600, fill: '#0F172A', textAlign: 'center' }, parent);
    }
  }

  pill(parent, label, x, y, w, active = false, color = '#0F172A') {
    this.rect(`Pill_${label}`, x, y, w, 26, {
      fill: active ? color : '#FFFFFF', stroke: active ? color : '#E2E8F0', strokeWeight: 1, cornerRadius: 13
    }, parent);
    this.text(label, x + 4, y + 7, w - 8, {
      fontSize: 9.5, fontWeight: active ? 700 : 600, fill: active ? '#FFFFFF' : '#475569', textAlign: 'center'
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

    // M01: Splash, Pre-Cache & Direct Update Sheet
    {
      const f = this.screenFrame('M01 · Splash & App Update', 'Offline Ready | Optional App Update Card', '#0F172A');
      this.statusBar(f, true, 'Offline Ready');

      // CampBuddy Official Wordmark in Universal White Pill (crystal clear contrast!)
      this.logoBox(assets.cbWordmark, 70, 96, 220, 68, f, 18, 10);

      // Selected Conference Badge
      this.logoBox(assets.delhiLogo, 136, 194, 88, 88, f, 20, 10);
      this.text('WordCamp Delhi 2026', 30, 296, 300, { fontSize: 21, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.text('"Dil se Dilli, code se community"', 30, 326, 300, { fontSize: 12, fontWeight: 500, fill: '#F59E0B', textAlign: 'center' }, f);
      this.text('Dec 12–13 · DAIC, New Delhi', 30, 348, 300, { fontSize: 11, fontWeight: 500, fill: '#94A3B8', textAlign: 'center' }, f);

      // Friendly Offline Status Card (Zero technical jargon!)
      this.rect('SyncBox', 24, 396, 312, 92, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Works 100% Offline at the Venue', 40, 412, 280, { fontSize: 12, fontWeight: 700, fill: '#FFFFFF' }, f);
      this.rect('BarBG', 40, 438, 280, 7, { fill: '#0F172A', cornerRadius: 3.5 }, f);
      this.rect('BarFill', 40, 438, 280, 7, { fill: '#10B981', cornerRadius: 3.5 }, f);
      this.text('22 talks, 11 speakers & venue maps ready', 40, 456, 280, { fontSize: 10, fontWeight: 600, fill: '#10B981' }, f);

      // Update Card State
      this.rect('UpdCard', 24, 508, 312, 82, { fill: '#FEF3C7', cornerRadius: 14 }, f);
      this.text('New Version Ready (v2.4.1)', 40, 520, 180, { fontSize: 11.5, fontWeight: 800, fill: '#B45309' }, f);
      this.text('Faster QR badge scan & venue map updates.', 40, 540, 175, { fontSize: 9.5, fontWeight: 500, fill: '#78350F' }, f);
      this.rect('UpdBtn', 236, 528, 84, 32, { fill: '#D97706', cornerRadius: 16 }, f);
      this.text('INSTALL', 238, 537, 80, { fontSize: 10, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);

      this.rect('StartBtn', 24, 628, 312, 48, { fill: '#FF4D2D', cornerRadius: 24 }, f);
      this.text('GET STARTED', 24, 644, 312, { fontSize: 12, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
    }

    // M02: WordCamp Picker, Hero Orbit & Desktop PIN
    {
      const f = this.screenFrame('M02 · WordCamp Picker & Hero Orbit', 'Hero Orbit | Country Filter | Desktop PIN | Staff');
      this.statusBar(f, false, 'Offline Ready');

      // CampBuddy Wordmark Header
      this.logoBox(assets.cbWordmark, 16, 40, 146, 36, f, 10, 4);
      this.rect('StaffSign', 244, 44, 100, 26, { fill: '#FFEDD5', stroke: '#FF4D2D', strokeWeight: 1, cornerRadius: 13 }, f);
      this.text('Staff Sign In', 246, 50, 96, { fontSize: 10, fontWeight: 700, fill: '#FF4D2D', textAlign: 'center' }, f);

      // Hero Orbit Graphic (from PickerScreen.kt!)
      this.ellipse('OuterOrbit', 110, 90, 140, 140, { fill: 'none', stroke: '#FF4D2D', strokeWeight: 1.5, dashes: [5, 4] }, f);
      this.ellipse('InnerOrbit', 132, 112, 96, 96, { fill: 'none', stroke: '#F59E0B', strokeWeight: 1.5 }, f);
      this.logoBox(assets.cbIcon, 148, 128, 64, 64, f, 16, 6);
      // Coffee & MapPin orbiting dots
      this.ellipse('Orb1', 216, 146, 24, 24, { fill: '#FFFFFF', stroke: '#F59E0B', strokeWeight: 1 }, f);
      this.vector('coffee', 221, 151, 14, '#D97706', f, 'OrbCaf');
      this.ellipse('Orb2', 120, 146, 24, 24, { fill: '#FFFFFF', stroke: '#FF4D2D', strokeWeight: 1 }, f);
      this.vector('mapPin', 125, 151, 14, '#FF4D2D', f, 'OrbPin');
      // 4 Floating Feature Pills with crisp icons!
      const orbPills = [
        { lbl: 'Talks', ico: 'calendar', x: 16, y: 94, w: 84 },
        { lbl: 'People', ico: 'users', x: 254, y: 108, w: 90 },
        { lbl: 'Alerts', ico: 'bell', x: 16, y: 194, w: 84 },
        { lbl: 'CampCard', ico: 'card', x: 244, y: 194, w: 100 },
      ];
      for (const p of orbPills) {
        this.rect(`OP_${p.lbl}`, p.x, p.y, p.w, 30, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 15 }, f);
        this.ellipse(`OI_${p.lbl}`, p.x + 5, p.y + 4, 22, 22, { fill: '#FFEDD5' }, f);
        this.vector(p.ico, p.x + 10, p.y + 9, 12, '#FF4D2D', f, `OIV_${p.lbl}`);
        this.text(p.lbl, p.x + 30, p.y + 8, p.w - 36, { fontSize: 10, fontWeight: 700, fill: '#0F172A' }, f);
      }

      // Country Filter Chips
      this.pill(f, 'All (26)', 16, 250, 68, true, '#0F172A');
      this.pill(f, 'India (4)', 90, 250, 74, false);
      this.pill(f, 'Greece (1)', 170, 250, 82, false);
      this.pill(f, 'Kenya (1)', 258, 250, 80, false);

      // Event 1: Delhi (Active/Featured)
      this.rect('EvtDelhi', 16, 286, 328, 112, { fill: '#FFFFFF', stroke: '#FF4D2D', strokeWeight: 2, cornerRadius: 16 }, f);
      this.logoBox(assets.delhiLogo, 28, 300, 56, 56, f, 12, 6);
      this.text('WordCamp Delhi 2026', 94, 298, 236, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Dec 12–13 · DAIC, New Delhi', 94, 318, 236, { fontSize: 10.5, fontWeight: 600, fill: '#EA580C' }, f);
      this.text('Saved on this phone · Works Offline', 94, 336, 236, { fontSize: 9.5, fontWeight: 600, fill: '#10B981' }, f);
      this.rect('GuideBtn', 28, 364, 130, 24, { fill: '#FFEDD5', cornerRadius: 12 }, f);
      this.text('First-Timer Guide', 30, 369, 126, { fontSize: 9, fontWeight: 700, fill: '#EA580C', textAlign: 'center' }, f);
      this.rect('OpenBtn', 238, 362, 94, 28, { fill: '#FF4D2D', cornerRadius: 14 }, f);
      this.text('OPEN EVENT', 240, 369, 90, { fontSize: 9.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);

      // Event 2: Athens (Default)
      this.rect('EvtAthens', 16, 408, 328, 76, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('WordCamp Athens 2026', 28, 422, 210, { fontSize: 13, fontWeight: 700, fill: '#0F172A' }, f);
      this.text('Dec 11–12 · Athens, Greece', 28, 442, 210, { fontSize: 10, fontWeight: 500, fill: '#64748B' }, f);
      this.rect('Open2', 258, 430, 74, 28, { fill: '#F1F5F9', cornerRadius: 14 }, f);
      this.text('OPEN', 260, 437, 70, { fontSize: 9.5, fontWeight: 700, fill: '#0F172A', textAlign: 'center' }, f);

      // Desktop 6-Digit PIN Card
      this.rect('PinCard', 16, 496, 328, 136, { fill: '#0F172A', cornerRadius: 18 }, f);
      this.text('Bring My Plan from Desktop', 30, 512, 280, { fontSize: 13, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('Enter the 6-digit code shown on CampBuddy Web:', 30, 532, 290, { fontSize: 10, fontWeight: 400, fill: '#94A3B8' }, f);
      for (let i = 0; i < 6; i++) {
        const dx = 30 + i * 48;
        this.rect(`Pin_${i}`, dx, 556, 40, 40, { fill: '#1E293B', stroke: i < 4 ? '#F59E0B' : '#334155', strokeWeight: 1.5, cornerRadius: 8 }, f);
        this.text(i < 4 ? ['4', '8', '2', '9'][i] : '_', dx, 568, 40, { fontSize: 15, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      }
      this.text('Import starred talks & contacts in 1 second', 30, 606, 290, { fontSize: 9.5, fontWeight: 500, fill: '#10B981' }, f);

      this.text('Privacy Policy · Terms · My Data Controls', 16, 650, 328, { fontSize: 9.5, fontWeight: 600, fill: '#64748B', textAlign: 'center' }, f);
    }

    // M03: Onboarding Wizard & Notification Choices (Overlap Fixed!)
    {
      const f = this.screenFrame('M03 · Onboarding & Alerts Setup', '5-Step Progress | Multi-Select | Alert Consent');
      this.statusBar(f, false, 'Step 2 of 5');
      this.attendeeTopBar(f, 'Personalize', true);

      // Progress Bars
      for (let i = 0; i < 5; i++) {
        this.rect(`Step_${i}`, 18 + i * 66, 84, 58, 5, { fill: i <= 1 ? '#FF4D2D' : '#E2E8F0', cornerRadius: 2.5 }, f);
      }

      // Proper Spacing: Title wraps across 2 lines (y=102..148), Subtitle cleanly at y=156!
      this.text('What brings you to WordCamp Delhi?', 18, 102, 324, { fontSize: 18, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Used only on this phone to highlight matching talks & peers.', 18, 156, 324, { fontSize: 10.5, fontWeight: 400, fill: '#64748B' }, f);

      const opts = [
        { t: 'Building WordPress Products & AI', s: 'Plugin/Theme creators & agency founders', on: true, y: 198 },
        { t: 'SEO, Content & Growth Marketing', s: 'AI search, Local SEO & editorial tools', on: true, y: 272 },
        { t: 'First-Time Contributor & Open Source', s: 'Core, Documentation & Translation tables', on: false, y: 346 },
        { t: 'Networking & Finding Collaborators', s: 'Meet speakers, sponsors & peers', on: false, y: 420 },
      ];
      for (const o of opts) {
        this.rect(`Opt_${o.t}`, 16, o.y, 328, 64, {
          fill: o.on ? '#FFEDD5' : '#FFFFFF', stroke: o.on ? '#FF4D2D' : '#E2E8F0', strokeWeight: o.on ? 2 : 1, cornerRadius: 14
        }, f);
        this.text(o.t, 30, o.y + 12, 260, { fontSize: 12, fontWeight: 700, fill: '#0F172A' }, f);
        this.text(o.s, 30, o.y + 34, 260, { fontSize: 10, fontWeight: 500, fill: '#64748B' }, f);
        this.ellipse('Circle', 306, o.y + 21, 22, 22, { fill: o.on ? '#FF4D2D' : '#F1F5F9' }, f);
        if (o.on) this.vector('check', 310, o.y + 25, 14, '#FFFFFF', f, 'Chk');
      }

      // Notification Prompt Card
      this.rect('NotifBox', 16, 500, 328, 92, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Talk Reminders & Live Updates', 30, 514, 230, { fontSize: 12, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('10-min alerts before your saved talks start.', 30, 534, 230, { fontSize: 10, fontWeight: 400, fill: '#64748B' }, f);
      this.toggle(f, 286, 520, true);
      this.text('Privacy First: Everything stays on your device.', 30, 564, 290, { fontSize: 9.5, fontWeight: 600, fill: '#10B981' }, f);

      this.rect('ContBtn', 16, 616, 220, 46, { fill: '#0F172A', cornerRadius: 23 }, f);
      this.text('CONTINUE (2 SELECTED)', 18, 632, 216, { fontSize: 10.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('SkipBtn', 246, 616, 98, 46, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 23 }, f);
      this.text('SKIP', 248, 632, 94, { fontSize: 10.5, fontWeight: 700, fill: '#475569', textAlign: 'center' }, f);
    }

    // M04: Live Organizer Banner & Pre-Event Alert Prompt
    {
      const f = this.screenFrame('M04 · Live Notice & Alert Prompts', 'Organizer Notice Banner | 3-Day Prompt | Delay');
      this.statusBar(f, false, 'Live Updates');
      this.attendeeTopBar(f, null, false, 'Staff');

      // 1. LiveNoticeBanner ("FROM THE ORGANIZERS")
      this.rect('NoticeBG', 16, 86, 328, 108, { fill: '#ECFDF5', stroke: '#10B981', strokeWeight: 1.5, cornerRadius: 16 }, f);
      this.ellipse('NoticeIcoBG', 28, 100, 38, 38, { fill: '#10B981' }, f);
      this.vector('megaphone', 38, 110, 18, '#FFFFFF', f, 'MegIco');
      this.text('FROM THE ORGANIZERS', 76, 98, 200, { fontSize: 9.5, fontWeight: 800, fill: '#065F46' }, f);
      this.text('Lunch is Ready in Courtyard!', 76, 114, 220, { fontSize: 13, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Visit Dining Courtyard now. Elementor & WP Fusion booths are handing out swag.', 76, 134, 240, { fontSize: 10, fontWeight: 400, fill: '#475569' }, f);
      this.text('✕', 320, 96, 16, { fontSize: 12, fontWeight: 700, fill: '#64748B' }, f);

      // 2. RunningLate Banner
      this.rect('DelayBG', 16, 208, 328, 68, { fill: '#FEF3C7', stroke: '#F59E0B', strokeWeight: 1.5, cornerRadius: 14 }, f);
      this.vector('clock', 28, 224, 20, '#B45309', f, 'ClkIco');
      this.text('BHIM Auditorium Running +15m Late', 56, 218, 270, { fontSize: 11.5, fontWeight: 800, fill: '#B45309' }, f);
      this.text('Session times in Schedule & My Day updated.', 56, 238, 270, { fontSize: 10, fontWeight: 500, fill: '#78350F' }, f);

      // 3. LiveAskCard ("Get live updates from organizers")
      this.rect('AskBG', 16, 290, 328, 152, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.vector('bell', 30, 306, 20, '#FF4D2D', f, 'BellAsk');
      this.text('Get live updates from the organizers', 58, 306, 260, { fontSize: 13, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('"Opening remarks starting", "Lunch is served", or a talk about to begin — sent to your phone even when CampBuddy is closed.', 30, 336, 300, { fontSize: 10, fontWeight: 400, fill: '#475569' }, f);
      this.rect('TurnOn', 30, 392, 146, 36, { fill: '#FF4D2D', cornerRadius: 18 }, f);
      this.text('TURN ON ALERTS', 32, 404, 142, { fontSize: 9.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('NotNow', 186, 392, 146, 36, { fill: '#F1F5F9', cornerRadius: 18 }, f);
      this.text('NOT NOW', 188, 404, 142, { fontSize: 9.5, fontWeight: 700, fill: '#475569', textAlign: 'center' }, f);

      // 4. First-Timer StartHere Card
      this.rect('FirstBG', 16, 458, 328, 100, { fill: '#0F172A', cornerRadius: 16 }, f);
      this.text('First time at WordCamp?', 30, 474, 240, { fontSize: 14, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('Read our 2-minute guide on talks, Contributor Day & Delhi Metro.', 30, 496, 290, { fontSize: 10, fontWeight: 400, fill: '#CBD5E1' }, f);
      this.rect('StartGuide', 30, 524, 140, 24, { fill: '#D97706', cornerRadius: 12 }, f);
      this.text('OPEN GUIDE →', 32, 529, 136, { fontSize: 9.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);

      this.bottomNav(f, 'home');
    }

    // =========================================================================
    // GROUP 2: CORE ATTENDEE EXPERIENCE (M05 - M11)
    // =========================================================================

    // M05: Tactile Live Event Home Dashboard (Flagship Screen!)
    {
      const f = this.screenFrame('M05 · Tactile Live Home Dashboard', 'Flagship Home | Vector Grid | Circular Avatars | SOS');
      this.statusBar(f, false, 'Offline Ready');
      this.attendeeTopBar(f, null, false, 'Staff');

      // Dark Navy Hero Card with White-Framed Delhi Logo!
      this.rect('HeroBG', 16, 84, 328, 152, { fill: '#0F172A', cornerRadius: 20 }, f);
      this.text('10:30 AM', 32, 98, 160, { fontSize: 30, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('Dec 12–13 · WordCamp Delhi', 32, 134, 210, { fontSize: 10.5, fontWeight: 600, fill: '#94A3B8' }, f);
      // Universal White Logo Container on Dark Hero!
      this.logoBox(assets.delhiLogo, 276, 98, 52, 52, f, 26, 6);
      // Offline Ready Status Pill
      this.rect('OffPill', 32, 158, 114, 22, { fill: '#064E3B', stroke: '#10B981', strokeWeight: 1, cornerRadius: 11 }, f);
      this.ellipse('OffDot', 42, 166, 6, 6, { fill: '#10B981' }, f);
      this.text('Offline Ready', 54, 163, 86, { fontSize: 9.5, fontWeight: 700, fill: '#FFFFFF' }, f);
      // Next Up Sub-Card
      this.rect('NextBox', 28, 190, 304, 36, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 12 }, f);
      this.text('NEXT UP · in 15m: Local SEO (Pankaj)', 38, 202, 220, { fontSize: 10, fontWeight: 700, fill: '#FBBF24' }, f);
      this.rect('ViewBtn', 268, 196, 56, 24, { fill: '#D97706', cornerRadius: 12 }, f);
      this.text('VIEW', 270, 202, 52, { fontSize: 9, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);

      // My People Strip (Clipped Circular Avatars!)
      this.text('My People & Matches', 16, 248, 180, { fontSize: 12, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('All peers →', 244, 249, 100, { fontSize: 10, fontWeight: 700, fill: '#FF4D2D', textAlign: 'right' }, f);

      // Wave Circle
      this.ellipse('WaveCircle', 16, 270, 48, 48, { fill: '#FFEDD5', stroke: '#D97706', strokeWeight: 1.5 }, f);
      this.vector('plus', 31, 285, 18, '#FF4D2D', f, 'WavePlus');
      this.text('Wave', 14, 322, 52, { fontSize: 9.5, fontWeight: 600, fill: '#0F172A', textAlign: 'center' }, f);

      const ppl = [
        { img: assets.pankaj, name: 'Pankaj' },
        { img: assets.radhika, name: 'Radhika' },
        { img: assets.amit, name: 'Amit' },
        { img: assets.shammi, name: 'Shammi' },
      ];
      for (let i = 0; i < ppl.length; i++) {
        const px = 80 + i * 66;
        this.avatar(ppl[i].img, px, 270, 48, f, '#E2E8F0', true);
        this.text(ppl[i].name, px - 2, 322, 52, { fontSize: 9.5, fontWeight: 600, fill: '#0F172A', textAlign: 'center' }, f);
      }

      // Event Hubs 2x3 Tactile Grid (from EventScreens.kt ActionGrid!)
      this.text('Event Hubs', 16, 344, 160, { fontSize: 12, fontWeight: 800, fill: '#0F172A' }, f);
      const hubs = [
        { t: 'My Day', s: '4 saved sessions', icon: 'calendar', x: 16, y: 366 },
        { t: 'Schedule', s: '22 talks & tracks', icon: 'clock', x: 184, y: 366 },
        { t: 'My CampCard', s: 'QR badge & shares', icon: 'qr', x: 16, y: 434 },
        { t: 'Attendees', s: 'Peers & speakers', icon: 'users', x: 184, y: 434 },
        { t: 'Quests', s: 'Contributor roadmap', icon: 'star', x: 16, y: 502 },
        { t: 'Sponsors & Swag', s: 'Steals, deals & booths', icon: 'gift', x: 184, y: 502 },
      ];
      for (const h of hubs) {
        this.rect(`Hub_${h.t}`, h.x, h.y, 160, 60, { fill: '#FAFAFD', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 14 }, f);
        this.rect(`IcoBG_${h.t}`, h.x + 10, h.y + 10, 26, 26, { fill: '#FFEDD5', cornerRadius: 8 }, f);
        this.vector(h.icon, h.x + 16, h.y + 16, 14, '#EA580C', f, `HubIco_${h.t}`);
        this.text(h.t, h.x + 42, h.y + 14, 110, { fontSize: 11, fontWeight: 700, fill: '#0F172A' }, f);
        this.text(h.s, h.x + 10, h.y + 40, 140, { fontSize: 9, fontWeight: 500, fill: '#64748B' }, f);
      }

      // Sticky Help / SOS Bar (matching StickyHelpBar in EventScreens.kt)
      this.rect('SOSBar', 16, 578, 328, 48, { fill: '#EF4444', cornerRadius: 24 }, f);
      this.vector('phone', 30, 593, 18, '#FFFFFF', f, 'SosPhone');
      this.text('Need Help at WordCamp?', 56, 586, 180, { fontSize: 11, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('Helpdesk · Safety · Organizers', 56, 602, 180, { fontSize: 9, fontWeight: 500, fill: '#FEE2E2' }, f);
      this.rect('HelpPill', 274, 590, 58, 24, { fill: '#FFFFFF', cornerRadius: 12 }, f);
      this.text('HELP', 276, 596, 54, { fontSize: 9.5, fontWeight: 800, fill: '#EF4444', textAlign: 'center' }, f);

      this.text('Privacy · Terms · My Data Controls', 16, 638, 328, { fontSize: 9.5, fontWeight: 600, fill: '#64748B', textAlign: 'center' }, f);
      this.bottomNav(f, 'home');
    }

    // M06: First-Timer Guide & Practical Info
    {
      const f = this.screenFrame('M06 · First-Timer Guide & Venue', 'Full-Bleed Delhi Banner | Stepper | FAQ Accordion');
      this.statusBar(f, false, 'Offline Guide');
      this.attendeeTopBar(f, 'First-Timer Guide', true);

      // Full-bleed rounded banner image + dark gradient scrim card
      this.roundedBanner(assets.indiaGate, 16, 82, 328, 112, 16, f);
      this.rect('Scrim', 16, 140, 328, 54, { fill: '#0F172A', cornerRadius: 14 }, f);
      this.text('Dil se Dilli — Welcome to WordCamp Delhi 2026!', 28, 150, 304, { fontSize: 12, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('Everything you need for Dec 12–13 at DAIC.', 28, 170, 304, { fontSize: 9.5, fontWeight: 500, fill: '#F59E0B' }, f);

      // Conference Stepper
      this.rect('StepBox', 16, 206, 328, 102, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('2-Day Conference Flow', 28, 218, 240, { fontSize: 12, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('1. Dec 12 · Contributor Day (Check-in 08:00 AM)', 28, 240, 300, { fontSize: 10, fontWeight: 700, fill: '#EA580C' }, f);
      this.text('2. Dec 13 · Main Conference (BHIM Auditorium)', 28, 260, 300, { fontSize: 10, fontWeight: 600, fill: '#0F172A' }, f);
      this.text('3. Dec 13 · Community After Party (WP Fusion)', 28, 280, 300, { fontSize: 10, fontWeight: 500, fill: '#64748B' }, f);

      // Venue & Transit (using crisp vector icons instead of broken emojis!)
      this.rect('VenueBox', 16, 320, 328, 116, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Venue & Metro Directions', 28, 332, 240, { fontSize: 12, fontWeight: 800, fill: '#0F172A' }, f);
      this.vector('mapPin', 28, 356, 14, '#FF4D2D', f, 'PinIco');
      this.text('15, Janpath Rd, Windsor Place, New Delhi', 48, 356, 280, { fontSize: 10, fontWeight: 500, fill: '#475569' }, f);
      this.vector('compass', 28, 378, 14, '#0F172A', f, 'MetIco');
      this.text('Metro: Janpath (Violet) / Patel Chowk (Yellow)', 48, 378, 280, { fontSize: 10, fontWeight: 500, fill: '#475569' }, f);
      this.vector('phone', 28, 400, 14, '#DC2626', f, 'EmgIco');
      this.text('Emergency Contact: delhi@wordcamp.org', 48, 400, 280, { fontSize: 10, fontWeight: 700, fill: '#DC2626' }, f);

      // Expandable FAQ
      this.rect('FaqOpen', 16, 448, 328, 86, { fill: '#FAFAFD', stroke: '#FF4D2D', strokeWeight: 1, cornerRadius: 14 }, f);
      this.text('▾ What should I bring to Contributor Day?', 28, 460, 290, { fontSize: 11, fontWeight: 700, fill: '#0F172A' }, f);
      this.text('Bring your laptop, charger, and a WordPress.org account. Lunch & Wi-Fi are provided.', 28, 482, 300, { fontSize: 10, fontWeight: 400, fill: '#475569' }, f);

      this.rect('Faq2', 16, 544, 328, 40, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 12 }, f);
      this.text('▸ Is the venue wheelchair accessible?', 28, 556, 290, { fontSize: 11, fontWeight: 600, fill: '#0F172A' }, f);

      this.rect('Faq3', 16, 592, 328, 40, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 12 }, f);
      this.text('▸ Where are quiet & nursing rooms?', 28, 604, 290, { fontSize: 11, fontWeight: 600, fill: '#0F172A' }, f);

      this.bottomNav(f, 'home');
    }

    // M07: Full Schedule & More Filters (MyDayTab -> Full schedule)
    {
      const f = this.screenFrame('M07 · Full Schedule & Filters', 'Schedule Health | Full vs My Switch | Track & Type');
      this.statusBar(f, false, '22 Sessions');
      this.attendeeTopBar(f, 'Schedule & My Day', true);

      // ScheduleHealthBanner (from MyDay.kt lines 461-547!)
      this.rect('HealthCard', 16, 82, 328, 86, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Schedule Health', 28, 92, 160, { fontSize: 12, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('4 bookmarked · 2h 30m planned', 28, 108, 190, { fontSize: 9.5, fontWeight: 500, fill: '#64748B' }, f);
      this.rect('ConfBadge', 238, 94, 94, 22, { fill: '#ECFDF5', stroke: '#10B981', strokeWeight: 1, cornerRadius: 11 }, f);
      this.text('✓ 0 Conflicts', 240, 99, 90, { fontSize: 9, fontWeight: 800, fill: '#10B981', textAlign: 'center' }, f);
      this.rect('AlertStrip', 28, 130, 304, 28, { fill: '#FFEDD5', cornerRadius: 8 }, f);
      this.vector('bell', 36, 137, 14, '#FF4D2D', f, 'AlBell');
      this.text('10-minute prior alerts active', 56, 138, 190, { fontSize: 9.5, fontWeight: 600, fill: '#0F172A' }, f);
      this.text('Change', 278, 138, 46, { fontSize: 9.5, fontWeight: 800, fill: '#FF4D2D', textAlign: 'right' }, f);

      // Two-Way Switch: Full schedule | My schedule (from MyDay.kt Segment!)
      this.pill(f, 'Full schedule', 16, 178, 160, true, '#EA580C');
      this.pill(f, 'My schedule (4)', 184, 178, 160, false);

      // Search Field with crisp vector icon
      this.rect('SearchBox', 16, 212, 328, 36, { fill: '#FFFFFF', stroke: '#0F172A', strokeWeight: 1.5, cornerRadius: 10 }, f);
      this.vector('search', 26, 221, 18, '#0F172A', f, 'SrchIco');
      this.text('Search sessions or speakers...', 52, 223, 270, { fontSize: 10.5, fontWeight: 400, fill: '#64748B' }, f);

      // More Filters Expanded: Day + Track + Type
      this.pill(f, 'All days', 16, 256, 74, false);
      this.pill(f, '✓ Sun, Dec 13', 96, 256, 108, true, '#D97706');
      this.pill(f, 'BHIM Hall', 210, 256, 76, true, '#0F172A');
      this.pill(f, 'Talk', 292, 256, 52, false);

      // Session List with Live Highlight + Starred/Unstarred
      const talks = [
        { time: '11:00 AM', live: true, title: 'Generative Engine Optimization (GEO)', spk: 'Mayank Kumar · BHIM Hall', starred: true, y: 294 },
        { time: '11:20 AM', live: false, title: '10 Things to Boost Local SEO in AI Era', spk: 'Pankaj Kumar · BHIM Hall', starred: true, y: 392 },
        { time: '02:00 PM', live: false, title: 'Cited but Not Recommended in AI Search', spk: 'Shammi Proothi · BHIM Hall', starred: false, y: 490 },
        { time: '04:00 PM', live: false, title: 'PANEL: The Value Shift in the AI Era', spk: 'Radhika, Amit, Om · Plenary', starred: true, y: 588 },
      ];
      for (const s of talks) {
        this.rect(`Talk_${s.time}`, 16, s.y, 328, 88, {
          fill: s.live ? '#FFEDD5' : '#FFFFFF', stroke: s.live ? '#FF4D2D' : '#E2E8F0', strokeWeight: s.live ? 1.5 : 1, cornerRadius: 14
        }, f);
        this.text(s.live ? `● LIVE NOW · ${s.time}` : s.time, 28, s.y + 10, 180, { fontSize: 9.5, fontWeight: 800, fill: s.live ? '#FF4D2D' : '#EA580C' }, f);
        this.text(s.title, 28, s.y + 28, 254, { fontSize: 12, fontWeight: 700, fill: '#0F172A' }, f);
        this.text(s.spk, 28, s.y + 62, 254, { fontSize: 9.5, fontWeight: 500, fill: '#475569' }, f);
        this.vector('star', 306, s.y + 32, 20, s.starred ? '#D97706' : '#CBD5E1', f, `Star_${s.time}`);
      }

      this.bottomNav(f, 'my-day');
    }

    // M08: Session Detail & 10-Min Reminder
    {
      const f = this.screenFrame('M08 · Session Detail & Reminder', 'Speaker Bio | Saved State | Slides | 10-Min Alert');
      this.statusBar(f, false, 'Offline Ready');
      this.attendeeTopBar(f, 'Session Profile', true);

      // Speaker Bio Card (Circular Clipped Avatar!)
      this.rect('SpkBox', 16, 84, 328, 88, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.avatar(assets.pankaj, 28, 96, 62, f, '#FFEDD5', true);
      this.text('Pankaj Kumar', 102, 100, 224, { fontSize: 15, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Founder @ Pankaj Kumar SEO', 102, 120, 224, { fontSize: 11, fontWeight: 600, fill: '#FF4D2D' }, f);
      this.text('10+ Years · Local SEO & AI Visibility', 102, 138, 224, { fontSize: 10, fontWeight: 500, fill: '#64748B' }, f);

      // Session Details
      this.rect('TalkBox', 16, 184, 328, 246, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Sun, Dec 13 · 11:20 AM (20m)', 28, 198, 190, { fontSize: 10, fontWeight: 700, fill: '#EA580C' }, f);
      this.text('BHIM Auditorium', 226, 198, 104, { fontSize: 10, fontWeight: 700, fill: '#10B981', textAlign: 'right' }, f);
      this.text('10 Things to Do to Boost Local SEO in the AI Era', 28, 220, 300, { fontSize: 15, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('AI search engines are changing how local businesses get discovered. Walk away with 10 practical schema, review, and entity strategies for WordPress.', 28, 272, 300, { fontSize: 11, fontWeight: 400, fill: '#475569' }, f);

      this.rect('StarredBtn', 28, 372, 144, 40, { fill: '#D97706', cornerRadius: 20 }, f);
      this.text('★ SAVED TO MY DAY', 30, 385, 140, { fontSize: 9.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('SlidesBtn', 184, 372, 144, 40, { fill: '#F1F5F9', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 20 }, f);
      this.text('OPEN SLIDES', 186, 385, 140, { fontSize: 10, fontWeight: 700, fill: '#0F172A', textAlign: 'center' }, f);

      // 10-Min Reminder Card
      this.rect('RemBox', 16, 444, 328, 62, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 14 }, f);
      this.text('Remind me 10 mins before', 28, 456, 220, { fontSize: 12, fontWeight: 700, fill: '#0F172A' }, f);
      this.text('Works offline — no internet needed', 28, 476, 220, { fontSize: 10, fontWeight: 600, fill: '#10B981' }, f);
      this.toggle(f, 286, 464, true);

      // Venue Location Pin
      this.rect('LocBox', 16, 518, 328, 62, { fill: '#FAFAFD', stroke: '#CBD5E1', strokeWeight: 1, cornerRadius: 14 }, f);
      this.vector('mapPin', 28, 534, 18, '#FF4D2D', f, 'LocPin');
      this.text('Ground Floor · BHIM Auditorium', 54, 530, 260, { fontSize: 11.5, fontWeight: 700, fill: '#0F172A' }, f);
      this.text('2 min walk from Main Registration Desk', 54, 550, 260, { fontSize: 10, fontWeight: 500, fill: '#64748B' }, f);

      this.bottomNav(f, 'my-day');
    }

    // M09: My Schedule, People Plan & Clash Hub (MyDayTab -> My schedule + Plan!)
    {
      const f = this.screenFrame('M09 · My Schedule & People Plan', 'Clash Warning | Plan Progress | People to Meet');
      this.statusBar(f, false, '5 In My Plan');
      this.attendeeTopBar(f, 'My Schedule', true);

      // Switch: Full vs My Schedule active
      this.pill(f, 'Full schedule', 16, 82, 160, false);
      this.pill(f, 'My schedule (5)', 184, 82, 160, true, '#EA580C');

      // PlanSummary Card (from Plan.kt!)
      this.rect('PlanSum', 16, 118, 328, 68, { fill: '#0F172A', cornerRadius: 16 }, f);
      this.text('Your WordCamp Plan · 2 of 5 Done', 28, 130, 210, { fontSize: 12, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.rect('AddCal', 238, 128, 94, 24, { fill: '#D97706', cornerRadius: 12 }, f);
      this.text('Add to Calendar', 240, 133, 90, { fontSize: 9, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('PBarBG', 28, 162, 304, 6, { fill: '#334155', cornerRadius: 3 }, f);
      this.rect('PBarFill', 28, 162, 122, 6, { fill: '#10B981', cornerRadius: 3 }, f);

      // Plan Filter Chips (All, To do, Done, Missed, Hidden)
      this.pill(f, 'All 5', 16, 196, 56, true, '#0F172A');
      this.pill(f, 'To do 3', 78, 196, 66, false);
      this.pill(f, 'Done 1', 150, 196, 62, false);
      this.pill(f, 'Missed 0', 218, 196, 68, false);
      this.pill(f, 'Hidden 1', 292, 196, 58, false);

      // Conflict Warning Card (from MyDay.kt lines 551-616!)
      this.rect('ClashCard', 16, 232, 328, 92, { fill: '#FFF5F5', stroke: '#FCA5A5', strokeWeight: 1.5, cornerRadius: 14 }, f);
      this.vector('alert', 28, 242, 16, '#DC2626', f, 'ClashIco');
      this.text('Overlapping Talk Conflicts Detected', 50, 243, 270, { fontSize: 11, fontWeight: 800, fill: '#DC2626' }, f);
      this.text('GEO Talk (BHIM) vs SEO Workshop (Lounge) · 11:00 AM', 28, 264, 300, { fontSize: 9.5, fontWeight: 500, fill: '#0F172A' }, f);
      this.rect('RemBtn', 28, 288, 114, 24, { fill: '#FFFFFF', stroke: '#DC2626', strokeWeight: 1, cornerRadius: 12 }, f);
      this.text('Remove One', 30, 293, 110, { fontSize: 9, fontWeight: 800, fill: '#DC2626', textAlign: 'center' }, f);

      // Saved Session with Attendance Status Buttons (from MyDay.kt lines 413-418)
      this.rect('SessSaved', 16, 336, 328, 98, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('10:00 AM · BHIM Auditorium', 28, 346, 220, { fontSize: 9.5, fontWeight: 700, fill: '#EA580C' }, f);
      this.text('From Feature to Ecosystem', 28, 364, 290, { fontSize: 12.5, fontWeight: 700, fill: '#0F172A' }, f);
      this.rect('AttBtn', 28, 394, 96, 26, { fill: '#ECFDF5', stroke: '#10B981', strokeWeight: 1, cornerRadius: 13 }, f);
      this.text('✓ Attended', 30, 400, 92, { fontSize: 9.5, fontWeight: 800, fill: '#10B981', textAlign: 'center' }, f);
      this.rect('MissBtn', 132, 394, 86, 26, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 13 }, f);
      this.text('✗ Missed', 134, 400, 82, { fontSize: 9.5, fontWeight: 600, fill: '#64748B', textAlign: 'center' }, f);

      // People to Meet Section (PersonPlanCard from Plan.kt!)
      this.text('People to Meet', 16, 448, 200, { fontSize: 13, fontWeight: 800, fill: '#0F172A' }, f);
      this.rect('MeetCard', 16, 474, 328, 128, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.avatar(assets.radhika, 28, 488, 44, f, '#E2E8F0', true);
      this.text('Radhika Bhama', 82, 488, 180, { fontSize: 13, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('01:30 PM · Dining Courtyard Coffee', 82, 506, 240, { fontSize: 10, fontWeight: 600, fill: '#EA580C' }, f);
      this.text('Note: Discuss WordPress product partnerships.', 28, 540, 300, { fontSize: 10, fontWeight: 400, fill: '#64748B' }, f);
      this.rect('MetBtn', 28, 564, 90, 26, { fill: '#ECFDF5', stroke: '#10B981', strokeWeight: 1, cornerRadius: 13 }, f);
      this.text('✓ Met', 30, 570, 86, { fontSize: 9.5, fontWeight: 800, fill: '#10B981', textAlign: 'center' }, f);
      this.rect('MissP', 126, 564, 90, 26, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 13 }, f);
      this.text('Missed', 128, 570, 86, { fontSize: 9.5, fontWeight: 600, fill: '#64748B', textAlign: 'center' }, f);
      this.rect('SkipP', 224, 564, 90, 26, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 13 }, f);
      this.text('Skip', 226, 570, 86, { fontSize: 9.5, fontWeight: 600, fill: '#64748B', textAlign: 'center' }, f);

      this.bottomNav(f, 'my-day');
    }

    // M10: Memories Archive & Post-Event Data Choice
    {
      const f = this.screenFrame('M10 · Memories & Post-Event Choice', 'Photo Album | WordPress.tv | Keep vs Auto-Sweep');
      this.statusBar(f, false, 'After Event');
      this.attendeeTopBar(f, 'Memories', true);

      // Event Header Card with White-Framed Delhi Logo
      this.rect('MemHdr', 16, 84, 328, 92, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.logoBox(assets.delhiLogo, 28, 96, 68, 68, f, 14, 6);
      this.text('MEMORIES', 108, 98, 120, { fontSize: 9.5, fontWeight: 800, fill: '#FF4D2D' }, f);
      this.text('WordCamp Delhi 2026', 108, 114, 220, { fontSize: 15, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Dec 12–13, 2026', 108, 138, 220, { fontSize: 10.5, fontWeight: 500, fill: '#64748B' }, f);

      // Album & WordPress.tv Links
      this.rect('AlbumCard', 16, 188, 328, 66, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 14 }, f);
      this.vector('camera', 28, 208, 22, '#FF4D2D', f, 'CamIco');
      this.text('Official Photo Album', 60, 198, 220, { fontSize: 12.5, fontWeight: 700, fill: '#0F172A' }, f);
      this.text('The official photos — find yourself in them.', 60, 218, 240, { fontSize: 10, fontWeight: 400, fill: '#64748B' }, f);
      this.vector('chevronRight', 318, 212, 16, '#94A3B8', f, 'Ar1');

      this.rect('WptvCard', 16, 264, 328, 66, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 14 }, f);
      this.vector('activity', 28, 284, 22, '#D97706', f, 'VidIco');
      this.text('Talks on WordPress.tv', 60, 274, 220, { fontSize: 12.5, fontWeight: 700, fill: '#0F172A' }, f);
      this.text('Watch speaker recordings anytime.', 60, 294, 240, { fontSize: 10, fontWeight: 400, fill: '#64748B' }, f);
      this.vector('chevronRight', 318, 288, 16, '#94A3B8', f, 'Ar2');

      // AfterEventChoice Privacy Sweep Modal (from AfterEvent.kt!)
      this.rect('SweepBox', 16, 344, 328, 250, { fill: '#FFFFFF', stroke: '#D97706', strokeWeight: 1.5, cornerRadius: 18 }, f);
      this.text('What should we do with your event data?', 28, 358, 300, { fontSize: 13, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Scheduled cleanup on Dec 28, 2026.', 28, 378, 280, { fontSize: 10, fontWeight: 600, fill: '#EA580C' }, f);

      this.rect('OptKeep', 28, 404, 304, 72, { fill: '#FFEDD5', stroke: '#FF4D2D', strokeWeight: 1.5, cornerRadius: 12 }, f);
      this.text('● Keep My People & CampCard', 40, 414, 260, { fontSize: 11.5, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Keep scanned contacts & digital card for future WordCamps.', 40, 434, 276, { fontSize: 9.5, fontWeight: 500, fill: '#475569' }, f);

      this.rect('OptSweep', 28, 486, 304, 72, { fill: '#FAFAFD', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 12 }, f);
      this.text('○ Clean up everything automatically', 40, 496, 260, { fontSize: 11.5, fontWeight: 700, fill: '#0F172A' }, f);
      this.text('Erase schedule, waves & event notes after 14 days.', 40, 516, 276, { fontSize: 9.5, fontWeight: 500, fill: '#64748B' }, f);

      this.rect('SaveChoice', 16, 608, 328, 44, { fill: '#0F172A', cornerRadius: 22 }, f);
      this.text('SAVE PREFERENCE', 16, 622, 328, { fontSize: 11, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);

      this.bottomNav(f, 'home');
    }

    // M11: Help Sheet, Legal & Confidential Safety Report
    {
      const f = this.screenFrame('M11 · Help Desk, Legal & Safety Report', 'HelpDesk | Privacy & Terms | Confidential Report');
      this.statusBar(f, false, '24/7 Support');
      this.attendeeTopBar(f, 'Support & Safety', true);

      // Tabs: Help Desk | Privacy | Terms | Code of Conduct
      this.pill(f, 'Help Desk', 16, 82, 78, true, '#DC2626');
      this.pill(f, 'Privacy', 100, 82, 68, false);
      this.pill(f, 'Terms', 174, 82, 64, false);
      this.pill(f, 'Safety Code', 244, 82, 92, false);

      // 1-Tap Organizer Call Card
      this.rect('SOSBox', 16, 120, 328, 118, { fill: '#DC2626', cornerRadius: 16 }, f);
      this.vector('shield', 28, 134, 22, '#FFFFFF', f, 'ShIco');
      this.text('Need Immediate Support?', 58, 134, 240, { fontSize: 15, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('Reach the Venue Info Desk, Medical Support or Safety Team anytime.', 28, 162, 300, { fontSize: 10, fontWeight: 400, fill: '#FEE2E2' }, f);
      this.rect('CallBtn', 28, 194, 304, 34, { fill: '#FFFFFF', cornerRadius: 17 }, f);
      this.vector('phone', 74, 203, 16, '#DC2626', f, 'PhBtn');
      this.text('CALL ORGANIZER DESK', 96, 204, 190, { fontSize: 10, fontWeight: 800, fill: '#DC2626' }, f);

      // Venue Support Rooms (Clean vector icons!)
      this.rect('FacBox', 16, 250, 328, 96, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 14 }, f);
      this.text('On-Site Support Facilities (DAIC)', 28, 262, 260, { fontSize: 11.5, fontWeight: 800, fill: '#0F172A' }, f);
      this.vector('heart', 28, 286, 14, '#DC2626', f, 'AidIco');
      this.text('First Aid Room: Ground Floor Main Lobby', 48, 286, 280, { fontSize: 10, fontWeight: 500, fill: '#475569' }, f);
      this.vector('bell', 28, 310, 14, '#D97706', f, 'QtIco');
      this.text('Quiet & Nursing Room: 1st Floor, Room 104', 48, 310, 280, { fontSize: 10, fontWeight: 500, fill: '#475569' }, f);

      // Confidential Incident Report Form
      this.rect('FormBox', 16, 358, 328, 274, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('File Confidential Incident Report', 28, 372, 280, { fontSize: 12.5, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Category: Safety / Harassment / Accessibility', 28, 394, 290, { fontSize: 10, fontWeight: 600, fill: '#EA580C' }, f);
      this.rect('TextBox', 28, 416, 304, 100, { fill: '#F8FAFC', stroke: '#CBD5E1', strokeWeight: 1, cornerRadius: 10 }, f);
      this.text('Describe what happened and where (e.g., Hall A Corridor)...', 38, 428, 284, { fontSize: 10, fontWeight: 400, fill: '#94A3B8' }, f);
      this.text('☑ Keep my report anonymous', 28, 528, 260, { fontSize: 10, fontWeight: 600, fill: '#0F172A' }, f);
      this.rect('SubRep', 28, 558, 304, 40, { fill: '#0F172A', cornerRadius: 20 }, f);
      this.text('SEND CONFIDENTIAL REPORT', 30, 571, 300, { fontSize: 10, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.text('Works offline — sends automatically when connected', 28, 608, 300, { fontSize: 9.5, fontWeight: 500, fill: '#10B981' }, f);
    }

    // =========================================================================
    // GROUP 3: CAMPCARD, QR SCANNER & DISCOVERY (M12 - M16)
    // =========================================================================

    // M12: CampCard 7-Layout Carousel & Real QR Code
    {
      const f = this.screenFrame('M12 · CampCard Carousel & Real QR', '7 Card Layouts | Real QR | Share & Save PNG');
      this.statusBar(f, false, 'My CampCard');
      this.attendeeTopBar(f, 'My CampCard', true);

      // Verified Profile Banner (ClaimProfileBanner from CampCardScreen.kt)
      this.rect('ClaimDone', 16, 82, 328, 48, { fill: '#FFFFFF', stroke: '#10B981', strokeWeight: 1.5, cornerRadius: 14 }, f);
      this.vector('award', 28, 96, 20, '#10B981', f, 'VerIco');
      this.text('Verified Attendee · Amit Singh', 56, 92, 190, { fontSize: 11, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Linked to WordCamp Delhi roster', 56, 108, 190, { fontSize: 9.5, fontWeight: 500, fill: '#64748B' }, f);
      this.text('Unlink', 292, 98, 44, { fontSize: 10, fontWeight: 700, fill: '#DC2626', textAlign: 'right' }, f);

      // 7 Layout Pills (from CampCardFaces.kt: Ticket, Classic, Minimal, Bold, Split, Badge, Pass)
      this.pill(f, 'Ticket', 16, 138, 56, false);
      this.pill(f, 'Classic', 76, 138, 62, true, '#0F172A');
      this.pill(f, 'Minimal', 142, 138, 62, false);
      this.pill(f, 'Bold', 208, 138, 48, false);
      this.pill(f, 'Split', 260, 138, 46, false);
      this.pill(f, 'Pass', 310, 138, 42, false);

      // Classic Card Face with Universal White Logo Container + Circular Avatar + REAL QR PNG!
      this.rect('CardFace', 26, 174, 308, 366, { fill: '#1E1E1E', stroke: '#D97706', strokeWeight: 2, cornerRadius: 20 }, f);
      this.logoBox(assets.delhiLogo, 42, 190, 42, 42, f, 10, 4);
      this.rect('RoleTag', 228, 196, 90, 22, { fill: '#FEF3C7', cornerRadius: 11 }, f);
      this.text('SPEAKER', 230, 201, 86, { fontSize: 9, fontWeight: 800, fill: '#B45309', textAlign: 'center' }, f);

      this.avatar(assets.amit, 142, 206, 76, f, '#D97706', false);
      this.text('Amit Kumar Singh', 42, 290, 276, { fontSize: 17, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.text('Managing Partner @ WPoets · Pune', 42, 314, 276, { fontSize: 10, fontWeight: 500, fill: '#CBD5E1', textAlign: 'center' }, f);

      // REAL QR Code Image inside White Plate!
      this.rect('QrPlate', 110, 340, 140, 140, { fill: '#FFFFFF', cornerRadius: 12 }, f);
      this.image(assets.qrSample, 118, 348, 124, 124, f, 'RealQR');
      this.text('Scan to open CampCard or save contact', 42, 492, 276, { fontSize: 9.5, fontWeight: 500, fill: '#F59E0B', textAlign: 'center' }, f);

      // Share & Save PNG Buttons
      this.rect('SharePng', 26, 552, 148, 38, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 19 }, f);
      this.vector('share', 52, 563, 16, '#0F172A', f, 'ShIco');
      this.text('Share', 76, 565, 60, { fontSize: 10.5, fontWeight: 700, fill: '#0F172A' }, f);

      this.rect('SavePng', 186, 552, 148, 38, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 19 }, f);
      this.vector('download', 208, 563, 16, '#0F172A', f, 'DlIco');
      this.text('Download', 230, 565, 70, { fontSize: 10.5, fontWeight: 700, fill: '#0F172A' }, f);

      // Active Social Channels in QR (from CampCardScreen.kt ActiveSocialBadges)
      this.text('ACTIVE CHANNELS IN QR:', 26, 604, 200, { fontSize: 9, fontWeight: 800, fill: '#64748B' }, f);
      this.pill(f, '● LinkedIn', 26, 624, 82, false);
      this.pill(f, '● WordPress.org', 114, 624, 110, false);
      this.pill(f, '● X / Twitter', 230, 624, 96, false);

      this.bottomNav(f, 'camp-card');
    }

    // M13: CampCard Edit Form & Claim Attendee Profile Picker
    {
      const f = this.screenFrame('M13 · Edit CampCard & Claim Profile', 'Claim Roster Picker | Tag Chips | Privacy Switches');
      this.statusBar(f, false, 'Saved On Phone');
      this.attendeeTopBar(f, 'Edit CampCard', true);

      // Claim Profile Banner + SharePicker ("Which name on the list is yours?")
      this.rect('ClaimBox', 16, 82, 328, 150, { fill: '#FFEDD5', stroke: '#FF4D2D', strokeWeight: 1.5, cornerRadius: 16 }, f);
      this.text('Claim Your Attendee Profile', 28, 94, 240, { fontSize: 12.5, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Which name on the WordCamp list is yours?', 28, 114, 280, { fontSize: 10, fontWeight: 500, fill: '#475569' }, f);
      this.rect('PickSearch', 28, 134, 304, 32, { fill: '#FFFFFF', stroke: '#CBD5E1', strokeWeight: 1, cornerRadius: 8 }, f);
      this.text('Sunil...', 38, 143, 200, { fontSize: 10.5, fontWeight: 600, fill: '#0F172A' }, f);
      this.rect('MatchRow', 28, 174, 304, 44, { fill: '#FFFFFF', stroke: '#10B981', strokeWeight: 1.5, cornerRadius: 10 }, f);
      this.text('Sunil Kumar Sharma (Ticket #482)', 38, 182, 220, { fontSize: 10.5, fontWeight: 700, fill: '#0F172A' }, f);
      this.text('Tap to link your CampCard to the list', 38, 198, 220, { fontSize: 9, fontWeight: 500, fill: '#10B981' }, f);

      // Form Fields (About You)
      this.rect('FormBG', 16, 244, 328, 188, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('About You (Saved on this phone only)', 28, 256, 280, { fontSize: 12, fontWeight: 800, fill: '#0F172A' }, f);
      this.rect('F1', 28, 280, 304, 34, { fill: '#FAFAFD', stroke: '#CBD5E1', strokeWeight: 1, cornerRadius: 8 }, f);
      this.text('Name: Amit Kumar Singh *', 38, 290, 280, { fontSize: 10.5, fontWeight: 600, fill: '#0F172A' }, f);
      this.rect('F2', 28, 322, 304, 34, { fill: '#FAFAFD', stroke: '#CBD5E1', strokeWeight: 1, cornerRadius: 8 }, f);
      this.text('Role: Managing Partner · WPoets', 38, 332, 280, { fontSize: 10.5, fontWeight: 500, fill: '#475569' }, f);
      this.text('Interests (TagInput):', 28, 366, 200, { fontSize: 10, fontWeight: 700, fill: '#0F172A' }, f);
      this.pill(f, 'AI & WordPress ×', 28, 388, 108, true, '#0F172A');
      this.pill(f, 'Enterprise ×', 142, 388, 86, true, '#0F172A');
      this.pill(f, '+ Add tag', 234, 388, 76, false);

      // Visibility Switches + Save Button
      this.rect('VisBox', 16, 444, 328, 128, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Show on CampCard QR & Attendee List', 28, 456, 280, { fontSize: 11.5, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Share Role & Company', 28, 482, 200, { fontSize: 10.5, fontWeight: 600, fill: '#0F172A' }, f);
      this.toggle(f, 288, 478, true);
      this.text('List my card in Attendees Directory', 28, 514, 220, { fontSize: 10.5, fontWeight: 600, fill: '#0F172A' }, f);
      this.toggle(f, 288, 510, true);
      this.text('☑ I agree to share selected fields with attendees', 28, 544, 300, { fontSize: 9.5, fontWeight: 600, fill: '#10B981' }, f);

      this.rect('SaveCard', 16, 588, 328, 44, { fill: '#FF4D2D', cornerRadius: 22 }, f);
      this.text('SAVE CAMP CARD', 16, 602, 328, { fontSize: 11, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);

      this.bottomNav(f, 'camp-card');
    }

    // M14: Native QR Scanner & Detected Contact Sheet
    {
      const f = this.screenFrame('M14 · Fast QR Scanner & Contact Sheet', 'Live Viewfinder | Real QR Target | Saved Sheet', '#000000');
      this.statusBar(f, true, 'Camera Active');
      this.text('< Back', 20, 48, 80, { fontSize: 13, fontWeight: 700, fill: '#FFFFFF' }, f);
      this.rect('Torch', 268, 44, 72, 26, { fill: '#1E293B', stroke: '#F59E0B', strokeWeight: 1, cornerRadius: 13 }, f);
      this.vector('sparkles', 278, 50, 14, '#F59E0B', f, 'TrchIco');
      this.text('Flash', 296, 50, 40, { fontSize: 10, fontWeight: 700, fill: '#F59E0B' }, f);

      // Viewfinder framing a REAL QR card target!
      this.rect('FrameBox', 60, 118, 240, 240, { fill: '#111827', stroke: '#FF4D2D', strokeWeight: 2.5, cornerRadius: 24 }, f);
      this.rect('QrTarget', 100, 158, 160, 160, { fill: '#FFFFFF', cornerRadius: 14 }, f);
      this.image(assets.qrSample, 110, 168, 140, 140, f, 'TargetQR');
      this.line('Laser', 74, 238, 212, { stroke: '#FF4D2D', strokeWeight: 3 }, f);

      this.text('Point at any attendee CampCard or sponsor booth', 30, 376, 300, { fontSize: 11, fontWeight: 600, fill: '#E2E8F0', textAlign: 'center' }, f);

      // Detected Contact Bottom Sheet (Circular Clipped Radhika Photo!)
      this.rect('DetSheet', 0, 446, 360, 314, { fill: '#1E293B', cornerRadius: 24 }, f);
      this.rect('Handle', 156, 458, 48, 4, { fill: '#475569', cornerRadius: 2 }, f);
      this.avatar(assets.radhika, 24, 484, 58, f, '#10B981', true);
      this.text('Radhika Bhama', 94, 488, 220, { fontSize: 16, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('Speaker · WordCamp Delhi 2026', 94, 510, 240, { fontSize: 11, fontWeight: 500, fill: '#CBD5E1' }, f);
      this.text('Shared: LinkedIn, X, Bio · Stored on your phone', 94, 530, 240, { fontSize: 9.5, fontWeight: 600, fill: '#10B981' }, f);

      this.rect('SaveBtn', 24, 568, 312, 46, { fill: '#D97706', cornerRadius: 23 }, f);
      this.text('SAVE TO MY PEOPLE', 24, 584, 312, { fontSize: 11, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('NextBtn', 24, 626, 312, 42, { fill: '#334155', cornerRadius: 21 }, f);
      this.text('SCAN ANOTHER CODE', 24, 640, 312, { fontSize: 10.5, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);
    }

    // M15: Explore -> People Discovery & Attendee Roster (Discovery + Roster!)
    {
      const f = this.screenFrame('M15 · Explore: Discovery & Roster', 'Join Discovery | Match Waves | Role Filter Pills');
      this.statusBar(f, false, '11 Verified');
      this.exploreHeader(f, 'people');

      // Discovery Top Prompt (for users not yet joined)
      this.rect('JoinBanner', 16, 148, 328, 72, { fill: '#0F172A', cornerRadius: 14 }, f);
      this.text('Meet People Sharing Your Interests', 28, 158, 220, { fontSize: 12, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('Create a private discovery card & wave at matches.', 28, 178, 210, { fontSize: 9.5, fontWeight: 400, fill: '#94A3B8' }, f);
      this.rect('JoinBtn', 244, 166, 88, 32, { fill: '#FF4D2D', cornerRadius: 16 }, f);
      this.text('JOIN NOW', 246, 175, 84, { fontSize: 9.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);

      // Discovery Match Cards (Default Wave + Mutual Chat Unlocked)
      this.rect('M1', 16, 230, 328, 108, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 14 }, f);
      this.avatar(assets.abhay, 28, 242, 46, f, '#E2E8F0', true);
      this.text('Abhay Kulkarni', 84, 240, 170, { fontSize: 13.5, fontWeight: 800, fill: '#0F172A' }, f);
      this.rect('Match92', 262, 240, 70, 20, { fill: '#ECFDF5', cornerRadius: 10 }, f);
      this.text('92% Match', 264, 244, 66, { fontSize: 9, fontWeight: 800, fill: '#10B981', textAlign: 'center' }, f);
      this.text('n8n Ambassador · AI Workflows', 84, 260, 240, { fontSize: 10, fontWeight: 500, fill: '#64748B' }, f);
      this.rect('WaveB', 28, 298, 144, 28, { fill: '#FF4D2D', cornerRadius: 14 }, f);
      this.text('WAVE HELLO', 30, 305, 140, { fontSize: 9.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('MeetB', 184, 298, 144, 28, { fill: '#F1F5F9', cornerRadius: 14 }, f);
      this.text('+ MEET', 186, 305, 140, { fontSize: 9.5, fontWeight: 700, fill: '#0F172A', textAlign: 'center' }, f);

      // Mutual Match Card (Chat Unlocked!)
      this.rect('M2', 16, 348, 328, 104, { fill: '#FFFFFF', stroke: '#10B981', strokeWeight: 1.5, cornerRadius: 14 }, f);
      this.avatar(assets.om, 28, 360, 46, f, '#10B981', true);
      this.text('Omprakash Asnani', 84, 358, 170, { fontSize: 13.5, fontWeight: 800, fill: '#0F172A' }, f);
      this.rect('MutPill', 256, 358, 76, 20, { fill: '#10B981', cornerRadius: 10 }, f);
      this.text('MUTUAL ✓', 258, 362, 72, { fontSize: 9, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.text('AI Workflows Lead @ Outskill', 84, 378, 240, { fontSize: 10, fontWeight: 500, fill: '#64748B' }, f);
      this.rect('ChatB', 28, 412, 144, 28, { fill: '#0F172A', cornerRadius: 14 }, f);
      this.text('OPEN CHAT', 30, 419, 140, { fontSize: 9.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('RepB', 184, 412, 144, 28, { fill: '#FFF5F5', stroke: '#FCA5A5', strokeWeight: 1, cornerRadius: 14 }, f);
      this.text('HIDE / REPORT', 186, 419, 140, { fontSize: 9, fontWeight: 700, fill: '#DC2626', textAlign: 'center' }, f);

      // Full Attendee Roster + Role Filter Pills (from MoreTabs.kt lines 704-760)
      this.text('Attendee Roster (420)', 16, 464, 200, { fontSize: 12, fontWeight: 800, fill: '#0F172A' }, f);
      this.pill(f, 'All', 16, 488, 44, true, '#0F172A');
      this.pill(f, 'Speakers 11', 66, 488, 84, false);
      this.pill(f, 'Organizers 13', 156, 488, 92, false);
      this.pill(f, 'Table Leads', 254, 488, 86, false);

      this.rect('Ros1', 16, 524, 328, 84, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 14 }, f);
      this.avatar(assets.shreya, 28, 540, 44, f, '#E2E8F0', false);
      this.text('Shreya Agarwal', 82, 536, 170, { fontSize: 12.5, fontWeight: 800, fill: '#0F172A' }, f);
      this.rect('BadgeSpk', 82, 558, 70, 18, { fill: '#FEF3C7', cornerRadius: 9 }, f);
      this.text('Table Lead', 84, 561, 66, { fontSize: 8.5, fontWeight: 800, fill: '#B45309', textAlign: 'center' }, f);
      this.rect('OpenMeet', 158, 558, 92, 18, { fill: '#ECFDF5', cornerRadius: 9 }, f);
      this.text('Open to meet', 160, 561, 88, { fontSize: 8.5, fontWeight: 700, fill: '#065F46', textAlign: 'center' }, f);
      this.rect('MeetRos', 268, 550, 64, 28, { fill: '#FFEDD5', cornerRadius: 14 }, f);
      this.text('+ Meet', 270, 557, 60, { fontSize: 9.5, fontWeight: 800, fill: '#EA580C', textAlign: 'center' }, f);

      this.bottomNav(f, 'explore');
    }

    // M16: Discovery Join Form, Wave, Chat & Meet Modals
    {
      const f = this.screenFrame('M16 · Join Form, Wave, Chat & Meet', '3 Identity Modes | Tags | Wave | Chat | MeetSheet');
      this.statusBar(f, false, 'Discovery Flow');
      this.attendeeTopBar(f, 'Join & Connect', true);

      // Join Discovery Form Card (from People.kt JoinForm!)
      this.rect('JoinForm', 16, 82, 328, 224, { fill: '#FFFFFF', stroke: '#FF4D2D', strokeWeight: 1.5, cornerRadius: 16 }, f);
      this.text('Join Attendee Discovery', 28, 94, 240, { fontSize: 13, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('How should you appear?', 28, 114, 200, { fontSize: 10, fontWeight: 600, fill: '#64748B' }, f);
      this.pill(f, 'Pick my name', 28, 134, 96, true, '#0F172A');
      this.pill(f, 'Type name', 130, 134, 84, false);
      this.pill(f, 'Anonymous', 220, 134, 92, false);
      this.text('Interests (pick up to 5):', 28, 170, 200, { fontSize: 10, fontWeight: 600, fill: '#64748B' }, f);
      this.pill(f, 'AI & WordPress', 28, 190, 102, true, '#D97706');
      this.pill(f, 'SEO', 136, 190, 52, true, '#D97706');
      this.pill(f, 'Community', 194, 190, 82, false);
      this.text('Who to meet: Agency founders, Core contributors', 28, 226, 300, { fontSize: 10, fontWeight: 500, fill: '#0F172A' }, f);
      this.text('☑ Share my card only with discovery attendees', 28, 248, 300, { fontSize: 9.5, fontWeight: 600, fill: '#10B981' }, f);
      this.rect('JoinSubmit', 28, 268, 304, 28, { fill: '#FF4D2D', cornerRadius: 14 }, f);
      this.text('JOIN ATTENDEE DISCOVERY', 30, 275, 300, { fontSize: 9.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);

      // Wave + Chat Modal Preview
      this.rect('ChatCard', 16, 318, 328, 172, { fill: '#0F172A', cornerRadius: 16 }, f);
      this.text('Mutual Match Chat (Omprakash Asnani)', 28, 330, 280, { fontSize: 11.5, fontWeight: 800, fill: '#10B981' }, f);
      this.rect('Msg1', 28, 354, 210, 30, { fill: '#1E293B', cornerRadius: 10 }, f);
      this.text('Om: Let’s meet after the AI panel!', 36, 362, 194, { fontSize: 10, fontWeight: 500, fill: '#FFFFFF' }, f);
      this.rect('Msg2', 120, 390, 212, 30, { fill: '#D97706', cornerRadius: 10 }, f);
      this.text('You: See you at the Coffee Courtyard!', 128, 398, 196, { fontSize: 10, fontWeight: 600, fill: '#FFFFFF' }, f);
      this.rect('InputC', 28, 434, 238, 34, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 17 }, f);
      this.text('Write a short message...', 40, 444, 200, { fontSize: 10, fontWeight: 400, fill: '#94A3B8' }, f);
      this.rect('SendC', 274, 434, 58, 34, { fill: '#10B981', cornerRadius: 17 }, f);
      this.text('SEND', 276, 444, 54, { fontSize: 9.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);

      // Meet Scheduler Sheet
      this.rect('MeetBox', 16, 502, 328, 134, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Schedule a Meet (MeetSheet)', 28, 514, 240, { fontSize: 12, fontWeight: 800, fill: '#0F172A' }, f);
      this.pill(f, '01:30 PM · Coffee', 28, 538, 114, true, '#0F172A');
      this.pill(f, '03:30 PM · Booth', 148, 538, 114, false);
      this.rect('ConfM', 28, 578, 144, 30, { fill: '#ECFDF5', stroke: '#10B981', strokeWeight: 1, cornerRadius: 15 }, f);
      this.text('CONFIRM MEET', 30, 586, 140, { fontSize: 9.5, fontWeight: 800, fill: '#10B981', textAlign: 'center' }, f);
      this.rect('DecM', 184, 578, 144, 30, { fill: '#FFF5F5', stroke: '#FCA5A5', strokeWeight: 1, cornerRadius: 15 }, f);
      this.text('DECLINE', 186, 586, 140, { fontSize: 9.5, fontWeight: 700, fill: '#DC2626', textAlign: 'center' }, f);

      this.bottomNav(f, 'explore');
    }

    // =========================================================================
    // GROUP 4: EXPLORE TABS — SPONSORS, DEALS, STEALS, CONTRIBUTE, QUESTS, INFO (M17 - M22)
    // =========================================================================

    // M17: Explore -> Sponsors Tiered Grid (Real Logos in Universal White Boxes!)
    {
      const f = this.screenFrame('M17 · Explore: Sponsors Tiered Grid', 'Real Sponsor Logos | Bronze, Startup & After Party');
      this.statusBar(f, false, '17 Sponsors');
      this.exploreHeader(f, 'sponsors');

      this.text('Stop by sponsor booths for demos, swag & open jobs.', 16, 150, 328, { fontSize: 10, fontWeight: 500, fill: '#64748B' }, f);

      // Bronze Tier
      this.text('BRONZE & PLATFORM SPONSORS', 16, 178, 260, { fontSize: 10, fontWeight: 800, fill: '#EA580C' }, f);
      this.logoBox(assets.sponsor_elementor, 16, 200, 158, 72, f, 12, 12);
      this.logoBox(assets.sponsor_nexgi, 186, 200, 158, 72, f, 12, 12);
      this.logoBox(assets.sponsor_cookieadmin, 16, 284, 158, 72, f, 12, 12);

      // Startup Tier
      this.text('STARTUP & COMMUNITY SPONSORS', 16, 374, 260, { fontSize: 10, fontWeight: 800, fill: '#0F172A' }, f);
      this.logoBox(assets.sponsor_housewise, 16, 396, 158, 72, f, 12, 12);
      this.logoBox(assets.sponsor_zuptek, 186, 396, 158, 72, f, 12, 12);

      // After Party Tier
      this.text('AFTER PARTY SPONSOR', 16, 486, 260, { fontSize: 10, fontWeight: 800, fill: '#D97706' }, f);
      this.logoBox(assets.sponsor_wpfusion, 16, 508, 328, 84, f, 14, 14);

      this.bottomNav(f, 'explore');
    }

    // M18: Explore -> Deals (Signature Tear-Line Coupon Cards & Lead Form!)
    {
      const f = this.screenFrame('M18 · Explore: Deals & Lead Form', 'Tear-Line Coupons | Copy Code | Short Lead Form');
      this.statusBar(f, false, '8 Active Deals');
      this.exploreHeader(f, 'deals');

      // Tear-Line Deal Card 1 (Elementor)
      this.rect('Deal1', 16, 150, 328, 224, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 18 }, f);
      this.logoBox(assets.sponsor_elementor, 28, 164, 52, 52, f, 10, 6);
      this.text('Elementor Ltd', 90, 168, 150, { fontSize: 13, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('elementor.com', 90, 188, 150, { fontSize: 10, fontWeight: 500, fill: '#64748B' }, f);
      this.rect('HotTag', 248, 166, 84, 22, { fill: '#FFFFFF', stroke: '#FF4D2D', strokeWeight: 1.5, cornerRadius: 6 }, f);
      this.text('HOT DEAL', 250, 171, 80, { fontSize: 9, fontWeight: 800, fill: '#FF4D2D', textAlign: 'center' }, f);

      this.text('3 Months Elementor One Free for Attendees', 28, 226, 300, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);
      // Tear Line with Details Pill!
      this.line('TearLine', 26, 272, 308, { stroke: '#CBD5E1', strokeWeight: 1.5, dashes: [6, 4] }, f);
      this.rect('DetPill', 136, 260, 88, 24, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 12 }, f);
      this.text('Details ▾', 138, 265, 84, { fontSize: 9.5, fontWeight: 700, fill: '#FF4D2D', textAlign: 'center' }, f);

      // Coupon Code Box
      this.rect('CodeBox', 28, 294, 304, 34, { fill: '#FFEDD5', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 10 }, f);
      this.text('Code: WCDELHI26', 40, 304, 180, { fontSize: 11.5, fontWeight: 800, fill: '#0F172A' }, f);
      this.rect('CopyB', 256, 299, 66, 24, { fill: '#FFFFFF', stroke: '#CBD5E1', strokeWeight: 1, cornerRadius: 12 }, f);
      this.text('Copied ✓', 258, 304, 62, { fontSize: 9, fontWeight: 700, fill: '#10B981', textAlign: 'center' }, f);

      this.rect('GetDeal', 28, 336, 304, 28, { fill: '#FF4D2D', cornerRadius: 14 }, f);
      this.text('GET THE DEAL →', 30, 343, 300, { fontSize: 9.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);

      // Lead Capture Dialog Preview (Short form first!)
      this.rect('LeadModal', 16, 388, 328, 240, { fill: '#0F172A', stroke: '#D97706', strokeWeight: 1.5, cornerRadius: 18 }, f);
      this.text('Quick Sponsor Form (LeadDialog)', 28, 402, 280, { fontSize: 12.5, fontWeight: 800, fill: '#F59E0B' }, f);
      this.text('WP Fusion asks for a quick intro before opening your 30% discount:', 28, 424, 300, { fontSize: 10, fontWeight: 400, fill: '#CBD5E1' }, f);
      this.rect('LF1', 28, 456, 304, 34, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 8 }, f);
      this.text('Work Email: sunil@wpsimplified.com', 38, 466, 280, { fontSize: 10.5, fontWeight: 500, fill: '#FFFFFF' }, f);
      this.rect('LF2', 28, 498, 304, 34, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 8 }, f);
      this.text('Agency Size: 10–25 Builders', 38, 508, 280, { fontSize: 10.5, fontWeight: 500, fill: '#FFFFFF' }, f);
      this.rect('SubLead', 28, 548, 180, 34, { fill: '#10B981', cornerRadius: 17 }, f);
      this.text('SEND & OPEN DEAL ↗', 30, 558, 176, { fontSize: 9.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('CanLead', 218, 548, 114, 34, { fill: '#334155', cornerRadius: 17 }, f);
      this.text('CANCEL', 220, 558, 110, { fontSize: 9.5, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);

      this.bottomNav(f, 'explore');
    }

    // M19: Explore -> Free Steals & Steal Sheet
    {
      const f = this.screenFrame('M19 · Explore: Free Steals & Sheet', 'Free Tools | Featured Tag | Made Here | Sheet');
      this.statusBar(f, false, 'Free Steals');
      this.exploreHeader(f, 'steals');

      this.text('Great free WordPress tools picked by CampBuddy.', 16, 150, 328, { fontSize: 10, fontWeight: 500, fill: '#64748B' }, f);

      // Steal Rows
      const steals = [
        { img: assets.sponsor_cookieadmin, name: 'CookieAdmin DPDP Shield', maker: 'by CookieAdmin Team', feat: true, y: 178 },
        { img: assets.sponsor_wpfusion, name: 'WP Fusion Lite Sync', maker: 'by VeryGoodPlugins', feat: false, y: 256 },
      ];
      for (const s of steals) {
        this.rect(`St_${s.name}`, 16, s.y, 328, 68, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 14 }, f);
        this.logoBox(s.img, 26, s.y + 10, 48, 48, f, 10, 6);
        this.text(s.name, 84, s.y + 14, 170, { fontSize: 12, fontWeight: 700, fill: '#0F172A' }, f);
        this.text(s.maker, 84, s.y + 34, 170, { fontSize: 9.5, fontWeight: 500, fill: '#64748B' }, f);
        if (s.feat) {
          this.rect('Feat', 256, s.y + 22, 66, 20, { fill: '#FFFFFF', stroke: '#FF4D2D', strokeWeight: 1.5, cornerRadius: 6 }, f);
          this.text('FEATURED', 258, s.y + 26, 62, { fontSize: 8, fontWeight: 800, fill: '#FF4D2D', textAlign: 'center' }, f);
        }
        this.vector('chevronRight', 324, s.y + 26, 14, '#94A3B8', f, `Ch_${s.name}`);
      }

      // Open StealSheet Bottom Sheet!
      this.rect('StealSheet', 0, 344, 360, 416, { fill: '#FFFFFF', stroke: '#0F172A', strokeWeight: 1.5, cornerRadius: 24 }, f);
      this.rect('Grab', 156, 356, 48, 4, { fill: '#CBD5E1', cornerRadius: 2 }, f);
      this.logoBox(assets.sponsor_cookieadmin, 24, 376, 60, 60, f, 12, 8);
      this.text('CookieAdmin DPDP Shield', 96, 382, 230, { fontSize: 15, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('by CookieAdmin Team', 96, 404, 230, { fontSize: 11, fontWeight: 500, fill: '#64748B' }, f);

      this.rect('MadeHere', 24, 450, 260, 24, { fill: '#ECFDF5', cornerRadius: 12 }, f);
      this.vector('mapPin', 34, 455, 14, '#10B981', f, 'PinMade');
      this.text('Made by someone attending Delhi 2026', 54, 456, 224, { fontSize: 9.5, fontWeight: 700, fill: '#065F46' }, f);

      this.text('Lightweight consent banner & privacy preference manager built for Indian & global WordPress sites.', 24, 488, 312, { fontSize: 11, fontWeight: 400, fill: '#475569' }, f);
      this.rect('GetFree', 24, 558, 312, 46, { fill: '#FF4D2D', cornerRadius: 23 }, f);
      this.text('GET IT FREE ↗', 24, 574, 312, { fontSize: 11.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.text('Opens wordpress.org/plugins', 24, 616, 312, { fontSize: 10, fontWeight: 500, fill: '#64748B', textAlign: 'center' }, f);

      this.bottomNav(f, 'explore');
    }

    // M20: Contributor Day, Matcher & Table Leads (from MoreTabs.kt ContributeTab)
    {
      const f = this.screenFrame('M20 · Contributor Day & Table Leads', 'Attendance Pill | Interest Matcher | Table Lead Card');
      this.statusBar(f, false, 'Dec 12 · Tables');
      this.attendeeTopBar(f, 'Contributor Day', true);

      // Contribute Hero
      this.rect('ContHero', 16, 82, 328, 92, { fill: '#0F172A', cornerRadius: 16 }, f);
      this.rect('TagC', 28, 94, 90, 20, { fill: '#FF4D2D', cornerRadius: 10 }, f);
      this.text('CONTRIBUTE', 30, 98, 86, { fontSize: 8.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.text('Where could you help?', 28, 120, 260, { fontSize: 15, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('Writers, designers, translators & devs all have a table.', 28, 144, 300, { fontSize: 10, fontWeight: 400, fill: '#CBD5E1' }, f);

      // Going to Contributor Day?
      this.rect('GoingBox', 16, 184, 328, 84, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 14 }, f);
      this.text('Going to Contributor Day?', 28, 196, 240, { fontSize: 12, fontWeight: 800, fill: '#0F172A' }, f);
      this.pill(f, 'Yes', 28, 224, 62, true, '#0F172A');
      this.pill(f, 'Not sure yet', 96, 224, 96, false);
      this.pill(f, 'Not this time', 198, 224, 96, false);

      // Opener Quote Card
      this.rect('OpenLine', 16, 278, 328, 66, { fill: '#FFEDD5', stroke: '#D97706', strokeWeight: 1, cornerRadius: 14 }, f);
      this.text('Your opening line:', 28, 288, 200, { fontSize: 9.5, fontWeight: 700, fill: '#B45309' }, f);
      this.text('"Hi, this is my first Contributor Day. Can you help me get started?"', 28, 306, 300, { fontSize: 10.5, fontWeight: 700, fill: '#0F172A' }, f);

      // Table + Rich ContributorLeadCard (from MoreTabs.kt lines 490-625!)
      this.rect('TableBox', 16, 356, 328, 188, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Core & Gutenberg Table', 28, 368, 200, { fontSize: 13.5, fontWeight: 800, fill: '#0F172A' }, f);
      this.pill(f, 'Hall B · Table 1', 238, 366, 94, false);
      this.text('Bring a laptop for block theme testing & core patches.', 28, 392, 300, { fontSize: 10, fontWeight: 400, fill: '#64748B' }, f);

      // Lead Inner Card
      this.rect('LeadInner', 28, 418, 304, 114, { fill: '#FAFAFD', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 12 }, f);
      this.avatar(assets.amit, 38, 428, 44, f, '#D97706', true);
      this.text('Amit Kumar Singh', 92, 428, 140, { fontSize: 12, fontWeight: 800, fill: '#0F172A' }, f);
      this.rect('TLBadge', 240, 428, 78, 18, { fill: '#FEF3C7', stroke: '#D97706', strokeWeight: 1, cornerRadius: 6 }, f);
      this.text('Table Lead', 242, 431, 74, { fontSize: 8.5, fontWeight: 800, fill: '#7A4B00', textAlign: 'center' }, f);
      this.text('Managing Partner @ WPoets', 92, 448, 200, { fontSize: 9.5, fontWeight: 600, fill: '#FF4D2D' }, f);
      this.text('Building enterprise WordPress systems since 2006.', 38, 480, 280, { fontSize: 9.5, fontWeight: 400, fill: '#475569' }, f);
      this.pill(f, 'Speaker Profile ↗', 38, 502, 108, false);
      this.pill(f, 'LinkedIn ↗', 152, 502, 84, false);

      // Documentation Table Row
      this.rect('DocTable', 16, 556, 328, 66, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 14 }, f);
      this.avatar(assets.shreya, 28, 568, 42, f, '#E2E8F0', false);
      this.text('Documentation & HelpHub', 80, 568, 220, { fontSize: 12.5, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Lead: Shreya Agarwal · Table 2 (No coding needed)', 80, 588, 250, { fontSize: 9.5, fontWeight: 500, fill: '#64748B' }, f);

      this.bottomNav(f, 'quest');
    }

    // M21: Quests, Checklist & Achievement Badges (Real Vector Icons inside Badges!)
    {
      const f = this.screenFrame('M21 · Quests, Checklist & Badges', 'Progress Bar | Things To Do | Checklist | Icon Badges');
      this.statusBar(f, false, '3/5 Complete');
      this.attendeeTopBar(f, 'Quests', true);

      // Progress Header
      this.text('Conference Quests', 16, 84, 200, { fontSize: 16, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('3 / 5 complete', 250, 88, 94, { fontSize: 11, fontWeight: 700, fill: '#EA580C', textAlign: 'right' }, f);
      this.rect('QBarBG', 16, 112, 328, 8, { fill: '#E2E8F0', cornerRadius: 4 }, f);
      this.rect('QBarFill', 16, 112, 196, 8, { fill: '#D97706', cornerRadius: 4 }, f);

      // Things to do Cards (with Action Jump + Mark Done / Done ✓)
      this.text('Things to do', 16, 134, 160, { fontSize: 12, fontWeight: 800, fill: '#0F172A' }, f);
      this.rect('Q1', 16, 158, 328, 108, { fill: '#FFFFFF', stroke: '#10B981', strokeWeight: 1.5, cornerRadius: 16 }, f);
      this.rect('QIco1', 28, 170, 38, 38, { fill: '#FFEDD5', cornerRadius: 10 }, f);
      this.vector('card', 37, 179, 20, '#FF4D2D', f, 'Q1Ico');
      this.text('Build & Share Your CampCard', 76, 170, 240, { fontSize: 12.5, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Generate your digital badge and scan 3 peers.', 76, 190, 250, { fontSize: 10, fontWeight: 400, fill: '#64748B' }, f);
      this.rect('Jump1', 76, 224, 114, 28, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 14 }, f);
      this.text('Open CampCard →', 78, 231, 110, { fontSize: 9, fontWeight: 700, fill: '#0F172A', textAlign: 'center' }, f);
      this.rect('Done1', 198, 224, 104, 28, { fill: '#ECFDF5', stroke: '#10B981', strokeWeight: 1, cornerRadius: 14 }, f);
      this.text('Done ✓', 200, 231, 100, { fontSize: 9.5, fontWeight: 800, fill: '#10B981', textAlign: 'center' }, f);

      // Checklist Section (from MoreTabs.kt lines 205-226)
      this.text('Checklist', 16, 282, 160, { fontSize: 12, fontWeight: 800, fill: '#0F172A' }, f);
      const checks = [
        { t: 'Visit 3 Sponsor Booths', s: 'Elementor, WP Fusion & CookieAdmin', on: true, y: 306 },
        { t: 'Star 3 Sessions in My Day', s: 'Build your conference plan', on: true, y: 368 },
        { t: 'Say Hi at a Contributor Table', s: 'Core, Docs, or Polyglots on Dec 12', on: false, y: 430 },
      ];
      for (const c of checks) {
        this.rect(`Chk_${c.t}`, 16, c.y, 328, 54, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 12 }, f);
        this.rect(`Box_${c.t}`, 28, c.y + 18, 18, 18, { fill: c.on ? '#FF4D2D' : '#FFFFFF', stroke: c.on ? '#FF4D2D' : '#94A3B8', strokeWeight: 1.5, cornerRadius: 4 }, f);
        if (c.on) this.vector('check', 30, c.y + 20, 14, '#FFFFFF', f, `CkV_${c.t}`);
        this.text(c.t, 56, c.y + 10, 270, { fontSize: 11.5, fontWeight: 700, fill: '#0F172A' }, f);
        this.text(c.s, 56, c.y + 30, 270, { fontSize: 9.5, fontWeight: 400, fill: '#64748B' }, f);
      }

      // Achievement Badges Grid WITH Crisp Vector Icons inside EVERY badge!
      this.text('UNLOCKED BADGES', 16, 500, 200, { fontSize: 11, fontWeight: 800, fill: '#0F172A' }, f);
      const badges = [
        { name: 'Early Bird', ico: 'clock', unlocked: true },
        { name: 'Core Helper', ico: 'wrench', unlocked: true },
        { name: 'Swag Hunter', ico: 'gift', unlocked: true },
        { name: 'Connector', ico: 'users', unlocked: false },
      ];
      for (let i = 0; i < 4; i++) {
        const bx = 16 + i * 84;
        const b = badges[i];
        this.rect(`Bdg_${i}`, bx, 524, 76, 84, {
          fill: b.unlocked ? '#FFFFFF' : '#F1F5F9', stroke: b.unlocked ? '#D97706' : '#CBD5E1', strokeWeight: 1.5, cornerRadius: 14
        }, f);
        this.ellipse(`BIcoBG_${i}`, bx + 20, 536, 36, 36, { fill: b.unlocked ? '#FEF3C7' : '#E2E8F0' }, f);
        this.vector(b.ico, bx + 29, 545, 18, b.unlocked ? '#D97706' : '#94A3B8', f, `BIco_${i}`);
        this.text(b.name, bx + 4, 582, 68, { fontSize: 9, fontWeight: 700, fill: b.unlocked ? '#0F172A' : '#94A3B8', textAlign: 'center' }, f);
      }

      this.bottomNav(f, 'quest');
    }

    // M22: Explore -> Event Info, Data Controls & Reminders
    {
      const f = this.screenFrame('M22 · Event Info & Data Controls', 'Event Info Rows | Copy/Open | Data Controls | Alerts');
      this.statusBar(f, false, 'Offline Ready');
      this.exploreHeader(f, 'info');

      // Event Info Header with White-Framed Delhi Logo
      this.rect('InfoHdr', 16, 150, 328, 72, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 14 }, f);
      this.logoBox(assets.delhiLogo, 28, 162, 48, 48, f, 10, 4);
      this.text('WordCamp Delhi 2026', 88, 164, 230, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Sat 12 – Sun 13 Dec 2026', 88, 186, 230, { fontSize: 10.5, fontWeight: 500, fill: '#64748B' }, f);

      // Practical Info Rows
      this.rect('InfoList', 16, 232, 328, 168, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 14 }, f);
      this.vector('mapPin', 28, 246, 16, '#FF4D2D', f, 'I1');
      this.text('Venue: DAIC, 15 Janpath Rd, New Delhi', 52, 246, 210, { fontSize: 10.5, fontWeight: 600, fill: '#0F172A' }, f);
      this.pill(f, 'Open Map ↗', 260, 242, 74, false);

      this.line('Sep1', 28, 284, 304, { stroke: '#E2E8F0', strokeWeight: 1 }, f);
      this.vector('shield', 28, 298, 16, '#DC2626', f, 'I2');
      this.text('Emergency: delhi@wordcamp.org', 52, 298, 210, { fontSize: 10.5, fontWeight: 600, fill: '#0F172A' }, f);
      this.pill(f, 'Copy', 278, 294, 56, false);

      this.line('Sep2', 28, 336, 304, { stroke: '#E2E8F0', strokeWeight: 1 }, f);
      this.vector('ticket', 28, 350, 16, '#0F172A', f, 'I3');
      this.text('Venue Wi-Fi: WCDELHI-GUEST', 52, 350, 210, { fontSize: 10.5, fontWeight: 600, fill: '#0F172A' }, f);
      this.pill(f, 'Copy', 278, 346, 56, false);

      // NotificationChoicesCard & DataControls (Zero technical jargon!)
      this.rect('NotifCard', 16, 412, 328, 108, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 14 }, f);
      this.text('Your Notification Choices', 28, 424, 240, { fontSize: 12, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('10-min Session Reminders', 28, 450, 200, { fontSize: 10.5, fontWeight: 600, fill: '#0F172A' }, f);
      this.toggle(f, 286, 446, true);
      this.text('Organizer Live Announcements', 28, 482, 200, { fontSize: 10.5, fontWeight: 600, fill: '#0F172A' }, f);
      this.toggle(f, 286, 478, true);

      this.rect('DataCard', 16, 532, 328, 100, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 14 }, f);
      this.text('Your Saved Data Controls', 28, 544, 240, { fontSize: 12, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('All bookmarks & contacts are saved on this phone.', 28, 564, 300, { fontSize: 9.5, fontWeight: 500, fill: '#64748B' }, f);
      this.rect('ExpD', 28, 588, 146, 30, { fill: '#F1F5F9', cornerRadius: 15 }, f);
      this.text('Download My Data', 30, 596, 142, { fontSize: 9.5, fontWeight: 700, fill: '#0F172A', textAlign: 'center' }, f);
      this.rect('DelD', 184, 588, 146, 30, { fill: '#FFF5F5', stroke: '#FCA5A5', strokeWeight: 1, cornerRadius: 15 }, f);
      this.text('Clear Event Data', 186, 596, 142, { fontSize: 9.5, fontWeight: 800, fill: '#DC2626', textAlign: 'center' }, f);

      this.bottomNav(f, 'explore');
    }

    // =========================================================================
    // GROUP 5: IN-APP ORGANIZER & SUPERADMIN CONSOLE (M23 - M32)
    // =========================================================================

    // M23: Staff Login & Managed Events List
    {
      const f = this.screenFrame('M23 · Staff Login & My WordCamps', 'Staff Sign-In | Role Cards | Managed Events List', '#0F172A');
      this.statusBar(f, true, 'Staff Console');
      this.staffTopBar(f, 'Staff Sign In', 'Attendee App');

      this.logoBox(assets.cbWordmark, 80, 86, 200, 54, f, 14, 8);

      // Login Card
      this.rect('LoginBox', 16, 152, 328, 224, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Sign in to Organizer Mode', 30, 166, 260, { fontSize: 13.5, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.rect('InEm', 30, 194, 300, 36, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 8 }, f);
      this.text('organizer@wordcampdelhi.org', 40, 205, 260, { fontSize: 10.5, fontWeight: 500, fill: '#FFFFFF' }, f);
      this.rect('InPw', 30, 238, 300, 36, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 8 }, f);
      this.text('••••••••••••', 40, 249, 260, { fontSize: 12, fontWeight: 700, fill: '#94A3B8' }, f);
      this.rect('SignBtn', 30, 286, 300, 40, { fill: '#FF4D2D', cornerRadius: 20 }, f);
      this.text('SIGN IN TO STAFF HUB', 30, 299, 300, { fontSize: 10.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.text('Role detected automatically (Manager or Super Admin)', 30, 340, 290, { fontSize: 9.5, fontWeight: 500, fill: '#10B981', textAlign: 'center' }, f);

      // Managed Events List Preview (EventsListPage)
      this.text('AFTER SIGN IN · MY WORDCAMPS (2)', 18, 394, 280, { fontSize: 10, fontWeight: 800, fill: '#94A3B8' }, f);
      this.rect('EvtRow1', 16, 416, 328, 108, { fill: '#1E293B', stroke: '#10B981', strokeWeight: 1.5, cornerRadius: 16 }, f);
      this.logoBox(assets.delhiLogo, 28, 430, 46, 46, f, 10, 4);
      this.text('WordCamp Delhi 2026', 84, 430, 180, { fontSize: 13.5, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('Dec 12–13 · Active · 2 Open Reports', 84, 450, 220, { fontSize: 10, fontWeight: 600, fill: '#F59E0B' }, f);
      this.rect('MngBtn', 28, 486, 146, 26, { fill: '#FF4D2D', cornerRadius: 13 }, f);
      this.text('MANAGE EVENT', 30, 492, 142, { fontSize: 9, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('PrevBtn', 186, 486, 146, 26, { fill: '#334155', cornerRadius: 13 }, f);
      this.text('PREVIEW ATTENDEE', 188, 492, 142, { fontSize: 9, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);

      this.rect('NewEvt', 16, 540, 328, 42, { fill: '#D97706', cornerRadius: 21 }, f);
      this.text('+ CREATE NEW WORDCAMP', 16, 554, 328, { fontSize: 10.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
    }

    // M24: Staff Event Overview & Details Editor
    {
      const f = this.screenFrame('M24 · Staff Event Overview & Details', '14 Event Tabs | KPIs | Details & Visibility Edit', '#0B1120');
      this.statusBar(f, true, 'Event Manager');
      this.staffTopBar(f, 'WordCamp Delhi', 'Events', 'Preview');

      // Horizontal Scrollable TabPills with LineIcons (Overview active)
      const orgPills = [
        { lbl: 'Overview', ico: 'home', x: 16, w: 90, on: true },
        { lbl: 'Details', ico: 'pencil', x: 112, w: 78, on: false },
        { lbl: 'Info', ico: 'mapPin', x: 196, w: 62, on: false },
        { lbl: 'Announce', ico: 'megaphone', x: 264, w: 86, on: false },
      ];
      for (const op of orgPills) {
        this.rect(`OP_${op.lbl}`, op.x, 82, op.w, 28, {
          fill: op.on ? '#FF4D2D' : '#1E293B', stroke: op.on ? '#FF4D2D' : '#334155', strokeWeight: 1, cornerRadius: 14
        }, f);
        this.vector(op.ico, op.x + 10, 89, 13, '#FFFFFF', f, `OPI_${op.lbl}`);
        this.text(op.lbl, op.x + 26, 89, op.w - 30, { fontSize: 9.5, fontWeight: 700, fill: '#FFFFFF' }, f);
      }

      // 2x2 KPIs
      const kpis = [
        { t: 'Checked In', v: '584', s: '92% Ticketed', c: '#10B981', x: 16, y: 120 },
        { t: 'Open Reports', v: '2', s: 'Tap to Triage', c: '#F59E0B', x: 184, y: 120 },
      ];
      for (const k of kpis) {
        this.rect(`K_${k.t}`, k.x, k.y, 160, 74, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 14 }, f);
        this.text(k.t, k.x + 12, k.y + 10, 136, { fontSize: 10, fontWeight: 600, fill: '#94A3B8' }, f);
        this.text(k.v, k.x + 12, k.y + 26, 136, { fontSize: 20, fontWeight: 800, fill: '#FFFFFF' }, f);
        this.text(k.s, k.x + 12, k.y + 52, 136, { fontSize: 9.5, fontWeight: 700, fill: k.c }, f);
      }

      // DetailsTab & InformationTab Quick Editor Preview
      this.rect('DetForm', 16, 208, 328, 304, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Event Details & Venue Info (DetailsTab)', 28, 222, 280, { fontSize: 12.5, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.rect('DF1', 28, 248, 304, 34, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 8 }, f);
      this.text('Name: WordCamp Delhi 2026', 38, 258, 280, { fontSize: 10.5, fontWeight: 500, fill: '#FFFFFF' }, f);
      this.rect('DF2', 28, 290, 304, 34, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 8 }, f);
      this.text('Venue: DAIC, 15 Janpath Rd, New Delhi', 38, 300, 280, { fontSize: 10.5, fontWeight: 500, fill: '#FFFFFF' }, f);
      this.rect('DF3', 28, 332, 304, 34, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 8 }, f);
      this.text('Emergency Email: delhi@wordcamp.org', 38, 342, 280, { fontSize: 10.5, fontWeight: 500, fill: '#FFFFFF' }, f);
      this.text('Visible in Public Picker', 28, 382, 200, { fontSize: 10.5, fontWeight: 600, fill: '#FFFFFF' }, f);
      this.toggle(f, 288, 378, true);
      this.text('Lock Dates (Manual Override)', 28, 414, 200, { fontSize: 10.5, fontWeight: 600, fill: '#FFFFFF' }, f);
      this.toggle(f, 288, 410, false);
      this.rect('SaveDet', 28, 450, 304, 40, { fill: '#10B981', cornerRadius: 20 }, f);
      this.text('SAVE DETAILS & PUBLISH', 30, 463, 300, { fontSize: 10, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);

      this.rect('DelEvt', 16, 526, 328, 40, { fill: '#0F172A', stroke: '#EF4444', strokeWeight: 1, cornerRadius: 20 }, f);
      this.text('DELETE THIS WORDCAMP...', 16, 539, 328, { fontSize: 10, fontWeight: 800, fill: '#EF4444', textAlign: 'center' }, f);
    }

    // M25: Staff Running Late (Delay Broadcaster)
    {
      const f = this.screenFrame('M25 · Staff Running Late Delays', 'Track Selection | +15m Offset | Cascade | Clear', '#0B1120');
      this.statusBar(f, true, 'Live Delays');
      this.staffTopBar(f, 'Running Late', 'Overview');

      this.pill(f, 'BHIM Auditorium', 16, 84, 130, true, '#FF4D2D');
      this.pill(f, 'Contrib Lounge', 154, 84, 114, false);
      this.pill(f, 'All Halls', 274, 84, 70, false);

      this.rect('OffCard', 16, 126, 328, 144, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Active Delay: +15 Minutes', 30, 142, 260, { fontSize: 16, fontWeight: 800, fill: '#F59E0B' }, f);
      this.text('Automatically shifts 5 upcoming talks in BHIM Hall.', 30, 168, 290, { fontSize: 10, fontWeight: 400, fill: '#CBD5E1' }, f);
      const mins = ['+5m', '+10m', '+15m', '+30m'];
      for (let i = 0; i < 4; i++) {
        const bx = 30 + i * 76;
        const on = i === 2;
        this.rect(`Min_${i}`, bx, 206, 68, 38, { fill: on ? '#FF4D2D' : '#0F172A', stroke: on ? '#FF4D2D' : '#334155', strokeWeight: 1, cornerRadius: 10 }, f);
        this.text(mins[i], bx, 218, 68, { fontSize: 11, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      }

      this.rect('CasBox', 16, 286, 328, 156, { fill: '#1E293B', cornerRadius: 16 }, f);
      this.text('Updated Schedule Preview', 28, 300, 240, { fontSize: 12.5, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('• Local SEO (Pankaj): 11:20 AM → 11:35 AM', 28, 326, 300, { fontSize: 10.5, fontWeight: 600, fill: '#F59E0B' }, f);
      this.text('• AI Citations (Shammi): 02:00 PM → 02:15 PM', 28, 350, 300, { fontSize: 10.5, fontWeight: 600, fill: '#F59E0B' }, f);
      this.text('• Value Shift Panel: 04:00 PM → 04:15 PM', 28, 374, 300, { fontSize: 10.5, fontWeight: 600, fill: '#F59E0B' }, f);
      this.text('Sends push update to 584 phones immediately.', 28, 408, 300, { fontSize: 10, fontWeight: 500, fill: '#10B981' }, f);

      this.rect('BrcBtn', 16, 464, 328, 46, { fill: '#FF4D2D', cornerRadius: 23 }, f);
      this.text('BROADCAST +15M DELAY', 16, 479, 328, { fontSize: 11, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('ClrBtn', 16, 522, 328, 42, { fill: '#0F172A', stroke: '#475569', strokeWeight: 1, cornerRadius: 21 }, f);
      this.text('CLEAR DELAY (BACK ON SCHEDULE)', 16, 536, 328, { fontSize: 10, fontWeight: 700, fill: '#CBD5E1', textAlign: 'center' }, f);
    }

    // M26: Staff Announce & Notifications History
    {
      const f = this.screenFrame('M26 · Staff Announce & Sent Log', 'Tone Pills | Live Composer | Audience | Sent History', '#0B1120');
      this.statusBar(f, true, 'Broadcasts');
      this.staffTopBar(f, 'Announce & Log', 'Overview');

      // BroadcastTab Composer
      this.rect('CompBox', 16, 84, 328, 250, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Send Live Announcement (BroadcastTab)', 28, 98, 290, { fontSize: 12.5, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.pill(f, 'Notice', 28, 124, 68, false);
      this.pill(f, 'Break / Food', 102, 124, 96, true, '#D97706');
      this.pill(f, 'Together', 204, 124, 82, false);

      this.rect('TitleIn', 28, 160, 304, 32, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 8 }, f);
      this.text('Title: Lunch is Served in Courtyard!', 38, 169, 280, { fontSize: 10.5, fontWeight: 600, fill: '#FFFFFF' }, f);
      this.rect('BodyIn', 28, 200, 304, 66, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 8 }, f);
      this.text('Hot buffet open near Hall B. Stop by sponsor booths on your way!', 38, 210, 284, { fontSize: 10, fontWeight: 400, fill: '#CBD5E1' }, f);

      this.text('Reaches 584 phones + Top Banner', 28, 284, 180, { fontSize: 9.5, fontWeight: 600, fill: '#10B981' }, f);
      this.rect('SendAnn', 216, 278, 116, 32, { fill: '#FF4D2D', cornerRadius: 16 }, f);
      this.text('SEND NOW', 218, 287, 112, { fontSize: 9.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);

      // NotificationsTab History
      this.text('SENT HISTORY (NOTIFICATIONS TAB)', 18, 352, 260, { fontSize: 10, fontWeight: 800, fill: '#94A3B8' }, f);
      const sent = [
        { t: 'BHIM Hall +15m Delay', m: '10:29 AM · Delivered to 584', y: 374 },
        { t: 'Opening Ceremony Starts in 10m', m: '08:50 AM · Delivered to 412', y: 446 },
        { t: 'Registration Desk Open', m: '08:00 AM · Delivered to 320', y: 518 },
      ];
      for (const s of sent) {
        this.rect(`S_${s.t}`, 16, s.y, 328, 60, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 12 }, f);
        this.text(s.t, 28, s.y + 12, 280, { fontSize: 11.5, fontWeight: 700, fill: '#FFFFFF' }, f);
        this.text(s.m, 28, s.y + 34, 280, { fontSize: 9.5, fontWeight: 500, fill: '#10B981' }, f);
      }
    }

    // M27: Staff Safety Reports & Attendee Roster Manager
    {
      const f = this.screenFrame('M27 · Staff Reports & Roster Roles', 'Report Triage | Hide/Resolve | Roster Role Picker', '#0B1120');
      this.statusBar(f, true, '2 Open Reports');
      this.staffTopBar(f, 'Reports & Roster', 'Overview');

      // ReportsTab Queue
      this.text('OPEN REPORTS QUEUE (2)', 18, 84, 200, { fontSize: 10, fontWeight: 800, fill: '#FCA5A5' }, f);
      this.rect('Rep1', 16, 106, 328, 138, { fill: '#1E293B', stroke: '#FCA5A5', strokeWeight: 1.5, cornerRadius: 16 }, f);
      this.text('Flagged Discovery Profile · "Spam Links"', 28, 118, 230, { fontSize: 11.5, fontWeight: 800, fill: '#FCA5A5' }, f);
      this.rect('OpenP', 274, 116, 58, 20, { fill: '#FEF3C7', cornerRadius: 10 }, f);
      this.text('OPEN', 276, 120, 54, { fontSize: 8.5, fontWeight: 800, fill: '#B45309', textAlign: 'center' }, f);
      this.text('Reported by attendee: "Card promotes off-topic crypto links."', 28, 144, 300, { fontSize: 10, fontWeight: 400, fill: '#CBD5E1' }, f);
      this.rect('HideBtn', 28, 194, 144, 32, { fill: '#EF4444', cornerRadius: 16 }, f);
      this.text('HIDE PROFILE', 30, 203, 140, { fontSize: 9.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('ResBtn', 184, 194, 144, 32, { fill: '#334155', cornerRadius: 16 }, f);
      this.text('DISMISS REPORT', 186, 203, 140, { fontSize: 9.5, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);

      // AttendeesTab Roster & Role Assignment
      this.text('ATTENDEE ROSTER & ROLES (ATTENDEES TAB)', 18, 262, 290, { fontSize: 10, fontWeight: 800, fill: '#94A3B8' }, f);
      this.rect('RosSearch', 16, 284, 328, 36, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 10 }, f);
      this.vector('search', 28, 293, 16, '#94A3B8', f, 'RSrch');
      this.text('Search roster name or role...', 52, 295, 250, { fontSize: 10.5, fontWeight: 400, fill: '#94A3B8' }, f);

      const roster = [
        { img: assets.pankaj, name: 'Pankaj Kumar', role: 'Speaker · Claimed Card ✓', y: 332 },
        { img: assets.shreya, name: 'Shreya Agarwal', role: 'Table Lead · Documentation', y: 422 },
      ];
      for (const r of roster) {
        this.rect(`Ros_${r.name}`, 16, r.y, 328, 78, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 14 }, f);
        this.avatar(r.img, 28, r.y + 14, 46, f, '#334155', false);
        this.text(r.name, 84, r.y + 14, 180, { fontSize: 13, fontWeight: 800, fill: '#FFFFFF' }, f);
        this.text(r.role, 84, r.y + 34, 190, { fontSize: 10, fontWeight: 500, fill: '#10B981' }, f);
        this.rect(`EdR_${r.name}`, 268, r.y + 24, 64, 26, { fill: '#334155', cornerRadius: 13 }, f);
        this.text('Edit Role', 270, r.y + 30, 60, { fontSize: 9, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);
      }
    }

    // M28: Staff Tables, Quests & Memories Publisher
    {
      const f = this.screenFrame('M28 · Staff Tables, Quests & Memories', 'TablesTab | QuestsTab | Publish Album & WPTV', '#0B1120');
      this.statusBar(f, true, 'Event Setup');
      this.staffTopBar(f, 'Tables & Memories', 'Overview');

      // TablesTab + QuestsTab Summary
      this.rect('TabEd', 16, 84, 328, 176, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Contributor Tables & Quests', 28, 98, 240, { fontSize: 12.5, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.rect('AddTb', 238, 94, 94, 24, { fill: '#FF4D2D', cornerRadius: 12 }, f);
      this.text('+ ADD TABLE', 240, 100, 90, { fontSize: 8.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.text('• Core & Gutenberg · Hall B · Lead: Amit Singh', 28, 130, 300, { fontSize: 10, fontWeight: 600, fill: '#CBD5E1' }, f);
      this.text('• Documentation · Hall B · Lead: Shreya Agarwal', 28, 152, 300, { fontSize: 10, fontWeight: 600, fill: '#CBD5E1' }, f);
      this.text('• Polyglots Hindi/Urdu · Table 3', 28, 174, 300, { fontSize: 10, fontWeight: 600, fill: '#CBD5E1' }, f);
      this.rect('EdQ', 28, 208, 304, 34, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 10 }, f);
      this.text('5 Active Quests Configured · Tap to Edit', 38, 218, 280, { fontSize: 10, fontWeight: 600, fill: '#F59E0B' }, f);

      // MemoriesOrgTab (Set Photo Album & WordPress.tv URLs + Notify Attendees!)
      this.rect('MemOrg', 16, 276, 328, 274, { fill: '#1E293B', stroke: '#10B981', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Publish Post-Event Memories', 28, 290, 280, { fontSize: 13, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('Official Photo Album URL:', 28, 316, 260, { fontSize: 10, fontWeight: 600, fill: '#94A3B8' }, f);
      this.rect('Url1', 28, 336, 304, 34, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 8 }, f);
      this.text('https://photos.app.goo.gl/wcdelhi2026', 38, 346, 280, { fontSize: 10, fontWeight: 500, fill: '#FFFFFF' }, f);
      this.text('WordPress.tv Event URL:', 28, 382, 260, { fontSize: 10, fontWeight: 600, fill: '#94A3B8' }, f);
      this.rect('Url2', 28, 402, 304, 34, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 8 }, f);
      this.text('https://wordpress.tv/event/wordcamp-delhi-2026/', 38, 412, 280, { fontSize: 10, fontWeight: 500, fill: '#FFFFFF' }, f);
      this.text('☑ Notify attendees that photos & talks are live', 28, 448, 300, { fontSize: 10, fontWeight: 600, fill: '#10B981' }, f);
      this.rect('PubMem', 28, 482, 304, 42, { fill: '#10B981', cornerRadius: 21 }, f);
      this.text('SAVE & NOTIFY ATTENDEES', 30, 496, 300, { fontSize: 10.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
    }

    // M29: Staff Social Kit & Daylight Card Studio (Fixed Logo + Circular Portrait!)
    {
      const f = this.screenFrame('M29 · Staff Social Kit & Reels', 'White-Framed Logos | Circular Speaker | PNG Share', '#0B1120');
      this.statusBar(f, true, 'Social Studio');
      this.staffTopBar(f, 'Social Card Studio', 'Overview');

      this.pill(f, 'Speaker Card', 16, 82, 108, true, '#FF4D2D');
      this.pill(f, 'Daylight Card', 130, 82, 108, false);
      this.pill(f, 'Schedule Reel', 244, 82, 100, false);

      // Generated Social Card Canvas
      this.rect('SocCanvas', 28, 122, 304, 396, { fill: '#0F172A', stroke: '#D97706', strokeWeight: 2, cornerRadius: 20 }, f);
      // Both Delhi Logo and CampBuddy Mark in clean White Containers!
      this.logoBox(assets.delhiLogo, 44, 138, 42, 42, f, 10, 4);
      this.text('WORDCAMP DELHI 2026', 96, 152, 170, { fontSize: 10.5, fontWeight: 800, fill: '#F59E0B' }, f);
      this.logoBox(assets.cbIcon, 276, 138, 38, 38, f, 10, 3);

      // Circular Clipped Speaker Headshot (ZERO corners sticking out!)
      this.avatar(assets.pankaj, 118, 200, 124, f, '#FF4D2D', false);

      this.text('Pankaj Kumar', 44, 340, 272, { fontSize: 20, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.text('10 Things to Do to Boost Local SEO in the AI Era', 44, 372, 272, { fontSize: 12, fontWeight: 600, fill: '#FF4D2D', textAlign: 'center' }, f);
      this.text('Sun, Dec 13 · 11:20 AM · BHIM Auditorium', 44, 420, 272, { fontSize: 10.5, fontWeight: 500, fill: '#CBD5E1', textAlign: 'center' }, f);
      this.text('campbuddy.org/delhi-2026', 44, 470, 272, { fontSize: 10, fontWeight: 700, fill: '#10B981', textAlign: 'center' }, f);

      this.rect('ExpSoc', 28, 536, 304, 44, { fill: '#FF4D2D', cornerRadius: 22 }, f);
      this.text('SHARE SOCIAL IMAGE', 28, 551, 304, { fontSize: 11, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('SwTheme', 28, 592, 304, 40, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 20 }, f);
      this.text('SWITCH TO DAYLIGHT PAPER THEME', 28, 605, 304, { fontSize: 10, fontWeight: 700, fill: '#CBD5E1', textAlign: 'center' }, f);
    }

    // M30: SuperAdmin Global Dashboard & Maintenance (AdminDashboardPage!)
    {
      const f = this.screenFrame('M30 · SuperAdmin Global Dashboard', '9-KPI StatGrid | 26 WordCamps | Refresh & Cache', '#0B1120');
      this.statusBar(f, true, 'Super Admin');
      this.staffTopBar(f, 'Global Dashboard', 'Sign Out');

      // Health Banner
      this.rect('HealthOk', 16, 82, 328, 48, { fill: '#064E3B', stroke: '#10B981', strokeWeight: 1, cornerRadius: 12 }, f);
      this.vector('check', 28, 97, 18, '#10B981', f, 'HChk');
      this.text('All good · No system errors in 7 days', 54, 98, 270, { fontSize: 11, fontWeight: 700, fill: '#FFFFFF' }, f);

      // 3x3 StatGrid (matching AdminDashboardPage 9 stats with crisp LineIcons!)
      const stats = [
        { t: 'WordCamps', v: '26', ico: 'calendar', c: '#FF4D2D', x: 16, y: 142 },
        { t: 'Live', v: '6', ico: 'clock', c: '#10B981', x: 128, y: 142 },
        { t: 'Drafts', v: '2', ico: 'pencil', c: '#F59E0B', x: 240, y: 142 },
        { t: 'Attendees', v: '4.2k', ico: 'users', c: '#FF4D2D', x: 16, y: 214 },
        { t: 'Deal Leads', v: '184', ico: 'gift', c: '#D97706', x: 128, y: 214 },
        { t: 'Managers', v: '18', ico: 'shield', c: '#38BDF8', x: 240, y: 214 },
        { t: 'Feedback', v: '96', ico: 'heart', c: '#EC4899', x: 16, y: 286 },
        { t: 'Errors', v: '0', ico: 'alert', c: '#10B981', x: 128, y: 286 },
        { t: 'Activity', v: 'Log', ico: 'activity', c: '#94A3B8', x: 240, y: 286 },
      ];
      for (const s of stats) {
        this.rect(`SG_${s.t}`, s.x, s.y, 104, 62, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 12 }, f);
        this.vector(s.ico, s.x + 10, s.y + 12, 14, s.c, f, `SGI_${s.t}`);
        this.text(s.v, s.x + 30, s.y + 10, 66, { fontSize: 16, fontWeight: 800, fill: '#FFFFFF', textAlign: 'right' }, f);
        this.text(s.t, s.x + 10, s.y + 38, 84, { fontSize: 9.5, fontWeight: 600, fill: '#94A3B8' }, f);
      }

      // Next WordCamps List
      this.text('NEXT WORDCAMPS', 18, 366, 200, { fontSize: 10, fontWeight: 800, fill: '#94A3B8' }, f);
      this.rect('Next1', 16, 388, 328, 66, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 14 }, f);
      this.logoBox(assets.delhiLogo, 28, 398, 44, 44, f, 10, 4);
      this.text('WordCamp Delhi 2026', 82, 400, 180, { fontSize: 12.5, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('Dec 12–13 · 17 Sponsors Ready', 82, 420, 200, { fontSize: 9.5, fontWeight: 500, fill: '#10B981' }, f);

      // MaintenanceCard (Refresh All Data + Clear Cache)
      this.rect('MaintBox', 16, 468, 328, 126, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Platform Maintenance', 28, 480, 220, { fontSize: 12.5, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('Last sync: Today 10:25 AM (Success)', 28, 502, 280, { fontSize: 10, fontWeight: 500, fill: '#94A3B8' }, f);
      this.rect('RefBtn', 28, 534, 146, 38, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 19 }, f);
      this.text('REFRESH DATA', 30, 546, 142, { fontSize: 9.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('PurBtn', 186, 534, 146, 38, { fill: '#EF4444', cornerRadius: 19 }, f);
      this.text('CLEAR CACHE', 188, 546, 142, { fontSize: 9.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);

      // Admin 4-Tab Bottom Bar
      this.adminBottomNav(f, 'dashboard');
    }

    // M31: SuperAdmin Managers & ManagerSheet
    {
      const f = this.screenFrame('M31 · SuperAdmin Event Managers', 'Search/Filter | Add ManagerSheet | WordCamp Chips', '#0B1120');
      this.statusBar(f, true, '18 Managers');
      this.staffTopBar(f, 'Event Managers', 'Dashboard');

      this.rect('AddMgr', 16, 82, 328, 40, { fill: '#FF4D2D', cornerRadius: 20 }, f);
      this.text('+ ADD EVENT MANAGER', 16, 95, 328, { fontSize: 10.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);

      this.pill(f, 'All (18)', 16, 134, 70, true, '#F59E0B');
      this.pill(f, 'Active (16)', 92, 134, 86, false);
      this.pill(f, 'Switched Off (2)', 184, 134, 114, false);

      // Manager Row Card
      this.rect('MgrCard', 16, 174, 328, 108, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 14 }, f);
      this.text('Rohan Verma', 28, 186, 200, { fontSize: 13.5, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.rect('OnPill', 280, 186, 48, 20, { fill: '#ECFDF5', cornerRadius: 10 }, f);
      this.text('ON', 282, 190, 44, { fontSize: 8.5, fontWeight: 800, fill: '#10B981', textAlign: 'center' }, f);
      this.text('rohan@wordcampdelhi.org · +91 98110xxxxx', 28, 208, 290, { fontSize: 9.5, fontWeight: 400, fill: '#94A3B8' }, f);
      this.pill(f, 'WordCamp Delhi 2026', 28, 234, 146, true, '#0F172A');
      this.pill(f, 'Rajasthan 2026', 180, 234, 114, true, '#0F172A');

      // ManagerSheet Edit Modal Preview
      this.rect('MgrSheet', 16, 296, 328, 356, { fill: '#1E293B', stroke: '#F59E0B', strokeWeight: 1.5, cornerRadius: 18 }, f);
      this.text('Edit Manager: Rohan Verma', 28, 310, 260, { fontSize: 13, fontWeight: 800, fill: '#F59E0B' }, f);
      this.rect('MF1', 28, 338, 304, 34, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 8 }, f);
      this.text('Email: rohan@wordcampdelhi.org', 38, 348, 280, { fontSize: 10, fontWeight: 500, fill: '#FFFFFF' }, f);
      this.text('Can sign in to App & Web Panel', 28, 386, 220, { fontSize: 10.5, fontWeight: 600, fill: '#FFFFFF' }, f);
      this.toggle(f, 288, 382, true);
      this.text('Assigned WordCamps (2 selected):', 28, 418, 260, { fontSize: 10, fontWeight: 700, fill: '#CBD5E1' }, f);
      this.pill(f, '✓ Delhi 2026', 28, 440, 100, true, '#10B981');
      this.pill(f, '✓ Rajasthan 2026', 134, 440, 120, true, '#10B981');
      this.pill(f, 'Mumbai 2027', 260, 440, 76, false);

      this.rect('SaveMgr', 28, 520, 304, 40, { fill: '#10B981', cornerRadius: 20 }, f);
      this.text('SAVE MANAGER CHANGES', 30, 533, 300, { fontSize: 10, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('DelMgr', 28, 572, 304, 36, { fill: '#0F172A', stroke: '#EF4444', strokeWeight: 1, cornerRadius: 18 }, f);
      this.text('REVOKE / DELETE MANAGER', 30, 584, 300, { fontSize: 9.5, fontWeight: 700, fill: '#EF4444', textAlign: 'center' }, f);

      this.adminBottomNav(f, 'managers');
    }

    // M32: SuperAdmin More: Analytics, Errors, Deals & Lead Exports
    {
      const f = this.screenFrame('M32 · SuperAdmin Analytics, Deals & Leads', 'Analytics | Errors | Sponsor Deals & Lead Exports', '#0B1120');
      this.statusBar(f, true, 'Admin More');
      this.staffTopBar(f, 'Analytics & Leads', 'Dashboard');

      // AdminMorePage Quick Rows
      const rows = [
        { ico: 'activity', t: 'Visitor Analytics', s: '4,820 active attendees · Top: Delhi 2026', y: 84 },
        { ico: 'alert', t: 'System Health & Fetches', s: 'All feeds healthy · 0 failed checks', y: 152 },
        { ico: 'heart', t: 'Attendee Feedback', s: '4.9 ★ avg rating from Thank-You card', y: 220 },
      ];
      for (const r of rows) {
        this.rect(`MR_${r.t}`, 16, r.y, 328, 58, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 14 }, f);
        this.ellipse(`MI_${r.t}`, 28, r.y + 11, 36, 36, { fill: '#0F172A' }, f);
        this.vector(r.ico, 37, r.y + 20, 18, '#F59E0B', f, `MVI_${r.t}`);
        this.text(r.t, 74, r.y + 12, 220, { fontSize: 12, fontWeight: 800, fill: '#FFFFFF' }, f);
        this.text(r.s, 74, r.y + 32, 230, { fontSize: 9.5, fontWeight: 500, fill: '#94A3B8' }, f);
        this.vector('chevronRight', 320, r.y + 22, 14, '#64748B', f, `MA_${r.t}`);
      }

      // DealsTab & LeadsTab (Admin Sponsor Lead Export!)
      this.text('EVENT DEALS & CAPTURED LEADS (ADMIN)', 18, 294, 300, { fontSize: 10, fontWeight: 800, fill: '#F59E0B' }, f);
      this.rect('LeadsBox', 16, 316, 328, 290, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 16 }, f);
      this.logoBox(assets.sponsor_wpfusion, 28, 330, 64, 36, f, 8, 4);
      this.text('WP Fusion · 64 Leads Captured', 102, 332, 220, { fontSize: 12.5, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('30% Off CRM Deal · Active', 102, 352, 220, { fontSize: 9.5, fontWeight: 600, fill: '#10B981' }, f);

      this.line('LSep', 28, 380, 304, { stroke: '#334155', strokeWeight: 1 }, f);
      this.text('Latest Leads (Opt-In Verified):', 28, 392, 260, { fontSize: 10, fontWeight: 700, fill: '#94A3B8' }, f);
      this.text('1. sunil@wpsimplified.com · Agency (10–25)', 28, 416, 300, { fontSize: 10, fontWeight: 500, fill: '#FFFFFF' }, f);
      this.text('2. priya@delhiwp.in · Freelancer', 28, 438, 300, { fontSize: 10, fontWeight: 500, fill: '#FFFFFF' }, f);
      this.text('3. rahul@wpindia.org · Enterprise', 28, 460, 300, { fontSize: 10, fontWeight: 500, fill: '#FFFFFF' }, f);

      this.rect('ExpLeads', 28, 502, 304, 40, { fill: '#D97706', cornerRadius: 20 }, f);
      this.text('EXPORT CAPTURED LEADS (CSV)', 30, 515, 300, { fontSize: 10, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('AddDeal', 28, 552, 304, 38, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 19 }, f);
      this.text('+ ADD SPONSOR DEAL', 30, 564, 300, { fontSize: 10, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);

      this.adminBottomNav(f, 'more');
    }
  }
}

const builder = new AuditedApkBuilder();
builder.buildAll32Screens();

const scriptPath = path.resolve(rootDir, 'scratch/figcraft_apk_32_script.json');
fs.writeFileSync(scriptPath, JSON.stringify(builder.commands, null, 2), 'utf8');
console.log(`Generated ${builder.commands.length} FigCraft commands for ALL 32 audited Mobile APK screens.`);
console.log(`Saved script to ${scriptPath}`);
