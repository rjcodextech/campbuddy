// Script to inject dynamic animated background effects into reel.css and engine.js
import fs from 'node:fs';
import path from 'node:path';

const REELS_DIR = 'E:/Sites/InHouse/campbuddy/marketing/usp-campaign/reels';
const cssPath = path.join(REELS_DIR, 'reel.css');
const jsPath = path.join(REELS_DIR, 'engine.js');

// 1. Update reel.css
let css = fs.readFileSync(cssPath, 'utf8');
const particleCss = `
/* Animated background particles & ambient glows */
.particles { position: absolute; inset: 0; pointer-events: none; overflow: hidden; z-index: 1; }
.particle { position: absolute; width: 6px; height: 6px; border-radius: 50%; background: #ea580c; filter: blur(0.5px); box-shadow: 0 0 12px rgba(234,88,12,0.7); pointer-events: none; will-change: transform, opacity; }
.particle--gold { background: #f59e0b; box-shadow: 0 0 10px rgba(245,158,11,0.6); }
.particle--glow { width: 10px; height: 10px; background: #fb923c; filter: blur(1.5px); box-shadow: 0 0 16px rgba(251,146,60,0.8); }
.glow-top, .glow-bottom, .glow-mid { will-change: transform; }
`;

if (!css.includes('.particles {')) {
  css = css.replace('.glow { position: absolute; border-radius: 50%; filter: blur(90px); }',
    `.glow { position: absolute; border-radius: 50%; filter: blur(90px); }\n${particleCss}`);
  fs.writeFileSync(cssPath, css);
  console.log('Updated reel.css with animated particle and glow rules');
}

// 2. Update engine.js
let js = fs.readFileSync(jsPath, 'utf8');

// Replace grounds(stage) with enriched grounds containing particles and multiple glow elements
const oldGroundsStart = js.indexOf('function grounds(stage) {');
const oldGroundsEnd = js.indexOf('// ------------------------------------------------------------------ boot', oldGroundsStart);

if (oldGroundsStart >= 0 && oldGroundsEnd >= 0) {
  const newGroundsCode = `function grounds(stage) {
	const make = (kind, inner) => { const g = $('div', \`ground ground--\${kind}\`, inner); g.dataset.kind = kind; stage.append(g); return g; };
	const wavesSvg = (colors, ops) => \`<svg class="waves" viewBox="0 0 1480 360">\${colors.map((c, i) => \`<path data-wave="\${i}" stroke="\${c}" stroke-width="\${5 - i}" opacity="\${ops[i]}"/>\`).join('')}</svg>\`;
	const particlesHtml = (n = 24) => {
		let s = '<div class="particles">';
		for (let i = 0; i < n; i++) {
			const cls = i % 3 === 0 ? 'particle particle--gold' : (i % 5 === 0 ? 'particle particle--glow' : 'particle');
			s += \`<div class="\${cls}" data-particle="\${i}"></div>\`;
		}
		return s + '</div>';
	};
	const G = {
		paper: make('paper', \`<div class="glow glow-top" style="width:840px;height:700px;right:-260px;top:-140px;background:rgba(245,158,11,.28)"></div>
			<div class="glow glow-bottom" style="width:900px;height:780px;left:-340px;bottom:100px;background:rgba(255,77,45,.18)"></div>
			<div class="glow glow-mid" style="width:550px;height:550px;right:20px;top:740px;background:rgba(251,146,60,.12)"></div>
			<div class="dots" style="left:480px;right:0;top:0;bottom:0;color:rgba(15,23,42,.08);-webkit-mask:linear-gradient(90deg,transparent,#000 40%)"></div>
			\${particlesHtml(24)}
			\${wavesSvg(['#ff4d2d', '#f59e0b', '#ff4d2d', '#f59e0b'], [0.36, 0.42, 0.22, 0.26])}\`),
		ink: make('ink', \`<div class="glow glow-top" style="width:800px;height:680px;right:-240px;top:-120px;background:rgba(245,158,11,.28)"></div>
			<div class="glow glow-bottom" style="width:880px;height:740px;left:-320px;bottom:60px;background:rgba(255,77,45,.25)"></div>
			<div class="dots" style="left:480px;right:0;top:0;bottom:0;color:rgba(15,23,42,.08);-webkit-mask:linear-gradient(90deg,transparent,#000 40%)"></div>
			\${particlesHtml(24)}
			\${wavesSvg(['#ff4d2d', '#f59e0b', '#ff4d2d', '#f59e0b'], [0.36, 0.42, 0.22, 0.26])}\`),
		gradient: make('gradient', \`<div class="glow glow-top" style="width:840px;height:700px;right:-260px;top:-160px;background:rgba(245,158,11,.28)"></div>
			<div class="glow glow-bottom" style="width:900px;height:780px;left:-340px;bottom:100px;background:rgba(255,77,45,.18)"></div>
			<div class="dots" style="left:480px;right:0;top:0;bottom:0;color:rgba(15,23,42,.08);-webkit-mask:linear-gradient(90deg,transparent,#000 40%)"></div>
			\${particlesHtml(24)}
			\${wavesSvg(['#ff4d2d', '#f59e0b', '#ff4d2d', '#f59e0b'], [0.36, 0.42, 0.22, 0.26])}\`),
		navy: make('navy', \`<div class="glow glow-top" style="width:840px;height:700px;right:-240px;top:-120px;background:rgba(245,158,11,.28)"></div>
			<div class="glow glow-bottom" style="width:900px;height:780px;left:-320px;bottom:60px;background:rgba(255,77,45,.18)"></div>
			<div class="dots" style="left:480px;right:0;top:0;bottom:0;color:rgba(15,23,42,.08);-webkit-mask:linear-gradient(90deg,transparent,#000 40%)"></div>
			\${particlesHtml(24)}
			\${wavesSvg(['#ff4d2d', '#f59e0b', '#ff4d2d', '#f59e0b'], [0.36, 0.42, 0.22, 0.26])}\`),
	};
	return G;
}

function wavePath(phase, amp, y, len = 1480, period = 370) {
	let d = '';
	for (let x = 0; x <= len; x += 20) d += (x ? ' L' : 'M') + x + ' ' + (y + Math.sin((x / period) * Math.PI * 2 + phase) * amp).toFixed(1);
	return d;
}

`;
  js = js.slice(0, oldGroundsStart) + newGroundsCode + js.slice(oldGroundsEnd);
  console.log('Updated grounds(stage) with rich particle specks and ambient glows');
}

// Now update seek(t) to animate the orbs, particles, dots drift, and waves
const seekWaveTarget = `g.querySelectorAll('[data-wave]').forEach((p) => { const i = +p.dataset.wave; p.setAttribute('d', wavePath(t * (0.55 + i * 0.12) + i * 1.3, 16 + i * 5, 200 + i * 34)); });`;
const seekAnimationCode = `g.querySelectorAll('[data-wave]').forEach((p) => { const i = +p.dataset.wave; p.setAttribute('d', wavePath(t * (0.55 + i * 0.12) + i * 1.3, 16 + i * 5, 200 + i * 34)); });
			// Living animated background motion
			const gt = g.querySelector('.glow-top');
			if (gt) gt.style.transform = \`translate(\${Math.sin(t * 0.7) * 36}px, \${Math.cos(t * 0.5) * 28}px) scale(\${1 + 0.08 * Math.sin(t * 1.1)})\`;
			const gb = g.querySelector('.glow-bottom');
			if (gb) gb.style.transform = \`translate(\${Math.cos(t * 0.6) * 40}px, \${Math.sin(t * 0.8) * 32}px) scale(\${1 + 0.06 * Math.cos(t * 0.9)})\`;
			const gm = g.querySelector('.glow-mid');
			if (gm) gm.style.transform = \`translate(\${Math.sin(t * 0.4) * 25}px, \${Math.cos(t * 0.6) * 35}px) scale(\${1 + 0.1 * Math.sin(t * 0.7)})\`;
			// Floating rising embers / sparkle particles
			g.querySelectorAll('[data-particle]').forEach((pt) => {
				const idx = +pt.dataset.particle;
				const speed = 40 + (idx % 6) * 16;
				const seed = idx * 1.37;
				const y = ((1920 - (t * speed + seed * 220) % 2080) + 2080) % 2080 - 60;
				const x = (idx * 43 + 40) % 1000 + Math.sin(t * 1.3 + seed) * 25;
				const op = (0.25 + 0.45 * Math.sin(t * 2.5 + seed)) * (y > 80 && y < 1820 ? 1 : 0);
				pt.style.transform = \`translate(\${x.toFixed(1)}px, \${y.toFixed(1)}px)\`;
				pt.style.opacity = Math.max(0, op).toFixed(2);
			});
			// Subtle dot matrix drift
			const dt = g.querySelector('.dots');
			if (dt) dt.style.backgroundPosition = \`\${(t * 5) % 26}px \${(t * 3.5) % 26}px\`;`;

if (js.includes(seekWaveTarget)) {
  js = js.replace(seekWaveTarget, seekAnimationCode);
  fs.writeFileSync(jsPath, js);
  console.log('Updated seek(t) with dynamic animated glows, floating embers, and drifting texture');
} else {
  console.warn('Could not find seekWaveTarget in engine.js');
}
