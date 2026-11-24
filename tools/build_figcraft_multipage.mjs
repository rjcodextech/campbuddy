import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const rootDir = path.resolve(__dirname, '..');

class FigCraftMultiPageBuilder {
  constructor() {
    this.commands = [];
    this.nextId = 2;
    this.isFirstPage = true;
    this.pages = [];
    this.currentScreenFrames = [];
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
    if (!parent) {
      this.currentScreenFrames.push({ id, name, x, y, width, height });
    }
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

  text(text, x, y, props = {}, parent = null, name = null) {
    const id = this.nextId++;
    const params = { text, x, y, props };
    if (parent) params.parent = parent;
    if (name) params.name = name;
    this.commands.push({ command: 'text.create', params });
    return id;
  }

  line(name, x, y, width, props = {}, parent = null) {
    const id = this.nextId++;
    const params = { name, x, y, width, props };
    if (parent) params.parent = parent;
    this.commands.push({ command: 'shape.line', params });
    return id;
  }

  mobileStatusBar(parent, dark = false) {
    const color = dark ? '#FFFFFF' : '#0F172A';
    this.text('09:41', 24, 14, { fontSize: 13, fontWeight: 700, fill: color }, parent, 'Time');
    this.text('5G', 320, 14, { fontSize: 11, fontWeight: 600, fill: color }, parent, 'Network');
    this.rect('Battery', 345, 15, 22, 11, { fill: color, cornerRadius: 3 }, parent);
    this.rect('Battery Level', 347, 17, 14, 7, { fill: dark ? '#0F172A' : '#FFFFFF', cornerRadius: 1.5 }, parent);
  }

  mobileBottomNav(parent, activeTab = 'home') {
    const y = 780;
    this.rect('Bottom Nav BG', 0, y, 390, 64, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 }, parent);
    const tabs = [
      { key: 'home', label: 'Home', icon: '⌂', x: 20 },
      { key: 'my-day', label: 'My Day', icon: '📅', x: 98 },
      { key: 'explore', label: 'Explore', icon: '👥', x: 176 },
      { key: 'quest', label: 'Quests', icon: '🎯', x: 254 },
      { key: 'camp-card', label: 'CampCard', icon: '🪪', x: 332 },
    ];
    for (const tab of tabs) {
      const isActive = tab.key === activeTab;
      if (isActive) {
        this.rect('Active Tab Pill', tab.x - 14, y + 8, 48, 26, { fill: '#FFEDD5', cornerRadius: 13 }, parent);
      }
      this.text(tab.icon, tab.x, y + 12, { fontSize: 13, textAlign: 'center', fill: isActive ? '#D33417' : '#64748B' }, parent);
      this.text(tab.label, tab.x - 12, y + 38, { fontSize: 10, fontWeight: isActive ? 700 : 500, fill: isActive ? '#D33417' : '#64748B' }, parent);
    }
  }

  webTopNav(parent, activeTab = 'home') {
    this.rect('Top Nav Bar', 0, 0, 1280, 72, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 }, parent);
    this.text('CampBuddy', 48, 22, { fontSize: 22, fontWeight: 800, fill: '#FF4D2D', fontFamily: 'Poppins' }, parent, 'Brand');
    this.rect('WordCamp Pill', 190, 24, 190, 26, { fill: '#FFF7ED', stroke: '#FDBA74', cornerRadius: 13, strokeWeight: 1 }, parent);
    this.text('📍 WordCamp Delhi 2026', 204, 30, { fontSize: 11, fontWeight: 700, fill: '#D97706' }, parent);

    const navItems = [
      { key: 'home', label: 'Home Stage', x: 420 },
      { key: 'schedule', label: 'Schedule Explorer', x: 540 },
      { key: 'my-day', label: 'My Day Planner', x: 690 },
      { key: 'camp-card', label: 'CampCard Studio', x: 830 },
      { key: 'explore', label: 'Attendees & Sponsors', x: 975 },
    ];

    for (const item of navItems) {
      const isActive = item.key === activeTab;
      this.text(item.label, item.x, 26, { fontSize: 13, fontWeight: isActive ? 700 : 500, fill: isActive ? '#FF4D2D' : '#334155' }, parent);
      if (isActive) {
        this.rect('Nav Indicator', item.x, 68, 64, 4, { fill: '#FF4D2D', cornerRadius: 2 }, parent);
      }
    }

    this.rect('Offline Badge', 1140, 20, 100, 32, { fill: '#ECFDF5', stroke: '#A7F3D0', cornerRadius: 16, strokeWeight: 1 }, parent);
    this.text('● Offline Ready', 1152, 28, { fontSize: 10, fontWeight: 700, fill: '#047857' }, parent);
  }
}

const b = new FigCraftMultiPageBuilder();

// ============================================================================
// PAGE 1: Web UI (11 Screens in complete user journey flow @ 1280x800)
// ============================================================================
b.setPage('Web UI');

// Screen W01: Landing Discovery Stage
{
  const f = b.frame('W01 - Landing Discovery Stage', 40, 100, 1280, 800, { fill: '#FFF7ED', clip: true });
  b.webTopNav(f, 'home');

  b.rect('Hero Stage', 48, 96, 1184, 380, { fill: '#1E293B', cornerRadius: 28 }, f);
  b.text('LIVE CONFERENCE DASHBOARD · DAY 1', 88, 136, { fontSize: 12, fontWeight: 800, fill: '#F59E0B', letterSpacing: 1.2 }, f);
  b.text('WordCamp Delhi 2026', 88, 164, { fontSize: 38, fontWeight: 800, fill: '#FFFFFF', fontFamily: 'Baloo 2' }, f);
  b.text('December 12–13, 2026 · NDMC Convention Centre, Sansad Marg, New Delhi', 88, 220, { fontSize: 16, fill: '#94A3B8' }, f);

  b.rect('Clock Badge', 88, 260, 240, 48, { fill: '#0F172A', stroke: '#334155', cornerRadius: 24, strokeWeight: 1 }, f);
  b.ellipse('Green Pulse', 106, 276, 16, 16, { fill: '#10B981' }, f);
  b.text('LIVE: 10:14 AM IST', 132, 276, { fontSize: 14, fontWeight: 800, fill: '#FFFFFF' }, f);

  b.rect('Open Schedule Btn', 348, 260, 180, 48, { fill: '#FF4D2D', cornerRadius: 12 }, f);
  b.text('Explore Schedule  →', 374, 276, { fontSize: 14, fontWeight: 700, fill: '#FFFFFF' }, f);

  b.rect('CampCard Web Btn', 544, 260, 180, 48, { fill: 'rgba(255,255,255,0.1)', stroke: '#64748B', cornerRadius: 12, strokeWeight: 1 }, f);
  b.text('Create CampCard 🪪', 572, 276, { fontSize: 14, fontWeight: 700, fill: '#FFFFFF' }, f);

  // Right Side Live Happening Box
  b.rect('Happening Box', 780, 136, 412, 280, { fill: '#0F172A', cornerRadius: 20, stroke: '#334155', strokeWeight: 1 }, f);
  b.text('⚡ HAPPENING NOW ACROSS TRACKS', 808, 160, { fontSize: 11, fontWeight: 800, fill: '#F59E0B' }, f);
  b.text('Track 1 · Hall A', 808, 192, { fontSize: 12, fontWeight: 700, fill: '#38BDF8' }, f);
  b.text('Scaling Headless WordPress & SEO', 808, 212, { fontSize: 16, fontWeight: 800, fill: '#FFFFFF' }, f);
  b.text('Pankaj Kumar (SEO Lead) · 10:30 AM – 11:15 AM', 808, 236, { fontSize: 12, fill: '#94A3B8' }, f);
  b.line('Box Div', 808, 262, 356, { stroke: '#1E293B', strokeWeight: 1 }, f);
  b.text('Track 2 · Hall B', 808, 282, { fontSize: 12, fontWeight: 700, fill: '#F472B6' }, f);
  b.text('Designing Accessible Enterprise Themes', 808, 302, { fontSize: 16, fontWeight: 800, fill: '#FFFFFF' }, f);
  b.text('Lena Lekkou (UI/UX Architect) · 11:30 AM – 12:15 PM', 808, 326, { fontSize: 12, fill: '#94A3B8' }, f);

  const features = [
    { title: '100% Offline Reliable', desc: 'Pre-caches complete sessions, speaker bios, and sponsor deals. Works even if venue Wi-Fi fails.', icon: '⚡', x: 48 },
    { title: 'Zero App Store Barrier', desc: 'Open instantly on desktop, laptop, Android or iPhone. PWA standalone installation in 1 click.', icon: '📱', x: 450 },
    { title: 'P2P Privacy First', desc: 'No accounts, zero tracker cookies, DPDP Act 2023 certified. Your personal notes stay on your device.', icon: '🛡', x: 852 },
  ];
  for (const feat of features) {
    b.rect('Feat Box', feat.x, 506, 380, 180, { fill: '#FFFFFF', cornerRadius: 20, stroke: '#E2E8F0', strokeWeight: 1 }, f);
    b.rect('Feat Icon Box', feat.x + 24, 530, 48, 48, { fill: '#FFEDD5', cornerRadius: 24 }, f);
    b.text(feat.icon, feat.x + 36, 542, { fontSize: 20 }, f);
    b.text(feat.title, feat.x + 24, 596, { fontSize: 16, fontWeight: 800, fill: '#0F172A' }, f);
    b.text(feat.desc, feat.x + 24, 624, { fontSize: 12, fill: '#64748B' }, f);
  }
}

// Screen W02: Global Event Directory
{
  const f = b.frame('W02 - Event Directory & Hub', 1360, 100, 1280, 800, { fill: '#FFF7ED', clip: true });
  b.webTopNav(f, 'home');

  b.text('Upcoming & Active WordCamps', 48, 100, { fontSize: 28, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Live conference companions synced with official schedules and attendee directories', 48, 134, { fontSize: 14, fill: '#64748B' }, f);

  const countries = ['All Events (10)', 'India (3)', 'Greece (1)', 'Kenya (1)', 'Indonesia (1)', 'Nepal (1)', 'Switzerland (1)'];
  countries.forEach((c, i) => {
    const x = 48 + i * 150;
    const active = i === 0;
    b.rect('C Pill', x, 168, 140, 36, { fill: active ? '#FF4D2D' : '#FFFFFF', stroke: active ? '#FF4D2D' : '#E2E8F0', cornerRadius: 18, strokeWeight: 1 }, f);
    b.text(c, x + 16, 178, { fontSize: 12, fontWeight: 700, fill: active ? '#FFFFFF' : '#334155' }, f);
  });

  const dirEvents = [
    { name: 'WordCamp Delhi 2026', loc: 'NDMC Convention Centre, New Delhi', date: '12–13 DEC 2026', status: 'ON NOW', talks: '22 Talks', spk: '11 Speakers', spon: '17 Sponsors' },
    { name: 'WordCamp Athens 2026', loc: 'Megaron Athens International Hall', date: '11–12 APR 2026', status: 'UPCOMING', talks: '34 Talks', spk: '19 Speakers', spon: '30 Sponsors' },
    { name: 'WordCamp Nairobi 2026', loc: 'The Edge Convention Centre, Nairobi', date: '28 NOV 2026', status: 'UPCOMING', talks: '18 Talks', spk: '18 Speakers', spon: '6 Sponsors' },
    { name: 'WordCamp Bandung 2026', loc: 'Trans Convention Centre, Bandung', date: '15 DEC 2026', status: 'UPCOMING', talks: '10 Talks', spk: '10 Speakers', spon: '13 Sponsors' },
    { name: 'WordCamp Nepal 2027', loc: 'Pokhara Event Arena, Nepal', date: '14 JAN 2027', status: 'UPCOMING', talks: '16 Talks', spk: '14 Speakers', spon: '12 Sponsors' },
    { name: 'WordCamp Mumbai 2027', loc: 'Bandra Kurla Complex, Mumbai', date: '06 FEB 2027', status: 'UPCOMING', talks: '28 Talks', spk: '20 Speakers', spon: '22 Sponsors' },
  ];

  dirEvents.forEach((ev, i) => {
    const col = i % 3;
    const row = Math.floor(i / 3);
    const x = 48 + col * 402;
    const y = 224 + row * 240;
    b.rect('Event Tile', x, y, 380, 220, { fill: '#FFFFFF', cornerRadius: 20, stroke: '#E2E8F0', strokeWeight: 1 }, f);
    b.rect('Ev Logo', x + 20, y + 20, 48, 48, { fill: '#1E293B', cornerRadius: 24 }, f);
    b.text(ev.name.charAt(9), x + 34, y + 32, { fontSize: 20, fontWeight: 800, fill: '#FFFFFF' }, f);
    b.text(ev.status + ' · ' + ev.date, x + 80, y + 22, { fontSize: 10, fontWeight: 800, fill: ev.status === 'ON NOW' ? '#10B981' : '#D97706' }, f);
    b.text(ev.name, x + 80, y + 38, { fontSize: 16, fontWeight: 800, fill: '#0F172A' }, f);
    b.text('📍 ' + ev.loc, x + 80, y + 58, { fontSize: 11, fill: '#64748B' }, f);
    b.line('Tile Line', x + 20, y + 88, 340, { stroke: '#F1F5F9', strokeWeight: 1 }, f);
    b.text(`${ev.talks} · ${ev.spk} · ${ev.spon}`, x + 20, y + 104, { fontSize: 12, fontWeight: 600, fill: '#334155' }, f);
    b.rect('Offline Badge Tiny', x + 20, y + 130, 110, 24, { fill: '#ECFDF5', stroke: '#A7F3D0', cornerRadius: 12, strokeWeight: 1 }, f);
    b.text('✔ Offline Cached', x + 28, y + 135, { fontSize: 10, fontWeight: 700, fill: '#047857' }, f);
    b.rect('Launch Btn', x + 20, y + 166, 340, 38, { fill: '#FF4D2D', cornerRadius: 10 }, f);
    b.text('Launch Companion Dashboard  →', x + 72, y + 177, { fontSize: 12, fontWeight: 700, fill: '#FFFFFF' }, f);
  });
}

// Screen W03: Event Home Companion Dashboard
{
  const f = b.frame('W03 - Event Companion Home', 2680, 100, 1280, 800, { fill: '#FFF7ED', clip: true });
  b.webTopNav(f, 'home');

  // Hero Card
  b.rect('Hero Stage Mini', 48, 96, 1184, 200, { fill: '#1E293B', cornerRadius: 20 }, f);
  b.text('WordCamp Delhi 2026 · Day 1 of 2', 76, 120, { fontSize: 13, fontWeight: 700, fill: '#F59E0B' }, f);
  b.text('Live at NDMC Convention Centre, Sansad Marg', 76, 142, { fontSize: 26, fontWeight: 800, fill: '#FFFFFF' }, f);
  b.text('10:14 AM IST · Track 1 & Track 2 Active · 22 Sessions Scheduled', 76, 180, { fontSize: 14, fill: '#94A3B8' }, f);

  // 2x3 Action Grid
  const webHubs = [
    { title: 'My Day Planner', sub: '4 saved talks · 0 conflicts', icon: '📅', x: 48, y: 320 },
    { title: 'Schedule Explorer', sub: '22 sessions · 2 halls', icon: '🎤', x: 448, y: 320 },
    { title: 'Interactive CampCard', sub: 'P2P QR exchange badge', icon: '🪪', x: 848, y: 320 },
    { title: 'Attendees Directory', sub: '82 attendees · gravatar sync', icon: '👥', x: 48, y: 440 },
    { title: 'Contributor Quests', sub: '4 team tables · handbook', icon: '🎯', x: 448, y: 440 },
    { title: 'Sponsors & Swag', sub: '17 verified sponsors · deals', icon: '🎁', x: 848, y: 440 },
  ];

  for (const h of webHubs) {
    b.rect('Hub Tile', h.x, h.y, 384, 100, { fill: '#FFFFFF', cornerRadius: 16, stroke: '#E2E8F0', strokeWeight: 1 }, f);
    b.rect('Icon Box', h.x + 20, h.y + 20, 48, 48, { fill: '#FFEDD5', cornerRadius: 24 }, f);
    b.text(h.icon, h.x + 32, h.y + 32, { fontSize: 20 }, f);
    b.text(h.title, h.x + 84, h.y + 28, { fontSize: 16, fontWeight: 800, fill: '#0F172A' }, f);
    b.text(h.sub, h.x + 84, h.y + 54, { fontSize: 12, fill: '#64748B' }, f);
  }

  // Sticky SOS Incident Banner
  b.rect('SOS Bar Web', 48, 560, 1184, 60, { fill: '#FEF2F2', stroke: '#FECACA', cornerRadius: 14, strokeWeight: 1.5 }, f);
  b.text('🛟  Need Help or Incident Support? Organizer desk is located at Central Registration Foyer.', 72, 582, { fontSize: 13, fontWeight: 700, fill: '#991B1B' }, f);
  b.rect('SOS Btn', 1040, 574, 160, 32, { fill: '#EF4444', cornerRadius: 8 }, f);
  b.text('Contact Helpdesk', 1068, 582, { fontSize: 11, fontWeight: 700, fill: '#FFFFFF' }, f);

  // Legal / DPDP Footer
  b.line('L Div', 48, 650, 1184, { stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.text('DPDP Act 2023 Compliant · Zero Cloud Accounts · Organizer Staff Console', 48, 680, { fontSize: 12, fill: '#94A3B8' }, f);
}

// Screen W04: Multi-Track Schedule Explorer
{
  const f = b.frame('W04 - Multi-Track Schedule', 4000, 100, 1280, 800, { fill: '#FFF7ED', clip: true });
  b.webTopNav(f, 'schedule');

  b.text('Multi-Track Program & Session Explorer', 48, 100, { fontSize: 26, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Live timeline with live delay compensation, track filters, and speaker profiles', 48, 132, { fontSize: 13, fill: '#64748B' }, f);

  b.rect('Track 1 Tab', 48, 164, 180, 40, { fill: '#FF4D2D', cornerRadius: 10 }, f);
  b.text('Track 1 (Hall A)', 86, 176, { fontSize: 13, fontWeight: 700, fill: '#FFFFFF' }, f);
  b.rect('Track 2 Tab', 240, 164, 180, 40, { fill: '#FFFFFF', stroke: '#CBD5E1', cornerRadius: 10, strokeWeight: 1 }, f);
  b.text('Track 2 (Hall B)', 278, 176, { fontSize: 13, fontWeight: 600, fill: '#334155' }, f);

  // Track 1 Box
  b.rect('Track 1 Container', 48, 224, 580, 500, { fill: '#FFFFFF', cornerRadius: 20, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.text('HALL A · TECHNICAL & PERFORMANCE', 68, 246, { fontSize: 12, fontWeight: 800, fill: '#FF4D2D' }, f);

  b.rect('Session A1', 68, 274, 540, 100, { fill: '#FFF7ED', stroke: '#FF4D2D', cornerRadius: 14, strokeWeight: 1.5 }, f);
  b.text('10:30 AM – 11:15 AM · 45 mins', 84, 288, { fontSize: 11, fontWeight: 700, fill: '#D97706' }, f);
  b.text('Scaling Headless WordPress & SEO', 84, 308, { fontSize: 15, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Pankaj Kumar (Pankaj Kumar SEO) · Technical Track', 84, 332, { fontSize: 12, fill: '#64748B' }, f);

  b.rect('Session A2', 68, 390, 540, 100, { fill: '#FFFFFF', stroke: '#E2E8F0', cornerRadius: 14, strokeWeight: 1 }, f);
  b.text('11:30 AM – 12:15 PM · 45 mins', 84, 404, { fontSize: 11, fontWeight: 600, fill: '#64748B' }, f);
  b.text('Full-Site Editing & Custom Block Governance', 84, 424, { fontSize: 15, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Patrick Lumumba (Core Contributor) · Architecture Track', 84, 448, { fontSize: 12, fill: '#64748B' }, f);

  // Track 2 Box
  b.rect('Track 2 Container', 652, 224, 580, 500, { fill: '#FFFFFF', cornerRadius: 20, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.text('HALL B · DESIGN, PRODUCT & BUSINESS', 672, 246, { fontSize: 12, fontWeight: 800, fill: '#0284C7' }, f);

  b.rect('Session B1', 672, 274, 540, 100, { fill: '#FFFFFF', stroke: '#E2E8F0', cornerRadius: 14, strokeWeight: 1 }, f);
  b.text('10:30 AM – 11:15 AM · 45 mins', 688, 288, { fontSize: 11, fontWeight: 600, fill: '#64748B' }, f);
  b.text('Building Accessible Community Experiences', 688, 308, { fontSize: 15, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Lena Lekkou (UI/UX Architect) · Design Track', 688, 332, { fontSize: 12, fill: '#64748B' }, f);

  b.rect('Session B2', 672, 390, 540, 100, { fill: '#F0F9FF', stroke: '#0284C7', cornerRadius: 14, strokeWeight: 1.5 }, f);
  b.text('11:30 AM – 12:15 PM · 45 mins', 688, 404, { fontSize: 11, fontWeight: 700, fill: '#0284C7' }, f);
  b.text('Monetization & Agency Growth Strategies', 688, 424, { fontSize: 15, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Chandra Alif Irawan (Agency Founder) · Business Track', 688, 448, { fontSize: 12, fill: '#64748B' }, f);
}

// Screen W05: My Day & Conflict Resolution Hub
{
  const f = b.frame('W05 - My Day & Conflict Hub', 5320, 100, 1280, 800, { fill: '#FFF7ED', clip: true });
  b.webTopNav(f, 'my-day');

  b.text('My Day Personal Schedule & Conflict Resolver', 48, 100, { fontSize: 26, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Automated conflict warnings, schedule health metrics, and local reminders', 48, 132, { fontSize: 13, fill: '#64748B' }, f);

  // Health Stats Card
  b.rect('Health Card', 48, 168, 1184, 70, { fill: '#FEF3C7', stroke: '#FDE68A', cornerRadius: 16, strokeWeight: 1 }, f);
  b.text('⚠️ SCHEDULE CONFLICT DETECTED AT 11:30 AM', 72, 188, { fontSize: 13, fontWeight: 800, fill: '#92400E' }, f);
  b.text('You have starred concurrent sessions in Hall A and Hall B. Please resolve preference below.', 72, 210, { fontSize: 11, fill: '#78350F' }, f);

  // Side-by-side Conflict Resolution Cards
  b.rect('Talk A Resolve', 48, 260, 580, 200, { fill: '#FFFFFF', stroke: '#EF4444', cornerRadius: 20, strokeWeight: 2 }, f);
  b.text('OPTION 1 · HALL A (11:30 AM)', 72, 284, { fontSize: 11, fontWeight: 800, fill: '#D33417' }, f);
  b.text('Full-Site Editing & Custom Block Governance', 72, 308, { fontSize: 18, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Patrick Lumumba · 45 mins · Track 1', 72, 336, { fontSize: 13, fill: '#64748B' }, f);
  b.rect('Keep A', 72, 390, 140, 42, { fill: '#FF4D2D', cornerRadius: 10 }, f);
  b.text('KEEP THIS TALK', 96, 403, { fontSize: 12, fontWeight: 800, fill: '#FFFFFF' }, f);

  b.rect('Talk B Resolve', 652, 260, 580, 200, { fill: '#FFFFFF', stroke: '#CBD5E1', cornerRadius: 20, strokeWeight: 1 }, f);
  b.text('OPTION 2 · HALL B (11:30 AM)', 676, 284, { fontSize: 11, fontWeight: 800, fill: '#0284C7' }, f);
  b.text('Monetization & Agency Growth Strategies', 676, 308, { fontSize: 18, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Chandra Alif Irawan · 45 mins · Track 2', 676, 336, { fontSize: 13, fill: '#64748B' }, f);
  b.rect('Remove B', 676, 390, 140, 42, { fill: '#F1F5F9', cornerRadius: 10 }, f);
  b.text('REMOVE THIS TALK', 696, 403, { fontSize: 12, fontWeight: 700, fill: '#64748B' }, f);
}

// Screen W06: CampCard Studio & QR Swap
{
  const f = b.frame('W06 - CampCard Badge Studio', 6640, 100, 1280, 800, { fill: '#FFF7ED', clip: true });
  b.webTopNav(f, 'camp-card');

  b.text('CampCard Badge Studio & Peer P2P Swap', 48, 100, { fontSize: 26, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Offline attendee badge with customizable fields, instant QR generation, and device transfer', 48, 132, { fontSize: 13, fill: '#64748B' }, f);

  // Badge Visual Card Preview
  b.rect('Badge Canvas', 48, 170, 420, 520, { fill: '#FFFFFF', cornerRadius: 24, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.rect('Badge Top', 48, 170, 420, 70, { fill: '#FF4D2D', cornerRadius: 24 }, f);
  b.rect('Top Fix', 48, 210, 420, 30, { fill: '#FF4D2D' }, f);
  b.text('WORDCAMP DELHI 2026 · ATTENDEE BADGE', 72, 192, { fontSize: 11, fontWeight: 800, fill: '#FFFFFF' }, f);

  b.text('Sunil Kumar Sharma', 72, 260, { fontSize: 22, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Full Stack WordPress Developer · WPSimplified', 72, 288, { fontSize: 13, fontWeight: 600, fill: '#FF4D2D' }, f);

  // QR Code Area
  b.rect('QR Area', 138, 330, 240, 240, { fill: '#F8FAFC', stroke: '#CBD5E1', cornerRadius: 16 }, f);
  b.rect('QRP1', 160, 352, 54, 54, { fill: '#0F172A', cornerRadius: 4 }, f);
  b.rect('QRP2', 302, 352, 54, 54, { fill: '#0F172A', cornerRadius: 4 }, f);
  b.rect('QRP3', 160, 494, 54, 54, { fill: '#0F172A', cornerRadius: 4 }, f);
  b.rect('QRD1', 230, 352, 54, 54, { fill: '#0F172A', cornerRadius: 3 }, f);
  b.rect('QRD2', 230, 424, 126, 54, { fill: '#0F172A', cornerRadius: 3 }, f);
  b.rect('QRD3', 160, 424, 54, 54, { fill: '#0F172A', cornerRadius: 3 }, f);
  b.rect('QRD4', 230, 494, 126, 54, { fill: '#0F172A', cornerRadius: 3 }, f);

  b.text('Point phone camera to scan & swap contact info', 94, 590, { fontSize: 11, fill: '#64748B' }, f);

  // Right Side Form & Transfer Box
  b.rect('Edit Box', 510, 170, 722, 520, { fill: '#FFFFFF', cornerRadius: 24, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.text('Customize Your CampCard Fields', 540, 204, { fontSize: 18, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Changes update the QR code immediately without sending data to any external server.', 540, 230, { fontSize: 12, fill: '#64748B' }, f);

  b.text('Full Name:', 540, 270, { fontSize: 12, fontWeight: 700, fill: '#334155' }, f);
  b.rect('Name Input', 540, 290, 660, 40, { fill: '#F8FAFC', stroke: '#CBD5E1', cornerRadius: 8 }, f);
  b.text('Sunil Kumar Sharma', 556, 302, { fontSize: 13, fill: '#0F172A' }, f);

  b.text('Job Title / Role:', 540, 344, { fontSize: 12, fontWeight: 700, fill: '#334155' }, f);
  b.rect('Role Input', 540, 364, 660, 40, { fill: '#F8FAFC', stroke: '#CBD5E1', cornerRadius: 8 }, f);
  b.text('Full Stack WordPress Developer', 556, 376, { fontSize: 13, fill: '#0F172A' }, f);

  b.text('Desktop to Mobile Transfer (6-Digit Sync):', 540, 420, { fontSize: 12, fontWeight: 700, fill: '#334155' }, f);
  b.rect('Transfer Box', 540, 440, 660, 80, { fill: '#FFF7ED', stroke: '#FDBA74', cornerRadius: 12 }, f);
  b.text('Link code: 482 109', 560, 460, { fontSize: 16, fontWeight: 800, fill: '#D97706' }, f);
  b.text('Type this 6-digit code in the CampBuddy Mobile App to sync your schedule & badge.', 560, 488, { fontSize: 12, fill: '#9A3412' }, f);

  b.rect('Export VCF', 540, 540, 180, 44, { fill: '#FF4D2D', cornerRadius: 10 }, f);
  b.text('Export vCard (.vcf)', 570, 554, { fontSize: 12, fontWeight: 700, fill: '#FFFFFF' }, f);
  b.rect('Apple Pass', 740, 540, 180, 44, { fill: '#0F172A', cornerRadius: 10 }, f);
  b.text('Apple / Google Pass', 770, 554, { fontSize: 12, fontWeight: 700, fill: '#FFFFFF' }, f);
}

// Screen W07: Attendees & Speaker Directory
{
  const f = b.frame('W07 - Attendees & Roles Directory', 40, 1000, 1280, 800, { fill: '#FFF7ED', clip: true });
  b.webTopNav(f, 'explore');

  b.text('Community Attendees & Speakers Directory', 48, 100, { fontSize: 26, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Search attendees, connect with speakers, and find people with shared interests', 48, 132, { fontSize: 13, fill: '#64748B' }, f);

  // Search Bar
  b.rect('Search Bar', 48, 164, 1184, 46, { fill: '#FFFFFF', stroke: '#CBD5E1', cornerRadius: 12 }, f);
  b.text('🔍 Search by name, topic (e.g. SEO, Gutenberg, Headless), or role...', 72, 178, { fontSize: 13, fill: '#94A3B8' }, f);

  // Filter Chips
  const chips = ['All (82)', 'Speakers (11)', 'Organizers (14)', 'Table Leads (8)', 'Volunteers (12)'];
  chips.forEach((c, i) => {
    const x = 48 + i * 140;
    const active = i === 0;
    b.rect('Role Chip', x, 226, 126, 32, { fill: active ? '#FF4D2D' : '#FFFFFF', stroke: active ? '#FF4D2D' : '#E2E8F0', cornerRadius: 16 }, f);
    b.text(c, x + 16, 234, { fontSize: 11, fontWeight: 700, fill: active ? '#FFFFFF' : '#334155' }, f);
  });

  // Attendee Cards Grid (2 rows x 3 columns)
  const rosterPeople = [
    { name: 'Pankaj Kumar', role: 'Speaker', org: 'Pankaj Kumar SEO', topics: 'Technical SEO · Headless WP', initial: 'PK' },
    { name: 'Lena Lekkou', role: 'Speaker', org: 'UI/UX Community Lead', topics: 'Design Systems · Accessibility', initial: 'LL' },
    { name: 'Patrick Lumumba', role: 'Speaker', org: 'Nairobi Community', topics: 'Full-Site Editing · Blocks', initial: 'PL' },
    { name: 'Chandra Alif Irawan', role: 'Speaker', org: 'Agency Founder', topics: 'Agency Growth · Business', initial: 'CA' },
    { name: 'Rahul Verma', role: 'Organizer', org: 'WordCamp Delhi Team', topics: 'Contributor Day · Polyglots', initial: 'RV' },
    { name: 'Sunil Kumar Sharma', role: 'Attendee', org: 'WPSimplified', topics: 'Core Contributor · Full Stack', initial: 'SS' },
  ];

  rosterPeople.forEach((p, i) => {
    const col = i % 3;
    const row = Math.floor(i / 3);
    const x = 48 + col * 402;
    const y = 276 + row * 210;

    b.rect('Person Card', x, y, 380, 190, { fill: '#FFFFFF', cornerRadius: 18, stroke: '#E2E8F0', strokeWeight: 1 }, f);
    b.rect('Avatar Box', x + 20, y + 20, 52, 52, { fill: '#1E293B', cornerRadius: 26 }, f);
    b.text(p.initial, x + 34, y + 34, { fontSize: 18, fontWeight: 800, fill: '#FFFFFF' }, f);

    b.rect('Role Badge', x + 84, y + 20, 90, 22, { fill: '#FFEDD5', cornerRadius: 11 }, f);
    b.text(p.role.toUpperCase(), x + 96, y + 24, { fontSize: 9, fontWeight: 800, fill: '#D33417' }, f);

    b.text(p.name, x + 84, y + 48, { fontSize: 16, fontWeight: 800, fill: '#0F172A' }, f);
    b.text(p.org, x + 84, y + 70, { fontSize: 12, fill: '#64748B' }, f);

    b.line('P Line', x + 20, y + 96, 340, { stroke: '#F1F5F9', strokeWeight: 1 }, f);
    b.text('Interests: ' + p.topics, x + 20, y + 112, { fontSize: 11, fontWeight: 600, fill: '#059669' }, f);

    b.rect('Wave Action Btn', x + 20, y + 138, 160, 34, { fill: '#FFEDD5', stroke: '#FDBA74', cornerRadius: 8, strokeWeight: 1 }, f);
    b.text('Send Wave 👋', x + 56, y + 148, { fontSize: 11, fontWeight: 700, fill: '#D33417' }, f);

    b.rect('CampCard View Btn', x + 200, y + 138, 160, 34, { fill: '#F1F5F9', cornerRadius: 8 }, f);
    b.text('View Profile 🪪', x + 238, y + 148, { fontSize: 11, fontWeight: 700, fill: '#334155' }, f);
  });
}

// Screen W08: Sponsors Wall & Exclusive Community Deals
{
  const f = b.frame('W08 - Sponsors & Steals Wall', 1360, 1000, 1280, 800, { fill: '#FFF7ED', clip: true });
  b.webTopNav(f, 'explore');

  b.text('Event Sponsors & Exclusive Community Steals', 48, 100, { fontSize: 26, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('17 Verified Sponsors for WordCamp Delhi 2026 with high-resolution brand assets', 48, 132, { fontSize: 13, fill: '#64748B' }, f);

  b.text('TERA & GIGA SPONSORS', 48, 172, { fontSize: 12, fontWeight: 800, fill: '#D97706', letterSpacing: 1.2 }, f);

  const topSponsors = [
    { name: 'Elementor Ltd', tier: 'Tera Sponsor', deal: '50% Off Studio Suite for attendees', site: 'elementor.com', x: 48 },
    { name: 'WPFusion', tier: 'Giga Sponsor', deal: 'Full CRM Integration Suite License', site: 'wpfusion.com', x: 450 },
    { name: 'Housewise', tier: 'Mega Sponsor', deal: 'Custom Tech Consultation & Swag', site: 'housewise.in', x: 852 },
  ];

  for (const s of topSponsors) {
    b.rect('Top Sp Card', s.x, 196, 380, 180, { fill: '#FFFFFF', cornerRadius: 20, stroke: '#E2E8F0', strokeWeight: 1 }, f);
    b.rect('Logo Container', s.x + 20, 216, 80, 50, { fill: '#F8FAFC', stroke: '#E2E8F0', cornerRadius: 10, strokeWeight: 1 }, f);
    b.text(s.name.charAt(0), s.x + 50, 230, { fontSize: 24, fontWeight: 800, fill: '#FF4D2D' }, f);
    b.text(s.tier.toUpperCase(), s.x + 116, 222, { fontSize: 10, fontWeight: 800, fill: '#D97706' }, f);
    b.text(s.name, s.x + 116, 240, { fontSize: 18, fontWeight: 800, fill: '#0F172A' }, f);
    b.text('🌐 ' + s.site, s.x + 116, 264, { fontSize: 11, fill: '#64748B' }, f);
    b.line('Sp Div', s.x + 20, 286, 340, { stroke: '#F1F5F9', strokeWeight: 1 }, f);
    b.text('🎁 Exclusive Deal: ' + s.deal, s.x + 20, 304, { fontSize: 11, fontWeight: 700, fill: '#059669' }, f);
    b.rect('Claim Btn', s.x + 20, 326, 340, 34, { fill: '#FFEDD5', stroke: '#FDBA74', cornerRadius: 8, strokeWeight: 1 }, f);
    b.text('Claim Sponsor Perk  →', s.x + 116, 336, { fontSize: 11, fontWeight: 700, fill: '#D33417' }, f);
  }

  b.text('GLOBAL & LOCAL SPONSORS', 48, 406, { fontSize: 12, fontWeight: 800, fill: '#475569', letterSpacing: 1.2 }, f);

  const moreSponsors = [
    { name: 'Bluehost', tier: 'Global Sponsor', perk: 'Free Cloud Hosting', x: 48 },
    { name: 'Automattic', tier: 'Global Sponsor', perk: 'WordPress VIP Credits', x: 290 },
    { name: 'Hostinger', tier: 'Global Sponsor', perk: 'Managed WP Tier', x: 532 },
    { name: 'SEOPress', tier: 'Local Sponsor', perk: 'SEO Pro Plugin', x: 774 },
    { name: 'Whinta', tier: 'Local Sponsor', perk: 'Cloud Security Audit', x: 1016 },
  ];

  for (const ms of moreSponsors) {
    b.rect('Grid Sp Card', ms.x, 430, 218, 120, { fill: '#FFFFFF', cornerRadius: 16, stroke: '#E2E8F0', strokeWeight: 1 }, f);
    b.rect('Mini Logo Box', ms.x + 16, 444, 36, 36, { fill: '#1E293B', cornerRadius: 8 }, f);
    b.text(ms.name.charAt(0), ms.x + 28, 452, { fontSize: 16, fontWeight: 800, fill: '#FFFFFF' }, f);
    b.text(ms.tier, ms.x + 60, 448, { fontSize: 9, fontWeight: 700, fill: '#D97706' }, f);
    b.text(ms.name, ms.x + 60, 464, { fontSize: 13, fontWeight: 800, fill: '#0F172A' }, f);
    b.line('L', ms.x + 16, 490, 186, { stroke: '#F1F5F9', strokeWeight: 1 }, f);
    b.text(ms.perk, ms.x + 16, 506, { fontSize: 10, fontWeight: 600, fill: '#059669' }, f);
  }
}

// Screen W09: Contributor Day Quests
{
  const f = b.frame('W09 - Contributor Day Quests', 2680, 1000, 1280, 800, { fill: '#FFF7ED', clip: true });
  b.webTopNav(f, 'home');

  b.text('Contributor Day Quests & Team Tables', 48, 100, { fontSize: 26, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Collaborate with core teams, earn contribution badges, and join working tables', 48, 132, { fontSize: 13, fill: '#64748B' }, f);

  // Progress Banner
  b.rect('Quest Banner', 48, 168, 1184, 80, { fill: '#1E293B', cornerRadius: 18 }, f);
  b.text('CONTRIBUTOR DAY PROGRESSION · LEVEL 2', 72, 188, { fontSize: 11, fontWeight: 800, fill: '#F59E0B' }, f);
  b.text('3 of 5 Quests Completed (60%) · 500 XP Earned', 72, 212, { fontSize: 16, fontWeight: 800, fill: '#FFFFFF' }, f);
  b.rect('Bar BG', 540, 204, 500, 12, { fill: '#334155', cornerRadius: 6 }, f);
  b.rect('Bar Fill', 540, 204, 300, 12, { fill: '#10B981', cornerRadius: 6 }, f);

  // 4 Table Cards
  const tables = [
    { title: 'Core PHP & JS', lead: 'Table Lead: Rahul Verma', desc: 'Working on Trac tickets and Gutenberg core enhancements.', x: 48, y: 276 },
    { title: 'Polyglots (Translations)', lead: 'Table Lead: Priya Sharma', desc: 'Translating WordPress core and top plugins into regional languages.', x: 652, y: 276 },
    { title: 'Documentation & Handbook', lead: 'Table Lead: Amit Singh', desc: 'Updating block editor guides, developer references, and docs.', x: 48, y: 440 },
    { title: 'Community & Kids Camp', lead: 'Table Lead: Elena Rostova', desc: 'Organizing mentorship tracks, meetup support, and student outreach.', x: 652, y: 440 },
  ];

  for (const t of tables) {
    b.rect('Table Card', t.x, t.y, 580, 140, { fill: '#FFFFFF', cornerRadius: 18, stroke: '#E2E8F0', strokeWeight: 1 }, f);
    b.text(t.title, t.x + 24, t.y + 24, { fontSize: 18, fontWeight: 800, fill: '#0F172A' }, f);
    b.text(t.lead, t.x + 24, t.y + 52, { fontSize: 12, fontWeight: 700, fill: '#D97706' }, f);
    b.text(t.desc, t.x + 24, t.y + 74, { fontSize: 12, fill: '#64748B' }, f);
    b.rect('Join Table Btn', t.x + 24, t.y + 96, 140, 32, { fill: '#FF4D2D', cornerRadius: 6 }, f);
    b.text('Join Table →', t.x + 56, t.y + 104, { fontSize: 11, fontWeight: 700, fill: '#FFFFFF' }, f);
  }
}

// Screen W10: Venue & Code of Conduct Safety
{
  const f = b.frame('W10 - Venue & Safety Hub', 4000, 1000, 1280, 800, { fill: '#FFF7ED', clip: true });
  b.webTopNav(f, 'home');

  b.text('Venue Logistics, Connectivity & Safety Support', 48, 100, { fontSize: 26, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Official floorplans, Wi-Fi credentials, and harassment-free Code of Conduct policies', 48, 132, { fontSize: 13, fill: '#64748B' }, f);

  // Safety Card
  b.rect('Safety Big Card', 48, 170, 780, 280, { fill: '#FEF2F2', stroke: '#FECACA', cornerRadius: 20, strokeWeight: 1.5 }, f);
  b.text('🛡 Code of Conduct & Incident Reporting', 76, 198, { fontSize: 18, fontWeight: 800, fill: '#991B1B' }, f);
  b.text('WordCamp Delhi is dedicated to providing a harassment-free conference experience for everyone, regardless of gender, sexual orientation, disability, physical appearance, race, or religion.', 76, 228, { fontSize: 12, fill: '#7F1D1D' }, f);
  b.text('If you experience or witness an incident, report it immediately to any staff member wearing an ORGANIZER lanyard or use the hotlines below:', 76, 268, { fontSize: 12, fill: '#7F1D1D' }, f);

  b.rect('Call Hotline Btn', 76, 330, 220, 44, { fill: '#EF4444', cornerRadius: 10 }, f);
  b.text('📞 Call Incident Hotline', 108, 344, { fontSize: 12, fontWeight: 700, fill: '#FFFFFF' }, f);

  b.rect('Report Form Btn', 312, 330, 220, 44, { fill: '#FFFFFF', stroke: '#DC2626', cornerRadius: 10, strokeWeight: 1 }, f);
  b.text('📝 File Confidential Report', 338, 344, { fontSize: 12, fontWeight: 700, fill: '#DC2626' }, f);

  // Venue Info
  b.rect('Venue Box', 852, 170, 380, 280, { fill: '#FFFFFF', stroke: '#E2E8F0', cornerRadius: 20, strokeWeight: 1 }, f);
  b.text('📍 NDMC Convention Centre', 876, 198, { fontSize: 16, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Sansad Marg, Connaught Place, New Delhi', 876, 224, { fontSize: 12, fill: '#64748B' }, f);

  b.rect('Wifi Details', 876, 260, 332, 70, { fill: '#F8FAFC', stroke: '#CBD5E1', cornerRadius: 12 }, f);
  b.text('📶 Network: WCDelhi2026', 894, 276, { fontSize: 12, fontWeight: 700, fill: '#0F172A' }, f);
  b.text('Password: wordpressrocks', 894, 298, { fontSize: 12, fontWeight: 700, fill: '#FF4D2D' }, f);

  b.rect('Map Action', 876, 350, 332, 42, { fill: '#FFEDD5', cornerRadius: 10 }, f);
  b.text('Open in Google Maps Navigation  →', 924, 363, { fontSize: 11, fontWeight: 700, fill: '#D33417' }, f);
}

// Screen W11: Post-Event Memories & Slides Wall
{
  const f = b.frame('W11 - Post-Event Memories & Slides', 5320, 1000, 1280, 800, { fill: '#FFF7ED', clip: true });
  b.webTopNav(f, 'home');

  b.text('Post-Event Memories, Slides & Ratings', 48, 100, { fontSize: 26, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Download official presentation slides, view community photo streams, and export your takeaway PDF', 48, 132, { fontSize: 13, fill: '#64748B' }, f);

  b.rect('Memories Hero', 48, 170, 1184, 180, { fill: '#1E293B', cornerRadius: 20 }, f);
  b.text('THANK YOU FOR ATTENDING WORDCAMP DELHI 2026!', 76, 198, { fontSize: 13, fontWeight: 800, fill: '#F59E0B' }, f);
  b.text('22 Sessions Completed · 850 Attendees · 17 Sponsors', 76, 224, { fontSize: 24, fontWeight: 800, fill: '#FFFFFF' }, f);
  b.text('Your starred sessions and CampCard connections remain available in your browser indefinitely.', 76, 260, { fontSize: 13, fill: '#94A3B8' }, f);

  b.rect('Export Takeaway PDF', 76, 290, 220, 42, { fill: '#FF4D2D', cornerRadius: 10 }, f);
  b.text('📄 Export Conference PDF', 104, 303, { fontSize: 12, fontWeight: 700, fill: '#FFFFFF' }, f);

  // Slides Gallery
  b.text('Available Presentation Slides', 48, 380, { fontSize: 18, fontWeight: 800, fill: '#0F172A' }, f);

  const slides = [
    { title: 'Scaling Headless WordPress & SEO', speaker: 'Pankaj Kumar', format: 'PDF (14.2 MB)', x: 48 },
    { title: 'Designing Accessible Enterprise Themes', speaker: 'Lena Lekkou', format: 'Keynote (22.5 MB)', x: 450 },
    { title: 'Full-Site Editing & Custom Block Governance', speaker: 'Patrick Lumumba', format: 'Slides (8.4 MB)', x: 852 },
  ];

  for (const sl of slides) {
    b.rect('Slide Card', sl.x, 410, 380, 140, { fill: '#FFFFFF', cornerRadius: 16, stroke: '#E2E8F0', strokeWeight: 1 }, f);
    b.text('📊 ' + sl.format, sl.x + 20, 434, { fontSize: 11, fontWeight: 700, fill: '#D97706' }, f);
    b.text(sl.title, sl.x + 20, 458, { fontSize: 15, fontWeight: 800, fill: '#0F172A' }, f);
    b.text(sl.speaker, sl.x + 20, 482, { fontSize: 12, fill: '#64748B' }, f);
    b.rect('DL Btn', sl.x + 20, 506, 120, 30, { fill: '#FFEDD5', cornerRadius: 6 }, f);
    b.text('Download ↓', sl.x + 44, 514, { fontSize: 11, fontWeight: 700, fill: '#D33417' }, f);
  }
}

// ============================================================================
// PAGE 2: PWA (9 Screens in complete mobile offline flow @ 390x844)
// ============================================================================
b.setPage('PWA');

// P01: Install & Cache Hero
{
  const f = b.frame('P01 - Install & Cache Hero', 40, 100, 390, 844, { fill: '#FFF7ED', clip: true });
  b.rect('Browser Chrome', 0, 0, 390, 78, { fill: '#F1F5F9' }, f);
  b.rect('URL Bar', 16, 38, 358, 32, { fill: '#FFFFFF', stroke: '#CBD5E1', cornerRadius: 16 }, f);
  b.text('🔒 campbuddy.club', 140, 46, { fontSize: 11, fontWeight: 600, fill: '#0F172A' }, f);

  b.rect('A2HS Card', 16, 96, 358, 180, { fill: '#1E293B', cornerRadius: 20, stroke: '#334155', strokeWeight: 1 }, f);
  b.rect('App Icon Box', 32, 114, 48, 48, { fill: '#FF4D2D', cornerRadius: 24 }, f);
  b.text('C', 48, 126, { fontSize: 24, fontWeight: 800, fill: '#FFFFFF' }, f);
  b.text('Install CampBuddy PWA', 92, 116, { fontSize: 16, fontWeight: 800, fill: '#FFFFFF' }, f);
  b.text('Add to Home Screen for 100% offline access', 92, 138, { fontSize: 11, fill: '#94A3B8' }, f);
  b.text('✔ Zero App Store account needed\n✔ Instant launch · No 50MB download\n✔ Works when venue Wi-Fi goes down', 92, 160, { fontSize: 10, fill: '#CBD5E1' }, f);

  b.rect('Install A2HS Btn', 32, 226, 326, 38, { fill: '#FF4D2D', cornerRadius: 10 }, f);
  b.text('Install PWA to Home Screen  ⚡', 100, 237, { fontSize: 12, fontWeight: 700, fill: '#FFFFFF' }, f);

  b.rect('Cache Status', 16, 290, 358, 64, { fill: '#ECFDF5', stroke: '#A7F3D0', cornerRadius: 14, strokeWeight: 1 }, f);
  b.text('⚡ Service Worker Active · 48 Assets Cached', 30, 304, { fontSize: 11, fontWeight: 700, fill: '#047857' }, f);
  b.text('IndexedDB schedule & CampCard encrypted locally', 30, 324, { fontSize: 10, fill: '#059669' }, f);
}

// P02: Standalone Tactile Home
{
  const f = b.frame('P02 - Standalone Tactile Home', 470, 100, 390, 844, { fill: '#FFF7ED', clip: true });
  b.rect('PWA Top Bar', 0, 0, 390, 44, { fill: '#1E293B' }, f);
  b.ellipse('Offline Green Dot', 16, 17, 10, 10, { fill: '#10B981' }, f);
  b.text('Offline PWA Mode · Venue Safe', 34, 15, { fontSize: 10, fontWeight: 700, fill: '#A7F3D0' }, f);
  b.text('10:14 AM', 330, 15, { fontSize: 11, fontWeight: 700, fill: '#FFFFFF' }, f);

  b.rect('PWA Hero Card', 16, 54, 358, 170, { fill: '#1E293B', cornerRadius: 22, stroke: '#334155', strokeWeight: 1 }, f);
  b.text('WordCamp Delhi 2026', 32, 70, { fontSize: 18, fontWeight: 800, fill: '#FFFFFF' }, f);
  b.text('NDMC Convention Centre · Hall A & B', 32, 94, { fontSize: 11, fill: '#94A3B8' }, f);

  b.rect('PWA Next Box', 28, 120, 334, 86, { fill: '#0F172A', cornerRadius: 14 }, f);
  b.text('⚡ NEXT TALK IN 16 MIN', 42, 132, { fontSize: 10, fontWeight: 800, fill: '#F59E0B' }, f);
  b.text('Scaling Headless WordPress & SEO', 42, 150, { fontSize: 13, fontWeight: 700, fill: '#FFFFFF' }, f);
  b.text('Pankaj Kumar · Hall A', 42, 170, { fontSize: 11, fill: '#94A3B8' }, f);
  b.rect('PWA View Btn', 270, 146, 76, 32, { fill: '#D97706', cornerRadius: 16 }, f);
  b.text('VIEW', 292, 154, { fontSize: 10, fontWeight: 800, fill: '#FFFFFF' }, f);

  const pwaHubs = [
    { title: 'My Schedule', sub: '4 saved talks', icon: '📅', x: 16, y: 242 },
    { title: 'Full Program', sub: '22 talks · 2 tracks', icon: '🎤', x: 200, y: 242 },
    { title: 'Offline Card', sub: 'QR Badge', icon: '🪪', x: 16, y: 336 },
    { title: 'Attendees', sub: 'Directory & roles', icon: '👥', x: 200, y: 336 },
    { title: 'Quests', sub: 'Roadmap', icon: '🎯', x: 16, y: 430 },
    { title: 'Deals & Steals', sub: '17 tools', icon: '🎁', x: 200, y: 430 },
  ];
  for (const h of pwaHubs) {
    b.rect('PWA Hub Tile', h.x, h.y, 174, 82, { fill: '#FFFFFF', cornerRadius: 16, stroke: '#E2E8F0', strokeWeight: 1 }, f);
    b.rect('PWA Hub Icon', h.x + 12, h.y + 12, 32, 32, { fill: '#FFEDD5', cornerRadius: 16 }, f);
    b.text(h.icon, h.x + 18, h.y + 18, { fontSize: 14 }, f);
    b.text(h.title, h.x + 12, h.y + 48, { fontSize: 13, fontWeight: 800, fill: '#0F172A' }, f);
    b.text(h.sub, h.x + 12, h.y + 64, { fontSize: 10, fill: '#64748B' }, f);
  }
  b.mobileBottomNav(f, 'home');
}

// P03: Offline Program & Hall Filter
{
  const f = b.frame('P03 - Offline Program & Rooms', 900, 100, 390, 844, { fill: '#FFF7ED', clip: true });
  b.rect('Top', 0, 0, 390, 44, { fill: '#1E293B' }, f);
  b.text('Program Explorer · Cached', 16, 14, { fontSize: 12, fontWeight: 700, fill: '#FFFFFF' }, f);

  b.text('All Tracks (22 Sessions)', 16, 56, { fontSize: 18, fontWeight: 800, fill: '#0F172A' }, f);

  b.rect('H1 Pill', 16, 88, 170, 36, { fill: '#FF4D2D', cornerRadius: 18 }, f);
  b.text('Hall A (Technical)', 46, 98, { fontSize: 12, fontWeight: 700, fill: '#FFFFFF' }, f);
  b.rect('H2 Pill', 196, 88, 170, 36, { fill: '#FFFFFF', stroke: '#E2E8F0', cornerRadius: 18 }, f);
  b.text('Hall B (Design & Biz)', 224, 98, { fontSize: 12, fontWeight: 600, fill: '#64748B' }, f);

  b.rect('Offline Session 1', 16, 136, 358, 110, { fill: '#FFFFFF', cornerRadius: 16, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.text('10:30 AM · Hall A · Track 1', 28, 148, { fontSize: 10, fontWeight: 700, fill: '#D97706' }, f);
  b.text('Scaling Headless WordPress & SEO', 28, 168, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Pankaj Kumar (Pankaj Kumar SEO)', 28, 190, { fontSize: 11, fill: '#64748B' }, f);
  b.rect('Star Btn 1', 324, 150, 36, 36, { fill: '#FFEDD5', cornerRadius: 18 }, f);
  b.text('★', 334, 160, { fontSize: 14, fill: '#D97706' }, f);

  b.rect('Offline Session 2', 16, 258, 358, 110, { fill: '#FFFFFF', cornerRadius: 16, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.text('11:30 AM · Hall A · Track 1', 28, 270, { fontSize: 10, fontWeight: 700, fill: '#D97706' }, f);
  b.text('Full-Site Editing & Custom Block Governance', 28, 290, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Patrick Lumumba (Core Contributor)', 28, 312, { fontSize: 11, fill: '#64748B' }, f);
  b.rect('Star Btn 2', 324, 272, 36, 36, { fill: '#F1F5F9', cornerRadius: 18 }, f);
  b.text('☆', 334, 282, { fontSize: 14, fill: '#94A3B8' }, f);

  b.mobileBottomNav(f, 'my-day');
}

// P04: Offline Schedule Conflicts
{
  const f = b.frame('P04 - Conflict Resolution', 1330, 100, 390, 844, { fill: '#FFF7ED', clip: true });
  b.rect('Top', 0, 0, 390, 44, { fill: '#1E293B' }, f);
  b.text('Schedule Conflicts', 16, 14, { fontSize: 12, fontWeight: 700, fill: '#FFFFFF' }, f);

  b.rect('Warn Box', 16, 60, 358, 70, { fill: '#FEF2F2', stroke: '#FECACA', cornerRadius: 14, strokeWeight: 1 }, f);
  b.text('⚠️ SCHEDULE OVERLAP DETECTED (11:30 AM)', 28, 74, { fontSize: 11, fontWeight: 800, fill: '#991B1B' }, f);
  b.text('You have starred two concurrent sessions.', 28, 92, { fontSize: 10, fill: '#7F1D1D' }, f);

  b.rect('C1', 16, 144, 358, 90, { fill: '#FFFFFF', stroke: '#EF4444', cornerRadius: 14, strokeWeight: 1.5 }, f);
  b.text('TRACK 1 · 11:30 AM', 28, 158, { fontSize: 10, fontWeight: 800, fill: '#D33417' }, f);
  b.text('Full-Site Editing & Custom Blocks', 28, 176, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);
  b.rect('Keep 1', 290, 168, 70, 30, { fill: '#FF4D2D', cornerRadius: 6 }, f);
  b.text('KEEP', 310, 176, { fontSize: 10, fontWeight: 700, fill: '#FFFFFF' }, f);

  b.rect('C2', 16, 244, 358, 90, { fill: '#FFFFFF', stroke: '#CBD5E1', cornerRadius: 14, strokeWeight: 1 }, f);
  b.text('TRACK 2 · 11:30 AM', 28, 258, { fontSize: 10, fontWeight: 800, fill: '#0284C7' }, f);
  b.text('Designing Accessible Themes', 28, 276, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);
  b.rect('Remove 2', 290, 268, 70, 30, { fill: '#F1F5F9', cornerRadius: 6 }, f);
  b.text('REMOVE', 300, 276, { fontSize: 10, fontWeight: 700, fill: '#64748B' }, f);

  b.mobileBottomNav(f, 'my-day');
}

// P05: Offline CampCard QR
{
  const f = b.frame('P05 - Offline CampCard Badge', 1760, 100, 390, 844, { fill: '#FFF7ED', clip: true });
  b.rect('Top', 0, 0, 390, 44, { fill: '#1E293B' }, f);
  b.text('Offline CampCard', 16, 14, { fontSize: 12, fontWeight: 700, fill: '#FFFFFF' }, f);

  b.rect('Badge Body', 24, 60, 342, 430, { fill: '#FFFFFF', cornerRadius: 24, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.rect('Badge Top', 24, 60, 342, 54, { fill: '#1E293B', cornerRadius: 24 }, f);
  b.rect('Top Fix', 24, 84, 342, 30, { fill: '#1E293B' }, f);
  b.text('OFFLINE DIGITAL PASS · NO WI-FI NEEDED', 44, 76, { fontSize: 10, fontWeight: 800, fill: '#F59E0B' }, f);
  b.text('Sunil Kumar Sharma', 44, 136, { fontSize: 19, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('WordPress Engineer · WPSimplified', 44, 160, { fontSize: 12, fontWeight: 600, fill: '#FF4D2D' }, f);

  b.rect('QR', 95, 190, 200, 200, { fill: '#F8FAFC', stroke: '#E2E8F0', cornerRadius: 14 }, f);
  b.rect('QP1', 115, 210, 46, 46, { fill: '#0F172A', cornerRadius: 4 }, f);
  b.rect('QP2', 229, 210, 46, 46, { fill: '#0F172A', cornerRadius: 4 }, f);
  b.rect('QP3', 115, 324, 46, 46, { fill: '#0F172A', cornerRadius: 4 }, f);
  b.rect('QD1', 176, 210, 38, 46, { fill: '#0F172A', cornerRadius: 3 }, f);
  b.rect('QD2', 176, 280, 99, 40, { fill: '#0F172A', cornerRadius: 3 }, f);
  b.rect('QD3', 115, 276, 46, 36, { fill: '#0F172A', cornerRadius: 3 }, f);
  b.rect('QD4', 176, 324, 99, 46, { fill: '#0F172A', cornerRadius: 3 }, f);

  b.rect('Scan Camera Btn', 24, 510, 342, 52, { fill: '#FF4D2D', cornerRadius: 14 }, f);
  b.text('📷 Scan Peer CampCard (Offline Camera)', 64, 528, { fontSize: 13, fontWeight: 800, fill: '#FFFFFF' }, f);
  b.mobileBottomNav(f, 'camp-card');
}

// P06: P2P Camera Scanner Modal
{
  const f = b.frame('P06 - Camera QR Scanner Modal', 40, 1050, 390, 844, { fill: '#0F172A', clip: true });
  b.mobileStatusBar(f, true);
  b.text('Scan Attendee Badge', 110, 60, { fontSize: 16, fontWeight: 700, fill: '#FFFFFF' }, f);

  // Viewfinder
  b.rect('Viewfinder', 45, 180, 300, 300, { fill: 'transparent', stroke: '#FF4D2D', cornerRadius: 20, strokeWeight: 3 }, f);
  b.text('Align QR code within frame', 115, 510, { fontSize: 12, fill: '#94A3B8' }, f);

  // Scanned Card Preview Drawer
  b.rect('Drawer', 16, 560, 358, 180, { fill: '#1E293B', cornerRadius: 20, stroke: '#334155', strokeWeight: 1 }, f);
  b.text('SCANNED PEER BADGE', 36, 582, { fontSize: 10, fontWeight: 800, fill: '#10B981' }, f);
  b.text('Lena Lekkou', 36, 604, { fontSize: 18, fontWeight: 800, fill: '#FFFFFF' }, f);
  b.text('UI/UX Community Lead · Athens', 36, 628, { fontSize: 12, fill: '#94A3B8' }, f);
  b.rect('Save vCard Btn', 36, 660, 318, 44, { fill: '#10B981', cornerRadius: 10 }, f);
  b.text('Save Contact to Device  📇', 110, 674, { fontSize: 12, fontWeight: 700, fill: '#FFFFFF' }, f);
}

// P07: Offline Attendees Roster
{
  const f = b.frame('P07 - Offline Attendee Roster', 470, 1050, 390, 844, { fill: '#FFF7ED', clip: true });
  b.rect('Top', 0, 0, 390, 44, { fill: '#1E293B' }, f);
  b.text('Attendees (IndexedDB)', 16, 14, { fontSize: 12, fontWeight: 700, fill: '#FFFFFF' }, f);

  b.rect('Search', 16, 56, 358, 40, { fill: '#FFFFFF', stroke: '#CBD5E1', cornerRadius: 10 }, f);
  b.text('🔍 Search 82 cached attendees...', 30, 68, { fontSize: 12, fill: '#94A3B8' }, f);

  const pwaPeople = [
    { name: 'Pankaj Kumar', role: 'Speaker', initial: 'PK', color: '#38BDF8' },
    { name: 'Lena Lekkou', role: 'Speaker', initial: 'LL', color: '#F472B6' },
    { name: 'Patrick Lumumba', role: 'Speaker', initial: 'PL', color: '#4ADE80' },
    { name: 'Chandra Alif Irawan', role: 'Speaker', initial: 'CA', color: '#A78BFA' },
  ];
  pwaPeople.forEach((p, i) => {
    const y = 110 + i * 70;
    b.rect('Card', 16, y, 358, 60, { fill: '#FFFFFF', cornerRadius: 12, stroke: '#E2E8F0', strokeWeight: 1 }, f);
    b.rect('Av', 28, y + 10, 40, 40, { fill: p.color, cornerRadius: 20 }, f);
    b.text(p.initial, 38, y + 22, { fontSize: 14, fontWeight: 800, fill: '#FFFFFF' }, f);
    b.text(p.name, 80, y + 14, { fontSize: 13, fontWeight: 800, fill: '#0F172A' }, f);
    b.text(p.role, 80, y + 34, { fontSize: 11, fill: '#64748B' }, f);
    b.rect('Wave', 290, y + 14, 70, 30, { fill: '#FFEDD5', cornerRadius: 6 }, f);
    b.text('Wave 👋', 304, y + 22, { fontSize: 10, fontWeight: 700, fill: '#D33417' }, f);
  });
  b.mobileBottomNav(f, 'explore');
}

// P08: Contributor Checklist
{
  const f = b.frame('P08 - Contributor Day Checklist', 900, 1050, 390, 844, { fill: '#FFF7ED', clip: true });
  b.rect('Top', 0, 0, 390, 44, { fill: '#1E293B' }, f);
  b.text('Contributor Roadmap', 16, 14, { fontSize: 12, fontWeight: 700, fill: '#FFFFFF' }, f);

  b.text('Quests Progress (60%)', 16, 60, { fontSize: 18, fontWeight: 800, fill: '#0F172A' }, f);

  b.rect('Q1', 16, 96, 358, 70, { fill: '#FFFFFF', stroke: '#10B981', cornerRadius: 14 }, f);
  b.text('✔ Set up WordPress.org Contributor ID', 32, 114, { fontSize: 12, fontWeight: 700, fill: '#0F172A' }, f);
  b.text('+100 XP Earned', 32, 134, { fontSize: 10, fill: '#10B981' }, f);

  b.rect('Q2', 16, 176, 358, 70, { fill: '#FFFFFF', stroke: '#10B981', cornerRadius: 14 }, f);
  b.text('✔ Translate 10 Core Strings', 32, 194, { fontSize: 12, fontWeight: 700, fill: '#0F172A' }, f);
  b.text('+150 XP Earned', 32, 214, { fontSize: 10, fill: '#10B981' }, f);

  b.rect('Q3', 16, 256, 358, 70, { fill: '#FFFFFF', stroke: '#FF4D2D', cornerRadius: 14, strokeWeight: 2 }, f);
  b.text('3. Submit Documentation Patch', 32, 274, { fontSize: 12, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('In progress · +250 XP', 32, 294, { fontSize: 10, fill: '#FF4D2D' }, f);

  b.mobileBottomNav(f, 'quest');
}

// P09: Venue Map & SOS
{
  const f = b.frame('P09 - Venue Map & Emergency SOS', 1330, 1050, 390, 844, { fill: '#FFF7ED', clip: true });
  b.rect('Top', 0, 0, 390, 44, { fill: '#1E293B' }, f);
  b.text('Emergency & Wi-Fi', 16, 14, { fontSize: 12, fontWeight: 700, fill: '#FFFFFF' }, f);

  b.rect('SOS', 16, 60, 358, 140, { fill: '#FEF2F2', stroke: '#FECACA', cornerRadius: 18, strokeWeight: 1.5 }, f);
  b.text('🛟 Code of Conduct Hotline', 32, 80, { fontSize: 15, fontWeight: 800, fill: '#991B1B' }, f);
  b.text('Contact event safety volunteers immediately on site.', 32, 104, { fontSize: 11, fill: '#7F1D1D' }, f);
  b.rect('Call', 32, 134, 150, 40, { fill: '#EF4444', cornerRadius: 8 }, f);
  b.text('📞 Call Helpdesk', 54, 146, { fontSize: 11, fontWeight: 700, fill: '#FFFFFF' }, f);

  b.rect('Wifi', 16, 220, 358, 90, { fill: '#FFFFFF', stroke: '#E2E8F0', cornerRadius: 16 }, f);
  b.text('📶 Venue Wi-Fi: WCDelhi2026', 32, 240, { fontSize: 12, fontWeight: 700, fill: '#0F172A' }, f);
  b.text('Pass: wordpressrocks', 32, 264, { fontSize: 12, fontWeight: 700, fill: '#FF4D2D' }, f);

  b.mobileBottomNav(f, 'home');
}

// ============================================================================
// PAGE 3: APK (10 Screens in complete Android/iOS native flow @ 390x844)
// ============================================================================
b.setPage('APK');

// A01: Splash & Onboarding
{
  const f = b.frame('A01 - Native Splash & Onboarding', 40, 100, 390, 844, { fill: '#1E293B', clip: true });
  b.mobileStatusBar(f, true);

  b.rect('Logo Circle', 145, 240, 100, 100, { fill: '#FF4D2D', cornerRadius: 50 }, f);
  b.text('C', 178, 265, { fontSize: 50, fontWeight: 800, fill: '#FFFFFF' }, f);

  b.text('CampBuddy', 115, 360, { fontSize: 32, fontWeight: 800, fill: '#FFFFFF', fontFamily: 'Baloo 2' }, f);
  b.text('100% Offline Conference Companion', 75, 406, { fontSize: 14, fill: '#94A3B8' }, f);

  b.rect('Onboard Card', 20, 480, 350, 220, { fill: 'rgba(255,255,255,0.06)', stroke: '#334155', cornerRadius: 20, strokeWeight: 1 }, f);
  b.text('⚡ Zero Accounts · Zero Cloud Passwords', 40, 506, { fontSize: 13, fontWeight: 700, fill: '#F59E0B' }, f);
  b.text('All data remains locked on your device. Sync schedules, build your itinerary, and swap contact badges peer-to-peer.', 40, 532, { fontSize: 12, fill: '#CBD5E1' }, f);

  b.rect('Get Started Btn', 40, 620, 310, 48, { fill: '#FF4D2D', cornerRadius: 12 }, f);
  b.text('Get Started  →', 145, 634, { fontSize: 14, fontWeight: 700, fill: '#FFFFFF' }, f);
}

// A02: Event Picker & Sync
{
  const f = b.frame('A02 - Event Picker & Transfer', 470, 100, 390, 844, { fill: '#FFF7ED', clip: true });
  b.mobileStatusBar(f);

  b.text('Choose WordCamp', 20, 52, { fontSize: 22, fontWeight: 800, fill: '#0F172A' }, f);

  b.rect('Sync Box', 16, 92, 358, 86, { fill: '#FFFFFF', cornerRadius: 16, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.text('🔄 Move data from laptop / PC', 32, 106, { fontSize: 13, fontWeight: 700, fill: '#0F172A' }, f);
  b.text('Enter 6-digit sync code shown on your laptop', 32, 126, { fontSize: 11, fill: '#64748B' }, f);
  b.rect('Code Input', 32, 144, 200, 26, { fill: '#F8FAFC', stroke: '#CBD5E1', cornerRadius: 6 }, f);
  b.text('Code: 482 109', 44, 149, { fontSize: 11, fontWeight: 700, fill: '#0F172A' }, f);
  b.rect('Link Btn', 242, 144, 116, 26, { fill: '#FF4D2D', cornerRadius: 6 }, f);
  b.text('Link Device', 270, 149, { fontSize: 11, fontWeight: 700, fill: '#FFFFFF' }, f);

  b.rect('Delhi Event Card', 16, 200, 358, 180, { fill: '#FFFFFF', cornerRadius: 20, stroke: '#FF4D2D', strokeWeight: 2 }, f);
  b.text('ON NOW · 12–13 DEC 2026', 32, 218, { fontSize: 10, fontWeight: 800, fill: '#D97706' }, f);
  b.text('WordCamp Delhi 2026', 32, 236, { fontSize: 18, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('📍 NDMC Convention Centre, New Delhi', 32, 260, { fontSize: 12, fill: '#64748B' }, f);
  b.text('22 Talks · 11 Speakers · 17 Sponsors · Live Delay 0m', 32, 286, { fontSize: 11, fontWeight: 600, fill: '#334155' }, f);
  b.rect('Open App Btn', 32, 320, 326, 42, { fill: '#FF4D2D', cornerRadius: 10 }, f);
  b.text('Open Event Companion  →', 114, 332, { fontSize: 13, fontWeight: 700, fill: '#FFFFFF' }, f);
}

// A03: Tactile Home Dashboard
{
  const f = b.frame('A03 - Tactile Home Dashboard', 900, 100, 390, 844, { fill: '#FFF7ED', clip: true });
  b.mobileStatusBar(f, true);

  b.rect('Hero Card', 16, 44, 358, 204, { fill: '#1E293B', cornerRadius: 24, stroke: '#334155', strokeWeight: 1 }, f);
  b.text('10:14 AM', 32, 60, { fontSize: 28, fontWeight: 800, fill: '#FFFFFF' }, f);
  b.text('WordCamp Delhi · Day 1 of 2', 32, 94, { fontSize: 11, fontWeight: 600, fill: '#94A3B8' }, f);
  b.rect('Next Teaser', 28, 142, 334, 88, { fill: '#0F172A', cornerRadius: 16, stroke: '#334155', strokeWeight: 1 }, f);
  b.text('⚡ NEXT SESSION STARTS IN 16 MIN', 42, 154, { fontSize: 10, fontWeight: 800, fill: '#F59E0B' }, f);
  b.text('Scaling Headless WordPress & SEO', 42, 172, { fontSize: 13, fontWeight: 700, fill: '#FFFFFF' }, f);
  b.text('Track 1 · Hall A · Pankaj Kumar (SEO Lead)', 42, 192, { fontSize: 11, fill: '#94A3B8' }, f);

  // People Wave Strip
  b.text('People & Wave Matches', 16, 260, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);
  const apkPeople = [
    { name: 'Pankaj K.', color: '#38BDF8', x: 80, initial: 'PK' },
    { name: 'Lena L.', color: '#F472B6', x: 144, initial: 'LL' },
    { name: 'Patrick L.', color: '#4ADE80', x: 208, initial: 'PL' },
    { name: 'Chandra A.', color: '#A78BFA', x: 272, initial: 'CA' },
  ];
  b.rect('Wave Circle', 16, 282, 52, 52, { fill: '#FFEDD5', stroke: '#D97706', cornerRadius: 26, strokeWeight: 1.5 }, f);
  b.text('👋', 30, 296, { fontSize: 20 }, f);
  b.text('Wave', 26, 340, { fontSize: 10, fontWeight: 600, fill: '#0F172A' }, f);

  for (const p of apkPeople) {
    b.rect('Avatar', p.x, 282, 52, 52, { fill: p.color, cornerRadius: 26 }, f);
    b.text(p.initial, p.x + 16, 298, { fontSize: 15, fontWeight: 800, fill: '#FFFFFF' }, f);
    b.text(p.name, p.x + 4, 340, { fontSize: 10, fontWeight: 600, fill: '#0F172A' }, f);
  }

  // 2x3 Hubs
  const apkHubs = [
    { title: 'My Day', sub: '4 saved talks', icon: '📅', x: 16, y: 368 },
    { title: 'Schedule', sub: '22 sessions', icon: '🎤', x: 200, y: 368 },
    { title: 'My CampCard', sub: 'P2P QR Badge', icon: '🪪', x: 16, y: 464 },
    { title: 'Attendees', sub: '82 in roster', icon: '👥', x: 200, y: 464 },
    { title: 'Quests', sub: 'Contributor Day', icon: '🎯', x: 16, y: 560 },
    { title: 'Sponsors', sub: '17 sponsor steals', icon: '🎁', x: 200, y: 560 },
  ];
  for (const h of apkHubs) {
    b.rect('Hub Tile', h.x, h.y, 174, 82, { fill: '#FFFFFF', cornerRadius: 16, stroke: '#E2E8F0', strokeWeight: 1 }, f);
    b.rect('Hub Icon Box', h.x + 12, h.y + 12, 32, 32, { fill: '#FFEDD5', cornerRadius: 16 }, f);
    b.text(h.icon, h.x + 18, h.y + 18, { fontSize: 14 }, f);
    b.text(h.title, h.x + 12, h.y + 48, { fontSize: 13, fontWeight: 800, fill: '#0F172A' }, f);
    b.text(h.sub, h.x + 12, h.y + 64, { fontSize: 10, fill: '#64748B' }, f);
  }
  b.mobileBottomNav(f, 'home');
}

// A04: Session Detail Screen
{
  const f = b.frame('A04 - Session Detail View', 1330, 100, 390, 844, { fill: '#FFF7ED', clip: true });
  b.mobileStatusBar(f);
  b.text('← Back to Schedule', 16, 52, { fontSize: 12, fontWeight: 700, fill: '#FF4D2D' }, f);

  b.rect('Track Tag', 16, 84, 80, 24, { fill: '#FFEDD5', cornerRadius: 12 }, f);
  b.text('TRACK 1', 30, 90, { fontSize: 10, fontWeight: 800, fill: '#D33417' }, f);
  b.text('Hall A · 10:30 AM – 11:15 AM (45 min)', 106, 88, { fontSize: 11, fontWeight: 600, fill: '#64748B' }, f);

  b.text('Scaling Headless WordPress & SEO', 16, 120, { fontSize: 20, fontWeight: 800, fill: '#0F172A' }, f);

  // Speaker Card
  b.rect('Spk Card', 16, 170, 358, 80, { fill: '#FFFFFF', cornerRadius: 16, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.rect('Spk Pic', 28, 184, 52, 52, { fill: '#38BDF8', cornerRadius: 26 }, f);
  b.text('PK', 42, 200, { fontSize: 16, fontWeight: 800, fill: '#FFFFFF' }, f);
  b.text('Pankaj Kumar', 92, 190, { fontSize: 15, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Founder & SEO Lead · Pankaj Kumar SEO', 92, 212, { fontSize: 11, fill: '#64748B' }, f);

  b.text('About This Session', 16, 270, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Learn architectural strategies for decoupling WordPress with modern frontend frameworks (Next.js, Astro) while maintaining top Core Web Vitals and programmatic schema SEO.', 16, 294, { fontSize: 12, fill: '#475569', lineHeight: 18 }, f);

  b.rect('Star CTA', 16, 420, 358, 48, { fill: '#FF4D2D', cornerRadius: 12 }, f);
  b.text('★ Add to My Day (Offline Reminder)', 88, 434, { fontSize: 13, fontWeight: 700, fill: '#FFFFFF' }, f);
}

// A05: My Day Itinerary
{
  const f = b.frame('A05 - My Day Itinerary', 1760, 100, 390, 844, { fill: '#FFF7ED', clip: true });
  b.mobileStatusBar(f);
  b.text('My Day', 16, 52, { fontSize: 22, fontWeight: 800, fill: '#0F172A' }, f);

  b.rect('Day Tab', 16, 86, 170, 36, { fill: '#FF4D2D', cornerRadius: 18 }, f);
  b.text('Day 1 · Dec 12', 54, 96, { fontSize: 12, fontWeight: 700, fill: '#FFFFFF' }, f);

  b.rect('Timeline 1', 16, 136, 358, 110, { fill: '#FFFFFF', cornerRadius: 18, stroke: '#FF4D2D', strokeWeight: 2 }, f);
  b.text('10:30 AM · Hall A · Track 1', 28, 150, { fontSize: 10, fontWeight: 800, fill: '#D33417' }, f);
  b.text('Scaling Headless WordPress & SEO', 28, 170, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Pankaj Kumar · Founder', 28, 194, { fontSize: 11, fill: '#64748B' }, f);

  b.rect('Timeline 2', 16, 258, 358, 110, { fill: '#FFFFFF', cornerRadius: 18, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.text('11:30 AM · Hall B · Track 2', 28, 272, { fontSize: 10, fontWeight: 800, fill: '#0284C7' }, f);
  b.text('Designing Accessible Enterprise Themes', 28, 292, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Lena Lekkou · UI/UX Lead', 28, 316, { fontSize: 11, fill: '#64748B' }, f);

  b.mobileBottomNav(f, 'my-day');
}

// A06: CampCard Native View
{
  const f = b.frame('A06 - CampCard Native View', 40, 1050, 390, 844, { fill: '#FFF7ED', clip: true });
  b.mobileStatusBar(f);
  b.text('CampCard Badge', 16, 52, { fontSize: 22, fontWeight: 800, fill: '#0F172A' }, f);

  b.rect('Card Outer', 20, 96, 350, 480, { fill: '#FFFFFF', cornerRadius: 24, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.rect('Header Fix', 20, 96, 350, 60, { fill: '#FF4D2D', cornerRadius: 24 }, f);
  b.rect('Header Cover', 20, 126, 350, 30, { fill: '#FF4D2D' }, f);
  b.text('WORDCAMP DELHI 2026 BADGE', 36, 114, { fontSize: 11, fontWeight: 800, fill: '#FFFFFF' }, f);

  b.text('Sunil Kumar Sharma', 36, 180, { fontSize: 20, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Full Stack WordPress Developer', 36, 206, { fontSize: 12, fontWeight: 600, fill: '#FF4D2D' }, f);

  b.rect('QR Area', 85, 250, 220, 220, { fill: '#F8FAFC', stroke: '#E2E8F0', cornerRadius: 16 }, f);
  b.rect('Q1', 105, 270, 50, 50, { fill: '#0F172A', cornerRadius: 4 }, f);
  b.rect('Q2', 235, 270, 50, 50, { fill: '#0F172A', cornerRadius: 4 }, f);
  b.rect('Q3', 105, 400, 50, 50, { fill: '#0F172A', cornerRadius: 4 }, f);
  b.rect('Q4', 170, 270, 45, 50, { fill: '#0F172A', cornerRadius: 3 }, f);
  b.rect('Q5', 170, 340, 115, 45, { fill: '#0F172A', cornerRadius: 3 }, f);
  b.rect('Q6', 105, 340, 50, 45, { fill: '#0F172A', cornerRadius: 3 }, f);
  b.rect('Q7', 170, 400, 115, 50, { fill: '#0F172A', cornerRadius: 3 }, f);

  b.rect('Share Btn', 20, 594, 350, 48, { fill: '#FF4D2D', cornerRadius: 12 }, f);
  b.text('📤 Share Contact Card (vCard)', 94, 608, { fontSize: 13, fontWeight: 700, fill: '#FFFFFF' }, f);

  b.mobileBottomNav(f, 'camp-card');
}

// A07: Native MLKit Camera Barcode Scanner
{
  const f = b.frame('A07 - Native MLKit Scanner', 470, 1050, 390, 844, { fill: '#0F172A', clip: true });
  b.mobileStatusBar(f, true);
  b.text('Scan Attendee Badge', 115, 60, { fontSize: 16, fontWeight: 700, fill: '#FFFFFF' }, f);

  b.rect('Scan Box', 45, 200, 300, 300, { fill: 'transparent', stroke: '#10B981', cornerRadius: 20, strokeWeight: 3 }, f);
  b.text('CameraX MLKit Active · Point at Badge', 80, 520, { fontSize: 12, fill: '#A7F3D0' }, f);

  b.rect('Flashlight', 165, 580, 60, 60, { fill: 'rgba(255,255,255,0.1)', cornerRadius: 30 }, f);
  b.text('🔦', 185, 600, { fontSize: 20 }, f);
}

// A08: Explore Roster & Sponsors
{
  const f = b.frame('A08 - Explore Directory', 900, 1050, 390, 844, { fill: '#FFF7ED', clip: true });
  b.mobileStatusBar(f);
  b.text('Explore Delhi 2026', 16, 52, { fontSize: 22, fontWeight: 800, fill: '#0F172A' }, f);

  b.text('Event Sponsors (17 Verified)', 16, 92, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);

  const realSp = [
    { name: 'Elementor Ltd', tier: 'Tera Sponsor', x: 16, y: 116 },
    { name: 'WPFusion', tier: 'Giga Sponsor', x: 200, y: 116 },
    { name: 'Housewise', tier: 'Mega Sponsor', x: 16, y: 208 },
    { name: 'Bluehost', tier: 'Global Sponsor', x: 200, y: 208 },
  ];
  for (const s of realSp) {
    b.rect('Card', s.x, s.y, 174, 80, { fill: '#FFFFFF', cornerRadius: 14, stroke: '#E2E8F0', strokeWeight: 1 }, f);
    b.text(s.tier.toUpperCase(), s.x + 14, s.y + 16, { fontSize: 9, fontWeight: 800, fill: '#D97706' }, f);
    b.text(s.name, s.x + 14, s.y + 34, { fontSize: 13, fontWeight: 800, fill: '#0F172A' }, f);
    b.text('View Steal Deals →', s.x + 14, s.y + 54, { fontSize: 10, fill: '#059669' }, f);
  }
  b.mobileBottomNav(f, 'explore');
}

// A09: Contributor Quests
{
  const f = b.frame('A09 - Quests & XP Rewards', 1330, 1050, 390, 844, { fill: '#FFF7ED', clip: true });
  b.mobileStatusBar(f);
  b.text('Contributor Roadmap', 16, 52, { fontSize: 22, fontWeight: 800, fill: '#0F172A' }, f);

  b.rect('Prog', 16, 92, 358, 80, { fill: '#1E293B', cornerRadius: 18 }, f);
  b.text('CONTRIBUTOR DAY · LEVEL 2', 28, 108, { fontSize: 10, fontWeight: 800, fill: '#F59E0B' }, f);
  b.text('60% Complete · 3 of 5 Quests', 28, 126, { fontSize: 15, fontWeight: 800, fill: '#FFFFFF' }, f);

  b.rect('Q1', 16, 184, 358, 80, { fill: '#FFFFFF', stroke: '#10B981', cornerRadius: 16 }, f);
  b.text('✔ WordPress.org Profile Link', 32, 202, { fontSize: 13, fontWeight: 700, fill: '#0F172A' }, f);
  b.text('+100 XP Earned', 32, 224, { fontSize: 11, fill: '#10B981' }, f);

  b.mobileBottomNav(f, 'quest');
}

// A10: Safety & Incident Report
{
  const f = b.frame('A10 - Safety & Incident Dialog', 1760, 1050, 390, 844, { fill: '#FFF7ED', clip: true });
  b.mobileStatusBar(f);
  b.text('Safety Support', 16, 52, { fontSize: 22, fontWeight: 800, fill: '#0F172A' }, f);

  b.rect('Box', 16, 96, 358, 240, { fill: '#FEF2F2', stroke: '#FECACA', cornerRadius: 20, strokeWeight: 1.5 }, f);
  b.text('🛟 Immediate Incident Help', 36, 122, { fontSize: 16, fontWeight: 800, fill: '#991B1B' }, f);
  b.text('WordCamp Delhi organizers are committed to attendee safety. If you need assistance, use the hotlines below:', 36, 150, { fontSize: 12, fill: '#7F1D1D' }, f);

  b.rect('Call', 36, 210, 318, 44, { fill: '#EF4444', cornerRadius: 10 }, f);
  b.text('📞 Call Venue Helpdesk Now', 106, 224, { fontSize: 12, fontWeight: 700, fill: '#FFFFFF' }, f);

  b.rect('Msg', 36, 266, 318, 44, { fill: '#FFFFFF', stroke: '#DC2626', cornerRadius: 10, strokeWeight: 1 }, f);
  b.text('💬 Send Confidential Message', 104, 280, { fontSize: 12, fontWeight: 700, fill: '#DC2626' }, f);
}

// ============================================================================
// PAGE 4: AdminPanel (8 Screens in complete desktop & staff console flow)
// ============================================================================
b.setPage('AdminPanel');

// AP01: Desktop Control Center
{
  const f = b.frame('AP01 - Desktop Control Center', 40, 100, 1280, 800, { fill: '#F8FAFC', clip: true });
  b.rect('Admin Header', 0, 0, 1280, 68, { fill: '#1E293B' }, f);
  b.text('CampBuddy Staff Console', 32, 22, { fontSize: 18, fontWeight: 800, fill: '#FFFFFF' }, f);
  b.rect('Badge', 270, 20, 100, 28, { fill: '#FF4D2D', cornerRadius: 14 }, f);
  b.text('SUPER ADMIN', 282, 27, { fontSize: 10, fontWeight: 800, fill: '#FFFFFF' }, f);
  b.text('Event: WordCamp Delhi 2026', 800, 26, { fontSize: 13, fontWeight: 700, fill: '#F59E0B' }, f);

  b.rect('Sidebar', 0, 68, 240, 732, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.rect('Active BG', 12, 92, 216, 38, { fill: '#FFEDD5', cornerRadius: 8 }, f);
  b.text('📊 Dashboard Overview', 24, 103, { fontSize: 12, fontWeight: 800, fill: '#D33417' }, f);
  b.text('⏱ Live Delays', 24, 151, { fontSize: 12, fill: '#475569' }, f);
  b.text('📢 Broadcast Alerts', 24, 199, { fontSize: 12, fill: '#475569' }, f);
  b.text('🎁 Sponsors & Media', 24, 247, { fontSize: 12, fill: '#475569' }, f);
  b.text('👥 Attendee Roster', 24, 295, { fontSize: 12, fill: '#475569' }, f);
  b.text('🎯 Contributor Tables', 24, 343, { fontSize: 12, fill: '#475569' }, f);

  b.text('Super Admin Overview · Delhi Operations', 272, 92, { fontSize: 22, fontWeight: 800, fill: '#0F172A' }, f);

  const metrics = [
    { num: '1,248', label: 'Synced Attendee Devices', sub: '92% offline ready', icon: '📱', x: 272 },
    { num: '22 Talks', label: 'Program Sessions Live', sub: '2 tracks active', icon: '🎤', x: 520 },
    { num: '17 Logos', label: 'Verified Sponsor Logos', sub: '100% scraped clean', icon: '🎁', x: 768 },
    { num: '0 Min', label: 'Active Schedule Delay', sub: 'All tracks on time', icon: '⏱', x: 1016 },
  ];
  for (const m of metrics) {
    b.rect('Metric Card', m.x, 140, 232, 100, { fill: '#FFFFFF', cornerRadius: 16, stroke: '#E2E8F0', strokeWeight: 1 }, f);
    b.text(m.icon, m.x + 16, 160, { fontSize: 20 }, f);
    b.text(m.num, m.x + 50, 158, { fontSize: 20, fontWeight: 800, fill: '#0F172A' }, f);
    b.text(m.label, m.x + 16, 196, { fontSize: 11, fontWeight: 700, fill: '#334155' }, f);
    b.text(m.sub, m.x + 16, 216, { fontSize: 10, fill: '#10B981' }, f);
  }

  // Feed status box
  b.rect('Feed Table Box', 272, 260, 976, 500, { fill: '#FFFFFF', cornerRadius: 20, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.text('Live Feeds Ingestion & Hourly Cron Telemetry', 296, 286, { fontSize: 16, fontWeight: 800, fill: '#0F172A' }, f);

  const feeds = [
    { ep: '/wp-json/wp/v2/sessions', count: '22 items', st: '200 OK (Clean)' },
    { ep: '/wp-json/wp/v2/speakers', count: '11 items', st: '200 OK (With Avatars)' },
    { ep: 'https://delhi.wordcamp.org/2026/sponsors/ (Page Map)', count: '17 logos', st: '100% High-Res Match' },
    { ep: '/wp-json/wp/v2/attendees (Roster)', count: '82 attendees', st: 'Gravatar Synced' },
  ];
  feeds.forEach((fd, i) => {
    const y = 330 + i * 60;
    b.rect('Row', 296, y, 928, 48, { fill: '#F8FAFC', cornerRadius: 8 }, f);
    b.text(fd.ep, 312, y + 16, { fontSize: 13, fontWeight: 700, fill: '#0F172A' }, f);
    b.text(fd.count, 700, y + 16, { fontSize: 12, fill: '#64748B' }, f);
    b.rect('Pill', 1050, y + 10, 150, 26, { fill: '#ECFDF5', cornerRadius: 13 }, f);
    b.text(fd.st, 1060, y + 16, { fontSize: 10, fontWeight: 700, fill: '#047857' }, f);
  });
}

// AP02: Live Delay Controller
{
  const f = b.frame('AP02 - Live Delay Broadcaster', 1360, 100, 1280, 800, { fill: '#F8FAFC', clip: true });
  b.rect('H', 0, 0, 1280, 68, { fill: '#1E293B' }, f);
  b.text('CampBuddy Staff Console · Live Delay Dispatcher', 32, 22, { fontSize: 18, fontWeight: 800, fill: '#FFFFFF' }, f);

  b.rect('Main Box', 48, 100, 1184, 660, { fill: '#FFFFFF', cornerRadius: 20, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.text('Schedule Delay Offset Controller', 80, 136, { fontSize: 20, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Broadcasts live time shifts to all 1,248 attendee devices without page reloads.', 80, 166, { fontSize: 13, fill: '#64748B' }, f);

  b.text('Select Track:', 80, 210, { fontSize: 13, fontWeight: 700, fill: '#334155' }, f);
  b.rect('Track 1 Tab', 80, 234, 180, 44, { fill: '#FFEDD5', stroke: '#FF4D2D', cornerRadius: 8, strokeWeight: 2 }, f);
  b.text('All Tracks (Hall A & B)', 98, 248, { fontSize: 12, fontWeight: 800, fill: '#D33417' }, f);

  b.text('Select Delay Duration:', 80, 300, { fontSize: 13, fontWeight: 700, fill: '#334155' }, f);
  const delays = ['+10 Mins', '+15 Mins', '+30 Mins', 'Clear Delay'];
  delays.forEach((d, i) => {
    const x = 80 + i * 150;
    const active = i === 1;
    b.rect('D Pill', x, 324, 130, 44, { fill: active ? '#FFEDD5' : '#F1F5F9', stroke: active ? '#FF4D2D' : '#CBD5E1', cornerRadius: 8, strokeWeight: active ? 2 : 1 }, f);
    b.text(d, x + 30, 338, { fontSize: 12, fontWeight: active ? 800 : 600, fill: active ? '#D33417' : '#334155' }, f);
  });

  b.rect('Preview', 80, 410, 600, 120, { fill: '#FFF7ED', stroke: '#FDBA74', cornerRadius: 14 }, f);
  b.text('⚠️ ATTENDEE SCREEN PREVIEW', 104, 430, { fontSize: 11, fontWeight: 800, fill: '#9A3412' }, f);
  b.text('Schedule Running +15 Mins Late', 104, 452, { fontSize: 16, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Pankaj Kumar\'s talk shifted: 10:30 AM → 10:45 AM', 104, 478, { fontSize: 12, fill: '#64748B' }, f);

  b.rect('Broadcast Delay Btn', 80, 560, 300, 50, { fill: '#D97706', cornerRadius: 12 }, f);
  b.text('Broadcast +15 Min Delay  ⏰', 140, 576, { fontSize: 14, fontWeight: 700, fill: '#FFFFFF' }, f);
}

// AP03: High Priority Push Broadcaster
{
  const f = b.frame('AP03 - Push Announcement Dispatcher', 2680, 100, 1280, 800, { fill: '#F8FAFC', clip: true });
  b.rect('H', 0, 0, 1280, 68, { fill: '#1E293B' }, f);
  b.text('CampBuddy Staff Console · Push Dispatcher', 32, 22, { fontSize: 18, fontWeight: 800, fill: '#FFFFFF' }, f);

  b.rect('Main Box', 48, 100, 1184, 660, { fill: '#FFFFFF', cornerRadius: 20, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.text('Compose High-Priority Announcement', 80, 136, { fontSize: 20, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Triggers instant local notifications and top banner across all attendee devices.', 80, 166, { fontSize: 13, fill: '#64748B' }, f);

  b.text('Announcement Title:', 80, 210, { fontSize: 13, fontWeight: 700, fill: '#334155' }, f);
  b.rect('Title In', 80, 234, 600, 44, { fill: '#F8FAFC', stroke: '#CBD5E1', cornerRadius: 8 }, f);
  b.text('Lunch is served at Dining Hall B!', 96, 248, { fontSize: 13, fill: '#0F172A' }, f);

  b.text('Announcement Body:', 80, 298, { fontSize: 13, fontWeight: 700, fill: '#334155' }, f);
  b.rect('Body In', 80, 322, 600, 120, { fill: '#F8FAFC', stroke: '#CBD5E1', cornerRadius: 8 }, f);
  b.text('Hot meals and dietary options are ready. Please visit sponsor booths for exclusive swag giveaways during the break.', 96, 342, { fontSize: 12, fill: '#0F172A', lineHeight: 18 }, f);

  b.rect('Send Push Btn', 80, 470, 300, 50, { fill: '#FF4D2D', cornerRadius: 12 }, f);
  b.text('Send Live Push Announcement  🚀', 116, 486, { fontSize: 13, fontWeight: 700, fill: '#FFFFFF' }, f);
}

// AP04: Sponsors & Scraped Media Audit
{
  const f = b.frame('AP04 - Sponsors & Media Health', 40, 1000, 1280, 800, { fill: '#F8FAFC', clip: true });
  b.rect('H', 0, 0, 1280, 68, { fill: '#1E293B' }, f);
  b.text('CampBuddy Staff Console · Sponsors & Media Health', 32, 22, { fontSize: 18, fontWeight: 800, fill: '#FFFFFF' }, f);

  b.rect('Box', 48, 100, 1184, 660, { fill: '#FFFFFF', cornerRadius: 20, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.text('17 Verified Delhi Sponsors · Public Page Scraper Audit', 80, 136, { fontSize: 20, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('All sponsor logos successfully resolved from https://delhi.wordcamp.org/2026/sponsors/', 80, 166, { fontSize: 13, fill: '#64748B' }, f);

  const sps = [
    { name: 'Elementor Ltd', tier: 'Tera Sponsor', url: '.../files/2026/11/Elementor.webp', code: '200 OK' },
    { name: 'WPFusion', tier: 'Giga Sponsor', url: '.../files/2026/11/WPFusion.webp', code: '200 OK' },
    { name: 'Housewise', tier: 'Mega Sponsor', url: '.../files/2026/11/Housewise.webp', code: '200 OK' },
    { name: 'Bluehost', tier: 'Global Sponsor', url: '.../files/2026/08/Bluehost.png', code: '200 OK' },
    { name: 'Hostinger', tier: 'Global Sponsor', url: '.../files/2026/08/Hostinger.png', code: '200 OK' },
    { name: 'Automattic', tier: 'Global Sponsor', url: '.../files/2026/08/Automattic.png', code: '200 OK' },
    { name: 'SEOPress', tier: 'Local Sponsor', url: '.../files/2026/09/SEOPress.webp', code: '200 OK' },
  ];
  sps.forEach((sp, i) => {
    const y = 210 + i * 54;
    b.text(sp.name, 80, y + 16, { fontSize: 13, fontWeight: 700, fill: '#0F172A' }, f);
    b.rect('Pill', 300, y + 10, 120, 26, { fill: '#FFEDD5', cornerRadius: 13 }, f);
    b.text(sp.tier, 314, y + 16, { fontSize: 10, fontWeight: 700, fill: '#D33417' }, f);
    b.text(sp.url, 480, y + 16, { fontSize: 11, fill: '#64748B' }, f);
    b.rect('Badge', 1050, y + 10, 90, 26, { fill: '#ECFDF5', cornerRadius: 13 }, f);
    b.text(sp.code, 1074, y + 16, { fontSize: 10, fontWeight: 700, fill: '#047857' }, f);
    b.line('L', 80, y + 46, 1120, { stroke: '#F1F5F9', strokeWeight: 1 }, f);
  });
}

// AP05: Attendee Roster & Role Manager
{
  const f = b.frame('AP05 - Attendee Roles Manager', 1360, 1000, 1280, 800, { fill: '#F8FAFC', clip: true });
  b.rect('H', 0, 0, 1280, 68, { fill: '#1E293B' }, f);
  b.text('CampBuddy Staff Console · Attendee Directory & Roles', 32, 22, { fontSize: 18, fontWeight: 800, fill: '#FFFFFF' }, f);

  b.rect('Box', 48, 100, 1184, 660, { fill: '#FFFFFF', cornerRadius: 20, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.text('Manage 82 Registered Attendees & Privileges', 80, 136, { fontSize: 20, fontWeight: 800, fill: '#0F172A' }, f);

  const attendees = [
    { name: 'Pankaj Kumar', email: 'pankaj@pankajkumar.com', role: 'Speaker' },
    { name: 'Lena Lekkou', email: 'lena@uxcommunity.gr', role: 'Speaker' },
    { name: 'Rahul Verma', email: 'rahul@delhi.wordcamp.org', role: 'Organizer' },
    { name: 'Sunil Kumar Sharma', email: 'sunil@wpsimplified.in', role: 'Table Lead' },
  ];
  attendees.forEach((a, i) => {
    const y = 200 + i * 60;
    b.text(a.name, 80, y + 18, { fontSize: 14, fontWeight: 700, fill: '#0F172A' }, f);
    b.text(a.email, 350, y + 18, { fontSize: 12, fill: '#64748B' }, f);
    b.rect('Role', 700, y + 10, 100, 28, { fill: '#E0F2FE', cornerRadius: 14 }, f);
    b.text(a.role, 724, y + 18, { fontSize: 11, fontWeight: 700, fill: '#0284C7' }, f);
    b.rect('Edit Role', 1050, y + 10, 100, 28, { fill: '#F1F5F9', cornerRadius: 6 }, f);
    b.text('Change Role', 1064, y + 17, { fontSize: 11, fontWeight: 700, fill: '#334155' }, f);
    b.line('L', 80, y + 50, 1120, { stroke: '#F1F5F9', strokeWeight: 1 }, f);
  });
}

// AP06: Contributor Day Table Configurator
{
  const f = b.frame('AP06 - Contributor Table Config', 2680, 1000, 1280, 800, { fill: '#F8FAFC', clip: true });
  b.rect('H', 0, 0, 1280, 68, { fill: '#1E293B' }, f);
  b.text('CampBuddy Staff Console · Contributor Tables', 32, 22, { fontSize: 18, fontWeight: 800, fill: '#FFFFFF' }, f);

  b.rect('Box', 48, 100, 1184, 660, { fill: '#FFFFFF', cornerRadius: 20, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.text('Contributor Day Tables & Leads Configuration', 80, 136, { fontSize: 20, fontWeight: 800, fill: '#0F172A' }, f);

  const tables = [
    { name: 'Core PHP & JS', lead: 'Rahul Verma', cap: '25 Contributors' },
    { name: 'Polyglots (Hindi/Regional)', lead: 'Priya Sharma', cap: '30 Contributors' },
    { name: 'Documentation', lead: 'Amit Singh', cap: '20 Contributors' },
    { name: 'Community Outreach', lead: 'Elena Rostova', cap: '20 Contributors' },
  ];
  tables.forEach((t, i) => {
    const y = 200 + i * 60;
    b.text(t.name, 80, y + 18, { fontSize: 14, fontWeight: 700, fill: '#0F172A' }, f);
    b.text('Lead: ' + t.lead, 450, y + 18, { fontSize: 12, fill: '#64748B' }, f);
    b.text(t.cap, 750, y + 18, { fontSize: 12, fill: '#10B981' }, f);
    b.rect('Btn', 1050, y + 10, 80, 28, { fill: '#F1F5F9', cornerRadius: 6 }, f);
    b.text('Configure', 1062, y + 17, { fontSize: 11, fontWeight: 700, fill: '#334155' }, f);
    b.line('L', 80, y + 50, 1120, { stroke: '#F1F5F9', strokeWeight: 1 }, f);
  });
}

// AP07: Mobile App Dynamic Config & Releases
{
  const f = b.frame('AP07 - Mobile App Config & Releases', 40, 1900, 1280, 800, { fill: '#F8FAFC', clip: true });
  b.rect('H', 0, 0, 1280, 68, { fill: '#1E293B' }, f);
  b.text('CampBuddy Staff Console · Mobile App Remote Config & Releases (/admin/mobile-app)', 32, 22, { fontSize: 18, fontWeight: 800, fill: '#FFFFFF' }, f);

  // Left card: App Navigation Tabs & Layout
  b.rect('Nav Box', 48, 100, 570, 660, { fill: '#FFFFFF', cornerRadius: 20, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.text('Mobile Navigation & UI Config', 80, 136, { fontSize: 18, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Configure active tabs, theme palette, and client runtime switches for APK & PWA', 80, 162, { fontSize: 12, fill: '#64748B' }, f);

  const tabs = [
    { num: '1', name: 'Home Hub', desc: 'Live Session Hero, 30s Countdown, ActionGrid', status: 'ACTIVE' },
    { num: '2', name: 'My Day', desc: 'Smart Itinerary, Conflict Resolution, ICS Export', status: 'ACTIVE' },
    { num: '3', name: 'Explore Directory', desc: 'Attendees, Speakers, 17 Sponsors, Live Search', status: 'ACTIVE' },
    { num: '4', name: 'Quests & Badges', desc: 'Contributor Day Roadmaps, XP Rewards', status: 'ACTIVE' },
    { num: '5', name: 'CampCard Studio', desc: 'P2P Offline QR Badge, 900 DPI Canvas', status: 'ACTIVE' },
  ];
  tabs.forEach((t, i) => {
    const y = 200 + i * 65;
    b.rect('Tab Row', 80, y, 506, 52, { fill: '#F8FAFC', stroke: '#E2E8F0', cornerRadius: 10, strokeWeight: 1 }, f);
    b.rect('Handle', 94, y + 14, 24, 24, { fill: '#E2E8F0', cornerRadius: 6 }, f);
    b.text(t.num, 102, y + 18, { fontSize: 12, fontWeight: 800, fill: '#475569' }, f);
    b.text(t.name, 130, y + 12, { fontSize: 13, fontWeight: 700, fill: '#0F172A' }, f);
    b.text(t.desc, 130, y + 30, { fontSize: 10, fill: '#64748B' }, f);
    b.rect('Tag', 510, y + 14, 62, 24, { fill: '#DCFCE7', cornerRadius: 12 }, f);
    b.text(t.status, 522, y + 20, { fontSize: 9, fontWeight: 800, fill: '#15803D' }, f);
  });

  b.text('BRAND PALETTE & ACCENT', 80, 545, { fontSize: 11, fontWeight: 800, fill: '#64748B' }, f);
  b.rect('Color 1', 80, 570, 70, 36, { fill: '#FF4D2D', cornerRadius: 8 }, f);
  b.text('Coral (Primary)', 80, 615, { fontSize: 10, fontWeight: 600, fill: '#0F172A' }, f);
  b.rect('Color 2', 170, 570, 70, 36, { fill: '#0F172A', cornerRadius: 8 }, f);
  b.text('Slate (Dark)', 170, 615, { fontSize: 10, fontWeight: 600, fill: '#0F172A' }, f);
  b.rect('Color 3', 260, 570, 70, 36, { fill: '#F59E0B', cornerRadius: 8 }, f);
  b.text('Amber (Pulse)', 260, 615, { fontSize: 10, fontWeight: 600, fill: '#0F172A' }, f);
  b.rect('Color 4', 350, 570, 70, 36, { fill: '#10B981', cornerRadius: 8 }, f);
  b.text('Emerald (Offline)', 350, 615, { fontSize: 10, fontWeight: 600, fill: '#0F172A' }, f);

  b.rect('Save Config', 80, 660, 506, 44, { fill: '#FF4D2D', cornerRadius: 10 }, f);
  b.text('Publish Dynamic Config to Apps', 230, 674, { fontSize: 13, fontWeight: 700, fill: '#FFFFFF' }, f);

  // Right card: APK Releases & Distribution
  b.rect('APK Box', 650, 100, 582, 660, { fill: '#FFFFFF', cornerRadius: 20, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.text('APK Binary Distribution & Releases (/app/android)', 682, 136, { fontSize: 18, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Host and serve official Android APK builds outside app stores', 682, 162, { fontSize: 12, fill: '#64748B' }, f);

  b.rect('Current Version Card', 682, 196, 518, 120, { fill: '#1E293B', cornerRadius: 16 }, f);
  b.text('ACTIVE PRODUCTION RELEASE', 706, 218, { fontSize: 10, fontWeight: 800, fill: '#F59E0B' }, f);
  b.text('CampBuddy APK v2.4.0 (Build 42)', 706, 240, { fontSize: 18, fontWeight: 800, fill: '#FFFFFF' }, f);
  b.text('Target SDK 34 · Compose Multiplatform · SharedPreferences KeyValueStore · 18.4 MB', 706, 268, { fontSize: 11, fill: '#94A3B8' }, f);
  b.text('Download link: https://campbuddy.in/app/android · 840 Downloads', 706, 288, { fontSize: 11, fill: '#38BDF8' }, f);

  b.rect('Upload Box', 682, 336, 518, 160, { fill: '#F8FAFC', stroke: '#CBD5E1', cornerRadius: 14, dashes: [4, 4], strokeWeight: 1.5 }, f);
  b.text('📦 Drag & drop new .apk release file here', 800, 390, { fontSize: 14, fontWeight: 700, fill: '#334155' }, f);
  b.text('Supports chunked multi-part upload for large builds over flaky connections', 740, 415, { fontSize: 11, fill: '#64748B' }, f);
  b.rect('Browse Btn', 870, 440, 140, 32, { fill: '#0F172A', cornerRadius: 8 }, f);
  b.text('Select APK File', 898, 448, { fontSize: 11, fontWeight: 700, fill: '#FFFFFF' }, f);

  b.text('CLIENT FEATURE SWITCHES', 682, 520, { fontSize: 11, fontWeight: 800, fill: '#64748B' }, f);
  const switches = [
    { title: 'Offline Stale-While-Revalidate Caching', state: 'ON' },
    { title: '10-Minute Prior Push & Local Talk Alerts', state: 'ON' },
    { title: 'Dynamic Schedule Delay Cascade Overlay', state: 'ON' },
  ];
  switches.forEach((sw, i) => {
    const y = 548 + i * 46;
    b.rect('Switch Row', 682, y, 518, 38, { fill: '#F1F5F9', cornerRadius: 8 }, f);
    b.text(sw.title, 702, y + 12, { fontSize: 12, fontWeight: 600, fill: '#0F172A' }, f);
    b.rect('Pill', 1130, y + 8, 50, 22, { fill: '#10B981', cornerRadius: 11 }, f);
    b.text(sw.state, 1146, y + 13, { fontSize: 10, fontWeight: 800, fill: '#FFFFFF' }, f);
  });
}

// AP08: Attendee Moderation & Incident Reports
{
  const f = b.frame('AP08 - Attendee Moderation & Reports', 1360, 1900, 1280, 800, { fill: '#F8FAFC', clip: true });
  b.rect('H', 0, 0, 1280, 68, { fill: '#1E293B' }, f);
  b.text('CampBuddy Staff Console · Safety & Moderation Reports (/admin/events/delhi-2026/reports)', 32, 22, { fontSize: 18, fontWeight: 800, fill: '#FFFFFF' }, f);

  // Top metric chips
  const repStats = [
    { label: 'TOTAL REPORTS', val: '3 Received', color: '#0F172A', x: 48 },
    { label: 'ACTIVE / UNDER REVIEW', val: '1 Pending', color: '#D97706', x: 350 },
    { label: 'RESOLVED SAFELY', val: '2 Closed', color: '#10B981', x: 652 },
    { label: 'SAFETY MARSHALS', val: '4 On Duty', color: '#0284C7', x: 954 },
  ];
  repStats.forEach((rs) => {
    b.rect('Stat Box', rs.x, 90, 278, 80, { fill: '#FFFFFF', stroke: '#E2E8F0', cornerRadius: 14, strokeWeight: 1 }, f);
    b.text(rs.label, rs.x + 20, 108, { fontSize: 10, fontWeight: 800, fill: '#64748B' }, f);
    b.text(rs.val, rs.x + 20, 130, { fontSize: 18, fontWeight: 800, fill: rs.color }, f);
  });

  // Table of reports
  b.rect('Rep Table', 48, 190, 1184, 460, { fill: '#FFFFFF', cornerRadius: 20, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.text('Reported Incidents & Profile Flags', 80, 222, { fontSize: 18, fontWeight: 800, fill: '#0F172A' }, f);

  const reportItems = [
    { id: '#104', type: 'Profile Link Flag', subject: 'Attendee @spambot_in', details: 'Promotional affiliate link placed in CampCard website bio', status: 'UNDER REVIEW', statColor: '#FEF3C7', textColor: '#D97706', actions: ['Dismiss', 'Hide Bio', 'Ban Profile'] },
    { id: '#103', type: 'Hall A Seating Notice', subject: 'Track 1 Main Hall', details: 'Wheelchair ramp clearway temporarily blocked by extra chairs', status: 'RESOLVED', statColor: '#DCFCE7', textColor: '#15803D', actions: ['View Log'] },
    { id: '#102', type: 'Lost Attendee Badge', subject: 'Priya Sharma (Attendee)', details: 'Attendee lost physical lanyard badge; verified photo ID and re-printed', status: 'RESOLVED', statColor: '#DCFCE7', textColor: '#15803D', actions: ['View Log'] },
  ];
  reportItems.forEach((ri, i) => {
    const y = 265 + i * 90;
    b.rect('Row', 80, y, 1120, 76, { fill: '#F8FAFC', stroke: '#E2E8F0', cornerRadius: 12, strokeWeight: 1 }, f);
    b.text(ri.id + ' · ' + ri.type, 100, y + 14, { fontSize: 13, fontWeight: 800, fill: '#0F172A' }, f);
    b.text('Target: ' + ri.subject, 320, y + 14, { fontSize: 12, fontWeight: 700, fill: '#334155' }, f);
    b.text(ri.details, 100, y + 42, { fontSize: 11, fill: '#64748B' }, f);

    b.rect('Status Pill', 780, y + 12, 110, 24, { fill: ri.statColor, cornerRadius: 12 }, f);
    b.text(ri.status, 796, y + 17, { fontSize: 10, fontWeight: 800, fill: ri.textColor }, f);

    let btnX = 910;
    ri.actions.forEach((act) => {
      b.rect('Action Btn', btnX, y + 10, 60, 28, { fill: act === 'Ban Profile' ? '#FEE2E2' : '#F1F5F9', cornerRadius: 6 }, f);
      b.text(act, btnX + 8, y + 17, { fontSize: 10, fontWeight: 700, fill: act === 'Ban Profile' ? '#DC2626' : '#334155' }, f);
      btnX += 66;
    });
  });

  // Emergency safety contacts
  b.rect('Safety Card', 48, 670, 1184, 90, { fill: '#FEF2F2', stroke: '#FCA5A5', cornerRadius: 16, strokeWeight: 1 }, f);
  b.text('🚨 VENUE EMERGENCY DISPATCH PROTOCOL', 76, 690, { fontSize: 11, fontWeight: 800, fill: '#DC2626' }, f);
  b.text('Designated WordCamp Delhi Safety Marshals: Rahul Verma (+91 98100 12345) & NDMC Centre Security Desk (Room 102)', 76, 712, { fontSize: 12, fontWeight: 600, fill: '#991B1B' }, f);
  b.text('All incidents are encrypted and logged in accordance with DPDPA 2023 & WordPress Community Code of Conduct.', 76, 732, { fontSize: 11, fill: '#B91C1C' }, f);
}

// Write the script to scratch/figcraft_multipage_script.json
const outputPath = path.resolve(rootDir, 'scratch', 'figcraft_multipage_script.json');
fs.writeFileSync(outputPath, JSON.stringify(b.commands, null, 2), 'utf8');

console.log(`Generated ${b.commands.length} FigCraft commands across ${b.pages.length} pages (${b.pages.join(', ')}) with ${b.currentScreenFrames.length} total screens.`);
console.log(`Saved script to: ${outputPath}`);
