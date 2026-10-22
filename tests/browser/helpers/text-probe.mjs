// Runs in the page (Runtime.evaluate): checks how the words on a screen read —
// text cut off by its box, lines set too tight, type too small, words running
// into each other or into a picture, text pressed against the edge of its card,
// columns too narrow to read, low contrast, pictures that did not load or are
// stretched, and anything that sticks out sideways. Returns plain data; the
// caller decides what fails. Used by tests/browser/text-audit.mjs.

export const TEXT_PROBE = `(() => {
  const vw = innerWidth;
  const MAX = 15;
  const seen = new Set();
  const push = (list, key, value) => { if (seen.has(list.name + key) || list.items.length >= MAX) return; seen.add(list.name + key); list.items.push(value); };
  const make = (name) => ({ name, items: [] });
  const L = Object.fromEntries(['clipped', 'ellipsis', 'tight', 'tiny', 'overlap', 'textOnImage', 'edge', 'cramped', 'contrast', 'images', 'overflow', 'fonts'].map((n) => [n, make(n)]));

  const css = (el) => getComputedStyle(el);
  const hiddenUp = (el) => el.closest('[hidden], template, dialog:not([open]), [aria-hidden="true"], .sr-only, .visually-hidden, details:not([open]) > :not(summary)');
  const shown = (el) => {
    if (!el || hiddenUp(el)) return false;
    const r = el.getBoundingClientRect();
    if (r.width < 2 || r.height < 2) return false;
    for (let a = el; a && a !== document.documentElement; a = a.parentElement) {
      const s = css(a);
      if (s.display === 'none' || s.visibility === 'hidden' || s.visibility === 'collapse' || Number(s.opacity) < 0.05) return false;
      if (s.clipPath && s.clipPath !== 'none' && /inset\\(50%|circle\\(0/.test(s.clipPath)) return false;
      if (s.clip && s.clip !== 'auto' && /rect\\(0(px)?,? 0(px)?,? 0(px)?,? 0/.test(s.clip)) return false;
    }
    return true;
  };
  const name = (el) => {
    const cls = typeof el.className === 'string' ? el.className.trim().split(/\\s+/).filter(Boolean).slice(0, 2).join('.') : '';
    return el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') + (cls ? '.' + cls : '');
  };
  const path = (el) => { const parts = []; for (let a = el; a && a !== document.body && parts.length < 3; a = a.parentElement) parts.unshift(name(a)); return parts.join(' > '); };
  const words = (s) => s.trim().replace(/\\s+/g, ' ').slice(0, 50);
  const fixedUp = (el) => { for (let a = el; a && a !== document.body; a = a.parentElement) { const p = css(a).position; if (p === 'fixed' || p === 'sticky') return a; } return null; };

  // ---- every visible run of text, with its line boxes
  const runs = [];
  const tw = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, { acceptNode: (n) => (n.textContent.trim() ? 1 : 2) });
  while (tw.nextNode()) {
    const node = tw.currentNode;
    const el = node.parentElement;
    if (!el || el.closest('script, style, noscript, svg, textarea, select, option, canvas') || !shown(el)) continue;
    const range = document.createRange();
    range.selectNodeContents(node);
    const rects = [...range.getClientRects()].filter((r) => r.width > 1 && r.height > 1);
    if (!rects.length) continue;
    // What of it can be seen: cut to every ancestor that clips or scrolls. Text scrolled out of a
    // scroller, or hidden by a deliberate overflow box, is not on screen and is left out.
    let seen = rects.map((r) => ({ left: r.left, top: r.top, right: r.right, bottom: r.bottom, width: r.width, height: r.height }));
    for (let a = el; a && a !== document.documentElement && seen.length; a = a.parentElement) {
      const as = css(a);
      const cx = as.overflowX !== 'visible', cy = as.overflowY !== 'visible';
      if (!cx && !cy) continue;
      const b = a.getBoundingClientRect();
      seen = seen.map((r) => {
        const left = cx ? Math.max(r.left, b.left) : r.left, right = cx ? Math.min(r.right, b.right) : r.right;
        const top = cy ? Math.max(r.top, b.top) : r.top, bottom = cy ? Math.min(r.bottom, b.bottom) : r.bottom;
        return { left, top, right, bottom, width: right - left, height: bottom - top };
      }).filter((r) => r.width > 1 && r.height > 1);
    }
    if (!seen.length) continue;
    runs.push({ node, el, rects, seen, text: node.textContent });
  }

  const blockOf = (el) => { for (let a = el; a && a !== document.body; a = a.parentElement) { const d = css(a).display; if (!/^inline/.test(d) || d === 'inline-block' || d === 'inline-flex' || d === 'inline-grid') return a; } return document.body; };

  for (const run of runs) {
    const s = css(run.el);
    const fs = parseFloat(s.fontSize);
    const label = path(run.el) + ' "' + words(run.text) + '"';

    // type too small to read on a phone
    if (fs < 12 && run.text.trim().length > 1) push(L.tiny, name(run.el) + fs, label + ' ' + fs + 'px');

    // line height: text that wraps onto more than one line, set too tight
    const tops = new Set(run.rects.map((r) => Math.round(r.top)));
    const lh = s.lineHeight === 'normal' ? fs * 1.2 : parseFloat(s.lineHeight);
    const ratio = lh / fs;
    const block = blockOf(run.el);
    const blockLines = Math.round(block.getBoundingClientRect().height / lh);
    if (tops.size > 1 || (blockLines > 1 && block === run.el)) {
      // Baloo 2 (headings) has tall built-in line spacing: 1.1 reads like ~1.3 in Poppins.
      const display = /Baloo/i.test(s.fontFamily);
      const min = fs >= 28 ? 1.05 : fs >= 20 || display ? 1.1 : 1.3;
      if (ratio < min - 0.001) push(L.tight, name(run.el) + ratio.toFixed(2), label + ' ' + fs + 'px / lh ' + ratio.toFixed(2));
    }

    // a narrow column: many lines with only a word or two on each
    if (tops.size >= 3) {
      const perLine = run.text.trim().length / tops.size;
      if (perLine < 11) push(L.cramped, name(block), label + ' ' + tops.size + ' lines, ~' + Math.round(perLine) + ' chars/line, box ' + Math.round(block.getBoundingClientRect().width) + 'px');
    }

    // cut off by an ancestor that clips (scrollers are left alone: they scroll on purpose)
    for (let a = run.el; a && a !== document.body; a = a.parentElement) {
      const as = css(a);
      if (/(auto|scroll)/.test(as.overflowX + as.overflowY)) break; // a scroller: its content scrolls into view
      if (as.textOverflow === 'ellipsis' || as.webkitLineClamp !== 'none') break; // reported as "ellipsis"
      const clipX = /(hidden|clip)/.test(as.overflowX), clipY = /(hidden|clip)/.test(as.overflowY);
      if (!clipX && !clipY) continue;
      const box = a.getBoundingClientRect();
      const out = run.rects.some((r) => (clipX && (r.right > box.right + 1.5 || r.left < box.left - 1.5)) || (clipY && (r.bottom > box.bottom + 1.5 || r.top < box.top - 1.5)));
      if (out) { push(L.clipped, name(a) + words(run.text), label + ' clipped by ' + name(a)); break; }
      if (/(auto|scroll)/.test(as.overflowX + as.overflowY)) break;
    }
  }

  // ellipsis / line-clamp actually cutting words
  for (const el of document.querySelectorAll('body *')) {
    const s = css(el);
    if (s.textOverflow !== 'ellipsis' && s.webkitLineClamp === 'none') continue;
    if (!shown(el)) continue;
    if (el.scrollWidth > el.clientWidth + 1 || (s.webkitLineClamp !== 'none' && el.scrollHeight > el.clientHeight + 1)) push(L.ellipsis, name(el) + words(el.textContent), path(el) + ' "' + words(el.textContent) + '"');
  }

  // words running into other words (different elements, not inside a fixed bar)
  const boxes = [];
  for (const run of runs) for (const r of run.seen) boxes.push({ r, run });
  boxes.sort((a, b) => a.r.top - b.r.top);
  for (let i = 0; i < boxes.length; i++) {
    const A = boxes[i];
    for (let j = i + 1; j < boxes.length && boxes[j].r.top < A.r.bottom; j++) {
      const B = boxes[j];
      if (A.run.el === B.run.el || A.run === B.run) continue;
      const w = Math.min(A.r.right, B.r.right) - Math.max(A.r.left, B.r.left);
      const h = Math.min(A.r.bottom, B.r.bottom) - Math.max(A.r.top, B.r.top);
      if (w <= 1 || h <= 1) continue;
      // inline neighbours on one line touch by a pixel or so; real collisions cover a share of the smaller box
      const share = (w * h) / Math.min(A.r.width * A.r.height, B.r.width * B.r.height);
      if (share < 0.25 || w < 3) continue;
      if (fixedUp(A.run.el) !== fixedUp(B.run.el)) continue;
      push(L.overlap, name(A.run.el) + name(B.run.el), '"' + words(A.run.text) + '" (' + path(A.run.el) + ') ON "' + words(B.run.text) + '" (' + path(B.run.el) + ')');
    }
  }

  // words printed over a photo/logo they do not belong to
  const pics = [...document.querySelectorAll('img, picture, video, canvas')].filter((p) => shown(p) && p.getBoundingClientRect().width > 28);
  for (const p of pics) {
    const pr = p.getBoundingClientRect();
    for (const run of runs) {
      if (fixedUp(run.el) !== fixedUp(p)) continue;
      if (run.el.contains(p)) continue;
      for (const r of run.seen) {
        const w = Math.min(r.right, pr.right) - Math.max(r.left, pr.left);
        const h = Math.min(r.bottom, pr.bottom) - Math.max(r.top, pr.top);
        if (w > 4 && h > 4 && (w * h) / (r.width * r.height) > 0.3) { push(L.textOnImage, name(p) + words(run.text), '"' + words(run.text) + '" (' + path(run.el) + ') over ' + path(p)); break; }
      }
    }
  }

  // text pressed against the edge of the card / button / pill it sits in
  const surface = (el) => {
    for (let a = el; a && a !== document.body; a = a.parentElement) {
      const s = css(a);
      const bg = s.backgroundColor !== 'rgba(0, 0, 0, 0)' && s.backgroundColor !== 'transparent';
      const border = parseFloat(s.borderLeftWidth) > 0 && s.borderLeftStyle !== 'none' && !/rgba\\(0, 0, 0, 0\\)/.test(s.borderLeftColor);
      if (bg || border || s.backgroundImage !== 'none') return a;
    }
    return null;
  };
  for (const run of runs) {
    const box = surface(run.el);
    if (!box || box === document.body || box === document.documentElement) continue;
    const b = box.getBoundingClientRect();
    if (b.width >= vw - 2 && b.height > 200) continue; // a page-wide band, not a card
    const s = css(run.el);
    if (parseFloat(s.fontSize) < 9) continue;
    const left = Math.min(...run.rects.map((r) => r.left)) - b.left;
    const right = b.right - Math.max(...run.rects.map((r) => r.right));
    const top = Math.min(...run.rects.map((r) => r.top)) - b.top;
    const bottom = b.bottom - Math.max(...run.rects.map((r) => r.bottom));
    const tightX = Math.min(left, right) < 6, tightY = Math.min(top, bottom) < 1;
    if ((tightX && (left < 6 && right < 6 ? true : left < 4 || right < 4)) || tightY) push(L.edge, name(box) + words(run.text), '"' + words(run.text) + '" in ' + path(box) + ' gaps L' + Math.round(left) + ' R' + Math.round(right) + ' T' + Math.round(top) + ' B' + Math.round(bottom));
  }

  // contrast (WCAG AA) where the background is a plain colour
  const rgba = (c) => { const m = c.match(/rgba?\\(([^)]+)\\)/); if (!m) return null; const p = m[1].split(/[ ,\\/]+/).filter(Boolean).map(Number); return [p[0], p[1], p[2], p.length > 3 ? p[3] : 1]; };
  const lum = ([r, g, b]) => { const f = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; }; return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b); };
  const blend = (top, under) => { const a = top[3]; return [top[0] * a + under[0] * (1 - a), top[1] * a + under[1] * (1 - a), top[2] * a + under[2] * (1 - a), 1]; };
  for (const run of runs) {
    const s = css(run.el);
    let fg = rgba(s.color); if (!fg) continue;
    const layers = []; let unknown = false;
    for (let a = run.el; a; a = a.parentElement) {
      const as = css(a);
      if (as.backgroundImage !== 'none') { unknown = true; break; }
      const c = rgba(as.backgroundColor); if (c && c[3] > 0) { layers.push(c); if (c[3] >= 1) break; }
    }
    if (unknown) continue;
    let bg = [255, 255, 255, 1];
    for (let i = layers.length - 1; i >= 0; i--) bg = blend(layers[i], bg);
    let op = 1; for (let a = run.el; a; a = a.parentElement) op *= Number(css(a).opacity);
    fg = blend([fg[0], fg[1], fg[2], fg[3] * op], bg);
    const L1 = lum(fg), L2 = lum(bg);
    const ratio = (Math.max(L1, L2) + 0.05) / (Math.min(L1, L2) + 0.05);
    const fs = parseFloat(s.fontSize), bold = Number(s.fontWeight) >= 700;
    const need = fs >= 24 || (bold && fs >= 18.66) ? 3 : 4.5;
    if (ratio < need - 0.05 && !run.el.closest('[disabled], .is-disabled, [aria-disabled="true"]')) push(L.contrast, name(run.el) + ratio.toFixed(1), path(run.el) + ' "' + words(run.text) + '" ' + ratio.toFixed(2) + ':1 (needs ' + need + ')');
  }

  // pictures: broken, or squashed out of shape
  for (const img of document.querySelectorAll('img')) {
    if (!shown(img)) continue;
    const r = img.getBoundingClientRect();
    if (img.complete && img.naturalWidth === 0 && img.getAttribute('src')) { push(L.images, img.src, 'broken ' + path(img) + ' ' + img.getAttribute('src').slice(0, 80)); continue; }
    if (!img.naturalWidth || r.width < 12) continue;
    const fit = css(img).objectFit;
    if (fit === 'fill' || !fit) {
      const st = css(img);
      const cw = r.width - parseFloat(st.paddingLeft) - parseFloat(st.paddingRight) - parseFloat(st.borderLeftWidth) - parseFloat(st.borderRightWidth);
      const ch = r.height - parseFloat(st.paddingTop) - parseFloat(st.paddingBottom) - parseFloat(st.borderTopWidth) - parseFloat(st.borderBottomWidth);
      const want = img.naturalWidth / img.naturalHeight, got = cw / Math.max(1, ch);
      if (Math.abs(want - got) / want > 0.06) push(L.images, img.src, 'stretched ' + path(img) + ' natural ' + img.naturalWidth + 'x' + img.naturalHeight + ' shown ' + Math.round(r.width) + 'x' + Math.round(r.height));
    }
  }

  // sideways overflow (outside horizontal scrollers)
  const scrollers = [...document.querySelectorAll('*')].filter((el) => /(auto|scroll|hidden|clip)/.test(css(el).overflowX) && el !== document.body && el !== document.documentElement);
  for (const el of document.querySelectorAll('body *')) {
    if (!shown(el) || scrollers.some((s) => s !== el && s.contains(el))) continue;
    const r = el.getBoundingClientRect();
    const drawer = fixedUp(el);
    if (drawer && css(drawer).position === 'fixed') { const d = drawer.getBoundingClientRect(); if (d.right <= 1 || d.left >= vw - 1) continue; }
    if (r.right > vw + 1 || r.left < -1) push(L.overflow, name(el), path(el) + ' x ' + Math.round(r.left) + '..' + Math.round(r.right));
  }

  // web fonts that did not load
  for (const f of document.fonts) if (f.status === 'error') push(L.fonts, f.family + f.weight, f.family + ' ' + f.weight + ' failed');

  const out = { title: document.title, width: vw, scrollWidth: document.documentElement.scrollWidth, height: document.documentElement.scrollHeight };
  for (const [k, v] of Object.entries(L)) out[k] = v.items;
  out.errors = window.__auditErrors || [];
  return out;
})()`;
