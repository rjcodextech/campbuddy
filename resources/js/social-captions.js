// Social media studio: one caption per network (Instagram, LinkedIn, X) for the posts, people cards
// and reels — put together like App\Support\SocialCaptions (the server writes the posts' and people's;
// this file the browser-made ones: a day's talks, a spotlight, a reel's group), and the caption box
// the studio shows them in: a button per network, the text, its count against the network's limit.
//
//  - Instagram: up to 2,200 characters and 5 hashtags in all. A link doesn't open there, so it points
//    to the QR on the card (or at the end of the reel) and campbuddy.club, without https://.
//  - LinkedIn: up to 3,000 characters, the hook first, the link, 5 hashtags.
//  - X: 280 counted the way X counts (a link 23, an emoji 2); the hook and the link always, then up to
//    3 hashtags, the list, the body and the tagline while they fit.

export const NETWORKS = [['instagram', 'Instagram'], ['linkedin', 'LinkedIn'], ['x', 'X']];
export const LIMITS = { instagram: 2200, linkedin: 3000, x: 280 };
export const MAX_TAGS = { instagram: 5, linkedin: 5, x: 3 };
const HINTS = {
  instagram: "Links don't open in an Instagram caption: it points to the QR.",
  linkedin: 'About the first 140 characters show before “see more”.',
  x: 'Counted the way X counts: a link is 23, an emoji 2.',
};

const X_LINK = 23;
const URL_RE = /(?:https?:\/\/\S+|\b[a-z0-9-]+(?:\.[a-z0-9-]+)*\.[a-z]{2,}(?:\/\S*)?)/giu;
const GENERIC = ['#wordcamp', '#wordpress'];

/** A text's length the way X counts it: a link (with or without https://) 23, most Latin letters 1, the rest (emoji, CJK…) 2. */
export function xLength(text) {
  let n = 0;
  for (const ch of String(text ?? '').replace(URL_RE, 'x'.repeat(X_LINK))) {
    const c = ch.codePointAt(0);
    n += (c <= 4351 || (c >= 8192 && c <= 8205) || (c >= 8208 && c <= 8223) || (c >= 8242 && c <= 8247)) ? 1 : 2;
  }
  return n;
}

/** Characters as Instagram and LinkedIn count them (one per letter, emoji included). */
const charLength = (text) => [...String(text ?? '')].length;

/** "#WCRajasthan" from "#WordCamp #WordPress #WCRajasthan #WCRajasthan2026", or null. */
export function ownTag(hashtags) {
  return String(hashtags ?? '').trim().split(/\s+/).find((t) => t.startsWith('#') && t.length > 1 && !GENERIC.includes(t.toLowerCase())) ?? null;
}

/** The hashtags a caption may end with, in the order shown. */
export function captionTags(hashtags) {
  return [...new Set([ownTag(hashtags), '#WordCamp', '#WordPress', '#CampBuddy', '#WPSimplified'].filter(Boolean))];
}

/** How many hashtags a text has (Instagram counts the ones in the words too). */
export function tagCount(text) {
  return (String(text ?? '').match(/(?<![\p{L}\p{N}_&])#[\p{L}\p{N}_]+/gu) ?? []).length;
}

const escape = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
const has = (text, tag) => new RegExp(`(?<![\\p{L}\\p{N}_&])${escape(tag)}(?![\\p{L}\\p{N}_])`, 'iu').test(text);
const byPriority = (tags) => [...tags.filter((t) => !GENERIC.includes(t.toLowerCase())), ...tags.filter((t) => GENERIC.includes(t.toLowerCase()))];
const shown = (tags, chosen) => tags.filter((t) => chosen.includes(t));
const join = (parts) => parts.filter((p) => typeof p === 'string' && p.trim() !== '').join('\n\n');
const clean = (t) => (typeof t === 'string' && t.trim() !== '' ? t.trim() : null);

function tagLine(tags, words, max) {
  const room = Math.max(0, max - tagCount(words));
  return shown(tags, byPriority(tags).filter((t) => !has(words, t)).slice(0, room)).join(' ');
}

/** "A", "A and B", "A, B and C": every name, whole. */
export function names(list) {
  const all = (list ?? []).map((n) => String(n ?? '').trim()).filter(Boolean);
  return all.length < 2 ? (all[0] ?? '') : `${all.slice(0, -1).join(', ')} and ${all.at(-1)}`;
}

function long(network, p, end, tags) {
  const limit = LIMITS[network];
  const compose = (list, withBody, withExtra) => {
    let words = join([p.opening, p.hook, list.length ? list.join('\n') : null, withExtra ? p.extra : null, withBody ? p.body : null, end]);
    if (network === 'instagram') words = words.replace(/https?:\/\//gi, '');
    return join([words, tagLine(tags, words, MAX_TAGS[network])]);
  };
  let list = [...p.lines];
  let text = compose(list, true, true);
  if (charLength(text) > limit) text = compose(list, true, false);
  while (list.length && charLength(text) > limit) {
    list = list.slice(0, -1);
    text = compose(list, true, false);
  }
  return charLength(text) > limit ? compose(list, false, false) : text;
}

function shorten(text, room) {
  if (xLength(text) <= room) return text;
  const words = text.split(/\s+/);
  while (words.length > 1 && xLength(`${words.join(' ')}…`) > room) words.pop();
  return `${words.join(' ')}…`;
}

function forX(parts, p, end, tags) {
  const fits = (t) => xLength(t) <= LIMITS.x;
  const compose = (open, head, list, more, chosen) => join([open, head, list.length ? list.join('\n') : null, ...more, end, chosen.length ? chosen.join(' ') : null]);

  // The fullest first line that fits with the hashtags (they come first: the event, CampBuddy,
  // WPSimplified), else the first that fits alone, else the shortest there is.
  const heads = [...[parts.xhook ?? []].flat(), p.hook].map((h) => String(h ?? '').trim()).filter(Boolean);
  const xTags = (h) => shown(tags, byPriority(tags).filter((t) => !has(h, t)).slice(0, MAX_TAGS.x));
  let head = heads.find((h) => fits(compose(null, h, [], [], xTags(h)))) ?? heads.find((h) => fits(compose(null, h, [], [], [])));
  if (head === undefined) {
    const shortest = [...heads].sort((a, b) => xLength(a) - xLength(b))[0] ?? '';
    head = shorten(shortest, LIMITS.x - xLength(compose(null, '', [], [], [])) - 2);
  }

  let chosen = [];
  for (const tag of byPriority(tags)) {
    const tryTags = shown(tags, [...chosen, tag]);
    if (tryTags.length <= MAX_TAGS.x && !has(head, tag) && fits(compose(null, head, [], [], tryTags))) chosen = tryTags;
  }
  const list = [];
  for (const line of p.lines) {
    if (!fits(compose(null, head, [...list, line], [], chosen))) break;
    list.push(line);
  }
  const more = [];
  for (const para of [clean(parts.xbody) ?? p.body, p.extra]) {
    if (para !== null && fits(compose(null, head, list, [...more, para], chosen))) more.push(para);
  }
  const open = p.opening !== null && fits(compose(p.opening, head, list, more, chosen)) ? p.opening : null;
  return compose(open, head, list, more, chosen);
}

/**
 * The three captions from a caption's parts (as App\Support\SocialCaptions::make):
 * { opening, hook, xhook, body, xbody, lines, extra, cta, link, media: 'card' | 'reel' }.
 */
export function makeCaptions(parts, hashtags) {
  const p = {
    opening: clean(parts.opening),
    hook: String(parts.hook ?? '').trim(),
    body: clean(parts.body),
    extra: clean(parts.extra),
    lines: (parts.lines ?? []).map((l) => String(l ?? '').trim()).filter(Boolean),
  };
  const cta = String(parts.cta ?? '').trim();
  const link = String(parts.link ?? '').trim();
  const where = parts.media === 'reel' ? 'the QR at the end of the reel' : 'the QR on the card';
  const tags = captionTags(hashtags);
  return {
    instagram: long('instagram', p, `${cta}: scan ${where}, or open campbuddy.club.`, tags),
    linkedin: long('linkedin', p, `${cta}: ${link}`, tags),
    x: forX(parts, p, `${cta}: ${link}`, tags),
  };
}

/** A post's captions for its reel: the Instagram one points to the QR at the reel's end, not on the card. */
export function forReel(captions) {
  return { ...captions, instagram: String(captions?.instagram ?? '').replace('the QR on the card', 'the QR at the end of the reel') };
}

/** The old single caption (a string) or a set of captions, as a set. */
export function asCaptions(value) {
  if (value && typeof value === 'object') return { instagram: value.instagram ?? '', linkedin: value.linkedin ?? '', x: value.x ?? '' };
  const text = String(value ?? '');
  return { instagram: text, linkedin: text, x: text };
}

/** How a caption stands against its network's limits. */
export function captionStats(network, text) {
  const length = network === 'x' ? xLength(text) : charLength(text);
  const tags = tagCount(text);
  return { length, limit: LIMITS[network], tags, maxTags: MAX_TAGS[network], over: length > LIMITS[network] || (network === 'instagram' && tags > MAX_TAGS.instagram) };
}

/** The captions as one text file (captions.txt in the ZIPs): each network under its name. */
export function captionsText(captions) {
  return NETWORKS.map(([key, label]) => `--- ${label} ---\n${captions?.[key] ?? ''}`).join('\n\n');
}

// ---- The caption box ---------------------------------------------------------

const NETWORK_KEY = 'cb-social:caption-network';
let network = (() => {
  try {
    const saved = localStorage.getItem(NETWORK_KEY);
    return NETWORKS.some(([k]) => k === saved) ? saved : 'instagram';
  } catch {
    return 'instagram';
  }
})();

/** The network shown in every caption box (the last one picked, in this browser). */
export const activeNetwork = () => network;
export const networkLabel = (key = network) => NETWORKS.find(([k]) => k === key)?.[1] ?? key;

function pickNetwork(key) {
  network = key;
  try { localStorage.setItem(NETWORK_KEY, key); } catch { /* private mode: this visit only */ }
  document.dispatchEvent(new CustomEvent('caption:network', { detail: key }));
}

let boxes = 0;

/**
 * A caption box: a button per network, the chosen network's caption to edit, and its count.
 * `defaults` are the captions as written; `edited` this browser's own edits (only those that
 * differ are kept, so a better default shows wherever nobody changed it). `onEdit` runs on each
 * edit. Picking a network picks it in every box on the page. Returns the box and what it holds.
 */
export function captionBox({ defaults, edited = {}, onEdit = () => {}, rows = 8 } = {}) {
  const id = `caption-box-${++boxes}`;
  let base = asCaptions(defaults);
  const values = { ...base };
  for (const [key] of NETWORKS) if (typeof edited?.[key] === 'string') values[key] = edited[key];
  const el = (tag, cls, text) => {
    const n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text !== undefined) n.textContent = text;
    return n;
  };

  const root = el('div', 'space-y-1.5');
  const head = el('div', 'flex flex-wrap items-center justify-between gap-2');
  const label = el('label', 'cb-label mb-0', 'Caption');
  label.htmlFor = id;
  const group = el('div', 'flex gap-1');
  group.setAttribute('role', 'group');
  group.setAttribute('aria-label', 'Caption for');
  const buttons = NETWORKS.map(([key, name]) => {
    const b = el('button', 'cb-btn cb-btn-sm', name);
    b.type = 'button';
    b.dataset.network = key;
    b.addEventListener('click', () => pickNetwork(key));
    group.append(b);
    return b;
  });
  head.append(label, group);
  const area = el('textarea', 'cb-input text-sm');
  area.id = id;
  area.rows = rows;
  const count = el('p', 'text-xs tabular-nums');
  count.setAttribute('aria-live', 'polite');
  const hint = el('p', 'cb-hint');
  root.append(head, area, count, hint);

  const showCount = () => {
    const s = captionStats(network, area.value);
    const parts = [`${s.length.toLocaleString('en-IN')} / ${s.limit.toLocaleString('en-IN')}`];
    if (network !== 'x') parts.push(`${s.tags} / ${s.maxTags} hashtags`);
    count.textContent = `${parts.join(' · ')}${s.over ? ` · too long for ${networkLabel()}` : ''}`;
    count.classList.toggle('text-danger', s.over);
    count.classList.toggle('font-semibold', s.over);
    count.classList.toggle('text-muted', !s.over);
  };
  const show = () => {
    buttons.forEach((b) => {
      const on = b.dataset.network === network;
      b.setAttribute('aria-pressed', String(on));
      b.classList.toggle('cb-btn-primary', on);
      b.classList.toggle('cb-btn-secondary', !on);
    });
    area.value = values[network];
    hint.textContent = HINTS[network];
    showCount();
  };
  area.addEventListener('input', () => {
    values[network] = area.value;
    showCount();
    onEdit();
  });
  // A box taken off the page (another person picked) stops listening.
  const onNetwork = () => {
    if (!root.isConnected) document.removeEventListener('caption:network', onNetwork);
    else show();
  };
  document.addEventListener('caption:network', onNetwork);
  show();

  return {
    root,
    /** Every network's caption as it stands. */
    values: () => ({ ...values }),
    /** The one on show. */
    current: () => values[network],
    /** Only the captions edited here (for this browser's store). */
    edited: () => Object.fromEntries(NETWORKS.map(([k]) => k).filter((k) => values[k] !== base[k]).map((k) => [k, values[k]])),
    /** New captions as written (a new day, a new session): replaces the ones nobody edited, or all with `all`. */
    refresh(next, all = false) {
      const fresh = asCaptions(next);
      for (const [key] of NETWORKS) if (all || values[key] === base[key]) values[key] = fresh[key];
      base = fresh;
      show();
    },
    /** Changes every caption with `fn` (the countdown's number of days). */
    map(fn) {
      for (const [key] of NETWORKS) {
        const wasBase = values[key] === base[key];
        values[key] = fn(values[key]);
        if (wasBase) base[key] = values[key];
      }
      show();
    },
  };
}
