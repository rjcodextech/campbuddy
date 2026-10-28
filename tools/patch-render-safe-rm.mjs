import fs from 'node:fs';

const p = 'E:/Sites/InHouse/campbuddy/marketing/usp-campaign/reels/render.mjs';
let code = fs.readFileSync(p, 'utf8');

// Replace fs.rmSync(silent) with safe removal
const oldRm = 'fs.rmSync(silent);';
const newRm = `try {
			await new Promise((r) => setTimeout(r, 500));
			if (fs.existsSync(silent)) fs.unlinkSync(silent);
		} catch (e) {
			// ignore Windows file lock on temp video
		}`;

if (code.includes(oldRm)) {
  code = code.replace(oldRm, newRm);
}

// Add -flags +global_header to ffmpeg silent encode to avoid NAL unit size errors when copying
const oldFf = "'-pix_fmt', 'yuv420p', '-profile:v', 'high', '-r', String(FPS), silent";
const newFf = "'-pix_fmt', 'yuv420p', '-profile:v', 'high', '-flags', '+global_header', '-r', String(FPS), silent";

if (code.includes(oldFf)) {
  code = code.replace(oldFf, newFf);
}

fs.writeFileSync(p, code);
console.log('Successfully patched render.mjs for Windows safe file removal and global headers');
