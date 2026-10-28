import fs from 'node:fs';

const file = '../marketing/usp-campaign/reels/engine.js';
let content = fs.readFileSync(file, 'utf8').replace(/\r\n/g, '\n');

const startMarker = '\tend(s, R) {';
const endMarker = '\t},\n};';

const startIdx = content.indexOf(startMarker);
const endIdx = content.indexOf(endMarker, startIdx);

if (startIdx === -1 || endIdx === -1) {
  console.error('Markers not found! startIdx:', startIdx, 'endIdx:', endIdx);
  process.exit(1);
}

const newEnd = `\tend(s, R) {
\t\tconst el = $('div', 'scene end');
\t\tconst brandCard = $('div', 'master-brand-plate', \`
\t\t\t<img class="brand-cb" src="../shared/img/logo-wordmark.png" alt="CampBuddy">
\t\t\t<span class="brand-pipe"></span>
\t\t\t<div class="brand-wps-wrap">
\t\t\t\t<span class="brand-sub">Crafted by</span>
\t\t\t\t<img class="brand-wps" src="../shared/img/wpsimplified-logo.png" alt="WPSimplified">
\t\t\t</div>
\t\t\`);
\t\tconst call = $('div', 'txt', \`<div class="title" style="font-size:76px;margin-top:0">\${rich(s.call || 'Make the most of your *WordCamp*')}</div>\`);
\t\tcall.style.top = '340px';
\t\tconst qr = $('div', 'qr', R.qr(s.url));
\t\tconst url = $('div', 'txt', \`<div class="url-badge">\${icon('globe')}<span>campbuddy.club</span></div>\`);
\t\tconst foot = $('div', 'abs', \`<div class="credit">100% Free · Zero Sign-Up · Works Offline</div>\`);
\t\tfoot.style.cssText = \`left:\${SAFE.x}px;width:\${SAFE.w}px;text-align:center;top:1360px;\`;
\t\tel.append(anim(brandCard, 'drop', 0, 0.45), anim(call, 'rise', 0.1, 0.5), anim(qr, 'pop', 0.25, 0.6), anim(url, 'rise', 0.45, 0.5), anim(foot, 'fade', 0.6, 0.5));
\t\treturn {
\t\t\tel, ground: 'paper', dark: false, hideStep: true, hideChrome: true,
\t\t\tlayout() {
\t\t\t\tconst h = fit(call.querySelector('.title'), 220, 56);
\t\t\t\tconst qTop = Math.max(490, 340 + h + 36);
\t\t\t\tqr.style.top = qTop + 'px';
\t\t\t\turl.style.top = (qTop + 500 + 36) + 'px';
\t\t\t},
\t\t\ttick(lt, t) {
\t\t\t\tconst pb = R.pulse(t);
\t\t\t\tqr.style.boxShadow = \`0 20px 50px -10px rgba(15,23,42,0.16), 0 0 0 \${8 + pb * 8}px rgba(255,77,45,\${0.12 + pb * 0.12})\`;
\t\t\t},
\t\t};`;

content = content.substring(0, startIdx) + newEnd + content.substring(endIdx);
fs.writeFileSync(file, content);
console.log('Successfully updated BUILD.end in engine.js');
