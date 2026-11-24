import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const rootDir = path.resolve(__dirname, '..');

const assets = {
  logo: path.resolve(rootDir, 'docs/designs/assets/delhi_logo.png'),
  pankaj: path.resolve(rootDir, 'docs/designs/assets/pankaj_kumar.webp'),
  radhika: path.resolve(rootDir, 'docs/designs/assets/radhika_bhama.jpg'),
  amit: path.resolve(rootDir, 'docs/designs/assets/amit_singh.webp'),
  shammi: path.resolve(rootDir, 'docs/designs/assets/shammi_proothi.webp'),
  abhay: path.resolve(rootDir, 'docs/designs/assets/abhay_kulkarni.webp'),
  skyline: path.resolve(rootDir, 'docs/designs/assets/delhi_skyline.webp'),
  sponsor_elementor: path.resolve(rootDir, 'docs/designs/assets/sponsor_elementor.webp'),
  sponsor_wpfusion: path.resolve(rootDir, 'docs/designs/assets/sponsor_wpfusion.webp'),
  sponsor_cookieadmin: path.resolve(rootDir, 'docs/designs/assets/sponsor_cookieadmin.webp'),
  sponsor_nexgi: path.resolve(rootDir, 'docs/designs/assets/sponsor_nexgi.webp'),
};

class FigCraftMultiPageBuilder {
  constructor() {
    this.commands = [];
    this.nextId = 2;
    this.isFirstPage = true;
    this.pages = [];
  }

  setPage(name) {
    if (this.isFirstPage) {
      this.commands.push({ command: 'page.rename', params: { name } });
      this.isFirstPage = false;
    } else {
      this.nextId++;
      this.commands.push({ command: 'page.add', params: { name } });
    }
    this.pages.push(name);
  }

  frame(name, x, y, width, height, props = {}, parent = null) {
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

  text(text, x, y, width = null, props = {}, parent = null, name = null) {
    const id = this.nextId++;
    const params = { text: String(text), x, y, props };
    if (width) params.width = width;
    if (parent) params.parent = parent;
    if (name) params.name = name;
    this.commands.push({ command: 'text.create', params });
    return id;
  }

  image(filePath, x, y, width, height, parent = null, name = 'Image') {
    if (!fs.existsSync(filePath)) {
      // Fallback rectangle if file missing
      return this.rect(name, x, y, width, height, { fill: '#334155', cornerRadius: 8 }, parent);
    }
    const id = this.nextId++;
    const params = { path: filePath, x, y, width, height, parent, name, mode: 'fit' };
    this.commands.push({ command: 'image.place', params });
    return id;
  }

  mobileStatusBar(parent, dark = false) {
    const color = dark ? '#FFFFFF' : '#0F172A';
    this.text('10:30 AM', 24, 14, 80, { fontSize: 12, fontWeight: 700, fill: color }, parent, 'Time');
    this.ellipse('Pulse Dot', 220, 18, 6, 6, { fill: '#10B981' }, parent);
    this.text('Offline Ready', 232, 14, 90, { fontSize: 10, fontWeight: 700, fill: '#10B981' }, parent, 'Offline');
    this.rect('Battery', 330, 15, 20, 10, { fill: color, cornerRadius: 2 }, parent);
  }

  mobileBottomNav(parent, activeTab = 'home') {
    const y = 700;
    this.rect('Nav Bar BG', 0, y, 360, 60, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 }, parent);
    const tabs = [
      { key: 'home', label: 'Home', x: 16 },
      { key: 'schedule', label: 'Schedule', x: 88 },
      { key: 'my-day', label: 'My Day', x: 160 },
      { key: 'camp-card', label: 'CampCard', x: 232 },
      { key: 'explore', label: 'Explore', x: 304 },
    ];
    for (const tab of tabs) {
      const isActive = tab.key === activeTab;
      if (isActive) {
        this.rect('Active Tab Pill', tab.x - 4, y + 10, 48, 22, { fill: '#FFEDD5', cornerRadius: 11 }, parent);
      }
      this.text(tab.label, tab.x - 6, y + 36, 52, { fontSize: 10, fontWeight: isActive ? 700 : 500, fill: isActive ? '#EA580C' : '#64748B', textAlign: 'center' }, parent);
    }
  }

  // ==========================================
  // PAGE 1: MOBILE APP APK (9 SCREENS)
  // ==========================================
  buildApkPage() {
    this.setPage('Page 1 - Mobile APK');
    const startX = 60;
    const startY = 60;
    const gapX = 420;
    const gapY = 820;

    // --- A01: Splash & Identity ---
    {
      const f = this.frame('A01 - Splash & Identity', startX, startY, 360, 760, { fill: '#0F172A', cornerRadius: 36 });
      this.mobileStatusBar(f, true);
      this.image(assets.logo, 140, 180, 80, 80, f, 'WordCamp Logo');
      this.text('WordCamp Delhi 2026', 40, 290, 280, { fontSize: 22, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.text('"Dil se Dilli, code se community"', 40, 325, 280, { fontSize: 12, fontWeight: 500, fill: '#F59E0B', textAlign: 'center' }, f);
      this.text('Dec 12–13 · Dr. Ambedkar International Centre', 40, 350, 280, { fontSize: 11, fontWeight: 500, fill: '#94A3B8', textAlign: 'center' }, f);

      this.rect('Cache Bar BG', 50, 520, 260, 6, { fill: '#1E293B', cornerRadius: 3 }, f);
      this.rect('Cache Bar Fill', 50, 520, 260, 6, { fill: '#10B981', cornerRadius: 3 }, f);
      this.text('SQLite Database 100% Pre-cached Offline', 50, 534, 260, { fontSize: 10, fontWeight: 600, fill: '#10B981', textAlign: 'center' }, f);

      this.rect('Enter Btn', 50, 620, 260, 48, { fill: '#FF4D2D', cornerRadius: 24 }, f);
      this.text('ENTER EXPERIENCE', 50, 636, 260, { fontSize: 12, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
    }

    // --- A02: Tactile Live Event Home Dashboard (Wireframe DNA!) ---
    {
      const f = this.frame('A02 - Tactile Home Dashboard', startX + gapX, startY, 360, 760, { fill: '#FFF7ED', cornerRadius: 36 });
      this.mobileStatusBar(f, false);

      // Topbar
      this.image(assets.logo, 20, 44, 28, 28, f, 'Delhi Logo');
      this.text('CampBuddy', 54, 48, 120, { fontSize: 17, fontWeight: 800, fill: '#FF4D2D' }, f);
      this.rect('Staff Badge', 220, 46, 68, 24, { fill: '#FFEDD5', stroke: '#FF4D2D', strokeWeight: 1, cornerRadius: 12 }, f);
      this.text('👑 Staff', 228, 51, 52, { fontSize: 10, fontWeight: 700, fill: '#FF4D2D', textAlign: 'center' }, f);
      this.ellipse('Help Btn', 302, 44, 28, 28, { fill: '#FFEDD5' }, f);
      this.text('?', 311, 49, 12, { fontSize: 13, fontWeight: 700, fill: '#EA580C' }, f);

      // Dark Hero Card
      this.rect('Hero Card BG', 16, 84, 328, 140, { fill: '#0F172A', cornerRadius: 20 }, f);
      this.text('Good morning, Sunil', 32, 100, 200, { fontSize: 11, fontWeight: 600, fill: '#94A3B8' }, f);
      this.text('10:30 AM', 32, 118, 200, { fontSize: 32, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('Day 1 • Dec 12, 2026 • DAIC New Delhi', 32, 156, 250, { fontSize: 11, fontWeight: 500, fill: '#CBD5E1' }, f);

      // Next Pill
      this.rect('Next Pill', 32, 178, 296, 36, { fill: '#1E293B', cornerRadius: 12 }, f);
      this.text('Next: Local SEO in AI Era (Pankaj)', 42, 188, 200, { fontSize: 10, fontWeight: 700, fill: '#FFFFFF' }, f);
      this.rect('View Btn', 260, 184, 58, 24, { fill: '#D97706', cornerRadius: 12 }, f);
      this.text('VIEW', 262, 189, 54, { fontSize: 9, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);

      // My People Strip with Real Photos!
      this.text('MY PEOPLE & MATCHES', 20, 240, 200, { fontSize: 11, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('5 Pinned · 11 Verified', 220, 240, 120, { fontSize: 10, fontWeight: 500, fill: '#64748B', textAlign: 'right' }, f);

      const people = [
        { img: assets.pankaj, label: 'Pankaj' },
        { img: assets.radhika, label: 'Radhika' },
        { img: assets.amit, label: 'Amit' },
        { img: assets.shammi, label: 'Shammi' },
        { img: assets.abhay, label: 'Abhay' },
      ];
      for (let i = 0; i < people.length; i++) {
        const px = 20 + i * 66;
        this.ellipse(`Avatar Frame ${i}`, px, 260, 48, 48, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 2 }, f);
        this.image(people[i].img, px + 2, 262, 44, 44, f, people[i].label);
        this.ellipse(`Dot ${i}`, px + 36, 296, 10, 10, { fill: '#10B981', stroke: '#FFFFFF', strokeWeight: 2 }, f);
        this.text(people[i].label, px - 2, 314, 52, { fontSize: 10, fontWeight: 600, fill: '#0F172A', textAlign: 'center' }, f);
      }

      // 2x3 Action Grid
      this.text('QUICK ACTIONS', 20, 342, 200, { fontSize: 11, fontWeight: 800, fill: '#0F172A' }, f);
      const cards = [
        { title: 'Schedule', sub: '22 talks · 3 halls', x: 20, y: 362 },
        { title: 'My Day', sub: '4 saved · 0 conflicts', x: 184, y: 362 },
        { title: 'Sponsors & Steals', sub: 'Elementor, WP Fusion...', x: 20, y: 442 },
        { title: 'Contributor XP', sub: '6 tables · Core, Docs', x: 184, y: 442 },
        { title: 'Venue Map', sub: 'DAIC Janpath Rd', x: 20, y: 522 },
        { title: 'CampCard QR', sub: 'Digital NFC pass', x: 184, y: 522 },
      ];
      for (const c of cards) {
        this.rect(`Card ${c.title}`, c.x, c.y, 156, 68, { fill: '#FAFAFD', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 14 }, f);
        this.rect(`Icon ${c.title}`, c.x + 10, c.y + 10, 24, 24, { fill: '#FFEDD5', cornerRadius: 6 }, f);
        this.text(c.title, c.x + 40, c.y + 14, 106, { fontSize: 12, fontWeight: 700, fill: '#0F172A' }, f);
        this.text(c.sub, c.x + 10, c.y + 44, 136, { fontSize: 9, fontWeight: 500, fill: '#64748B' }, f);
      }

      // Sticky SOS Safety Bar
      this.rect('SOS Bar', 16, 608, 328, 48, { fill: '#FFF5F5', stroke: '#FCA5A5', strokeWeight: 1, cornerRadius: 14 }, f);
      this.text('🛡️ On-site Emergency & Code of Conduct', 26, 616, 210, { fontSize: 10, fontWeight: 700, fill: '#DC2626' }, f);
      this.text('DAIC medical desk & organizer hotline', 26, 632, 210, { fontSize: 9, fontWeight: 500, fill: '#64748B' }, f);
      this.rect('SOS Btn', 246, 616, 88, 30, { fill: '#FF4D2D', cornerRadius: 15 }, f);
      this.text('EMERGENCY', 248, 624, 84, { fontSize: 9, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);

      this.mobileBottomNav(f, 'home');
    }

    // --- A03: Multi-Track Schedule ---
    {
      const f = this.frame('A03 - Multi-Track Schedule', startX + gapX * 2, startY, 360, 760, { fill: '#FFF7ED', cornerRadius: 36 });
      this.mobileStatusBar(f, false);
      this.text('Conference Schedule', 20, 48, 200, { fontSize: 18, fontWeight: 800, fill: '#0F172A' }, f);

      // Track Pills
      this.rect('Day 1 Pill', 20, 84, 156, 32, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Day 1 (Dec 12)', 30, 92, 136, { fontSize: 11, fontWeight: 600, fill: '#64748B', textAlign: 'center' }, f);
      this.rect('Day 2 Pill', 184, 84, 156, 32, { fill: '#0F172A', cornerRadius: 16 }, f);
      this.text('Day 2 (Dec 13)', 194, 92, 136, { fontSize: 11, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);

      // Session Cards
      const sessions = [
        { time: '10:00 AM · 30m', hall: 'BHIM Hall', title: 'From Feature to Ecosystem', spk: 'Radhika Bhama', y: 130 },
        { time: '11:00 AM · 20m', hall: 'BHIM Hall', title: 'Generative Engine Optimization (GEO)', spk: 'Mayank Kumar', y: 236 },
        { time: '11:20 AM · 20m', hall: 'BHIM Hall', title: '10 Things to Boost Local SEO in AI Era', spk: 'Pankaj Kumar', y: 342 },
        { time: '02:00 PM · 30m', hall: 'BHIM Hall', title: 'Cited but Not Recommended in AI Search', spk: 'Shammi Proothi', y: 448 },
        { time: '04:00 PM · 60m', hall: 'Full Plenary', title: 'PANEL: Value Shift in the AI Era', spk: 'Amit, Om, Abhay', y: 554 },
      ];
      for (const s of sessions) {
        this.rect(`Sess BG ${s.time}`, 16, s.y, 328, 94, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
        this.text(s.time, 28, s.y + 10, 140, { fontSize: 10, fontWeight: 700, fill: '#EA580C' }, f);
        this.text(s.hall, 240, s.y + 10, 90, { fontSize: 9, fontWeight: 600, fill: '#64748B', textAlign: 'right' }, f);
        this.text(s.title, 28, s.y + 28, 290, { fontSize: 12, fontWeight: 700, fill: '#0F172A' }, f);
        this.text(`Speaker: ${s.spk}`, 28, s.y + 68, 220, { fontSize: 10, fontWeight: 500, fill: '#64748B' }, f);
        this.rect(`Star ${s.time}`, 296, s.y + 64, 34, 20, { fill: '#FEF3C7', cornerRadius: 10 }, f);
        this.text('★', 307, s.y + 67, 14, { fontSize: 10, fontWeight: 800, fill: '#D97706' }, f);
      }

      this.mobileBottomNav(f, 'schedule');
    }

    // --- A04: Session Detail View ---
    {
      const f = this.frame('A04 - Session Detail View', startX + gapX * 3, startY, 360, 760, { fill: '#FFF7ED', cornerRadius: 36 });
      this.mobileStatusBar(f, false);
      this.text('← Session Profile', 20, 48, 200, { fontSize: 14, fontWeight: 700, fill: '#EA580C' }, f);

      // Speaker Card
      this.rect('Spk BG', 16, 84, 328, 90, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 18 }, f);
      this.image(assets.pankaj, 28, 96, 64, 64, f, 'Pankaj Headshot');
      this.text('Pankaj Kumar', 104, 102, 220, { fontSize: 15, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Founder @ Pankaj Kumar SEO', 104, 122, 220, { fontSize: 11, fontWeight: 600, fill: '#FF4D2D' }, f);
      this.text('10+ Years in Search & AI Visibility', 104, 140, 220, { fontSize: 10, fontWeight: 500, fill: '#64748B' }, f);

      // Talk Details
      this.rect('Talk BG', 16, 186, 328, 260, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 18 }, f);
      this.text('Day 2 · 11:20 AM (20 mins)', 28, 200, 180, { fontSize: 10, fontWeight: 700, fill: '#EA580C' }, f);
      this.text('BHIM Hall', 260, 200, 70, { fontSize: 10, fontWeight: 700, fill: '#10B981', textAlign: 'right' }, f);
      this.text('10 Things to Do to Boost Local SEO in the AI Era', 28, 224, 300, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('AI engines like ChatGPT Search, Perplexity, and Google AI Overviews are changing how local services and businesses are discovered. Learn actionable schema and entity strategies.', 28, 274, 300, { fontSize: 11, fontWeight: 400, fill: '#475569' }, f);

      this.rect('Bookmark Btn', 28, 380, 140, 42, { fill: '#D97706', cornerRadius: 21 }, f);
      this.text('BOOKMARK TALK', 30, 394, 136, { fontSize: 10, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('Slides Btn', 180, 380, 140, 42, { fill: '#F1F5F9', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 21 }, f);
      this.text('DOWNLOAD SLIDES', 182, 394, 136, { fontSize: 10, fontWeight: 700, fill: '#0F172A', textAlign: 'center' }, f);

      // Venue Locator Box
      this.rect('Venue Box', 16, 460, 328, 64, { fill: '#FAFAFD', stroke: '#CBD5E1', strokeWeight: 1, cornerRadius: 14 }, f);
      this.text('📍 Ground Floor, BHIM Auditorium', 28, 474, 280, { fontSize: 11, fontWeight: 700, fill: '#0F172A' }, f);
      this.text('Dr. Ambedkar International Centre · 15 Janpath Rd', 28, 494, 280, { fontSize: 10, fontWeight: 500, fill: '#64748B' }, f);

      this.mobileBottomNav(f, 'schedule');
    }

    // --- A05: My Day & Conflict Hub ---
    {
      const f = this.frame('A05 - My Day & Conflict Hub', startX, startY + gapY, 360, 760, { fill: '#FFF7ED', cornerRadius: 36 });
      this.mobileStatusBar(f, false);
      this.text('My Day Itinerary', 20, 48, 200, { fontSize: 18, fontWeight: 800, fill: '#0F172A' }, f);

      // Conflict Banner
      this.rect('Conflict BG', 16, 84, 328, 92, { fill: '#FEF3C7', stroke: '#F59E0B', strokeWeight: 1.5, cornerRadius: 16 }, f);
      this.text('⚠️ Schedule Conflict at 11:00 AM', 28, 96, 280, { fontSize: 11, fontWeight: 800, fill: '#B45309' }, f);
      this.text('You starred GEO Talk (Mayank) & SEO Talk (Pankaj) in overlapping slots.', 28, 114, 300, { fontSize: 10, fontWeight: 500, fill: '#78350F' }, f);
      this.rect('Keep A', 28, 140, 140, 26, { fill: '#F59E0B', cornerRadius: 13 }, f);
      this.text('KEEP GEO TALK', 30, 146, 136, { fontSize: 9, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('Keep B', 180, 140, 140, 26, { fill: '#FFFFFF', stroke: '#D97706', strokeWeight: 1, cornerRadius: 13 }, f);
      this.text('KEEP SEO TALK', 182, 146, 136, { fontSize: 9, fontWeight: 800, fill: '#B45309', textAlign: 'center' }, f);

      // Timeline
      this.text('SAVED ITINERARY (3 TALKS)', 20, 192, 250, { fontSize: 11, fontWeight: 800, fill: '#0F172A' }, f);

      const items = [
        { time: '10:00 AM', title: 'From Feature to Ecosystem', hall: 'BHIM Hall', y: 216 },
        { time: '01:00 PM', title: 'Networking Lunch & Sponsor Expo', hall: 'Dining Courtyard', y: 310 },
        { time: '04:00 PM', title: 'PANEL: Value Shift in AI Era', hall: 'BHIM Hall', y: 404 },
      ];
      for (const it of items) {
        this.rect(`It BG ${it.time}`, 16, it.y, 328, 80, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
        this.text(it.time, 28, it.y + 12, 100, { fontSize: 10, fontWeight: 700, fill: '#EA580C' }, f);
        this.text(it.hall, 220, it.y + 12, 100, { fontSize: 9, fontWeight: 600, fill: '#64748B', textAlign: 'right' }, f);
        this.text(it.title, 28, it.y + 32, 290, { fontSize: 12, fontWeight: 700, fill: '#0F172A' }, f);
      }

      this.mobileBottomNav(f, 'my-day');
    }

    // --- A06: CampCard Digital Badge ---
    {
      const f = this.frame('A06 - CampCard Digital Badge', startX + gapX, startY + gapY, 360, 760, { fill: '#FFF7ED', cornerRadius: 36 });
      this.mobileStatusBar(f, false);
      this.text('My CampCard Badge', 20, 48, 200, { fontSize: 18, fontWeight: 800, fill: '#0F172A' }, f);

      // Metallic Badge Card
      this.rect('Badge BG', 24, 84, 312, 420, { fill: '#0F172A', stroke: '#D97706', strokeWeight: 2, cornerRadius: 24 }, f);
      this.image(assets.logo, 40, 104, 32, 32, f, 'Delhi Badge Logo');
      this.rect('Attendee Pill', 220, 108, 96, 22, { fill: '#FEF3C7', cornerRadius: 11 }, f);
      this.text('ATTENDEE', 222, 113, 92, { fontSize: 9, fontWeight: 800, fill: '#B45309', textAlign: 'center' }, f);

      this.ellipse('Badge Avatar', 136, 150, 72, 72, { fill: '#FFFFFF', stroke: '#D97706', strokeWeight: 2 }, f);
      this.image(assets.amit, 140, 154, 64, 64, f, 'Attendee Photo');

      this.text('Sunil Kumar', 40, 236, 280, { fontSize: 18, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.text('Full-Stack Engineer · InHouse', 40, 260, 280, { fontSize: 11, fontWeight: 500, fill: '#94A3B8', textAlign: 'center' }, f);

      this.rect('QR Mock', 106, 290, 148, 148, { fill: '#FFFFFF', cornerRadius: 12 }, f);
      this.text('[ Encrypted vCard QR Code ]', 110, 356, 140, { fontSize: 10, fontWeight: 700, fill: '#0F172A', textAlign: 'center' }, f);

      this.text('Tap with another attendee to beam profile', 40, 460, 280, { fontSize: 10, fontWeight: 500, fill: '#CBD5E1', textAlign: 'center' }, f);

      // Privacy Settings
      this.rect('Privacy Box', 24, 520, 312, 100, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
      this.text('Data Privacy Controls', 36, 532, 200, { fontSize: 11, fontWeight: 700, fill: '#0F172A' }, f);
      this.text('GitHub Profile: PUBLIC', 36, 554, 250, { fontSize: 10, fontWeight: 500, fill: '#10B981' }, f);
      this.text('LinkedIn: PUBLIC', 36, 574, 250, { fontSize: 10, fontWeight: 500, fill: '#10B981' }, f);
      this.text('Phone Number: OFFLINE PEER ONLY', 36, 594, 250, { fontSize: 10, fontWeight: 500, fill: '#FF4D2D' }, f);

      this.mobileBottomNav(f, 'camp-card');
    }

    // --- A07: Native MLKit Scanner ---
    {
      const f = this.frame('A07 - Native MLKit Scanner', startX + gapX * 2, startY + gapY, 360, 760, { fill: '#000000', cornerRadius: 36 });
      this.mobileStatusBar(f, true);

      this.text('Scanning CampCards & Booths...', 40, 80, 280, { fontSize: 13, fontWeight: 700, fill: '#FFFFFF', textAlign: 'center' }, f);

      // Viewfinder
      this.rect('Viewfinder', 60, 160, 240, 240, { fill: '#1E293B', stroke: '#FF4D2D', strokeWeight: 2, cornerRadius: 24 }, f);
      this.line('Laser', 70, 280, 220, { stroke: '#FF4D2D', strokeWeight: 2 }, f);

      // Detected Bottom Sheet
      this.rect('Detected Sheet', 0, 520, 360, 240, { fill: '#1E293B', cornerRadius: 24 }, f);
      this.image(assets.radhika, 28, 544, 56, 56, f, 'Scanned Contact Photo');
      this.text('Radhika Bhama', 96, 550, 200, { fontSize: 15, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('VP Product · Speaker @ Delhi 2026', 96, 570, 220, { fontSize: 11, fontWeight: 500, fill: '#94A3B8' }, f);
      this.rect('Save Btn', 28, 624, 304, 46, { fill: '#D97706', cornerRadius: 23 }, f);
      this.text('SAVE TO OFFLINE CONTACTS', 30, 638, 300, { fontSize: 11, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
    }

    // --- A08: Explore & Verified Sponsors ---
    {
      const f = this.frame('A08 - Explore Directory', startX + gapX * 3, startY + gapY, 360, 760, { fill: '#FFF7ED', cornerRadius: 36 });
      this.mobileStatusBar(f, false);
      this.text('Sponsors & Steals', 20, 48, 200, { fontSize: 18, fontWeight: 800, fill: '#0F172A' }, f);

      // Steal Cards
      const deals = [
        { img: assets.sponsor_elementor, name: 'Elementor Ltd', deal: '3 Months Free Elementor One', code: 'Code: WCDELHI26', y: 90 },
        { img: assets.sponsor_wpfusion, name: 'WP Fusion', deal: '30% Off CRM Automations', code: 'After Party Sponsor', y: 194 },
        { img: assets.sponsor_cookieadmin, name: 'CookieAdmin', deal: 'Free DPDP 2023 Consent Audit', code: 'Indian Privacy Plugin', y: 298 },
        { img: assets.sponsor_nexgi, name: 'NexGi Systems', deal: 'Free Enterprise Stack Audit', code: 'Bronze Partner', y: 402 },
      ];
      for (const d of deals) {
        this.rect(`Deal BG ${d.name}`, 16, d.y, 328, 88, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
        this.image(d.img, 28, d.y + 16, 72, 48, f, d.name);
        this.text(d.name, 112, d.y + 14, 200, { fontSize: 13, fontWeight: 800, fill: '#0F172A' }, f);
        this.text(d.deal, 112, d.y + 36, 210, { fontSize: 11, fontWeight: 600, fill: '#EA580C' }, f);
        this.text(d.code, 112, d.y + 56, 160, { fontSize: 10, fontWeight: 500, fill: '#64748B' }, f);
        this.rect(`Claim ${d.name}`, 268, d.y + 50, 60, 24, { fill: '#D97706', cornerRadius: 12 }, f);
        this.text('CLAIM', 270, d.y + 55, 56, { fontSize: 9, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      }

      this.mobileBottomNav(f, 'explore');
    }

    // --- A09: Quests & Contributor Day ---
    {
      const f = this.frame('A09 - Quests & XP Rewards', startX + gapX * 4, startY, 360, 760, { fill: '#FFF7ED', cornerRadius: 36 });
      this.mobileStatusBar(f, false);
      this.text('Contributor Quests', 20, 48, 200, { fontSize: 18, fontWeight: 800, fill: '#0F172A' }, f);

      // XP Hero Card
      this.rect('XP Card', 16, 84, 328, 100, { fill: '#0F172A', cornerRadius: 18 }, f);
      this.text('Level 2 Contributor', 30, 100, 200, { fontSize: 12, fontWeight: 700, fill: '#F59E0B' }, f);
      this.text('450 / 600 XP', 240, 100, 80, { fontSize: 11, fontWeight: 600, fill: '#CBD5E1', textAlign: 'right' }, f);
      this.rect('XP BG', 30, 126, 280, 8, { fill: '#1E293B', cornerRadius: 4 }, f);
      this.rect('XP Bar', 30, 126, 210, 8, { fill: '#F59E0B', cornerRadius: 4 }, f);
      this.text('Next Reward: Community Pioneer Badge', 30, 146, 260, { fontSize: 10, fontWeight: 500, fill: '#94A3B8' }, f);

      // Quest Tables
      const tables = [
        { name: 'Core & Gutenberg Table', lead: 'Amit Singh · Table 1', xp: '+200 XP', y: 200 },
        { name: 'Documentation & HelpHub', lead: 'Shreya Agarwal · Table 2', xp: '+150 XP', y: 294 },
        { name: 'Polyglots Hindi & Urdu', lead: 'Community Table · Table 3', xp: '+150 XP', y: 388 },
      ];
      for (const t of tables) {
        this.rect(`Tab BG ${t.name}`, 16, t.y, 328, 80, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
        this.text(t.name, 28, t.y + 14, 220, { fontSize: 13, fontWeight: 800, fill: '#0F172A' }, f);
        this.text(t.xp, 260, t.y + 14, 70, { fontSize: 11, fontWeight: 800, fill: '#FF4D2D', textAlign: 'right' }, f);
        this.text(t.lead, 28, t.y + 36, 220, { fontSize: 10, fontWeight: 500, fill: '#64748B' }, f);
        this.rect(`Checkin ${t.name}`, 28, t.y + 54, 110, 20, { fill: '#FEF3C7', cornerRadius: 10 }, f);
        this.text('CHECK IN AT TABLE', 30, t.y + 58, 106, { fontSize: 8, fontWeight: 800, fill: '#B45309', textAlign: 'center' }, f);
      }

      this.mobileBottomNav(f, 'explore');
    }
  }

  // ==========================================
  // PAGE 2: WEBSITE FRONTEND (4 SCREENS)
  // ==========================================
  buildWebPage() {
    this.setPage('Page 2 - Website Frontend');
    const startX = 60;
    const startY = 60;
    const gapY = 880;

    // --- W01: Landing Discovery Stage ---
    {
      const f = this.frame('W01 - Landing Discovery Stage', startX, startY, 1280, 800, { fill: '#FAF8F5' });

      // Navbar
      this.rect('Web Nav BG', 0, 0, 1280, 72, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 }, f);
      this.image(assets.logo, 60, 16, 40, 40, f, 'Web Logo');
      this.text('CampBuddy', 110, 24, 140, { fontSize: 22, fontWeight: 800, fill: '#FF4D2D' }, f);
      this.text('Schedule', 600, 26, 80, { fontSize: 14, fontWeight: 600, fill: '#0F172A' }, f);
      this.text('Speakers (11)', 700, 26, 100, { fontSize: 14, fontWeight: 600, fill: '#0F172A' }, f);
      this.text('Sponsors (17)', 820, 26, 100, { fontSize: 14, fontWeight: 600, fill: '#0F172A' }, f);
      this.text('CampCard Studio', 940, 26, 130, { fontSize: 14, fontWeight: 600, fill: '#0F172A' }, f);
      this.rect('Tickets Btn', 1100, 18, 120, 36, { fill: '#FF4D2D', cornerRadius: 18 }, f);
      this.text('GET TICKETS', 1100, 28, 120, { fontSize: 11, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);

      // Hero Grid
      this.rect('Pill Date', 60, 110, 280, 28, { fill: '#FEF3C7', cornerRadius: 14 }, f);
      this.text('DECEMBER 12–13, 2026 · DAIC NEW DELHI', 70, 116, 260, { fontSize: 10, fontWeight: 800, fill: '#B45309' }, f);

      this.text('WordCamp Delhi 2026: Dil se Dilli, code se community.', 60, 154, 620, { fontSize: 38, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Join 600+ WordPress creators, agency founders, core contributors, and AI pioneers at Dr. Ambedkar International Centre for Northern India’s flagship WordPress gathering.', 60, 260, 580, { fontSize: 15, fontWeight: 400, fill: '#475569' }, f);

      this.rect('CTA 1', 60, 340, 200, 48, { fill: '#FF4D2D', cornerRadius: 24 }, f);
      this.text('EXPLORE SCHEDULE', 60, 356, 200, { fontSize: 12, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.rect('CTA 2', 280, 340, 200, 48, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1.5, cornerRadius: 24 }, f);
      this.text('BUILD CAMPCARD', 280, 356, 200, { fontSize: 12, fontWeight: 700, fill: '#0F172A', textAlign: 'center' }, f);

      // Hero Skyline Artwork
      this.image(assets.skyline, 680, 130, 540, 260, f, 'Delhi Skyline Artwork');

      // Stats Bar
      this.rect('Stats Bar', 60, 440, 1160, 100, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 20 }, f);
      const stats = [
        { num: '2 Days', label: 'Contributor & Conference', x: 100 },
        { num: '22 Talks', label: 'AI, SEO & Enterprise', x: 400 },
        { num: '11 Keynotes', label: 'Verified Speakers', x: 700 },
        { num: '17 Brands', label: 'Verified Sponsors', x: 980 },
      ];
      for (const st of stats) {
        this.text(st.num, st.x, 462, 200, { fontSize: 32, fontWeight: 800, fill: '#FF4D2D' }, f);
        this.text(st.label, st.x, 504, 220, { fontSize: 12, fontWeight: 600, fill: '#64748B' }, f);
      }

      // Featured Speakers Row
      this.text('FEATURED KEYNOTE SPEAKERS', 60, 580, 300, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);
      const spkCards = [
        { img: assets.pankaj, name: 'Pankaj Kumar', topic: 'Local SEO in AI Era', x: 60 },
        { img: assets.radhika, name: 'Radhika Bhama', topic: 'From Feature to Ecosystem', x: 360 },
        { img: assets.amit, name: 'Amit Kumar Singh', topic: 'Enterprise WordPress Arch.', x: 660 },
        { img: assets.shammi, name: 'Shammi Proothi', topic: 'Winning in AI Search', x: 960 },
      ];
      for (const sc of spkCards) {
        this.rect(`Web Spk ${sc.name}`, sc.x, 610, 260, 140, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
        this.image(sc.img, sc.x + 16, 626, 54, 54, f, sc.name);
        this.text(sc.name, sc.x + 82, 632, 160, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);
        this.text(sc.topic, sc.x + 16, 700, 228, { fontSize: 11, fontWeight: 600, fill: '#EA580C' }, f);
      }
    }

    // --- W02: Verified Sponsors Wall ---
    {
      const f = this.frame('W02 - Sponsors & Steals Wall', startX, startY + gapY, 1280, 800, { fill: '#FAF8F5' });
      this.text('Verified WordCamp Delhi 2026 Sponsors', 60, 60, 600, { fontSize: 30, fontWeight: 800, fill: '#0F172A' }, f);
      this.text('Scraped official logos, booth numbers, and exclusive conference attendee perks.', 60, 102, 600, { fontSize: 14, fontWeight: 400, fill: '#64748B' }, f);

      // Bronze / Platform
      this.text('PLATFORM & BRONZE SPONSORS', 60, 150, 400, { fontSize: 13, fontWeight: 800, fill: '#FF4D2D' }, f);
      const row1 = [
        { img: assets.sponsor_elementor, name: 'Elementor Ltd', perk: '3 Months Elementor One Free', x: 60 },
        { img: assets.sponsor_nexgi, name: 'NexGi Systems', perk: 'Free Stack Architecture Review', x: 360 },
        { img: assets.sponsor_cookieadmin, name: 'CookieAdmin', perk: 'DPDP 2023 Consent Audit', x: 660 },
        { img: assets.sponsor_pankaj_seo, name: 'Pankaj Kumar SEO', perk: 'Agency Masterclass Pass', x: 960 },
      ];
      for (const r of row1) {
        this.rect(`Sponsor ${r.name}`, r.x, 180, 260, 140, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
        this.image(r.img, r.x + 40, 200, 180, 60, f, r.name);
        this.text(r.perk, r.x + 16, 280, 228, { fontSize: 11, fontWeight: 700, fill: '#0F172A', textAlign: 'center' }, f);
      }

      // Startup Sponsors
      this.text('STARTUP & AFTER PARTY SPONSORS', 60, 360, 400, { fontSize: 13, fontWeight: 800, fill: '#D97706' }, f);
      const row2 = [
        { img: assets.sponsor_wpfusion, name: 'WP Fusion', perk: '30% Off CRM Integration', x: 60 },
        { img: assets.sponsor_housewise, name: 'Housewise', perk: 'NRI Property Consultation', x: 360 },
        { img: assets.sponsor_zuptek, name: 'Zuptek Infotech', perk: 'SEO Audit for Startups', x: 660 },
        { img: assets.sponsor_digiliance, name: 'Digiliance', perk: 'Labour Law Compliance Tool', x: 960 },
      ];
      for (const r of row2) {
        this.rect(`Sponsor ${r.name}`, r.x, 390, 260, 140, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1, cornerRadius: 16 }, f);
        this.image(r.img, r.x + 40, 410, 180, 60, f, r.name);
        this.text(r.perk, r.x + 16, 490, 228, { fontSize: 11, fontWeight: 700, fill: '#0F172A', textAlign: 'center' }, f);
      }
    }
  }

  // ==========================================
  // PAGE 3: PROGRESSIVE WEB APP (2 SCREENS)
  // ==========================================
  buildPwaPage() {
    this.setPage('Page 3 - Standalone PWA');
    const startX = 60;
    const startY = 60;
    const gapX = 420;

    // --- P01: Install Hero ---
    {
      const f = this.frame('P01 - Install & Pre-cache', startX, startY, 360, 760, { fill: '#0F172A', cornerRadius: 36 });
      this.mobileStatusBar(f, true);
      this.image(assets.logo, 140, 140, 80, 80, f, 'PWA Logo');
      this.text('Install WordCamp Delhi PWA', 40, 240, 280, { fontSize: 20, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
      this.text('Full offline access to 22 talks & 11 speakers without needing network.', 40, 272, 280, { fontSize: 11, fontWeight: 400, fill: '#94A3B8', textAlign: 'center' }, f);

      this.rect('PWA Cache Box', 30, 340, 300, 120, { fill: '#1E293B', cornerRadius: 18 }, f);
      this.text('Offline Guarantees', 46, 356, 200, { fontSize: 12, fontWeight: 700, fill: '#FFFFFF' }, f);
      this.text('✓ IndexedDB Schedule: 100% Synced', 46, 380, 260, { fontSize: 10, fontWeight: 500, fill: '#10B981' }, f);
      this.text('✓ ServiceWorker Cache: 17 Sponsor Logos Pre-Loaded', 46, 400, 260, { fontSize: 10, fontWeight: 500, fill: '#10B981' }, f);
      this.text('✓ Zero-Latency CampCard NFC & QR Beaming', 46, 420, 260, { fontSize: 10, fontWeight: 500, fill: '#10B981' }, f);

      this.rect('Install PWA Btn', 30, 520, 300, 48, { fill: '#FF4D2D', cornerRadius: 24 }, f);
      this.text('ADD TO HOME SCREEN (PWA)', 30, 536, 300, { fontSize: 12, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
    }

    // --- P02: Standalone Companion Home ---
    {
      const f = this.frame('P02 - Standalone Companion Home', startX + gapX, startY, 360, 760, { fill: '#FFF7ED', cornerRadius: 36 });
      this.mobileStatusBar(f, false);
      this.rect('PWA Offline Pill', 20, 46, 320, 30, { fill: '#0F172A', cornerRadius: 15 }, f);
      this.text('⚡ Standalone PWA Active · 0 KB Latency', 30, 53, 300, { fontSize: 10, fontWeight: 700, fill: '#10B981', textAlign: 'center' }, f);

      // Hero
      this.rect('Hero PWA', 20, 94, 320, 120, { fill: '#1E293B', cornerRadius: 18 }, f);
      this.text('LIVE IN BHIM AUDITORIUM', 36, 110, 200, { fontSize: 10, fontWeight: 700, fill: '#F59E0B' }, f);
      this.text('10 Things to Boost Local SEO', 36, 128, 280, { fontSize: 15, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('Pankaj Kumar · Ends in 12 mins', 36, 154, 250, { fontSize: 11, fontWeight: 500, fill: '#CBD5E1' }, f);

      this.mobileBottomNav(f, 'home');
    }
  }

  // ==========================================
  // PAGE 4: ADMIN OPERATIONS PANEL (2 SCREENS)
  // ==========================================
  buildAdminPage() {
    this.setPage('Page 4 - Admin Panel');
    const startX = 60;
    const startY = 60;

    // --- AP01: Control Center Dashboard ---
    {
      const f = this.frame('AP01 - Control Center Dashboard', startX, startY, 1280, 800, { fill: '#0B1120' });

      // Sidebar
      this.rect('Sidebar BG', 0, 0, 240, 800, { fill: '#0F172A', stroke: '#1E293B', strokeWeight: 1 }, f);
      this.image(assets.logo, 24, 24, 36, 36, f, 'Admin Logo');
      this.text('Admin HQ', 70, 32, 140, { fontSize: 18, fontWeight: 800, fill: '#FFFFFF' }, f);

      const navItems = [
        { label: 'Dashboard', active: true, y: 90 },
        { label: 'Delay Broadcaster', active: false, y: 136 },
        { label: 'Push Dispatcher', active: false, y: 182 },
        { label: 'Media & Scraper Health', active: false, y: 228 },
        { label: 'Attendee Roles', active: false, y: 274 },
        { label: 'Safety Incident Queue', active: false, y: 320 },
      ];
      for (const ni of navItems) {
        if (ni.active) {
          this.rect(`Nav Active ${ni.label}`, 16, ni.y, 208, 36, { fill: '#FF4D2D', cornerRadius: 8 }, f);
        }
        this.text(ni.label, 30, ni.y + 10, 180, { fontSize: 12, fontWeight: 700, fill: ni.active ? '#FFFFFF' : '#94A3B8' }, f);
      }

      // Main Admin Body
      this.text('WordCamp Delhi 2026 · Operations Desk', 280, 40, 500, { fontSize: 24, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('Real-time feed sync, live delay cascades, and emergency dispatch.', 280, 72, 500, { fontSize: 13, fontWeight: 400, fill: '#94A3B8' }, f);

      // KPI Grid
      const kpis = [
        { title: 'Checked-in Attendees', val: '584', sub: '↑ 92% capacity', x: 280 },
        { title: 'Scheduled Sessions', val: '22', sub: '3 Active in Parallel', x: 520 },
        { title: 'Verified Sponsors', val: '17', sub: '100% Media Health', x: 760 },
        { title: 'Active Incidents', val: '0', sub: 'Zero Escalations', x: 1000 },
      ];
      for (const k of kpis) {
        this.rect(`KPI BG ${k.title}`, k.x, 120, 220, 110, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 16 }, f);
        this.text(k.title, k.x + 16, 136, 190, { fontSize: 11, fontWeight: 600, fill: '#94A3B8' }, f);
        this.text(k.val, k.x + 16, 158, 100, { fontSize: 28, fontWeight: 800, fill: '#FFFFFF' }, f);
        this.text(k.sub, k.x + 16, 198, 190, { fontSize: 10, fontWeight: 700, fill: '#10B981' }, f);
      }

      // Live Delay Broadcaster Widget
      this.rect('Delay Widget', 280, 260, 940, 160, { fill: '#1E293B', stroke: '#334155', strokeWeight: 1, cornerRadius: 18 }, f);
      this.text('Live Schedule Delay Broadcaster', 304, 280, 400, { fontSize: 16, fontWeight: 800, fill: '#FFFFFF' }, f);
      this.text('Select track and adjust offset; cascade times are automatically calculated across all attendee mobile devices.', 304, 304, 600, { fontSize: 11, fontWeight: 400, fill: '#94A3B8' }, f);

      this.rect('Track Sel', 304, 340, 220, 44, { fill: '#0F172A', stroke: '#334155', strokeWeight: 1, cornerRadius: 8 }, f);
      this.text('Track 1: BHIM Auditorium', 320, 354, 180, { fontSize: 12, fontWeight: 600, fill: '#FFFFFF' }, f);

      this.rect('Slider BG', 550, 358, 400, 8, { fill: '#0F172A', cornerRadius: 4 }, f);
      this.rect('Slider Fill', 550, 358, 160, 8, { fill: '#FF4D2D', cornerRadius: 4 }, f);
      this.text('Current Delay: +15 mins (Affects 5 talks)', 550, 340, 300, { fontSize: 11, fontWeight: 700, fill: '#F59E0B' }, f);

      this.rect('Broadcast Btn', 980, 340, 210, 44, { fill: '#FF4D2D', cornerRadius: 8 }, f);
      this.text('BROADCAST DELAY', 980, 354, 210, { fontSize: 11, fontWeight: 800, fill: '#FFFFFF', textAlign: 'center' }, f);
    }
  }

  generateScript() {
    this.buildApkPage();
    this.buildWebPage();
    this.buildPwaPage();
    this.buildAdminPage();
    return this.commands;
  }
}

const builder = new FigCraftMultiPageBuilder();
const commands = builder.generateScript();

const scriptPath = path.resolve('scratch/figcraft_multipage_script.json');
fs.writeFileSync(scriptPath, JSON.stringify(commands, null, 2), 'utf8');
console.log(`Generated ${commands.length} FigCraft commands across 4 pages.`);
console.log(`Saved to ${scriptPath}`);
