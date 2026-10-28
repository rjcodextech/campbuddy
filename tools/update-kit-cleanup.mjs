import fs from 'node:fs';

const p = 'E:/Sites/InHouse/campbuddy/marketing/usp-campaign/design/kit.js';
let code = fs.readFileSync(p, 'utf8');

// 1. Update eventChip to show only logo, no city name
const oldChip = `const eventChip = (E, h) => \`<span class="chip" style="height:\${h}px;padding:0 \${h * 0.45}px 0 \${h * 0.14}px;font-size:\${h * 0.4}px"><span class="mark" style="width:\${h * 0.76}px;height:\${h * 0.76}px"><img src="../shared/img/\${E.mark}" style="width:\${h * 0.58}px;height:\${h * 0.58}px"></span>\${E.chip}</span>\`;`;
const newChip = `const eventChip = (E, h) => \`<span class="chip" style="height:\${h}px;padding:0 \${h * 0.3}px;display:inline-flex;align-items:center;justify-content:center"><img src="../shared/img/\${E.logo}" style="height:\${h * 0.65}px;max-width:260px"></span>\`;`;

if (code.includes(oldChip)) {
  code = code.replace(oldChip, newChip);
}

// 2. In portrait footer: replace ${E.name} with WPSimplified logo
const oldFoot = `<div class="ev">\${E.name} · \${E.when.replace(/\\*/g, '')}</div>`;
const newFoot = `<div class="ev" style="display:flex;align-items:center;gap:12px;margin-top:4px"><img src="../shared/img/wpsimplified-logo.png" style="height:28px"></div>`;

if (code.includes(oldFoot)) {
  code = code.replace(oldFoot, newFoot);
}

// 3. In cover: place WPSimplified logo at bottom
const oldCoverChip = `const chip = place($('div', 'abs', eventChip(E, 76)), 0, 1590, W);`;
const newCoverChip = `const chip = place($('div', 'abs', \`<span class="chip" style="height:76px;padding:0 24px;display:inline-flex;align-items:center;justify-content:center"><img src="../shared/img/wpsimplified-logo.png" style="height:44px"></span>\`), 0, 1590, W);`;

if (code.includes(oldCoverChip)) {
  code = code.replace(oldCoverChip, newCoverChip);
}

fs.writeFileSync(p, code);
console.log('Successfully updated kit.js to remove city names and show WPSimplified logo at bottom');
