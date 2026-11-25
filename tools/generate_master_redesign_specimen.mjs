import fs from 'node:fs';
import path from 'node:path';

const artifactFile = 'C:/Users/sunil/.gemini/antigravity/brain/bd655e0a-5a1d-4d84-917e-eca77c7a4030/figcraft_design_system_specimen.html';
const docFile = 'docs/designs/specimen.html';

// Read all SVG icons from docs/icons/svg/
const svgDir = path.resolve('docs/icons/svg');
const icons = {};
if (fs.existsSync(svgDir)) {
  const files = fs.readdirSync(svgDir).filter(f => f.endsWith('.svg'));
  for (const f of files) {
    const name = f.replace('.svg', '');
    icons[name] = fs.readFileSync(path.join(svgDir, f), 'utf8');
  }
}

// Fallback helper for icons (Lucide SVGs are stroke-based with fill="none"; never overwrite fill="none")
function getIcon(name, size = 18, color = 'currentColor') {
  if (icons[name]) {
    return icons[name]
      .replace(/width="[^"]*"/, `width="${size}"`)
      .replace(/height="[^"]*"/, `height="${size}"`)
      .replace(/stroke="[^"]*"/, `stroke="${color}"`);
  }
  // Safe minimal SVG fallback
  return `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="${color}" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg>`;
}

console.log(`Loaded ${Object.keys(icons).length} SVG icons.`);

// Build HTML content
const html = `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>WordCamp Delhi 2026 · CampBuddy Master UI/UX Redesign System</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg-dark: #0B1120;
      --bg-surface: #1E293B;
      --bg-surface-elevated: #334155;
      --paper: #FAF8F5;
      --paper-soft: #FFF7ED;
      --ink: #0F172A;
      --navy: #1E293B;
      --coral: #FF4D2D;
      --coral-hover: #E03616;
      --wine: #EA580C;
      --gold: #D97706;
      --gold-light: #FEF3C7;
      --peach: #FFEDD5;
      --line: #E2E8F0;
      --line-dark: #334155;
      --muted: #64748B;
      --muted-light: #94A3B8;
      --success: #10B981;
      --success-light: #ECFDF5;
      --danger: #EF4444;
      --danger-light: #FFF5F5;
      --font-heading: 'Baloo 2', sans-serif;
      --font-body: 'Poppins', -apple-system, BlinkMacSystemFont, sans-serif;
      --radius-sm: 8px;
      --radius-md: 14px;
      --radius-lg: 20px;
      --radius-pill: 9999px;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      background: var(--bg-dark);
      color: #F8FAFC;
      font-family: var(--font-body);
      line-height: 1.5;
      padding: 30px 20px 100px;
      -webkit-font-smoothing: antialiased;
    }

    /* Top Master Header */
    .master-header {
      max-width: 1720px;
      margin: 0 auto 30px auto;
      background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%);
      border: 1px solid var(--line-dark);
      border-radius: 24px;
      padding: 32px 40px;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 24px;
    }
    .header-left {
      max-width: 900px;
    }
    .brand-pills {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 12px;
      flex-wrap: wrap;
    }
    .badge-pill {
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      padding: 5px 12px;
      border-radius: var(--radius-pill);
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }
    .badge-pill-coral { background: rgba(255, 77, 45, 0.15); color: #FF6B4A; border: 1px solid rgba(255, 77, 45, 0.4); }
    .badge-pill-gold { background: rgba(217, 119, 6, 0.15); color: #F59E0B; border: 1px solid rgba(217, 119, 6, 0.4); }
    .badge-pill-green { background: rgba(16, 185, 129, 0.15); color: #10B981; border: 1px solid rgba(16, 185, 129, 0.4); }

    .master-title {
      font-family: var(--font-heading);
      font-size: 34px;
      font-weight: 800;
      color: #FFFFFF;
      line-height: 1.2;
    }
    .master-subtitle {
      font-size: 14px;
      color: var(--muted-light);
      margin-top: 8px;
      line-height: 1.6;
    }
    .header-right {
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      gap: 12px;
    }
    .live-pulse-box {
      background: rgba(15, 23, 42, 0.6);
      border: 1px solid var(--line-dark);
      padding: 10px 16px;
      border-radius: var(--radius-md);
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 12px;
      color: #E2E8F0;
    }
    .pulse-dot {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      background: var(--success);
      box-shadow: 0 0 10px var(--success);
      animation: pulse 1.8s infinite;
    }
    @keyframes pulse {
      0%, 100% { transform: scale(1); opacity: 1; }
      50% { transform: scale(1.2); opacity: 0.5; }
    }

    /* Master Platform Navigation Tabs */
    .platform-tabs {
      max-width: 1720px;
      margin: 0 auto 36px auto;
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
      background: #111827;
      padding: 8px;
      border-radius: 18px;
      border: 1px solid #1F2937;
    }
    .platform-tab {
      flex: 1;
      min-width: 220px;
      padding: 14px 20px;
      border-radius: 12px;
      background: transparent;
      border: none;
      color: var(--muted-light);
      font-family: var(--font-heading);
      font-size: 16px;
      font-weight: 700;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      cursor: pointer;
      transition: all 0.2s ease;
    }
    .platform-tab:hover {
      color: #FFFFFF;
      background: rgba(255, 255, 255, 0.05);
    }
    .platform-tab.active {
      background: var(--navy);
      color: #FFFFFF;
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
      border: 1px solid rgba(255, 255, 255, 0.1);
    }
    .platform-tab .count-badge {
      background: rgba(255, 255, 255, 0.15);
      font-size: 11px;
      padding: 2px 8px;
      border-radius: var(--radius-pill);
      font-family: var(--font-body);
    }

    /* Section Containers */
    .section-container {
      max-width: 1720px;
      margin: 0 auto 60px auto;
      display: none;
    }
    .section-container.active {
      display: block;
    }

    .section-intro {
      margin-bottom: 28px;
      padding-bottom: 16px;
      border-bottom: 1px solid var(--line-dark);
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
      flex-wrap: wrap;
      gap: 16px;
    }
    .section-intro h2 {
      font-family: var(--font-heading);
      font-size: 24px;
      font-weight: 800;
      color: #FFFFFF;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .section-intro p {
      color: var(--muted-light);
      font-size: 13px;
      margin-top: 4px;
    }

    /* Grids */
    .screens-grid-phones {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
      gap: 32px;
      justify-content: center;
    }
    .screens-grid-desktop {
      display: grid;
      grid-template-columns: 1fr;
      gap: 40px;
    }

    /* Phone Mockup Chrome */
    .phone-card-wrapper {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 14px;
    }
    .screen-meta-title {
      font-family: var(--font-heading);
      font-size: 15px;
      font-weight: 700;
      color: #E2E8F0;
      text-align: center;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .screen-meta-badge {
      font-size: 10px;
      padding: 2px 8px;
      border-radius: var(--radius-pill);
      background: rgba(255, 77, 45, 0.2);
      color: #FF6B4A;
      font-weight: 700;
      font-family: var(--font-body);
    }

    .phone-device {
      width: 360px;
      height: 760px;
      background: var(--paper-soft);
      border-radius: 44px;
      border: 9px solid #0F172A;
      box-shadow: 0 25px 60px rgba(0, 0, 0, 0.6), 0 0 0 1px rgba(255, 255, 255, 0.08);
      position: relative;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      color: var(--ink);
    }
    /* Dynamic Island / Notch */
    .notch {
      position: absolute;
      top: 8px;
      left: 50%;
      transform: translateX(-50%);
      width: 90px;
      height: 22px;
      background: #0F172A;
      border-radius: 12px;
      z-index: 100;
      display: flex;
      align-items: center;
      justify-content: flex-end;
      padding-right: 8px;
    }
    .notch-camera {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: #1E293B;
      border: 1px solid rgba(255, 255, 255, 0.1);
    }

    /* Status Bar */
    .phone-status-bar {
      height: 38px;
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
      padding: 0 20px 6px 20px;
      font-size: 11px;
      font-weight: 700;
      color: var(--navy);
      flex-shrink: 0;
      background: var(--paper-soft);
      z-index: 10;
    }
    .phone-status-bar.dark-theme {
      background: #0F172A;
      color: #F8FAFC;
    }

    /* Phone Content Area */
    .phone-scrollable {
      flex: 1;
      overflow-y: auto;
      overflow-x: hidden;
      padding: 12px 16px 20px 16px;
      display: flex;
      flex-direction: column;
      gap: 14px;
    }
    .phone-scrollable::-webkit-scrollbar { width: 4px; }
    .phone-scrollable::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.1); border-radius: 4px; }

    /* Bottom Navigation Bar */
    .phone-bottom-nav {
      height: 60px;
      background: #FFFFFF;
      border-top: 1px solid var(--line);
      display: flex;
      align-items: center;
      justify-content: space-around;
      padding: 0 10px;
      flex-shrink: 0;
      z-index: 20;
    }
    .nav-tab-item {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 3px;
      color: var(--muted);
      font-size: 10px;
      font-weight: 600;
      cursor: pointer;
      padding: 4px 8px;
      border-radius: 8px;
      transition: all 0.2s ease;
    }
    .nav-tab-item.active {
      color: var(--coral);
    }
    .nav-tab-item.active .nav-icon-wrap {
      background: var(--peach);
      border-radius: 12px;
    }
    .nav-icon-wrap {
      width: 28px;
      height: 28px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    /* UI Components (Rebuilt on beloved Wireframe DNA) */
    .app-bar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding-bottom: 2px;
    }
    .app-brand {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .app-brand-logo {
      height: 26px;
      width: auto;
      object-fit: contain;
    }
    .app-brand-title {
      font-family: var(--font-heading);
      font-size: 18px;
      font-weight: 800;
      color: var(--coral);
      line-height: 1;
    }
    .app-bar-actions {
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .role-badge-pill {
      background: rgba(255, 77, 45, 0.12);
      border: 1px solid rgba(255, 77, 45, 0.35);
      border-radius: var(--radius-pill);
      padding: 4px 10px;
      font-size: 10px;
      font-weight: 700;
      color: var(--coral);
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }
    .circle-icon-btn {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: var(--peach);
      border: 1px solid rgba(255, 77, 45, 0.15);
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--coral);
      cursor: pointer;
    }

    /* Dark Hero Card */
    .tactile-hero-card {
      background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%);
      border-radius: var(--radius-lg);
      padding: 18px;
      color: #FFFFFF;
      box-shadow: 0 10px 24px -6px rgba(15, 23, 42, 0.35);
      position: relative;
      overflow: hidden;
      border: 1px solid rgba(255, 255, 255, 0.1);
    }
    .tactile-hero-card::after {
      content: '';
      position: absolute;
      top: -30px;
      right: -30px;
      width: 120px;
      height: 120px;
      background: radial-gradient(circle, rgba(245, 158, 11, 0.25) 0%, transparent 70%);
      pointer-events: none;
    }
    .hero-pretitle {
      font-size: 11px;
      color: #94A3B8;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .hero-clock {
      font-family: var(--font-heading);
      font-size: 34px;
      font-weight: 800;
      line-height: 1.1;
      margin: 2px 0;
      color: #FFFFFF;
    }
    .hero-meta-line {
      font-size: 11px;
      color: #CBD5E1;
      margin-bottom: 12px;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .hero-next-pill {
      background: rgba(255, 255, 255, 0.08);
      border: 1px solid rgba(255, 255, 255, 0.18);
      border-radius: var(--radius-md);
      padding: 10px 12px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 10px;
    }
    .next-pill-text {
      flex: 1;
      min-width: 0;
    }
    .next-pill-title {
      font-size: 11px;
      font-weight: 700;
      color: #FFFFFF;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .next-pill-sub {
      font-size: 10px;
      color: #CBD5E1;
      margin-top: 2px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .btn-gold {
      background: var(--gold);
      color: #FFFFFF;
      border: none;
      border-radius: var(--radius-pill);
      padding: 6px 14px;
      font-size: 10px;
      font-weight: 700;
      text-transform: uppercase;
      cursor: pointer;
      flex-shrink: 0;
      box-shadow: 0 3px 8px rgba(217, 119, 6, 0.4);
    }

    /* People Strip */
    .section-head {
      font-family: var(--font-heading);
      font-size: 13px;
      font-weight: 700;
      color: var(--ink);
      text-transform: uppercase;
      letter-spacing: 0.5px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .section-head-sub {
      font-size: 11px;
      color: var(--muted);
      font-weight: 500;
      text-transform: none;
    }
    .avatar-scroll-strip {
      display: flex;
      gap: 12px;
      overflow-x: auto;
      padding-bottom: 4px;
    }
    .avatar-scroll-strip::-webkit-scrollbar { display: none; }
    .avatar-item {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 4px;
      flex-shrink: 0;
      cursor: pointer;
    }
    .avatar-frame {
      width: 48px;
      height: 48px;
      border-radius: 50%;
      border: 2px solid #FFFFFF;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
      position: relative;
      overflow: hidden;
      background: var(--peach);
    }
    .avatar-frame img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .avatar-dot {
      position: absolute;
      bottom: 1px;
      right: 1px;
      width: 10px;
      height: 10px;
      border-radius: 50%;
      background: var(--success);
      border: 2px solid #FFFFFF;
    }
    .avatar-label {
      font-size: 10px;
      font-weight: 600;
      color: var(--ink);
      max-width: 54px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      text-align: center;
    }

    /* 2x3 Tactile Grid */
    .tactile-2col-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
    }
    .tactile-card {
      background: var(--paper);
      border: 1px solid var(--line);
      border-radius: var(--radius-md);
      padding: 12px;
      display: flex;
      flex-direction: column;
      gap: 6px;
      cursor: pointer;
      transition: all 0.2s ease;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
    }
    .tactile-card:hover {
      border-color: var(--coral);
      transform: translateY(-2px);
      box-shadow: 0 6px 14px rgba(255, 77, 45, 0.08);
    }
    .icon-box-square {
      width: 36px;
      height: 36px;
      border-radius: 10px;
      background: var(--peach);
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--wine);
    }
    .tactile-card-title {
      font-family: var(--font-heading);
      font-size: 13px;
      font-weight: 700;
      color: var(--ink);
      line-height: 1.2;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .tactile-card-sub {
      font-size: 10px;
      color: var(--muted);
      line-height: 1.3;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    /* Sticky SOS Bar */
    .sticky-sos-wrap {
      background: var(--danger-light);
      border: 1.5px solid #FCA5A5;
      border-radius: var(--radius-md);
      padding: 10px 12px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
    }
    .sos-content {
      flex: 1;
      min-width: 0;
    }
    .sos-heading {
      font-size: 11px;
      font-weight: 700;
      color: #DC2626;
      display: flex;
      align-items: center;
      gap: 5px;
    }
    .sos-caption {
      font-size: 9px;
      color: var(--muted);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .btn-sos-red {
      background: var(--coral);
      color: #FFFFFF;
      border: none;
      border-radius: var(--radius-pill);
      padding: 6px 14px;
      font-size: 10px;
      font-weight: 800;
      cursor: pointer;
      flex-shrink: 0;
      text-transform: uppercase;
      box-shadow: 0 3px 8px rgba(239, 68, 68, 0.4);
    }

    /* Timeline & Session Cards */
    .filter-chips-row {
      display: flex;
      gap: 6px;
      overflow-x: auto;
      padding-bottom: 2px;
    }
    .filter-chips-row::-webkit-scrollbar { display: none; }
    .filter-chip {
      background: var(--paper);
      border: 1px solid var(--line);
      border-radius: var(--radius-pill);
      padding: 5px 12px;
      font-size: 10px;
      font-weight: 600;
      white-space: nowrap;
      cursor: pointer;
      color: var(--ink);
    }
    .filter-chip.active {
      background: var(--navy);
      color: #FFFFFF;
      border-color: var(--navy);
    }

    .session-item-card {
      background: #FFFFFF;
      border: 1px solid var(--line);
      border-radius: var(--radius-md);
      padding: 12px;
      display: flex;
      flex-direction: column;
      gap: 8px;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
    }
    .session-card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .time-badge {
      font-size: 10px;
      font-weight: 700;
      color: var(--wine);
      background: var(--peach);
      padding: 2px 8px;
      border-radius: var(--radius-pill);
    }
    .room-badge {
      font-size: 10px;
      font-weight: 600;
      color: var(--muted);
    }
    .session-title {
      font-family: var(--font-heading);
      font-size: 13px;
      font-weight: 700;
      color: var(--ink);
      line-height: 1.3;
    }
    .session-speaker-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
      margin-top: 2px;
    }
    .speaker-info-chip {
      display: flex;
      align-items: center;
      gap: 8px;
      min-width: 0;
    }
    .speaker-micro-avatar {
      width: 24px;
      height: 24px;
      border-radius: 50%;
      object-fit: cover;
      flex-shrink: 0;
    }
    .speaker-name-title {
      font-size: 11px;
      font-weight: 600;
      color: var(--ink);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .star-btn {
      background: transparent;
      border: none;
      cursor: pointer;
      color: var(--muted);
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .star-btn.starred { color: var(--gold); }

    /* CampCard Badge Graphic */
    .campcard-badge-container {
      background: linear-gradient(145deg, #1E293B, #0F172A);
      border: 2px solid #D97706;
      border-radius: 20px;
      padding: 20px 16px;
      color: #FFFFFF;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 12px;
      box-shadow: 0 12px 28px rgba(0, 0, 0, 0.4);
      position: relative;
    }
    .campcard-qr {
      background: #FFFFFF;
      padding: 10px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }
    .campcard-name {
      font-family: var(--font-heading);
      font-size: 18px;
      font-weight: 800;
      color: #FFFFFF;
      text-align: center;
      line-height: 1.2;
    }
    .campcard-role {
      font-size: 10px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: #F59E0B;
      background: rgba(245, 158, 11, 0.15);
      padding: 3px 12px;
      border-radius: var(--radius-pill);
      border: 1px solid rgba(245, 158, 11, 0.3);
    }

    /* Desktop Web Viewports */
    .desktop-browser-frame {
      background: #FFFFFF;
      border-radius: 20px;
      border: 1px solid var(--line-dark);
      overflow: hidden;
      box-shadow: 0 25px 60px rgba(0,0,0,0.5);
      color: var(--ink);
      display: flex;
      flex-direction: column;
    }
    .browser-top-bar {
      background: #0F172A;
      padding: 12px 20px;
      display: flex;
      align-items: center;
      gap: 16px;
      border-bottom: 1px solid var(--line-dark);
    }
    .browser-dots {
      display: flex;
      gap: 6px;
    }
    .browser-dot {
      width: 10px;
      height: 10px;
      border-radius: 50%;
    }
    .browser-url-pill {
      background: #1E293B;
      border-radius: var(--radius-pill);
      padding: 6px 20px;
      font-size: 12px;
      color: #94A3B8;
      display: flex;
      align-items: center;
      gap: 8px;
      flex: 1;
      max-width: 500px;
    }
    .browser-viewport {
      padding: 32px 40px;
      background: var(--paper);
    }

    /* Web Landing Hero */
    .web-navbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding-bottom: 24px;
      border-bottom: 1px solid var(--line);
      margin-bottom: 32px;
    }
    .web-nav-links {
      display: flex;
      align-items: center;
      gap: 24px;
      font-size: 13px;
      font-weight: 600;
      color: var(--navy);
    }
    .web-nav-links a { color: inherit; text-decoration: none; }
    .web-nav-links a:hover { color: var(--coral); }
    .web-hero-grid {
      display: grid;
      grid-template-columns: 1.2fr 0.8fr;
      gap: 40px;
      align-items: center;
      margin-bottom: 40px;
    }
    .web-hero-left h1 {
      font-family: var(--font-heading);
      font-size: 44px;
      font-weight: 800;
      line-height: 1.15;
      color: var(--navy);
      margin-bottom: 16px;
    }
    .web-hero-left p {
      font-size: 16px;
      color: var(--muted);
      line-height: 1.6;
      margin-bottom: 24px;
      max-width: 580px;
    }
    .web-cta-row {
      display: flex;
      gap: 14px;
      flex-wrap: wrap;
    }
    .btn-coral-lg {
      background: var(--coral);
      color: #FFFFFF;
      padding: 12px 28px;
      border-radius: var(--radius-pill);
      font-size: 14px;
      font-weight: 700;
      border: none;
      cursor: pointer;
      box-shadow: 0 6px 18px rgba(255, 77, 45, 0.35);
    }
    .btn-outline-lg {
      background: transparent;
      color: var(--navy);
      padding: 12px 28px;
      border-radius: var(--radius-pill);
      font-size: 14px;
      font-weight: 700;
      border: 1.5px solid var(--line);
      cursor: pointer;
    }

    /* Web Stats Bar */
    .web-stats-bar {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 20px;
      background: #FFFFFF;
      border: 1px solid var(--line);
      border-radius: 18px;
      padding: 24px 32px;
      margin-bottom: 40px;
      box-shadow: 0 4px 14px rgba(0, 0, 0, 0.03);
    }
    .stat-item {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }
    .stat-number {
      font-family: var(--font-heading);
      font-size: 32px;
      font-weight: 800;
      color: var(--coral);
      line-height: 1;
    }
    .stat-label {
      font-size: 12px;
      color: var(--muted);
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    /* Admin Panel Styling */
    .admin-layout {
      display: grid;
      grid-template-columns: 240px 1fr;
      min-height: 640px;
      background: #0B1120;
    }
    .admin-sidebar {
      background: #0F172A;
      border-right: 1px solid #1E293B;
      padding: 24px 16px;
      display: flex;
      flex-direction: column;
      gap: 24px;
    }
    .admin-nav-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 10px 14px;
      border-radius: 10px;
      font-size: 13px;
      font-weight: 600;
      color: #94A3B8;
      cursor: pointer;
    }
    .admin-nav-item.active {
      background: var(--coral);
      color: #FFFFFF;
    }
    .admin-main {
      padding: 28px 32px;
      background: #0B1120;
      display: flex;
      flex-direction: column;
      gap: 24px;
      color: #F8FAFC;
    }
    .admin-kpi-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 16px;
    }
    .admin-kpi-card {
      background: #1E293B;
      border: 1px solid #334155;
      border-radius: 14px;
      padding: 18px;
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    .kpi-title { font-size: 12px; color: #94A3B8; font-weight: 600; }
    .kpi-value { font-family: var(--font-heading); font-size: 28px; font-weight: 800; color: #FFFFFF; }
    .kpi-trend { font-size: 11px; color: #10B981; font-weight: 700; }

    /* Sponsor Grid */
    .sponsor-logos-matrix {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
      gap: 16px;
      align-items: center;
    }
    .sponsor-card-box {
      background: #FFFFFF;
      border: 1px solid var(--line);
      border-radius: 14px;
      padding: 16px;
      height: 90px;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
    }
    .sponsor-card-box img {
      max-width: 100%;
      max-height: 54px;
      object-fit: contain;
    }

    /* Speaker Grid */
    .speaker-catalog-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
      gap: 20px;
    }
    .speaker-card-detailed {
      background: #FFFFFF;
      border: 1px solid var(--line);
      border-radius: 16px;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
    }
    .speaker-card-img-wrap {
      height: 180px;
      width: 100%;
      background: #F1F5F9;
      position: relative;
    }
    .speaker-card-img-wrap img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .speaker-card-body {
      padding: 16px;
      display: flex;
      flex-direction: column;
      gap: 6px;
      flex: 1;
    }
    .speaker-name {
      font-family: var(--font-heading);
      font-size: 16px;
      font-weight: 700;
      color: var(--navy);
    }
    .speaker-role {
      font-size: 11px;
      font-weight: 600;
      color: var(--coral);
    }
    .speaker-talk-tag {
      font-size: 11px;
      color: var(--muted);
      margin-top: 4px;
      line-height: 1.4;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    /* Verification Checklist Bar */
    .audit-bar {
      max-width: 1720px;
      margin: 40px auto 0 auto;
      background: #111827;
      border: 1px solid #1F2937;
      border-radius: 16px;
      padding: 20px 24px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 16px;
    }
    .audit-checkmarks {
      display: flex;
      align-items: center;
      gap: 20px;
      font-size: 12px;
      color: #94A3B8;
      flex-wrap: wrap;
    }
    .audit-checkmarks span {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      color: #E2E8F0;
    }
    .audit-checkmarks span svg { color: var(--success); }

    /* Refined APK Gallery Cards + Lightbox + Complete Web/PWA/Admin Layouts */
    .apk-filter-bar { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 24px; }
    .apk-filter-pill { background: #1E293B; border: 1px solid #334155; color: #CBD5E1; padding: 7px 16px; border-radius: 9999px; font-size: 12px; font-weight: 600; cursor: pointer; }
    .apk-filter-pill.active { background: var(--coral); border-color: var(--coral); color: #FFFFFF; }
    .apk-group-block { margin-bottom: 44px; }
    .apk-group-heading { font-family: var(--font-heading); font-size: 18px; font-weight: 800; color: #F59E0B; margin-bottom: 18px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #1E293B; padding-bottom: 8px; }
    .phone-png-bezel { width: 320px; border-radius: 38px; border: 8px solid #0F172A; background: #0F172A; box-shadow: 0 22px 48px rgba(0,0,0,0.55), 0 0 0 1px rgba(255,255,255,0.08); overflow: hidden; cursor: zoom-in; transition: transform 0.2s ease; }
    .phone-png-bezel:hover { transform: translateY(-4px); }
    .phone-png-bezel img { width: 100%; height: auto; display: block; }
    .screen-meta-sub { font-size: 11px; color: var(--muted-light); text-align: center; max-width: 310px; line-height: 1.4; }
    .sponsor-card-box { background: #FDFBF7; }
    .studio-grid { display: grid; grid-template-columns: 320px 1fr; gap: 32px; align-items: start; }
    .studio-card { background: #FFFFFF; border: 1px solid var(--line); border-radius: 18px; padding: 22px; display: flex; flex-direction: column; gap: 14px; }
    .field-mock { background: #F8FAFC; border: 1px solid var(--line); border-radius: 10px; padding: 10px 14px; font-size: 12px; color: var(--ink); font-weight: 500; }
    .lightbox-modal { display: none; position: fixed; inset: 0; background: rgba(11,17,32,0.92); z-index: 9999; align-items: center; justify-content: center; padding: 24px; }
    .lightbox-modal.open { display: flex; }
    .lightbox-modal img { max-height: 88vh; max-width: 92vw; border-radius: 32px; border: 6px solid #1E293B; box-shadow: 0 30px 70px rgba(0,0,0,0.7); }
    .lightbox-close { position: fixed; top: 20px; right: 26px; background: #1E293B; border: 1px solid #334155; color: #FFFFFF; width: 40px; height: 40px; border-radius: 50%; font-size: 18px; cursor: pointer; }
    .admin-main { min-width: 0; }
    .admin-main > div:first-child { flex-wrap: wrap; gap: 12px; }
    @media (max-width: 960px) {
      .admin-layout { grid-template-columns: 1fr; }
      .admin-kpi-grid { grid-template-columns: repeat(2, 1fr) !important; }
      .studio-grid { grid-template-columns: 1fr; }
      .web-hero-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

  <!-- Master Specimen Header -->
  <header class="master-header">
    <div class="header-left">
      <div class="brand-pills">
        <span class="badge-pill badge-pill-coral">Official Event Companion</span>
        <span class="badge-pill badge-pill-gold">Verified Real Data · WordCamp Delhi 2026</span>
        <span class="badge-pill badge-pill-green">100% Offline Venue-Ready</span>
      </div>
      <div style="display:flex; align-items:center; gap:14px; flex-wrap:wrap; margin-bottom:6px;">
        <div style="background:#FFFFFF; border-radius:12px; padding:5px 12px; display:inline-flex; align-items:center;">
          <img src="assets/campbuddy_wordmark.png" style="height:26px; width:auto;" alt="CampBuddy" onerror="this.src='docs/designs/assets/campbuddy_wordmark.png'">
        </div>
        <div style="background:#FFFFFF; border-radius:50%; width:38px; height:38px; padding:3px; display:inline-flex; align-items:center; justify-content:center;">
          <img src="assets/delhi_favicon.png" style="width:100%; height:100%; object-fit:contain;" alt="WordCamp Delhi" onerror="this.src='docs/designs/assets/delhi_favicon.png'">
        </div>
      </div>
      <h1 class="master-title">WordCamp Delhi 2026 · CampBuddy UI/UX Design System</h1>
      <p class="master-subtitle">
        Refined calm tactile design system built with 100% real WordCamp Delhi 2026 content: speaker portraits, verified sponsor logos, multi-track sessions, scannable QR badges, hairline borders, crisp Lucide vector icons, and zero technical jargon across all 4 platforms.
      </p>
    </div>
    <div class="header-right">
      <div class="live-pulse-box">
        <span class="pulse-dot"></span>
        <span>DAIC New Delhi · Dec 12–13, 2026</span>
      </div>
      <div style="font-size: 11px; color: #94A3B8;">
        22 Sessions · 11 Speakers · 17 Verified Sponsors
      </div>
    </div>
  </header>

  <!-- Platform Switcher Tabs -->
  <nav class="platform-tabs">
    <button class="platform-tab active" onclick="switchPlatform('apk')">
      ${getIcon('globe', 18)}
      <span>1. Mobile App APK (Compose)</span>
      <span class="count-badge">50 Screens</span>
    </button>
    <button class="platform-tab" onclick="switchPlatform('web')">
      ${getIcon('columns', 18)}
      <span>2. Website Frontend (Web UI)</span>
      <span class="count-badge">7 Screens</span>
    </button>
    <button class="platform-tab" onclick="switchPlatform('pwa')">
      ${getIcon('zap', 18)}
      <span>3. Progressive Web App (PWA)</span>
      <span class="count-badge">5 Screens</span>
    </button>
    <button class="platform-tab" onclick="switchPlatform('admin')">
      ${getIcon('shield', 18)}
      <span>4. Admin Operations Panel</span>
      <span class="count-badge">4 Screens</span>
    </button>
  </nav>

  <!-- ========================================== -->
  <!-- TAB 1: MOBILE APP APK (ALL 32 REFINED)     -->
  <!-- ========================================== -->
  <main id="section-apk" class="section-container active">
    <div class="section-intro">
      <div>
        <h2>${getIcon('globe', 22)} Native Mobile App APK (Compose Multiplatform)</h2>
        <p>All 50 refined, single-focus mobile screens spanning Attendee, First-Timer Onboarding, Discovery &amp; Modals, Explore, Quests, Contributor Day, Staff Editors, and SuperAdmin Console.</p>
      </div>
      <span class="badge-pill badge-pill-coral">50 Refined FigCraft Screens · 360×760dp</span>
    </div>

    <div class="apk-filter-bar">
      <button type="button" class="apk-filter-pill active" onclick="filterApk('all', this)">All Screens (50)</button>
      <button type="button" class="apk-filter-pill" onclick="filterApk('attendee', this)">Attendee Flow (28)</button>
      <button type="button" class="apk-filter-pill" onclick="filterApk('staff', this)">Organizer &amp; Admin Mode (22)</button>
    </div>

    ${(() => {
      const apkGroups = [
        {
          role: 'attendee',
          title: 'Group 1 · Launch, Picker & Onboarding (M01–M04)',
          items: [
            { id: 'M01', file: 'M01 · Splash & App Update.png', note: 'CampBuddy Wordmark Pill · Delhi Roundel Seal · Offline Ready · App Update' },
            { id: 'M02', file: 'M02 · WordCamp Picker & Directory.png', note: 'Native Wordmark · Country Filter · Featured Delhi Card · Sync Row' },
            { id: 'M03', file: 'M03 · Personalize Interests.png', note: '5-Step Progress · Soft Interest Selection · Session Reminders' },
            { id: 'M04', file: 'M04 · Live Notice & Alert Prompts.png', note: 'Organizer Notice Banner · +15m Delay Chip · Alert Prompt · Guide Card' },
          ]
        },
        {
          role: 'attendee',
          title: 'Group 2 · Core Attendee Experience (M05–M11)',
          items: [
            { id: 'M05', file: 'M05 · Tactile Live Home Dashboard.png', note: 'Flagship Home · Clock Hero · Circular My People · Quick Grid · SOS Bar' },
            { id: 'M06', file: 'M06 · First-Timer Guide & Venue.png', note: 'India Gate Hero · 2-Day Flow · Venue & Metro · FAQ Accordion' },
            { id: 'M07', file: 'M07 · Full Schedule & Filters.png', note: 'Schedule Root Header · Search Pill · Day & Hall Chips · Speaker Avatars' },
            { id: 'M08', file: 'M08 · Session Detail & Reminder.png', note: 'Speaker Card · Talk Overview · Saved Action · 10m Alert Toggle' },
            { id: 'M09', file: 'M09 · My Schedule & People Plan.png', note: 'Plan Progress · Conflict Banner · Attended Toggle · People to Meet' },
            { id: 'M10', file: 'M10 · Memories & Post-Event Choice.png', note: 'Event Recap · Photo Album · WordPress.tv · Data Retention' },
            { id: 'M11', file: 'M11 · Help Desk, Legal & Safety Report.png', note: 'Organizer Dialer · Support Rooms · Confidential Report Form' },
          ]
        },
        {
          role: 'attendee',
          title: 'Group 3 · CampCard, QR Scanner & Discovery (M12–M16)',
          items: [
            { id: 'M12', file: 'M12 · CampCard Carousel & Real QR.png', note: 'Style Switcher · Sharp Delhi Seal · Scannable QR · Share/Download' },
            { id: 'M13', file: 'M13 · Edit CampCard & Claim Profile.png', note: 'Roster Profile Link · Card Fields · Interest Chips · Privacy Toggles' },
            { id: 'M14', file: 'M14 · Fast QR Scanner & Contact Sheet.png', note: 'Camera Viewfinder · Flash Toggle · Detected Contact Bottom Sheet' },
            { id: 'M15', file: 'M15 · Explore_ Discovery & Roster.png', note: '5 Explore Tabs · Join Banner · Discovery Match · Attendee Roster' },
            { id: 'M16', file: 'M16 · Mutual Match Chat & Meet.png', note: 'Focused Light Chat · Suggested Meet Card · Quick Message Composer' },
          ]
        },
        {
          role: 'attendee',
          title: 'Group 4 · Explore Tabs, Contributor Day & Quests (M17–M22)',
          items: [
            { id: 'M17', file: 'M17 · Explore_ Sponsors Tiered Grid.png', note: 'Seamless Sponsor Logos · Bronze, Startup & After Party Tiers' },
            { id: 'M18', file: 'M18 · Explore_ Deals & Lead Sheet.png', note: 'Tear-Line Coupon Card · Clean Elevated Lead Form Bottom Sheet' },
            { id: 'M19', file: 'M19 · Explore_ Free Steals & Sheet.png', note: 'Free Tools List · Made Here Badge · Elevated Tool Sheet' },
            { id: 'M20', file: 'M20 · Contributor Day & Table Leads.png', note: 'Light Intro Card · Attendance Toggle · Conversation Starter · Table Leads' },
            { id: 'M21', file: 'M21 · Quests, Checklist & Badges.png', note: 'Navy Level Hero · Checklist · Unlocked Vector-Icon Badges' },
            { id: 'M22', file: 'M22 · Event Info & Data Controls.png', note: 'Event Header · Venue & Wi-Fi Copy · Notifications · Data Controls' },
          ]
        },
        {
          role: 'staff',
          title: 'Group 5 · In-App Organizer & SuperAdmin Console (M23–M32)',
          items: [
            { id: 'M23', file: 'M23 · Staff Sign In.png', note: 'Dedicated Organizer & Admin Sign-In · Role Auto-Detection' },
            { id: 'M24', file: 'M24 · Staff Event Overview.png', note: 'Event Tabs · Live KPIs · Active WordCamp Card · Organizer Tools' },
            { id: 'M25', file: 'M25 · Staff Running Late Delays.png', note: 'Hall Selector · +15m Offset · Shifted Talk Preview · Broadcast' },
            { id: 'M26', file: 'M26 · Staff Announce & Sent Log.png', note: 'Tone Pills · Live Announcement Composer · Recent Broadcasts' },
            { id: 'M27', file: 'M27 · Staff Reports & Roster Roles.png', note: 'Safety Report Triage · Hide/Dismiss · Roster Role Assignment' },
            { id: 'M28', file: 'M28 · Staff Tables, Quests & Memories.png', note: 'Contributor Tables · Active Quests · Publish Album & WordPress.tv' },
            { id: 'M29', file: 'M29 · Staff Social Kit & Reels.png', note: 'Speaker Card Studio · Sharp Delhi Seal · Circular Portrait · Share' },
            { id: 'M30', file: 'M30 · SuperAdmin Global Dashboard.png', note: 'System Health · 2×3 Key Metrics · Featured Delhi · Maintenance' },
            { id: 'M31', file: 'M31 · SuperAdmin Event Managers.png', note: 'Add Manager CTA · Status Filters · Clean Manager Roster' },
            { id: 'M32', file: 'M32 · SuperAdmin Analytics & Leads.png', note: 'Visitor Analytics · System Health · Feedback · Deal Lead Exports' },
          ]
        },
        {
          role: 'attendee',
          title: 'Group 6 · Attendee Onboarding, Legal & Interactive Modals (M33–M38)',
          items: [
            { id: 'M33', file: 'M33 · Onboarding Intro & First-Timer.png', note: 'Welcome Personalize Prompt · Step 1 Binary Yes/No First-Timer Card' },
            { id: 'M34', file: 'M34 · Onboarding Contributor & Finish.png', note: 'Steps 4–5 Who to Meet & WP.org · Private Summary Completion Card' },
            { id: 'M35', file: 'M35 · Legal Notice & Terms Reader.png', note: 'Privacy Notice & Terms Tabs · On-Device Storage · DPDP Act 2023 Rights' },
            { id: 'M36', file: 'M36 · Plan Meet & Schedule Pickers.png', note: 'Private Meeting Note · Date & Time Pickers · .ics & Google Calendar' },
            { id: 'M37', file: 'M37 · Join Discovery & Wave Sheets.png', note: 'Name Picker & Interests · Consent Toggle · Private Wave Note Sheet' },
            { id: 'M38', file: 'M38 · Contributor Quiz & Data Erase.png', note: '30-Sec Contributor Quiz · Matched Table Result · Clear Phone Data Modal' },
          ]
        },
        {
          role: 'staff',
          title: 'Group 7 · Staff Directory, Venue Editors & CRUD Sheets (M39–M44)',
          items: [
            { id: 'M39', file: 'M39 · Staff Events Directory.png', note: 'Search All WordCamps · Status Filters · Manage & Preview Shortcuts' },
            { id: 'M40', file: 'M40 · Staff Event Details & Data Sync.png', note: 'Slug & Dates Editor · Public Directory Switch · WordCamp Feed Refresh' },
            { id: 'M41', file: 'M41 · Staff Venue Info & Danger Zone.png', note: 'Venue Address, Guest Wi-Fi & Desk Editor · Danger Zone Slug Confirm' },
            { id: 'M42', file: 'M42 · Staff CRUD Sheets (Roles & Deals).png', note: 'Multi-Select Roster Roles · Sponsor Deal Coupon & Lead Capture Editor' },
            { id: 'M43', file: 'M43 · Staff Notifications & Event Log.png', note: 'Event Push Notification Triggers · Real-Time Organizer Activity Timeline' },
            { id: 'M44', file: 'M44 · Admin New Event & Report Sheet.png', note: 'Create WordCamp & Fetch Feed · Attendee Safety Report Moderation Sheet' },
          ]
        },
        {
          role: 'staff',
          title: 'Group 8 · SuperAdmin More Hub & Platform Sub-Pages (M45–M50)',
          items: [
            { id: 'M45', file: 'M45 · SuperAdmin More & Maintenance.png', note: 'Analytics/Errors/Feedback/Logs Hub · Global Refresh & Cache Controls' },
            { id: 'M46', file: 'M46 · SuperAdmin System Errors.png', note: '7d/30d Filters · Healthy Shield Card · Upstream Timeout Retry · Checks' },
            { id: 'M47', file: 'M47 · SuperAdmin Attendee Feedback.png', note: '4.9/5 Average Rating Histogram · Thank-You Card Comments Stream' },
            { id: 'M48', file: 'M48 · SuperAdmin Global Activity Log.png', note: 'Cross-Event Audit Feed · Filter by Manager · Timestamps & Actions' },
            { id: 'M49', file: 'M49 · SuperAdmin Analytics.png', note: 'Visitors & Engagement KPIs · Top WordCamps · Device Platform Split' },
            { id: 'M50', file: 'M50 · SuperAdmin Manager Sheet.png', note: 'Manager Email & Phone · Can Sign In Switch · Assigned WordCamps' },
          ]
        }
      ];
      return apkGroups.map(g => `
        <section class="apk-group-block" data-apk-role="${g.role}">
          <h3 class="apk-group-heading"><span>${g.title}</span><span style="font-size:11px; color:#94A3B8; font-weight:600;">${g.items.length} Screens</span></h3>
          <div class="screens-grid-phones">
            ${g.items.map(it => {
              const src = 'screens/apk/' + encodeURIComponent(it.file);
              const label = it.file.replace('.png', '').replace('Explore_ ', 'Explore: ');
              return `
                <div class="phone-card-wrapper">
                  <div class="screen-meta-title">
                    <span class="screen-meta-badge">${it.id}</span>
                    <span>${label.replace(it.id + ' · ', '')}</span>
                  </div>
                  <div class="phone-png-bezel" onclick="openLightbox('${src}')">
                    <img src="${src}" alt="${label}" loading="lazy">
                  </div>
                  <div class="screen-meta-sub">${it.note}</div>
                </div>
              `;
            }).join('')}
          </div>
        </section>
      `).join('');
    })()}
  </main>

  <!-- ========================================== -->
  <!-- TAB 2: WEBSITE FRONTEND (7 SCREENS)        -->
  <!-- ========================================== -->
  <main id="section-web" class="section-container">
    <div class="section-intro">
      <div>
        <h2>${getIcon('columns', 22)} Website Frontend (Desktop Web UI)</h2>
        <p>Responsive web discovery, WordCamp Delhi dashboard, multi-track schedule matrix, speakers, seamless sponsor showcase, CampCard web studio, and venue guide.</p>
      </div>
      <span class="badge-pill badge-pill-gold">Desktop 1280px+ Viewports · 7 Complete Web Screens</span>
    </div>

    <div class="screens-grid-desktop">

      <!-- Web 01: Landing Discovery Stage -->
      <div class="desktop-browser-frame">
        <div class="browser-top-bar">
          <div class="browser-dots">
            <div class="browser-dot" style="background:#EF4444;"></div>
            <div class="browser-dot" style="background:#F59E0B;"></div>
            <div class="browser-dot" style="background:#10B981;"></div>
          </div>
          <div class="browser-url-pill">
            ${getIcon('globe', 14, '#94A3B8')}
            <span>https://campbuddy.club/</span>
          </div>
        </div>

        <div class="browser-viewport">
          <nav class="web-navbar">
            <div style="display:flex; align-items:center; gap:12px;">
              <img src="assets/campbuddy_wordmark.png" style="height:34px; width:auto;" alt="CampBuddy" onerror="this.src='docs/designs/assets/campbuddy_wordmark.png'">
              <span style="height:22px; width:1px; background:var(--line);"></span>
              <img src="assets/delhi_favicon.png" style="height:30px; width:30px; border-radius:50%; object-fit:contain;" alt="WordCamp Delhi" onerror="this.src='docs/designs/assets/delhi_favicon.png'">
            </div>
            <div class="web-nav-links">
              <a href="#wordcamps">WordCamps</a>
              <a href="#about">About</a>
              <a href="#guide">First-Timer Guide</a>
              <button class="btn-outline-lg" style="padding:7px 16px; font-size:12px;">Open on phone</button>
              <button class="btn-coral-lg" style="padding:8px 20px; font-size:12px;">Install App</button>
            </div>
          </nav>

          <div class="web-hero-grid">
            <div class="web-hero-left">
              <div class="badge-pill badge-pill-gold" style="margin-bottom:12px;">December 12–13, 2026 · DAIC New Delhi</div>
              <h1>Make the most of your <span style="color:var(--coral);">WordCamp</span></h1>
              <p>
                Navigate multi-track schedules at Dr. Ambedkar International Centre, curate your personal My Day timetable, and swap digital CampCards via camera — zero paper clutter and no sign-up needed.
              </p>
              <div class="web-cta-row">
                <button class="btn-coral-lg">Choose Your WordCamp</button>
                <button class="btn-outline-lg">First WordCamp Guide →</button>
              </div>
            </div>
            <div style="border-radius:20px; overflow:hidden; box-shadow:0 12px 30px rgba(0,0,0,0.1); border:1px solid var(--line);">
              <img src="assets/delhi_skyline.webp" style="width:100%; height:260px; object-fit:cover;" alt="Delhi Skyline" onerror="this.src='docs/designs/assets/delhi_skyline.webp'">
            </div>
          </div>

          <div class="web-stats-bar">
            <div class="stat-item"><span class="stat-number">2 Days</span><span class="stat-label">Contributor &amp; Conf</span></div>
            <div class="stat-item"><span class="stat-number">22 Talks</span><span class="stat-label">AI, SEO &amp; Enterprise</span></div>
            <div class="stat-item"><span class="stat-number">11 Keynotes</span><span class="stat-label">Verified Speakers</span></div>
            <div class="stat-item"><span class="stat-number">17 Brands</span><span class="stat-label">Verified Sponsors</span></div>
          </div>
        </div>
      </div>

      <!-- Web 02: Event Companion Dashboard -->
      <div class="desktop-browser-frame">
        <div class="browser-top-bar">
          <div class="browser-dots"><div class="browser-dot" style="background:#EF4444;"></div><div class="browser-dot" style="background:#F59E0B;"></div><div class="browser-dot" style="background:#10B981;"></div></div>
          <div class="browser-url-pill">${getIcon('globe', 14, '#94A3B8')}<span>https://campbuddy.club/event/wordcamp-delhi-2026</span></div>
        </div>
        <div class="browser-viewport">
          <div class="studio-grid">
            <div class="tactile-hero-card" style="padding:24px;">
              <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                <span class="hero-pretitle">LIVE EVENT DASHBOARD</span>
                <div style="background:#FFFFFF; border-radius:50%; width:42px; height:42px; padding:3px;">
                  <img src="assets/delhi_favicon.png" style="width:100%; height:100%; object-fit:contain;" alt="Delhi">
                </div>
              </div>
              <div class="hero-clock">10:30 AM</div>
              <div class="hero-meta-line"><span>Day 1 • Dec 12, 2026</span><span>•</span><span>DAIC New Delhi</span></div>
              <div class="hero-next-pill" style="margin-top:14px;">
                <div class="next-pill-text">
                  <div class="next-pill-title">Next: Local SEO in the AI Era</div>
                  <div class="next-pill-sub">Pankaj Kumar · BHIM Hall · in 15m</div>
                </div>
                <button class="btn-gold">View</button>
              </div>
            </div>

            <div style="display:flex; flex-direction:column; gap:16px;">
              <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                <div class="studio-card" style="padding:16px;">
                  <div style="font-size:11px; font-weight:700; color:var(--coral); text-transform:uppercase;">Happening Now</div>
                  <div style="font-family:var(--font-heading); font-size:16px; font-weight:800;">From Feature to Ecosystem</div>
                  <div style="font-size:12px; color:var(--muted);">Radhika Bhama &amp; Raitis Sevelis · BHIM Auditorium</div>
                </div>
                <div class="studio-card" style="padding:16px; background:var(--paper-soft); border-color:#FED7AA;">
                  <div style="font-size:11px; font-weight:700; color:var(--wine); text-transform:uppercase;">First Time at WordCamp?</div>
                  <div style="font-family:var(--font-heading); font-size:16px; font-weight:800;">5-Minute Venue &amp; Jargon Guide</div>
                  <div style="font-size:12px; color:var(--muted);">Hall locations, Metro exits &amp; Contributor Day basics</div>
                </div>
              </div>
              <div class="tactile-2col-grid" style="grid-template-columns:repeat(3, 1fr);">
                <div class="tactile-card"><div class="tactile-card-title">My Day</div><div class="tactile-card-sub">4 saved sessions</div></div>
                <div class="tactile-card"><div class="tactile-card-title">People Discovery</div><div class="tactile-card-sub">11 speakers · Waves</div></div>
                <div class="tactile-card"><div class="tactile-card-title">Sponsors &amp; Deals</div><div class="tactile-card-sub">17 brands · Codes</div></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Web 03: Multi-Track Schedule Matrix -->
      <div class="desktop-browser-frame">
        <div class="browser-top-bar">
          <div class="browser-dots">
            <div class="browser-dot" style="background:#EF4444;"></div>
            <div class="browser-dot" style="background:#F59E0B;"></div>
            <div class="browser-dot" style="background:#10B981;"></div>
          </div>
          <div class="browser-url-pill">https://campbuddy.club/event/wordcamp-delhi-2026/my-day</div>
        </div>

        <div class="browser-viewport">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
            <div>
              <h2 style="font-family:var(--font-heading); font-size:26px; font-weight:800; color:var(--navy);">Conference Schedule Browser</h2>
              <p style="font-size:13px; color:var(--muted);">Filter by track, room, and bookmark sessions to your offline itinerary.</p>
            </div>
            <div style="display:flex; gap:10px;">
              <button class="btn-coral-lg" style="font-size:12px; padding:8px 16px;">Day 1: Contributor Day</button>
              <button class="btn-outline-lg" style="font-size:12px; padding:8px 16px; background:#FFFFFF;">Day 2: Main Conference</button>
            </div>
          </div>

          <div style="display:grid; grid-template-columns: 120px 1fr 1fr; gap:16px;">
            <div style="font-weight:700; font-size:12px; color:var(--muted); text-transform:uppercase;">Time</div>
            <div style="font-weight:700; font-size:12px; color:var(--navy); text-transform:uppercase; background:#F1F5F9; padding:8px 12px; border-radius:8px;">Track 1: BHIM Auditorium (Main Stage)</div>
            <div style="font-weight:700; font-size:12px; color:var(--navy); text-transform:uppercase; background:#F1F5F9; padding:8px 12px; border-radius:8px;">Track 2: Contributor &amp; Dev Lounge</div>

            <div style="font-weight:700; font-size:13px; color:var(--wine); padding-top:10px;">10:00 AM</div>
            <div class="session-item-card">
              <span class="time-badge">30m Keynote</span>
              <div class="session-title">From Feature to Ecosystem: How to Build WordPress Products That Last</div>
              <div style="font-size:11px; color:var(--muted);">Radhika Bhama (VP Product) &amp; Raitis Sevelis (WPBakery)</div>
            </div>
            <div class="session-item-card" style="background:#F8FAFC;">
              <span class="time-badge">Table Kickoff</span>
              <div class="session-title">Core &amp; Gutenberg Architecture Review</div>
              <div style="font-size:11px; color:var(--muted);">Amit Singh (WPoets)</div>
            </div>

            <div style="font-weight:700; font-size:13px; color:var(--wine); padding-top:10px;">11:20 AM</div>
            <div class="session-item-card" style="border-left: 3px solid var(--coral);">
              <span class="time-badge" style="background:#FEE2E2; color:#DC2626;">20m Tactical Talk</span>
              <div class="session-title">10 Things to Do to Boost Local SEO in the AI Era</div>
              <div style="font-size:11px; color:var(--muted);">Pankaj Kumar (Pankaj Kumar SEO)</div>
            </div>
            <div class="session-item-card" style="background:#F8FAFC;">
              <span class="time-badge">Workshop</span>
              <div class="session-title">Documentation &amp; HelpHub Sprint</div>
              <div style="font-size:11px; color:var(--muted);">Shreya Agarwal</div>
            </div>

            <div style="font-weight:700; font-size:13px; color:var(--wine); padding-top:10px;">04:00 PM</div>
            <div class="session-item-card" style="grid-column: 2 / span 2; border: 1px solid #F59E0B;">
              <div style="display:flex; justify-content:space-between;">
                <span class="time-badge" style="background:#FEF3C7; color:#B45309;">60m Featured Panel</span>
                <span style="font-size:11px; font-weight:700; color:var(--coral);">Full Hall Plenary</span>
              </div>
              <div class="session-title">PANEL: The Value Shift in the AI Era — When Everyone Can Build a Website, What Makes Yours Matter?</div>
              <div style="font-size:11px; color:var(--muted); margin-top:4px;">Panelists: Radhika Bhama, Amit Singh, Om Asnani, Abhay Kulkarni</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Web 04: Verified Speaker Showcase -->
      <div class="desktop-browser-frame">
        <div class="browser-top-bar">
          <div class="browser-dots">
            <div class="browser-dot" style="background:#EF4444;"></div>
            <div class="browser-dot" style="background:#F59E0B;"></div>
            <div class="browser-dot" style="background:#10B981;"></div>
          </div>
          <div class="browser-url-pill">https://campbuddy.club/event/wordcamp-delhi-2026/explore#people</div>
        </div>

        <div class="browser-viewport">
          <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:24px; flex-wrap:wrap; gap:12px;">
            <div>
              <h2 style="font-family:var(--font-heading); font-size:26px; font-weight:800; color:var(--navy);">WordCamp Delhi 2026 Keynote Speakers &amp; People</h2>
              <p style="font-size:13px; color:var(--muted);">Verified speakers, organizers, table leads, and mutual interest matches.</p>
            </div>
            <div style="display:flex; gap:8px;">
              <span class="filter-chip active">All (420)</span>
              <span class="filter-chip">Speakers (11)</span>
              <span class="filter-chip">Organizers (13)</span>
              <span class="filter-chip">Table Leads</span>
            </div>
          </div>

          <div class="speaker-catalog-grid">
            <div class="speaker-card-detailed">
              <div class="speaker-card-img-wrap"><img src="assets/pankaj_kumar.webp" alt="Pankaj Kumar" onerror="this.src='docs/designs/assets/pankaj_kumar.webp'"></div>
              <div class="speaker-card-body"><div class="speaker-name">Pankaj Kumar</div><div class="speaker-role">Founder @ Pankaj Kumar SEO</div><div class="speaker-talk-tag">Talk: 10 Things to Do to Boost Local SEO in the AI Era</div></div>
            </div>
            <div class="speaker-card-detailed">
              <div class="speaker-card-img-wrap"><img src="assets/radhika_bhama.jpg" alt="Radhika Bhama" onerror="this.src='docs/designs/assets/radhika_bhama.jpg'"></div>
              <div class="speaker-card-body"><div class="speaker-name">Radhika Bhama</div><div class="speaker-role">VP Product &amp; Community Leader</div><div class="speaker-talk-tag">Talk: From Feature to Ecosystem &amp; AI Value Panel</div></div>
            </div>
            <div class="speaker-card-detailed">
              <div class="speaker-card-img-wrap"><img src="assets/amit_singh.webp" alt="Amit Singh" onerror="this.src='docs/designs/assets/amit_singh.webp'"></div>
              <div class="speaker-card-body"><div class="speaker-name">Amit Kumar Singh</div><div class="speaker-role">Managing Partner @ WPoets</div><div class="speaker-talk-tag">Talk: Enterprise Architectures &amp; AI Shift Panel</div></div>
            </div>
            <div class="speaker-card-detailed">
              <div class="speaker-card-img-wrap"><img src="assets/shammi_proothi.webp" alt="Shammi Proothi" onerror="this.src='docs/designs/assets/shammi_proothi.webp'"></div>
              <div class="speaker-card-body"><div class="speaker-name">Shammi Proothi</div><div class="speaker-role">VP @ ANON Global Foundation</div><div class="speaker-talk-tag">Talk: Cited but Not Recommended in AI Search</div></div>
            </div>
            <div class="speaker-card-detailed">
              <div class="speaker-card-img-wrap"><img src="assets/om_asnani.webp" alt="Om Asnani" onerror="this.src='docs/designs/assets/om_asnani.webp'"></div>
              <div class="speaker-card-body"><div class="speaker-name">Omprakash Asnani</div><div class="speaker-role">AI Workflows Lead @ Outskill</div><div class="speaker-talk-tag">Talk: Content at Scale Without Drowning Editors</div></div>
            </div>
            <div class="speaker-card-detailed">
              <div class="speaker-card-img-wrap"><img src="assets/abhay_kulkarni.webp" alt="Abhay Kulkarni" onerror="this.src='docs/designs/assets/abhay_kulkarni.webp'"></div>
              <div class="speaker-card-body"><div class="speaker-name">Abhay Kulkarni</div><div class="speaker-role">India's First n8n Ambassador</div><div class="speaker-talk-tag">Talk: AI Automation for WordPress Agencies</div></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Web 05: Verified Sponsors & Deals Wall -->
      <div class="desktop-browser-frame">
        <div class="browser-top-bar">
          <div class="browser-dots">
            <div class="browser-dot" style="background:#EF4444;"></div>
            <div class="browser-dot" style="background:#F59E0B;"></div>
            <div class="browser-dot" style="background:#10B981;"></div>
          </div>
          <div class="browser-url-pill">https://campbuddy.club/event/wordcamp-delhi-2026/explore#sponsors</div>
        </div>

        <div class="browser-viewport">
          <div style="margin-bottom:24px;">
            <h2 style="font-family:var(--font-heading); font-size:26px; font-weight:800; color:var(--navy);">Verified WordCamp Delhi Sponsors &amp; Perks</h2>
            <p style="font-size:13px; color:var(--muted);">Seamless sponsor logo matrix across Platinum, Bronze, Startup, and After Party tiers.</p>
          </div>

          <div style="display:flex; flex-direction:column; gap:24px;">
            <div>
              <div style="font-size:12px; font-weight:700; color:var(--coral); text-transform:uppercase; letter-spacing:1px; margin-bottom:12px;">Bronze &amp; Platform Sponsors</div>
              <div class="sponsor-logos-matrix">
                <div class="sponsor-card-box"><img src="assets/sponsor_elementor.webp" alt="Elementor" onerror="this.src='docs/designs/assets/sponsor_elementor.webp'"></div>
                <div class="sponsor-card-box"><img src="assets/sponsor_nexgi.webp" alt="NexGi" onerror="this.src='docs/designs/assets/sponsor_nexgi.webp'"></div>
                <div class="sponsor-card-box"><img src="assets/sponsor_cookieadmin.webp" alt="CookieAdmin" onerror="this.src='docs/designs/assets/sponsor_cookieadmin.webp'"></div>
                <div class="sponsor-card-box"><img src="assets/sponsor_pankaj_seo.webp" alt="Pankaj Kumar SEO" onerror="this.src='docs/designs/assets/sponsor_pankaj_seo.webp'"></div>
              </div>
            </div>

            <div>
              <div style="font-size:12px; font-weight:700; color:var(--wine); text-transform:uppercase; letter-spacing:1px; margin-bottom:12px;">Startup, Community &amp; After Party Sponsors</div>
              <div class="sponsor-logos-matrix">
                <div class="sponsor-card-box"><img src="assets/sponsor_housewise.webp" alt="Housewise" onerror="this.src='docs/designs/assets/sponsor_housewise.webp'"></div>
                <div class="sponsor-card-box"><img src="assets/sponsor_zuptek.webp" alt="Zuptek" onerror="this.src='docs/designs/assets/sponsor_zuptek.webp'"></div>
                <div class="sponsor-card-box"><img src="assets/sponsor_digiliance.webp" alt="Digiliance" onerror="this.src='docs/designs/assets/sponsor_digiliance.webp'"></div>
                <div class="sponsor-card-box"><img src="assets/sponsor_montaki.webp" alt="Montaki" onerror="this.src='docs/designs/assets/sponsor_montaki.webp'"></div>
                <div class="sponsor-card-box"><img src="assets/sponsor_wpfusion.webp" alt="WPFusion" onerror="this.src='docs/designs/assets/sponsor_wpfusion.webp'"></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Web 06: CampCard Badge Studio -->
      <div class="desktop-browser-frame">
        <div class="browser-top-bar">
          <div class="browser-dots"><div class="browser-dot" style="background:#EF4444;"></div><div class="browser-dot" style="background:#F59E0B;"></div><div class="browser-dot" style="background:#10B981;"></div></div>
          <div class="browser-url-pill">https://campbuddy.club/event/wordcamp-delhi-2026/camp-card</div>
        </div>
        <div class="browser-viewport">
          <div style="margin-bottom:20px;">
            <h2 style="font-family:var(--font-heading); font-size:26px; font-weight:800; color:var(--navy);">CampCard Digital Badge Studio</h2>
            <p style="font-size:13px; color:var(--muted);">Customize your digital pass across 7 styles, control shared fields, and export 900 DPI print-ready badges.</p>
          </div>
          <div class="studio-grid">
            <div class="campcard-badge-container" style="border-width:1.5px;">
              <div style="display:flex; justify-content:space-between; width:100%; align-items:center;">
                <div style="background:#FFFFFF; border-radius:50%; width:36px; height:36px; padding:2px;">
                  <img src="assets/delhi_favicon.png" style="width:100%; height:100%; object-fit:contain;" alt="Delhi">
                </div>
                <span class="campcard-role">SPEAKER</span>
              </div>
              <div style="width:76px; height:76px; border-radius:50%; border:2.5px solid #D97706; overflow:hidden;">
                <img src="assets/amit_singh.webp" style="width:100%; height:100%; object-fit:cover;" alt="Amit">
              </div>
              <div class="campcard-name">Amit Kumar Singh</div>
              <div style="font-size:11px; color:#94A3B8; margin-top:-6px;">Managing Partner · WPoets</div>
              <div class="campcard-qr"><img src="assets/qr_sample.png" style="width:124px; height:124px;" alt="CampCard QR"></div>
              <div style="font-size:10px; color:#CBD5E1;">campbuddy.club · Offline Scannable QR</div>
            </div>

            <div class="studio-card">
              <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <span class="filter-chip">Ticket</span>
                <span class="filter-chip active">Classic</span>
                <span class="filter-chip">Minimal</span>
                <span class="filter-chip">Bold</span>
                <span class="filter-chip">Split</span>
                <span class="filter-chip">Pass</span>
              </div>
              <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="field-mock">Name: Amit Kumar Singh</div>
                <div class="field-mock">Role: Managing Partner</div>
                <div class="field-mock">Company: WPoets</div>
                <div class="field-mock">City: Pune / New Delhi</div>
              </div>
              <div style="background:var(--success-light); border:1px solid #A7F3D0; border-radius:12px; padding:12px; font-size:12px; color:#047857; font-weight:600;">
                Saved on this device only · Linked to WordCamp Delhi roster listing
              </div>
              <div style="display:flex; gap:12px;">
                <button class="btn-coral-lg" style="padding:10px 24px; font-size:13px;">Share CampCard</button>
                <button class="btn-outline-lg" style="padding:10px 24px; font-size:13px; background:#FFFFFF;">Download 900 DPI PNG</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Web 07: Contributor Day & Venue Guide -->
      <div class="desktop-browser-frame">
        <div class="browser-top-bar">
          <div class="browser-dots"><div class="browser-dot" style="background:#EF4444;"></div><div class="browser-dot" style="background:#F59E0B;"></div><div class="browser-dot" style="background:#10B981;"></div></div>
          <div class="browser-url-pill">https://campbuddy.club/event/wordcamp-delhi-2026/guide</div>
        </div>
        <div class="browser-viewport">
          <div class="web-hero-grid" style="margin-bottom:24px;">
            <div>
              <h2 style="font-family:var(--font-heading); font-size:26px; font-weight:800; color:var(--navy); margin-bottom:8px;">Venue, Contributor Day &amp; First-Timer Handbook</h2>
              <p style="font-size:13px; color:var(--muted); margin-bottom:16px;">Dr. Ambedkar International Centre (DAIC), 15 Janpath Rd, Windsor Place, New Delhi · Metro: Janpath (Violet) &amp; Patel Chowk (Yellow).</p>
              <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <span class="filter-chip active">Core &amp; Gutenberg (Table 1 · Amit Singh)</span>
                <span class="filter-chip">Documentation (Table 2 · Shreya Agarwal)</span>
                <span class="filter-chip">Polyglots Hindi &amp; Urdu</span>
              </div>
            </div>
            <div style="border-radius:16px; overflow:hidden; border:1px solid var(--line);">
              <img src="assets/india_gate.jpg" style="width:100%; height:170px; object-fit:cover;" alt="India Gate New Delhi">
            </div>
          </div>
        </div>
      </div>

    </div>
  </main>

  <!-- ========================================== -->
  <!-- TAB 3: PROGRESSIVE WEB APP (5 SCREENS)     -->
  <!-- ========================================== -->
  <main id="section-pwa" class="section-container">
    <div class="section-intro">
      <div>
        <h2>${getIcon('zap', 22)} Progressive Web App (PWA Standalone)</h2>
        <p>1-tap "Add to Home Screen", offline schedules stored locally on phone, conflict resolution, high-contrast QR CampCard, and venue directions.</p>
      </div>
      <span class="badge-pill badge-pill-green">PWA Standalone · 5 Offline-Ready Screens</span>
    </div>

    <div class="screens-grid-phones">

      <!-- PWA 01: Add to Home Screen -->
      <div class="phone-card-wrapper">
        <div class="screen-meta-title"><span class="screen-meta-badge">P01</span><span>Add to Home Screen</span></div>
        <div class="phone-device">
          <div class="notch"><div class="notch-camera"></div></div>
          <div class="phone-status-bar"><span>10:30 AM</span><span style="color:var(--success); font-weight:700;">Offline · 98%</span></div>
          <div class="phone-scrollable" style="justify-content:center; align-items:center; text-align:center;">
            <div style="width:76px; height:76px; border-radius:20px; background:#FFFFFF; padding:6px; box-shadow:0 10px 25px rgba(0,0,0,0.08); border:1px solid var(--line); margin-bottom:14px;">
              <img src="assets/delhi_favicon.png" style="width:100%; height:100%; object-fit:contain;" alt="WordCamp Delhi">
            </div>
            <h3 style="font-family:var(--font-heading); font-size:20px; font-weight:800; color:var(--ink);">Install WordCamp Delhi</h3>
            <p style="font-size:11px; color:var(--muted); margin:6px 0 18px 0; max-width:260px;">Open straight from your home screen with schedules, bios, and your CampCard ready even without venue Wi-Fi.</p>
            <div style="background:#FFFFFF; border:1px solid var(--line); border-radius:14px; padding:14px; width:100%; text-align:left; display:flex; flex-direction:column; gap:8px;">
              <div style="font-size:11px; font-weight:700; color:var(--ink);">Saved on This Device</div>
              <div style="display:flex; align-items:center; gap:6px; font-size:10px; color:var(--muted);">${getIcon('award', 14, '#10B981')} <span>22 Sessions &amp; 11 Speaker Profiles</span></div>
              <div style="display:flex; align-items:center; gap:6px; font-size:10px; color:var(--muted);">${getIcon('award', 14, '#10B981')} <span>17 Sponsor Logos &amp; Booth Perks</span></div>
              <div style="display:flex; align-items:center; gap:6px; font-size:10px; color:var(--muted);">${getIcon('award', 14, '#10B981')} <span>Instant Offline CampCard QR</span></div>
            </div>
            <button class="btn-coral-lg" style="margin-top:22px; width:100%; font-size:13px;">Add to Home Screen</button>
          </div>
        </div>
      </div>

      <!-- PWA 02: Standalone Companion Home -->
      <div class="phone-card-wrapper">
        <div class="screen-meta-title"><span class="screen-meta-badge">P02</span><span>Standalone Live Companion</span></div>
        <div class="phone-device">
          <div class="notch"><div class="notch-camera"></div></div>
          <div class="phone-status-bar"><span>10:30 AM</span><span style="color:var(--success); font-weight:700;"><span class="pulse-dot" style="width:6px; height:6px; display:inline-block;"></span> Ready · 98%</span></div>
          <div class="phone-scrollable">
            <div class="app-bar">
              <img src="assets/campbuddy_wordmark.png" style="height:26px; width:auto;" alt="CampBuddy">
              <span class="role-badge-pill">Delhi 2026</span>
            </div>
            <div class="tactile-hero-card" style="padding:16px;">
              <div style="font-size:10px; color:#F59E0B; font-weight:700;">HAPPENING NEXT · BHIM HALL</div>
              <div style="font-family:var(--font-heading); font-size:17px; font-weight:800; color:#FFFFFF; margin:4px 0;">10 Things to Do to Boost Local SEO</div>
              <div style="font-size:11px; color:#CBD5E1;">Pankaj Kumar · Starts in 15 mins</div>
            </div>
            <div class="tactile-2col-grid">
              <div class="tactile-card"><div class="icon-box-square">${getIcon('calendar', 18, '#EA580C')}</div><div class="tactile-card-title">Schedule</div><div class="tactile-card-sub">Works offline</div></div>
              <div class="tactile-card"><div class="icon-box-square">${getIcon('users', 18, '#EA580C')}</div><div class="tactile-card-title">CampCard</div><div class="tactile-card-sub">Instant QR pass</div></div>
              <div class="tactile-card"><div class="icon-box-square">${getIcon('gift', 18, '#EA580C')}</div><div class="tactile-card-title">Sponsors</div><div class="tactile-card-sub">17 brands &amp; deals</div></div>
              <div class="tactile-card"><div class="icon-box-square">${getIcon('compass', 18, '#EA580C')}</div><div class="tactile-card-title">Venue Guide</div><div class="tactile-card-sub">DAIC Janpath Rd</div></div>
            </div>
          </div>
        </div>
      </div>

      <!-- PWA 03: Offline My Day & Overlap Check -->
      <div class="phone-card-wrapper">
        <div class="screen-meta-title"><span class="screen-meta-badge">P03</span><span>Offline My Day &amp; Overlaps</span></div>
        <div class="phone-device">
          <div class="notch"><div class="notch-camera"></div></div>
          <div class="phone-status-bar"><span>10:30 AM</span><span style="color:var(--success); font-weight:700;">Offline · 98%</span></div>
          <div class="phone-scrollable">
            <div class="app-bar"><span style="font-family:var(--font-heading); font-size:18px; font-weight:800; color:var(--navy);">My Day</span><button class="btn-gold" style="font-size:9px; padding:5px 12px;">Add Calendar</button></div>
            <div style="background:#FFF5F5; border:1px solid #FCA5A5; border-radius:12px; padding:10px 12px;">
              <div style="font-size:11px; font-weight:700; color:#DC2626;">Overlap at 11:00 AM</div>
              <div style="font-size:10px; color:#64748B;">GEO Talk (BHIM) vs SEO Workshop</div>
            </div>
            <div class="session-item-card"><span class="time-badge">10:00 AM</span><div class="session-title">From Feature to Ecosystem</div><div style="font-size:10px; color:var(--muted);">Radhika Bhama · BHIM Hall</div></div>
            <div class="session-item-card"><span class="time-badge">11:20 AM</span><div class="session-title">Local SEO in the AI Era</div><div style="font-size:10px; color:var(--muted);">Pankaj Kumar · BHIM Hall</div></div>
          </div>
        </div>
      </div>

      <!-- PWA 04: Offline CampCard Badge -->
      <div class="phone-card-wrapper">
        <div class="screen-meta-title"><span class="screen-meta-badge">P04</span><span>Offline CampCard Badge</span></div>
        <div class="phone-device">
          <div class="notch"><div class="notch-camera"></div></div>
          <div class="phone-status-bar"><span>10:30 AM</span><span style="color:var(--success); font-weight:700;">QR Ready · 98%</span></div>
          <div class="phone-scrollable" style="align-items:center;">
            <div class="campcard-badge-container" style="width:100%; border-width:1.5px;">
              <div style="display:flex; justify-content:space-between; width:100%; align-items:center;">
                <div style="background:#FFFFFF; border-radius:50%; width:34px; height:34px; padding:2px;"><img src="assets/delhi_favicon.png" style="width:100%; height:100%; object-fit:contain;" alt="Delhi"></div>
                <span class="campcard-role">ATTENDEE</span>
              </div>
              <div class="campcard-name">Amit Kumar Singh</div>
              <div style="font-size:10px; color:#94A3B8;">Managing Partner · WPoets</div>
              <div class="campcard-qr"><img src="assets/qr_sample.png" style="width:120px; height:120px;" alt="QR"></div>
              <div style="font-size:10px; color:#CBD5E1;">Scan to save contact offline</div>
            </div>
            <button class="btn-outline-lg" style="width:100%; padding:10px; font-size:12px; background:#FFFFFF;">Download 900 DPI Card</button>
          </div>
        </div>
      </div>

      <!-- PWA 05: Venue Map & Emergency SOS -->
      <div class="phone-card-wrapper">
        <div class="screen-meta-title"><span class="screen-meta-badge">P05</span><span>Venue Guide &amp; Safety</span></div>
        <div class="phone-device">
          <div class="notch"><div class="notch-camera"></div></div>
          <div class="phone-status-bar"><span>10:30 AM</span><span style="color:var(--success); font-weight:700;">Offline · 98%</span></div>
          <div class="phone-scrollable">
            <img src="assets/india_gate.jpg" style="width:100%; height:110px; object-fit:cover; border-radius:14px;" alt="Delhi">
            <div class="tactile-card"><div class="tactile-card-title">Dr. Ambedkar Intl. Centre</div><div class="tactile-card-sub">15 Janpath Rd · Metro: Janpath &amp; Patel Chowk</div></div>
            <div class="sticky-sos-wrap">
              <div class="sos-content"><div class="sos-heading">Need Immediate Support?</div><div class="sos-caption">Reach Safety Desk · delhi@wordcamp.org</div></div>
              <button class="btn-sos-red">Help</button>
            </div>
          </div>
        </div>
      </div>

    </div>
  </main>

  <!-- ========================================== -->
  <!-- TAB 4: ADMIN OPERATIONS PANEL (4 SCREENS)  -->
  <!-- ========================================== -->
  <main id="section-admin" class="section-container">
    <div class="section-intro">
      <div>
        <h2>${getIcon('shield', 22)} Admin Operations Panel (Conference HQ)</h2>
        <p>Live delay broadcaster, multi-channel announcements, media health &amp; sponsor leads export, and event manager roles.</p>
      </div>
      <span class="badge-pill badge-pill-coral">Staff &amp; Organizer Web Console · 4 Operations Screens</span>
    </div>

    <div class="screens-grid-desktop">

      <!-- Admin 01: Operations Control Center -->
      <div class="desktop-browser-frame">
        <div class="browser-top-bar">
          <div class="browser-dots"><div class="browser-dot" style="background:#EF4444;"></div><div class="browser-dot" style="background:#F59E0B;"></div><div class="browser-dot" style="background:#10B981;"></div></div>
          <div class="browser-url-pill">https://campbuddy.club/admin/events/wordcamp-delhi-2026</div>
        </div>

        <div class="admin-layout">
          <aside class="admin-sidebar">
            <div style="display:flex; align-items:center; gap:10px;">
              <div style="background:#FFFFFF; border-radius:50%; width:32px; height:32px; padding:2px;">
                <img src="assets/delhi_favicon.png" style="width:100%; height:100%; object-fit:contain;" alt="Delhi" onerror="this.src='docs/designs/assets/delhi_favicon.png'">
              </div>
              <span style="font-family:var(--font-heading); font-size:16px; font-weight:800; color:#FFFFFF;">CampBuddy HQ</span>
            </div>

            <nav style="display:flex; flex-direction:column; gap:6px;">
              <div class="admin-nav-item active">${getIcon('columns', 16)} Dashboard</div>
              <div class="admin-nav-item">${getIcon('clock', 16)} Delay Broadcaster</div>
              <div class="admin-nav-item">${getIcon('bell', 16)} Push Dispatcher</div>
              <div class="admin-nav-item">${getIcon('award', 16)} Media &amp; Feeds</div>
              <div class="admin-nav-item">${getIcon('users', 16)} Roster &amp; Managers</div>
              <div class="admin-nav-item">${getIcon('shield', 16)} Safety Queue</div>
            </nav>
          </aside>

          <div class="admin-main">
            <div style="display:flex; justify-content:space-between; align-items:center;">
              <div>
                <h2 style="font-family:var(--font-heading); font-size:24px; font-weight:800; color:#FFFFFF;">WordCamp Delhi 2026 · Operations Desk</h2>
                <p style="font-size:12px; color:#94A3B8;">Real-time feed sync, venue telemetry, and live schedule adjustments.</p>
              </div>
              <button class="btn-coral-lg" style="font-size:12px; padding:8px 18px;">Send Broadcast</button>
            </div>

            <div class="admin-kpi-grid">
              <div class="admin-kpi-card"><span class="kpi-title">Checked-in Attendees</span><span class="kpi-value">584</span><span class="kpi-trend">↑ 92% of capacity</span></div>
              <div class="admin-kpi-card"><span class="kpi-title">Scheduled Sessions</span><span class="kpi-value">22</span><span class="kpi-trend" style="color:#F59E0B;">3 Active Tracks</span></div>
              <div class="admin-kpi-card"><span class="kpi-title">Verified Sponsors</span><span class="kpi-value">17</span><span class="kpi-trend">100% Media Healthy</span></div>
              <div class="admin-kpi-card"><span class="kpi-title">Open Reports</span><span class="kpi-value">2</span><span class="kpi-trend" style="color:#F59E0B;">Ready to Review</span></div>
            </div>

            <div style="background:#1E293B; border:1px solid #334155; border-radius:16px; padding:20px; display:flex; flex-direction:column; gap:14px;">
              <div style="display:flex; justify-content:space-between; align-items:center;">
                <div style="font-family:var(--font-heading); font-size:16px; font-weight:700; color:#FFFFFF;">Live Schedule Delay Broadcaster</div>
                <span style="font-size:11px; color:#F59E0B; font-weight:700;">Automatic Talk Shift</span>
              </div>
              <div style="display:grid; grid-template-columns: 200px 1fr 160px; gap:20px; align-items:center;">
                <select style="background:#0F172A; border:1px solid #334155; color:#FFFFFF; padding:10px; border-radius:8px; font-size:12px;">
                  <option>Track 1: BHIM Auditorium</option>
                  <option>Track 2: Contributor Lounge</option>
                </select>
                <div>
                  <div style="display:flex; justify-content:space-between; font-size:11px; margin-bottom:4px;"><span>Delay Offset: +15 minutes</span><span style="color:#94A3B8;">Shifts 5 upcoming talks</span></div>
                  <input type="range" min="0" max="60" value="15" style="width:100%; accent-color:var(--coral);">
                </div>
                <button class="btn-coral-lg" style="font-size:12px; padding:10px;">Broadcast Delay</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Admin 02: Broadcasts & Safety Moderation -->
      <div class="desktop-browser-frame">
        <div class="browser-top-bar">
          <div class="browser-dots"><div class="browser-dot" style="background:#EF4444;"></div><div class="browser-dot" style="background:#F59E0B;"></div><div class="browser-dot" style="background:#10B981;"></div></div>
          <div class="browser-url-pill">https://campbuddy.club/admin/events/wordcamp-delhi-2026/notifications</div>
        </div>
        <div class="admin-main" style="padding:28px 32px;">
          <div style="display:grid; grid-template-columns:1fr 1fr; gap:24px;">
            <div class="admin-kpi-card" style="gap:12px;">
              <div style="font-family:var(--font-heading); font-size:18px; font-weight:800; color:#FFFFFF;">Broadcast Composer</div>
              <div style="background:#0F172A; border:1px solid #334155; border-radius:10px; padding:10px 14px; font-size:12px; color:#FFFFFF;">Lunch is Served in Courtyard!</div>
              <div style="background:#0F172A; border:1px solid #334155; border-radius:10px; padding:10px 14px; font-size:12px; color:#94A3B8;">Hot buffet open near Hall B. Stop by Elementor &amp; WP Fusion booths on your way!</div>
              <div style="display:flex; justify-content:space-between; align-items:center;">
                <span style="font-size:11px; color:#10B981;">Reaches 584 attendee devices</span>
                <button class="btn-coral-lg" style="padding:8px 20px; font-size:12px;">Send Now</button>
              </div>
            </div>
            <div class="admin-kpi-card" style="gap:12px; border-color:#7F1D1D;">
              <div style="display:flex; justify-content:space-between; align-items:center;">
                <span style="font-family:var(--font-heading); font-size:18px; font-weight:800; color:#FFFFFF;">Safety &amp; Profile Moderation</span>
                <span class="badge-pill badge-pill-coral">2 Open</span>
              </div>
              <div style="font-size:12px; color:#CBD5E1;">Flagged Discovery Card: "Card promotes off-topic external links."</div>
              <div style="display:flex; gap:10px;">
                <button class="btn-coral-lg" style="padding:8px 18px; font-size:12px;">Hide Profile</button>
                <button style="background:#334155; color:#FFFFFF; border:none; border-radius:9999px; padding:8px 18px; font-size:12px; font-weight:600; cursor:pointer;">Dismiss Report</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Admin 03: Sponsor Deals, Captured Leads & Media Health -->
      <div class="desktop-browser-frame">
        <div class="browser-top-bar">
          <div class="browser-dots"><div class="browser-dot" style="background:#EF4444;"></div><div class="browser-dot" style="background:#F59E0B;"></div><div class="browser-dot" style="background:#10B981;"></div></div>
          <div class="browser-url-pill">https://campbuddy.club/admin/deals/leads</div>
        </div>
        <div class="admin-main" style="padding:28px 32px;">
          <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
              <h3 style="font-family:var(--font-heading); font-size:20px; font-weight:800; color:#FFFFFF;">Sponsor Deals &amp; Opt-In Lead Exports</h3>
              <p style="font-size:12px; color:#94A3B8;">17/17 sponsor logos &amp; 11/11 speaker portraits verified · 184 total opt-in leads</p>
            </div>
            <button class="btn-gold" style="padding:9px 18px; font-size:12px;">Export CSV</button>
          </div>
          <div class="admin-kpi-grid" style="grid-template-columns:repeat(3, 1fr);">
            <div class="admin-kpi-card"><span class="kpi-title">WP Fusion · 30% Off</span><span class="kpi-value">64 Leads</span><span class="kpi-trend">DPDP Consent Logged</span></div>
            <div class="admin-kpi-card"><span class="kpi-title">Elementor One · Free</span><span class="kpi-value">78 Claims</span><span class="kpi-trend">Code: WCDELHI26</span></div>
            <div class="admin-kpi-card"><span class="kpi-title">Media Feed Health</span><span class="kpi-value">100%</span><span class="kpi-trend">0 broken assets</span></div>
          </div>
        </div>
      </div>

      <!-- Admin 04: Event Managers & Social Kit Studio -->
      <div class="desktop-browser-frame">
        <div class="browser-top-bar">
          <div class="browser-dots"><div class="browser-dot" style="background:#EF4444;"></div><div class="browser-dot" style="background:#F59E0B;"></div><div class="browser-dot" style="background:#10B981;"></div></div>
          <div class="browser-url-pill">https://campbuddy.club/admin/events/wordcamp-delhi-2026/social</div>
        </div>
        <div class="admin-main" style="padding:28px 32px;">
          <div style="display:grid; grid-template-columns:1fr 300px; gap:24px; align-items:center;">
            <div style="display:flex; flex-direction:column; gap:12px;">
              <h3 style="font-family:var(--font-heading); font-size:20px; font-weight:800; color:#FFFFFF;">Event Managers &amp; Social Asset Generator</h3>
              <p style="font-size:12px; color:#94A3B8;">Assign event managers per WordCamp and generate speaker promo graphics and 1080×1920 schedule reels.</p>
              <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="admin-kpi-card"><span class="kpi-title">Rohan Verma · Active</span><span style="font-size:12px; color:#FFFFFF;">WordCamp Delhi &amp; Rajasthan</span></div>
                <div class="admin-kpi-card"><span class="kpi-title">Neha Kapoor · Active</span><span style="font-size:12px; color:#FFFFFF;">WordCamp Delhi &amp; Pune</span></div>
              </div>
            </div>
            <div class="campcard-badge-container" style="padding:18px; border-width:1.5px;">
              <div style="display:flex; justify-content:space-between; width:100%; align-items:center;">
                <div style="background:#FFFFFF; border-radius:50%; width:32px; height:32px; padding:2px;"><img src="assets/delhi_favicon.png" style="width:100%; height:100%; object-fit:contain;" alt="Delhi"></div>
                <span style="font-size:10px; font-weight:700; color:#F59E0B;">WORDCAMP DELHI 2026</span>
              </div>
              <div style="width:76px; height:76px; border-radius:50%; border:2px solid var(--coral); overflow:hidden;"><img src="assets/pankaj_kumar.webp" style="width:100%; height:100%; object-fit:cover;" alt="Pankaj"></div>
              <div style="font-family:var(--font-heading); font-size:16px; font-weight:800;">Pankaj Kumar</div>
              <div style="font-size:11px; color:#CBD5E1; text-align:center;">Local SEO in the AI Era · BHIM Hall</div>
              <div style="font-size:10px; color:#10B981; font-weight:700;">campbuddy.club/delhi-2026</div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </main>

  <!-- Master Audit & Guarantee Bar -->
  <footer class="audit-bar">
    <div style="display:flex; flex-direction:column; gap:2px;">
      <span style="font-family:var(--font-heading); font-size:14px; font-weight:700; color:#FFFFFF;">Clean Design System · Zero Visual Clutter Guarantee</span>
      <span style="font-size:11px; color:#94A3B8;">50 Mobile APK screens · 7 Desktop Web screens · 5 PWA screens · 4 Admin screens</span>
    </div>
    <div class="audit-checkmarks">
      <span>${getIcon('award', 16, '#10B981')} 100% Real Delhi 2026 Data</span>
      <span>${getIcon('award', 16, '#10B981')} Sharp Delhi Roundel &amp; CampBuddy Brand</span>
      <span>${getIcon('award', 16, '#10B981')} Hairline Cards &amp; Lucide Vectors</span>
      <span>${getIcon('award', 16, '#10B981')} Zero Technical Jargon</span>
    </div>
  </footer>

  <!-- Full-Screen Zoom Lightbox -->
  <div id="lightbox-modal" class="lightbox-modal" onclick="closeLightbox(event)">
    <button type="button" class="lightbox-close" onclick="closeLightbox(event)">×</button>
    <img id="lightbox-img" src="" alt="Preview">
  </div>

  <script>
    function switchPlatform(tabKey) {
      document.querySelectorAll('.platform-tab').forEach(t => t.classList.remove('active'));
      document.querySelectorAll('.section-container').forEach(s => s.classList.remove('active'));

      const activeTabBtn = Array.from(document.querySelectorAll('.platform-tab')).find(b => b.getAttribute('onclick').includes(tabKey));
      if (activeTabBtn) activeTabBtn.classList.add('active');

      const targetSection = document.getElementById('section-' + tabKey);
      if (targetSection) targetSection.classList.add('active');
    }

    function filterApk(role, btn) {
      document.querySelectorAll('.apk-filter-pill').forEach(b => b.classList.remove('active'));
      if (btn) btn.classList.add('active');
      document.querySelectorAll('.apk-group-block').forEach(g => {
        g.style.display = (role === 'all' || g.dataset.apkRole === role) ? 'block' : 'none';
      });
    }

    function openLightbox(src) {
      const m = document.getElementById('lightbox-modal');
      const img = document.getElementById('lightbox-img');
      if (m && img) {
        img.src = src;
        m.classList.add('open');
      }
    }

    function closeLightbox(e) {
      if (e.target.id === 'lightbox-modal' || e.target.classList.contains('lightbox-close')) {
        document.getElementById('lightbox-modal').classList.remove('open');
      }
    }
  </script>
</body>
</html>
`;

// Write to artifact URI (remapping screens/apk/ -> designs/apk/) and docs/designs/specimen.html
const artifactHtml = html.replace(/screens\/apk\//g, 'designs/apk/');
fs.writeFileSync(artifactFile, artifactHtml, 'utf8');
console.log(`Saved specimen to Artifact: ${artifactFile}`);

fs.writeFileSync(docFile, html, 'utf8');
console.log(`Saved specimen to Docs: ${docFile}`);
