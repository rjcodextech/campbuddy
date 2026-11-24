import fs from 'node:fs';
import path from 'node:path';

const svgDir = path.resolve('docs/icons/svg');
const files = fs.readdirSync(svgDir).filter(f => f.endsWith('.svg')).map(f => f.replace('.svg', '')).sort();

const cards = files.map(name => {
  const svg = fs.readFileSync(path.join(svgDir, name + '.svg'), 'utf8');
  return `
  <div class="icon-card" data-name="${name}">
    <div class="preview-box">${svg}</div>
    <div class="name">${name}</div>
    <div class="actions">
      <a href="svg/${name}.svg" download class="btn btn-svg">SVG</a>
      <a href="png/${name}.png" download class="btn btn-png">PNG</a>
    </div>
  </div>`;
}).join('\n');

const html = `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CampBuddy Unified Icon Suite (76 Icons)</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --navy: #1E293B;
      --ink: #0F172A;
      --coral: #FF4D2D;
      --ochre: #D97706;
      --peach: #FFF7ED;
      --surface: #FAFAFD;
      --border: #E2E8F0;
      --muted: #64748B;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Poppins', sans-serif;
      background: var(--peach);
      color: var(--ink);
      padding: 40px 24px;
      line-height: 1.5;
    }
    .header {
      max-width: 1200px;
      margin: 0 auto 32px auto;
      text-align: center;
    }
    .header h1 {
      font-family: 'Baloo 2', sans-serif;
      font-size: 38px;
      font-weight: 800;
      color: var(--navy);
      margin-bottom: 8px;
    }
    .header p {
      font-size: 16px;
      color: var(--muted);
      margin-bottom: 24px;
    }
    .search-bar {
      max-width: 480px;
      margin: 0 auto;
      position: relative;
    }
    .search-bar input {
      width: 100%;
      padding: 14px 20px;
      border-radius: 50px;
      border: 1.5px solid var(--border);
      background: #fff;
      font-size: 15px;
      font-family: inherit;
      outline: none;
      transition: border-color 0.2s;
    }
    .search-bar input:focus {
      border-color: var(--coral);
    }
    .stats {
      margin-top: 12px;
      font-size: 13px;
      color: var(--muted);
    }
    .icon-grid {
      max-width: 1200px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
      gap: 16px;
    }
    .icon-card {
      background: #fff;
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 20px 14px 14px;
      display: flex;
      flex-direction: column;
      align-items: center;
      transition: transform 0.15s, box-shadow 0.15s, border-color 0.15s;
    }
    .icon-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(0,0,0,0.06);
      border-color: var(--coral);
    }
    .preview-box {
      width: 54px;
      height: 54px;
      background: var(--peach);
      border-radius: 14px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 12px;
      color: var(--navy);
    }
    .preview-box svg {
      width: 28px;
      height: 28px;
    }
    .name {
      font-size: 13px;
      font-weight: 600;
      color: var(--navy);
      text-align: center;
      word-break: break-all;
      margin-bottom: 12px;
    }
    .actions {
      display: flex;
      gap: 6px;
      width: 100%;
    }
    .btn {
      flex: 1;
      text-align: center;
      padding: 6px 0;
      font-size: 11px;
      font-weight: 700;
      border-radius: 6px;
      text-decoration: none;
      transition: background 0.15s;
    }
    .btn-svg {
      background: #F1F5F9;
      color: var(--navy);
    }
    .btn-svg:hover {
      background: #E2E8F0;
    }
    .btn-png {
      background: var(--coral);
      color: #fff;
    }
    .btn-png:hover {
      background: #E03E1F;
    }
  </style>
</head>
<body>
  <div class="header">
    <h1>CampBuddy Icon Suite</h1>
    <p>Complete set of 76 unified line icons for Web, Admin Panel, and Mobile APK.</p>
    <div class="search-bar">
      <input type="text" id="search" placeholder="Search 76 icons by name (e.g. qr-code, crown, calendar)..." oninput="filterIcons()">
      <div class="stats"><span id="count">${files.length}</span> icons visible</div>
    </div>
  </div>

  <div class="icon-grid" id="grid">
    ${cards}
  </div>

  <script>
    function filterIcons() {
      const q = document.getElementById('search').value.toLowerCase().trim();
      const cards = document.querySelectorAll('.icon-card');
      let visible = 0;
      cards.forEach(c => {
        const name = c.dataset.name;
        if (!q || name.includes(q)) {
          c.style.display = 'flex';
          visible++;
        } else {
          c.style.display = 'none';
        }
      });
      document.getElementById('count').textContent = visible;
    }
  </script>
</body>
</html>`;

fs.writeFileSync('docs/icons/index.html', html, 'utf8');
console.log('docs/icons/index.html generated successfully!');
