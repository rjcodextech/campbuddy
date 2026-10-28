import fs from 'node:fs';

const scssDir = 'E:/Sites/InHouse/campbuddy/V2/resources/scss';

function walk(dir) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = dir + '/' + entry.name;
    if (entry.isDirectory()) walk(full);
    else if (entry.name.endsWith('.scss')) {
      let content = fs.readFileSync(full, 'utf8');
      let changed = false;
      if (content.includes('rgba(244, 166, 41, 0.35)')) {
        content = content.replaceAll('rgba(244, 166, 41, 0.35)', 'rgba(217, 119, 6, 0.12)');
        changed = true;
      }
      if (content.includes('rgba(244, 166, 41, 0.45)')) {
        content = content.replaceAll('rgba(244, 166, 41, 0.45)', 'rgba(217, 119, 6, 0.14)');
        changed = true;
      }
      if (content.includes('rgba(244, 166, 41, 0.22)')) {
        content = content.replaceAll('rgba(244, 166, 41, 0.22)', 'rgba(217, 119, 6, 0.10)');
        changed = true;
      }
      if (content.includes('rgba(244, 166, 41, 0.12)')) {
        content = content.replaceAll('rgba(244, 166, 41, 0.12)', 'rgba(217, 119, 6, 0.08)');
        changed = true;
      }
      if (content.includes('rgba(244, 166, 41, 0.25)')) {
        content = content.replaceAll('rgba(244, 166, 41, 0.25)', 'rgba(217, 119, 6, 0.10)');
        changed = true;
      }
      if (changed) {
        fs.writeFileSync(full, content, 'utf8');
        console.log(`Softened glows in ${entry.name}`);
      }
    }
  }
}

walk(scssDir);
