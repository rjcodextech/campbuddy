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
}

const b = new FigCraftBuilder();

// ==========================================
// 1. MOBILE APP SCREENS (Y = 100)
// ==========================================

// M1: Event Picker & Device Sync
{
  const f = b.frame('Mobile - Event Picker', 40, 100, 390, 844, { fill: '#FFF7ED', clip: true });
  b.mobileStatusBar(f);

  // Header Brand
  b.text('CampBuddy', 20, 52, { fontSize: 22, fontWeight: 800, fill: '#FF4D2D', fontFamily: 'Poppins' }, f, 'App Title');
  b.rect('Offline Badge', 280, 52, 90, 24, { fill: '#064E3B', cornerRadius: 12 }, f);
  b.text('● Offline Ready', 290, 57, { fontSize: 10, fontWeight: 700, fill: '#A7F3D0' }, f);

  // Desktop to Mobile Sync Card
  b.rect('Sync Card BG', 16, 92, 358, 86, { fill: '#FFFFFF', cornerRadius: 16, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.rect('Sync Icon Box', 28, 104, 38, 38, { fill: '#FFEDD5', cornerRadius: 19 }, f);
  b.text('🔄', 39, 114, { fontSize: 16 }, f);
  b.text('Move data from laptop / PC', 76, 104, { fontSize: 14, fontWeight: 700, fill: '#0F172A' }, f);
  b.text('Sync schedule & CampCard via 6-digit code', 76, 124, { fontSize: 11, fill: '#64748B' }, f);
  b.rect('Code Input', 76, 144, 180, 26, { fill: '#F8FAFC', stroke: '#CBD5E1', cornerRadius: 6 }, f);
  b.text('Code: 482 109', 88, 149, { fontSize: 12, fontWeight: 700, fill: '#0F172A' }, f);
  b.rect('Sync Btn', 266, 144, 96, 26, { fill: '#FF4D2D', cornerRadius: 6 }, f);
  b.text('Link Device', 282, 149, { fontSize: 11, fontWeight: 700, fill: '#FFFFFF' }, f);

  // Section Heading & Filter Pills
  b.text('Choose your WordCamp', 16, 196, { fontSize: 17, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Active & upcoming events', 16, 218, { fontSize: 12, fill: '#64748B' }, f);

  // Pills
  const pills = [
    { label: 'All (10)', active: true, x: 16, w: 62 },
    { label: 'India', active: false, x: 84, w: 56 },
    { label: 'Kenya', active: false, x: 146, w: 58 },
    { label: 'Indonesia', active: false, x: 210, w: 76 },
    { label: 'Nepal', active: false, x: 292, w: 58 },
  ];
  for (const p of pills) {
    b.rect('Pill', p.x, 240, p.w, 30, { fill: p.active ? '#FF4D2D' : '#FFFFFF', cornerRadius: 15, stroke: p.active ? '#FF4D2D' : '#E2E8F0', strokeWeight: 1 }, f);
    b.text(p.label, p.x + 10, 248, { fontSize: 11, fontWeight: 700, fill: p.active ? '#FFFFFF' : '#0F172A' }, f);
  }

  // Event Card 1
  b.rect('Event 1 Card', 16, 286, 358, 220, { fill: '#FFFFFF', cornerRadius: 20, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.rect('Logo Box 1', 32, 302, 52, 52, { fill: '#1E293B', cornerRadius: 26 }, f);
  b.text('W', 49, 314, { fontSize: 24, fontWeight: 800, fill: '#FFFFFF' }, f);
  b.rect('Live Dot', 94, 304, 8, 8, { fill: '#10B981', cornerRadius: 4 }, f);
  b.text('UPCOMING · 21–22 NOV 2026', 108, 302, { fontSize: 10, fontWeight: 800, fill: '#D97706' }, f);
  b.text('WordCamp Delhi 2026', 94, 318, { fontSize: 17, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('📍 NDMC Convention Centre, New Delhi', 94, 340, { fontSize: 11, fill: '#64748B' }, f);
  b.line('Card Div', 32, 372, 326, { stroke: '#F1F5F9', strokeWeight: 1 }, f);

  b.text('36 Talks · 4 Tracks · Contributor Day', 32, 386, { fontSize: 12, fontWeight: 600, fill: '#475569' }, f);
  b.rect('Offline Chip', 32, 410, 110, 24, { fill: '#ECFDF5', stroke: '#A7F3D0', cornerRadius: 12, strokeWeight: 1 }, f);
  b.text('✔ 100% Offline Ready', 40, 415, { fontSize: 10, fontWeight: 700, fill: '#047857' }, f);
  b.rect('Open Btn', 32, 448, 326, 44, { fill: '#FF4D2D', cornerRadius: 12 }, f);
  b.text('Open Event Companion  →', 116, 461, { fontSize: 14, fontWeight: 700, fill: '#FFFFFF' }, f);

  // Event Card 2
  b.rect('Event 2 Card', 16, 520, 358, 140, { fill: '#FFFFFF', cornerRadius: 20, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.rect('Logo Box 2', 32, 536, 44, 44, { fill: '#334155', cornerRadius: 22 }, f);
  b.text('N', 47, 546, { fontSize: 20, fontWeight: 800, fill: '#FFFFFF' }, f);
  b.text('28 NOV 2026', 88, 536, { fontSize: 10, fontWeight: 800, fill: '#D97706' }, f);
  b.text('WordCamp Nairobi 2026', 88, 552, { fontSize: 16, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('📍 The Edge Convention Centre, Nairobi', 88, 572, { fontSize: 11, fill: '#64748B' }, f);
  b.rect('Open Btn 2', 88, 598, 160, 34, { fill: '#F1F5F9', cornerRadius: 8 }, f);
  b.text('View Details →', 126, 608, { fontSize: 12, fontWeight: 700, fill: '#0F172A' }, f);

  // Bottom Notice
  b.text('Zero-signup · DPDP Act 2023 Compliant · Built by WPSimplified', 30, 800, { fontSize: 10, fill: '#94A3B8' }, f);
}

// M2: Tactile Home Dashboard
{
  const f = b.frame('Mobile - Tactile Home', 470, 100, 390, 844, { fill: '#FFF7ED', clip: true });
  b.mobileStatusBar(f, true);

  // Hero Card (Navy Gradient)
  b.rect('Hero Card', 16, 44, 358, 204, { fill: '#1E293B', cornerRadius: 24, stroke: '#334155', strokeWeight: 1 }, f);
  b.text('10:14 AM', 32, 60, { fontSize: 28, fontWeight: 800, fill: '#FFFFFF' }, f);
  b.text('WordCamp Delhi · Day 1 of 2', 32, 94, { fontSize: 11, fontWeight: 600, fill: '#94A3B8' }, f);
  b.rect('Logo Mini', 304, 60, 48, 48, { fill: '#334155', cornerRadius: 24 }, f);
  b.text('W', 320, 72, { fontSize: 20, fontWeight: 800, fill: '#FF4D2D' }, f);

  b.text('Live at NDMC Convention Centre', 32, 118, { fontSize: 14, fontWeight: 700, fill: '#FFFFFF' }, f);

  // Next Session Teaser Box
  b.rect('Next Teaser', 28, 142, 334, 88, { fill: '#0F172A', cornerRadius: 16, stroke: '#334155', strokeWeight: 1 }, f);
  b.text('⚡ NEXT SESSION STARTS IN 12 MIN', 42, 154, { fontSize: 10, fontWeight: 800, fill: '#F59E0B' }, f);
  b.text('Building Offline-First Web Apps', 42, 172, { fontSize: 13, fontWeight: 700, fill: '#FFFFFF' }, f);
  b.text('Track A · Hall 1 · Rahul Verma', 42, 192, { fontSize: 11, fill: '#94A3B8' }, f);
  b.rect('View Schedule Btn', 270, 168, 76, 34, { fill: '#D97706', cornerRadius: 17 }, f);
  b.text('VIEW', 292, 178, { fontSize: 11, fontWeight: 800, fill: '#FFFFFF' }, f);

  // People & Matches Strip
  b.text('People & Wave Matches', 16, 260, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('See all →', 330, 262, { fontSize: 11, fontWeight: 700, fill: '#D33417' }, f);

  // Wave button + Avatars
  b.rect('Wave Circle', 16, 282, 52, 52, { fill: '#FFEDD5', stroke: '#D97706', cornerRadius: 26, strokeWeight: 1.5 }, f);
  b.text('👋', 30, 296, { fontSize: 20 }, f);
  b.text('Wave', 26, 340, { fontSize: 10, fontWeight: 600, fill: '#0F172A' }, f);

  const people = [
    { name: 'Amit S.', color: '#38BDF8', x: 80, initial: 'A' },
    { name: 'Priya K.', color: '#F472B6', x: 144, initial: 'P' },
    { name: 'Rahul V.', color: '#4ADE80', x: 208, initial: 'R' },
    { name: 'Elena R.', color: '#A78BFA', x: 272, initial: 'E' },
    { name: 'Sunil S.', color: '#FB923C', x: 336, initial: 'S' },
  ];
  for (const p of people) {
    b.rect('Avatar', p.x, 282, 52, 52, { fill: p.color, cornerRadius: 26 }, f);
    b.text(p.initial, p.x + 19, 296, { fontSize: 18, fontWeight: 800, fill: '#FFFFFF' }, f);
    b.ellipse('Online Dot', p.x + 38, 320, 12, 12, { fill: '#10B981', stroke: '#FFFFFF', strokeWeight: 2 }, f);
    b.text(p.name, p.x + 4, 340, { fontSize: 10, fontWeight: 600, fill: '#0F172A' }, f);
  }

  // 2x3 Event Hubs ActionGrid
  b.text('Event Hubs', 16, 368, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);

  const hubs = [
    { title: 'My Day', sub: '4 saved talks', icon: '📅', x: 16, y: 390 },
    { title: 'Schedule', sub: '36 talks & tracks', icon: '🎤', x: 200, y: 390 },
    { title: 'My CampCard', sub: 'P2P QR Badge', icon: '🪪', x: 16, y: 486 },
    { title: 'Attendees', sub: 'Directory & roles', icon: '👥', x: 200, y: 486 },
    { title: 'Quests', sub: 'Contributor Day', icon: '🎯', x: 16, y: 582 },
    { title: 'Sponsors & Swag', sub: 'Steals & deals', icon: '🎁', x: 200, y: 582 },
  ];
  for (const h of hubs) {
    b.rect('Hub Tile', h.x, h.y, 174, 84, { fill: '#FFFFFF', cornerRadius: 16, stroke: '#E2E8F0', strokeWeight: 1 }, f);
    b.rect('Hub Icon Box', h.x + 12, h.y + 12, 34, 34, { fill: '#FFEDD5', cornerRadius: 17 }, f);
    b.text(h.icon, h.x + 20, h.y + 19, { fontSize: 15 }, f);
    b.text(h.title, h.x + 12, h.y + 52, { fontSize: 13, fontWeight: 800, fill: '#0F172A' }, f);
    b.text(h.sub, h.x + 12, h.y + 68, { fontSize: 10, fill: '#64748B' }, f);
  }

  // Floating SOS / Help Bar
  b.rect('SOS Bar', 16, 680, 358, 50, { fill: '#EF4444', cornerRadius: 25 }, f);
  b.text('🛟  Need Help at WordCamp?', 32, 696, { fontSize: 13, fontWeight: 700, fill: '#FFFFFF' }, f);
  b.text('Incident Support & Organizers', 32, 712, { fontSize: 10, fill: 'rgba(255,255,255,0.85)' }, f);
  b.rect('Help Badge', 304, 690, 56, 30, { fill: 'rgba(255,255,255,0.25)', cornerRadius: 15 }, f);
  b.text('HELP', 316, 698, { fontSize: 11, fontWeight: 800, fill: '#FFFFFF' }, f);

  // Legal & Staff links
  b.text('Privacy · Terms · DPDP Act · Staff Sign In', 65, 750, { fontSize: 10, fill: '#64748B' }, f);

  b.mobileBottomNav(f, 'home');
}

// M3: My Day & Smart Schedule
{
  const f = b.frame('Mobile - My Day Schedule', 900, 100, 390, 844, { fill: '#FFF7ED', clip: true });
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

  // Talk 1
  b.rect('Talk 1 BG', 16, 200, 358, 120, { fill: '#FFFFFF', cornerRadius: 18, stroke: '#FF4D2D', strokeWeight: 2 }, f);
  b.rect('Track Pill 1', 28, 212, 70, 20, { fill: '#FFEDD5', cornerRadius: 10 }, f);
  b.text('TRACK A', 38, 216, { fontSize: 9, fontWeight: 800, fill: '#D33417' }, f);
  b.text('10:30 AM – 11:15 AM · Hall 1', 106, 215, { fontSize: 11, fontWeight: 600, fill: '#64748B' }, f);
  b.text('★', 346, 212, { fontSize: 16, fill: '#D97706' }, f);
  b.text('Building Offline-First Web Apps', 28, 238, { fontSize: 15, fontWeight: 800, fill: '#0F172A' }, f);
  b.rect('Speaker Pic', 28, 268, 36, 36, { fill: '#38BDF8', cornerRadius: 18 }, f);
  b.text('R', 40, 276, { fontSize: 14, fontWeight: 800, fill: '#FFFFFF' }, f);
  b.text('Rahul Verma', 72, 270, { fontSize: 12, fontWeight: 700, fill: '#0F172A' }, f);
  b.text('Senior Engineer · WPSimplified', 72, 286, { fontSize: 10, fill: '#64748B' }, f);

  // Talk 2
  b.rect('Talk 2 BG', 16, 332, 358, 120, { fill: '#FFFFFF', cornerRadius: 18, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.rect('Track Pill 2', 28, 344, 70, 20, { fill: '#E0F2FE', cornerRadius: 10 }, f);
  b.text('TRACK B', 38, 348, { fontSize: 9, fontWeight: 800, fill: '#0369A1' }, f);
  b.text('11:30 AM – 12:15 PM · Hall 2', 106, 347, { fontSize: 11, fontWeight: 600, fill: '#64748B' }, f);
  b.text('★', 346, 344, { fontSize: 16, fill: '#D97706' }, f);
  b.text('Full-Site Editing & Custom Blocks', 28, 370, { fontSize: 15, fontWeight: 800, fill: '#0F172A' }, f);
  b.rect('Speaker Pic 2', 28, 400, 36, 36, { fill: '#F472B6', cornerRadius: 18 }, f);
  b.text('E', 40, 408, { fontSize: 14, fontWeight: 800, fill: '#FFFFFF' }, f);
  b.text('Elena Rostova', 72, 402, { fontSize: 12, fontWeight: 700, fill: '#0F172A' }, f);
  b.text('Core Contributor · Automattic', 72, 418, { fontSize: 10, fill: '#64748B' }, f);

  // Lunch Break
  b.rect('Lunch BG', 16, 464, 358, 60, { fill: '#F8FAFC', cornerRadius: 14, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.text('🍴 12:30 PM – 01:45 PM · Lunch & Sponsor Swag Steals', 28, 482, { fontSize: 12, fontWeight: 700, fill: '#475569' }, f);
  b.text('Dining Hall & Exhibition Arena', 28, 500, { fontSize: 10, fill: '#94A3B8' }, f);

  // Talk 3
  b.rect('Talk 3 BG', 16, 536, 358, 120, { fill: '#FFFFFF', cornerRadius: 18, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.rect('Track Pill 3', 28, 548, 70, 20, { fill: '#FFEDD5', cornerRadius: 10 }, f);
  b.text('TRACK A', 38, 552, { fontSize: 9, fontWeight: 800, fill: '#D33417' }, f);
  b.text('02:00 PM – 02:45 PM · Hall 1', 106, 551, { fontSize: 11, fontWeight: 600, fill: '#64748B' }, f);
  b.text('★', 346, 548, { fontSize: 16, fill: '#CBD5E1' }, f);
  b.text('Scaling WordPress to 10M Visitors', 28, 574, { fontSize: 15, fontWeight: 800, fill: '#0F172A' }, f);
  b.rect('Speaker Pic 3', 28, 604, 36, 36, { fill: '#10B981', cornerRadius: 18 }, f);
  b.text('A', 40, 612, { fontSize: 14, fontWeight: 800, fill: '#FFFFFF' }, f);
  b.text('Amit Singh', 72, 606, { fontSize: 12, fontWeight: 700, fill: '#0F172A' }, f);
  b.text('Performance Lead · Cloudways', 72, 622, { fontSize: 10, fill: '#64748B' }, f);

  b.mobileBottomNav(f, 'my-day');
}

// M4: Digital CampCard & P2P QR Swap
{
  const f = b.frame('Mobile - CampCard Swap', 1330, 100, 390, 844, { fill: '#FFF7ED', clip: true });
  b.mobileStatusBar(f);

  b.text('CampCard', 16, 52, { fontSize: 22, fontWeight: 800, fill: '#0F172A' }, f);

  // Verified Badge Banner
  b.rect('Claim Banner', 16, 86, 358, 48, { fill: '#FFEDD5', stroke: '#FDBA74', cornerRadius: 14, strokeWeight: 1 }, f);
  b.text('🛡 Verified Attendee Profile', 28, 98, { fontSize: 12, fontWeight: 700, fill: '#9A3412' }, f);
  b.text('Linked to public attendee directory', 28, 114, { fontSize: 10, fill: '#C2410C' }, f);
  b.rect('Claim Pill', 280, 96, 82, 26, { fill: '#EA580C', cornerRadius: 13 }, f);
  b.text('Claimed ✔', 294, 102, { fontSize: 10, fontWeight: 700, fill: '#FFFFFF' }, f);

  // Tactical CampCard Badge
  b.rect('Badge Main', 24, 146, 342, 440, { fill: '#FFFFFF', cornerRadius: 24, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  // Header navy strip
  b.rect('Badge Header', 24, 146, 342, 60, { fill: '#1E293B', cornerRadius: 24 }, f);
  b.rect('Header Fix', 24, 176, 342, 30, { fill: '#1E293B' }, f);
  b.text('WORDCAMP DELHI 2026', 44, 162, { fontSize: 11, fontWeight: 800, fill: '#F59E0B' }, f);
  b.text('ATTENDEE PASS · NO SIGNUP', 44, 180, { fontSize: 9, fontWeight: 700, fill: '#94A3B8' }, f);

  // User details
  b.text('Sunil Kumar Sharma', 44, 222, { fontSize: 20, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('WordPress Engineer · WPSimplified', 44, 248, { fontSize: 12, fontWeight: 600, fill: '#FF4D2D' }, f);
  b.text('📍 Jaipur, India', 44, 268, { fontSize: 11, fill: '#64748B' }, f);

  // Interest tags
  b.rect('Tag 1', 44, 290, 80, 22, { fill: '#F1F5F9', cornerRadius: 11 }, f);
  b.text('Plugin Dev', 54, 295, { fontSize: 10, fontWeight: 700, fill: '#334155' }, f);
  b.rect('Tag 2', 130, 290, 78, 22, { fill: '#F1F5F9', cornerRadius: 11 }, f);
  b.text('Gutenberg', 140, 295, { fontSize: 10, fontWeight: 700, fill: '#334155' }, f);
  b.rect('Tag 3', 214, 290, 88, 22, { fill: '#F1F5F9', cornerRadius: 11 }, f);
  b.text('Performance', 222, 295, { fontSize: 10, fontWeight: 700, fill: '#334155' }, f);

  // Live QR Code Frame
  b.rect('QR Frame', 95, 330, 200, 200, { fill: '#F8FAFC', stroke: '#E2E8F0', cornerRadius: 16, strokeWeight: 1 }, f);
  // Simulated high contrast QR pattern
  b.rect('QR TopL', 115, 350, 48, 48, { fill: '#0F172A', cornerRadius: 6 }, f);
  b.rect('QR TopL In', 123, 358, 32, 32, { fill: '#FFFFFF', cornerRadius: 4 }, f);
  b.rect('QR TopL Dot', 131, 366, 16, 16, { fill: '#0F172A', cornerRadius: 2 }, f);

  b.rect('QR TopR', 227, 350, 48, 48, { fill: '#0F172A', cornerRadius: 6 }, f);
  b.rect('QR TopR In', 235, 358, 32, 32, { fill: '#FFFFFF', cornerRadius: 4 }, f);
  b.rect('QR TopR Dot', 243, 366, 16, 16, { fill: '#0F172A', cornerRadius: 2 }, f);

  b.rect('QR BotL', 115, 462, 48, 48, { fill: '#0F172A', cornerRadius: 6 }, f);
  b.rect('QR BotL In', 123, 470, 32, 32, { fill: '#FFFFFF', cornerRadius: 4 }, f);
  b.rect('QR BotL Dot', 131, 478, 16, 16, { fill: '#0F172A', cornerRadius: 2 }, f);

  b.rect('QR Data 1', 178, 350, 34, 48, { fill: '#0F172A', cornerRadius: 3 }, f);
  b.rect('QR Data 2', 178, 420, 97, 34, { fill: '#0F172A', cornerRadius: 3 }, f);
  b.rect('QR Data 3', 115, 412, 48, 34, { fill: '#0F172A', cornerRadius: 3 }, f);
  b.rect('QR Data 4', 178, 462, 97, 48, { fill: '#0F172A', cornerRadius: 3 }, f);

  b.text('Scan with phone camera to exchange contacts', 65, 550, { fontSize: 10, fontWeight: 600, fill: '#64748B' }, f);

  // Action Buttons
  b.rect('Share Btn', 24, 600, 164, 46, { fill: '#FF4D2D', cornerRadius: 12 }, f);
  b.text('📤 Share Card', 64, 614, { fontSize: 13, fontWeight: 700, fill: '#FFFFFF' }, f);

  b.rect('Download Btn', 202, 600, 164, 46, { fill: '#FFFFFF', stroke: '#E2E8F0', cornerRadius: 12, strokeWeight: 1 }, f);
  b.text('⬇ Download Pass', 232, 614, { fontSize: 13, fontWeight: 700, fill: '#0F172A' }, f);

  // Social Links dock
  b.text('Linked accounts: LinkedIn · WordPress.org · Website', 60, 665, { fontSize: 10, fill: '#64748B' }, f);

  b.mobileBottomNav(f, 'camp-card');
}

// M5: Explore & Who's Attending
{
  const f = b.frame('Mobile - Explore Roster', 1760, 100, 390, 844, { fill: '#FFF7ED', clip: true });
  b.mobileStatusBar(f);

  b.text('Explore', 16, 52, { fontSize: 22, fontWeight: 800, fill: '#0F172A' }, f);

  // 5 Subtabs
  const subtabs = [
    { label: 'People', active: true, x: 16, w: 70 },
    { label: 'Sponsors', active: false, x: 92, w: 76 },
    { label: 'Deals', active: false, x: 174, w: 60 },
    { label: 'Free Steals', active: false, x: 240, w: 84 },
    { label: 'Info', active: false, x: 330, w: 44 },
  ];
  for (const t of subtabs) {
    b.rect('Subtab', t.x, 86, t.w, 32, { fill: t.active ? '#FF4D2D' : '#FFFFFF', cornerRadius: 16, stroke: t.active ? '#FF4D2D' : '#E2E8F0', strokeWeight: 1 }, f);
    b.text(t.label, t.x + 10, 95, { fontSize: 11, fontWeight: 700, fill: t.active ? '#FFFFFF' : '#64748B' }, f);
  }

  // Search input
  b.rect('Search Box', 16, 130, 358, 42, { fill: '#FFFFFF', stroke: '#CBD5E1', cornerRadius: 12, strokeWeight: 1 }, f);
  b.text('🔍  Search 240+ attendees or topics…', 32, 142, { fontSize: 12, fill: '#94A3B8' }, f);

  // Quick Role Pills
  b.text('Filter by role:', 16, 186, { fontSize: 11, fontWeight: 700, fill: '#64748B' }, f);
  b.rect('Role 1', 94, 182, 66, 22, { fill: '#FFEDD5', cornerRadius: 11 }, f);
  b.text('Speaker', 106, 186, { fontSize: 10, fontWeight: 700, fill: '#D33417' }, f);
  b.rect('Role 2', 166, 182, 74, 22, { fill: '#E0F2FE', cornerRadius: 11 }, f);
  b.text('Organizer', 176, 186, { fontSize: 10, fontWeight: 700, fill: '#0369A1' }, f);
  b.rect('Role 3', 246, 182, 68, 22, { fill: '#FEF3C7', cornerRadius: 11 }, f);
  b.text('Sponsor', 256, 186, { fontSize: 10, fontWeight: 700, fill: '#92400E' }, f);

  // Attendee Cards
  const attendees = [
    { name: 'Amit Singh', role: 'Speaker', title: 'Senior Cloud Architect', org: 'Cloudways', color: '#10B981', initial: 'A', y: 216 },
    { name: 'Priya Sharma', role: 'Organizer', title: 'Lead Designer & Mentor', org: 'WordPress Delhi', color: '#F472B6', initial: 'P', y: 310 },
    { name: 'David Kim', role: 'Sponsor', title: 'DevRel Director', org: 'Automattic', color: '#38BDF8', initial: 'D', y: 404 },
    { name: 'Neha Gupta', role: 'Volunteer', title: 'Community Manager', org: 'WP Jaipur', color: '#A78BFA', initial: 'N', y: 498 },
    { name: 'Rahul Verma', role: 'Speaker', title: 'Tech Lead', org: 'WPSimplified', color: '#FB923C', initial: 'R', y: 592 },
  ];

  for (const a of attendees) {
    b.rect('Att Card', 16, a.y, 358, 82, { fill: '#FFFFFF', cornerRadius: 16, stroke: '#E2E8F0', strokeWeight: 1 }, f);
    b.rect('Att Pic', 30, a.y + 14, 46, 46, { fill: a.color, cornerRadius: 23 }, f);
    b.text(a.initial, 47, a.y + 24, { fontSize: 18, fontWeight: 800, fill: '#FFFFFF' }, f);
    b.text(a.name, 88, a.y + 16, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);
    b.text(`${a.title} · ${a.org}`, 88, a.y + 36, { fontSize: 11, fill: '#64748B' }, f);
    b.rect('Role Pill', 88, a.y + 54, 60, 18, { fill: '#F1F5F9', cornerRadius: 9 }, f);
    b.text(a.role, 96, a.y + 57, { fontSize: 9, fontWeight: 700, fill: '#334155' }, f);

    b.rect('Connect Btn', 290, a.y + 24, 72, 34, { fill: '#FFEDD5', cornerRadius: 17 }, f);
    b.text('Wave 👋', 302, a.y + 34, { fontSize: 11, fontWeight: 700, fill: '#D33417' }, f);
  }

  b.mobileBottomNav(f, 'explore');
}

// M6: Contributor Day & Quests
{
  const f = b.frame('Mobile - Quests Contribute', 2190, 100, 390, 844, { fill: '#FFF7ED', clip: true });
  b.mobileStatusBar(f);

  b.text('Contributor Quests', 16, 52, { fontSize: 22, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Level up your WordCamp experience', 16, 78, { fontSize: 12, fill: '#64748B' }, f);

  // Progress Card
  b.rect('Quest Prog BG', 16, 104, 358, 86, { fill: '#1E293B', cornerRadius: 20 }, f);
  b.text('CONFERENCE EXPEDITION', 32, 118, { fontSize: 10, fontWeight: 800, fill: '#F59E0B' }, f);
  b.text('Level 2 Companion · 4 of 8 Done', 32, 134, { fontSize: 14, fontWeight: 800, fill: '#FFFFFF' }, f);
  // Bar
  b.rect('Prog Track', 32, 158, 326, 10, { fill: '#334155', cornerRadius: 5 }, f);
  b.rect('Prog Fill', 32, 158, 163, 10, { fill: '#10B981', cornerRadius: 5 }, f);

  // Contributor Day Team Matchmaker
  b.rect('Contrib Box', 16, 204, 358, 120, { fill: '#FFFFFF', cornerRadius: 18, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.text('Joining Contributor Day?', 30, 218, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('You do not need to code. Find your table:', 30, 238, { fontSize: 11, fill: '#64748B' }, f);

  const teams = ['Core', 'Polyglots', 'Docs', 'Community', 'Design'];
  let tx = 30;
  for (const t of teams) {
    b.rect('Team Pill', tx, 260, 58, 26, { fill: '#F1F5F9', cornerRadius: 13 }, f);
    b.text(t, tx + 10, 266, { fontSize: 10, fontWeight: 700, fill: '#334155' }, f);
    tx += 64;
  }
  b.rect('Find Table Btn', 30, 292, 140, 24, { fill: '#FF4D2D', cornerRadius: 6 }, f);
  b.text('Find My Table →', 46, 297, { fontSize: 10, fontWeight: 700, fill: '#FFFFFF' }, f);

  // Quest Checklist
  b.text('Active Quests', 16, 340, { fontSize: 15, fontWeight: 800, fill: '#0F172A' }, f);

  const quests = [
    { title: 'Exchange CampCards with 3 attendees', done: true, xp: '+100 XP', icon: '🪪' },
    { title: 'Bookmark 4 sessions to My Day', done: true, xp: '+100 XP', icon: '📅' },
    { title: 'Visit 2 sponsor booths for swag deals', done: false, xp: '+150 XP', icon: '🎁' },
    { title: 'Sit at a Contributor Day table', done: false, xp: '+250 XP', icon: '💻' },
    { title: 'Say hi to an event volunteer or organizer', done: false, xp: '+100 XP', icon: '👋' },
  ];

  let qy = 368;
  for (const q of quests) {
    b.rect('Quest Card', 16, qy, 358, 64, { fill: '#FFFFFF', cornerRadius: 14, stroke: q.done ? '#A7F3D0' : '#E2E8F0', strokeWeight: 1 }, f);
    b.rect('Check Box', 28, qy + 18, 28, 28, { fill: q.done ? '#10B981' : '#F1F5F9', cornerRadius: 14 }, f);
    b.text(q.done ? '✔' : q.icon, 35, qy + 24, { fontSize: 13, fill: q.done ? '#FFFFFF' : '#64748B' }, f);
    b.text(q.title, 66, qy + 18, { fontSize: 12, fontWeight: 700, fill: q.done ? '#065F46' : '#0F172A' }, f);
    b.text(q.xp, 66, qy + 36, { fontSize: 10, fontWeight: 700, fill: '#D97706' }, f);
    qy += 72;
  }

  b.mobileBottomNav(f, 'quest');
}

// ==========================================
// 2. WEBSITE SCREENS (Y = 1050)
// ==========================================

// W1: Web Landing & Hero Stage
{
  const f = b.frame('Web - Landing Hero Stage', 40, 1050, 1280, 800, { fill: '#FFF7ED', clip: true });

  // Top Nav
  b.rect('Web Nav BG', 0, 0, 1280, 72, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.text('CampBuddy', 48, 22, { fontSize: 24, fontWeight: 800, fill: '#FF4D2D', fontFamily: 'Poppins' }, f);
  b.text('WordCamps', 320, 26, { fontSize: 14, fontWeight: 600, fill: '#0F172A' }, f);
  b.text('First-Timer Guide', 440, 26, { fontSize: 14, fontWeight: 600, fill: '#0F172A' }, f);
  b.text('About', 600, 26, { fontSize: 14, fontWeight: 600, fill: '#0F172A' }, f);
  b.text('Privacy & DPDP', 680, 26, { fontSize: 14, fontWeight: 600, fill: '#0F172A' }, f);

  b.rect('Staff Login Btn', 1010, 18, 120, 36, { fill: '#F1F5F9', cornerRadius: 18 }, f);
  b.text('Staff Login', 1038, 27, { fontSize: 12, fontWeight: 700, fill: '#0F172A' }, f);

  b.rect('Get App Btn', 1146, 18, 100, 36, { fill: '#FF4D2D', cornerRadius: 18 }, f);
  b.text('Install PWA', 1162, 27, { fontSize: 12, fontWeight: 700, fill: '#FFFFFF' }, f);

  // Left Column Content
  b.rect('Kicker Pill', 48, 110, 260, 28, { fill: '#FFEDD5', cornerRadius: 14 }, f);
  b.text('✨ Open-Source Community Companion', 60, 116, { fontSize: 11, fontWeight: 800, fill: '#D33417' }, f);

  b.text('Make the most of\nyour WordCamp', 48, 154, { fontSize: 44, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Navigate multi-track halls, star talks into your personal "My Day",\nand exchange contacts in seconds via phone camera — 100% free,\nzero paper cards, and no login needed.', 48, 270, { fontSize: 15, fill: '#475569' }, f);

  // 4 Core Promises
  const promises = [
    '✔ 100% Free & Open Source',
    '✔ Zero Login or Account Needed',
    '✔ Runs 100% Offline in Venue',
    '✔ Instant Progressive Web App (PWA)',
  ];
  let py = 350;
  for (const p of promises) {
    b.text(p, 48, py, { fontSize: 13, fontWeight: 700, fill: '#0F172A' }, f);
    py += 24;
  }

  // CTAs
  b.rect('Primary CTA', 48, 470, 220, 52, { fill: '#FF4D2D', cornerRadius: 12 }, f);
  b.text('Choose your WordCamp  ↓', 74, 486, { fontSize: 14, fontWeight: 700, fill: '#FFFFFF' }, f);

  b.rect('Secondary CTA', 284, 470, 180, 52, { fill: '#FFFFFF', stroke: '#E2E8F0', cornerRadius: 12, strokeWeight: 1 }, f);
  b.text('First WordCamp? Guide →', 304, 486, { fontSize: 14, fontWeight: 700, fill: '#0F172A' }, f);

  // Trust Bar
  b.text('Built by WPSimplified · Open Source Initiative · DPDP Act 2023 Compliant', 48, 550, { fontSize: 12, fill: '#94A3B8' }, f);

  // Right Column: Interactive Companion Stage
  b.rect('Stage Ambient Glow', 660, 100, 570, 640, { fill: '#1E293B', cornerRadius: 32 }, f);

  // Stage Top Header
  b.rect('Stage Header', 690, 130, 510, 50, { fill: '#334155', cornerRadius: 16 }, f);
  b.rect('Live Indicator', 710, 148, 12, 12, { fill: '#10B981', cornerRadius: 6 }, f);
  b.text('LIVE COMPANION · WORDCAMP DELHI 2026', 732, 146, { fontSize: 12, fontWeight: 800, fill: '#FFFFFF' }, f);
  b.rect('Shuffle Btn', 1110, 140, 76, 30, { fill: '#475569', cornerRadius: 8 }, f);
  b.text('Shuffle ↻', 1124, 147, { fontSize: 10, fontWeight: 700, fill: '#FFFFFF' }, f);

  // Stage Card 1: Speaker Spotlight
  b.rect('Stage Spotlight Card', 690, 200, 510, 210, { fill: '#0F172A', cornerRadius: 20, stroke: '#334155', strokeWeight: 1 }, f);
  b.rect('Tag Pill', 710, 220, 120, 24, { fill: '#FFEDD5', cornerRadius: 12 }, f);
  b.text('SPEAKER SPOTLIGHT', 718, 225, { fontSize: 10, fontWeight: 800, fill: '#D33417' }, f);
  b.text('Aditya Vikram Singh', 710, 258, { fontSize: 22, fontWeight: 800, fill: '#FFFFFF' }, f);
  b.text('Demystifying Headless WordPress with Next.js 15 & GraphQL', 710, 290, { fontSize: 13, fill: '#94A3B8' }, f);
  b.text('Track A · 11:30 AM · Hall 1', 710, 320, { fontSize: 12, fontWeight: 700, fill: '#F59E0B' }, f);
  b.rect('Explore Talk Btn', 710, 350, 180, 38, { fill: '#D97706', cornerRadius: 10 }, f);
  b.text('Explore This WordCamp →', 726, 361, { fontSize: 12, fontWeight: 800, fill: '#FFFFFF' }, f);

  // Stage Card 2: P2P Card Swap
  b.rect('Stage Swap Card', 690, 430, 510, 140, { fill: '#0F172A', cornerRadius: 20, stroke: '#334155', strokeWeight: 1 }, f);
  b.rect('Mini QR Box', 714, 450, 56, 56, { fill: '#FFFFFF', cornerRadius: 12 }, f);
  b.text('🪪', 730, 466, { fontSize: 24 }, f);
  b.text('Instant Digital CampCard (P2P Swap)', 786, 454, { fontSize: 15, fontWeight: 800, fill: '#FFFFFF' }, f);
  b.text('Swap LinkedIn, portfolio & contacts directly via camera — zero paper cards', 786, 478, { fontSize: 12, fill: '#94A3B8' }, f);
  b.rect('Offline Badge', 786, 510, 96, 22, { fill: '#064E3B', cornerRadius: 11 }, f);
  b.text('✔ 100% Offline', 796, 514, { fontSize: 10, fontWeight: 700, fill: '#A7F3D0' }, f);

  // Stage Feature Chips
  const stageChips = ['📅 Multi-Track Schedule', '🪪 CampCard', '🎯 Quests', '💻 Contributor Day'];
  let scx = 690;
  for (const sc of stageChips) {
    b.rect('Stage Chip', scx, 600, 118, 30, { fill: '#334155', cornerRadius: 15 }, f);
    b.text(sc, scx + 8, 608, { fontSize: 10, fontWeight: 700, fill: '#FFFFFF' }, f);
    scx += 128;
  }
}

// W2: Web Event Directory & Country Hub
{
  const f = b.frame('Web - Event Directory', 1360, 1050, 1280, 800, { fill: '#FFF7ED', clip: true });

  // Top Nav
  b.rect('Web Nav BG 2', 0, 0, 1280, 72, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.text('CampBuddy', 48, 22, { fontSize: 24, fontWeight: 800, fill: '#FF4D2D', fontFamily: 'Poppins' }, f);
  b.text('Active WordCamps', 240, 26, { fontSize: 14, fontWeight: 700, fill: '#0F172A' }, f);

  // Section Header
  b.text('Choose your WordCamp', 48, 96, { fontSize: 28, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Tap your event to access its multi-track schedule, attendee list, and local guide.', 48, 134, { fontSize: 14, fill: '#64748B' }, f);

  // Sync Card
  b.rect('Web Sync Card', 48, 170, 1184, 76, { fill: '#FFFFFF', cornerRadius: 16, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.rect('Sync Box', 68, 186, 44, 44, { fill: '#FFEDD5', cornerRadius: 22 }, f);
  b.text('🔄', 80, 198, { fontSize: 20 }, f);
  b.text('Move your data to Mobile App or PWA', 128, 188, { fontSize: 15, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Enter this 6-digit transfer code on your phone to link your bookmarks:  ', 128, 212, { fontSize: 12, fill: '#64748B' }, f);
  b.text('482 109', 496, 210, { fontSize: 14, fontWeight: 800, fill: '#FF4D2D' }, f);

  // Country Filter Pills
  const cPills = [
    { label: 'All WordCamps (10)', active: true, x: 48, w: 140 },
    { label: 'India (3)', active: false, x: 198, w: 80 },
    { label: 'Kenya (1)', active: false, x: 288, w: 84 },
    { label: 'Indonesia (1)', active: false, x: 382, w: 104 },
    { label: 'Greece (1)', active: false, x: 496, w: 90 },
    { label: 'Nepal (1)', active: false, x: 596, w: 86 },
    { label: 'Europe (3)', active: false, x: 692, w: 94 },
  ];
  for (const cp of cPills) {
    b.rect('C Pill', cp.x, 266, cp.w, 36, { fill: cp.active ? '#FF4D2D' : '#FFFFFF', cornerRadius: 18, stroke: cp.active ? '#FF4D2D' : '#E2E8F0', strokeWeight: 1 }, f);
    b.text(cp.label, cp.x + 14, 276, { fontSize: 12, fontWeight: 700, fill: cp.active ? '#FFFFFF' : '#0F172A' }, f);
  }

  // 3-Column Event Grid
  const events = [
    { name: 'WordCamp Delhi 2026', city: 'New Delhi, India', dates: '21–22 Nov 2026', talks: '36 Talks', x: 48, initial: 'D' },
    { name: 'WordCamp Nairobi 2026', city: 'Nairobi, Kenya', dates: '28 Nov 2026', talks: '28 Talks', x: 456, initial: 'N' },
    { name: 'WordCamp Bandung 2026', city: 'Bandung, Indonesia', dates: '5 Dec 2026', talks: '32 Talks', x: 864, initial: 'B' },
  ];

  for (const e of events) {
    b.rect('Event Card', e.x, 320, 368, 380, { fill: '#FFFFFF', cornerRadius: 20, stroke: '#E2E8F0', strokeWeight: 1 }, f);
    b.rect('Card Logo Box', e.x + 24, 344, 60, 60, { fill: '#1E293B', cornerRadius: 30 }, f);
    b.text(e.initial, e.x + 44, 358, { fontSize: 26, fontWeight: 800, fill: '#FF4D2D' }, f);
    b.text('LIVE EVENT', e.x + 96, 348, { fontSize: 10, fontWeight: 800, fill: '#D97706' }, f);
    b.text(e.name, e.x + 96, 366, { fontSize: 18, fontWeight: 800, fill: '#0F172A' }, f);
    b.text(`📍 ${e.city}`, e.x + 96, 392, { fontSize: 12, fill: '#64748B' }, f);

    b.line('Card Div', e.x + 24, 424, 320, { stroke: '#F1F5F9', strokeWeight: 1 }, f);
    b.text('Event Dates:', e.x + 24, 442, { fontSize: 11, fill: '#64748B' }, f);
    b.text(e.dates, e.x + 24, 460, { fontSize: 14, fontWeight: 700, fill: '#0F172A' }, f);

    b.text('Program Overview:', e.x + 24, 492, { fontSize: 11, fill: '#64748B' }, f);
    b.text(`${e.talks} · 4 Tracks · Contributor Day`, e.x + 24, 510, { fontSize: 13, fontWeight: 700, fill: '#0F172A' }, f);

    b.rect('Offline Pill', e.x + 24, 544, 120, 26, { fill: '#ECFDF5', stroke: '#A7F3D0', cornerRadius: 13, strokeWeight: 1 }, f);
    b.text('✔ 100% Offline Ready', e.x + 34, 550, { fontSize: 10, fontWeight: 700, fill: '#047857' }, f);

    b.rect('Open Btn', e.x + 24, 620, 320, 48, { fill: '#FF4D2D', cornerRadius: 12 }, f);
    b.text('Open Event Companion  →', e.x + 100, 635, { fontSize: 13, fontWeight: 700, fill: '#FFFFFF' }, f);
  }

  // Footer
  b.text('CampBuddy · Local-first Community Companion · Built by WPSimplified · Privacy Policy · Terms of Use', 48, 740, { fontSize: 11, fill: '#64748B' }, f);
}

// W3: Web Schedule Explorer
{
  const f = b.frame('Web - Schedule Explorer', 2680, 1050, 1280, 800, { fill: '#FFF7ED', clip: true });

  // Top Nav
  b.rect('Web Nav BG 3', 0, 0, 1280, 72, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.text('WordCamp Delhi 2026', 48, 22, { fontSize: 20, fontWeight: 800, fill: '#0F172A', fontFamily: 'Poppins' }, f);
  b.text('Multi-Track Schedule', 290, 25, { fontSize: 14, fontWeight: 700, fill: '#FF4D2D' }, f);
  b.text('Attendees', 460, 25, { fontSize: 14, fontWeight: 600, fill: '#64748B' }, f);
  b.text('Sponsors', 550, 25, { fontSize: 14, fontWeight: 600, fill: '#64748B' }, f);
  b.text('Quests', 630, 25, { fontSize: 14, fontWeight: 600, fill: '#64748B' }, f);

  b.rect('PDF Export Btn', 1100, 18, 140, 36, { fill: '#FFFFFF', stroke: '#CBD5E1', cornerRadius: 8, strokeWeight: 1 }, f);
  b.text('📄 Export PDF', 1130, 27, { fontSize: 12, fontWeight: 700, fill: '#0F172A' }, f);

  // Day Filter Bar
  b.rect('Day 1 Btn', 48, 92, 120, 36, { fill: '#FF4D2D', cornerRadius: 8 }, f);
  b.text('Day 1 · Nov 21', 66, 102, { fontSize: 12, fontWeight: 700, fill: '#FFFFFF' }, f);
  b.rect('Day 2 Btn', 180, 92, 120, 36, { fill: '#FFFFFF', stroke: '#CBD5E1', cornerRadius: 8, strokeWeight: 1 }, f);
  b.text('Day 2 · Nov 22', 198, 102, { fontSize: 12, fontWeight: 600, fill: '#64748B' }, f);

  // 3-Track Columns Layout
  const tracks = [
    { title: 'Track 1 · Main Hall', color: '#FF4D2D', x: 48, w: 370 },
    { title: 'Track 2 · Workshop Room', color: '#0284C7', x: 440, w: 370 },
    { title: 'Track 3 · Contributor Hub', color: '#D97706', x: 832, w: 370 },
  ];

  for (const tr of tracks) {
    b.rect('Track Header', tr.x, 148, tr.w, 40, { fill: '#1E293B', cornerRadius: 10 }, f);
    b.text(tr.title, tr.x + 16, 160, { fontSize: 13, fontWeight: 700, fill: '#FFFFFF' }, f);
  }

  // Row 1: 10:00 AM
  const r1 = [
    { talk: 'Building Offline-First Web Apps', spk: 'Rahul Verma · WPSimplified', time: '10:00 – 10:45 AM', x: 48 },
    { talk: 'Mastering Full-Site Editing & Patterns', spk: 'Elena Rostova · Automattic', time: '10:00 – 10:45 AM', x: 440 },
    { talk: 'First Time Contributor Onboarding', spk: 'Priya Sharma · Community Team', time: '10:00 – 10:45 AM', x: 832 },
  ];
  for (const item of r1) {
    b.rect('Talk Card', item.x, 204, 370, 110, { fill: '#FFFFFF', cornerRadius: 14, stroke: '#E2E8F0', strokeWeight: 1 }, f);
    b.text(item.time, item.x + 16, 218, { fontSize: 11, fontWeight: 700, fill: '#D97706' }, f);
    b.text(item.talk, item.x + 16, 240, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);
    b.text(item.spk, item.x + 16, 268, { fontSize: 11, fill: '#64748B' }, f);
    b.text('★ Bookmark', item.x + 280, 218, { fontSize: 11, fontWeight: 700, fill: '#FF4D2D' }, f);
  }

  // Row 2: 11:15 AM
  const r2 = [
    { talk: 'Scaling WordPress to 10M Pageviews', spk: 'Amit Singh · Cloudways', time: '11:15 – 12:00 PM', x: 48 },
    { talk: 'Modern CSS & Gutenberg Theming', spk: 'Neha Gupta · UI/UX Lead', time: '11:15 – 12:00 PM', x: 440 },
    { talk: 'Translating WordPress: Polyglots Table', spk: 'Table Leads & Mentors', time: '11:15 – 12:00 PM', x: 832 },
  ];
  for (const item of r2) {
    b.rect('Talk Card', item.x, 328, 370, 110, { fill: '#FFFFFF', cornerRadius: 14, stroke: '#E2E8F0', strokeWeight: 1 }, f);
    b.text(item.time, item.x + 16, 342, { fontSize: 11, fontWeight: 700, fill: '#D97706' }, f);
    b.text(item.talk, item.x + 16, 364, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);
    b.text(item.spk, item.x + 16, 392, { fontSize: 11, fill: '#64748B' }, f);
    b.text('★ Bookmark', item.x + 280, 342, { fontSize: 11, fontWeight: 700, fill: '#FF4D2D' }, f);
  }

  // Row 3: 01:30 PM
  const r3 = [
    { talk: 'Headless WordPress with Next.js 15', spk: 'Aditya Vikram Singh · WP VIP', time: '01:30 – 02:15 PM', x: 48 },
    { talk: 'Security Auditing for Core & Plugins', spk: 'David Kim · Automattic Security', time: '01:30 – 02:15 PM', x: 440 },
    { talk: 'Plugin Review Guidelines Workshop', spk: 'Plugin Review Team', time: '01:30 – 02:15 PM', x: 832 },
  ];
  for (const item of r3) {
    b.rect('Talk Card', item.x, 452, 370, 110, { fill: '#FFFFFF', cornerRadius: 14, stroke: '#E2E8F0', strokeWeight: 1 }, f);
    b.text(item.time, item.x + 16, 466, { fontSize: 11, fontWeight: 700, fill: '#D97706' }, f);
    b.text(item.talk, item.x + 16, 488, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);
    b.text(item.spk, item.x + 16, 516, { fontSize: 11, fill: '#64748B' }, f);
    b.text('★ Bookmark', item.x + 280, 466, { fontSize: 11, fontWeight: 700, fill: '#FF4D2D' }, f);
  }

  // Bottom Summary Bar
  b.rect('Bottom Schedule Bar', 48, 590, 1184, 80, { fill: '#FEF3C7', stroke: '#FDE68A', cornerRadius: 16, strokeWeight: 1 }, f);
  b.text('My Schedule Summary: 4 sessions saved (2h 45m)', 72, 610, { fontSize: 14, fontWeight: 800, fill: '#92400E' }, f);
  b.text('0 conflicts detected · Ready for 100% offline access in mobile PWA', 72, 634, { fontSize: 12, fill: '#B45309' }, f);
  b.rect('Save Local Btn', 1040, 608, 160, 44, { fill: '#D97706', cornerRadius: 10 }, f);
  b.text('Sync to Phone →', 1070, 622, { fontSize: 13, fontWeight: 700, fill: '#FFFFFF' }, f);
}

// W4: Web Attendee & Sponsor Showcase
{
  const f = b.frame('Web - Attendees Sponsors', 4000, 1050, 1280, 800, { fill: '#FFF7ED', clip: true });

  // Top Nav
  b.rect('Web Nav BG 4', 0, 0, 1280, 72, { fill: '#FFFFFF', stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.text('WordCamp Delhi 2026', 48, 22, { fontSize: 20, fontWeight: 800, fill: '#0F172A', fontFamily: 'Poppins' }, f);
  b.text('Schedule', 290, 25, { fontSize: 14, fontWeight: 600, fill: '#64748B' }, f);
  b.text('Attendees & Community', 380, 25, { fontSize: 14, fontWeight: 700, fill: '#FF4D2D' }, f);
  b.text('Sponsors & Steals', 580, 25, { fontSize: 14, fontWeight: 600, fill: '#64748B' }, f);

  // Search & Claim Header
  b.text('Community & Attendee Directory', 48, 96, { fontSize: 28, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Discover fellow attendees, speakers, organizers, and sponsor teams.', 48, 134, { fontSize: 14, fill: '#64748B' }, f);

  // Claim Banner
  b.rect('Web Claim Card', 48, 166, 1184, 60, { fill: '#FFEDD5', stroke: '#FDBA74', cornerRadius: 14, strokeWeight: 1 }, f);
  b.text('🛡 Attending WordCamp Delhi? Claim your public attendee listing to link your CampCard.', 72, 188, { fontSize: 13, fontWeight: 700, fill: '#9A3412' }, f);
  b.rect('Claim Trigger', 1040, 178, 160, 36, { fill: '#EA580C', cornerRadius: 8 }, f);
  b.text('Claim My Profile', 1070, 188, { fontSize: 12, fontWeight: 700, fill: '#FFFFFF' }, f);

  // 3-Column Attendee Cards
  const webAttendees = [
    { name: 'Amit Singh', role: 'Speaker', title: 'Senior Cloud Architect', org: 'Cloudways', x: 48, y: 246 },
    { name: 'Priya Sharma', role: 'Organizer', title: 'Lead Designer', org: 'WordPress Delhi', x: 440, y: 246 },
    { name: 'David Kim', role: 'Sponsor Lead', title: 'DevRel Director', org: 'Automattic', x: 832, y: 246 },
    { name: 'Rahul Verma', role: 'Speaker', title: 'Tech Lead', org: 'WPSimplified', x: 48, y: 350 },
    { name: 'Elena Rostova', role: 'Speaker', title: 'Core Contributor', org: 'Automattic', x: 440, y: 350 },
    { name: 'Neha Gupta', role: 'Volunteer', title: 'Registration Lead', org: 'Community', x: 832, y: 350 },
  ];

  for (const wa of webAttendees) {
    b.rect('WA Card', wa.x, wa.y, 370, 90, { fill: '#FFFFFF', cornerRadius: 14, stroke: '#E2E8F0', strokeWeight: 1 }, f);
    b.rect('WA Pic', wa.x + 16, wa.y + 18, 54, 54, { fill: '#1E293B', cornerRadius: 27 }, f);
    b.text(wa.name[0], wa.x + 36, wa.y + 32, { fontSize: 20, fontWeight: 800, fill: '#FF4D2D' }, f);
    b.text(wa.name, wa.x + 84, wa.y + 20, { fontSize: 15, fontWeight: 800, fill: '#0F172A' }, f);
    b.text(`${wa.title} · ${wa.org}`, wa.x + 84, wa.y + 42, { fontSize: 11, fill: '#64748B' }, f);
    b.rect('WA Role', wa.x + 84, wa.y + 60, 70, 18, { fill: '#F1F5F9', cornerRadius: 9 }, f);
    b.text(wa.role, wa.x + 94, wa.y + 63, { fontSize: 9, fontWeight: 700, fill: '#334155' }, f);
  }

  // Sponsors Section
  b.text('Featured Sponsors & Community Partners', 48, 470, { fontSize: 22, fontWeight: 800, fill: '#0F172A' }, f);

  const sponsors = [
    { name: 'Automattic', tier: 'PLATINUM SPONSOR', booth: 'Booth P1 · Main Hall', deal: 'Free .blog domain + 6 months hosting', x: 48 },
    { name: 'Cloudways', tier: 'GOLD SPONSOR', booth: 'Booth G2 · Arena', deal: '$50 Cloud credits for all attendees', x: 440 },
    { name: 'WPSimplified', tier: 'MEDIA PARTNER', booth: 'CampBuddy Companion Desk', deal: 'Free Open Source companion toolkit', x: 832 },
  ];

  for (const sp of sponsors) {
    b.rect('Sponsor Card', sp.x, 508, 370, 180, { fill: '#FFFFFF', cornerRadius: 18, stroke: '#E2E8F0', strokeWeight: 1 }, f);
    b.text(sp.tier, sp.x + 20, 528, { fontSize: 10, fontWeight: 800, fill: '#D97706' }, f);
    b.text(sp.name, sp.x + 20, 550, { fontSize: 20, fontWeight: 800, fill: '#0F172A' }, f);
    b.text(`📍 ${sp.booth}`, sp.x + 20, 580, { fontSize: 12, fill: '#64748B' }, f);
    b.rect('Deal Box', sp.x + 20, 608, 330, 40, { fill: '#ECFDF5', stroke: '#A7F3D0', cornerRadius: 10, strokeWeight: 1 }, f);
    b.text(`🎁 Deal: ${sp.deal}`, sp.x + 30, 620, { fontSize: 11, fontWeight: 700, fill: '#065F46' }, f);
  }
}

// W5: Web Organizer & Admin Portal
{
  const f = b.frame('Web - Organizer Portal', 5320, 1050, 1280, 800, { fill: '#F8FAFC', clip: true });

  // Admin Top Bar
  b.rect('Admin Nav', 0, 0, 1280, 72, { fill: '#1E293B' }, f);
  b.text('CampBuddy Staff Console', 48, 22, { fontSize: 20, fontWeight: 800, fill: '#FFFFFF', fontFamily: 'Poppins' }, f);
  b.rect('Staff Badge', 320, 20, 150, 32, { fill: '#334155', cornerRadius: 16 }, f);
  b.text('👑 Super Admin & Manager', 332, 28, { fontSize: 11, fontWeight: 700, fill: '#38BDF8' }, f);

  b.text('WordCamp Delhi 2026', 980, 26, { fontSize: 13, fontWeight: 700, fill: '#94A3B8' }, f);
  b.rect('Logout Btn', 1160, 20, 80, 32, { fill: '#475569', cornerRadius: 8 }, f);
  b.text('Sign Out', 1176, 28, { fontSize: 11, fontWeight: 700, fill: '#FFFFFF' }, f);

  // Stat Counters
  const stats = [
    { num: '1,248', label: 'Synced Devices (Phones)', icon: '📱', x: 48 },
    { num: '92%', label: 'Offline Warmup Complete', icon: '⚡', x: 350 },
    { num: '482', label: 'Top Talk Bookmarks', icon: '⭐', x: 652 },
    { num: '0', label: 'Active Schedule Delays', icon: '⏱', x: 954 },
  ];
  for (const st of stats) {
    b.rect('Stat Card', st.x, 96, 280, 100, { fill: '#FFFFFF', cornerRadius: 16, stroke: '#E2E8F0', strokeWeight: 1 }, f);
    b.text(st.icon, st.x + 20, 116, { fontSize: 22 }, f);
    b.text(st.num, st.x + 60, 114, { fontSize: 24, fontWeight: 800, fill: '#0F172A' }, f);
    b.text(st.label, st.x + 20, 156, { fontSize: 12, fill: '#64748B' }, f);
  }

  // Live Announcement Broadcaster
  b.rect('Broadcast Card', 48, 220, 580, 480, { fill: '#FFFFFF', cornerRadius: 20, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.text('📢 Broadcast Announcement to All Devices', 72, 246, { fontSize: 16, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Sends high-priority local push notification to 1,248 attendee devices.', 72, 272, { fontSize: 12, fill: '#64748B' }, f);

  b.text('Announcement Title:', 72, 310, { fontSize: 12, fontWeight: 700, fill: '#334155' }, f);
  b.rect('Title Input', 72, 330, 532, 44, { fill: '#F8FAFC', stroke: '#CBD5E1', cornerRadius: 8, strokeWeight: 1 }, f);
  b.text('Lunch is served at Main Dining Hall!', 88, 342, { fontSize: 13, fill: '#0F172A' }, f);

  b.text('Message Body:', 72, 394, { fontSize: 12, fontWeight: 700, fill: '#334155' }, f);
  b.rect('Body Input', 72, 414, 532, 100, { fill: '#F8FAFC', stroke: '#CBD5E1', cornerRadius: 8, strokeWeight: 1 }, f);
  b.text('Hot meals and vegetarian options are ready. Please visit sponsor booths in Hall B.', 88, 430, { fontSize: 12, fill: '#0F172A' }, f);

  b.rect('Send Broadcast Btn', 72, 540, 240, 48, { fill: '#FF4D2D', cornerRadius: 10 }, f);
  b.text('Send Live Broadcast  🚀', 106, 555, { fontSize: 13, fontWeight: 700, fill: '#FFFFFF' }, f);

  // Schedule Delay Controller
  b.rect('Delay Card', 652, 220, 580, 480, { fill: '#FFFFFF', cornerRadius: 20, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.text('⏱ Schedule Delay Broadcast Controller', 676, 246, { fontSize: 16, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Automatically updates talk countdowns on every phone without server reload.', 676, 272, { fontSize: 12, fill: '#64748B' }, f);

  b.text('Select Track:', 676, 310, { fontSize: 12, fontWeight: 700, fill: '#334155' }, f);
  b.rect('Track Sel', 676, 330, 532, 44, { fill: '#F8FAFC', stroke: '#CBD5E1', cornerRadius: 8, strokeWeight: 1 }, f);
  b.text('All Tracks (Track 1 & Track 2)', 692, 342, { fontSize: 13, fill: '#0F172A' }, f);

  b.text('Delay Duration:', 676, 394, { fontSize: 12, fontWeight: 700, fill: '#334155' }, f);
  b.rect('Delay 10m', 676, 414, 120, 44, { fill: '#F1F5F9', cornerRadius: 8 }, f);
  b.text('+10 Mins', 710, 428, { fontSize: 13, fontWeight: 700, fill: '#334155' }, f);

  b.rect('Delay 15m', 812, 414, 120, 44, { fill: '#FFEDD5', stroke: '#FF4D2D', cornerRadius: 8, strokeWeight: 2 }, f);
  b.text('+15 Mins', 846, 428, { fontSize: 13, fontWeight: 800, fill: '#D33417' }, f);

  b.rect('Delay 30m', 948, 414, 120, 44, { fill: '#F1F5F9', cornerRadius: 8 }, f);
  b.text('+30 Mins', 982, 428, { fontSize: 13, fontWeight: 700, fill: '#334155' }, f);

  b.rect('Apply Delay Btn', 676, 540, 240, 48, { fill: '#D97706', cornerRadius: 10 }, f);
  b.text('Apply +15m Delay  ⏰', 714, 555, { fontSize: 13, fontWeight: 700, fill: '#FFFFFF' }, f);
}

// ==========================================
// 3. PWA APP SCREENS (Y = 1950)
// ==========================================

// P1: PWA Instant Install & Cache Hero
{
  const f = b.frame('PWA - Install Offline Hero', 40, 1950, 390, 844, { fill: '#FFF7ED', clip: true });

  // Browser Chrome Bar
  b.rect('Browser Chrome', 0, 0, 390, 78, { fill: '#F1F5F9' }, f);
  b.rect('URL Bar', 16, 38, 358, 32, { fill: '#FFFFFF', stroke: '#CBD5E1', cornerRadius: 16, strokeWeight: 1 }, f);
  b.text('🔒 campbuddy.club', 140, 46, { fontSize: 11, fontWeight: 600, fill: '#0F172A' }, f);

  // Floating PWA Install Modal / Banner
  b.rect('A2HS Card', 16, 96, 358, 180, { fill: '#1E293B', cornerRadius: 20, stroke: '#334155', strokeWeight: 1 }, f);
  b.rect('App Icon Box', 32, 114, 48, 48, { fill: '#FF4D2D', cornerRadius: 24 }, f);
  b.text('C', 48, 126, { fontSize: 24, fontWeight: 800, fill: '#FFFFFF' }, f);

  b.text('Install CampBuddy PWA', 92, 116, { fontSize: 16, fontWeight: 800, fill: '#FFFFFF' }, f);
  b.text('Add to Home Screen for 100% offline access', 92, 138, { fontSize: 11, fill: '#94A3B8' }, f);
  b.text('✔ Zero App Store account needed\n✔ Instant launch · No 50MB download\n✔ Works when venue Wi-Fi goes down', 92, 160, { fontSize: 10, fill: '#CBD5E1' }, f);

  b.rect('Install A2HS Btn', 32, 226, 326, 38, { fill: '#FF4D2D', cornerRadius: 10 }, f);
  b.text('Install PWA to Home Screen  ⚡', 100, 237, { fontSize: 12, fontWeight: 700, fill: '#FFFFFF' }, f);

  // Offline Cache Status
  b.rect('Cache Status', 16, 290, 358, 64, { fill: '#ECFDF5', stroke: '#A7F3D0', cornerRadius: 14, strokeWeight: 1 }, f);
  b.text('⚡ Service Worker Active · 48 Assets Cached', 30, 304, { fontSize: 11, fontWeight: 700, fill: '#047857' }, f);
  b.text('IndexedDB schedule & CampCard encrypted locally', 30, 324, { fontSize: 10, fill: '#059669' }, f);

  // Quick Event Selection
  b.text('Choose event to store offline:', 16, 372, { fontSize: 13, fontWeight: 800, fill: '#0F172A' }, f);

  b.rect('PWA Ev 1', 16, 396, 358, 80, { fill: '#FFFFFF', cornerRadius: 16, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.rect('Ev 1 Logo', 30, 412, 48, 48, { fill: '#1E293B', cornerRadius: 24 }, f);
  b.text('D', 46, 424, { fontSize: 20, fontWeight: 800, fill: '#FF4D2D' }, f);
  b.text('WordCamp Delhi 2026', 88, 416, { fontSize: 15, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('36 Talks · Full Schedule Synced', 88, 438, { fontSize: 11, fill: '#64748B' }, f);
  b.rect('Enter Pill', 300, 420, 60, 30, { fill: '#FF4D2D', cornerRadius: 15 }, f);
  b.text('OPEN', 314, 428, { fontSize: 11, fontWeight: 800, fill: '#FFFFFF' }, f);

  b.rect('PWA Ev 2', 16, 490, 358, 80, { fill: '#FFFFFF', cornerRadius: 16, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.rect('Ev 2 Logo', 30, 506, 48, 48, { fill: '#334155', cornerRadius: 24 }, f);
  b.text('N', 46, 518, { fontSize: 20, fontWeight: 800, fill: '#FFFFFF' }, f);
  b.text('WordCamp Nairobi 2026', 88, 510, { fontSize: 15, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('28 Talks · Scheduled for Nov 28', 88, 532, { fontSize: 11, fill: '#64748B' }, f);
  b.rect('Enter Pill 2', 300, 514, 60, 30, { fill: '#F1F5F9', cornerRadius: 15 }, f);
  b.text('SYNC', 314, 522, { fontSize: 11, fontWeight: 800, fill: '#0F172A' }, f);

  b.text('CampBuddy PWA runs completely standalone once installed.', 44, 760, { fontSize: 10, fill: '#94A3B8' }, f);
}

// P2: PWA Mobile Dashboard
{
  const f = b.frame('PWA - Standalone Dashboard', 470, 1950, 390, 844, { fill: '#FFF7ED', clip: true });

  // PWA Standalone Header (No browser chrome)
  b.rect('PWA Top Bar', 0, 0, 390, 44, { fill: '#1E293B' }, f);
  b.ellipse('Offline Green Dot', 16, 17, 10, 10, { fill: '#10B981' }, f);
  b.text('Offline PWA Mode · Venue Safe', 34, 15, { fontSize: 10, fontWeight: 700, fill: '#A7F3D0' }, f);
  b.text('10:14 AM', 330, 15, { fontSize: 11, fontWeight: 700, fill: '#FFFFFF' }, f);

  // Hero Card
  b.rect('PWA Hero Card', 16, 54, 358, 170, { fill: '#1E293B', cornerRadius: 22, stroke: '#334155', strokeWeight: 1 }, f);
  b.text('WordCamp Delhi 2026', 32, 70, { fontSize: 18, fontWeight: 800, fill: '#FFFFFF' }, f);
  b.text('NDMC Convention Centre · Hall A & B', 32, 94, { fontSize: 11, fill: '#94A3B8' }, f);

  b.rect('PWA Next Box', 28, 120, 334, 86, { fill: '#0F172A', cornerRadius: 14 }, f);
  b.text('⚡ NEXT TALK IN 12 MIN', 42, 132, { fontSize: 10, fontWeight: 800, fill: '#F59E0B' }, f);
  b.text('Building Offline-First Web Apps', 42, 150, { fontSize: 13, fontWeight: 700, fill: '#FFFFFF' }, f);
  b.text('Rahul Verma · Hall 1', 42, 170, { fontSize: 11, fill: '#94A3B8' }, f);
  b.rect('PWA View Btn', 270, 146, 76, 32, { fill: '#D97706', cornerRadius: 16 }, f);
  b.text('VIEW', 292, 154, { fontSize: 10, fontWeight: 800, fill: '#FFFFFF' }, f);

  // Hubs ActionGrid
  b.text('Quick Event Hubs', 16, 240, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);

  const pwaHubs = [
    { title: 'My Schedule', sub: '4 saved talks', icon: '📅', x: 16, y: 262 },
    { title: 'Full Program', sub: '36 talks & tracks', icon: '🎤', x: 200, y: 262 },
    { title: 'Offline Card', sub: 'QR Badge', icon: '🪪', x: 16, y: 356 },
    { title: 'Attendees', sub: 'Directory & roles', icon: '👥', x: 200, y: 356 },
    { title: 'Quests', sub: 'Roadmap', icon: '🎯', x: 16, y: 450 },
    { title: 'Deals & Steals', sub: 'Free tools', icon: '🎁', x: 200, y: 450 },
  ];
  for (const h of pwaHubs) {
    b.rect('PWA Hub Tile', h.x, h.y, 174, 82, { fill: '#FFFFFF', cornerRadius: 16, stroke: '#E2E8F0', strokeWeight: 1 }, f);
    b.rect('PWA Hub Icon', h.x + 12, h.y + 12, 32, 32, { fill: '#FFEDD5', cornerRadius: 16 }, f);
    b.text(h.icon, h.x + 18, h.y + 18, { fontSize: 14 }, f);
    b.text(h.title, h.x + 12, h.y + 48, { fontSize: 13, fontWeight: 800, fill: '#0F172A' }, f);
    b.text(h.sub, h.x + 12, h.y + 64, { fontSize: 10, fill: '#64748B' }, f);
  }

  // Floating Help Bar
  b.rect('PWA SOS Bar', 16, 680, 358, 50, { fill: '#EF4444', cornerRadius: 25 }, f);
  b.text('🛟  Need Help at WordCamp?', 32, 696, { fontSize: 13, fontWeight: 700, fill: '#FFFFFF' }, f);
  b.text('Helpdesk & Organizers', 32, 712, { fontSize: 10, fill: 'rgba(255,255,255,0.85)' }, f);
  b.rect('PWA Help Badge', 304, 690, 56, 30, { fill: 'rgba(255,255,255,0.25)', cornerRadius: 15 }, f);
  b.text('HELP', 316, 698, { fontSize: 11, fontWeight: 800, fill: '#FFFFFF' }, f);

  b.mobileBottomNav(f, 'home');
}

// P3: PWA Offline Schedule & Conflict Resolver
{
  const f = b.frame('PWA - Offline Schedule', 900, 1950, 390, 844, { fill: '#FFF7ED', clip: true });

  b.rect('PWA Top Bar 2', 0, 0, 390, 44, { fill: '#1E293B' }, f);
  b.text('My Offline Schedule', 16, 14, { fontSize: 12, fontWeight: 700, fill: '#FFFFFF' }, f);

  b.text('Smart Schedule & Conflicts', 16, 60, { fontSize: 20, fontWeight: 800, fill: '#0F172A' }, f);

  // Conflict Warning Card
  b.rect('Conflict Card', 16, 96, 358, 70, { fill: '#FEF2F2', stroke: '#FECACA', cornerRadius: 14, strokeWeight: 1 }, f);
  b.text('⚠ SCHEDULE OVERLAP DETECTED', 28, 110, { fontSize: 11, fontWeight: 800, fill: '#991B1B' }, f);
  b.text('You have starred two talks at 11:30 AM in Track A and Track B.', 28, 128, { fontSize: 10, fill: '#7F1D1D' }, f);
  b.text('Tap to resolve track preference →', 28, 144, { fontSize: 10, fontWeight: 700, fill: '#B91C1C' }, f);

  // Overlapping talks
  b.rect('Conflict Talk 1', 16, 180, 358, 90, { fill: '#FFFFFF', stroke: '#EF4444', cornerRadius: 14, strokeWeight: 1.5 }, f);
  b.text('TRACK A · 11:30 AM', 28, 194, { fontSize: 10, fontWeight: 800, fill: '#D33417' }, f);
  b.text('Building Offline-First Web Apps', 28, 212, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Rahul Verma · Hall 1', 28, 234, { fontSize: 11, fill: '#64748B' }, f);
  b.rect('Keep 1', 290, 204, 70, 30, { fill: '#FF4D2D', cornerRadius: 6 }, f);
  b.text('KEEP', 310, 212, { fontSize: 10, fontWeight: 700, fill: '#FFFFFF' }, f);

  b.rect('Conflict Talk 2', 16, 280, 358, 90, { fill: '#FFFFFF', stroke: '#CBD5E1', cornerRadius: 14, strokeWeight: 1 }, f);
  b.text('TRACK B · 11:30 AM', 28, 294, { fontSize: 10, fontWeight: 800, fill: '#0284C7' }, f);
  b.text('Full-Site Editing & Custom Blocks', 28, 312, { fontSize: 14, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Elena Rostova · Hall 2', 28, 334, { fontSize: 11, fill: '#64748B' }, f);
  b.rect('Remove 2', 290, 304, 70, 30, { fill: '#F1F5F9', cornerRadius: 6 }, f);
  b.text('REMOVE', 300, 312, { fontSize: 10, fontWeight: 700, fill: '#64748B' }, f);

  // 10-Min Notification Settings
  b.rect('Notification Box', 16, 390, 358, 80, { fill: '#FFFFFF', cornerRadius: 16, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.text('🔔 10-Minute Prior Offline Alerts', 28, 406, { fontSize: 13, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('Browser vibration & sound alert before your saved session starts.', 28, 426, { fontSize: 10, fill: '#64748B' }, f);
  b.rect('Toggle On', 290, 412, 68, 30, { fill: '#10B981', cornerRadius: 15 }, f);
  b.text('ON ✔', 308, 420, { fontSize: 10, fontWeight: 800, fill: '#FFFFFF' }, f);

  b.mobileBottomNav(f, 'my-day');
}

// P4: PWA Offline CampCard & Camera QR Scan
{
  const f = b.frame('PWA - Offline CampCard', 1330, 1950, 390, 844, { fill: '#FFF7ED', clip: true });

  b.rect('PWA Top Bar 3', 0, 0, 390, 44, { fill: '#1E293B' }, f);
  b.text('Offline CampCard & QR Scan', 16, 14, { fontSize: 12, fontWeight: 700, fill: '#FFFFFF' }, f);

  // Offline QR Card
  b.rect('PWA Card Badge', 24, 60, 342, 430, { fill: '#FFFFFF', cornerRadius: 24, stroke: '#E2E8F0', strokeWeight: 1 }, f);
  b.rect('PWA Badge Top', 24, 60, 342, 54, { fill: '#1E293B', cornerRadius: 24 }, f);
  b.rect('Top Fix', 24, 84, 342, 30, { fill: '#1E293B' }, f);
  b.text('OFFLINE DIGITAL PASS · NO WI-FI NEEDED', 44, 76, { fontSize: 10, fontWeight: 800, fill: '#F59E0B' }, f);
  b.text('WordCamp Delhi 2026', 44, 94, { fontSize: 9, fill: '#94A3B8' }, f);

  b.text('Sunil Kumar Sharma', 44, 136, { fontSize: 19, fontWeight: 800, fill: '#0F172A' }, f);
  b.text('WordPress Engineer · WPSimplified', 44, 160, { fontSize: 12, fontWeight: 600, fill: '#FF4D2D' }, f);

  // QR Code
  b.rect('PWA QR Area', 95, 190, 200, 200, { fill: '#F8FAFC', stroke: '#E2E8F0', cornerRadius: 14, strokeWeight: 1 }, f);
  b.rect('QR P1', 115, 210, 46, 46, { fill: '#0F172A', cornerRadius: 4 }, f);
  b.rect('QR P2', 229, 210, 46, 46, { fill: '#0F172A', cornerRadius: 4 }, f);
  b.rect('QR P3', 115, 324, 46, 46, { fill: '#0F172A', cornerRadius: 4 }, f);
  b.rect('QR D1', 176, 210, 38, 46, { fill: '#0F172A', cornerRadius: 3 }, f);
  b.rect('QR D2', 176, 280, 99, 40, { fill: '#0F172A', cornerRadius: 3 }, f);
  b.rect('QR D3', 115, 276, 46, 36, { fill: '#0F172A', cornerRadius: 3 }, f);
  b.rect('QR D4', 176, 324, 99, 46, { fill: '#0F172A', cornerRadius: 3 }, f);

  b.text('Zero-server camera swap: scan directly on phone', 55, 410, { fontSize: 10, fill: '#64748B' }, f);

  // Camera QR Scanner Trigger
  b.rect('Scan Camera Btn', 24, 510, 342, 52, { fill: '#FF4D2D', cornerRadius: 14 }, f);
  b.text('📷 Scan Peer CampCard (Offline Camera)', 64, 528, { fontSize: 13, fontWeight: 800, fill: '#FFFFFF' }, f);

  // Export buttons
  b.rect('vCard Btn', 24, 574, 164, 42, { fill: '#FFFFFF', stroke: '#E2E8F0', cornerRadius: 10, strokeWeight: 1 }, f);
  b.text('📇 Export vCard', 56, 588, { fontSize: 11, fontWeight: 700, fill: '#0F172A' }, f);

  b.rect('Wallet Btn', 202, 574, 164, 42, { fill: '#FFFFFF', stroke: '#E2E8F0', cornerRadius: 10, strokeWeight: 1 }, f);
  b.text('🎟 Apple/Google Pass', 224, 588, { fontSize: 11, fontWeight: 700, fill: '#0F172A' }, f);

  b.mobileBottomNav(f, 'camp-card');
}

// Write the script to scratch/figcraft_script.json
const outputPath = path.resolve(rootDir, 'scratch', 'figcraft_script.json');
fs.writeFileSync(outputPath, JSON.stringify(b.commands, null, 2), 'utf8');

console.log(`Generated ${b.commands.length} FigCraft commands.`);
console.log(`Saved script to: ${outputPath}`);
