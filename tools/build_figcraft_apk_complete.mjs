import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const rootDir = path.resolve(__dirname, '..');

const assets = {
  logo: path.resolve(rootDir, 'docs/designs/assets/delhi_logo.png'),
  favicon: path.resolve(rootDir, 'docs/designs/assets/delhi_favicon.png'),
  pankaj: path.resolve(rootDir, 'docs/designs/assets/pankaj_kumar.webp'),
  radhika: path.resolve(rootDir, 'docs/designs/assets/radhika_bhama.jpg'),
  amit: path.resolve(rootDir, 'docs/designs/assets/amit_singh.webp'),
  shammi: path.resolve(rootDir, 'docs/designs/assets/shammi_proothi.webp'),
  abhay: path.resolve(rootDir, 'docs/designs/assets/abhay_kulkarni.webp'),
  om: path.resolve(rootDir, 'docs/designs/assets/om_asnani.webp'),
  shreya: path.resolve(rootDir, 'docs/designs/assets/shreya_agarwal.webp'),
  aditya: path.resolve(rootDir, 'docs/designs/assets/aditya_singh.webp'),
  raitis: path.resolve(rootDir, 'docs/designs/assets/raitis_sevelis.webp'),
  skyline: path.resolve(rootDir, 'docs/designs/assets/delhi_skyline.webp'),
  india_gate: path.resolve(rootDir, 'docs/designs/assets/india_gate.jpg'),
  sponsor_elementor: path.resolve(rootDir, 'docs/designs/assets/sponsor_elementor.webp'),
  sponsor_wpfusion: path.resolve(rootDir, 'docs/designs/assets/sponsor_wpfusion.webp'),
  sponsor_cookieadmin: path.resolve(rootDir, 'docs/designs/assets/sponsor_cookieadmin.webp'),
  sponsor_nexgi: path.resolve(rootDir, 'docs/designs/assets/sponsor_nexgi.webp'),
  sponsor_housewise: path.resolve(rootDir, 'docs/designs/assets/sponsor_housewise.webp'),
  sponsor_zuptek: path.resolve(rootDir, 'docs/designs/assets/sponsor_zuptek.webp'),
};

class ApkFigCraftBuilder {
  constructor() {
    this.commands = [];
    this.nextId = 2;
    this.commands.push({ command: 'page.rename', params: { name: 'Mobile APK - All 24 Screens & States' } });
    this.screenIndex = 0;
  }

  nextCoords() {
    const col = this.screenIndex % 6;
    const row = Math.floor(this.screenIndex / 6);
    this.screenIndex++;
    return { x: 60 + col * 430, y: 80 + row * 850 };
  }

  frame(name, width, height, props = {}, parent = null) {
    const id = this.nextId++;
    let x = 0, y = 0;
    if (!parent) {
      const pos = this.nextCoords();
      x = pos.x;
      y = pos.y;
    }
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

  vector(svgPath, x, y, width, height, props = {}, parent = null, name = 'Icon') {
    const id = this.nextId++;
    const params = { path: svgPath, x, y, width, height, props, name };
    if (parent) params.parent = parent;
    this.commands.push({ command: 'vector.create', params });
    return id;
  }

  image(filePath, x, y, width, height, parent = null, name = 'Image') {
    if (!filePath || !fs.existsSync(filePath)) {
      return this.rect(name, x, y, width, height, { fill: '#334155', cornerRadius: 8 }, parent);
    }
    const id = this.nextId++;
    const params = { path: filePath, x, y, width, height, parent, name, mode: 'fit' };
    this.commands.push({ command: 'image.place', params });
    return id;
  }

  statusBar(parent, dark = false, statusText = 'Offline Ready') {
    const color = dark ? '#FFFFFF' : '#0F172A';
    this.text('10:30 AM', 22, 14, 70, { fontSize: 11, fontWeight: 700, fill: color }, parent, 'Time');
    this.ellipse('Pulse Dot', 210, 18, 6, 6, { fill: '#10B981' }, parent);
    this.text(statusText, 220, 14, 100, { fontSize: 10, fontWeight: 700, fill: '#10B981' }, parent, 'Status');
    this.rect('Battery', 326, 15, 18, 9, { fill: color, cornerRadius: 2 }, parent);
  }

  headerBar(parent, title, back = false, badgeText = null) {
    if (back) {
      this.ellipse('Back Circle', 16, 42, 30, 30, { fill: '#FFEDD5' }, parent);
      this.text('<', 25, 48, 14, { fontSize: 14, fontWeight: 800, fill: '#EA580C' }, parent);
      this.text(title, 54, 47, 210, { fontSize: 16, fontWeight: 800, fill: '#0F172A' }, parent);
    } else {
      this.text(title, 18, 46, 230, { fontSize: 18, fontWeight: 800, fill: '#0F172A' }, parent);
    }
    if (badgeText) {
      this.rect('Header Badge', 262, 46, 82, 24, { fill: '#FFEDD5', stroke: '#FF4D2D', strokeWeight: 1, cornerRadius: 12 }, parent);
      this.text(badgeText, 264, 52, 78, { fontSize: 9, fontWeight: 800, fill: '#FF4D2D', textAlign: 'center' }, parent);
    }
  }

  bottomNav(parent, activeTab = 'home') {
    const y = 700;
    this.rect('Bottom Nav BG', 0, y, 360, 60, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 }, parent);
    const tabs = [
      { key: 'home', label: 'Home', x: 12 },
      { key: 'schedule', label: 'Schedule', x: 82 },
      { key: 'my-day', label: 'My Day', x: 152 },
      { key: 'camp-card', label: 'CampCard', x: 222 },
      { key: 'explore', label: 'Explore', x: 292 },
    ];
    for (const t of tabs) {
      const active = t.key === activeTab;
      if (active) {
        this.rect('Active Pill', t.x + 4, y + 8, 48, 22, { fill: '#FFEDD5', cornerRadius: 11 }, parent);
        this.ellipse('Tab Dot', t.x + 25, y + 16, 6, 6, { fill: '#FF4D2D' }, parent);
      } else {
        this.ellipse('Tab Dot', t.x + 25, y + 16, 6, 6, { fill: '#CBD5E1' }, parent);
      }
      this.text(t.label, t.x, y + 36, 56, { fontSize: 9.5, fontWeight: active ? 700 : 500, fill: active ? '#EA580C' : '#64748B', textAlign: 'center' }, parent);
    }
  }

  pill(parent, label, x, y, w, active = false, color = '#0F172A') {
    this.rect(`Pill ${label}`, x, y, w, 26, {
      fill: active ? color : '#FFFFFF',
      stroke: active ? color : '#E2E8F0',
      strokeWeight: 1,
      cornerRadius: 13
    }, parent);
    this.text(label, x + 4, y + 7, w - 8, {
      fontSize: 9.5,
      fontWeight: active ? 700 : 600,
      fill: active ? '#FFFFFF' : '#475569',
      textAlign: 'center'
    }, parent);
  }

  stateLegend(parent, text, y = 668) {
    this.rect('State Legend BG', 16, y, 328, 24, { fill: '#EEF2FF', stroke: '#C7D2FE', strokeWeight: 1, cornerRadius: 6 }, parent);
    this.text(`STATES: ${text}`, 22, y + 6, 316, { fontSize: 8.5, fontWeight: 700, fill: '#3730A3' }, parent);
  }

  buildAll24Screens() {
    // =========================================================================
    // GROUP 1: LAUNCH, ONBOARDING & EVENT SELECTION (M01 - M03)
    // =========================================================================

    // M01: Splash & Offline Cache Init
    {
      const f = this.frame('M01 - Splash & Offline Cache Init', 360, 760, { fill: '#0F172A', cornerRadius: 36 });
      this.statusBar(f, true, 'Booting');
      this.image(assets.logo, 136, 140, 88, 88, f, 'Delhi Logo');
      this.text('WordCamp Delhi 2026', 30, 244, 300, { fontSize: 22, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.text('"Dil se Dilli, code se community"', 30, 276, 300, { fontSize: 12, fontWeight: 500, fill: '#F59E0B', textAlign: 'center' }, f);
      this.text('Dec 12-13, 2026 · DAIC New Delhi', 30, 298, 300, { fontSize: 11, fontWeight: 500, fill: '#94A3B8', textAlign: 'center' }, f);

      // Offline Sync Progress
      this.rect('Sync Card', 24, 370, 312, 110, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('KeyValueStore Offline Pre-Cache', 40, 386, 280, { fontSize: 11, fontWeight: 700, fill: '#FFFFFF' }, f);
      this.rect('Prog BG', 40, 412, 280, 8, { fill: '#0F172A', cornerRadius: 4 }, f);
      this.rect('Prog Fill', 40, 412, 280, 8, { fill: '#10B981', cornerRadius: 4 }, f);
      this.text('22 Sessions · 11 Speakers · 17 Sponsors Cached', 40, 430, 280, { fontSize: 10, fontWeight: 500, fill: '#10B981' }, f);
      this.text('State: 100% Ready (0ms Latency)', 40, 450, 280, { fontSize: 9.5, fontWeight: 600, fill: '#CBD5E1' }, f);

      // Update Sheet State Preview
      this.rect('Update Banner', 24, 500, 312, 74, { fill: '#FEF3C7', cornerRadius: 14 }, f);
      this.text('Optional Update: v2.4.0 Ready', 38, 512, 200, { fontSize: 11, fontWeight: 800, fill: '#B45309' }, f);
      this.text('Offline APK installer verified.', 38, 530, 180, { fontSize: 9.5, fontWeight: 500, fill: '#78350F' }, f);
      this.rect('Upd Btn', 244, 516, 78, 28, { fill: '#D97706', cornerRadius: 14 }, f);
      this.text('INSTALL', 246, 523, 74, { fontSize: 9.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);

      this.rect('Enter Btn', 24, 608, 312, 48, { fill: '#FF4D2D', cornerRadius: 24 }, f);
      this.text('GET STARTED', 24, 624, 312, { fontSize: 12, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.stateLegend(f, 'Loading | Offline Ready | Update Sheet', 680);
    }

    // M02: Event Picker & Desktop Sync PIN
    {
      const f = this.frame('M02 - Event Picker & Sync PIN', 360, 760, { fill: '#FFF7ED', cornerRadius: 36 });
      this.statusBar(f, false, '4 Events Cached');
      this.headerBar(f, 'Choose WordCamp', false, 'Staff Login');

      // Country Filter Chips (Active vs Default)
      this.pill(f, 'All (26)', 16, 86, 66, true, '#0F172A');
      this.pill(f, 'IN (4)', 88, 86, 56, false);
      this.pill(f, 'GR (1)', 150, 86, 56, false);
      this.pill(f, 'ID (1)', 212, 86, 56, false);
      this.pill(f, 'KE (1)', 274, 86, 56, false);

      // Event 1: Delhi (Selected / Active)
      this.rect('Evt Delhi', 16, 124, 328, 108, { fill: '#FFFFFF', stroke: '#FF4D2D', strokeWeight: 2, cornerRadius: 16 }, f);
      this.image(assets.logo, 28, 138, 48, 48, f, 'Delhi Logo');
      this.text('WordCamp Delhi 2026', 88, 136, 180, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Dec 12-13, 2026 · New Delhi', 88, 156, 180, { fontSize: 10, fontWeight: 600, fill: '#EA580C' }, f);
      this.text('22 Talks · 17 Sponsors · Cached', 88, 174, 180, { fontSize: 9.5, fontWeight: 500, fill: '#10B981' }, f);
      this.rect('Open Btn', 250, 192, 82, 26, { fill: '#FF4D2D', cornerRadius: 13 }, f);
      this.text('OPEN EVENT', 252, 198, 78, { fontSize: 9, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('FirstTimer Btn', 28, 194, 120, 24, { fill: '#FFEDD5', cornerRadius: 12 }, f);
      this.text('First-Timer Guide', 30, 199, 116, { fontSize: 9, fontWeight: 700, fill: '#EA580C', textAlign: 'center' }, f);

      // Event 2: Athens (Default State)
      this.rect('Evt Athens', 16, 244, 328, 82, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('WordCamp Athens 2026', 28, 256, 220, { fontSize: 13, fontWeight: 700, fill: '#0F172A' }, f);
      this.text('Dec 11-12, 2026 · Athens, Greece', 28, 276, 220, { fontSize: 10, fontWeight: 500, fill: '#64748B' }, f);
      this.rect('Load Btn', 260, 266, 72, 28, { fill: '#F1F5F9', cornerRadius: 14 }, f);
      this.text('OPEN', 262, 273, 68, { fontSize: 9.5, fontWeight: 700, fill: '#0F172A', textAlign: 'center' }, f);

      // Desktop 6-Digit PIN Sync Card (Active Input State)
      this.rect('PIN Card', 16, 340, 328, 148, { fill: '#0F172A', cornerRadius: 18 }, f);
      this.text('Import Bookmarks from Desktop', 30, 356, 280, { fontSize: 13, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('Enter the 6-digit PIN shown on CampBuddy Web:', 30, 376, 290, { fontSize: 10, fontWeight: 400, fill: '#94A3B8' }, f);
      for (let i = 0; i < 6; i++) {
        const dx = 30 + i * 48;
        this.rect(`PinBox ${i}`, dx, 400, 40, 42, { fill: '#1E293B', stroke: i < 4 ? '#F59E0B' : '#334155', strokeWeight: 1.5, cornerRadius: 8 }, f);
        const digit = i < 4 ? ['4', '8', '2', '9'][i] : '_';
        this.text(digit, dx, 412, 40, { fontSize: 16, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      }
      this.text('State: Focused PIN input (Error state shows red border)', 30, 456, 290, { fontSize: 9, fontWeight: 500, fill: '#F59E0B' }, f);

      // Offline Empty / Error State Preview
      this.rect('Offline Warn', 16, 502, 328, 52, { fill: '#FFF5F5', stroke: '#FCA5A5', strokeWeight: 1, cornerRadius: 12 }, f);
      this.text('Network Offline: Showing 4 cached conferences only.', 28, 518, 300, { fontSize: 10, fontWeight: 600, fill: '#DC2626' }, f);

      this.stateLegend(f, 'Selected | Country Filter | PIN Focus | Offline', 710);
    }

    // M03: First-Timer Onboarding Wizard
    {
      const f = this.frame('M03 - Onboarding & Notifications', 360, 760, { fill: '#FFF7ED', cornerRadius: 36 });
      this.statusBar(f, false, 'Step 2 of 5');
      this.headerBar(f, 'Personalize Camp', true, 'Skip');

      // Step Progress Dots
      for (let i = 0; i < 5; i++) {
        this.rect(`Step ${i}`, 20 + i * 66, 86, 58, 5, { fill: i <= 1 ? '#FF4D2D' : '#E2E8F0', cornerRadius: 2.5 }, f);
      }

      this.text('What brings you to WordCamp Delhi?', 20, 108, 320, { fontSize: 18, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('We use this locally to highlight matching talks & people. Stored only on your phone.', 20, 138, 320, { fontSize: 10.5, fontWeight: 400, fill: '#64748B' }, f);

      // Selectable Choice Cards (Selected vs Default)
      const choices = [
        { title: 'Building WordPress Products & AI', sub: 'Plugin/Theme devs, agency founders', active: true, y: 182 },
        { title: 'SEO, Content & Growth Marketing', sub: 'GEO, Local SEO, editorial workflows', active: true, y: 256 },
        { title: 'First-Time Contributor & Open Source', sub: 'Core, Docs, Polyglots tables', active: false, y: 330 },
        { title: 'Networking & Hiring Talent', sub: 'Meet speakers, sponsors & peers', active: false, y: 404 },
      ];
      for (const c of choices) {
        this.rect(`Choice ${c.title}`, 16, c.y, 328, 62, {
          fill: c.active ? '#FFEDD5' : '#FFFFFF',
          stroke: c.active ? '#FF4D2D' : '#E2E8F0',
          strokeWeight: c.active ? 2 : 1,
          cornerRadius: 14
        }, f);
        this.text(c.title, 30, c.y + 12, 260, { fontSize: 12, fontWeight: 700, fill: '#0F172A' }, f);
        this.text(c.sub, 30, c.y + 32, 260, { fontSize: 10, fontWeight: 500, fill: '#64748B' }, f);
        this.ellipse('Check', 306, c.y + 20, 22, 22, { fill: c.active ? '#FF4D2D' : '#F1F5F9' }, f);
        if (c.active) this.text('✓', 312, c.y + 24, 12, { fontSize: 11, fontWeight: 800, fill: '#FFFFFF' }, f);
      }

      // Notification Permission Card
      this.rect('Notif Card', 16, 482, 328, 96, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Smart Talk & Delay Alerts', 30, 494, 240, { fontSize: 12, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('10-min reminders before starred talks & live hall delays.', 30, 514, 230, { fontSize: 10, fontWeight: 400, fill: '#64748B' }, f);
      this.rect('Toggle ON', 284, 504, 44, 24, { fill: '#10B981', cornerRadius: 12 }, f);
      this.ellipse('Knob', 306, 507, 18, 18, { fill: '#FFFFFF' }, f);
      this.text('Privacy Consent: On-device only (DPDP compliant)', 30, 550, 290, { fontSize: 9.5, fontWeight: 600, fill: '#10B981' }, f);

      this.rect('Next Btn', 16, 600, 328, 46, { fill: '#0F172A', cornerRadius: 23 }, f);
      this.text('CONTINUE (2 SELECTED)', 16, 615, 328, { fontSize: 11, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.stateLegend(f, 'Multi-Select Active | Toggle ON | Skip Option', 710);
    }

    // =========================================================================
    // GROUP 2: CORE ATTENDEE EXPERIENCE (M04 - M10)
    // =========================================================================

    // M04: Live Event Home Dashboard (Beloved Wireframe DNA + Delay Banner State)
    {
      const f = this.frame('M04 - Tactile Live Home Dashboard', 360, 760, { fill: '#FFF7ED', cornerRadius: 36 });
      this.statusBar(f, false, 'Offline Ready');

      // TopBar
      this.image(assets.logo, 18, 42, 28, 28, f, 'Delhi Logo');
      this.text('CampBuddy', 52, 46, 110, { fontSize: 17, fontWeight: 800, fill: '#FF4D2D' }, f);
      this.rect('Staff Pill', 206, 44, 68, 24, { fill: '#FFEDD5', stroke: '#FF4D2D', strokeWeight: 1, cornerRadius: 12 }, f);
      this.text('👑 Staff', 208, 49, 64, { fontSize: 10, fontWeight: 700, fill: '#FF4D2D', textAlign: 'center' }, f);
      this.ellipse('Guide Btn', 284, 42, 28, 28, { fill: '#FFEDD5' }, f);
      this.text('?', 294, 47, 12, { fontSize: 13, fontWeight: 800, fill: '#EA580C' }, f);
      this.ellipse('Switch Btn', 318, 42, 28, 28, { fill: '#FFEDD5' }, f);
      this.text('⇄', 325, 47, 14, { fontSize: 13, fontWeight: 800, fill: '#EA580C' }, f);

      // Active Delay Banner (Live Broadcast State)
      this.rect('Delay Alert', 16, 78, 328, 28, { fill: '#FEF3C7', stroke: '#F59E0B', strokeWeight: 1, cornerRadius: 10 }, f);
      this.text('⏱️ BHIM Auditorium running +15m late (Updated 1m ago)', 24, 85, 312, { fontSize: 9.5, fontWeight: 700, fill: '#B45309' }, f);

      // Dark Hero Card
      this.rect('Hero BG', 16, 114, 328, 126, { fill: '#0F172A', cornerRadius: 20 }, f);
      this.text('Good morning, Sunil', 30, 126, 200, { fontSize: 11, fontWeight: 600, fill: '#94A3B8' }, f);
      this.text('10:30 AM', 30, 142, 180, { fontSize: 30, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('Day 1 · Dec 12 · DAIC New Delhi', 30, 178, 240, { fontSize: 10.5, fontWeight: 500, fill: '#CBD5E1' }, f);
      this.rect('Next Pill', 28, 198, 304, 34, { fill: '#1E293B', cornerRadius: 12 }, f);
      this.text('Next: Local SEO in AI Era (Pankaj)', 38, 208, 210, { fontSize: 10, fontWeight: 700, fill: '#FFFFFF' }, f);
      this.rect('View Btn', 266, 203, 58, 24, { fill: '#D97706', cornerRadius: 12 }, f);
      this.text('VIEW', 268, 208, 54, { fontSize: 9, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);

      // My People Strip
      this.text('MY PEOPLE & MATCHES', 18, 252, 180, { fontSize: 11, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('5 Pinned · 11 Matches', 210, 252, 130, { fontSize: 10, fontWeight: 500, fill: '#64748B', textAlign: 'right' }, f);
      const ppl = [
        { img: assets.pankaj, name: 'Pankaj' },
        { img: assets.radhika, name: 'Radhika' },
        { img: assets.amit, name: 'Amit' },
        { img: assets.shammi, name: 'Shammi' },
        { img: assets.abhay, name: 'Abhay' },
      ];
      for (let i = 0; i < ppl.length; i++) {
        const px = 18 + i * 66;
        this.ellipse(`Av ${i}`, px, 270, 46, 46, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 2 }, f);
        this.image(ppl[i].img, px + 2, 272, 42, 42, f, ppl[i].name);
        this.ellipse(`On ${i}`, px + 34, 304, 10, 10, { fill: '#10B981', stroke: '#FFFFFF', strokeWeight: 2 }, f);
        this.text(ppl[i].name, px - 2, 320, 50, { fontSize: 9.5, fontWeight: 600, fill: '#0F172A', textAlign: 'center' }, f);
      }

      // 2x3 Tactile Grid
      const grid = [
        { t: 'Schedule', s: '22 talks · 3 halls', x: 16, y: 344, icon: 'M19 4h-1V2h-2v2H8V2H6v2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V9h14v11z' },
        { t: 'My Day', s: '4 saved · 1 clash', x: 184, y: 344, icon: 'M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10 10-4.5 10-10S17.5 2 12 2zm4.2 14.2L11 13V7h1.5v5.2l4.5 2.7-.8 1.3z' },
        { t: 'Sponsors & Steals', s: '17 brands · Deals', x: 16, y: 416, icon: 'M20 6h-2.18c.11-.31.18-.65.18-1a2.996 2.996 0 0 0-5.5-1.65l-.5.67-.5-.68C10.96 2.54 10.05 2 9 2 7.34 2 6 3.34 6 5c0 .35.07.69.18 1H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2z' },
        { t: 'Contributor XP', s: '6 tables · 450 XP', x: 184, y: 416, icon: 'M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z' },
        { t: 'Venue Map', s: 'DAIC Janpath Rd', x: 16, y: 488, icon: 'M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 0 1 0-5 2.5 2.5 0 0 1 0 5z' },
        { t: 'CampCard QR', s: 'NFC digital pass', x: 184, y: 488, icon: 'M3 3h8v8H3V3zm2 2v4h4V5H5zm8-2h8v8h-8V3zm2 2v4h4V5h-4zM3 13h8v8H3v-8zm2 2v4h4v-4H5zm10 0h2v2h-2v-2zm4 0h2v2h-2v-2zm-4 4h2v2h-2v-2zm4 0h2v2h-2v-2z' },
      ];
      for (const g of grid) {
        this.rect(`Tile ${g.t}`, g.x, g.y, 160, 62, { fill: '#FAFAFD', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 14 }, f);
        this.rect(`Icon ${g.t}`, g.x + 10, g.y + 10, 24, 24, { fill: '#FFEDD5', cornerRadius: 6 }, f);
        this.vector(g.icon, g.x + 15, g.y + 15, 14, 14, { fill: '#EA580C' }, f, `Vec ${g.t}`);
        this.text(g.t, g.x + 40, g.y + 12, 112, { fontSize: 11.5, fontWeight: 700, fill: '#0F172A' }, f);
        this.text(g.s, g.x + 10, g.y + 40, 140, { fontSize: 9.5, fontWeight: 500, fill: '#64748B' }, f);
      }

      // Sticky SOS Safety Bar
      this.rect('SOS Bar', 16, 566, 328, 46, { fill: '#FFF5F5', stroke: '#FCA5A5', strokeWeight: 1, cornerRadius: 14 }, f);
      this.text('Emergency & Code of Conduct', 26, 574, 200, { fontSize: 10.5, fontWeight: 700, fill: '#DC2626' }, f);
      this.text('DAIC medical & organizer desk', 26, 590, 200, { fontSize: 9, fontWeight: 500, fill: '#64748B' }, f);
      this.rect('SOS Btn', 250, 574, 84, 28, { fill: '#FF4D2D', cornerRadius: 14 }, f);
      this.text('SOS HELP', 252, 581, 80, { fontSize: 9.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);

      // Legal Footer Links
      this.text('Privacy Policy · Terms · My Data Controls', 16, 624, 328, { fontSize: 9.5, fontWeight: 600, fill: '#64748B', textAlign: 'center' }, f);

      this.stateLegend(f, 'Live Delay | Online Dots | Hover Tile | SOS', 654);
      this.bottomNav(f, 'home');
    }

    // M05: First-Timer Guide & Practical Info
    {
      const f = this.frame('M05 - First-Timer Guide & Venue', 360, 760, { fill: '#FFF7ED', cornerRadius: 36 });
      this.statusBar(f, false, 'Offline Guide');
      this.headerBar(f, 'First-Timer Guide', true, 'Delhi 2026');

      // Hero Banner
      this.image(assets.india_gate, 16, 84, 328, 100, f, 'India Gate Delhi');
      this.rect('Hero Overlay', 16, 136, 328, 48, { fill: '#0F172A', cornerRadius: 10 }, f);
      this.text('Welcome to WordCamp Delhi! Here is everything you need.', 26, 148, 308, { fontSize: 11, fontWeight: 700, fill: '#FFFFFF' }, f);

      // Day Stepper
      this.rect('Step Card', 16, 198, 328, 104, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('2-Day Conference Flow', 28, 210, 240, { fontSize: 12, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('1. Dec 12 · Contributor Day (08:00 AM Check-in)', 28, 232, 300, { fontSize: 10.5, fontWeight: 700, fill: '#EA580C' }, f);
      this.text('2. Dec 13 · Main Conference (BHIM Auditorium)', 28, 254, 300, { fontSize: 10.5, fontWeight: 600, fill: '#0F172A' }, f);
      this.text('3. Dec 13 · After Party (Sponsored by WP Fusion)', 28, 276, 300, { fontSize: 10, fontWeight: 500, fill: '#64748B' }, f);

      // Practical Venue Info
      this.rect('Venue Info', 16, 314, 328, 118, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Venue & Transit (DAIC)', 28, 326, 240, { fontSize: 12, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('📍 15, Janpath Rd, Windsor Place, New Delhi 110001', 28, 348, 300, { fontSize: 10, fontWeight: 500, fill: '#475569' }, f);
      this.text('🚇 Nearest Metro: Janpath (Violet) / Patel Chowk (Yellow)', 28, 370, 300, { fontSize: 10, fontWeight: 500, fill: '#475569' }, f);
      this.text('✉️ Emergency Desk: delhi@wordcamp.org', 28, 392, 300, { fontSize: 10, fontWeight: 600, fill: '#FF4D2D' }, f);

      // Expandable FAQ Accordion (Expanded + Collapsed states)
      this.rect('FAQ Open', 16, 444, 328, 86, { fill: '#FAFAFD', stroke: '#FF4D2D', strokeWeight: 1, cornerRadius: 14 }, f);
      this.text('▾ What should I bring to Contributor Day?', 28, 456, 290, { fontSize: 11, fontWeight: 700, fill: '#0F172A' }, f);
      this.text('Bring your laptop, charger, and a WordPress.org account. Wi-Fi & lunch are provided on-site.', 28, 478, 300, { fontSize: 10, fontWeight: 400, fill: '#475569' }, f);

      this.rect('FAQ Closed', 16, 540, 328, 40, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 12 }, f);
      this.text('▸ Is the venue wheelchair accessible?', 28, 552, 290, { fontSize: 11, fontWeight: 600, fill: '#0F172A' }, f);

      this.rect('FAQ Closed 2', 16, 588, 328, 40, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 12 }, f);
      this.text('▸ Where are quiet rooms & nursing rooms?', 28, 600, 290, { fontSize: 11, fontWeight: 600, fill: '#0F172A' }, f);

      this.stateLegend(f, 'Accordion Expanded/Collapsed | External Links', 654);
      this.bottomNav(f, 'home');
    }

    // M06: Multi-Track Schedule & Session List
    {
      const f = this.frame('M06 - Multi-Track Schedule', 360, 760, { fill: '#FFF7ED', cornerRadius: 36 });
      this.statusBar(f, false, '22 Sessions');
      this.headerBar(f, 'Schedule', false, 'Filter');

      // Search Input (Active Focus State)
      this.rect('Search Input', 16, 82, 328, 36, { fill: '#FFFFFF', stroke: '#0F172A', strokeWeight: 1.5, cornerRadius: 18 }, f);
      this.text('🔍 Search SEO, AI, Radhika...', 30, 93, 280, { fontSize: 11, fontWeight: 500, fill: '#64748B' }, f);

      // Day Toggle
      this.rect('Day Seg BG', 16, 128, 328, 34, { fill: '#E2E8F0', cornerRadius: 17 }, f);
      this.text('Day 1 (Dec 12)', 22, 138, 150, { fontSize: 10.5, fontWeight: 600, fill: '#475569', textAlign: 'center' }, f);
      this.rect('Day 2 Active', 180, 131, 160, 28, { fill: '#FFFFFF', cornerRadius: 14 }, f);
      this.text('Day 2 (Dec 13)', 182, 138, 156, { fontSize: 10.5, fontWeight: 800, fill: '#0F172A', textAlign: 'center' }, f);

      // Room Filter Chips
      this.pill(f, 'All Halls', 16, 172, 74, true, '#0F172A');
      this.pill(f, 'BHIM Hall', 96, 172, 82, false);
      this.pill(f, 'Contrib Lounge', 184, 172, 98, false);

      // Session Cards (Live Now + Upcoming + Starred/Unstarred)
      const list = [
        { time: 'LIVE NOW · 11:00 AM', hall: 'BHIM Hall', title: 'Generative Engine Optimization (GEO) for WordPress', spk: 'Mayank Kumar', img: assets.pankaj, starred: true, live: true, y: 210 },
        { time: '11:20 AM · 20m', hall: 'BHIM Hall', title: '10 Things to Do to Boost Local SEO in the AI Era', spk: 'Pankaj Kumar', img: assets.pankaj, starred: true, live: false, y: 320 },
        { time: '02:00 PM · 30m', hall: 'BHIM Hall', title: 'Cited but Not Recommended: Winning in AI Search', spk: 'Shammi Proothi', img: assets.shammi, starred: false, live: false, y: 430 },
        { time: '04:00 PM · 60m', hall: 'Plenary', title: 'PANEL: The Value Shift in the AI Era', spk: 'Radhika, Amit, Om', img: assets.radhika, starred: true, live: false, y: 540 },
      ];
      for (const s of list) {
        this.rect(`Card ${s.time}`, 16, s.y, 328, 98, {
          fill: '#FFFFFF',
          stroke: s.live ? '#FF4D2D' : '#E2E8F0',
          strokeWeight: s.live ? 2 : 1,
          cornerRadius: 16
        }, f);
        this.text(s.time, 28, s.y + 10, 160, { fontSize: 9.5, fontWeight: 800, fill: s.live ? '#FF4D2D' : '#EA580C' }, f);
        this.text(s.hall, 230, s.y + 10, 100, { fontSize: 9.5, fontWeight: 600, fill: '#64748B', textAlign: 'right' }, f);
        this.text(s.title, 28, s.y + 28, 290, { fontSize: 12, fontWeight: 700, fill: '#0F172A' }, f);
        this.image(s.img, 28, s.y + 66, 22, 22, f, s.spk);
        this.text(s.spk, 58, s.y + 70, 190, { fontSize: 10, fontWeight: 600, fill: '#475569' }, f);
        this.rect(`Star ${s.y}`, 296, s.y + 64, 34, 22, { fill: s.starred ? '#FEF3C7' : '#F1F5F9', cornerRadius: 11 }, f);
        this.text(s.starred ? '★' : '☆', 307, s.y + 67, 14, { fontSize: 11, fontWeight: 800, fill: s.starred ? '#D97706' : '#64748B' }, f);
      }

      this.stateLegend(f, 'Live Border | Starred vs Unstarred | Search Focus', 654);
      this.bottomNav(f, 'schedule');
    }

    // M07: Session Detail & Speaker Bio
    {
      const f = this.frame('M07 - Session Detail & Reminder', 360, 760, { fill: '#FFF7ED', cornerRadius: 36 });
      this.statusBar(f, false, 'Offline Ready');
      this.headerBar(f, 'Session Profile', true, 'Saved ✓');

      // Speaker Card
      this.rect('Spk Card', 16, 84, 328, 88, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.image(assets.pankaj, 28, 96, 64, 64, f, 'Pankaj');
      this.text('Pankaj Kumar', 104, 100, 220, { fontSize: 15, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Founder @ Pankaj Kumar SEO', 104, 120, 220, { fontSize: 11, fontWeight: 600, fill: '#FF4D2D' }, f);
      this.text('10+ Years · Local SEO & AI Visibility', 104, 138, 220, { fontSize: 10, fontWeight: 500, fill: '#64748B' }, f);

      // Talk Abstract
      this.rect('Talk Box', 16, 184, 328, 250, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Day 2 · 11:20 AM - 11:40 AM', 28, 198, 180, { fontSize: 10, fontWeight: 700, fill: '#EA580C' }, f);
      this.text('BHIM Auditorium', 220, 198, 110, { fontSize: 10, fontWeight: 700, fill: '#10B981', textAlign: 'right' }, f);
      this.text('10 Things to Do to Boost Local SEO in the AI Era', 28, 220, 300, { fontSize: 15, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('AI search platforms like ChatGPT Search, Perplexity, and Google AI Overviews are reshaping local discovery. Learn practical entity optimization, structured data, and citation tactics for WordPress.', 28, 272, 300, { fontSize: 11, fontWeight: 400, fill: '#475569' }, f);

      // Action Buttons (Active Bookmarked + Slides)
      this.rect('Bookmarked Btn', 28, 376, 144, 40, { fill: '#D97706', cornerRadius: 20 }, f);
      this.text('★ IN MY DAY', 30, 389, 140, { fontSize: 10, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('Slides Btn', 184, 376, 144, 40, { fill: '#F1F5F9', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 20 }, f);
      this.text('SLIDES (.PDF)', 186, 389, 140, { fontSize: 10, fontWeight: 700, fill: '#0F172A', textAlign: 'center' }, f);

      // 10-Min Reminder Toggle
      this.rect('Rem Card', 16, 446, 328, 62, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 14 }, f);
      this.text('Remind me 10 mins before', 28, 458, 220, { fontSize: 12, fontWeight: 700, fill: '#0F172A' }, f);
      this.text('Local push alert (works offline)', 28, 478, 220, { fontSize: 10, fontWeight: 500, fill: '#10B981' }, f);
      this.rect('Switch ON', 284, 464, 44, 24, { fill: '#10B981', cornerRadius: 12 }, f);
      this.ellipse('Knob', 306, 467, 18, 18, { fill: '#FFFFFF' }, f);

      // Room Pin
      this.rect('Room Pin', 16, 520, 328, 64, { fill: '#FAFAFD', stroke: '#CBD5E1', strokeWeight: 1, cornerRadius: 14 }, f);
      this.text('📍 Ground Floor · Main Stage (BHIM)', 28, 534, 280, { fontSize: 11, fontWeight: 700, fill: '#0F172A' }, f);
      this.text('2 min walk from Registration Desk', 28, 554, 280, { fontSize: 10, fontWeight: 500, fill: '#64748B' }, f);

      this.stateLegend(f, 'Bookmarked State | Reminder ON | Slides Link', 654);
      this.bottomNav(f, 'schedule');
    }

    // M08: My Day Itinerary & Conflict Hub
    {
      const f = this.frame('M08 - My Day & Conflict Hub', 360, 760, { fill: '#FFF7ED', cornerRadius: 36 });
      this.statusBar(f, false, '4 Saved Talks');
      this.headerBar(f, 'My Day Itinerary', false, 'Export .ics');

      // Conflict Warning Card
      this.rect('Clash Card', 16, 84, 328, 102, { fill: '#FEF3C7', stroke: '#F59E0B', strokeWeight: 1.5, cornerRadius: 16 }, f);
      this.text('⚠️ Schedule Conflict Detected (11:00 AM)', 28, 96, 300, { fontSize: 11, fontWeight: 800, fill: '#B45309' }, f);
      this.text('GEO Talk (BHIM) overlaps with SEO Workshop (Lounge).', 28, 116, 300, { fontSize: 10, fontWeight: 500, fill: '#78350F' }, f);
      this.rect('Keep A', 28, 146, 144, 28, { fill: '#D97706', cornerRadius: 14 }, f);
      this.text('KEEP GEO TALK', 30, 153, 140, { fontSize: 9.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('Keep B', 184, 146, 144, 28, { fill: '#FFFFFF', stroke: '#D97706', strokeWeight: 1, cornerRadius: 14 }, f);
      this.text('KEEP WORKSHOP', 186, 153, 140, { fontSize: 9.5, fontWeight: 800, fill: '#B45309', textAlign: 'center' }, f);

      // Saved Timeline + Transit Chips
      const timeline = [
        { time: '10:00 AM', title: 'From Feature to Ecosystem', spk: 'Radhika Bhama · BHIM Hall', transit: '✓ Checked In (+50 XP)', y: 202 },
        { time: '01:00 PM', title: 'Networking Lunch & Sponsor Expo', spk: 'Dining Courtyard · 60m', transit: '🚶 3 min walk from BHIM', y: 298 },
        { time: '04:00 PM', title: 'PANEL: The Value Shift in the AI Era', spk: 'Amit, Om, Abhay · BHIM Hall', transit: '🔔 10m alert armed', y: 394 },
      ];
      for (const t of timeline) {
        this.rect(`TL ${t.time}`, 16, t.y, 328, 84, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
        this.text(t.time, 28, t.y + 10, 100, { fontSize: 10, fontWeight: 800, fill: '#EA580C' }, f);
        this.text(t.transit, 160, t.y + 10, 170, { fontSize: 9.5, fontWeight: 700, fill: '#10B981', textAlign: 'right' }, f);
        this.text(t.title, 28, t.y + 30, 300, { fontSize: 12, fontWeight: 700, fill: '#0F172A' }, f);
        this.text(t.spk, 28, t.y + 52, 300, { fontSize: 10, fontWeight: 500, fill: '#64748B' }, f);
      }

      // Empty State Callout Box
      this.rect('Empty Preview', 16, 494, 328, 72, { fill: '#FAFAFD', stroke: '#CBD5E1', strokeWeight: 1, cornerRadius: 14 }, f);
      this.text('Empty State (When 0 saved):', 28, 504, 240, { fontSize: 10, fontWeight: 700, fill: '#64748B' }, f);
      this.text('"Your day is open — browse the Schedule to star talks."', 28, 522, 300, { fontSize: 10, fontWeight: 500, fill: '#475569' }, f);
      this.text('Export PDF / .ics available above', 28, 542, 240, { fontSize: 9.5, fontWeight: 600, fill: '#FF4D2D' }, f);

      this.stateLegend(f, 'Clash Resolution | Transit Chips | Empty State', 654);
      this.bottomNav(f, 'my-day');
    }

    // M09: CampCard Digital Badge (Front & Back/Edit)
    {
      const f = this.frame('M09 - CampCard Front & Edit Back', 360, 760, { fill: '#FFF7ED', cornerRadius: 36 });
      this.statusBar(f, false, 'NFC & QR Ready');
      this.headerBar(f, 'My CampCard', false, 'Edit Card');

      // Toggle Front / Back Face
      this.pill(f, 'Front Badge (QR)', 16, 82, 160, true, '#0F172A');
      this.pill(f, 'Back / Privacy Edit', 184, 82, 160, false);

      // Dark Metallic Badge
      this.rect('Badge Front', 24, 118, 312, 350, { fill: '#0F172A', stroke: '#D97706', strokeWeight: 2, cornerRadius: 22 }, f);
      this.image(assets.logo, 40, 134, 32, 32, f, 'Delhi Logo');
      this.rect('Role Pill', 224, 138, 96, 22, { fill: '#FEF3C7', cornerRadius: 11 }, f);
      this.text('SPEAKER', 226, 143, 92, { fontSize: 9, fontWeight: 800, fill: '#B45309', textAlign: 'center' }, f);

      this.ellipse('Avatar', 142, 164, 76, 76, { fill: '#FFFFFF', stroke: '#D97706', strokeWeight: 2 }, f);
      this.image(assets.amit, 146, 168, 68, 68, f, 'Amit');
      this.text('Amit Kumar Singh', 40, 248, 280, { fontSize: 17, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.text('Managing Partner @ WPoets', 40, 272, 280, { fontSize: 10.5, fontWeight: 500, fill: '#94A3B8', textAlign: 'center' }, f);

      this.rect('QR Box', 110, 298, 140, 140, { fill: '#FFFFFF', cornerRadius: 12 }, f);
      this.text('[ High-Contrast vCard QR ]', 116, 360, 128, { fontSize: 9.5, fontWeight: 700, fill: '#0F172A', textAlign: 'center' }, f);

      // Action Buttons: Scan Peer + Share Card + Brightness Boost
      this.rect('Scan Btn', 24, 482, 150, 38, { fill: '#FF4D2D', cornerRadius: 19 }, f);
      this.text('SCAN PEER QR', 26, 494, 146, { fontSize: 10, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('Share Btn', 186, 482, 150, 38, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 19 }, f);
      this.text('SHARE / WALLET', 188, 494, 146, { fontSize: 10, fontWeight: 700, fill: '#0F172A', textAlign: 'center' }, f);

      // Privacy Controls Preview (Back Face Summary)
      this.rect('Privacy Card', 24, 532, 312, 108, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 14 }, f);
      this.text('Back Face Privacy Toggles & Claim Banner:', 36, 542, 288, { fontSize: 10.5, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('• GitHub (@thecancerus) — PUBLIC', 36, 562, 288, { fontSize: 10, fontWeight: 600, fill: '#10B981' }, f);
      this.text('• LinkedIn — PUBLIC', 36, 580, 288, { fontSize: 10, fontWeight: 600, fill: '#10B981' }, f);
      this.text('• Phone — OFFLINE PEER EXCHANGE ONLY', 36, 598, 288, { fontSize: 10, fontWeight: 600, fill: '#FF4D2D' }, f);
      this.text('• Claim Profile Banner: Verified Speaker ✓', 36, 616, 288, { fontSize: 9.5, fontWeight: 600, fill: '#D97706' }, f);

      this.stateLegend(f, 'Front/Back Flip | Privacy Switches | Brightness Boost', 654);
      this.bottomNav(f, 'camp-card');
    }

    // M10: Native MLKit QR Scanner
    {
      const f = this.frame('M10 - Native MLKit QR Scanner', 360, 760, { fill: '#000000', cornerRadius: 36 });
      this.statusBar(f, true, 'Camera 60fps');
      this.text('< Back', 20, 48, 80, { fontSize: 13, fontWeight: 700, fill: '#FFFFFF' }, f);
      this.rect('Torch', 268, 44, 72, 26, { fill: '#1E293B', cornerRadius: 13 }, f);
      this.text('🔦 Flash', 270, 50, 68, { fontSize: 10, fontWeight: 700, fill: '#F59E0B', textAlign: 'center' }, f);

      // Viewfinder
      this.rect('Reticle', 60, 140, 240, 240, { fill: '#0F172A', stroke: '#FF4D2D', strokeWeight: 2.5, cornerRadius: 24 }, f);
      this.line('Laser', 74, 260, 212, { stroke: '#FF4D2D', strokeWeight: 2 }, f);
      this.text('Align peer CampCard or Booth QR (<50ms)', 40, 400, 280, { fontSize: 11, fontWeight: 600, fill: '#CBD5E1', textAlign: 'center' }, f);

      // Detected Contact Bottom Sheet
      this.rect('Sheet BG', 0, 470, 360, 290, { fill: '#1E293B', cornerRadius: 24 }, f);
      this.rect('Handle', 156, 482, 48, 4, { fill: '#475569', cornerRadius: 2 }, f);
      this.image(assets.radhika, 24, 504, 56, 56, f, 'Radhika');
      this.text('Radhika Bhama', 92, 508, 220, { fontSize: 16, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('VP Product · WordCamp Delhi Speaker', 92, 530, 240, { fontSize: 11, fontWeight: 500, fill: '#94A3B8' }, f);
      this.text('Shared: LinkedIn, X, Bio (Saved Offline)', 92, 550, 240, { fontSize: 10, fontWeight: 600, fill: '#10B981' }, f);

      this.rect('Save Btn', 24, 584, 312, 44, { fill: '#D97706', cornerRadius: 22 }, f);
      this.text('SAVE TO MY PEOPLE', 24, 599, 312, { fontSize: 11, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('Scan Next', 24, 640, 312, 40, { fill: '#334155', cornerRadius: 20 }, f);
      this.text('SCAN ANOTHER CODE', 24, 653, 312, { fontSize: 10.5, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);

      this.stateLegend(f, 'Scanning | Torch ON | Detected Bottom Sheet', 704);
    }

    // =========================================================================
    // GROUP 3: NETWORKING, PEOPLE & MEETINGS (M11 - M12)
    // =========================================================================

    // M11: People & Smart Matches Directory
    {
      const f = this.frame('M11 - People & Matches Directory', 360, 760, { fill: '#FFF7ED', cornerRadius: 36 });
      this.statusBar(f, false, '11 Verified');
      this.headerBar(f, 'People & Matches', false, 'My Card');

      // Filter Sub-tabs
      this.pill(f, 'Matches (8)', 16, 84, 86, true, '#0F172A');
      this.pill(f, 'All Roster', 108, 84, 78, false);
      this.pill(f, 'My People', 192, 84, 78, false);
      this.pill(f, 'Plan (2)', 276, 84, 68, false);

      // Person Card 1 (Default Wave State)
      this.rect('P1 BG', 16, 124, 328, 126, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.image(assets.abhay, 28, 138, 52, 52, f, 'Abhay');
      this.text('Abhay Kulkarni', 90, 136, 170, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);
      this.rect('Match 92%', 264, 136, 68, 22, { fill: '#ECFDF5', cornerRadius: 11 }, f);
      this.text('92% Match', 266, 141, 64, { fontSize: 9, fontWeight: 800, fill: '#10B981', textAlign: 'center' }, f);
      this.text('AI & Automation Specialist · n8n Ambassador', 90, 156, 240, { fontSize: 10, fontWeight: 500, fill: '#64748B' }, f);
      this.text('Shared interests: AI Workflows, WordPress', 90, 174, 240, { fontSize: 9.5, fontWeight: 600, fill: '#EA580C' }, f);
      this.rect('Wave Btn', 28, 206, 144, 30, { fill: '#FF4D2D', cornerRadius: 15 }, f);
      this.text('👋 WAVE HELLO', 30, 214, 140, { fontSize: 9.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('Meet Btn', 184, 206, 144, 30, { fill: '#F1F5F9', cornerRadius: 15 }, f);
      this.text('📅 PROPOSE MEET', 186, 214, 140, { fontSize: 9.5, fontWeight: 700, fill: '#0F172A', textAlign: 'center' }, f);

      // Person Card 2 (Mutual Match State -> Chat Unlocked!)
      this.rect('P2 BG', 16, 264, 328, 126, { fill: '#FFFFFF', stroke: '#10B981', strokeWeight: 1.5, cornerRadius: 16 }, f);
      this.image(assets.om, 28, 278, 52, 52, f, 'Om');
      this.text('Omprakash Asnani', 90, 276, 170, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);
      this.rect('Mutual', 252, 276, 80, 22, { fill: '#10B981', cornerRadius: 11 }, f);
      this.text('MUTUAL ✓', 254, 281, 76, { fontSize: 9, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.text('AI Workflows Lead @ Outskill', 90, 296, 240, { fontSize: 10, fontWeight: 500, fill: '#64748B' }, f);
      this.text('Both waved! Chat & Meet unlocked.', 90, 314, 240, { fontSize: 9.5, fontWeight: 600, fill: '#10B981' }, f);
      this.rect('Chat Btn', 28, 346, 144, 30, { fill: '#0F172A', cornerRadius: 15 }, f);
      this.text('💬 OPEN CHAT', 30, 354, 140, { fontSize: 9.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('Report Btn', 184, 346, 144, 30, { fill: '#FFF5F5', stroke: '#FCA5A5', strokeWeight: 1, cornerRadius: 15 }, f);
      this.text('🚩 REPORT', 186, 354, 140, { fontSize: 9.5, fontWeight: 700, fill: '#DC2626', textAlign: 'center' }, f);

      // Person Card 3 (Waved Sent / Pending State)
      this.rect('P3 BG', 16, 404, 328, 92, { fill: '#FAFAFD', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.image(assets.shreya, 28, 418, 48, 48, f, 'Shreya');
      this.text('Shreya Agarwal', 88, 418, 170, { fontSize: 13, fontWeight: 700, fill: '#0F172A' }, f);
      this.text('Documentation Table Lead', 88, 438, 180, { fontSize: 10, fontWeight: 500, fill: '#64748B' }, f);
      this.rect('Waved', 246, 426, 86, 26, { fill: '#FFEDD5', cornerRadius: 13 }, f);
      this.text('WAVED ✓', 248, 432, 82, { fontSize: 9, fontWeight: 800, fill: '#EA580C', textAlign: 'center' }, f);

      this.stateLegend(f, 'Wave Default | Waved Sent | Mutual Match Chat', 654);
      this.bottomNav(f, 'explore');
    }

    // M12: Wave, Chat & Meet Scheduler Dialogs
    {
      const f = this.frame('M12 - Wave, Chat & Meet Dialogs', 360, 760, { fill: '#FFF7ED', cornerRadius: 36 });
      this.statusBar(f, false, 'Modals & Sheets');
      this.headerBar(f, 'Networking Modals', true, '3 States');

      // 1. Wave Dialog Preview
      this.rect('Wave Modal', 16, 84, 328, 154, { fill: '#FFFFFF', stroke: '#FF4D2D', strokeWeight: 1.5, cornerRadius: 16 }, f);
      this.text('1. Wave Dialog (WaveDialog)', 28, 96, 240, { fontSize: 12, fontWeight: 800, fill: '#FF4D2D' }, f);
      this.text('Send a quick hello note to Abhay Kulkarni:', 28, 114, 280, { fontSize: 10, fontWeight: 500, fill: '#475569' }, f);
      this.rect('Note Input', 28, 134, 304, 48, { fill: '#F8FAFC', stroke: '#CBD5E1', strokeWeight: 1, cornerRadius: 10 }, f);
      this.text('"Hey Abhay! Loved your n8n automation demo..."', 36, 144, 288, { fontSize: 10, fontWeight: 400, fill: '#0F172A' }, f);
      this.rect('Send Wave', 216, 194, 116, 30, { fill: '#FF4D2D', cornerRadius: 15 }, f);
      this.text('SEND WAVE', 218, 202, 112, { fontSize: 9.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);

      // 2. Mutual Chat Dialog Preview
      this.rect('Chat Modal', 16, 250, 328, 180, { fill: '#0F172A', cornerRadius: 16 }, f);
      this.text('2. Mutual Match Chat (ChatDialog)', 28, 262, 260, { fontSize: 12, fontWeight: 800, fill: '#10B981' }, f);
      this.rect('Msg Them', 28, 286, 220, 34, { fill: '#1E293B', cornerRadius: 10 }, f);
      this.text('Om: Let’s catch up after the AI panel!', 36, 296, 204, { fontSize: 10, fontWeight: 500, fill: '#FFFFFF' }, f);
      this.rect('Msg Me', 112, 328, 220, 34, { fill: '#D97706', cornerRadius: 10 }, f);
      this.text('You: Sounds great, see you at Booth 2!', 120, 338, 204, { fontSize: 10, fontWeight: 600, fill: '#FFFFFF' }, f);
      this.rect('Chat Box', 28, 376, 240, 36, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 18 }, f);
      this.text('Type message (140/200)...', 40, 388, 200, { fontSize: 10, fontWeight: 400, fill: '#94A3B8' }, f);
      this.rect('Send', 276, 376, 56, 36, { fill: '#10B981', cornerRadius: 18 }, f);
      this.text('SEND', 278, 388, 52, { fontSize: 9.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);

      // 3. Meet Scheduler Sheet Preview
      this.rect('Meet Sheet', 16, 444, 328, 196, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('3. Meet Scheduler Sheet (MeetSheet)', 28, 456, 280, { fontSize: 12, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Pick Slot & Spot:', 28, 478, 160, { fontSize: 10, fontWeight: 600, fill: '#64748B' }, f);
      this.pill(f, '1:30 PM · Coffee', 28, 498, 110, true, '#0F172A');
      this.pill(f, '3:30 PM · Lounge', 146, 498, 114, false);
      this.text('Status Actions:', 28, 538, 160, { fontSize: 10, fontWeight: 600, fill: '#64748B' }, f);
      this.rect('Confirm', 28, 560, 96, 28, { fill: '#ECFDF5', stroke: '#10B981', strokeWeight: 1, cornerRadius: 14 }, f);
      this.text('CONFIRM ✓', 30, 567, 92, { fontSize: 9, fontWeight: 800, fill: '#10B981', textAlign: 'center' }, f);
      this.rect('Decline', 132, 560, 96, 28, { fill: '#FFF5F5', stroke: '#FCA5A5', strokeWeight: 1, cornerRadius: 14 }, f);
      this.text('DECLINE ✕', 134, 567, 92, { fontSize: 9, fontWeight: 800, fill: '#DC2626', textAlign: 'center' }, f);
      this.text('Auto-exports meeting to device .ics calendar', 28, 602, 290, { fontSize: 9.5, fontWeight: 500, fill: '#64748B' }, f);

      this.stateLegend(f, 'Wave Input | Live Chat | Confirm/Decline Meet', 654);
      this.bottomNav(f, 'explore');
    }

    // =========================================================================
    // GROUP 4: EXPLORE, SPONSORS, QUESTS & POST-EVENT (M13 - M16)
    // =========================================================================

    // M13: Explore Directory: Sponsors, Steals & Lead Dialog
    {
      const f = this.frame('M13 - Sponsors, Steals & Lead Dialog', 360, 760, { fill: '#FFF7ED', cornerRadius: 36 });
      this.statusBar(f, false, '17 Sponsors');
      this.headerBar(f, 'Explore Delhi 2026', false, '17 Brands');

      // Sub-tabs
      this.pill(f, 'Steals (8)', 16, 84, 78, true, '#FF4D2D');
      this.pill(f, 'Sponsors', 100, 84, 76, false);
      this.pill(f, 'Speakers', 182, 84, 76, false);
      this.pill(f, 'Orgs', 264, 84, 62, false);

      // Steal Cards with Real Logos
      const steals = [
        { img: assets.sponsor_elementor, name: 'Elementor Ltd', deal: '3 Months Elementor One Free', code: 'WCDELHI26', y: 122 },
        { img: assets.sponsor_wpfusion, name: 'WP Fusion', deal: '30% Off CRM Automation', code: 'AFTERPARTY30', y: 214 },
        { img: assets.sponsor_cookieadmin, name: 'CookieAdmin', deal: 'Free DPDP Act 2023 Audit', code: 'DPDPFREE', y: 306 },
      ];
      for (const s of steals) {
        this.rect(`St ${s.name}`, 16, s.y, 328, 82, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
        this.image(s.img, 28, s.y + 16, 76, 48, f, s.name);
        this.text(s.name, 114, s.y + 12, 160, { fontSize: 13, fontWeight: 800, fill: '#0F172A' }, f);
        this.text(s.deal, 114, s.y + 32, 210, { fontSize: 10.5, fontWeight: 600, fill: '#EA580C' }, f);
        this.text(`Code: ${s.code}`, 114, s.y + 52, 140, { fontSize: 9.5, fontWeight: 600, fill: '#64748B' }, f);
        this.rect(`Btn ${s.name}`, 266, s.y + 46, 66, 24, { fill: '#D97706', cornerRadius: 12 }, f);
        this.text('CLAIM', 268, s.y + 51, 62, { fontSize: 9, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      }

      // StealSheet & LeadDialog Preview
      this.rect('Lead Modal', 16, 404, 328, 224, { fill: '#0F172A', stroke: '#D97706', strokeWeight: 1.5, cornerRadius: 18 }, f);
      this.text('StealSheet + Sponsor Lead Opt-In', 28, 418, 280, { fontSize: 12, fontWeight: 800, fill: '#F59E0B' }, f);
      this.text('Claiming: Elementor One (Code: WCDELHI26)', 28, 438, 290, { fontSize: 11, fontWeight: 700, fill: '#FFFFFF' }, f);
      this.rect('Code Copy', 28, 464, 304, 36, { fill: '#1E293B', stroke: '#F59E0B', strokeWeight: 1, cornerRadius: 10 }, f);
      this.text('WCDELHI26', 40, 475, 160, { fontSize: 13, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('TAP TO COPY ✓', 220, 476, 100, { fontSize: 9.5, fontWeight: 800, fill: '#10B981', textAlign: 'right' }, f);
      this.text('Optional: Share my email with Elementor booth for swag raffle?', 28, 512, 300, { fontSize: 10, fontWeight: 400, fill: '#CBD5E1' }, f);
      this.rect('Share Lead', 28, 550, 146, 32, { fill: '#10B981', cornerRadius: 16 }, f);
      this.text('SHARE & CLAIM', 30, 559, 142, { fontSize: 9.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('Cancel Lead', 186, 550, 146, 32, { fill: '#334155', cornerRadius: 16 }, f);
      this.text('NO THANKS', 188, 559, 142, { fontSize: 9.5, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);

      this.stateLegend(f, 'Sub-Tabs | StealSheet | Copy Code | Lead Opt-In', 654);
      this.bottomNav(f, 'explore');
    }

    // M14: Contributor Day & Tables Guide
    {
      const f = this.frame('M14 - Contributor Day & Tables', 360, 760, { fill: '#FFF7ED', cornerRadius: 36 });
      this.statusBar(f, false, 'Dec 12 · 6 Tables');
      this.headerBar(f, 'Contributor Day', true, 'First-Timer?');

      // Mentor Onboarding Banner
      this.rect('Mentor Box', 16, 84, 328, 76, { fill: '#0F172A', cornerRadius: 16 }, f);
      this.text('New to WordPress Contributing?', 28, 96, 260, { fontSize: 13, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('Pick any table below. Mentors guide you on your first PR or translation.', 28, 116, 300, { fontSize: 10, fontWeight: 400, fill: '#CBD5E1' }, f);

      // Table Cards with Leads & Check-In State
      const tables = [
        { name: 'Core & Gutenberg Table', lead: 'Amit Singh · Table 1', img: assets.amit, checked: true, y: 174 },
        { name: 'Documentation & HelpHub', lead: 'Shreya Agarwal · Table 2', img: assets.shreya, checked: false, y: 286 },
        { name: 'Polyglots (Hindi & Urdu)', lead: 'Community Mentors · Table 3', img: assets.radhika, checked: false, y: 398 },
      ];
      for (const t of tables) {
        this.rect(`Tb ${t.name}`, 16, t.y, 328, 100, {
          fill: '#FFFFFF',
          stroke: t.checked ? '#10B981' : '#E2E8F0',
          strokeWeight: t.checked ? 2 : 1,
          cornerRadius: 16
        }, f);
        this.image(t.img, 28, t.y + 14, 44, 44, f, t.lead);
        this.text(t.name, 84, t.y + 14, 240, { fontSize: 13, fontWeight: 800, fill: '#0F172A' }, f);
        this.text(t.lead, 84, t.y + 34, 240, { fontSize: 10, fontWeight: 500, fill: '#64748B' }, f);
        this.rect(`Btn ${t.name}`, 28, t.y + 64, 150, 26, { fill: t.checked ? '#ECFDF5' : '#FF4D2D', cornerRadius: 13 }, f);
        this.text(t.checked ? 'CHECKED IN ✓ (+200 XP)' : 'CHECK IN AT TABLE', 30, t.y + 70, 146, {
          fontSize: 9,
          fontWeight: 800,
          fill: t.checked ? '#10B981' : '#FFFFFF',
          textAlign: 'center'
        }, f);
        this.rect(`Guide ${t.name}`, 188, t.y + 64, 140, 26, { fill: '#F1F5F9', cornerRadius: 13 }, f);
        this.text('ONBOARDING DOCS', 190, t.y + 70, 136, { fontSize: 9, fontWeight: 700, fill: '#0F172A', textAlign: 'center' }, f);
      }

      // GitHub Setup Checklist
      this.rect('Prep Card', 16, 512, 328, 114, { fill: '#FAFAFD', stroke: '#CBD5E1', strokeWeight: 1, cornerRadius: 14 }, f);
      this.text('Contributor Prep Checklist', 28, 524, 240, { fontSize: 11.5, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('☑ WordPress.org account linked', 28, 546, 280, { fontSize: 10, fontWeight: 600, fill: '#10B981' }, f);
      this.text('☑ Joined #wcdelhi Slack channel', 28, 566, 280, { fontSize: 10, fontWeight: 600, fill: '#10B981' }, f);
      this.text('☐ Local WP / Docker environment ready', 28, 586, 280, { fontSize: 10, fontWeight: 500, fill: '#475569' }, f);

      this.stateLegend(f, 'Checked-In Table | Lead Card | Prep Checklist', 654);
      this.bottomNav(f, 'explore');
    }

    // M15: Quests & XP Rewards
    {
      const f = this.frame('M15 - Quests & XP Rewards', 360, 760, { fill: '#FFF7ED', cornerRadius: 36 });
      this.statusBar(f, false, '450 XP');
      this.headerBar(f, 'Quests & Badges', true, 'Level 2');

      // XP Hero
      this.rect('XP Hero', 16, 84, 328, 108, { fill: '#0F172A', cornerRadius: 18 }, f);
      this.text('Level 2 · Community Builder', 30, 100, 220, { fontSize: 13, fontWeight: 800, fill: '#F59E0B' }, f);
      this.text('450 / 600 XP', 240, 102, 90, { fontSize: 11, fontWeight: 700, fill: '#FFFFFF', textAlign: 'right' }, f);
      this.rect('Bar BG', 30, 128, 300, 10, { fill: '#1E293B', cornerRadius: 5 }, f);
      this.rect('Bar Fill', 30, 128, 225, 10, { fill: '#F59E0B', cornerRadius: 5 }, f);
      this.text('150 XP away from "Delhi Pioneer" Gold Badge', 30, 148, 290, { fontSize: 10, fontWeight: 500, fill: '#CBD5E1' }, f);

      // Quests List (Completed vs Active)
      const quests = [
        { title: 'Scan 3 Sponsor Booths', prog: '3/3 Done', xp: '+150 XP', done: true, y: 206 },
        { title: 'Check In at Contributor Table', prog: '1/1 Done', xp: '+200 XP', done: true, y: 284 },
        { title: 'Exchange 5 Peer CampCards', prog: '2/5 Scanned', xp: '+150 XP', done: false, y: 362 },
        { title: 'Attend 4 Sessions in My Day', prog: '1/4 Attended', xp: '+100 XP', done: false, y: 440 },
      ];
      for (const q of quests) {
        this.rect(`Q ${q.title}`, 16, q.y, 328, 66, {
          fill: q.done ? '#ECFDF5' : '#FFFFFF',
          stroke: q.done ? '#10B981' : '#E2E8F0',
          strokeWeight: 1,
          cornerRadius: 14
        }, f);
        this.text(q.title, 28, q.y + 12, 220, { fontSize: 12, fontWeight: 700, fill: '#0F172A' }, f);
        this.text(q.xp, 254, q.y + 12, 76, { fontSize: 10.5, fontWeight: 800, fill: q.done ? '#10B981' : '#FF4D2D', textAlign: 'right' }, f);
        this.text(q.done ? '✓ COMPLETED' : `Progress: ${q.prog}`, 28, q.y + 36, 220, { fontSize: 10, fontWeight: 600, fill: q.done ? '#065F46' : '#64748B' }, f);
      }

      // Achievement Badges Strip
      this.text('UNLOCKED BADGES', 18, 524, 200, { fontSize: 11, fontWeight: 800, fill: '#0F172A' }, f);
      const badges = ['Early Bird', 'Core Helper', 'Swag Hunter', 'Locked'];
      for (let i = 0; i < 4; i++) {
        const bx = 18 + i * 82;
        this.rect(`Bdg ${i}`, bx, 546, 74, 76, { fill: i < 3 ? '#FFFFFF' : '#F1F5F9', stroke: i < 3 ? '#D97706' : '#CBD5E1', strokeWeight: 1, cornerRadius: 14 }, f);
        this.ellipse(`Ico ${i}`, bx + 21, 558, 32, 32, { fill: i < 3 ? '#FEF3C7' : '#E2E8F0' }, f);
        this.text(badges[i], bx + 4, 598, 66, { fontSize: 9, fontWeight: 700, fill: i < 3 ? '#0F172A' : '#94A3B8', textAlign: 'center' }, f);
      }

      this.stateLegend(f, 'XP Progress | Completed vs Active | Badge Grid', 654);
      this.bottomNav(f, 'explore');
    }

    // M16: Post-Event Memories & Data Sweep
    {
      const f = this.frame('M16 - Memories & Data Sweep', 360, 760, { fill: '#FFF7ED', cornerRadius: 36 });
      this.statusBar(f, false, 'Post-Event Mode');
      this.headerBar(f, 'Delhi Memories', true, 'Dec 14+');

      // Thank You & Gallery Card
      this.rect('Mem Hero', 16, 84, 328, 120, { fill: '#0F172A', cornerRadius: 18 }, f);
      this.text('Thank You, WordCamp Delhi!', 30, 100, 280, { fontSize: 16, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('Relive the talks, group photos, and connections.', 30, 124, 280, { fontSize: 10.5, fontWeight: 400, fill: '#CBD5E1' }, f);
      this.rect('Album Btn', 30, 156, 140, 32, { fill: '#FF4D2D', cornerRadius: 16 }, f);
      this.text('PHOTO ALBUM', 32, 165, 136, { fontSize: 9.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('WPTV Btn', 180, 156, 140, 32, { fill: '#334155', cornerRadius: 16 }, f);
      this.text('WORDPRESS.TV', 182, 165, 136, { fontSize: 9.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);

      // After-Event Data Choice (Privacy Auto-Sweep)
      this.rect('Sweep Card', 16, 220, 328, 250, { fill: '#FFFFFF', stroke: '#D97706', strokeWeight: 1.5, cornerRadius: 18 }, f);
      this.text('What happens to your event data?', 28, 234, 280, { fontSize: 13, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Auto-sweep scheduled on Dec 28, 2026.', 28, 254, 280, { fontSize: 10, fontWeight: 600, fill: '#EA580C' }, f);

      // Option 1 (Selected)
      this.rect('Opt Keep', 28, 280, 304, 78, { fill: '#FFEDD5', stroke: '#FF4D2D', strokeWeight: 1.5, cornerRadius: 12 }, f);
      this.text('● Keep My People & CampCard', 40, 292, 260, { fontSize: 11.5, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Keep scanned contacts on your device for future WordCamps.', 40, 312, 276, { fontSize: 9.5, fontWeight: 500, fill: '#475569' }, f);

      // Option 2 (Default)
      this.rect('Opt Sweep', 28, 370, 304, 78, { fill: '#FAFAFD', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 12 }, f);
      this.text('○ Auto-Sweep Everything', 40, 382, 260, { fontSize: 11.5, fontWeight: 700, fill: '#0F172A' }, f);
      this.text('Automatically erase schedule, waves & event cache after 14 days.', 40, 402, 276, { fontSize: 9.5, fontWeight: 500, fill: '#64748B' }, f);

      this.rect('Export All', 16, 488, 328, 44, { fill: '#0F172A', cornerRadius: 22 }, f);
      this.text('EXPORT MY PEOPLE AS .VCF / .JSON', 16, 502, 328, { fontSize: 10.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);

      this.stateLegend(f, 'Photo/Video Links | Radio Selection | Data Sweep', 654);
      this.bottomNav(f, 'home');
    }

    // =========================================================================
    // GROUP 5: SAFETY, LEGAL, SETTINGS & SYSTEM STATES (M17 - M19)
    // =========================================================================

    // M17: Emergency SOS & Help Sheet
    {
      const f = this.frame('M17 - Emergency SOS & Help Sheet', 360, 760, { fill: '#FFF5F5', cornerRadius: 36 });
      this.statusBar(f, false, 'SOS Active');
      this.headerBar(f, 'Safety & Help', true, '24/7 Desk');

      // 1-Tap Emergency Call Card
      this.rect('Call Card', 16, 86, 328, 128, { fill: '#DC2626', cornerRadius: 18 }, f);
      this.text('Need Immediate Assistance?', 30, 102, 280, { fontSize: 16, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('Direct line to WordCamp Delhi Safety Marshal & DAIC Medical Desk.', 30, 126, 290, { fontSize: 10.5, fontWeight: 400, fill: '#FEE2E2' }, f);
      this.rect('Call Btn', 30, 162, 298, 38, { fill: '#FFFFFF', cornerRadius: 19 }, f);
      this.text('📞 CALL HEAD ORGANIZER NOW', 32, 174, 294, { fontSize: 10.5, fontWeight: 800, fill: '#DC2626', textAlign: 'center' }, f);

      // Confidential Report Card
      this.rect('CoC Card', 16, 228, 328, 120, { fill: '#FFFFFF', stroke: '#FCA5A5', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Code of Conduct Incident Report', 28, 242, 280, { fontSize: 13, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Report harassment or safety concerns confidentially. Anonymous mode supported.', 28, 264, 300, { fontSize: 10, fontWeight: 400, fill: '#64748B' }, f);
      this.rect('Rep Btn', 28, 304, 304, 32, { fill: '#0F172A', cornerRadius: 16 }, f);
      this.text('FILE CONFIDENTIAL REPORT', 30, 313, 300, { fontSize: 10, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);

      // Venue Safety & Quiet Rooms
      this.rect('Quiet Card', 16, 362, 328, 124, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('On-Site Facilities (DAIC)', 28, 376, 260, { fontSize: 12, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('🏥 First Aid Room: Ground Floor Lobby', 28, 400, 290, { fontSize: 10.5, fontWeight: 500, fill: '#475569' }, f);
      this.text('🤫 Quiet & Sensory Room: 1st Floor, Room 104', 28, 422, 290, { fontSize: 10.5, fontWeight: 500, fill: '#475569' }, f);
      this.text('♿ Elevator Access: All Halls & Dining Courtyard', 28, 444, 290, { fontSize: 10.5, fontWeight: 500, fill: '#475569' }, f);

      this.stateLegend(f, '1-Tap Dialer | Confidential Form | Offline Cached', 680);
    }

    // M18: Legal, Privacy & Confidential Report Form
    {
      const f = this.frame('M18 - Legal, Privacy & Report Form', 360, 760, { fill: '#FFF7ED', cornerRadius: 36 });
      this.statusBar(f, false, 'DPDP 2023');
      this.headerBar(f, 'Legal & Incident', true, 'DPDP');

      // Tabs
      this.pill(f, 'Privacy', 16, 84, 80, true, '#0F172A');
      this.pill(f, 'Terms', 102, 84, 72, false);
      this.pill(f, 'Code of Conduct', 180, 84, 110, false);

      // Privacy Content Card
      this.rect('Priv Card', 16, 122, 328, 180, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('1. On-Device First Architecture', 28, 136, 290, { fontSize: 12, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('✓ CampCard vCards & scanned contacts never touch central servers.', 28, 158, 300, { fontSize: 10, fontWeight: 500, fill: '#10B981' }, f);
      this.text('✓ DPDP Act 2023 Compliant Grievance Officer: delhi@wordcamp.org', 28, 192, 300, { fontSize: 10, fontWeight: 500, fill: '#475569' }, f);
      this.rect('Export JSON', 28, 246, 146, 36, { fill: '#F1F5F9', cornerRadius: 18 }, f);
      this.text('EXPORT MY DATA', 30, 258, 142, { fontSize: 9.5, fontWeight: 700, fill: '#0F172A', textAlign: 'center' }, f);
      this.rect('Delete All', 184, 246, 146, 36, { fill: '#FFF5F5', stroke: '#FCA5A5', strokeWeight: 1, cornerRadius: 18 }, f);
      this.text('DELETE MY DATA', 186, 258, 142, { fontSize: 9.5, fontWeight: 800, fill: '#DC2626', textAlign: 'center' }, f);

      // Incident Report Form
      this.rect('Form Card', 16, 316, 328, 316, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Confidential Incident Report Form', 28, 330, 280, { fontSize: 12.5, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Category: Harassment / Safety / Accessibility', 28, 354, 290, { fontSize: 10, fontWeight: 600, fill: '#EA580C' }, f);
      this.rect('Desc Box', 28, 376, 304, 110, { fill: '#F8FAFC', stroke: '#CBD5E1', strokeWeight: 1, cornerRadius: 10 }, f);
      this.text('Describe what happened and where (e.g., Hall A, Corridor)...', 38, 388, 284, { fontSize: 10, fontWeight: 400, fill: '#94A3B8' }, f);
      this.text('☑ Submit Anonymously (Hide my CampCard ID)', 28, 502, 290, { fontSize: 10, fontWeight: 600, fill: '#0F172A' }, f);
      this.rect('Submit Rep', 28, 536, 304, 42, { fill: '#FF4D2D', cornerRadius: 21 }, f);
      this.text('SUBMIT REPORT (QUEUES IF OFFLINE)', 30, 550, 300, { fontSize: 10, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.text('Offline Queue State: Auto-retries when Wi-Fi returns', 28, 594, 300, { fontSize: 9.5, fontWeight: 500, fill: '#64748B' }, f);

      this.stateLegend(f, 'Legal Tabs | Data Export/Delete | Offline Queue', 680);
    }

    // M19: App Settings, Cache & Update Sheet
    {
      const f = this.frame('M19 - Settings, Cache & App Update', 360, 760, { fill: '#FFF7ED', cornerRadius: 36 });
      this.statusBar(f, false, 'v2.4.0');
      this.headerBar(f, 'App Settings', true, 'v2.4.0');

      // Notification Preferences
      this.rect('Notif Prefs', 16, 84, 328, 170, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Notification Preferences', 28, 98, 240, { fontSize: 12, fontWeight: 800, fill: '#0F172A' }, f);
      const prefs = [
        { label: '10-min Session Reminders', on: true, y: 124 },
        { label: 'Urgent Live Delay Alerts', on: true, y: 164 },
        { label: 'New Wave & Match Pings', on: false, y: 204 },
      ];
      for (const p of prefs) {
        this.text(p.label, 28, p.y + 4, 220, { fontSize: 11, fontWeight: 600, fill: '#0F172A' }, f);
        this.rect(`Sw ${p.label}`, 284, p.y, 44, 22, { fill: p.on ? '#10B981' : '#CBD5E1', cornerRadius: 11 }, f);
        this.ellipse(`Kn ${p.label}`, p.on ? 308 : 287, p.y + 3, 16, 16, { fill: '#FFFFFF' }, f);
      }

      // Offline Storage Card
      this.rect('Cache Card', 16, 268, 328, 136, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Offline Storage & Sync', 28, 282, 240, { fontSize: 12, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('14.2 MB cached locally (Delhi 2026 + Logos + Avatars)', 28, 304, 300, { fontSize: 10, fontWeight: 500, fill: '#64748B' }, f);
      this.text('Last Sync: Today 10:28 AM (ETag Verified)', 28, 324, 300, { fontSize: 10, fontWeight: 600, fill: '#10B981' }, f);
      this.rect('Sync Now', 28, 352, 304, 36, { fill: '#0F172A', cornerRadius: 18 }, f);
      this.text('FORCE REFRESH EVENT CACHE', 30, 364, 300, { fontSize: 10, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);

      // App Update Sheet State
      this.rect('Upd Card', 16, 420, 328, 130, { fill: '#0F172A', cornerRadius: 16 }, f);
      this.text('Direct APK Updater (v2.4.1 Available)', 28, 434, 280, { fontSize: 12, fontWeight: 800, fill: '#F59E0B' }, f);
      this.text('Includes improved MLKit scanner speed & Delhi map pins.', 28, 456, 300, { fontSize: 10, fontWeight: 400, fill: '#CBD5E1' }, f);
      this.rect('Install', 28, 494, 146, 36, { fill: '#FF4D2D', cornerRadius: 18 }, f);
      this.text('INSTALL NOW', 30, 506, 142, { fontSize: 10, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('Later', 186, 494, 146, 36, { fill: '#334155', cornerRadius: 18 }, f);
      this.text('LATER', 188, 506, 142, { fontSize: 10, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);

      this.stateLegend(f, 'Switches ON/OFF | Force Sync | Direct APK Update', 680);
    }

    // =========================================================================
    // GROUP 6: IN-APP ORGANIZER / STAFF MODE (M20 - M24)
    // =========================================================================

    // M20: Organizer Login & Role Selection
    {
      const f = this.frame('M20 - Staff Login & Role Picker', 360, 760, { fill: '#0F172A', cornerRadius: 36 });
      this.statusBar(f, true, 'Staff Mode');
      this.text('< Attendee App', 20, 48, 140, { fontSize: 13, fontWeight: 700, fill: '#F59E0B' }, f);

      this.image(assets.logo, 144, 88, 72, 72, f, 'Delhi Logo');
      this.text('CampBuddy Staff Console', 30, 172, 300, { fontSize: 19, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.text('Select your organizer role for WordCamp Delhi 2026', 30, 200, 300, { fontSize: 11, fontWeight: 400, fill: '#94A3B8', textAlign: 'center' }, f);

      // Role Cards (Selected vs Default)
      const roles = [
        { title: 'Event Lead / Organizer', sub: 'Full delays, broadcasts, reports & roster', active: true, y: 244 },
        { title: 'Staff / Volunteer', sub: 'Check-ins, contributor tables & safety desk', active: false, y: 326 },
        { title: 'Super Admin', sub: 'Multi-event config & manager permissions', active: false, y: 408 },
      ];
      for (const r of roles) {
        this.rect(`Role ${r.title}`, 20, r.y, 320, 70, {
          fill: '#1E293B',
          stroke: r.active ? '#FF4D2D' : '#334155',
          strokeWeight: r.active ? 2 : 1,
          cornerRadius: 16
        }, f);
        this.text(r.title, 34, r.y + 14, 240, { fontSize: 13, fontWeight: 800, fill: '#FFFFFF' }, f);
        this.text(r.sub, 34, r.y + 36, 260, { fontSize: 10, fontWeight: 400, fill: '#94A3B8' }, f);
        if (r.active) {
          this.ellipse('Sel', 306, r.y + 24, 20, 20, { fill: '#FF4D2D' }, f);
          this.text('✓', 311, r.y + 27, 12, { fontSize: 10, fontWeight: 800, fill: '#FFFFFF' }, f);
        }
      }

      // Pairing Code / Email Input
      this.rect('Code Input', 20, 496, 320, 46, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 12 }, f);
      this.text('Staff Pairing Token or Organizer Email...', 34, 512, 280, { fontSize: 11, fontWeight: 500, fill: '#64748B' }, f);

      this.rect('Sign In Btn', 20, 560, 320, 48, { fill: '#FF4D2D', cornerRadius: 24 }, f);
      this.text('SIGN IN TO STAFF CONSOLE', 20, 576, 320, { fontSize: 11.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);

      this.stateLegend(f, 'Role Card Selected | Pairing Input | Loading State', 680);
    }

    // M21: Organizer Home & Event Overview
    {
      const f = this.frame('M21 - Organizer Event Overview', 360, 760, { fill: '#0B1120', cornerRadius: 36 });
      this.statusBar(f, true, 'Organizer HQ');
      this.text('< Events', 20, 48, 100, { fontSize: 13, fontWeight: 700, fill: '#94A3B8' }, f);
      this.text('WordCamp Delhi 2026', 90, 47, 190, { fontSize: 15, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('Preview', 274, 44, 70, 24, { fill: '#1E293B', stroke: '#F59E0B', strokeWeight: 1, cornerRadius: 12 }, f);
      this.text('Preview', 276, 49, 66, { fontSize: 9.5, fontWeight: 700, fill: '#F59E0B', textAlign: 'center' }, f);

      // KPI 2x2 Grid
      const kpis = [
        { t: 'Checked In', v: '584', s: '92% Capacity', c: '#10B981', x: 16, y: 84 },
        { t: 'Live Talks', v: '3 / 22', s: 'BHIM +15m Delay', c: '#F59E0B', x: 184, y: 84 },
        { t: 'Sponsors', v: '17 / 17', s: '100% Media OK', c: '#10B981', x: 16, y: 174 },
        { t: 'Safety Queue', v: '0', s: 'All Resolved', c: '#10B981', x: 184, y: 174 },
      ];
      for (const k of kpis) {
        this.rect(`KPI ${k.t}`, k.x, k.y, 160, 78, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 14 }, f);
        this.text(k.t, k.x + 12, k.y + 10, 136, { fontSize: 10, fontWeight: 600, fill: '#94A3B8' }, f);
        this.text(k.v, k.x + 12, k.y + 28, 136, { fontSize: 20, fontWeight: 800, fill: '#FFFFFF' }, f);
        this.text(k.s, k.x + 12, k.y + 54, 136, { fontSize: 9.5, fontWeight: 700, fill: k.c }, f);
      }

      // Staff Action Tabs Grid (OrganizerEventTabs)
      this.text('STAFF OPERATIONS TOOLS', 18, 268, 240, { fontSize: 10.5, fontWeight: 800, fill: '#94A3B8' }, f);
      const tools = [
        { t: '⏱️ Live Delays', s: 'Cascade hall offsets', x: 16, y: 290 },
        { t: '📣 Broadcasts', s: 'Push alerts to attendees', x: 184, y: 290 },
        { t: '🛡️ Safety Reports', s: 'Incident triage queue', x: 16, y: 364 },
        { t: '👥 Attendee Roster', s: 'Roles & check-in scan', x: 184, y: 364 },
        { t: '📋 Contrib Tables', s: 'Leads & quests XP', x: 16, y: 438 },
        { t: '🎨 Social Kit', s: 'Generate reels & cards', x: 184, y: 438 },
      ];
      for (const tl of tools) {
        this.rect(`Tool ${tl.t}`, tl.x, tl.y, 160, 62, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 14 }, f);
        this.text(tl.t, tl.x + 12, tl.y + 12, 136, { fontSize: 11.5, fontWeight: 700, fill: '#FFFFFF' }, f);
        this.text(tl.s, tl.x + 12, tl.y + 34, 136, { fontSize: 9.5, fontWeight: 400, fill: '#94A3B8' }, f);
      }

      // Recent Activity Feed
      this.rect('Act Box', 16, 514, 328, 120, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 14 }, f);
      this.text('Recent Staff Activity', 28, 526, 200, { fontSize: 11, fontWeight: 700, fill: '#FFFFFF' }, f);
      this.text('• 10:29 AM: Amit set BHIM Auditorium +15m delay', 28, 548, 300, { fontSize: 9.5, fontWeight: 500, fill: '#F59E0B' }, f);
      this.text('• 10:15 AM: 42 attendees checked in via QR', 28, 568, 300, { fontSize: 9.5, fontWeight: 500, fill: '#10B981' }, f);
      this.text('• 09:45 AM: Media scraper verified 17/17 logos', 28, 588, 300, { fontSize: 9.5, fontWeight: 500, fill: '#CBD5E1' }, f);

      this.stateLegend(f, 'Staff KPIs | Preview as Attendee | Activity Feed', 680);
    }

    // M22: Staff Live Delay Broadcaster
    {
      const f = this.frame('M22 - Staff Live Delay Broadcaster', 360, 760, { fill: '#0B1120', cornerRadius: 36 });
      this.statusBar(f, true, 'Live Cascade');
      this.text('< Staff HQ', 20, 48, 120, { fontSize: 13, fontWeight: 700, fill: '#94A3B8' }, f);
      this.text('Delay Broadcaster', 120, 47, 180, { fontSize: 15, fontWeight: 800, fill: '#FFFFFF' }, f);

      // Track Selector
      this.text('SELECT HALL / TRACK', 18, 86, 200, { fontSize: 10.5, fontWeight: 800, fill: '#94A3B8' }, f);
      this.pill(f, 'BHIM Auditorium', 16, 108, 130, true, '#FF4D2D');
      this.pill(f, 'Contrib Lounge', 154, 108, 114, false);
      this.pill(f, 'All Halls', 274, 108, 70, false);

      // Increment Controls
      this.rect('Inc Box', 16, 150, 328, 140, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Current Offset: +15 Minutes', 30, 166, 260, { fontSize: 15, fontWeight: 800, fill: '#F59E0B' }, f);
      this.text('Automatically cascades to 5 remaining sessions.', 30, 190, 290, { fontSize: 10, fontWeight: 400, fill: '#CBD5E1' }, f);
      const btns = ['+5m', '+10m', '+15m', '+30m'];
      for (let i = 0; i < 4; i++) {
        const bx = 30 + i * 76;
        const act = i === 2;
        this.rect(`Inc ${btns[i]}`, bx, 224, 68, 36, { fill: act ? '#FF4D2D' : '#0F172A', stroke: act ? '#FF4D2D' : '#334155', strokeWeight: 1, cornerRadius: 10 }, f);
        this.text(btns[i], bx, 235, 68, { fontSize: 11, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      }

      // Cascade Preview
      this.rect('Casc Box', 16, 306, 328, 156, { fill: '#1E293B', cornerRadius: 16 }, f);
      this.text('Cascade Time Preview', 28, 320, 220, { fontSize: 12, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('• Local SEO (Pankaj): 11:20 AM → 11:35 AM', 28, 346, 300, { fontSize: 10.5, fontWeight: 600, fill: '#F59E0B' }, f);
      this.text('• AI Citations (Shammi): 02:00 PM → 02:15 PM', 28, 370, 300, { fontSize: 10.5, fontWeight: 600, fill: '#F59E0B' }, f);
      this.text('• Value Shift Panel: 04:00 PM → 04:15 PM', 28, 394, 300, { fontSize: 10.5, fontWeight: 600, fill: '#F59E0B' }, f);
      this.text('Push notification queued for 584 attendees.', 28, 426, 300, { fontSize: 10, fontWeight: 500, fill: '#10B981' }, f);

      // Broadcast & Clear Buttons
      this.rect('Send Delay', 16, 480, 328, 46, { fill: '#FF4D2D', cornerRadius: 23 }, f);
      this.text('BROADCAST +15M DELAY NOW', 16, 495, 328, { fontSize: 11, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('Clear Delay', 16, 538, 328, 40, { fill: '#0F172A', stroke: '#475569', strokeWeight: 1, cornerRadius: 20 }, f);
      this.text('CLEAR ACTIVE DELAYS (BACK ON TIME)', 16, 551, 328, { fontSize: 10, fontWeight: 700, fill: '#CBD5E1', textAlign: 'center' }, f);

      this.stateLegend(f, 'Track Pill | +15m Active | Cascade Preview | Clear', 680);
    }

    // M23: Staff Push Broadcast & Safety Reports
    {
      const f = this.frame('M23 - Staff Push & Safety Reports', 360, 760, { fill: '#0B1120', cornerRadius: 36 });
      this.statusBar(f, true, 'Staff Moderation');
      this.text('< Staff HQ', 20, 48, 120, { fontSize: 13, fontWeight: 700, fill: '#94A3B8' }, f);
      this.text('Broadcast & Safety', 120, 47, 200, { fontSize: 15, fontWeight: 800, fill: '#FFFFFF' }, f);

      // Push Composer Card
      this.rect('Push Box', 16, 84, 328, 224, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Push Announcement Composer', 28, 98, 260, { fontSize: 12.5, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.pill(f, 'Info', 28, 124, 62, false);
      this.pill(f, 'Schedule', 96, 124, 82, true, '#D97706');
      this.pill(f, 'Urgent', 184, 124, 72, false);

      this.rect('Msg Box', 28, 160, 304, 72, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 10 }, f);
      this.text('Lunch is now served in the Dining Courtyard! Elementor & WP Fusion booths open.', 38, 170, 284, { fontSize: 10.5, fontWeight: 400, fill: '#FFFFFF' }, f);

      this.text('Audience: All Attendees (584)', 28, 244, 180, { fontSize: 10, fontWeight: 600, fill: '#10B981' }, f);
      this.rect('Send Push', 216, 240, 116, 30, { fill: '#FF4D2D', cornerRadius: 15 }, f);
      this.text('SEND PUSH', 218, 248, 112, { fontSize: 9.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);

      // Safety & Moderation Report Triage
      this.text('SAFETY & PROFILE REPORTS QUEUE', 18, 324, 280, { fontSize: 10.5, fontWeight: 800, fill: '#94A3B8' }, f);
      this.rect('Rep 1', 16, 346, 328, 134, { fill: '#1E293B', stroke: '#FCA5A5', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Report #42 · Accessibility Concern', 28, 360, 220, { fontSize: 12, fontWeight: 800, fill: '#FCA5A5' }, f);
      this.rect('InProg', 252, 358, 80, 22, { fill: '#FEF3C7', cornerRadius: 11 }, f);
      this.text('IN PROGRESS', 254, 363, 76, { fontSize: 8.5, fontWeight: 800, fill: '#B45309', textAlign: 'center' }, f);
      this.text('"Elevator near Hall B needs staff assistance for wheelchair access."', 28, 384, 300, { fontSize: 10, fontWeight: 400, fill: '#CBD5E1' }, f);
      this.rect('Resolve', 28, 434, 144, 30, { fill: '#10B981', cornerRadius: 15 }, f);
      this.text('MARK RESOLVED ✓', 30, 442, 140, { fontSize: 9.5, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('Dismiss', 184, 434, 144, 30, { fill: '#334155', cornerRadius: 15 }, f);
      this.text('ASSIGN MARSHAL', 186, 442, 140, { fontSize: 9.5, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);

      this.stateLegend(f, 'Severity Pills | Audience Filter | Triage Actions', 680);
    }

    // M24: Staff Social Kit & Reels Generator
    {
      const f = this.frame('M24 - Staff Social Kit & Reels', 360, 760, { fill: '#0B1120', cornerRadius: 36 });
      this.statusBar(f, true, 'Social Kit');
      this.text('< Staff HQ', 20, 48, 120, { fontSize: 13, fontWeight: 700, fill: '#94A3B8' }, f);
      this.text('Social Card Studio', 120, 47, 200, { fontSize: 15, fontWeight: 800, fill: '#FFFFFF' }, f);

      // Theme & Template Pills
      this.pill(f, 'Speaker Spotlight', 16, 84, 120, true, '#FF4D2D');
      this.pill(f, 'Daylight Card', 142, 84, 102, false);
      this.pill(f, 'Schedule Reel', 250, 84, 94, false);

      // Live Generated Social Card Preview
      this.rect('Social Canvas', 32, 126, 296, 370, { fill: '#0F172A', stroke: '#D97706', strokeWeight: 2, cornerRadius: 20 }, f);
      this.image(assets.logo, 50, 144, 34, 34, f, 'Delhi Logo');
      this.text('WORDCAMP DELHI 2026', 92, 152, 200, { fontSize: 10.5, fontWeight: 800, fill: '#F59E0B' }, f);

      this.ellipse('Spk Ring', 118, 198, 124, 124, { fill: '#1E293B', stroke: '#FF4D2D', strokeWeight: 3 }, f);
      this.image(assets.pankaj, 124, 204, 112, 112, f, 'Pankaj Social');

      this.text('Pankaj Kumar', 48, 336, 264, { fontSize: 20, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.text('10 Things to Do to Boost Local SEO in the AI Era', 48, 366, 264, { fontSize: 12, fontWeight: 600, fill: '#FF4D2D', textAlign: 'center' }, f);
      this.text('Dec 13 · 11:20 AM · BHIM Auditorium', 48, 412, 264, { fontSize: 10.5, fontWeight: 500, fill: '#CBD5E1', textAlign: 'center' }, f);
      this.text('campy.org/delhi-2026', 48, 456, 264, { fontSize: 10, fontWeight: 700, fill: '#10B981', textAlign: 'center' }, f);

      // Share & Export Buttons
      this.rect('Share PNG', 32, 514, 296, 44, { fill: '#FF4D2D', cornerRadius: 22 }, f);
      this.text('EXPORT & SHARE SOCIAL PNG', 32, 529, 296, { fontSize: 11, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('Toggle Theme', 32, 570, 296, 38, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 19 }, f);
      this.text('SWITCH TO DAYLIGHT PAPER THEME', 32, 583, 296, { fontSize: 10, fontWeight: 700, fill: '#CBD5E1', textAlign: 'center' }, f);

      this.stateLegend(f, 'Template Switch | Dark/Daylight | PNG Export', 680);
    }
  }
}

const builder = new ApkFigCraftBuilder();
builder.buildAll24Screens();

const scriptPath = path.resolve(rootDir, 'scratch/figcraft_apk_24_script.json');
fs.writeFileSync(scriptPath, JSON.stringify(builder.commands, null, 2), 'utf8');
console.log(`Generated ${builder.commands.length} FigCraft commands for ALL 24 Mobile APK screens.`);
console.log(`Saved script to ${scriptPath}`);
