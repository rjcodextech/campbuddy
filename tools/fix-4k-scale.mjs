import fs from 'node:fs';

const p = 'E:/Sites/InHouse/campbuddy/marketing/usp-campaign/reels/render.mjs';
let code = fs.readFileSync(p, 'utf8').replace(/\r\n/g, '\n');

// 1. Ensure clip scale is 1 (since deviceScaleFactor is already SCALE=2 for 4K)
code = code.replace(
  'clip: { x: 0, y: 0, width: 1080, height: 1920, scale: SCALE }',
  'clip: { x: 0, y: 0, width: 1080, height: 1920, scale: 1 }'
);

// 2. Add -level:v 5.2 for 4K H.264 compliance
const oldFf = "'-pix_fmt', 'yuv420p', '-profile:v', 'high'";
const newFf = "'-pix_fmt', 'yuv420p', '-profile:v', 'high', '-level:v', '5.2'";

if (code.includes(oldFf)) {
  code = code.replace(oldFf, newFf);
}

fs.writeFileSync(p, code);
console.log('Successfully patched render.mjs for true 2160x3840 4K Level 5.2 encoding');
