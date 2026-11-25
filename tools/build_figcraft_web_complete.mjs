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
  delhiSkyline: path.resolve(rootDir, 'docs/designs/assets/delhi_skyline.webp'),
  indiaGate: path.resolve(rootDir, 'docs/designs/assets/india_gate.jpg'),
  qrSample: path.resolve(rootDir, 'docs/designs/assets/qr_sample.png'),
  pankaj: path.resolve(rootDir, 'docs/designs/assets/pankaj_kumar.webp'),
  radhika: path.resolve(rootDir, 'docs/designs/assets/radhika_bhama.jpg'),
  amit: path.resolve(rootDir, 'docs/designs/assets/amit_singh.webp'),
  shammi: path.resolve(rootDir, 'docs/designs/assets/shammi_proothi.webp'),
  om: path.resolve(rootDir, 'docs/designs/assets/om_asnani.webp'),
  abhay: path.resolve(rootDir, 'docs/designs/assets/abhay_kulkarni.webp'),
  shreya: path.resolve(rootDir, 'docs/designs/assets/shreya_agarwal.webp'),
  sponsor_elementor: path.resolve(rootDir, 'docs/designs/assets/sponsor_elementor.webp'),
  sponsor_nexgi: path.resolve(rootDir, 'docs/designs/assets/sponsor_nexgi.webp'),
  sponsor_cookieadmin: path.resolve(rootDir, 'docs/designs/assets/sponsor_cookieadmin.webp'),
  sponsor_pankaj: path.resolve(rootDir, 'docs/designs/assets/sponsor_pankaj_seo.webp'),
  sponsor_wpfusion: path.resolve(rootDir, 'docs/designs/assets/sponsor_wpfusion.webp'),
  sponsor_housewise: path.resolve(rootDir, 'docs/designs/assets/sponsor_housewise.webp'),
  sponsor_zuptek: path.resolve(rootDir, 'docs/designs/assets/sponsor_zuptek.webp'),
  sponsor_digiliance: path.resolve(rootDir, 'docs/designs/assets/sponsor_digiliance.webp'),
  sponsor_montaki: path.resolve(rootDir, 'docs/designs/assets/sponsor_montaki.webp'),
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
  check: 'M20 6 9 17l-5-5',
  award: 'M6 8a6 6 0 1 0 12 0 6 6 0 1 0 -12 0zM15.48 12.89l 2.52 9.11-6-4-6 4 2.52-9.11',
  camera: 'M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3zM9 13a3 3 0 1 0 6 0 3 3 0 1 0 -6 0z',
  lock: 'M6 11h12a2 2 0 0 1 2 2v6a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-6a2 2 0 0 1 2 -2zM8 11V7a4 4 0 0 1 8 0v4',
  globe: 'M2 12a10 10 0 1 0 20 0 10 10 0 1 0 -20 0zM12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20M2 12h20',
  hand: 'M18 11V6a2 2 0 0 0-4 0v5M14 10V4a2 2 0 0 0-4 0v2M10 10.5V6a2 2 0 0 0-4 0v8M18 8a2 2 0 1 1 4 0v6a8 8 0 0 1-8 8h-2c-2.8 0-4.5-.86-5.99-2.34l-3.6-3.6a2 2 0 0 1 2.83-2.82L7 15',
  messageCircle: 'M7.9 20A9 9 0 1 0 4 16.1L2 22z',
  download: 'M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l 5 5 5-5M12 15V3',
  play: 'M7 4v16l13-8z',
  qrCode: 'M4 3h5a1 1 0 0 1 1 1v5a1 1 0 0 1 -1 1h-5a1 1 0 0 1 -1 -1v-5a1 1 0 0 1 1 -1zM15 3h5a1 1 0 0 1 1 1v5a1 1 0 0 1 -1 1h-5a1 1 0 0 1 -1 -1v-5a1 1 0 0 1 1 -1zM4 14h5a1 1 0 0 1 1 1v5a1 1 0 0 1 -1 1h-5a1 1 0 0 1 -1 -1v-5a1 1 0 0 1 1 -1zM17 14v3M14 17h3M14 14h.01M20 17v.01M17 20v.01M20 20v.01',
};

const ICON_DIMS = {
  check: [16, 11],
  award: [12, 20],
  mapPin: [14, 18],
  shield: [16, 19],
  heart: [20, 18],
  lock: [16, 18],
  play: [14, 16],
};

class WebFigCraftBuilder {
  constructor() {
    this.commands = [];
    this.nextId = 2;
    this.commands.push({ command: 'page.rename', params: { name: 'Website Frontend - 14 Complete Web Screens (W01-W14)' } });
    this.idx = 0;
  }

  coords() {
    const col = this.idx % 3;
    const row = Math.floor(this.idx / 3);
    this.idx++;
    return { x: 80 + col * 1400, y: 120 + row * 920 };
  }

  webFrame(title, subtitle) {
    const pos = this.coords();
    this.text(title, pos.x, pos.y - 54, 1280, { fontSize: 18, fontWeight: 800, fill: '#0F172A' }, null, `Lbl ${title}`);
    this.text(subtitle, pos.x, pos.y - 30, 1280, { fontSize: 12, fontWeight: 600, fill: '#64748B' }, null, `Sub ${title}`);
    const id = this.nextId++;
    this.commands.push({
      command: 'frame.create',
      params: { name: title, x: pos.x, y: pos.y, width: 1280, height: 780, props: { fill: '#FFF7ED', cornerRadius: 16, clip: true } }
    });
    return id;
  }

  subFrame(name, x, y, width, height, props, parent) {
    const id = this.nextId++;
    this.commands.push({
      command: 'frame.create',
      params: { name, x, y, width, height, props, parent }
    });
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

  image(filePath, x, y, width, height, parent, name = 'Img', mode = 'fit') {
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
    const wrap = this.subFrame('Av', x, y, size, size, {
      fill: '#FFEDD5', cornerRadius: size / 2, clip: true, stroke: borderColor, strokeWeight: 2
    }, parent);
    this.image(filePath, 0, 0, size, size, wrap, 'AvImg', 'fill');
    if (onlineDot) {
      this.ellipse('Dot', x + size - 12, y + size - 12, 12, 12, { fill: '#10B981', stroke: '#FFFFFF', strokeWeight: 2 }, parent);
    }
    return wrap;
  }

  logoTile(filePath, x, y, w, h, parent, radius = 12, padding = 8, fill = '#FFFFFF') {
    const box = this.subFrame('Logo', x, y, w, h, { fill, cornerRadius: radius, clip: true, stroke: '#E2E8F0', strokeWeight: 1 }, parent);
    this.image(filePath, padding, padding, w - padding * 2, h - padding * 2, box, 'LogoImg', 'fit');
    return box;
  }

  webNavbar(f, url, activeNav = 'events', isEventWorkspace = true) {
    // Browser Chrome Strip (40px)
    this.rect('ChromeBg', 0, 0, 1280, 40, { fill: '#0F172A' }, f);
    this.ellipse('Dot1', 18, 14, 12, 12, { fill: '#EF4444' }, f);
    this.ellipse('Dot2', 36, 14, 12, 12, { fill: '#F59E0B' }, f);
    this.ellipse('Dot3', 54, 14, 12, 12, { fill: '#10B981' }, f);
    this.rect('UrlPill', 260, 7, 760, 26, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 13 }, f);
    this.text(url, 280, 13, 720, { fontSize: 11, fontWeight: 500, fill: '#CBD5E1', textAlign: 'center' }, f);

    // Web Header (68px below chrome)
    this.rect('NavBg', 0, 40, 1280, 68, { fill: '#FFFFFF' }, f);
    this.line('NavLine', 0, 108, 1280, { stroke: '#E2E8F0', strokeWeight: 1 }, f);
    this.image(assets.cbWordmark, 56, 56, 148, 36, f, 'WM', 'fit');
    this.line('NavSep', 218, 60, 1, { stroke: '#E2E8F0', strokeWeight: 1 }, f);
    this.logoTile(assets.delhiFavicon, 228, 56, 36, 36, f, 18, 4);

    if (isEventWorkspace) {
      const links = [
        { k: 'home', l: 'Home', x: 310 },
        { k: 'myday', l: 'My Day', x: 390 },
        { k: 'explore', l: 'Explore', x: 486 },
        { k: 'quest', l: 'Quests', x: 582 },
        { k: 'contribute', l: 'Contribute', x: 672 },
        { k: 'card', l: 'CampCard', x: 782 },
      ];
      for (const l of links) {
        const on = l.k === activeNav;
        if (on) this.rect(`N_${l.k}`, l.x - 12, 57, 84, 34, { fill: '#FFEDD5', cornerRadius: 17 }, f);
        this.text(l.l, l.x - 10, 66, 80, { fontSize: 13, fontWeight: on ? 800 : 600, fill: on ? '#D33417' : '#475569', textAlign: 'center' }, f);
      }
      this.rect('NavGuide', 1020, 56, 106, 36, { fill: '#FFF7ED', stroke: '#FED7AA', strokeWeight: 1, cornerRadius: 18 }, f);
      this.text('101 Guide', 1024, 66, 98, { fontSize: 12, fontWeight: 700, fill: '#EA580C', textAlign: 'center' }, f);
      this.rect('NavSwitch', 1138, 56, 86, 36, { fill: '#0F172A', cornerRadius: 18 }, f);
      this.text('All Camps', 1142, 66, 78, { fontSize: 12, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);
    } else {
      const links = [
        { k: 'events', l: 'WordCamps', x: 640 },
        { k: 'about', l: 'About', x: 756 },
        { k: 'guide', l: 'First-Timer Guide', x: 834 },
      ];
      for (const l of links) {
        const on = l.k === activeNav;
        this.text(l.l, l.x, 66, 128, { fontSize: 13, fontWeight: on ? 800 : 600, fill: on ? '#FF4D2D' : '#475569' }, f);
      }
      this.rect('NavSync', 990, 56, 114, 36, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 18 }, f);
      this.text('Open on Phone', 994, 66, 106, { fontSize: 12, fontWeight: 700, fill: '#0F172A', textAlign: 'center' }, f);
      this.rect('NavGetApp', 1114, 56, 110, 36, { fill: '#FF4D2D', cornerRadius: 18 }, f);
      this.text('Install App', 1118, 66, 102, { fontSize: 12, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
    }
  }

  pill(f, label, x, y, w, active = false, color = '#0F172A') {
    this.rect(`P_${label}_${x}`, x, y, w, 34, { fill: active ? color : '#FFFFFF', stroke: active ? color : '#E2E8F0', strokeWeight: 1, cornerRadius: 17 }, f);
    this.text(label, x + 6, y + 9, w - 12, { fontSize: 12, fontWeight: active ? 700 : 600, fill: active ? '#FFFFFF' : '#475569', textAlign: 'center' }, f);
  }

  buildAll14WebScreens() {
    // W01: Landing & WordCamp Directory (/)
    {
      const f = this.webFrame('W01 · Landing & WordCamp Directory', 'Public Discovery Hero · Country Filters · Featured Delhi Card · Desktop-to-Phone Sync');
      this.webNavbar(f, 'https://campbuddy.club/', 'events', false);

      // Left Hero
      this.rect('HeroBadge', 56, 144, 290, 30, { fill: '#FFEDD5', cornerRadius: 15 }, f);
      this.text('DECEMBER 12–13, 2026 · DAIC NEW DELHI', 64, 152, 274, { fontSize: 11, fontWeight: 800, fill: '#D33417', textAlign: 'center' }, f);
      this.text('Make the most of your WordCamp', 56, 190, 560, { fontSize: 44, fontWeight: 900, fill: '#0F172A' }, f);
      this.text('Navigate multi-track sessions at Dr. Ambedkar International Centre, curate your personal My Day timetable offline, wave at peers with shared interests, and swap QR CampCards — zero login required.', 56, 300, 540, { fontSize: 15, fontWeight: 400, fill: '#475569' }, f);

      this.rect('HeroCta1', 56, 390, 224, 48, { fill: '#FF4D2D', cornerRadius: 24 }, f);
      this.text('Open WordCamp Delhi →', 60, 405, 216, { fontSize: 14, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('HeroCta2', 296, 390, 210, 48, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1.5, cornerRadius: 24 }, f);
      this.text('First-Timer Guide', 300, 405, 202, { fontSize: 14, fontWeight: 700, fill: '#0F172A', textAlign: 'center' }, f);

      // Right Hero Image
      const heroImg = this.subFrame('SkyBox', 644, 142, 580, 296, { fill: '#FFFFFF', cornerRadius: 22, clip: true, stroke: '#E2E8F0', strokeWeight: 1 }, f);
      this.image(assets.delhiSkyline, 0, 0, 580, 296, heroImg, 'Sky', 'fill');

      // Featured Directory Row below Hero
      this.pill(f, 'All WordCamps (26)', 56, 474, 152, true);
      this.pill(f, 'India (8)', 220, 474, 108, false);
      this.pill(f, 'Europe (10)', 340, 474, 118, false);
      this.pill(f, 'North America (8)', 470, 474, 146, false);

      this.rect('FeatCard', 56, 526, 760, 212, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 22 }, f);
      this.logoTile(assets.delhiFavicon, 80, 550, 74, 74, f, 18, 8);
      this.rect('LivePill', 172, 550, 140, 24, { fill: '#ECFDF5', cornerRadius: 12 }, f);
      this.text('LIVE THIS WEEKEND', 176, 556, 132, { fontSize: 10, fontWeight: 800, fill: '#047857', textAlign: 'center' }, f);
      this.text('WordCamp Delhi 2026', 172, 584, 380, { fontSize: 22, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Dec 12–13, 2026 · Dr. Ambedkar International Centre · 22 Talks · 17 Sponsors', 172, 616, 580, { fontSize: 13, fontWeight: 500, fill: '#64748B' }, f);
      this.rect('EvBtn1', 172, 666, 184, 42, { fill: '#FF4D2D', cornerRadius: 21 }, f);
      this.text('Open Companion →', 176, 679, 176, { fontSize: 13, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('EvBtn2', 370, 666, 170, 42, { fill: '#FFF7ED', stroke: '#FED7AA', strokeWeight: 1, cornerRadius: 21 }, f);
      this.text('101 First-Timer', 374, 679, 162, { fontSize: 13, fontWeight: 700, fill: '#EA580C', textAlign: 'center' }, f);

      // Right Desktop Sync Card
      this.rect('SyncW', 840, 526, 384, 212, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 22 }, f);
      this.text('Transfer Plan to Phone', 868, 554, 320, { fontSize: 18, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('Planned sessions on desktop? Move everything privately with a 6-digit code — no accounts.', 868, 584, 328, { fontSize: 12.5, fontWeight: 400, fill: '#CBD5E1' }, f);
      this.rect('CodeBox', 868, 650, 190, 52, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 14 }, f);
      this.text('4 8 2 · 9 1 0', 874, 664, 178, { fontSize: 18, fontWeight: 800, fill: '#F59E0B', textAlign: 'center' }, f);
      this.rect('SyncBtn', 1072, 650, 126, 52, { fill: '#10B981', cornerRadius: 14 }, f);
      this.text('Send Now', 1076, 667, 118, { fontSize: 13, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
    }

    // W02: Web Onboarding & Bring-My-Data Sync Modal
    {
      const f = this.webFrame('W02 · Personalize Onboarding & Device Transfer', '5-Step Web Personalization Card · Encrypted 6-Digit Desktop↔Phone Transfer');
      this.webNavbar(f, 'https://campbuddy.club/#personalize', 'events', false);

      // Left Onboarding Card
      this.rect('OnbCard', 56, 138, 680, 600, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 22 }, f);
      this.rect('OnbTop', 56, 138, 680, 5, { fill: '#FF4D2D', cornerRadius: 2.5 }, f);
      for (let i = 0; i < 5; i++) this.rect(`WBar${i}`, 96 + i * 96, 172, 82, 6, { fill: i < 2 ? '#FF4D2D' : '#E2E8F0', cornerRadius: 3 }, f);
      this.text('Step 2 of 5', 590, 165, 108, { fontSize: 12, fontWeight: 700, fill: '#64748B', textAlign: 'right' }, f);

      this.text('What topics & tracks excite you most?', 96, 206, 560, { fontSize: 24, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Highlights matching talks across tracks and suggests peers you may want to meet. Stays in your browser.', 96, 244, 580, { fontSize: 13.5, fontWeight: 400, fill: '#64748B' }, f);

      const tags = [
        { l: 'Developers & Block Themes', on: true, x: 96, y: 310 },
        { l: 'AI Workflows & Automation', on: true, x: 400, y: 310 },
        { l: 'Agency Owners & Growth', on: false, x: 96, y: 374 },
        { l: 'Core Contributors & Docs', on: true, x: 400, y: 374 },
        { l: 'Designers & Accessibility', on: false, x: 96, y: 438 },
        { l: 'First-Timer / Student Track', on: false, x: 400, y: 438 },
      ];
      for (const t of tags) {
        this.rect(`WT_${t.l}`, t.x, t.y, 286, 50, { fill: t.on ? '#FFF7ED' : '#F8FAFC', stroke: t.on ? '#FF4D2D' : '#E2E8F0', strokeWeight: t.on ? 1.5 : 1, cornerRadius: 14 }, f);
        this.text((t.on ? '✓ ' : '') + t.l, t.x + 16, t.y + 16, 254, { fontSize: 13, fontWeight: t.on ? 700 : 500, fill: '#0F172A' }, f);
      }

      this.text('Skip all questions', 96, 666, 160, { fontSize: 13, fontWeight: 600, fill: '#64748B' }, f);
      this.rect('WCont', 526, 650, 172, 46, { fill: '#0F172A', cornerRadius: 23 }, f);
      this.text('Continue →', 530, 665, 164, { fontSize: 13.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);

      // Right Side: Bring Data & Privacy Guarantee
      this.rect('BringBox', 764, 138, 460, 356, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 22 }, f);
      this.text('Bring My Data Across Devices', 796, 170, 396, { fontSize: 20, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Scan a quick QR on your phone or enter the 6-digit one-time transfer phrase to mirror saved sessions & CampCard.', 796, 206, 396, { fontSize: 13, fontWeight: 400, fill: '#64748B' }, f);
      this.rect('QRWrap', 918, 266, 152, 152, { fill: '#F8FAFC', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.image(assets.qrSample, 932, 280, 124, 124, f, 'SyncQR', 'fit');
      this.text('Encrypted end-to-end · Expires in 5 minutes', 796, 438, 396, { fontSize: 12, fontWeight: 700, fill: '#059669', textAlign: 'center' }, f);

      this.rect('PrivW', 764, 514, 460, 224, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 22 }, f);
      this.vector('lock', 796, 548, 24, '#10B981', f);
      this.text('Zero Login & On-Device First', 832, 548, 340, { fontSize: 17, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('• Your schedule & draft CampCard never leave your browser.\n• Clear or export everything in 1 click under DPDP Act 2023.\n• Works 100% offline once opened at the venue.', 796, 594, 396, { fontSize: 13, fontWeight: 400, fill: '#CBD5E1' }, f);
    }

    // W03: About CampBuddy (/about)
    {
      const f = this.webFrame('W03 · About CampBuddy & Capabilities', 'Built for WordCamp Attendees · Zero Account Philosophy · Android APK & PWA Install');
      this.webNavbar(f, 'https://campbuddy.club/about', 'about', false);

      this.rect('AbHero', 56, 136, 1168, 224, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 24 }, f);
      this.text('Built by WordPress Community Volunteers', 96, 168, 700, { fontSize: 32, fontWeight: 900, fill: '#FFFFFF' }, f);
      this.text('CampBuddy turns official WordCamp schedules, venue notes, speaker lineups, Contributor Day tables, and sponsor perks into one lightning-fast offline companion — available on Web, PWA, and Native Android APK.', 96, 218, 780, { fontSize: 14.5, fontWeight: 400, fill: '#CBD5E1' }, f);
      this.rect('AbBtn1', 96, 290, 210, 42, { fill: '#FF4D2D', cornerRadius: 21 }, f);
      this.text('Download Android APK', 100, 303, 202, { fontSize: 13, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('AbBtn2', 322, 290, 190, 42, { fill: '#1E293B', stroke: '#475569', strokeWeight: 1, cornerRadius: 21 }, f);
      this.text('Browse WordCamps →', 326, 303, 182, { fontSize: 13, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.image(assets.cbIcon, 1044, 182, 128, 128, f, 'AbIco', 'fit');

      const pillars = [
        { t: 'Works Inside Basement Venues', s: 'Every schedule, speaker bio, venue Wi-Fi password, and saved badge stays stored on your device.', i: 'shield', x: 56 },
        { t: 'Interest Matching Without Spam', s: 'Create a private discovery card, wave anonymously, and unlock 3-msg daily chat only on mutual waves.', i: 'hand', x: 454 },
        { t: 'Digital QR CampCard Studio', s: 'Pick from 7 tactile badge layouts, filter what shares publicly, and swap scannable passes offline.', i: 'card', x: 852 },
      ];
      pillars.forEach(p => {
        this.rect(`Pil_${p.t}`, p.x, 388, 372, 186, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 20 }, f);
        this.ellipse(`PIBg_${p.x}`, p.x + 28, 414, 46, 46, { fill: '#FFEDD5' }, f);
        this.vector(p.i, p.x + 40, 426, 22, '#EA580C', f);
        this.text(p.t, p.x + 28, 478, 316, { fontSize: 16, fontWeight: 800, fill: '#0F172A' }, f);
        this.text(p.s, p.x + 28, 508, 316, { fontSize: 12.5, fontWeight: 400, fill: '#64748B' }, f);
      });

      this.rect('OrgBanner', 56, 600, 1168, 140, { fill: '#FFFBEB', stroke: '#FDE68A', strokeWeight: 1.5, cornerRadius: 20 }, f);
      this.text('Organizing a WordCamp?', 92, 628, 500, { fontSize: 20, fontWeight: 800, fill: '#92400E' }, f);
      this.text('Your WordCamp schedule, speakers, and sponsors sync automatically from central.wordcamp.org. Manage live delays, broadcasts, tables, quests & social kits directly.', 92, 662, 780, { fontSize: 13, fontWeight: 500, fill: '#78350F' }, f);
      this.rect('OrgPortalBtn', 964, 646, 224, 46, { fill: '#0F172A', cornerRadius: 23 }, f);
      this.text('Staff Console Overview', 968, 661, 216, { fontSize: 13, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
    }

    // W04: First-Timer Guide (/event/wordcamp-delhi-2026/guide)
    {
      const f = this.webFrame('W04 · First-Timer Handbook & Venue Practicals', 'DAIC Venue & Metro Guide · WordCamp Day Flow · WordPress Jargon · FAQs');
      this.webNavbar(f, 'https://campbuddy.club/event/wordcamp-delhi-2026/guide', 'guide', true);

      // Hero with India Gate
      this.rect('GCard', 56, 134, 1168, 220, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 22 }, f);
      const ig = this.subFrame('IGImg', 80, 158, 380, 172, { fill: '#FFEDD5', cornerRadius: 16, clip: true }, f);
      this.image(assets.indiaGate, 0, 0, 380, 172, ig, 'IG', 'fill');
      this.rect('G101', 490, 160, 140, 26, { fill: '#FFEDD5', cornerRadius: 13 }, f);
      this.text('WORDCAMP 101 GUIDE', 496, 166, 128, { fontSize: 10, fontWeight: 800, fill: '#D33417', textAlign: 'center' }, f);
      this.text('First Time at WordCamp Delhi?', 490, 198, 660, { fontSize: 28, fontWeight: 900, fill: '#0F172A' }, f);
      this.text('Everything you need for a relaxed two-day experience at DAIC New Delhi — where to grab your badge, metro exits, Wi-Fi password, and what Contributor Day actually means.', 490, 240, 680, { fontSize: 13.5, fontWeight: 400, fill: '#64748B' }, f);

      // Jump pills
      const jumps = ['At DAIC Venue', 'How the Day Flows', "Words You'll Hear", 'Contributor Day Basics', 'What to Bring', 'FAQs'];
      jumps.forEach((j, idx) => this.pill(f, j, 56 + idx * 184, 376, 174, idx === 0, '#FF4D2D'));

      // Bottom 3 practical cards
      this.rect('Pract1', 56, 434, 372, 304, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 20 }, f);
      this.text('At DAIC New Delhi', 84, 462, 316, { fontSize: 18, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('📍 15 Janpath Rd, Windsor Place\n🚇 Janpath (Violet) & Patel Chowk\n📶 Wi-Fi: WCDELHI-GUEST\n🎫 Doors open: 8:00 AM daily\n☕ Chai, snacks & hot lunch included', 84, 500, 316, { fontSize: 13, fontWeight: 500, fill: '#475569' }, f);

      this.rect('Pract2', 454, 434, 372, 304, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 20 }, f);
      this.text("Words You'll Hear", 482, 462, 316, { fontSize: 18, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('• Happiness Bar — Free live WP clinic\n• Hallway Track — Informal hall chats\n• Contributor Day — Day 1 workshops\n• Track 1 & 2 — Simultaneous talks\n• Swag / Steals — Free goodies & tools', 482, 500, 316, { fontSize: 13, fontWeight: 500, fill: '#475569' }, f);

      this.rect('Pract3', 852, 434, 372, 304, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 20 }, f);
      this.text('Need Help On-Site?', 880, 462, 316, { fontSize: 18, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('Volunteers in bright CampBuddy lanyards are at every hall door.\n\nConfidential Safety Contact:\ndelhi@wordcamp.org', 880, 504, 316, { fontSize: 13, fontWeight: 400, fill: '#CBD5E1' }, f);
      this.rect('CodeBtn', 880, 668, 316, 42, { fill: '#FF4D2D', cornerRadius: 21 }, f);
      this.text('Open Code of Conduct →', 884, 681, 308, { fontSize: 13, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
    }

    // W05: Privacy Policy & DPDP Act 2023 (/privacy & /terms)
    {
      const f = this.webFrame('W05 · Privacy Policy & DPDP Act 2023 Rights', 'Device-Only Data Guarantee · 90-Day Expiry · Download JSON & Erase Controls');
      this.webNavbar(f, 'https://campbuddy.club/privacy', 'about', false);

      this.pill(f, 'Privacy Notice (DPDP Act)', 56, 136, 220, true, '#0F172A');
      this.pill(f, 'Terms of Use & Conduct', 290, 136, 210, false);

      this.rect('LCard1', 56, 194, 720, 540, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 22 }, f);
      this.text('Privacy Notice · Updated Oct 2026', 92, 224, 520, { fontSize: 22, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('1. What Stays Strictly in Your Browser\nSaved talk bookmarks, My Day notes, meetings planned, onboarding choices, and your draft CampCard never leave your device unless you actively tap Share.', 92, 274, 640, { fontSize: 13.5, fontWeight: 500, fill: '#475569' }, f);
      this.text('2. Discovery & Waves Auto-Expiry\nDiscovery profiles and mutual match messages auto-delete permanently 90 days after the WordCamp ends.', 92, 366, 640, { fontSize: 13.5, fontWeight: 500, fill: '#475569' }, f);
      this.text('3. Right to Access & Erasure\nYou can export a complete JSON copy or erase everything immediately without creating an account or contacting support.', 92, 456, 640, { fontSize: 13.5, fontWeight: 500, fill: '#475569' }, f);

      this.rect('ExportBtn', 92, 570, 300, 46, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1.5, cornerRadius: 23 }, f);
      this.text('Download Complete Data (JSON)', 96, 584, 292, { fontSize: 13, fontWeight: 700, fill: '#0F172A', textAlign: 'center' }, f);
      this.rect('EraseBtn', 412, 570, 290, 46, { fill: '#EF4444', cornerRadius: 23 }, f);
      this.text('Clear All Data on This Device', 416, 584, 282, { fontSize: 13, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);

      this.rect('Officer', 804, 194, 420, 270, { fill: '#FFF7ED', stroke: '#FED7AA', strokeWeight: 1.5, cornerRadius: 22 }, f);
      this.text('Data Officer & Grievance Support', 836, 228, 356, { fontSize: 18, fontWeight: 800, fill: '#92400E' }, f);
      this.text('WPSimplified Community Support\nE-mail: hello@wpsimplified.in\nResponse SLA: Within 48 hours for all DPDP access, correction, or roster removal requests.', 836, 268, 356, { fontSize: 13, fontWeight: 500, fill: '#78350F' }, f);
      this.rect('MailBtn', 836, 386, 356, 44, { fill: '#0F172A', cornerRadius: 22 }, f);
      this.text('Send Data Request E-mail →', 840, 399, 348, { fontSize: 13, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
    }

    // W06: Roster Self-Removal & Sponsor Deal Submission Portal
    {
      const f = this.webFrame('W06 · Attendee Roster Removal & Sponsor Deal Portal', 'Instant Self-Takedown Page · Sponsor Coupon & Lead Capture Submission Form');
      this.webNavbar(f, 'https://campbuddy.club/sponsor-deals/submit', 'events', false);

      // Left: Roster Removal
      this.rect('RemCard', 56, 138, 560, 600, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 22 }, f);
      this.text('Remove Name from Public Roster', 90, 170, 480, { fontSize: 20, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Do not want your name shown in WordCamp Delhi attendee listings? Search your name below to remove it immediately.', 90, 206, 492, { fontSize: 13, fontWeight: 400, fill: '#64748B' }, f);
      this.rect('RemSearch', 90, 270, 492, 46, { fill: '#F8FAFC', stroke: '#CBD5E1', strokeWeight: 1, cornerRadius: 12 }, f);
      this.text('Search attendee list by name...', 110, 286, 340, { fontSize: 13, fontWeight: 500, fill: '#94A3B8' }, f);
      this.rect('RemHit', 90, 340, 492, 92, { fill: '#FFF5F5', stroke: '#FCA5A5', strokeWeight: 1.5, cornerRadius: 16 }, f);
      this.text('Rohan Verma · Attendee Listing', 112, 360, 300, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Synced from official WordCamp public attendees', 112, 384, 300, { fontSize: 11.5, fontWeight: 500, fill: '#64748B' }, f);
      this.rect('RemBtn', 428, 366, 136, 38, { fill: '#EF4444', cornerRadius: 19 }, f);
      this.text('Remove Me', 432, 378, 128, { fontSize: 12, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);

      // Right: Sponsor Deal Submission
      this.rect('SubCard', 644, 138, 580, 600, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 22 }, f);
      this.text('Submit Attendee Sponsor Offer', 678, 170, 480, { fontSize: 20, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Exclusive discounts, extended free trials, or swag vouchers verified by CampBuddy organizers.', 678, 204, 512, { fontSize: 13, fontWeight: 400, fill: '#64748B' }, f);
      const subFields = [
        { l: 'Target Event · WordCamp Delhi 2026', y: 266 },
        { l: 'Brand · WP Fusion · 30% Off Annual CRM License', y: 332 },
        { l: 'Promo Code: WCDELHI30   |   Opt-In Lead Capture: Active', y: 398 },
        { l: 'Landing URL: https://wpfusion.com/wordcamp-delhi', y: 464 },
      ];
      subFields.forEach(sf => {
        this.rect(`SF_${sf.y}`, 678, sf.y, 512, 48, { fill: '#F8FAFC', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 12 }, f);
        this.text(sf.l, 696, sf.y + 15, 476, { fontSize: 13, fontWeight: 600, fill: '#0F172A' }, f);
      });
      this.rect('SubBtn', 678, 558, 512, 48, { fill: '#FF4D2D', cornerRadius: 24 }, f);
      this.text('Submit Sponsor Offer for Review →', 682, 573, 504, { fontSize: 14, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
    }

    // W07: WordCamp Delhi Live Event Home (/event/wordcamp-delhi-2026)
    {
      const f = this.webFrame('W07 · WordCamp Delhi Live Home Dashboard', 'Navy Clock Hero · Live Organizer Notice · My People Strip · Action Hubs');
      this.webNavbar(f, 'https://campbuddy.club/event/wordcamp-delhi-2026', 'home', true);

      // Live Broadcast Alert Banner
      this.rect('LiveNotice', 56, 128, 1168, 56, { fill: '#FEF3C7', stroke: '#F59E0B', strokeWeight: 1.5, cornerRadius: 16 }, f);
      this.vector('bell', 80, 144, 22, '#B45309', f);
      this.text('FROM ORGANIZERS: Track 1 BHIM Auditorium is running +15m behind — all My Day times adjusted automatically.', 116, 148, 960, { fontSize: 13.5, fontWeight: 700, fill: '#92400E' }, f);

      // Left Navy Hero
      this.rect('WHero', 56, 206, 520, 316, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 24 }, f);
      this.text('LIVE EVENT DASHBOARD · DAY 1', 88, 238, 300, { fontSize: 11, fontWeight: 800, fill: '#F59E0B' }, f);
      this.logoTile(assets.delhiFavicon, 500, 228, 48, 48, f, 24, 5);
      this.text('10:30 AM', 88, 270, 340, { fontSize: 48, fontWeight: 900, fill: '#FFFFFF' }, f);
      this.text('Dec 12, 2026 · DAIC New Delhi', 88, 336, 360, { fontSize: 14, fontWeight: 500, fill: '#CBD5E1' }, f);
      this.rect('NextTalk', 88, 388, 456, 104, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('NEXT UP · STARTS IN 15M', 110, 408, 260, { fontSize: 10.5, fontWeight: 800, fill: '#FBBF24' }, f);
      this.text('10 Things to Boost Local SEO in AI', 110, 430, 320, { fontSize: 15, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('Pankaj Kumar · BHIM Auditorium', 110, 456, 300, { fontSize: 12, fontWeight: 400, fill: '#94A3B8' }, f);
      this.rect('HeroView', 442, 420, 82, 38, { fill: '#D97706', cornerRadius: 19 }, f);
      this.text('View', 446, 431, 74, { fontSize: 12.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);

      // Right Side: Action Hub Grid (2x3)
      const tiles = [
        { t: 'Schedule Matrix', s: '22 talks · All stages', i: 'calendar', x: 604, y: 206 },
        { t: 'My Day Planner', s: '4 saved sessions', i: 'clock', x: 924, y: 206 },
        { t: 'People & Waves', s: '11 speakers · 3 waves', i: 'users', x: 604, y: 316 },
        { t: 'Sponsors & Perks', s: '17 brands & deals', i: 'gift', x: 924, y: 316 },
        { t: 'Contributor Day', s: '4 team tables', i: 'award', x: 604, y: 426 },
        { t: 'CampCard Studio', s: 'Share QR badge', i: 'card', x: 924, y: 426 },
      ];
      tiles.forEach(tl => {
        this.rect(`HT_${tl.t}`, tl.x, tl.y, 300, 96, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 18 }, f);
        this.ellipse(`HTI_${tl.t}`, tl.x + 20, tl.y + 26, 44, 44, { fill: '#FFEDD5' }, f);
        this.vector(tl.i, tl.x + 32, tl.y + 38, 20, '#EA580C', f);
        this.text(tl.t, tl.x + 78, tl.y + 26, 200, { fontSize: 15, fontWeight: 800, fill: '#0F172A' }, f);
        this.text(tl.s, tl.x + 78, tl.y + 52, 200, { fontSize: 12.5, fontWeight: 500, fill: '#64748B' }, f);
      });

      // People Strip & Help Desk bottom row
      this.rect('Strip', 56, 548, 800, 190, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 20 }, f);
      this.text('Keynote Speakers & Matches Near You', 84, 574, 460, { fontSize: 16, fontWeight: 800, fill: '#0F172A' }, f);
      const spks = [
        { k: 'radhika', n: 'Radhika B.', x: 84 },
        { k: 'pankaj', n: 'Pankaj K.', x: 204 },
        { k: 'amit', n: 'Amit S.', x: 324 },
        { k: 'shammi', n: 'Shammi P.', x: 444 },
        { k: 'om', n: 'Omprakash A.', x: 564 },
        { k: 'abhay', n: 'Abhay K.', x: 684 },
      ];
      spks.forEach(s => {
        this.avatar(assets[s.k], s.x + 12, 616, 62, f, '#E2E8F0', true);
        this.text(s.n, s.x, 690, 88, { fontSize: 11.5, fontWeight: 700, fill: '#0F172A', textAlign: 'center' }, f);
      });

      this.rect('SOSW', 880, 548, 344, 190, { fill: '#FFF5F5', stroke: '#FCA5A5', strokeWeight: 1.5, cornerRadius: 20 }, f);
      this.vector('shield', 912, 576, 26, '#DC2626', f);
      this.text('Safety & Help Desk', 948, 576, 240, { fontSize: 18, fontWeight: 800, fill: '#DC2626' }, f);
      this.text('Venue emergency support, quiet room directions & confidential reporting.', 912, 616, 280, { fontSize: 12.5, fontWeight: 500, fill: '#475569' }, f);
      this.rect('SOSBtn', 912, 672, 280, 42, { fill: '#EF4444', cornerRadius: 21 }, f);
      this.text('Open Venue Safety Desk', 916, 684, 272, { fontSize: 13, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
    }

    // W08: Interactive Multi-Track Schedule Matrix & My Day
    {
      const f = this.webFrame('W08 · Multi-Track Schedule Matrix & My Day', 'Two-Track Desktop Matrix · Hall Filters · Overlap Conflict Check · Calendar Export');
      this.webNavbar(f, 'https://campbuddy.club/event/wordcamp-delhi-2026/my-day', 'myday', true);

      this.text('Schedule & My Day Planner', 56, 134, 480, { fontSize: 26, fontWeight: 900, fill: '#0F172A' }, f);
      this.pill(f, 'Day 1: Contributor Day', 680, 134, 180, false);
      this.pill(f, 'Day 2: Conference Talks', 874, 134, 196, true, '#FF4D2D');
      this.pill(f, 'Export .ics', 1084, 134, 140, false);

      // Left Tracks Matrix
      this.rect('Tr1Hdr', 200, 194, 340, 38, { fill: '#0F172A', cornerRadius: 10 }, f);
      this.text('Track 1 · BHIM Auditorium', 216, 205, 308, { fontSize: 12.5, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.rect('Tr2Hdr', 558, 194, 340, 38, { fill: '#1E293B', cornerRadius: 10 }, f);
      this.text('Track 2 · Contributor & Dev Lounge', 574, 205, 308, { fontSize: 12.5, fontWeight: 800, fill: '#FFFFFF' }, f);

      const slots = [
        { tm: '10:00 AM', t1: 'From Feature to Ecosystem', s1: 'Radhika Bhama & Raitis Sevelis', t2: 'Core & Gutenberg Sprint Kickoff', s2: 'Amit Singh · Table 1', y: 248 },
        { tm: '11:20 AM', t1: '10 Things for Local SEO in AI', s1: 'Pankaj Kumar · Beginner Friendly', t2: 'Documentation & HelpHub Prep', s2: 'Shreya Agarwal · Table 2', y: 374 },
        { tm: '04:00 PM', t1: 'PANEL: AI Era Value Shift', s1: 'Radhika, Amit, Om & Abhay · Plenary', t2: 'Agency Automation Showcase', s2: 'Abhay Kulkarni · n8n Track', y: 500 },
      ];
      slots.forEach(sl => {
        this.text(sl.tm, 56, sl.y + 34, 120, { fontSize: 15, fontWeight: 800, fill: '#D33417' }, f);
        this.rect(`S1_${sl.tm}`, 200, sl.y, 340, 108, { fill: '#FFFFFF', stroke: '#FF4D2D', strokeWeight: 1.5, cornerRadius: 16 }, f);
        this.text(sl.t1, 218, sl.y + 18, 270, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);
        this.text(sl.s1, 218, sl.y + 52, 280, { fontSize: 12, fontWeight: 500, fill: '#64748B' }, f);
        this.rect(`SV_${sl.tm}`, 218, sl.y + 74, 96, 22, { fill: '#FFEDD5', cornerRadius: 11 }, f);
        this.text('★ Saved', 222, sl.y + 78, 88, { fontSize: 10.5, fontWeight: 800, fill: '#D33417', textAlign: 'center' }, f);

        this.rect(`S2_${sl.tm}`, 558, sl.y, 340, 108, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
        this.text(sl.t2, 576, sl.y + 18, 270, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);
        this.text(sl.s2, 576, sl.y + 52, 280, { fontSize: 12, fontWeight: 500, fill: '#64748B' }, f);
      });

      // Right Sticky My Day Itinerary Sidebar
      this.rect('MySide', 924, 194, 300, 540, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 20 }, f);
      this.text('My Day Itinerary (4)', 950, 220, 240, { fontSize: 18, fontWeight: 800, fill: '#0F172A' }, f);
      this.rect('ConfBox', 950, 260, 248, 72, { fill: '#FEF2F2', stroke: '#FCA5A5', strokeWeight: 1, cornerRadius: 14 }, f);
      this.text('Schedule Conflict @ 11:20 AM\nBoth SEO Talk & HelpHub selected', 964, 276, 220, { fontSize: 11.5, fontWeight: 700, fill: '#DC2626' }, f);

      const savedSide = [
        { tm: '10:00 AM', t: 'From Feature to Ecosystem', st: 'Saved · Attended', y: 350 },
        { tm: '11:20 AM', t: 'Local SEO in the AI Era', st: 'Saved · 10m alert on', y: 446 },
        { tm: '04:00 PM', t: 'AI Era Value Shift Panel', st: 'Saved · Plenary', y: 542 },
      ];
      savedSide.forEach(ss => {
        this.rect(`SS_${ss.tm}`, 950, ss.y, 248, 82, { fill: '#F8FAFC', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 14 }, f);
        this.text(ss.tm, 966, ss.y + 14, 120, { fontSize: 10.5, fontWeight: 800, fill: '#D33417' }, f);
        this.text(ss.t, 966, ss.y + 34, 216, { fontSize: 12.5, fontWeight: 700, fill: '#0F172A' }, f);
        this.text(ss.st, 966, ss.y + 56, 216, { fontSize: 10.5, fontWeight: 600, fill: '#059669' }, f);
      });

      this.rect('PdfBtn', 950, 668, 248, 44, { fill: '#0F172A', cornerRadius: 22 }, f);
      this.text('Download My Day PDF', 954, 682, 240, { fontSize: 12.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
    }

    // W09: Explore — People Discovery & Waves
    {
      const f = this.webFrame('W09 · Explore: People Discovery & Waves', 'Opt-In Matching Card · Incoming Hidden Wave Reveal · Ranked Peers · Filterable Roster');
      this.webNavbar(f, 'https://campbuddy.club/event/wordcamp-delhi-2026/explore#people', 'explore', true);

      const expTabs = ['People (420)', 'Sponsors (17)', 'Deals (6)', 'Free Steals (4)', 'Venue Info'];
      expTabs.forEach((et, idx) => this.pill(f, et, 56 + idx * 168, 128, 156, idx === 0, '#FF4D2D'));

      // Join Discovery Card (Left column)
      this.rect('JoinW', 56, 184, 560, 140, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 20 }, f);
      this.text('You are Discoverable as Sunil Kumar Sharma', 84, 210, 420, { fontSize: 17, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('Interests: Developers · AI Workflows · Product Engineering', 84, 240, 420, { fontSize: 12.5, fontWeight: 400, fill: '#CBD5E1' }, f);
      this.rect('EditD', 84, 274, 100, 32, { fill: '#1E293B', stroke: '#475569', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Edit Card', 88, 282, 92, { fontSize: 11.5, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('LeaveD', 196, 274, 140, 32, { fill: '#450A0A', stroke: '#EF4444', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Leave Discovery', 200, 282, 132, { fontSize: 11.5, fontWeight: 700, fill: '#FCA5A5', textAlign: 'center' }, f);

      // Incoming Wave Reveal
      this.rect('InW', 56, 344, 560, 166, { fill: '#FFFFFF', stroke: '#FF4D2D', strokeWeight: 1.5, cornerRadius: 20 }, f);
      this.rect('InTag', 84, 366, 178, 24, { fill: '#FFEDD5', cornerRadius: 12 }, f);
      this.text('SOMEONE WAVED AT YOU', 88, 372, 170, { fontSize: 10, fontWeight: 800, fill: '#EA580C', textAlign: 'center' }, f);
      this.text('They also included an intro note! Wave back to swap names and read it.', 84, 404, 480, { fontSize: 13.5, fontWeight: 600, fill: '#0F172A' }, f);
      this.rect('WBBtn', 84, 452, 168, 38, { fill: '#FF4D2D', cornerRadius: 19 }, f);
      this.text('Wave Back to Reveal', 88, 463, 160, { fontSize: 12, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);

      // Mutual Match Preview Card
      this.rect('MutW', 56, 530, 560, 114, { fill: '#ECFDF5', stroke: '#86EFAC', strokeWeight: 1.5, cornerRadius: 20 }, f);
      this.avatar(assets.om, 80, 554, 62, f, '#10B981', true);
      this.text('Omprakash Asnani · 94% Interest Match', 162, 556, 300, { fontSize: 15, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Both waved · Open Chat Thread (2 msgs)', 162, 582, 290, { fontSize: 12.5, fontWeight: 600, fill: '#047857' }, f);
      this.rect('OChat', 494, 566, 98, 40, { fill: '#0F172A', cornerRadius: 20 }, f);
      this.text('Open Chat', 498, 578, 90, { fontSize: 12, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);

      // Right Column: Full Roster (420)
      this.rect('RosCard', 644, 184, 580, 554, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 22 }, f);
      this.text("Who's Attending (420 Roster)", 676, 214, 340, { fontSize: 20, fontWeight: 800, fill: '#0F172A' }, f);
      this.pill(f, 'All 420', 676, 256, 90, true);
      this.pill(f, 'Speakers 11', 776, 256, 118, false);
      this.pill(f, 'Organizers 13', 904, 256, 130, false);

      const ros = [
        { k: 'radhika', n: 'Radhika Bhama', r: 'VP Product · Speaker', y: 314 },
        { k: 'amit', n: 'Amit Kumar Singh', r: 'Managing Partner · Core Lead', y: 408 },
        { k: 'shreya', n: 'Shreya Agarwal', r: 'HelpHub Lead · Docs Table 2', y: 502 },
        { k: 'shammi', n: 'Shammi Proothi', r: 'AI Search Speaker', y: 596 },
      ];
      ros.forEach(r => {
        this.rect(`RR_${r.n}`, 676, r.y, 516, 80, { fill: '#F8FAFC', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
        this.avatar(assets[r.k], 694, r.y + 15, 50, f, '#FFFFFF', false);
        this.text(r.n, 760, r.y + 18, 260, { fontSize: 14.5, fontWeight: 800, fill: '#0F172A' }, f);
        this.text(r.r, 760, r.y + 42, 260, { fontSize: 12, fontWeight: 500, fill: '#64748B' }, f);
        this.rect(`RM_${r.n}`, 1076, r.y + 22, 96, 36, { fill: '#FFF7ED', stroke: '#FF4D2D', strokeWeight: 1, cornerRadius: 18 }, f);
        this.text('+ Meet', 1080, r.y + 32, 88, { fontSize: 12, fontWeight: 800, fill: '#D33417', textAlign: 'center' }, f);
      });
    }

    // W10: Explore — Attendee Chat Thread, Turn-Taking & Report Safety Modal
    {
      const f = this.webFrame('W10 · Explore: Attendee Chat, Turn-Taking & Safety', 'Desktop Split Mutual Match Conversation · 3-Msg Daily Rule · Report & Block Modal');
      this.webNavbar(f, 'https://campbuddy.club/event/wordcamp-delhi-2026/explore#chat', 'explore', true);

      // Left Thread List Sidebar
      this.rect('ThList', 56, 134, 340, 608, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 22 }, f);
      this.text('Mutual Match Chats', 82, 162, 280, { fontSize: 18, fontWeight: 800, fill: '#0F172A' }, f);
      const thr = [
        { k: 'shammi', n: 'Shammi Proothi', s: 'Your turn · Message 2 of 3', on: true, y: 212 },
        { k: 'om', n: 'Omprakash Asnani', s: 'Waiting for their reply', on: false, y: 312 },
        { k: 'radhika', n: 'Radhika Bhama', s: '3 of 3 sent today · Swap card', on: false, y: 412 },
      ];
      thr.forEach(t => {
        this.rect(`TL_${t.n}`, 74, t.y, 304, 84, { fill: t.on ? '#ECFDF5' : '#F8FAFC', stroke: t.on ? '#10B981' : '#E2E8F0', strokeWeight: t.on ? 1.5 : 1, cornerRadius: 16 }, f);
        this.avatar(assets[t.k], 90, t.y + 18, 48, f, t.on ? '#10B981' : '#CBD5E1', true);
        this.text(t.n, 152, t.y + 20, 210, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);
        this.text(t.s, 152, t.y + 44, 210, { fontSize: 11.5, fontWeight: 600, fill: t.on ? '#047857' : '#64748B' }, f);
      });

      // Right Active Chat Thread Window
      this.rect('ChatMain', 420, 134, 804, 608, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 22 }, f);
      this.avatar(assets.shammi, 452, 158, 52, f, '#10B981', true);
      this.text('Shammi Proothi', 520, 162, 260, { fontSize: 18, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Mutual match · Chat open until 7:00 PM (Day 1 of 2)', 520, 188, 420, { fontSize: 12, fontWeight: 600, fill: '#059669' }, f);
      this.rect('RepHdr', 1068, 166, 128, 36, { fill: '#FFF5F5', stroke: '#FCA5A5', strokeWeight: 1, cornerRadius: 18 }, f);
      this.text('Report & Block', 1072, 176, 120, { fontSize: 11.5, fontWeight: 700, fill: '#DC2626', textAlign: 'center' }, f);
      this.line('ChatDiv', 420, 226, 804, { stroke: '#E2E8F0', strokeWeight: 1 }, f);

      // Chat Bubbles
      this.rect('CB1', 452, 264, 430, 80, { fill: '#F8FAFC', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 18 }, f);
      this.text('Hi Sunil! Saw your talk on headless WP security. Catch up near the Espresso cart at 2 PM?', 474, 280, 386, { fontSize: 13.5, fontWeight: 500, fill: '#0F172A' }, f);
      this.text('Their message 1 of 3 · 11:20 AM', 474, 322, 260, { fontSize: 10.5, fontWeight: 600, fill: '#64748B' }, f);

      this.rect('CB2', 750, 366, 442, 80, { fill: '#FF4D2D', cornerRadius: 18 }, f);
      this.text("Sounds great! Adding 2:00 PM to my schedule now — let's swap CampCards too.", 772, 382, 398, { fontSize: 13.5, fontWeight: 500, fill: '#FFFFFF' }, f);
      this.text('Message 1 of 3 · sent ✓ · 11:24 AM', 772, 424, 398, { fontSize: 10.5, fontWeight: 600, fill: '#FFE4DE', textAlign: 'right' }, f);

      // CampCard & + Meet Bar inside Chat
      this.rect('SwapStrip', 452, 490, 740, 74, { fill: '#FFF7ED', stroke: '#FED7AA', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Take the conversation offline — share your digital pass or save a calendar reminder.', 476, 516, 420, { fontSize: 12.5, fontWeight: 600, fill: '#92400E' }, f);
      this.rect('ChMeet', 918, 507, 120, 40, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 20 }, f);
      this.text('+ Plan Meet', 922, 519, 112, { fontSize: 12, fontWeight: 700, fill: '#0F172A', textAlign: 'center' }, f);
      this.rect('ChCC', 1048, 507, 128, 40, { fill: '#0F172A', cornerRadius: 20 }, f);
      this.text('Share Card →', 1052, 519, 120, { fontSize: 12, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);

      // Composer Bar
      this.rect('WebComp', 452, 650, 740, 56, { fill: '#F8FAFC', stroke: '#CBD5E1', strokeWeight: 1.5, cornerRadius: 28 }, f);
      this.text('Write message 2 of 3 today (max 140 chars)...', 480, 669, 500, { fontSize: 13, fontWeight: 400, fill: '#64748B' }, f);
      this.rect('WebSend', 1068, 657, 114, 42, { fill: '#10B981', cornerRadius: 21 }, f);
      this.text('Send', 1072, 670, 106, { fontSize: 13, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
    }

    // W11: Explore — Verified Sponsors, Deals & Free Steals
    {
      const f = this.webFrame('W11 · Explore: Verified Sponsors, Coupons & Free Steals', 'Tiered Sponsor Logo Grid · Tear-Line Promo Coupon · Free Community Tools');
      this.webNavbar(f, 'https://campbuddy.club/event/wordcamp-delhi-2026/explore#sponsors', 'explore', true);

      const expTabs = ['People (420)', 'Sponsors (17)', 'Deals (6)', 'Free Steals (4)', 'Venue Info'];
      expTabs.forEach((et, idx) => this.pill(f, et, 56 + idx * 168, 128, 156, idx === 1, '#FF4D2D'));

      this.text('Bronze & Platform Sponsors', 56, 186, 400, { fontSize: 14, fontWeight: 800, fill: '#D33417' }, f);
      const sp1 = [
        { k: 'sponsor_elementor', x: 56 },
        { k: 'sponsor_nexgi', x: 356 },
        { k: 'sponsor_cookieadmin', x: 656 },
        { k: 'sponsor_pankaj', x: 956 },
      ];
      sp1.forEach(s => this.logoTile(assets[s.k], s.x, 216, 268, 96, f, 16, 18, '#FDFBF7'));

      this.text('Startup & Community Sponsors', 56, 336, 400, { fontSize: 14, fontWeight: 800, fill: '#D97706' }, f);
      const sp2 = [
        { k: 'sponsor_housewise', x: 56 },
        { k: 'sponsor_zuptek', x: 296 },
        { k: 'sponsor_digiliance', x: 536 },
        { k: 'sponsor_montaki', x: 776 },
        { k: 'sponsor_wpfusion', x: 1016 },
      ];
      sp2.forEach(s => this.logoTile(assets[s.k], s.x, 366, 208, 86, f, 16, 16, '#FDFBF7'));

      // Featured Tear-Line Coupon Deal Card (Left) & Free Steals (Right)
      this.rect('DealW', 56, 480, 580, 260, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 22 }, f);
      this.logoTile(assets.sponsor_wpfusion, 84, 506, 72, 52, f, 12, 8);
      this.text('WP Fusion · 30% Off Annual CRM License', 176, 514, 420, { fontSize: 17, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Sync WooCommerce & WordPress users with 50+ CRMs.', 176, 540, 420, { fontSize: 12.5, fontWeight: 500, fill: '#64748B' }, f);
      this.line('TearW', 76, 584, 540, { stroke: '#CBD5E1', strokeWeight: 1.5, dashes: [8, 6] }, f);
      this.rect('CodePill', 84, 608, 260, 46, { fill: '#FFF7ED', stroke: '#FED7AA', strokeWeight: 1, cornerRadius: 14 }, f);
      this.text('CODE: WCDELHI30', 96, 623, 236, { fontSize: 14, fontWeight: 800, fill: '#D33417', textAlign: 'center' }, f);
      this.rect('ClaimD', 364, 608, 240, 46, { fill: '#FF4D2D', cornerRadius: 23 }, f);
      this.text('Claim Deal →', 368, 622, 232, { fontSize: 13.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);

      this.rect('StealW', 664, 480, 560, 260, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 22 }, f);
      this.text('Free Community Steals', 696, 510, 360, { fontSize: 19, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('Handy tools built by WordCamp speakers — completely free.', 696, 540, 480, { fontSize: 12.5, fontWeight: 400, fill: '#CBD5E1' }, f);
      this.rect('StItem1', 696, 584, 496, 60, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 14 }, f);
      this.text('Block Theme Starter Kit · Made at WordCamp', 716, 604, 340, { fontSize: 13.5, fontWeight: 700, fill: '#FFFFFF' }, f);
      this.rect('StItem2', 696, 658, 496, 60, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 14 }, f);
      this.text('Local SEO Structured Schema Checklist PDF', 716, 678, 340, { fontSize: 13.5, fontWeight: 700, fill: '#FFFFFF' }, f);
    }

    // W12: Quests & Contributor Day Matcher
    {
      const f = this.webFrame('W12 · Quests, Badges & Contributor Day', 'Contributor Level Progress · Interactive Quests · 30-Sec Table Matcher & Leads');
      this.webNavbar(f, 'https://campbuddy.club/event/wordcamp-delhi-2026/quest', 'quest', true);

      // Left: Quests Level & Cards
      this.rect('QHero', 56, 134, 580, 140, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 22 }, f);
      this.text('LEVEL 3 CONTRIBUTOR', 88, 162, 240, { fontSize: 12, fontWeight: 800, fill: '#F59E0B' }, f);
      this.text('5 of 9 Event Quests Completed', 88, 186, 400, { fontSize: 20, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.rect('QBarBg', 88, 230, 516, 12, { fill: '#334155', cornerRadius: 6 }, f);
      this.rect('QBarFill', 88, 230, 290, 12, { fill: '#F59E0B', cornerRadius: 6 }, f);

      const qItems = [
        { t: 'First Hello — Say hi to 3 new attendees', done: true, y: 294 },
        { t: 'Sponsor Explore — Visit booths & claim a perk', done: true, y: 400 },
        { t: 'Share CampCard — Swap 1 QR digital pass', done: false, y: 506 },
        { t: 'Contribution Curious — Join a Contributor Table', done: false, y: 612 },
      ];
      qItems.forEach(q => {
        this.rect(`QI_${q.y}`, 56, q.y, 580, 90, { fill: '#FFFFFF', stroke: q.done ? '#10B981' : '#E2E8F0', strokeWeight: q.done ? 1.5 : 1, cornerRadius: 18 }, f);
        this.text(q.t, 84, q.y + 32, 390, { fontSize: 14, fontWeight: 700, fill: '#0F172A' }, f);
        this.rect(`QB_${q.y}`, 506, q.y + 24, 108, 40, { fill: q.done ? '#ECFDF5' : '#FF4D2D', cornerRadius: 20 }, f);
        this.text(q.done ? 'Done ✓' : 'Mark Done', 510, q.y + 36, 100, { fontSize: 12, fontWeight: 800, fill: q.done ? '#047857' : '#FFFFFF', textAlign: 'center' }, f);
      });

      // Right: Contributor Day Tables & Quiz
      this.rect('ConBox', 664, 134, 560, 568, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 22 }, f);
      this.text('Contributor Day Teams & Table Matcher', 696, 166, 460, { fontSize: 20, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('No coding experience required — pick what you enjoy to get matched to an on-site Hall B table.', 696, 198, 496, { fontSize: 13, fontWeight: 400, fill: '#64748B' }, f);
      this.pill(f, 'Code & Core', 696, 250, 120, true, '#0F172A');
      this.pill(f, 'Writing Docs', 826, 250, 124, true, '#0F172A');
      this.pill(f, 'Translations', 960, 250, 120, false);

      const tbls = [
        { t: 'Table 1 · Core & Gutenberg Team', s: 'Hall B · Lead: Amit Kumar Singh', y: 316 },
        { t: 'Table 2 · Documentation & HelpHub', s: 'Hall B · Lead: Shreya Agarwal', y: 422 },
        { t: 'Table 3 · Polyglots (Hindi & Regional)', s: 'Mezzanine Lounge · Beginner Friendly', y: 528 },
      ];
      tbls.forEach(tb => {
        this.rect(`TB_${tb.t}`, 696, tb.y, 496, 90, { fill: '#FFF7ED', stroke: '#FED7AA', strokeWeight: 1, cornerRadius: 16 }, f);
        this.text(tb.t, 720, tb.y + 20, 440, { fontSize: 15, fontWeight: 800, fill: '#0F172A' }, f);
        this.text(tb.s, 720, tb.y + 48, 440, { fontSize: 12.5, fontWeight: 600, fill: '#D33417' }, f);
      });
    }

    // W13: CampCard Web Studio & QR Scanner (/event/wordcamp-delhi-2026/camp-card)
    {
      const f = this.webFrame('W13 · CampCard Web Studio & 900 DPI Pass', '7 Interactive Badge Styles · Selective Field Privacy · Scannable QR Export');
      this.webNavbar(f, 'https://campbuddy.club/event/wordcamp-delhi-2026/camp-card', 'card', true);

      // Left Live Badge Card
      this.rect('CCBg', 56, 134, 440, 608, { fill: '#0F172A', stroke: '#D97706', strokeWeight: 2, cornerRadius: 26 }, f);
      this.logoTile(assets.delhiFavicon, 92, 168, 52, 52, f, 26, 5);
      this.rect('RoleTag', 348, 178, 110, 28, { fill: '#D97706', cornerRadius: 14 }, f);
      this.text('SPEAKER', 352, 185, 102, { fontSize: 11, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.avatar(assets.amit, 212, 232, 128, f, '#F59E0B', false);
      this.text('Amit Kumar Singh', 88, 376, 376, { fontSize: 22, fontWeight: 900, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.text('Managing Partner · WPoets · Pune', 88, 410, 376, { fontSize: 13, fontWeight: 500, fill: '#CBD5E1', textAlign: 'center' }, f);
      this.rect('QRBoxW', 192, 464, 168, 168, { fill: '#FFFFFF', cornerRadius: 18 }, f);
      this.image(assets.qrSample, 206, 478, 140, 140, f, 'CCQR', 'fit');
      this.text('Scan to save contact offline · profiles.wp.org', 88, 660, 376, { fontSize: 11.5, fontWeight: 700, fill: '#10B981', textAlign: 'center' }, f);

      // Right Studio Customizer
      this.rect('CCEdit', 524, 134, 700, 608, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 24 }, f);
      this.text('Customize Your CampCard Badge', 560, 166, 500, { fontSize: 22, fontWeight: 800, fill: '#0F172A' }, f);
      const styles = ['Ticket', 'Classic', 'Minimal', 'Bold', 'Split', 'Pass'];
      styles.forEach((st, idx) => this.pill(f, st, 560 + idx * 102, 214, 92, st === 'Classic', '#FF4D2D'));

      const ccInputs = [
        { l: 'Name: Amit Kumar Singh', x: 560, y: 274 },
        { l: 'Title: Managing Partner @ WPoets', x: 896, y: 274 },
        { l: 'LinkedIn: linkedin.com/in/amitsingh', x: 560, y: 340 },
        { l: 'WordPress.org: @amitwpoets', x: 896, y: 340 },
      ];
      ccInputs.forEach(ci => {
        this.rect(`CI_${ci.l}`, ci.x, ci.y, 316, 48, { fill: '#F8FAFC', stroke: '#CBD5E1', strokeWeight: 1, cornerRadius: 12 }, f);
        this.text(ci.l, ci.x + 14, ci.y + 16, 288, { fontSize: 12.5, fontWeight: 600, fill: '#0F172A' }, f);
      });

      this.rect('ClaimW', 560, 418, 632, 92, { fill: '#ECFDF5', stroke: '#86EFAC', strokeWeight: 1.5, cornerRadius: 16 }, f);
      this.text('Show my CampCard on the WordCamp Delhi Roster', 584, 440, 440, { fontSize: 14.5, fontWeight: 800, fill: '#065F46' }, f);
      this.text('Linked to Amit Kumar Singh · Tap toggle to remove anytime', 584, 466, 440, { fontSize: 12, fontWeight: 500, fill: '#047857' }, f);
      this.rect('TogOn', 1114, 448, 56, 30, { fill: '#10B981', cornerRadius: 15 }, f);
      this.ellipse('TogK', 1142, 452, 22, 22, { fill: '#FFFFFF' }, f);

      this.rect('DlPng', 560, 646, 304, 50, { fill: '#FF4D2D', cornerRadius: 25 }, f);
      this.text('Download 900 DPI PNG', 564, 662, 296, { fontSize: 13.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('ScanCam', 884, 646, 308, 50, { fill: '#0F172A', cornerRadius: 25 }, f);
      this.text('Open QR Connect Camera →', 888, 662, 300, { fontSize: 13.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
    }

    // W14: Post-Event Memories, Venue Info & 90-Day Retention
    {
      const f = this.webFrame('W14 · Post-Event Memories & Venue Info', 'Official Photo Album & WordPress.tv Talks · Venue Wi-Fi · Keep or Delete Data');
      this.webNavbar(f, 'https://campbuddy.club/event/wordcamp-delhi-2026/memories', 'home', true);

      this.rect('MemHero', 56, 134, 700, 320, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 24 }, f);
      this.text('THANK YOU FOR COMING!', 92, 166, 260, { fontSize: 11, fontWeight: 800, fill: '#F59E0B' }, f);
      this.text('WordCamp Delhi 2026 Memories', 92, 194, 500, { fontSize: 28, fontWeight: 900, fill: '#FFFFFF' }, f);
      this.text('Browse official hall photos, catch every recorded talk on WordPress.tv, and export your personal conference notes.', 92, 240, 560, { fontSize: 13.5, fontWeight: 400, fill: '#CBD5E1' }, f);
      this.rect('PhBtn', 92, 340, 260, 52, { fill: '#FF4D2D', cornerRadius: 26 }, f);
      this.text('Official Photo Album ↗', 96, 358, 252, { fontSize: 14, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('TvBtn', 372, 340, 290, 52, { fill: '#1E293B', stroke: '#475569', strokeWeight: 1.5, cornerRadius: 26 }, f);
      this.text('Watch Talks on WordPress.tv ↗', 376, 358, 282, { fontSize: 13.5, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);

      // Right: Venue & Wi-Fi Summary
      this.rect('VenInfo', 784, 134, 440, 320, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 24 }, f);
      this.text('Venue & Guest Wi-Fi Info', 816, 166, 340, { fontSize: 19, fontWeight: 800, fill: '#0F172A' }, f);
      this.rect('WifiBox', 816, 214, 376, 84, { fill: '#FFF7ED', stroke: '#FED7AA', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Network: WCDELHI-GUEST\nPasskey: CodeIsPoetry26', 838, 232, 300, { fontSize: 14, fontWeight: 800, fill: '#D33417' }, f);
      this.text('DAIC, 15 Janpath Rd · Main Lobby Registration\nSafety Contact: delhi@wordcamp.org', 816, 320, 376, { fontSize: 13, fontWeight: 500, fill: '#475569' }, f);

      // Bottom: 90-Day Data Choice Banner
      this.rect('RetW', 56, 480, 1168, 250, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 24 }, f);
      this.text('WordCamp Delhi is Over — What Should Happen to Your Data?', 92, 512, 760, { fontSize: 20, fontWeight: 800, fill: '#0F172A' }, f);
      this.rect('RetOpt1', 92, 566, 530, 130, { fill: '#ECFDF5', stroke: '#10B981', strokeWeight: 1.5, cornerRadius: 18 }, f);
      this.text('✓ Keep My Schedule & Connections', 120, 594, 440, { fontSize: 16, fontWeight: 800, fill: '#065F46' }, f);
      this.text('Keeps your saved talks and personal notes on this device until you decide to clear them.', 120, 628, 460, { fontSize: 13, fontWeight: 400, fill: '#047857' }, f);

      this.rect('RetOpt2', 654, 566, 534, 130, { fill: '#F8FAFC', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 18 }, f);
      this.text('Delete Automatically After 90 Days', 682, 594, 440, { fontSize: 16, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Clears event data automatically on Mar 13, 2027. Your CampCard remains reusable for future WordCamps.', 682, 628, 460, { fontSize: 13, fontWeight: 400, fill: '#64748B' }, f);
    }
  }
}

const builder = new WebFigCraftBuilder();
builder.buildAll14WebScreens();

const scriptPath = path.resolve(rootDir, 'scratch/figcraft_web_complete_script.json');
fs.writeFileSync(scriptPath, JSON.stringify(builder.commands, null, 2), 'utf8');
console.log(`Generated ${builder.commands.length} FigCraft commands for 14 Website Frontend screens (W01-W14).`);
console.log(`Saved script to ${scriptPath}`);
