import fs from 'node:fs';

const kitPath = 'E:/Sites/InHouse/campbuddy/marketing/usp-campaign/design/kit.js';
let kit = fs.readFileSync(kitPath, 'utf8').replace(/\r\n/g, '\n');

if (!kit.includes('const customUrlPill =')) {
  kit = kit.replace(
    'const urlPill = (h) =>',
    'const customUrlPill = (url, h) => `<span class="urlpill" style="height:${h}px;padding:0 ${h * 0.45}px;font-size:${h * 0.42}px">${icon(\'globe\', `style="width:${h * 0.48}px;height:${h * 0.48}px"`)}${url}</span>`;\nconst urlPill = (h) =>'
  );
}

const startIdx = kit.indexOf('// ------------------------------------------------------------------ channel art');
const endIdx = kit.indexOf('async function boot()');

if (startIdx === -1 || endIdx === -1) {
  console.error('Indices not found!', { startIdx, endIdx });
  process.exit(1);
}

const newChannelBlock = `// ------------------------------------------------------------------ channel art
const CHANNEL = {
	/** Profile picture: full clean icon on transparent background with circle-safe margins. */
	avatar(card, A) {
		const D = A.w;
		card.style.background = 'transparent';
		const pad = A.square ? D * 0.06 : D * 0.10;
		const s = D - pad * 2;
		const img = place($('img'), pad, pad, s, s);
		img.src = '../shared/img/icon-512.png';
		img.style.position = 'absolute';
		img.style.objectFit = 'contain';
		card.append(img);
	},
	watermark(card, A) {
		card.style.background = 'transparent';
		const img = place($('img'), 0, 0, A.w, A.h);
		img.src = '../shared/img/icon-512.png';
		img.style.position = 'absolute';
		img.style.objectFit = 'contain';
		card.append(img);
	},
	/** Instagram highlight cover: middle circle on Daylight ground. */
	highlight(card, A, H) {
		const W = A.w, Ht = A.h, D = 640;
		ground(card, W, Ht, { kind: 'paper', dotsAll: true, waves: false });
		const o = place($('div', 'orb', icon(H[2], \`style="width:\${D * 0.42}px;height:\${D * 0.42}px;stroke-width:1.5"\`)), (W - D) / 2, (Ht - D) / 2, D, D);
		o.style.position = 'absolute';
		o.style.setProperty('--d', D + 'px');
		card.append(o);
	},
	/** Banners and covers: dual brand lockup (CampBuddy + WPSimplified) + Tagline & concise chips */
	banner(card, A, C) {
		const W = A.w, H = A.h, [sx, sy, sw, sh] = A.safe;
		ground(card, W, H, { waveH: Math.max(26, H * 0.07), waveY: sy + sh + (H - sy - sh) * 0.15 - Math.max(26, H * 0.07) * 0.5 });
		const k = Math.min(sw / 1480, sh / 400);

		if (A.slim) {
			// Slim horizontal banner (e.g. LinkedIn 1128x191)
			const row = place($('div', 'abs'), sx, sy, sw, sh);
			row.style.cssText += \`display:flex;align-items:center;justify-content:center;gap:\${16 * k}px;height:\${sh}px\`;

			// 1. CampBuddy
			const cb = $('div', '', \`<div class="tile" style="padding:\${6 * k}px \${14 * k}px;border-radius:\${10 * k}px"><img src="../shared/img/logo-wordmark.png" style="height:\${32 * k}px"></div>\`);
			cb.style.cssText = \`display:flex;flex-direction:column;align-items:center;gap:\${4 * k}px;flex:none\`;
			cb.insertAdjacentHTML('beforeend', customUrlPill('campbuddy.club', 24 * k));

			const div1 = $('div'); div1.style.cssText = \`width:1px;height:\${sh * 0.6}px;background:var(--line);opacity:0.6;flex:none\`;

			// 2. WPSimplified
			const wps = $('div', '', \`<div class="tile" style="padding:\${6 * k}px \${14 * k}px;border-radius:\${10 * k}px"><img src="../shared/img/wpsimplified-logo.png" style="height:\${30 * k}px"></div>\`);
			wps.style.cssText = \`display:flex;flex-direction:column;align-items:center;gap:\${4 * k}px;flex:none\`;
			wps.insertAdjacentHTML('beforeend', customUrlPill('wpsimplified.in', 24 * k));

			const div2 = $('div'); div2.style.cssText = \`width:2px;height:\${sh * 0.7}px;background:var(--line);flex:none\`;

			// Tagline
			const right = $('div', '', \`<div class="title" style="font-size:\${30 * k}px">\${rich(C.tagline)}</div>\`);
			right.style.cssText = 'flex:none';

			row.append(cb, div1, wps, div2, right);
			card.append(row);
			return;
		}

		// Standard Banners (YouTube, Facebook, Twitter/X, GitHub, Discord)
		const row = place($('div', 'abs'), sx, sy, sw, sh);
		row.style.cssText += \`display:flex;align-items:center;gap:\${34 * k}px;height:\${sh}px\`;

		// Dual Brands Container on the left
		const brands = $('div');
		brands.style.cssText = \`display:flex;align-items:center;gap:\${18 * k}px;flex:none\`;

		const boxW = 200 * k;
		const boxH = 92 * k;
		const boxR = 16 * k;

		// 1. CampBuddy Column
		const cbCol = $('div', '', \`<div class="tile" style="width:\${boxW}px;height:\${boxH}px;border-radius:\${boxR}px;display:flex;align-items:center;justify-content:center"><img src="../shared/img/logo-wordmark.png" style="max-height:\${boxH * 0.68}px;max-width:\${boxW * 0.88}px"></div>\`);
		cbCol.style.cssText = \`display:flex;flex-direction:column;align-items:center;gap:\${10 * k}px\`;
		cbCol.insertAdjacentHTML('beforeend', customUrlPill('campbuddy.club', 36 * k));

		// Vertical divider between CampBuddy and WPSimplified
		const brandDiv = $('div');
		brandDiv.style.cssText = \`width:1px;height:\${boxH * 0.9}px;background:var(--line);opacity:0.6\`;

		// 2. WPSimplified Column
		const wpsCol = $('div', '', \`<div class="tile" style="width:\${boxW}px;height:\${boxH}px;border-radius:\${boxR}px;display:flex;align-items:center;justify-content:center"><img src="../shared/img/wpsimplified-logo.png" style="max-height:\${boxH * 0.62}px;max-width:\${boxW * 0.88}px"></div>\`);
		wpsCol.style.cssText = \`display:flex;flex-direction:column;align-items:center;gap:\${10 * k}px\`;
		wpsCol.insertAdjacentHTML('beforeend', customUrlPill('wpsimplified.in', 36 * k));

		brands.append(cbCol, brandDiv, wpsCol);

		// Main vertical divider separating brands from message
		const mainDiv = $('div');
		mainDiv.style.cssText = \`flex:none;width:2px;height:\${sh * 0.68}px;background:var(--line)\`;

		// Right Message Column: Kicker, Tagline, and concise chips
		const right = $('div', '', \`
			<div class="kicker" style="font-size:\${19 * k}px">\${waveMark()}\${C.kicker}</div>
			<div class="title" style="font-size:\${66 * k}px;margin-top:\${8 * k}px">\${rich(C.tagline)}</div>
			<div style="display:flex;gap:\${8 * k}px;margin-top:\${16 * k}px;flex-wrap:wrap">
				\${C.chips.map(([l, i]) => \`<span class="chip" style="height:\${38 * k}px;padding:0 \${14 * k}px;font-size:\${16 * k}px;gap:\${8 * k}px">\${icon(i, \`style="width:\${18 * k}px;height:\${18 * k}px"\`)}\${l}</span>\`).join('')}
			</div>
		\`);
		right.style.cssText = 'flex:1;min-width:0';

		row.append(brands, mainDiv, right);
		card.append(row);
	},
};\n\n`;

kit = kit.substring(0, startIdx) + newChannelBlock + kit.substring(endIdx);
fs.writeFileSync(kitPath, kit, 'utf8');
console.log('kit.js successfully updated!');

// Update render.mjs
const renderPath = 'E:/Sites/InHouse/campbuddy/marketing/usp-campaign/design/render.mjs';
let render = fs.readFileSync(renderPath, 'utf8').replace(/\r\n/g, '\n');
render = render.replace(
  "transparent: a.kind === 'watermark'",
  "transparent: a.kind === 'watermark' || a.kind === 'avatar'"
);
fs.writeFileSync(renderPath, render, 'utf8');
console.log('render.mjs updated!');
