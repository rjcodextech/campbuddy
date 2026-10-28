import fs from 'node:fs';

const filePath = '.claude/skills/campbuddy-docs/spec/26-brand-guideline.md';
let s = fs.readFileSync(filePath, 'utf8');

s = s.replace(
  /Owner decisions \(22 Oct 2026, confirmed twice\): keep CampBuddy maroon `#C33A19` as the one brand\s*voice \(logo wine `#721313` as gradient partner, gold\/ochre `#E39D1C` light only\); Baloo 2 \+ Poppins/,
  `Owner decisions (27 Oct 2026, Daylight 2.0 update): CampBuddy Electric Fire Coral \`#FF4D2D\` as the primary brand voice (Radiant Amber \`#EA580C\` as gradient partner, Sunny Gold \`#F59E0B\` for stars/highlights, Tech Indigo \`#2563EB\` for session tracks); Baloo 2 + Poppins`
);

fs.writeFileSync(filePath, s);
console.log('Updated 26-brand-guideline.md');
