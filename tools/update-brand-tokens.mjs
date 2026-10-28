import fs from 'node:fs';

// 1. resources/css/app.css
let tw = fs.readFileSync('resources/css/app.css', 'utf8');
tw = tw.replace(/--shadow-focus:\s*0 0 0 3px rgba\(195, 58, 25, 0\.32\);/, '--shadow-focus: 0 0 0 3px rgba(255, 77, 45, 0.32);');
tw = tw.replace(/--color-ink:\s*#231f20;/, '--color-ink: #0f172a;');
tw = tw.replace(/--color-paper:\s*#fffaf4;/, '--color-paper: #fafafd;');
tw = tw.replace(/--color-paper-soft:\s*#fff0df;/, '--color-paper-soft: #fff7ed;');
tw = tw.replace(/--color-muted:\s*#6b625e;/, '--color-muted: #475569;');
tw = tw.replace(/--color-subtle:\s*#7a706b;/, '--color-subtle: #64748b;');
tw = tw.replace(/--color-line:\s*#eadfd6;/, '--color-line: #e2e8f0;');
tw = tw.replace(/--color-maroon:\s*#c33a19;/, '--color-maroon: #ff4d2d;');
tw = tw.replace(/--color-maroon-dark:\s*#a12f14;/, '--color-maroon-dark: #e03616;');
tw = tw.replace(/--color-navy:\s*#0d2343;/, '--color-navy: #1e293b;');
tw = tw.replace(/--color-gold:\s*#e39d1c;/, '--color-gold: #f59e0b;');
tw = tw.replace(/--color-wine:\s*#721313;/, '--color-wine: #ea580c;');
tw = tw.replace(/--color-teal:\s*#047a7c;/, '--color-teal: #2563eb;');
fs.writeFileSync('resources/css/app.css', tw);
console.log('Updated resources/css/app.css');

// 2. resources/js/brand.js
let bjs = fs.readFileSync('resources/js/brand.js', 'utf8');
bjs = bjs.replace(/maroon:\s*'#c33a19'/, "maroon: '#ff4d2d'");
bjs = bjs.replace(/maroonDark:\s*'#a12f14'/, "maroonDark: '#e03616'");
bjs = bjs.replace(/wine:\s*'#721313'/, "wine: '#ea580c'");
bjs = bjs.replace(/gold:\s*'#e39d1c'/, "gold: '#f59e0b'");
bjs = bjs.replace(/ink:\s*'#231f20'/, "ink: '#0f172a'");
bjs = bjs.replace(/paper:\s*'#fffaf4'/, "paper: '#fafafd'");
bjs = bjs.replace(/paperSoft:\s*'#fff0df'/, "paperSoft: '#fff7ed'");
bjs = bjs.replace(/line:\s*'#eadfd6'/, "line: '#e2e8f0'");
bjs = bjs.replace(/muted:\s*'#6b625e'/, "muted: '#475569'");
bjs = bjs.replace(/teal:\s*'#047a7c'/, "teal: '#2563eb'");
bjs = bjs.replace(/navy:\s*'#0d2343'/, "navy: '#1e293b'");
fs.writeFileSync('resources/js/brand.js', bjs);
console.log('Updated resources/js/brand.js');

// 3. app/Support/BrandTokens.php
let bt = fs.readFileSync('app/Support/BrandTokens.php', 'utf8');
bt = bt.replace(/\['--paper',\s*'#FFFAF4'/g, "['--paper', '#FAFAFD'");
bt = bt.replace(/\['--paper-soft',\s*'#FFF0DF'/g, "['--paper-soft', '#FFF7ED'");
bt = bt.replace(/\['--line',\s*'#EADFD6'/g, "['--line', '#E2E8F0'");
bt = bt.replace(/\['--ink',\s*'#231F20'/g, "['--ink', '#0F172A'");
bt = bt.replace(/\['--muted',\s*'#6B625E'/g, "['--muted', '#475569'");
bt = bt.replace(/\['--subtle',\s*'#7A706B'/g, "['--subtle', '#64748B'");
bt = bt.replace(/\['--brand-primary',\s*'#C33A19'/g, "['--brand-primary', '#FF4D2D'");
bt = bt.replace(/\['--brand-accent',\s*'#A12F14'/g, "['--brand-accent', '#E03616'");
bt = bt.replace(/\['--wine',\s*'#721313'/g, "['--wine', '#EA580C'");
bt = bt.replace(/\['--gold',\s*'#E39D1C'/g, "['--gold', '#F59E0B'");
bt = bt.replace(/\['--peach',\s*'#FFE4C4'/g, "['--peach', '#FFEDD5'");
bt = bt.replace(/\['--teal',\s*'#047A7C'/g, "['--teal', '#2563EB'");
bt = bt.replace(/\['--navy',\s*'#0D2343'/g, "['--navy', '#1E293B'");
bt = bt.replace(/\['--success',\s*'#1F7A4D'/g, "['--success', '#10B981'");
bt = bt.replace(/\['--danger',\s*'#B3261E'/g, "['--danger', '#EF4444'");

// Also update SHARE array in BrandTokens.php
bt = bt.replace(/\['Paper',\s*56,\s*'#FFFAF4',\s*'#231F20'\]/, "['Paper', 56, '#FAFAFD', '#0F172A']");
bt = bt.replace(/\['Ink',\s*20,\s*'#231F20',\s*'#FFFFFF'\]/, "['Ink', 20, '#0F172A', '#FFFFFF']");
bt = bt.replace(/\['Maroon',\s*12,\s*'#C33A19',\s*'#FFFFFF'\]/, "['Primary Coral', 12, '#FF4D2D', '#FFFFFF']");
bt = bt.replace(/\['Wine',\s*5,\s*'#721313',\s*'#FFFFFF'\]/, "['Amber Glow', 5, '#EA580C', '#FFFFFF']");
bt = bt.replace(/\['Ochre',\s*4,\s*'#E39D1C',\s*'#231F20'\]/, "['Sunny Gold', 4, '#F59E0B', '#0F172A']");
bt = bt.replace(/\['Roles',\s*3,\s*'#047A7C',\s*'#FFFFFF'\]/, "['Roles Indigo', 3, '#2563EB', '#FFFFFF']");

// Update CONTRAST array
bt = bt.replace(/\['Ink on paper',\s*'#231F20',\s*'#FFFAF4'\]/, "['Ink on paper', '#0F172A', '#FAFAFD']");
bt = bt.replace(/\['Maroon on paper',\s*'#C33A19',\s*'#FFFAF4'\]/, "['Primary Coral on paper', '#FF4D2D', '#FAFAFD']");
bt = bt.replace(/\['White on maroon',\s*'#FFFFFF',\s*'#C33A19'\]/, "['White on Primary Coral', '#FFFFFF', '#FF4D2D']");
bt = bt.replace(/\['Muted on paper',\s*'#6B625E',\s*'#FFFAF4'\]/, "['Muted on paper', '#475569', '#FAFAFD']");
bt = bt.replace(/\['Ochre on ink',\s*'#E39D1C',\s*'#231F20'\]/, "['Sunny Gold on ink', '#F59E0B', '#0F172A']");
bt = bt.replace(/\['Ochre text on paper',\s*'#E39D1C',\s*'#FFFAF4'\]/, "['Sunny Gold text on paper', '#F59E0B', '#FAFAFD']");

fs.writeFileSync('app/Support/BrandTokens.php', bt);
console.log('Updated app/Support/BrandTokens.php');

// 4. app/Support/MobileAppDefaults.php
let mad = fs.readFileSync('app/Support/MobileAppDefaults.php', 'utf8');
mad = mad.replace(/'primary' => '#C33A19'/, "'primary' => '#FF4D2D'");
mad = mad.replace(/'accent' => '#E39D1C'/, "'accent' => '#F59E0B'");
mad = mad.replace(/'background' => '#FFF0DF'/, "'background' => '#FFF7ED'");
mad = mad.replace(/'surface' => '#FFFAF4'/, "'surface' => '#FAFAFD'");
mad = mad.replace(/'peach' => '#FFE4C4'/, "'peach' => '#FFEDD5'");
mad = mad.replace(/'line' => '#EADFD6'/, "'line' => '#E2E8F0'");
mad = mad.replace(/'ink' => '#231F20'/, "'ink' => '#0F172A'");
mad = mad.replace(/'muted' => '#6B625E'/, "'muted' => '#475569'");
mad = mad.replace(/'danger' => '#B3261E'/, "'danger' => '#EF4444'");
mad = mad.replace(/'success' => '#1F7A4D'/, "'success' => '#10B981'");
fs.writeFileSync('app/Support/MobileAppDefaults.php', mad);
console.log('Updated app/Support/MobileAppDefaults.php');

// 5. ConfigModels.kt
let kt = fs.readFileSync('native-app/shared/src/commonMain/kotlin/club/campbuddy/shared/model/ConfigModels.kt', 'utf8');
kt = kt.replace(/val primary: String = "#C33A19"/, 'val primary: String = "#FF4D2D"');
kt = kt.replace(/val accent: String = "#E39D1C"/, 'val accent: String = "#F59E0B"');
kt = kt.replace(/val background: String = "#FFF0DF"/, 'val background: String = "#FFF7ED"');
kt = kt.replace(/val surface: String = "#FFFAF4"/, 'val surface: String = "#FAFAFD"');
kt = kt.replace(/val peach: String = "#FFE4C4"/, 'val peach: String = "#FFEDD5"');
kt = kt.replace(/val line: String = "#EADFD6"/, 'val line: String = "#E2E8F0"');
kt = kt.replace(/val ink: String = "#231F20"/, 'val ink: String = "#0F172A"');
kt = kt.replace(/val muted: String = "#6B625E"/, 'val muted: String = "#475569"');
kt = kt.replace(/val danger: String = "#B3261E"/, 'val danger: String = "#EF4444"');
kt = kt.replace(/val success: String = "#1F7A4D"/, 'val success: String = "#10B981"');
fs.writeFileSync('native-app/shared/src/commonMain/kotlin/club/campbuddy/shared/model/ConfigModels.kt', kt);
console.log('Updated ConfigModels.kt');
