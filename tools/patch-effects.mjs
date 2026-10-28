import fs from 'node:fs';

let content = fs.readFileSync('resources/scss/components/_effects.scss', 'utf8');

content = content.replace(/rgba\(227,\s*157,\s*28,/g, 'rgba(217, 119, 6,');
content = content.replace(/rgba\(195,\s*58,\s*25,/g, 'rgba(255, 77, 45,');
content = content.replace(/rgba\(255,\s*240,\s*223,\s*0\.82\)/g, 'rgba(255, 247, 237, 0.88)');

fs.writeFileSync('resources/scss/components/_effects.scss', content, 'utf8');
console.log('Updated _effects.scss successfully');
