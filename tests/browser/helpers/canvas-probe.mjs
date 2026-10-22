// Records what a page draws on its <canvas> elements (text with its real ink
// box, pictures, and canvases copied onto other canvases), so a test can ask
// of a finished post, people card or reel frame: does any text leave the
// picture or crowd its edge, is any text too small, do two lines of text run
// into each other, is any text covered by a picture drawn on top of it, is
// anything cut short with an ellipsis. Install CANVAS_HOOK before the page
// loads (Page.addScriptToEvaluateOnNewDocument), then call
// window.__canvasIssues(canvas, nominalWidth, nominalHeight, options).

export const CANVAS_HOOK = `(() => {
  if (window.__canvasOps) return;
  const OPS = new Map();
  window.__canvasOps = OPS;
  const P = CanvasRenderingContext2D.prototype;
  const list = (c) => { let a = OPS.get(c); if (!a) OPS.set(c, (a = [])); return a; };
  const isEl = (c) => typeof HTMLCanvasElement !== 'undefined' && c instanceof HTMLCanvasElement;
  const corners = (t, x0, y0, x1, y1) => {
    const pts = [[x0, y0], [x1, y0], [x0, y1], [x1, y1]].map(([x, y]) => [t.a * x + t.c * y + t.e, t.b * x + t.d * y + t.f]);
    const xs = pts.map((p) => p[0]), ys = pts.map((p) => p[1]);
    return [Math.min(...xs), Math.min(...ys), Math.max(...xs), Math.max(...ys)];
  };
  const det = (t) => Math.sqrt(Math.abs(t.a * t.d - t.b * t.c));
  const px = (font) => { const m = String(font).match(/([\\d.]+)px/); return m ? parseFloat(m[1]) : 10; };
  const opaque = (style) => typeof style === 'string' && !/rgba\\([^)]*,\\s*0?\\.\\d+\\)|hsla|transparent/.test(style) && !/^#[0-9a-f]{8}$/i.test(style);
  const clips = new WeakMap();
  const depth = (ctx) => (clips.get(ctx) || [0]).at(-1);
  const { fillText, strokeText, drawImage, clearRect, fillRect, save, restore, clip } = P;

  P.save = function () { const s = clips.get(this) || [0]; s.push(s.at(-1)); clips.set(this, s); return save.call(this); };
  P.restore = function () { const s = clips.get(this); if (s && s.length > 1) s.pop(); return restore.call(this); };
  P.clip = function (...a) { const s = clips.get(this) || [0]; s[s.length - 1] += 1; clips.set(this, s); return clip.apply(this, a); };

  const text = (ctx, how, value, x, y, maxWidth) => {
    try {
      const c = ctx.canvas;
      if (!isEl(c) || !String(value).trim()) return;
      const m = ctx.measureText(value);
      let l = x - m.actualBoundingBoxLeft, r = x + m.actualBoundingBoxRight;
      if (maxWidth !== undefined && m.width > maxWidth) { const k = maxWidth / m.width; l = x + (l - x) * k; r = x + (r - x) * k; }
      const t = ctx.getTransform();
      list(c).push({ k: 'text', how, rotated: Math.abs(t.b) > 1e-6 || Math.abs(t.c) > 1e-6, text: String(value), box: corners(t, l, y - m.actualBoundingBoxAscent, r, y + m.actualBoundingBoxDescent), size: px(ctx.font) * det(t), font: ctx.font, alpha: ctx.globalAlpha, clipped: depth(ctx) > 0, squeezed: maxWidth !== undefined && m.width > maxWidth * 1.001 ? +(maxWidth / m.width).toFixed(2) : 0 });
    } catch (e) { /* never break the page */ }
  };
  P.fillText = function (value, x, y, maxWidth) { text(this, 'fill', value, x, y, maxWidth); return maxWidth === undefined ? fillText.call(this, value, x, y) : fillText.call(this, value, x, y, maxWidth); };
  P.strokeText = function (value, x, y, maxWidth) { text(this, 'stroke', value, x, y, maxWidth); return maxWidth === undefined ? strokeText.call(this, value, x, y) : strokeText.call(this, value, x, y, maxWidth); };

  P.drawImage = function (src, ...a) {
    try {
      const c = this.canvas;
      if (isEl(c)) {
        const sw0 = src.width || src.naturalWidth || src.videoWidth || 0, sh0 = src.height || src.naturalHeight || src.videoHeight || 0;
        let sx = 0, sy = 0, sw = sw0, sh = sh0, dx, dy, dw, dh;
        if (a.length === 2) { [dx, dy] = a; dw = sw; dh = sh; } else if (a.length === 4) { [dx, dy, dw, dh] = a; } else { [sx, sy, sw, sh, dx, dy, dw, dh] = a; }
        const t = this.getTransform();
        const box = corners(t, dx, dy, dx + dw, dy + dh);
        const full = box[0] <= 1 && box[1] <= 1 && box[2] >= c.width - 1 && box[3] >= c.height - 1;
        // A blurred copy (the backdrop of a landscape post) is a picture, not text anyone reads.
        const blurred = /blur\\(/.test(String(this.filter || ''));
        if (isEl(src) && OPS.has(src) && sw && sh && !blurred) {
          if (full && this.globalAlpha >= 0.99) OPS.set(c, []);
          const kx = dw / sw, ky = dh / sh;
          for (const op of OPS.get(src)) {
            const b = op.box;
            const mapped = corners(t, dx + (b[0] - sx) * kx, dy + (b[1] - sy) * ky, dx + (b[2] - sx) * kx, dy + (b[3] - sy) * ky);
            if (mapped[2] < 0 || mapped[3] < 0 || mapped[0] > c.width || mapped[1] > c.height) continue;
            list(c).push({ ...op, box: mapped, size: op.size * Math.sqrt(Math.abs(kx * ky)) * det(t), alpha: op.alpha * this.globalAlpha, clipped: op.clipped || depth(this) > 0 });
          }
        } else {
          list(c).push({ k: 'image', box, full, src: String(src.currentSrc || src.src || src.tagName || '').slice(-70), alpha: this.globalAlpha, clipped: depth(this) > 0 });
        }
      }
    } catch (e) { /* never break the page */ }
    return drawImage.call(this, src, ...a);
  };

  const wipe = (ctx, x, y, w, h, style) => {
    try {
      const c = ctx.canvas;
      if (!isEl(c)) return;
      const b = corners(ctx.getTransform(), x, y, x + w, y + h);
      if (b[0] <= 1 && b[1] <= 1 && b[2] >= c.width - 1 && b[3] >= c.height - 1 && (style === null || (ctx.globalAlpha >= 0.99 && opaque(style)))) OPS.set(c, []);
    } catch (e) { /* never break the page */ }
  };
  P.clearRect = function (x, y, w, h) { wipe(this, x, y, w, h, null); return clearRect.call(this, x, y, w, h); };
  P.fillRect = function (x, y, w, h) { wipe(this, x, y, w, h, this.fillStyle); return fillRect.call(this, x, y, w, h); };

  for (const prop of ['width', 'height']) {
    const d = Object.getOwnPropertyDescriptor(HTMLCanvasElement.prototype, prop);
    Object.defineProperty(HTMLCanvasElement.prototype, prop, { configurable: true, enumerable: d.enumerable, get() { return d.get.call(this); }, set(v) { OPS.set(this, []); d.set.call(this, v); } });
  }

  // What is wrong with the picture now on canvas c, measured in the design's own pixels
  // (nominal size, e.g. 1080 x 1350, whatever scale it was drawn at).
  window.__canvasIssues = (c, W, H, o = {}) => {
    const minSize = o.minSize ?? 24, margin = o.margin ?? 40;
    const k = c.width / W;
    const ops = (OPS.get(c) || []).map((op, i) => ({ ...op, i, b: op.box.map((v) => v / k), s: op.size / k }));
    const texts = ops.filter((op) => op.k === 'text' && op.alpha > (o.minAlpha ?? 0.05));
    const out = [];
    const add = (kind, detail) => { if (out.length < 40 && !out.some((x) => x[0] === kind && x[1] === detail)) out.push([kind, detail]); };
    const short = (s) => s.replace(/\\s+/g, ' ').slice(0, 40);
    for (const t of texts) {
      const [x0, y0, x1, y1] = t.b;
      const edge = Math.min(x0, y0, W - x1, H - y1);
      if (edge < -1) add('off-canvas', short(t.text) + ' by ' + Math.round(-edge) + 'px');
      else if (edge < margin && !t.clipped) add('near-edge', short(t.text) + ' ' + Math.round(edge) + 'px from edge');
      if (t.s < minSize - 0.5) add('small', short(t.text) + ' ' + Math.round(t.s) + 'px');
      if (/…|\\.\\.\\.\\s*$/.test(t.text)) add('ellipsis', short(t.text));
      if (t.squeezed && t.squeezed < 0.9) add('squeezed', short(t.text) + ' to ' + Math.round(t.squeezed * 100) + '%');
    }
    for (let i = 0; i < texts.length; i++) for (let j = i + 1; j < texts.length; j++) {
      const A = texts[i], B = texts[j];
      const same = A.text === B.text && A.b.every((v, n) => Math.abs(v - B.b[n]) < 8);
      // A tilted line's upright box is bigger than its ink: tilted text is left to the eye (screenshots).
      if (same || A.rotated || B.rotated) continue;
      const w = Math.min(A.b[2], B.b[2]) - Math.max(A.b[0], B.b[0]);
      const h = Math.min(A.b[3], B.b[3]) - Math.max(A.b[1], B.b[1]);
      if (w <= 2 || h <= 2) continue;
      const area = (b) => (b[2] - b[0]) * (b[3] - b[1]);
      const share = (w * h) / Math.min(area(A.b), area(B.b));
      if (share > 0.12) add('text-on-text', '"' + short(A.text) + '" x "' + short(B.text) + '" ' + Math.round(share * 100) + '%');
    }
    for (const t of texts) for (const im of ops) {
      if (im.k !== 'image' || im.i < t.i || im.full || im.alpha < 0.5) continue;
      const w = Math.min(t.b[2], im.b[2]) - Math.max(t.b[0], im.b[0]);
      const h = Math.min(t.b[3], im.b[3]) - Math.max(t.b[1], im.b[1]);
      if (w > 2 && h > 2 && (w * h) / ((t.b[2] - t.b[0]) * (t.b[3] - t.b[1])) > 0.2) add('covered-by-picture', short(t.text) + ' under ' + im.src);
    }
    return { issues: out, texts: texts.length, pictures: ops.filter((op) => op.k === 'image').length };
  };
})();`;
