// Update reels render.mjs to support --4k native 2160x3840 rendering
import fs from 'node:fs';
import path from 'node:path';

const renderPath = 'E:/Sites/InHouse/campbuddy/marketing/usp-campaign/reels/render.mjs';
let code = fs.readFileSync(renderPath, 'utf8');

// Replace deviceScaleFactor and shot function with 4K support
const oldSetup = `const srv = await serve();
const base = \`http://127.0.0.1:\${srv.address().port}/usp-campaign/reels/reel.html\`;
const c = await launchChrome();
await c.send('Emulation.setDeviceMetricsOverride', { width: 1080, height: 1920, deviceScaleFactor: Number(opt('scale', 1)), mobile: false });
fs.mkdirSync(OUT, { recursive: true });
fs.mkdirSync(PREVIEW, { recursive: true });

const shot = async (t, q = 95) => {
	await c.evaluate(\`seek(\${t}),1\`);
	return Buffer.from((await c.send('Page.captureScreenshot', { format: 'jpeg', quality: q, clip: { x: 0, y: 0, width: 1080, height: 1920, scale: 1 } })).result.data, 'base64');
};`;

const newSetup = `const is4K = has('4k') || opt('scale') === '2';
const SCALE = is4K ? 2 : Number(opt('scale', 1));

const srv = await serve();
const base = \`http://127.0.0.1:\${srv.address().port}/usp-campaign/reels/reel.html\`;
const c = await launchChrome();
await c.send('Emulation.setDeviceMetricsOverride', { width: 1080, height: 1920, deviceScaleFactor: SCALE, mobile: false });
fs.mkdirSync(OUT, { recursive: true });
fs.mkdirSync(PREVIEW, { recursive: true });

const shot = async (t, q = 95) => {
	await c.evaluate(\`seek(\${t}),1\`);
	return Buffer.from((await c.send('Page.captureScreenshot', { format: 'jpeg', quality: q, clip: { x: 0, y: 0, width: 1080, height: 1920, scale: SCALE } })).result.data, 'base64');
};`;

code = code.replace(oldSetup, newSetup);

// Replace ffmpeg spawn settings with 4K bitrate
const oldFfmpeg = `const silent = path.join(PREVIEW, \`\${id}-video.mp4\`);
		const ff = spawn('ffmpeg', ['-y', '-v', 'error', '-f', 'image2pipe', '-framerate', String(FPS), '-c:v', 'mjpeg', '-i', '-',
			'-c:v', 'libx264', '-preset', 'slow', '-b:v', '9M', '-maxrate', '12M', '-bufsize', '18M', '-pix_fmt', 'yuv420p', '-profile:v', 'high', '-r', String(FPS), silent], { stdio: ['pipe', 'inherit', 'inherit'] });`;

const newFfmpeg = `const silent = path.join(PREVIEW, \`\${id}-video.mp4\`);
		const bitrate = is4K ? '24M' : '9M';
		const maxrate = is4K ? '32M' : '12M';
		const bufsize = is4K ? '48M' : '18M';
		const ff = spawn('ffmpeg', ['-y', '-v', 'error', '-f', 'image2pipe', '-framerate', String(FPS), '-c:v', 'mjpeg', '-i', '-',
			'-c:v', 'libx264', '-preset', 'slow', '-b:v', bitrate, '-maxrate', maxrate, '-bufsize', bufsize, '-pix_fmt', 'yuv420p', '-profile:v', 'high', '-r', String(FPS), silent], { stdio: ['pipe', 'inherit', 'inherit'] });`;

code = code.replace(oldFfmpeg, newFfmpeg);

fs.writeFileSync(renderPath, code);
console.log('Successfully updated reels render.mjs with native 4K support');
