import fs from 'node:fs';
import path from 'node:path';

const mkt = 'E:/Sites/InHouse/campbuddy/marketing';
const v2Tools = 'E:/Sites/InHouse/campbuddy/V2/tools';
const mktScripts = path.join(mkt, 'scripts');

fs.copyFileSync(
  path.join(v2Tools, 'generate-master-figcraft-clean.mjs'),
  path.join(mktScripts, 'generate-master-figcraft-clean.mjs')
);
fs.copyFileSync(
  path.join(v2Tools, 'generate-post-captions.mjs'),
  path.join(mktScripts, 'generate-post-captions.mjs')
);
console.log('Copied generator scripts into marketing/scripts/');
