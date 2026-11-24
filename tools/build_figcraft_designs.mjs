import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const rootDir = path.resolve(__dirname, '..');

class FigCraftBuilder {
  constructor() {
    this.commands = [];
    this.nextId = 2; // Document root page is id: 1
    this.screenFrames = [];
  }

  frame(name, x, y, width, height, props = {}, parent = null) {
    const id = this.nextId++;
    const params = { name, x, y, width, height, props };
    if (parent) params.parent = parent;
    this.commands.push({ command: 'frame.create', params });
    if (!parent) {
      this.screenFrames.push({ id, name, x, y, width, height });
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

  // Mobile Top Status Bar helper
  mobileStatusBar(parent, dark = false) {
    const color = dark ? '#FFFFFF' : '#0F172A';
    this.text('09:41', 24, 14, { fontSize: 13, fontWeight: 700, fill: color }, parent, 'Time');
    this.text('5G', 320, 14, { fontSize: 11, fontWeight: 600, fill: color }, parent, 'Network');
    this.rect('Battery', 345, 15, 22, 11, { fill: color, cornerRadius: 3 }, parent);
    this.rect('Battery Level', 347, 17, 14, 7, { fill: dark ? '#0F172A' : '#FFFFFF', cornerRadius: 1.5 }, parent);
  }

  // Mobile Bottom Navigation Bar helper
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

  // Web Top Navigation Header helper
  webTopNav(parent, activeTab = 'home') {
    this.rect('Top Nav Bar', 0, 0, 1280, 72, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 }, parent);
    this.text('CampBuddy', 48, 22, { fontSize: 22, fontWeight: 800, fill: '#FF4D2D', fontFamily: 'Poppins' }, parent, 'Brand');
    this.rect('WordCamp Pill', 190, 24, 190, 26, { fill: '#FFF7ED', stroke: '#FDBA74', cornerRadius: 13, strokeWeight: 1 }, parent);
    this.text('📍 WordCamp Delhi 2026', 204, 30, { fontSize: 11, fontWeight: 700, fill: '#D97706' }, parent);

    const navItems = [
      { key: 'home', label: 'Home Stage', x: 420 },
      { key: 'schedule', label: 'Schedule', x: 540 },
      { key: 'attendees', label: 'Attendees & Roles', x: 650 },
      { key: 'sponsors', label: 'Sponsors & Steals', x: 800 },
      { key: 'quests', label: 'Contributor Quests', x: 955 },
    ];

    for (const item of navItems) {
      const isActive = item.key === activeTab;
      this.text(item.label, item.x, 26, { fontSize: 13, fontWeight: isActive ? 700 : 500, fill: isActive ? '#FF4D2D' : '#334155' }, parent);
      if (isActive) {
        this.rect('Nav Indicator', item.x, 68, 64, 4, { fill: '#FF4D2D', cornerRadius: 2 }, parent);
      }
    }

    this.rect('Offline Badge', 1120, 20, 112, 32, { fill: '#ECFDF5', stroke: '#A7F3D0', cornerRadius: 16, strokeWeight: 1 }, parent);
    this.text('● 100% Offline', 1134, 28, { fontSize: 11, fontWeight: 700, fill: '#047857' }, parent);
  }
}

const b = new FigCraftBuilder();

// ==========================================
// 1. MOBILE APP (APK) SCREENS (Y = 100)
// ==========================================

// M1: APK - Event Picker & Device Sync
{
  const f = b.frame('APK - Event Picker & Sync', 40, 100, 390, 844, { fill: '#FFF7ED', clip: true });
  b.mobileStatusBar(f);

  b.text('CampBuddy', 20, 52, { fontSize: 22, fontWeight: 800, fill: '#FF4D2D', fontFamily: 'Poppins' }, f, 'App Title');
  b.rect('Offline Badge', 280, 52, 90, 24, { fill: '#064E3B', cornerRadius: 12 }, f);
  b.text('● Offline Ready', 290, 57, { fontSize: 10, fontWeight: 700, fill: '#A7F3D0' }, f);

  // Desktop to Mobile Transfer Card
  b.rect('Sync Card BG', 16, 92, 358, 86, { fill: '#FFFFFF', cornerRadius: 16, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.rect('Sync Icon Box', 28, 104, 38, 38, { fill: '#FFEDD5', cornerRadius: 19 }, f);
  b.text('🔄', 39, 114, { fontSize: 16 }, f);
  b.text('Move data from laptop / PC', 76, 104, { fontSize: 14, fontWeight: 700, fill: '#0F172A' }, f);
  b.text('Sync schedule & CampCard via 6-digit code', 76, 124, { fontSize: 11, fill: '#64748B' }, f);
  b.rect('Code Input', 76, 144, 180, 26, { fill: '#F8FAFC', stroke: '#CBD5E1', cornerRadius: 6 }, f);
  b.text('Code: 482 109', 88, 149, { fontSize: 12, fontWeight: 700, fill: '#0F172A' }, f);
  b.rect('Sync Btn', 266, 144, 96, 26, { fill: '#FF4D2D', cornerRadius: 6 }, f);
  b.text('Link Device', 282, 149, { fontSize: 11, fontWeight: 700, fill: '#FFFFFF' }, f);

  b.text('Choose your WordCamp', 16, 196, { fontSize: 17, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Active & upcoming global community events', 16, 218, { fontSize: 12, fill: '#64748B' }, f);

  // Filter Pills
  const pills = [
    { label: 'All (10)', active: true, x: 16, w: 62 },
    { label: 'India', active: false, x: 84, w: 56 },
    { label: 'Greece', active: false, x: 146, w: 66 },
    { label: 'Kenya', active: false, x: 218, w: 58 },
    { label: 'Indonesia', active: false, x: 282, w: 76 },
  ];
  for (const p of pills) {
    b.rect('Pill', p.x, 240, p.w, 30, { fill: p.active ? '#FF4D2D' : '#FFFFFF', cornerRadius: 15, stroke: p.active ? '#FF4D2D' : '#E2E8F0', strokeWeight: 1 }, f);
    b.text(p.label, p.x + 10, 248, { fontSize: 11, fontWeight: 700, fill: p.active ? '#FFFFFF' : '#0F172A' }, f);
  }

  // Real Event Card 1: Delhi 2026
  b.rect('Event 1 Card', 16, 286, 358, 220, { fill: '#FFFFFF', cornerRadius: 20, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.rect('Logo Box 1', 32, 302, 52, 52, { fill: '#1E293B', cornerRadius: 26 }, f);
  b.text('W', 49, 314, { fontSize: 24, fontWeight: 800, fill: '#FFFFFF' }, f);
  b.rect('Live Dot', 94, 304, 8, 8, { fill: '#10B981', cornerRadius: 4 }, f);
  b.text('ON NOW · 21–22 NOV 2026', 108, 302, { fontSize: 10, fontWeight: 800, fill: '#D97706' }, f);
  b.text('WordCamp Delhi 2026', 94, 318, { fontSize: 17, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('📍 NDMC Convention Centre, New Delhi', 94, 340, { fontSize: 11, fill: '#64748B' }, f);
  b.line('Card Div', 32, 372, 326, { stroke: '#F1F5F9', strokeWeight: 1 }, f);

  b.text('22 Talks · 11 Speakers · 17 Sponsors · Live Delay 0m', 32, 386, { fontSize: 11, fontWeight: 600, fill: '#475569' }, f);
  b.rect('Offline Chip', 32, 410, 110, 24, { fill: '#ECFDF5', stroke: '#A7F3D0', cornerRadius: 12, strokeWeight: 1 }, f);
  b.text('✔ 100% Offline Ready', 40, 415, { fontSize: 10, fontWeight: 700, fill: '#047857' }, f);
  b.rect('Open Btn', 32, 448, 326, 44, { fill: '#FF4D2D', cornerRadius: 12 }, f);
  b.text('Open Event Companion  →', 116, 461, { fontSize: 14, fontWeight: 700, fill: '#FFFFFF' }, f);

  // Real Event Card 2: Athens 2026
  b.rect('Event 2 Card', 16, 520, 358, 140, { fill: '#FFFFFF', cornerRadius: 20, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.rect('Logo Box 2', 32, 536, 44, 44, { fill: '#334155', cornerRadius: 22 }, f);
  b.text('A', 47, 546, { fontSize: 20, fontWeight: 800, fill: '#FFFFFF' }, f);
  b.text('11–12 APR 2026 · ATHENS, GREECE', 88, 536, { fontSize: 10, fontWeight: 800, fill: '#D97706' }, f);
  b.text('WordCamp Athens 2026', 88, 552, { fontSize: 16, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('📍 Megaron Athens International Hall', 88, 572, { fontSize: 11, fill: '#64748B' }, f);
  b.rect('Open Btn 2', 88, 598, 160, 34, { fill: '#F1F5F9', cornerRadius: 8 }, f);
  b.text('34 Talks · 30 Sponsors →', 106, 608, { fontSize: 11, fontWeight: 700, fill: '#0F172A' }, f);

  b.text('Zero-signup · DPDP Act 2023 Compliant · Built by WPSimplified', 30, 800, { fontSize: 10, fill: '#94A3B8' }, f);
}

// M2: APK - Tactile Home Dashboard
{
  const f = b.frame('APK - Tactile Home', 470, 100, 390, 844, { fill: '#FFF7ED', clip: true });
  b.mobileStatusBar(f, true);

  // Hero Card (Navy Gradient #1E293B to #0F172A)
  b.rect('Hero Card', 16, 44, 358, 204, { fill: '#1E293B', cornerRadius: 24, stroke: '#334155', strokeWeight: 1 }, f);
  b.text('10:14 AM', 32, 60, { fontSize: 28, fontWeight: 800, fill: '#FFFFFF' }, f);
  b.text('WordCamp Delhi · Day 1 of 2', 32, 94, { fontSize: 11, fontWeight: 600, fill: '#94A3B8' }, f);
  b.rect('Logo Mini', 304, 60, 48, 48, { fill: '#334155', cornerRadius: 24 }, f);
  b.text('W', 320, 72, { fontSize: 20, fontWeight: 800, fill: '#FF4D2D' }, f);
  b.text('Live at NDMC Convention Centre', 32, 118, { fontSize: 14, fontWeight: 700, fill: '#FFFFFF' }, f);

  // Next Session Teaser Box with Real Speaker
  b.rect('Next Teaser', 28, 142, 334, 88, { fill: '#0F172A', cornerRadius: 16, stroke: '#334155', strokeWeight: 1 }, f);
  b.text('⚡ NEXT SESSION STARTS IN 16 MIN', 42, 154, { fontSize: 10, fontWeight: 800, fill: '#F59E0B' }, f);
  b.text('Scaling Headless WordPress & SEO', 42, 172, { fontSize: 13, fontWeight: 700, fill: '#FFFFFF' }, f);
  b.text('Track 1 · Hall A · Pankaj Kumar (SEO Lead)', 42, 192, { fontSize: 11, fill: '#94A3B8' }, f);
  b.rect('View Schedule Btn', 270, 168, 76, 34, { fill: '#D97706', cornerRadius: 17 }, f);
  b.text('VIEW', 292, 178, { fontSize: 11, fontWeight: 800, fill: '#FFFFFF' }, f);

  // Real People Strip with Scraped Avatars
  b.text('People & Wave Matches', 16, 260, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('See all 82 →', 315, 262, { fontSize: 11, fontWeight: 700, fill: '#D33417' }, f);

  b.rect('Wave Circle', 16, 282, 52, 52, { fill: '#FFEDD5', stroke: '#D97706', cornerRadius: 26, strokeWeight: 1.5 }, f);
  b.text('👋', 30, 296, { fontSize: 20 }, f);
  b.text('Wave', 26, 340, { fontSize: 10, fontWeight: 600, fill: '#0F172A' }, f);

  // Real Speakers / Attendees from DB
  const realPeople = [
    { name: 'Pankaj K.', color: '#38BDF8', x: 80, initial: 'PK', role: 'Speaker' },
    { name: 'Lena L.', color: '#F472B6', x: 144, initial: 'LL', role: 'Speaker' },
    { name: 'Patrick L.', color: '#4ADE80', x: 208, initial: 'PL', role: 'Speaker' },
    { name: 'Chandra A.', color: '#A78BFA', x: 272, initial: 'CA', role: 'Speaker' },
    { name: 'Sunil S.', color: '#FB923C', x: 336, initial: 'SS', role: 'Attendee' },
  ];
  for (const p of realPeople) {
    b.rect('Avatar', p.x, 282, 52, 52, { fill: p.color, cornerRadius: 26 }, f);
    b.text(p.initial, p.x + 16, 298, { fontSize: 15, fontWeight: 800, fill: '#FFFFFF' }, f);
    b.ellipse('Online Dot', p.x + 38, 320, 12, 12, { fill: '#10B981', stroke: '#FFFFFF', strokeWeight: 2 }, f);
    b.text(p.name, p.x + 4, 340, { fontSize: 10, fontWeight: 600, fill: '#0F172A' }, f);
  }

  // 2x3 Event Hubs ActionGrid
  b.text('Event Hubs', 16, 368, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);

  const hubs = [
    { title: 'My Day', sub: '4 saved talks', icon: '📅', x: 16, y: 390 },
    { title: 'Schedule', sub: '22 talks · 2 tracks', icon: '🎤', x: 200, y: 390 },
    { title: 'My CampCard', sub: 'P2P QR Badge', icon: '🪪', x: 16, y: 486 },
    { title: 'Attendees', sub: '82 in directory', icon: '👥', x: 200, y: 486 },
    { title: 'Quests', sub: 'Contributor Day', icon: '🎯', x: 16, y: 582 },
    { title: 'Sponsors & Swag', sub: '17 sponsor steals', icon: '🎁', x: 200, y: 582 },
  ];
  for (const h of hubs) {
    b.rect('Hub Tile', h.x, h.y, 174, 84, { fill: '#FFFFFF', cornerRadius: 16, stroke: '#E2E8F0', strokeWeight: 1 }, f);
    b.rect('Hub Icon Box', h.x + 12, h.y + 12, 34, 34, { fill: '#FFEDD5', cornerRadius: 17 }, f);
    b.text(h.icon, h.x + 20, h.y + 19, { fontSize: 15 }, f);
    b.text(h.title, h.x + 12, h.y + 52, { fontSize: 13, fontWeight: 800, fill: '#0F172A' }, f);
    b.text(h.sub, h.x + 12, h.y + 68, { fontSize: 10, fill: '#64748B' }, f);
  }

  // Floating SOS / Sticky Help Bar
  b.rect('SOS Bar', 16, 680, 358, 50, { fill: '#EF4444', cornerRadius: 25 }, f);
  b.text('🛟  Need Help at WordCamp?', 32, 696, { fontSize: 13, fontWeight: 700, fill: '#FFFFFF' }, f);
  b.text('Incident Support & Staff Hub', 32, 712, { fontSize: 10, fill: 'rgba(255,255,255,0.85)' }, f);
  b.rect('Help Badge', 304, 690, 56, 30, { fill: 'rgba(255,255,255,0.25)', cornerRadius: 15 }, f);
  b.text('HELP', 316, 698, { fontSize: 11, fontWeight: 800, fill: '#FFFFFF' }, f);

  b.text('Privacy · Terms · DPDP Act · Staff Sign In', 65, 750, { fontSize: 10, fill: '#64748B' }, f);
  b.mobileBottomNav(f, 'home');
}

// M3: APK - My Day Schedule
{
  const f = b.frame('APK - My Day Schedule', 900, 100, 390, 844, { fill: '#FFF7ED', clip: true });
  b.mobileStatusBar(f);

  b.text('My Day', 16, 52, { fontSize: 22, fontWeight: 800, fill: '#0F172A' }, f);
  b.rect('Day Tab 1', 16, 86, 174, 36, { fill: '#FF4D2D', cornerRadius: 18 }, f);
  b.text('Day 1 · Nov 21', 60, 96, { fontSize: 12, fontWeight: 700, fill: '#FFFFFF' }, f);
  b.rect('Day Tab 2', 200, 86, 174, 36, { fill: '#FFFFFF', stroke: '#E2E8F0', cornerRadius: 18, strokeWeight: 1 }, f);
  b.text('Day 2 · Nov 22', 244, 96, { fontSize: 12, fontWeight: 600, fill: '#64748B' }, f);

  // Schedule Health Banner
  b.rect('Health Banner', 16, 134, 358, 54, { fill: '#FEF3C7', stroke: '#FDE68A', cornerRadius: 14, strokeWeight: 1 }, f);
  b.text('📅 4 saved · ⏰ 2h 45m · 🛡 0 conflicts', 28, 145, { fontSize: 11, fontWeight: 700, fill: '#92400E' }, f);
  b.rect('Alert Pill', 256, 144, 106, 26, { fill: '#FFFFFF', stroke: '#D97706', cornerRadius: 13, strokeWeight: 1 }, f);
  b.text('🔔 10m Alerts', 268, 150, { fontSize: 10, fontWeight: 700, fill: '#92400E' }, f);

  // Real Talk 1
  b.rect('Talk 1 BG', 16, 200, 358, 120, { fill: '#FFFFFF', cornerRadius: 18, stroke: '#FF4D2D', strokeWeight: 2 }, f);
  b.rect('Track Pill 1', 28, 212, 70, 20, { fill: '#FFEDD5', cornerRadius: 10 }, f);
  b.text('TRACK 1', 38, 216, { fontSize: 9, fontWeight: 800, fill: '#D33417' }, f);
  b.text('10:30 AM – 11:15 AM · Hall A', 106, 215, { fontSize: 11, fontWeight: 600, fill: '#64748B' }, f);
  b.text('★', 346, 212, { fontSize: 16, fill: '#D97706' }, f);
  b.text('Scaling Headless WordPress & SEO', 28, 238, { fontSize: 15, fontWeight: 800, fill: '#0F172A' }, f);
  b.rect('Speaker Pic', 28, 268, 36, 36, { fill: '#38BDF8', cornerRadius: 18 }, f);
  b.text('PK', 36, 276, { fontSize: 12, fontWeight: 800, fill: '#FFFFFF' }, f);
  b.text('Pankaj Kumar', 72, 270, { fontSize: 12, fontWeight: 700, fill: '#0F172A' }, f);
  b.text('Founder & SEO Lead · Pankaj Kumar SEO', 72, 286, { fontSize: 10, fill: '#64748B' }, f);

  // Real Talk 2
  b.rect('Talk 2 BG', 16, 332, 358, 120, { fill: '#FFFFFF', cornerRadius: 18, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.rect('Track Pill 2', 28, 344, 70, 20, { fill: '#E0F2FE', cornerRadius: 10 }, f);
  b.text('TRACK 2', 38, 348, { fontSize: 9, fontWeight: 800, fill: '#0369A1' }, f);
  b.text('11:30 AM – 12:15 PM · Hall B', 106, 347, { fontSize: 11, fontWeight: 600, fill: '#64748B' }, f);
  b.text('★', 346, 344, { fontSize: 16, fill: '#D97706' }, f);
  b.text('Designing Accessible Enterprise Themes', 28, 370, { fontSize: 15, fontWeight: 800, fill: '#0F172A' }, f);
  b.rect('Speaker Pic 2', 28, 400, 36, 36, { fill: '#F472B6', cornerRadius: 18 }, f);
  b.text('LL', 38, 408, { fontSize: 12, fontWeight: 800, fill: '#FFFFFF' }, f);
  b.text('Lena Lekkou', 72, 402, { fontSize: 12, fontWeight: 700, fill: '#0F172A' }, f);
  b.text('UI/UX Architect · WordCamp Community', 72, 418, { fontSize: 10, fill: '#64748B' }, f);

  // Real Lunch Break
  b.rect('Lunch BG', 16, 464, 358, 60, { fill: '#F8FAFC', cornerRadius: 14, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.text('🍴 12:30 PM – 01:45 PM · Lunch & Sponsor Swag Steals', 28, 482, { fontSize: 12, fontWeight: 700, fill: '#475569' }, f);
  b.text('Dining Hall & Exhibition Arena (Sponsor Booths)', 28, 500, { fontSize: 10, fill: '#94A3B8' }, f);

  // Real Talk 3
  b.rect('Talk 3 BG', 16, 536, 358, 120, { fill: '#FFFFFF', cornerRadius: 18, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.rect('Track Pill 3', 28, 548, 70, 20, { fill: '#FFEDD5', cornerRadius: 10 }, f);
  b.text('TRACK 1', 38, 552, { fontSize: 9, fontWeight: 800, fill: '#D33417' }, f);
  b.text('02:00 PM – 02:45 PM · Hall A', 106, 551, { fontSize: 11, fontWeight: 600, fill: '#64748B' }, f);
  b.text('★', 346, 548, { fontSize: 16, fill: '#CBD5E1' }, f);
  b.text('Modern Gutenberg & Block Interactivity', 28, 574, { fontSize: 15, fontWeight: 800, fill: '#0F172A' }, f);
  b.rect('Speaker Pic 3', 28, 604, 36, 36, { fill: '#10B981', cornerRadius: 18 }, f);
  b.text('PL', 38, 612, { fontSize: 12, fontWeight: 800, fill: '#FFFFFF' }, f);
  b.text('Patrick Lumumba', 72, 606, { fontSize: 12, fontWeight: 700, fill: '#0F172A' }, f);
  b.text('Full-Stack Contributor · Nairobi Community', 72, 622, { fontSize: 10, fill: '#64748B' }, f);

  b.mobileBottomNav(f, 'my-day');
}

// M4: APK - CampCard Swap
{
  const f = b.frame('APK - CampCard Swap', 1330, 100, 390, 844, { fill: '#FFF7ED', clip: true });
  b.mobileStatusBar(f);

  b.text('CampCard Badge', 16, 52, { fontSize: 22, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Offline P2P QR badge · Zero server storage', 16, 76, { fontSize: 12, fill: '#64748B' }, f);

  // CampCard Surface
  b.rect('Card Outer', 20, 106, 350, 480, { fill: '#FFFFFF', cornerRadius: 24, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.rect('Card Header Bar', 20, 106, 350, 60, { fill: '#FF4D2D', cornerRadius: 24 }, f);
  b.rect('Header Fix', 20, 136, 350, 30, { fill: '#FF4D2D' }, f);
  b.text('OFFICIAL ATTENDEE BADGE', 36, 122, { fontSize: 11, fontWeight: 800, fill: 'rgba(255,255,255,0.9)' }, f);
  b.text('WordCamp Delhi 2026', 36, 138, { fontSize: 10, fill: '#FFFFFF' }, f);

  b.text('Sunil Kumar Sharma', 36, 186, { fontSize: 20, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Full Stack WordPress Developer', 36, 210, { fontSize: 12, fontWeight: 600, fill: '#FF4D2D' }, f);
  b.text('WPSimplified · New Delhi, India', 36, 226, { fontSize: 11, fill: '#64748B' }, f);

  // QR Code Area
  b.rect('QR Container', 85, 256, 220, 220, { fill: '#F8FAFC', stroke: '#E2E8F0', cornerRadius: 16, strokeWeight: 1 }, f);
  b.rect('QR P1', 105, 276, 50, 50, { fill: '#0F172A', cornerRadius: 4 }, f);
  b.rect('QR P2', 235, 276, 50, 50, { fill: '#0F172A', cornerRadius: 4 }, f);
  b.rect('QR P3', 105, 406, 50, 50, { fill: '#0F172A', cornerRadius: 4 }, f);
  b.rect('QR D1', 170, 276, 45, 50, { fill: '#0F172A', cornerRadius: 3 }, f);
  b.rect('QR D2', 170, 346, 115, 45, { fill: '#0F172A', cornerRadius: 3 }, f);
  b.rect('QR D3', 105, 346, 50, 45, { fill: '#0F172A', cornerRadius: 3 }, f);
  b.rect('QR D4', 170, 406, 115, 50, { fill: '#0F172A', cornerRadius: 3 }, f);

  b.text('Point camera to swap vCard & social links', 65, 496, { fontSize: 11, fontWeight: 600, fill: '#64748B' }, f);
  b.text('Includes GitHub, Twitter/X & LinkedIn', 95, 514, { fontSize: 10, fill: '#94A3B8' }, f);

  // P2P Camera Scan Trigger
  b.rect('Scan Trigger', 20, 604, 350, 50, { fill: '#FF4D2D', cornerRadius: 14 }, f);
  b.text('📷 Scan Peer CampCard with Camera', 68, 622, { fontSize: 13, fontWeight: 800, fill: '#FFFFFF' }, f);

  // Action Buttons
  b.rect('Share Btn', 20, 666, 168, 42, { fill: '#FFFFFF', stroke: '#E2E8F0', cornerRadius: 10, strokeWeight: 1 }, f);
  b.text('📤 Share vCard', 58, 680, { fontSize: 12, fontWeight: 700, fill: '#0F172A' }, f);

  b.rect('Wallet Btn', 202, 666, 168, 42, { fill: '#FFFFFF', stroke: '#E2E8F0', cornerRadius: 10, strokeWeight: 1 }, f);
  b.text('🎟 Apple/Google Pass', 222, 680, { fontSize: 12, fontWeight: 700, fill: '#0F172A' }, f);

  b.mobileBottomNav(f, 'camp-card');
}

// M5: APK - Explore Roster & Real Sponsors
{
  const f = b.frame('APK - Explore & Sponsors', 1760, 100, 390, 844, { fill: '#FFF7ED', clip: true });
  b.mobileStatusBar(f);

  b.text('Explore & Sponsors', 16, 52, { fontSize: 22, fontWeight: 800, fill: '#0F172A' }, f);

  // Search input
  b.rect('Search Box', 16, 86, 358, 42, { fill: '#FFFFFF', stroke: '#CBD5E1', cornerRadius: 12, strokeWeight: 1 }, f);
  b.text('🔍 Search 17 sponsors, speakers, topics...', 32, 99, { fontSize: 12, fill: '#94A3B8' }, f);

  // Real Sponsor Spotlight Grid (Delhi Real Sponsors)
  b.text('Featured Event Sponsors', 16, 142, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);

  const realSponsors = [
    { name: 'Elementor Ltd', tier: 'TERA SPONSOR', deal: '50% off Pro Studio', bg: '#0F172A', color: '#FF4D2D', x: 16, y: 164 },
    { name: 'WPFusion', tier: 'GIGA SPONSOR', deal: 'Full CRM Sync Trial', bg: '#1E293B', color: '#38BDF8', x: 200, y: 164 },
    { name: 'Housewise', tier: 'MEGA SPONSOR', deal: 'Exclusive Community Swag', bg: '#334155', color: '#4ADE80', x: 16, y: 260 },
    { name: 'Bluehost', tier: 'GLOBAL SPONSOR', deal: 'Free Managed Cloud', bg: '#0369A1', color: '#FFFFFF', x: 200, y: 260 },
  ];

  for (const sp of realSponsors) {
    b.rect('Sponsor Card', sp.x, sp.y, 174, 84, { fill: '#FFFFFF', cornerRadius: 16, stroke: '#E2E8F0', strokeWeight: 1 }, f);
    b.rect('Sponsor Logo Box', sp.x + 10, sp.y + 10, 36, 36, { fill: sp.bg, cornerRadius: 8 }, f);
    b.text(sp.name.charAt(0), sp.x + 22, sp.y + 20, { fontSize: 16, fontWeight: 800, fill: sp.color }, f);
    b.text(sp.tier, sp.x + 52, sp.y + 12, { fontSize: 8, fontWeight: 800, fill: '#D97706' }, f);
    b.text(sp.name, sp.x + 52, sp.y + 26, { fontSize: 11, fontWeight: 800, fill: '#0F172A' }, f);
    b.line('Sp Line', sp.x + 10, sp.y + 54, 154, { stroke: '#F1F5F9', strokeWeight: 1 }, f);
    b.text(sp.deal, sp.x + 10, sp.y + 64, { fontSize: 10, fontWeight: 600, fill: '#059669' }, f);
  }

  // Real Speaker Directory
  b.text('Featured Speakers', 16, 364, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);

  const realSpeakers = [
    { name: 'Pankaj Kumar', topic: 'Technical SEO for High-Scale WP', role: 'Pankaj Kumar SEO', x: 16, y: 386 },
    { name: 'Lena Lekkou', topic: 'Accessible Design Systems in 2026', role: 'UI/UX Community Lead', x: 16, y: 462 },
    { name: 'Patrick Lumumba', topic: 'Full-Stack Blocks & Interactivity', role: 'Nairobi Contributor', x: 16, y: 538 },
  ];

  for (const spk of realSpeakers) {
    b.rect('Speaker Card', spk.x, spk.y, 358, 64, { fill: '#FFFFFF', cornerRadius: 14, stroke: '#E2E8F0', strokeWeight: 1 }, f);
    b.rect('Speaker Avatar Box', spk.x + 12, spk.y + 12, 40, 40, { fill: '#1E293B', cornerRadius: 20 }, f);
    b.text(spk.name.charAt(0), spk.x + 26, spk.y + 22, { fontSize: 16, fontWeight: 800, fill: '#FFFFFF' }, f);
    b.text(spk.name, spk.x + 60, spk.y + 14, { fontSize: 13, fontWeight: 800, fill: '#0F172A' }, f);
    b.text(spk.topic, spk.x + 60, spk.y + 30, { fontSize: 11, fontWeight: 600, fill: '#FF4D2D' }, f);
    b.text(spk.role, spk.x + 60, spk.y + 45, { fontSize: 10, fill: '#64748B' }, f);
    b.rect('Meet Btn', spk.x + 280, spk.y + 16, 66, 32, { fill: '#FFEDD5', stroke: '#FDBA74', cornerRadius: 8, strokeWeight: 1 }, f);
    b.text('Wave 👋', spk.x + 292, spk.y + 26, { fontSize: 11, fontWeight: 700, fill: '#D33417' }, f);
  }

  // Real Sponsor Deals List
  b.text('Sponsor Steals & Free Software', 16, 618, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);
  b.rect('Deal Banner', 16, 640, 358, 60, { fill: '#F0FDF4', stroke: '#BBF7D0', cornerRadius: 14, strokeWeight: 1 }, f);
  b.text('🎁 Automattic & Woo: Free Dev Hosting for 1 Year', 28, 654, { fontSize: 12, fontWeight: 700, fill: '#166534' }, f);
  b.text('Available to all verified WordCamp attendees at Booth 4', 28, 674, { fontSize: 10, fill: '#15803D' }, f);

  b.mobileBottomNav(f, 'explore');
}

// M6: APK - Quests & Contributor Day
{
  const f = b.frame('APK - Quests & Contribute', 2190, 100, 390, 844, { fill: '#FFF7ED', clip: true });
  b.mobileStatusBar(f);

  b.text('Contributor Quests', 16, 52, { fontSize: 22, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Make your first contribution to open source WordPress', 16, 76, { fontSize: 12, fill: '#64748B' }, f);

  // Contributor Day Progress Bar
  b.rect('Progress Card', 16, 102, 358, 86, { fill: '#1E293B', cornerRadius: 18 }, f);
  b.text('CONTRIBUTOR DAY ROADMAP', 28, 116, { fontSize: 10, fontWeight: 800, fill: '#F59E0B' }, f);
  b.text('Level 2 Contributor · 3 of 5 Quests Done', 28, 134, { fontSize: 14, fontWeight: 800, fill: '#FFFFFF' }, f);
  b.rect('Bar Outer', 28, 156, 334, 10, { fill: '#334155', cornerRadius: 5 }, f);
  b.rect('Bar Inner', 28, 156, 200, 10, { fill: '#10B981', cornerRadius: 5 }, f);
  b.text('60%', 336, 134, { fontSize: 12, fontWeight: 800, fill: '#10B981' }, f);

  // Quest 1
  b.rect('Quest 1', 16, 202, 358, 84, { fill: '#FFFFFF', stroke: '#10B981', cornerRadius: 16, strokeWeight: 1.5 }, f);
  b.rect('Check 1', 28, 220, 24, 24, { fill: '#10B981', cornerRadius: 12 }, f);
  b.text('✔', 35, 225, { fontSize: 12, fontWeight: 800, fill: '#FFFFFF' }, f);
  b.text('Set up WordPress.org Contributor ID', 64, 218, { fontSize: 13, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Linked profile: @sunilsharma (Verified badge)', 64, 238, { fontSize: 11, fill: '#64748B' }, f);
  b.text('+100 XP', 312, 220, { fontSize: 11, fontWeight: 800, fill: '#10B981' }, f);

  // Quest 2
  b.rect('Quest 2', 16, 298, 358, 84, { fill: '#FFFFFF', stroke: '#10B981', cornerRadius: 16, strokeWeight: 1.5 }, f);
  b.rect('Check 2', 28, 316, 24, 24, { fill: '#10B981', cornerRadius: 12 }, f);
  b.text('✔', 35, 321, { fontSize: 12, fontWeight: 800, fill: '#FFFFFF' }, f);
  b.text('Translate 10 Core Strings to Hindi / Regional', 64, 314, { fontSize: 13, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Polyglots Table · Lead: Rahul Verma', 64, 334, { fontSize: 11, fill: '#64748B' }, f);
  b.text('+150 XP', 312, 316, { fontSize: 11, fontWeight: 800, fill: '#10B981' }, f);

  // Quest 3 (In progress)
  b.rect('Quest 3', 16, 394, 358, 96, { fill: '#FFFFFF', stroke: '#FF4D2D', cornerRadius: 16, strokeWeight: 2 }, f);
  b.rect('Check 3', 28, 412, 24, 24, { fill: '#FFEDD5', stroke: '#FF4D2D', cornerRadius: 12, strokeWeight: 1.5 }, f);
  b.text('3', 36, 417, { fontSize: 11, fontWeight: 800, fill: '#D33417' }, f);
  b.text('Submit First Core Documentation Patch', 64, 410, { fontSize: 13, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Docs Table · Track: Gutenberg handbook improvements', 64, 430, { fontSize: 11, fill: '#64748B' }, f);
  b.rect('Action Btn', 64, 452, 140, 28, { fill: '#FF4D2D', cornerRadius: 6 }, f);
  b.text('Open Docs Table →', 76, 459, { fontSize: 11, fontWeight: 700, fill: '#FFFFFF' }, f);
  b.text('+250 XP', 312, 412, { fontSize: 11, fontWeight: 800, fill: '#FF4D2D' }, f);

  // Contributor Teams Grid
  b.text('Contributor Day Tables', 16, 506, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);

  const teams = [
    { name: 'Core PHP & JS', table: 'Table 1', leads: '3 Leads' },
    { name: 'Polyglots', table: 'Table 2', leads: '5 Leads' },
    { name: 'Documentation', table: 'Table 3', leads: '2 Leads' },
    { name: 'Community & Kids', table: 'Table 4', leads: '4 Leads' },
  ];

  teams.forEach((t, i) => {
    const x = 16 + (i % 2) * 184;
    const y = 528 + Math.floor(i / 2) * 78;
    b.rect('Team Card', x, y, 174, 68, { fill: '#FFFFFF', cornerRadius: 14, stroke: '#E2E8F0', strokeWeight: 1 }, f);
    b.text(t.table, x + 12, y + 10, { fontSize: 9, fontWeight: 800, fill: '#D97706' }, f);
    b.text(t.name, x + 12, y + 26, { fontSize: 12, fontWeight: 800, fill: '#0F172A' }, f);
    b.text(t.leads + ' · Join Table →', x + 12, y + 44, { fontSize: 10, fontWeight: 600, fill: '#059669' }, f);
  });

  b.mobileBottomNav(f, 'quest');
}

// ==========================================
// 2. WEBSITE (FRONTEND) SCREENS (Y = 1050)
// ==========================================

// W1: Web - Landing Hero & Offline Stage
{
  const f = b.frame('Web - Landing Hero Stage', 40, 1050, 1280, 800, { fill: '#FFF7ED', clip: true });
  b.webTopNav(f, 'home');

  // Hero Canvas Stage (Navy #1E293B)
  b.rect('Web Hero Stage', 48, 96, 1184, 380, { fill: '#1E293B', cornerRadius: 28 }, f);
  b.text('LIVE CONFERENCE DASHBOARD · DAY 1', 88, 136, { fontSize: 12, fontWeight: 800, fill: '#F59E0B', letterSpacing: 1.2 }, f);
  b.text('WordCamp Delhi 2026', 88, 164, { fontSize: 38, fontWeight: 800, fill: '#FFFFFF', fontFamily: 'Baloo 2' }, f);
  b.text('November 21–22, 2026 · NDMC Convention Centre, Sansad Marg, New Delhi', 88, 220, { fontSize: 16, fill: '#94A3B8' }, f);

  // Live countdown clock badge
  b.rect('Clock Badge', 88, 260, 240, 48, { fill: '#0F172A', stroke: '#334155', cornerRadius: 24, strokeWeight: 1 }, f);
  b.ellipse('Green Pulse', 106, 276, 16, 16, { fill: '#10B981' }, f);
  b.text('LIVE: 10:14 AM IST', 132, 276, { fontSize: 14, fontWeight: 800, fill: '#FFFFFF' }, f);

  // CTAs
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

  // 3-Column Value Features below Hero
  const features = [
    { title: '100% Offline Reliable', desc: 'Pre-caches complete sessions, speaker bios, and sponsor deals. Works seamlessly even if venue Wi-Fi fails.', icon: '⚡', x: 48 },
    { title: 'Zero App Store Barrier', desc: 'Open instantly on desktop, laptop, Android or iPhone. PWA standalone installation available with one tap.', icon: '📱', x: 450 },
    { title: 'P2P Privacy First', desc: 'No accounts, zero tracker cookies, DPDP Act 2023 certified. Your personal notes and contacts stay on your device.', icon: '🛡', x: 852 },
  ];

  for (const feat of features) {
    b.rect('Feat Box', feat.x, 506, 380, 180, { fill: '#FFFFFF', cornerRadius: 20, stroke: '#E2E8F0', strokeWeight: 1 }, f);
    b.rect('Feat Icon Box', feat.x + 24, 530, 48, 48, { fill: '#FFEDD5', cornerRadius: 24 }, f);
    b.text(feat.icon, feat.x + 36, 542, { fontSize: 20 }, f);
    b.text(feat.title, feat.x + 24, 596, { fontSize: 16, fontWeight: 800, fill: '#0F172A' }, f);
    b.text(feat.desc, feat.x + 24, 624, { fontSize: 12, fill: '#64748B' }, f);
  }

  // Footer Row
  b.line('Foot Line', 48, 720, 1184, { stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.text('CampBuddy Open Source Companion · Built by WPSimplified · Connected to official WordCamp.org REST Feeds', 48, 744, { fontSize: 12, fill: '#94A3B8' }, f);
}

// W2: Web - Event Directory & Country Hub
{
  const f = b.frame('Web - Event Directory', 1360, 1050, 1280, 800, { fill: '#FFF7ED', clip: true });
  b.webTopNav(f, 'home');

  b.text('Upcoming & Active WordCamps', 48, 100, { fontSize: 28, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Live conference companions synced with official schedules and attendee directories', 48, 134, { fontSize: 14, fill: '#64748B' }, f);

  // Country Filter Buttons
  const countries = ['All Events (10)', 'India (3)', 'Greece (1)', 'Kenya (1)', 'Indonesia (1)', 'Nepal (1)', 'Switzerland (1)'];
  countries.forEach((c, i) => {
    const x = 48 + i * 150;
    const active = i === 0;
    b.rect('C Pill', x, 168, 140, 36, { fill: active ? '#FF4D2D' : '#FFFFFF', stroke: active ? '#FF4D2D' : '#E2E8F0', cornerRadius: 18, strokeWeight: 1 }, f);
    b.text(c, x + 16, 178, { fontSize: 12, fontWeight: 700, fill: active ? '#FFFFFF' : '#334155' }, f);
  });

  // Event Cards Grid (2 rows x 3 columns)
  const dirEvents = [
    { name: 'WordCamp Delhi 2026', loc: 'NDMC Convention Centre, New Delhi', date: '21–22 NOV 2026', status: 'ON NOW', talks: '22 Talks', spk: '11 Speakers', spon: '17 Sponsors' },
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

  b.text('Showing 6 of 10 registered WordCamps · All data verified via official REST endpoints', 48, 730, { fontSize: 12, fill: '#94A3B8' }, f);
}

// W3: Web - Multi-Track Schedule Explorer
{
  const f = b.frame('Web - Schedule Explorer', 2680, 1050, 1280, 800, { fill: '#FFF7ED', clip: true });
  b.webTopNav(f, 'schedule');

  b.text('Multi-Track Program & Session Explorer', 48, 100, { fontSize: 26, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Live timeline with live delay compensation, track filters, and speaker profiles', 48, 132, { fontSize: 13, fill: '#64748B' }, f);

  // Track Selector Tabs
  b.rect('Track 1 Tab', 48, 164, 180, 40, { fill: '#FF4D2D', cornerRadius: 10 }, f);
  b.text('Track 1 (Hall A)', 86, 176, { fontSize: 13, fontWeight: 700, fill: '#FFFFFF' }, f);

  b.rect('Track 2 Tab', 240, 164, 180, 40, { fill: '#FFFFFF', stroke: '#CBD5E1', cornerRadius: 10, strokeWeight: 1 }, f);
  b.text('Track 2 (Hall B)', 278, 176, { fontSize: 13, fontWeight: 600, fill: '#334155' }, f);

  b.rect('All Tracks Tab', 432, 164, 180, 40, { fill: '#FFFFFF', stroke: '#CBD5E1', cornerRadius: 10, strokeWeight: 1 }, f);
  b.text('Grid View (All Tracks)', 464, 176, { fontSize: 13, fontWeight: 600, fill: '#334155' }, f);

  // Schedule Timeline Grid (2 Columns: Track 1 & Track 2)
  b.rect('Track 1 Container', 48, 224, 580, 500, { fill: '#FFFFFF', cornerRadius: 20, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.text('HALL A · TECHNICAL & PERFORMANCE', 68, 246, { fontSize: 12, fontWeight: 800, fill: '#FF4D2D' }, f);

  // Session A1
  b.rect('Session A1', 68, 274, 540, 100, { fill: '#FFF7ED', stroke: '#FF4D2D', cornerRadius: 14, strokeWeight: 1.5 }, f);
  b.text('10:30 AM – 11:15 AM · 45 mins', 84, 288, { fontSize: 11, fontWeight: 700, fill: '#D97706' }, f);
  b.text('Scaling Headless WordPress & SEO', 84, 308, { fontSize: 15, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Pankaj Kumar (Pankaj Kumar SEO) · Technical Track', 84, 332, { fontSize: 12, fill: '#64748B' }, f);
  b.rect('Star A1', 548, 288, 36, 36, { fill: '#FFEDD5', cornerRadius: 18 }, f);
  b.text('★', 558, 298, { fontSize: 14, fill: '#D97706' }, f);

  // Session A2
  b.rect('Session A2', 68, 390, 540, 100, { fill: '#FFFFFF', stroke: '#E2E8F0', cornerRadius: 14, strokeWeight: 1 }, f);
  b.text('11:30 AM – 12:15 PM · 45 mins', 84, 404, { fontSize: 11, fontWeight: 600, fill: '#64748B' }, f);
  b.text('Full-Site Editing & Custom Block Governance', 84, 424, { fontSize: 15, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Patrick Lumumba (Core Contributor) · Architecture Track', 84, 448, { fontSize: 12, fill: '#64748B' }, f);
  b.rect('Star A2', 548, 404, 36, 36, { fill: '#F1F5F9', cornerRadius: 18 }, f);
  b.text('☆', 558, 414, { fontSize: 14, fill: '#94A3B8' }, f);

  // Track 2 Container
  b.rect('Track 2 Container', 652, 224, 580, 500, { fill: '#FFFFFF', cornerRadius: 20, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.text('HALL B · DESIGN, PRODUCT & BUSINESS', 672, 246, { fontSize: 12, fontWeight: 800, fill: '#0284C7' }, f);

  // Session B1
  b.rect('Session B1', 672, 274, 540, 100, { fill: '#FFFFFF', stroke: '#E2E8F0', cornerRadius: 14, strokeWeight: 1 }, f);
  b.text('10:30 AM – 11:15 AM · 45 mins', 688, 288, { fontSize: 11, fontWeight: 600, fill: '#64748B' }, f);
  b.text('Building Accessible Community Experiences', 688, 308, { fontSize: 15, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Lena Lekkou (UI/UX Architect) · Design Track', 688, 332, { fontSize: 12, fill: '#64748B' }, f);
  b.rect('Star B1', 1152, 288, 36, 36, { fill: '#F1F5F9', cornerRadius: 18 }, f);
  b.text('☆', 1162, 298, { fontSize: 14, fill: '#94A3B8' }, f);

  // Session B2
  b.rect('Session B2', 672, 390, 540, 100, { fill: '#F0F9FF', stroke: '#0284C7', cornerRadius: 14, strokeWeight: 1.5 }, f);
  b.text('11:30 AM – 12:15 PM · 45 mins', 688, 404, { fontSize: 11, fontWeight: 700, fill: '#0284C7' }, f);
  b.text('Monetization & Agency Growth Strategies', 688, 424, { fontSize: 15, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Chandra Alif Irawan (Agency Founder) · Business Track', 688, 448, { fontSize: 12, fill: '#64748B' }, f);
  b.rect('Star B2', 1152, 404, 36, 36, { fill: '#E0F2FE', cornerRadius: 18 }, f);
  b.text('★', 1162, 414, { fontSize: 14, fill: '#0284C7' }, f);

  b.text('Automatic schedule conflict checker warns if you star 2 concurrent talks.', 48, 744, { fontSize: 12, fill: '#64748B' }, f);
}

// W4: Web - Attendees & Sponsor Wall
{
  const f = b.frame('Web - Attendees Sponsors', 4000, 1050, 1280, 800, { fill: '#FFF7ED', clip: true });
  b.webTopNav(f, 'sponsors');

  b.text('Event Sponsors & Exclusive Community Steals', 48, 100, { fontSize: 26, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('17 Verified Sponsors for WordCamp Delhi 2026 with high-resolution brand assets', 48, 132, { fontSize: 13, fill: '#64748B' }, f);

  // Sponsor Tier 1 (Tera Sponsors)
  b.text('TERA & GIGA SPONSORS', 48, 172, { fontSize: 12, fontWeight: 800, fill: '#D97706', letterSpacing: 1.2 }, f);

  const topSponsors = [
    { name: 'Elementor Ltd', logo: 'Elementor.webp', tier: 'Tera Sponsor', deal: '50% Off Studio Suite for attendees', site: 'elementor.com', x: 48 },
    { name: 'WPFusion', logo: 'WPFusion.webp', tier: 'Giga Sponsor', deal: 'Full CRM Integration Suite License', site: 'wpfusion.com', x: 450 },
    { name: 'Housewise', logo: 'Housewise.webp', tier: 'Mega Sponsor', deal: 'Custom Tech Consultation & Swag', site: 'housewise.in', x: 852 },
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

  // Sponsor Tier 2 (Mega & Global Sponsors)
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

  b.text('All sponsor logos and URLs are fetched directly from https://delhi.wordcamp.org/2026/sponsors/', 48, 730, { fontSize: 12, fill: '#94A3B8' }, f);
}

// W5: Web - Live Event Dashboard & Helpdesk
{
  const f = b.frame('Web - Live Event Stage', 5320, 1050, 1280, 800, { fill: '#FFF7ED', clip: true });
  b.webTopNav(f, 'home');

  b.text('Live Attendee Pulse & Incident Helpdesk', 48, 100, { fontSize: 26, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('On-site communications, Code of Conduct support, and volunteer leads', 48, 132, { fontSize: 13, fill: '#64748B' }, f);

  // Live Emergency & Code of Conduct Card
  b.rect('Help Desk Hero', 48, 170, 780, 260, { fill: '#FEF2F2', stroke: '#FECACA', cornerRadius: 20, strokeWeight: 1.5 }, f);
  b.rect('SOS Icon Box', 76, 196, 52, 52, { fill: '#EF4444', cornerRadius: 26 }, f);
  b.text('🛟', 90, 208, { fontSize: 22 }, f);
  b.text('Code of Conduct & Safety Helpdesk', 144, 202, { fontSize: 18, fontWeight: 800, fill: '#991B1B' }, f);
  b.text('WordCamp Delhi is dedicated to providing a harassment-free experience for everyone.', 144, 228, { fontSize: 12, fill: '#7F1D1D' }, f);
  b.text('If you need immediate support, locate any organizer or volunteer wearing a STAFF lanyard.', 144, 246, { fontSize: 12, fill: '#7F1D1D' }, f);

  b.rect('Call Emergency Btn', 76, 310, 220, 44, { fill: '#EF4444', cornerRadius: 10 }, f);
  b.text('📞 Call Venue Helpdesk', 104, 324, { fontSize: 12, fontWeight: 700, fill: '#FFFFFF' }, f);

  b.rect('Chat Staff Btn', 312, 310, 220, 44, { fill: '#FFFFFF', stroke: '#DC2626', cornerRadius: 10, strokeWeight: 1 }, f);
  b.text('💬 Message Organizers', 340, 324, { fontSize: 12, fontWeight: 700, fill: '#DC2626' }, f);

  // Venue & Wi-Fi Details Card
  b.rect('Venue Info Card', 852, 170, 380, 260, { fill: '#FFFFFF', stroke: '#E2E8F0', cornerRadius: 20, strokeWeight: 1 }, f);
  b.text('📍 Venue & Connectivity', 876, 196, { fontSize: 16, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('NDMC Convention Centre, Sansad Marg, New Delhi', 876, 222, { fontSize: 12, fill: '#64748B' }, f);

  b.rect('Wifi Box', 876, 256, 332, 68, { fill: '#F8FAFC', stroke: '#CBD5E1', cornerRadius: 12, strokeWeight: 1 }, f);
  b.text('📶 Venue Wi-Fi: WCDelhi2026', 892, 272, { fontSize: 12, fontWeight: 700, fill: '#0F172A' }, f);
  b.text('Password: wordpressrocks', 892, 294, { fontSize: 12, fontWeight: 700, fill: '#FF4D2D' }, f);

  b.rect('Map Link', 876, 342, 332, 40, { fill: '#FFEDD5', cornerRadius: 10 }, f);
  b.text('Open Google Maps Navigation  →', 934, 354, { fontSize: 11, fontWeight: 700, fill: '#D33417' }, f);

  b.text('CampBuddy guarantees 100% offline access when Wi-Fi is intermittent.', 48, 730, { fontSize: 12, fill: '#94A3B8' }, f);
}

// ==========================================
// 3. PWA (PROGRESSIVE WEB APP) (Y = 1950)
// ==========================================

// P1: PWA - Install Hero
{
  const f = b.frame('PWA - Install Offline Hero', 40, 1950, 390, 844, { fill: '#FFF7ED', clip: true });

  // Browser Chrome simulation
  b.rect('Browser Chrome', 0, 0, 390, 78, { fill: '#F1F5F9' }, f);
  b.rect('URL Bar', 16, 38, 358, 32, { fill: '#FFFFFF', stroke: '#CBD5E1', cornerRadius: 16, strokeWeight: 1 }, f);
  b.text('🔒 campbuddy.club', 140, 46, { fontSize: 11, fontWeight: 600, fill: '#0F172A' }, f);

  // A2HS Modal Banner
  b.rect('A2HS Card', 16, 96, 358, 180, { fill: '#1E293B', cornerRadius: 20, stroke: '#334155', strokeWeight: 1 }, f);
  b.rect('App Icon Box', 32, 114, 48, 48, { fill: '#FF4D2D', cornerRadius: 24 }, f);
  b.text('C', 48, 126, { fontSize: 24, fontWeight: 800, fill: '#FFFFFF' }, f);

  b.text('Install CampBuddy PWA', 92, 116, { fontSize: 16, fontWeight: 800, fill: '#FFFFFF' }, f);
  b.text('Add to Home Screen for 100% offline access', 92, 138, { fontSize: 11, fill: '#94A3B8' }, f);
  b.text('✔ Zero App Store account needed\n✔ Instant launch · No 50MB download\n✔ Works when venue Wi-Fi goes down', 92, 160, { fontSize: 10, fill: '#CBD5E1' }, f);

  b.rect('Install A2HS Btn', 32, 226, 326, 38, { fill: '#FF4D2D', cornerRadius: 10 }, f);
  b.text('Install PWA to Home Screen  ⚡', 100, 237, { fontSize: 12, fontWeight: 700, fill: '#FFFFFF' }, f);

  // Service Worker Cache Status
  b.rect('Cache Status', 16, 290, 358, 64, { fill: '#ECFDF5', stroke: '#A7F3D0', cornerRadius: 14, strokeWeight: 1 }, f);
  b.text('⚡ Service Worker Active · 48 Assets Cached', 30, 304, { fontSize: 11, fontWeight: 700, fill: '#047857' }, f);
  b.text('IndexedDB schedule & CampCard encrypted locally', 30, 324, { fontSize: 10, fill: '#059669' }, f);

  b.text('Choose event to store offline:', 16, 372, { fontSize: 13, fontWeight: 800, fill: '#0F172A' }, f);

  // Delhi PWA Event
  b.rect('PWA Ev 1', 16, 396, 358, 80, { fill: '#FFFFFF', cornerRadius: 16, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.rect('Ev 1 Logo', 30, 412, 48, 48, { fill: '#1E293B', cornerRadius: 24 }, f);
  b.text('D', 46, 424, { fontSize: 20, fontWeight: 800, fill: '#FF4D2D' }, f);
  b.text('WordCamp Delhi 2026', 88, 416, { fontSize: 15, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('22 Talks · Full Schedule Synced', 88, 438, { fontSize: 11, fill: '#64748B' }, f);
  b.rect('Enter Pill', 300, 420, 60, 30, { fill: '#FF4D2D', cornerRadius: 15 }, f);
  b.text('OPEN', 314, 428, { fontSize: 11, fontWeight: 800, fill: '#FFFFFF' }, f);

  // Nairobi PWA Event
  b.rect('PWA Ev 2', 16, 490, 358, 80, { fill: '#FFFFFF', cornerRadius: 16, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.rect('Ev 2 Logo', 30, 506, 48, 48, { fill: '#334155', cornerRadius: 24 }, f);
  b.text('N', 46, 518, { fontSize: 20, fontWeight: 800, fill: '#FFFFFF' }, f);
  b.text('WordCamp Nairobi 2026', 88, 510, { fontSize: 15, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('18 Speakers · Full Program Synced', 88, 532, { fontSize: 11, fill: '#64748B' }, f);
  b.rect('Enter Pill 2', 300, 514, 60, 30, { fill: '#F1F5F9', cornerRadius: 15 }, f);
  b.text('SYNC', 314, 522, { fontSize: 11, fontWeight: 800, fill: '#0F172A' }, f);

  b.text('CampBuddy PWA runs completely standalone once installed.', 44, 760, { fontSize: 10, fill: '#94A3B8' }, f);
}

// P2: PWA - Standalone Dashboard
{
  const f = b.frame('PWA - Standalone Dashboard', 470, 1950, 390, 844, { fill: '#FFF7ED', clip: true });

  // PWA Standalone Header
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

  b.text('Quick Event Hubs', 16, 240, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);

  const pwaHubs = [
    { title: 'My Schedule', sub: '4 saved talks', icon: '📅', x: 16, y: 262 },
    { title: 'Full Program', sub: '22 talks & tracks', icon: '🎤', x: 200, y: 262 },
    { title: 'Offline Card', sub: 'QR Badge', icon: '🪪', x: 16, y: 356 },
    { title: 'Attendees', sub: 'Directory & roles', icon: '👥', x: 200, y: 356 },
    { title: 'Quests', sub: 'Roadmap', icon: '🎯', x: 16, y: 450 },
    { title: 'Deals & Steals', sub: '17 tools', icon: '🎁', x: 200, y: 450 },
  ];
  for (const h of pwaHubs) {
    b.rect('PWA Hub Tile', h.x, h.y, 174, 82, { fill: '#FFFFFF', cornerRadius: 16, stroke: '#E2E8F0', strokeWeight: 1 }, f);
    b.rect('PWA Hub Icon', h.x + 12, h.y + 12, 32, 32, { fill: '#FFEDD5', cornerRadius: 16 }, f);
    b.text(h.icon, h.x + 18, h.y + 18, { fontSize: 14 }, f);
    b.text(h.title, h.x + 12, h.y + 48, { fontSize: 13, fontWeight: 800, fill: '#0F172A' }, f);
    b.text(h.sub, h.x + 12, h.y + 64, { fontSize: 10, fill: '#64748B' }, f);
  }

  b.rect('PWA SOS Bar', 16, 680, 358, 50, { fill: '#EF4444', cornerRadius: 25 }, f);
  b.text('🛟  Need Help at WordCamp?', 32, 696, { fontSize: 13, fontWeight: 700, fill: '#FFFFFF' }, f);
  b.text('Helpdesk & Organizers', 32, 712, { fontSize: 10, fill: 'rgba(255,255,255,0.85)' }, f);
  b.rect('PWA Help Badge', 304, 690, 56, 30, { fill: 'rgba(255,255,255,0.25)', cornerRadius: 15 }, f);
  b.text('HELP', 316, 698, { fontSize: 11, fontWeight: 800, fill: '#FFFFFF' }, f);

  b.mobileBottomNav(f, 'home');
}

// P3: PWA - Offline Schedule
{
  const f = b.frame('PWA - Offline Schedule', 900, 1950, 390, 844, { fill: '#FFF7ED', clip: true });

  b.rect('PWA Top Bar 2', 0, 0, 390, 44, { fill: '#1E293B' }, f);
  b.text('My Offline Schedule', 16, 14, { fontSize: 12, fontWeight: 700, fill: '#FFFFFF' }, f);

  b.text('Smart Schedule & Conflicts', 16, 60, { fontSize: 20, fontWeight: 800, fill: '#0F172A' }, f);

  b.rect('Conflict Card', 16, 96, 358, 70, { fill: '#FEF2F2', stroke: '#FECACA', cornerRadius: 14, strokeWeight: 1 }, f);
  b.text('⚠ SCHEDULE OVERLAP DETECTED', 28, 110, { fontSize: 11, fontWeight: 800, fill: '#991B1B' }, f);
  b.text('You have starred two talks at 11:30 AM in Track 1 and Track 2.', 28, 128, { fontSize: 10, fill: '#7F1D1D' }, f);
  b.text('Tap to resolve track preference →', 28, 144, { fontSize: 10, fontWeight: 700, fill: '#B91C1C' }, f);

  b.rect('Conflict Talk 1', 16, 180, 358, 90, { fill: '#FFFFFF', stroke: '#EF4444', cornerRadius: 14, strokeWeight: 1.5 }, f);
  b.text('TRACK 1 · 11:30 AM', 28, 194, { fontSize: 10, fontWeight: 800, fill: '#D33417' }, f);
  b.text('Full-Site Editing & Custom Block Governance', 28, 212, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Patrick Lumumba · Hall A', 28, 234, { fontSize: 11, fill: '#64748B' }, f);
  b.rect('Keep 1', 290, 204, 70, 30, { fill: '#FF4D2D', cornerRadius: 6 }, f);
  b.text('KEEP', 310, 212, { fontSize: 10, fontWeight: 700, fill: '#FFFFFF' }, f);

  b.rect('Conflict Talk 2', 16, 280, 358, 90, { fill: '#FFFFFF', stroke: '#CBD5E1', cornerRadius: 14, strokeWeight: 1 }, f);
  b.text('TRACK 2 · 11:30 AM', 28, 294, { fontSize: 10, fontWeight: 800, fill: '#0284C7' }, f);
  b.text('Designing Accessible Enterprise Themes', 28, 312, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Lena Lekkou · Hall B', 28, 334, { fontSize: 11, fill: '#64748B' }, f);
  b.rect('Remove 2', 290, 304, 70, 30, { fill: '#F1F5F9', cornerRadius: 6 }, f);
  b.text('REMOVE', 300, 312, { fontSize: 10, fontWeight: 700, fill: '#64748B' }, f);

  b.rect('Notification Box', 16, 390, 358, 80, { fill: '#FFFFFF', cornerRadius: 16, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.text('🔔 10-Minute Prior Offline Alerts', 28, 406, { fontSize: 13, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Browser vibration & sound alert before your saved session starts.', 28, 426, { fontSize: 10, fill: '#64748B' }, f);
  b.rect('Toggle On', 290, 412, 68, 30, { fill: '#10B981', cornerRadius: 15 }, f);
  b.text('ON ✔', 308, 420, { fontSize: 10, fontWeight: 800, fill: '#FFFFFF' }, f);

  b.mobileBottomNav(f, 'my-day');
}

// P4: PWA - Offline CampCard
{
  const f = b.frame('PWA - Offline CampCard', 1330, 1950, 390, 844, { fill: '#FFF7ED', clip: true });

  b.rect('PWA Top Bar 3', 0, 0, 390, 44, { fill: '#1E293B' }, f);
  b.text('Offline CampCard & QR Scan', 16, 14, { fontSize: 12, fontWeight: 700, fill: '#FFFFFF' }, f);

  b.rect('PWA Card Badge', 24, 60, 342, 430, { fill: '#FFFFFF', cornerRadius: 24, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.rect('PWA Badge Top', 24, 60, 342, 54, { fill: '#1E293B', cornerRadius: 24 }, f);
  b.rect('Top Fix', 24, 84, 342, 30, { fill: '#1E293B' }, f);
  b.text('OFFLINE DIGITAL PASS · NO WI-FI NEEDED', 44, 76, { fontSize: 10, fontWeight: 800, fill: '#F59E0B' }, f);
  b.text('WordCamp Delhi 2026', 44, 94, { fontSize: 9, fill: '#94A3B8' }, f);

  b.text('Sunil Kumar Sharma', 44, 136, { fontSize: 19, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('WordPress Engineer · WPSimplified', 44, 160, { fontSize: 12, fontWeight: 600, fill: '#FF4D2D' }, f);

  b.rect('PWA QR Area', 95, 190, 200, 200, { fill: '#F8FAFC', stroke: '#E2E8F0', cornerRadius: 14, strokeWeight: 1 }, f);
  b.rect('QR P1', 115, 210, 46, 46, { fill: '#0F172A', cornerRadius: 4 }, f);
  b.rect('QR P2', 229, 210, 46, 46, { fill: '#0F172A', cornerRadius: 4 }, f);
  b.rect('QR P3', 115, 324, 46, 46, { fill: '#0F172A', cornerRadius: 4 }, f);
  b.rect('QR D1', 176, 210, 38, 46, { fill: '#0F172A', cornerRadius: 3 }, f);
  b.rect('QR D2', 176, 280, 99, 40, { fill: '#0F172A', cornerRadius: 3 }, f);
  b.rect('QR D3', 115, 276, 46, 36, { fill: '#0F172A', cornerRadius: 3 }, f);
  b.rect('QR D4', 176, 324, 99, 46, { fill: '#0F172A', cornerRadius: 3 }, f);

  b.text('Zero-server camera swap: scan directly on phone', 55, 410, { fontSize: 10, fill: '#64748B' }, f);

  b.rect('Scan Camera Btn', 24, 510, 342, 52, { fill: '#FF4D2D', cornerRadius: 14 }, f);
  b.text('📷 Scan Peer CampCard (Offline Camera)', 64, 528, { fontSize: 13, fontWeight: 800, fill: '#FFFFFF' }, f);

  b.rect('vCard Btn', 24, 574, 164, 42, { fill: '#FFFFFF', stroke: '#E2E8F0', cornerRadius: 10, strokeWeight: 1 }, f);
  b.text('📇 Export vCard', 56, 588, { fontSize: 11, fontWeight: 700, fill: '#0F172A' }, f);

  b.rect('Wallet Btn', 202, 574, 164, 42, { fill: '#FFFFFF', stroke: '#E2E8F0', cornerRadius: 10, strokeWeight: 1 }, f);
  b.text('🎟 Apple/Google Pass', 224, 588, { fontSize: 11, fontWeight: 700, fill: '#0F172A' }, f);

  b.mobileBottomNav(f, 'camp-card');
}

// ==========================================
// 4. ADMIN PANEL SCREENS (Y = 2900)
// ==========================================

// A1: Admin - Desktop Control Center
{
  const f = b.frame('Admin - Desktop Control Center', 40, 2900, 1280, 800, { fill: '#F8FAFC', clip: true });

  // Admin Top Bar
  b.rect('Admin Header', 0, 0, 1280, 68, { fill: '#1E293B' }, f);
  b.text('CampBuddy Staff Console', 32, 22, { fontSize: 18, fontWeight: 800, fill: '#FFFFFF' }, f);
  b.rect('Badge', 270, 20, 100, 28, { fill: '#FF4D2D', cornerRadius: 14 }, f);
  b.text('SUPER ADMIN', 282, 27, { fontSize: 10, fontWeight: 800, fill: '#FFFFFF' }, f);

  b.text('Event: WordCamp Delhi 2026', 800, 26, { fontSize: 13, fontWeight: 700, fill: '#F59E0B' }, f);
  b.rect('Switch Ev Btn', 1010, 18, 120, 32, { fill: '#334155', cornerRadius: 8 }, f);
  b.text('Switch Event ▾', 1026, 26, { fontSize: 12, fill: '#FFFFFF' }, f);
  b.rect('Sign Out', 1146, 18, 90, 32, { fill: '#DC2626', cornerRadius: 8 }, f);
  b.text('Sign Out', 1166, 26, { fontSize: 12, fontWeight: 700, fill: '#FFFFFF' }, f);

  // Left Sidebar
  b.rect('Sidebar', 0, 68, 240, 732, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 }, f);

  const menu = [
    { label: '📊 Dashboard Overview', active: true, y: 92 },
    { label: '⏱ Live Delays & Schedule', active: false, y: 140 },
    { label: '📢 Broadcast Push Alerts', active: false, y: 188 },
    { label: '🎁 Sponsors & Tiers', active: false, y: 236 },
    { label: '👥 Attendee Roster Roles', active: false, y: 284 },
    { label: '🎯 Contributor Quests', active: false, y: 332 },
    { label: '🛡 CoC Safety Reports (0)', active: false, y: 380 },
    { label: '🔄 Ingestion Feeds (10/10)', active: false, y: 428 },
    { label: '⚙ Settings & API Keys', active: false, y: 476 },
  ];

  for (const m of menu) {
    if (m.active) {
      b.rect('Active BG', 12, m.y, 216, 38, { fill: '#FFEDD5', cornerRadius: 8 }, f);
      b.text(m.label, 24, m.y + 11, { fontSize: 12, fontWeight: 800, fill: '#D33417' }, f);
    } else {
      b.text(m.label, 24, m.y + 11, { fontSize: 12, fontWeight: 600, fill: '#475569' }, f);
    }
  }

  // Main Content Area
  b.text('Dashboard Overview · Live Operations', 272, 92, { fontSize: 22, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Real-time sync telemetry from 1,248 attendee devices and official WordCamp REST endpoints', 272, 122, { fontSize: 13, fill: '#64748B' }, f);

  // 4 Top Metric Cards
  const adminMetrics = [
    { num: '1,248', label: 'Synced Attendee Devices', sub: '92% offline ready', icon: '📱', x: 272 },
    { num: '22 Talks', label: 'Program Sessions Live', sub: '2 tracks active', icon: '🎤', x: 520 },
    { num: '17 Logos', label: 'Verified Sponsor Logos', sub: '100% scraped clean', icon: '🎁', x: 768 },
    { num: '0 Min', label: 'Active Schedule Delay', sub: 'All tracks on time', icon: '⏱', x: 1016 },
  ];

  for (const met of adminMetrics) {
    b.rect('Metric Card', met.x, 150, 232, 110, { fill: '#FFFFFF', cornerRadius: 16, stroke: '#E2E8F0', strokeWeight: 1 }, f);
    b.text(met.icon, met.x + 16, 170, { fontSize: 20 }, f);
    b.text(met.num, met.x + 50, 168, { fontSize: 22, fontWeight: 800, fill: '#0F172A' }, f);
    b.text(met.label, met.x + 16, 206, { fontSize: 12, fontWeight: 700, fill: '#334155' }, f);
    b.text(met.sub, met.x + 16, 226, { fontSize: 11, fill: '#10B981' }, f);
  }

  // Left Column: Live Ingestion Feed Health
  b.rect('Feed Table Box', 272, 280, 580, 480, { fill: '#FFFFFF', cornerRadius: 20, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.text('Official Event Feeds & Scraper Status', 296, 306, { fontSize: 16, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Hourly cron verified · Fallback image scrapers active', 296, 328, { fontSize: 12, fill: '#64748B' }, f);

  const feeds = [
    { endpoint: '/wp-json/wp/v2/sessions', count: '22 items', status: '200 OK (Clean)', time: '1 min ago' },
    { endpoint: '/wp-json/wp/v2/speakers', count: '11 items', status: '200 OK (With Avatars)', time: '1 min ago' },
    { endpoint: '/sponsors/ (Scraper Map)', count: '17 logos', status: '100% High-Res Match', time: '1 min ago' },
    { endpoint: '/attendees/ (Roster)', count: '82 attendees', status: 'Synced w/ Gravatar', time: '5 mins ago' },
  ];

  feeds.forEach((fd, i) => {
    const y = 360 + i * 70;
    b.rect('Row', 296, y, 532, 58, { fill: '#F8FAFC', cornerRadius: 10 }, f);
    b.text(fd.endpoint, 312, y + 14, { fontSize: 13, fontWeight: 700, fill: '#0F172A' }, f);
    b.text(fd.count, 312, y + 34, { fontSize: 11, fill: '#64748B' }, f);
    b.rect('Pill', 670, y + 16, 140, 26, { fill: '#ECFDF5', stroke: '#A7F3D0', cornerRadius: 13, strokeWeight: 1 }, f);
    b.text(fd.status, 680, y + 22, { fontSize: 10, fontWeight: 700, fill: '#047857' }, f);
  });

  b.rect('Force Refresh Btn', 296, 670, 200, 44, { fill: '#FF4D2D', cornerRadius: 10 }, f);
  b.text('Force Refetch All Feeds  🔄', 320, 684, { fontSize: 12, fontWeight: 700, fill: '#FFFFFF' }, f);

  // Right Column: Active Delays & Live Broadcast Pulse
  b.rect('Right Box', 872, 280, 376, 480, { fill: '#FFFFFF', cornerRadius: 20, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.text('Quick Broadcaster & Incident Pulse', 896, 306, { fontSize: 16, fontWeight: 800, fill: '#0F172A' }, f);

  b.rect('Broadcast Mini', 896, 344, 328, 140, { fill: '#FFF7ED', stroke: '#FDBA74', cornerRadius: 14, strokeWeight: 1 }, f);
  b.text('📢 Quick Broadcast Announcement', 912, 362, { fontSize: 13, fontWeight: 800, fill: '#D33417' }, f);
  b.text('Send push notification to all 1,248 phones.', 912, 384, { fontSize: 11, fill: '#9A3412' }, f);
  b.rect('Q Input', 912, 404, 296, 32, { fill: '#FFFFFF', stroke: '#FDBA74', cornerRadius: 6 }, f);
  b.text('e.g. Keynote starting in 10 mins in Hall A', 922, 412, { fontSize: 11, fill: '#94A3B8' }, f);
  b.rect('Send Q', 912, 444, 120, 28, { fill: '#FF4D2D', cornerRadius: 6 }, f);
  b.text('Dispatch Alert', 934, 451, { fontSize: 11, fontWeight: 700, fill: '#FFFFFF' }, f);

  b.text('Safety & Code of Conduct Reports', 896, 514, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);
  b.rect('Safe Status', 896, 536, 328, 70, { fill: '#F0FDF4', stroke: '#BBF7D0', cornerRadius: 14, strokeWeight: 1 }, f);
  b.text('✔ Zero Open Incidents Reported', 912, 552, { fontSize: 12, fontWeight: 800, fill: '#166534' }, f);
  b.text('All organizer help channels operating normally.', 912, 574, { fontSize: 11, fill: '#15803D' }, f);
}

// A2: Admin - Live Delay & Broadcast Dispatcher
{
  const f = b.frame('Admin - Delay & Broadcast', 1360, 2900, 1280, 800, { fill: '#F8FAFC', clip: true });

  // Admin Top Bar
  b.rect('Admin Header 2', 0, 0, 1280, 68, { fill: '#1E293B' }, f);
  b.text('CampBuddy Staff Console', 32, 22, { fontSize: 18, fontWeight: 800, fill: '#FFFFFF' }, f);
  b.text('Event: WordCamp Delhi 2026', 800, 26, { fontSize: 13, fontWeight: 700, fill: '#F59E0B' }, f);

  // Left Sidebar
  b.rect('Sidebar 2', 0, 68, 240, 732, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.text('⏱ Live Delays & Broadcasts', 24, 103, { fontSize: 12, fontWeight: 800, fill: '#D33417' }, f);

  b.text('Live Schedule Delay & Instant Broadcast Dispatcher', 272, 92, { fontSize: 22, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Adjust session countdowns across all attendee phones without requiring app reloads or server queries', 272, 122, { fontSize: 13, fill: '#64748B' }, f);

  // Left Side: Live Delay Broadcaster
  b.rect('Delay Container', 272, 160, 470, 560, { fill: '#FFFFFF', cornerRadius: 20, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.text('⏱ Running Late / Delay Broadcaster', 296, 186, { fontSize: 16, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Pushes live delta offset to IndexedDB & native storage on all attendee devices.', 296, 210, { fontSize: 11, fill: '#64748B' }, f);

  b.text('Select Affected Track:', 296, 248, { fontSize: 12, fontWeight: 700, fill: '#334155' }, f);
  b.rect('Track Sel 1', 296, 268, 422, 40, { fill: '#F8FAFC', stroke: '#CBD5E1', cornerRadius: 8 }, f);
  b.text('All Tracks (Hall A & Hall B)', 312, 280, { fontSize: 12, fill: '#0F172A' }, f);

  b.text('Delay Duration Offset:', 296, 328, { fontSize: 12, fontWeight: 700, fill: '#334155' }, f);

  const delayOpts = [
    { label: '+10 Mins', active: false, x: 296 },
    { label: '+15 Mins', active: true, x: 406 },
    { label: '+30 Mins', active: false, x: 516 },
    { label: 'Clear', active: false, x: 626 },
  ];

  for (const opt of delayOpts) {
    b.rect('Delay Pill', opt.x, 348, 98, 44, { fill: opt.active ? '#FFEDD5' : '#F1F5F9', stroke: opt.active ? '#FF4D2D' : '#CBD5E1', cornerRadius: 8, strokeWeight: opt.active ? 2 : 1 }, f);
    b.text(opt.label, opt.x + 20, 362, { fontSize: 12, fontWeight: opt.active ? 800 : 600, fill: opt.active ? '#D33417' : '#334155' }, f);
  }

  b.text('Preview Effect on Attendee Screens:', 296, 420, { fontSize: 12, fontWeight: 700, fill: '#334155' }, f);
  b.rect('Preview Card', 296, 440, 422, 100, { fill: '#FFF7ED', stroke: '#FDBA74', cornerRadius: 12, strokeWeight: 1 }, f);
  b.text('⚠️ SCHEDULE RUNNING 15 MINS LATE', 312, 456, { fontSize: 11, fontWeight: 800, fill: '#9A3412' }, f);
  b.text('Pankaj Kumar\'s talk shifted: 10:30 AM → 10:45 AM', 312, 478, { fontSize: 12, fontWeight: 700, fill: '#0F172A' }, f);
  b.text('Updated automatically across 1,248 devices', 312, 502, { fontSize: 11, fill: '#64748B' }, f);

  b.rect('Apply Delay Btn', 296, 570, 422, 48, { fill: '#D97706', cornerRadius: 10 }, f);
  b.text('Broadcast +15 Min Delay  ⏰', 416, 585, { fontSize: 13, fontWeight: 700, fill: '#FFFFFF' }, f);

  // Right Side: High Priority Push Announcement
  b.rect('Push Container', 766, 160, 470, 560, { fill: '#FFFFFF', cornerRadius: 20, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.text('📢 High-Priority Push Announcement', 790, 186, { fontSize: 16, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Sends local browser & APK notification banner immediately.', 790, 210, { fontSize: 11, fill: '#64748B' }, f);

  b.text('Notification Title:', 790, 248, { fontSize: 12, fontWeight: 700, fill: '#334155' }, f);
  b.rect('Push Title Input', 790, 268, 422, 40, { fill: '#F8FAFC', stroke: '#CBD5E1', cornerRadius: 8 }, f);
  b.text('Lunch is served at Dining Hall B!', 806, 280, { fontSize: 12, fill: '#0F172A' }, f);

  b.text('Message Body:', 790, 328, { fontSize: 12, fontWeight: 700, fill: '#334155' }, f);
  b.rect('Push Body Input', 790, 348, 422, 120, { fill: '#F8FAFC', stroke: '#CBD5E1', cornerRadius: 8 }, f);
  b.text('Hot meals and dietary options are ready. Please visit sponsor booths for exclusive swag giveaways during the break.', 806, 364, { fontSize: 12, fill: '#0F172A' }, f);

  b.rect('Send Push Btn', 790, 570, 422, 48, { fill: '#FF4D2D', cornerRadius: 10 }, f);
  b.text('Send Live Push to 1,248 Phones  🚀', 880, 585, { fontSize: 13, fontWeight: 700, fill: '#FFFFFF' }, f);
}

// A3: Admin - Sponsors & Media Library
{
  const f = b.frame('Admin - Sponsors & Media', 2680, 2900, 1280, 800, { fill: '#F8FAFC', clip: true });

  b.rect('Admin Header 3', 0, 0, 1280, 68, { fill: '#1E293B' }, f);
  b.text('CampBuddy Staff Console', 32, 22, { fontSize: 18, fontWeight: 800, fill: '#FFFFFF' }, f);
  b.text('Event: WordCamp Delhi 2026', 800, 26, { fontSize: 13, fontWeight: 700, fill: '#F59E0B' }, f);

  b.rect('Sidebar 3', 0, 68, 240, 732, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.text('🎁 Sponsors & Media Library', 24, 103, { fontSize: 12, fontWeight: 800, fill: '#D33417' }, f);

  b.text('Sponsor Management & Scraped Media Asset Health', 272, 92, { fontSize: 22, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('17 Sponsors successfully verified from public page with SVG guard checks and high-res logos', 272, 122, { fontSize: 13, fill: '#64748B' }, f);

  // Table of Real Sponsors
  b.rect('Sponsor Table Box', 272, 160, 964, 560, { fill: '#FFFFFF', cornerRadius: 20, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  
  // Table Header
  b.rect('TH BG', 272, 160, 964, 48, { fill: '#F8FAFC', cornerRadius: 20 }, f);
  b.text('SPONSOR NAME', 300, 178, { fontSize: 11, fontWeight: 800, fill: '#475569' }, f);
  b.text('TIER LEVEL', 520, 178, { fontSize: 11, fontWeight: 800, fill: '#475569' }, f);
  b.text('LOGO URL & MEDIA HEALTH', 700, 178, { fontSize: 11, fontWeight: 800, fill: '#475569' }, f);
  b.text('ACTION', 1120, 178, { fontSize: 11, fontWeight: 800, fill: '#475569' }, f);

  const adminSponsors = [
    { name: 'Elementor Ltd', tier: 'Tera Sponsor', logo: '.../files/2026/11/Elementor.webp', status: '200 OK' },
    { name: 'WPFusion', tier: 'Giga Sponsor', logo: '.../files/2026/11/WPFusion.webp', status: '200 OK' },
    { name: 'Housewise', tier: 'Mega Sponsor', logo: '.../files/2026/11/Housewise.webp', status: '200 OK' },
    { name: 'Bluehost', tier: 'Global Sponsor', logo: '.../files/2026/08/Bluehost.png', status: '200 OK' },
    { name: 'Hostinger', tier: 'Global Sponsor', logo: '.../files/2026/08/Hostinger.png', status: '200 OK' },
    { name: 'Automattic', tier: 'Global Sponsor', logo: '.../files/2026/08/Automattic.png', status: '200 OK' },
    { name: 'SEOPress', tier: 'Local Sponsor', logo: '.../files/2026/09/SEOPress.webp', status: '200 OK' },
  ];

  adminSponsors.forEach((as, i) => {
    const y = 218 + i * 54;
    b.text(as.name, 300, y + 12, { fontSize: 13, fontWeight: 700, fill: '#0F172A' }, f);
    b.rect('Tier Pill', 520, y + 6, 120, 26, { fill: '#FFEDD5', cornerRadius: 13 }, f);
    b.text(as.tier, 532, y + 12, { fontSize: 10, fontWeight: 700, fill: '#D33417' }, f);
    b.text(as.logo, 700, y + 12, { fontSize: 11, fill: '#64748B' }, f);
    b.rect('Edit Btn', 1110, y + 4, 70, 28, { fill: '#F1F5F9', cornerRadius: 6 }, f);
    b.text('Edit', 1134, y + 10, { fontSize: 11, fontWeight: 700, fill: '#0F172A' }, f);
    b.line('Row L', 290, y + 46, 928, { stroke: '#F1F5F9', strokeWeight: 1 }, f);
  });

  b.text('17 of 17 sponsor logos mapped via WordCampRestClient::fetchSponsorLogosFromPage()', 300, 680, { fontSize: 12, fontWeight: 600, fill: '#059669' }, f);
}

// A4: Admin - Mobile Staff Console
{
  const f = b.frame('Admin - Mobile Staff Console', 4000, 2900, 390, 844, { fill: '#FFF7ED', clip: true });
  b.mobileStatusBar(f, true);

  // Staff Header
  b.rect('Staff Top', 0, 0, 390, 60, { fill: '#1E293B' }, f);
  b.text('Staff Console · WCDelhi', 20, 24, { fontSize: 15, fontWeight: 800, fill: '#FFFFFF' }, f);
  b.rect('Staff Badge', 280, 20, 90, 26, { fill: '#EF4444', cornerRadius: 13 }, f);
  b.text('ORGANIZER', 294, 26, { fontSize: 10, fontWeight: 800, fill: '#FFFFFF' }, f);

  // Quick Action Buttons
  b.text('Organizer Operations', 16, 76, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);

  b.rect('Scan Attendee Badge', 16, 98, 358, 70, { fill: '#FFFFFF', stroke: '#FF4D2D', cornerRadius: 16, strokeWeight: 1.5 }, f);
  b.text('📷 Check-in & Badge QR Scanner', 32, 114, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Scan attendee CampCard to verify ticket & assign roles', 32, 136, { fontSize: 11, fill: '#64748B' }, f);

  b.rect('Trigger Delay Card', 16, 178, 358, 70, { fill: '#FFFFFF', stroke: '#D97706', cornerRadius: 16, strokeWeight: 1.5 }, f);
  b.text('⏱ Fast Delay Broadcast (+10m / +15m)', 32, 194, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Push immediate delay offset without opening laptop', 32, 216, { fontSize: 11, fill: '#64748B' }, f);

  b.rect('Safety Alert Card', 16, 258, 358, 70, { fill: '#FEF2F2', stroke: '#EF4444', cornerRadius: 16, strokeWeight: 1.5 }, f);
  b.text('🛡 Code of Conduct Incident Dispatch', 32, 274, { fontSize: 14, fontWeight: 800, fill: '#991B1B' }, f);
  b.text('Zero open safety incidents · Incident team on call', 32, 296, { fontSize: 11, fill: '#7F1D1D' }, f);

  // Live Check-in Stats
  b.rect('Stats Card', 16, 344, 358, 120, { fill: '#1E293B', cornerRadius: 18 }, f);
  b.text('ATTENDEE CHECK-IN PULSE', 32, 362, { fontSize: 10, fontWeight: 800, fill: '#F59E0B' }, f);
  b.text('742 / 850 Checked In (87%)', 32, 384, { fontSize: 18, fontWeight: 800, fill: '#FFFFFF' }, f);
  b.rect('Bar Outer', 32, 412, 326, 10, { fill: '#334155', cornerRadius: 5 }, f);
  b.rect('Bar Inner', 32, 412, 284, 10, { fill: '#10B981', cornerRadius: 5 }, f);
  b.text('Peak flow: Hall A Registration Desks', 32, 432, { fontSize: 11, fill: '#94A3B8' }, f);

  // Recent Staff Activity Log
  b.text('Recent Staff Activity', 16, 480, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);

  const logs = [
    { text: 'Checked in: Sunil Kumar Sharma', time: '10:04 AM' },
    { text: 'Checked in: Lena Lekkou (Speaker)', time: '09:58 AM' },
    { text: 'Verified Booth: Elementor Ltd', time: '09:45 AM' },
  ];

  logs.forEach((lg, i) => {
    const y = 504 + i * 46;
    b.rect('Log Row', 16, y, 358, 38, { fill: '#FFFFFF', cornerRadius: 8, stroke: '#E2E8F0', strokeWeight: 1 }, f);
    b.text(lg.text, 28, y + 12, { fontSize: 11, fontWeight: 600, fill: '#334155' }, f);
    b.text(lg.time, 312, y + 12, { fontSize: 10, fill: '#94A3B8' }, f);
  });

  b.text('Staff portal requires active organizer authentication token.', 36, 780, { fontSize: 10, fill: '#94A3B8' }, f);
}

// Write the script to scratch/figcraft_script.json
const outputPath = path.resolve(rootDir, 'scratch', 'figcraft_script.json');
fs.writeFileSync(outputPath, JSON.stringify(b.commands, null, 2), 'utf8');

console.log(`Generated ${b.commands.length} FigCraft commands for ${b.screenFrames.length} audited screens.`);
console.log(`Saved script to: ${outputPath}`);
