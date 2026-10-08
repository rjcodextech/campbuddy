// Camp Card: local-only (CC5), attendee chooses which filled
// fields actually show (CC2), QR points at whichever link they designate
// primary (CC3). All 7 layouts render at once in a gallery
// (resources/views/attendee/camp-card.blade.php's #camp-card-scroll)
// rather than one preview behind a layout picker — each card is its own
// self-contained subtree (data-layout-card="…"), with its own
// Share/Download buttons, so nothing here relies on a single
// #camp-card-preview id.
//
// Every card is the same fixed 3:5 shape whose contents scale with its
// width (components/_camp-card.scss), so Share/Download can repaint a
// clone of it at print size and get exactly the on-screen layout.

import QRCode from 'qrcode-generator';
import { track } from './analytics.js';
import { kvGet, kvSet } from './db.js';
import { campCardPrefill, onboardingAfterCampCard } from './profile-sync.js';
import { cardContent, cardPng, paintCard, saveBlob, tapStillCounts } from './camp-card-paint.js';
import { render } from './template.js';
import { showToast } from './toast.js';

const LINK_FIELDS = ['linkedin', 'website', 'wordpressOrg', 'twitter'];
const LAYOUTS = ['ticket', 'classic', 'minimal', 'bold', 'split', 'badge', 'pass'];
const DEFAULT_QR_TARGET = 'linkedin';

// What "Scan to …" says under the QR, by where the QR points.
const SCAN_LABELS = {
  linkedin: 'Scan to connect on LinkedIn',
  website: 'Scan to visit my website',
  wordpressOrg: 'Scan for my WordPress.org profile',
  twitter: 'Scan to follow me on X',
};

// What every card on screen currently shows — kept so an export can
// repaint a clone of any card from the same content.
let shown = null;

const SAMPLE_CARD = {
  name: 'Sunil Kumar Sharma',
  role: 'WordPress Engineer',
  company: 'WPSimplified',
  interests: ['Gutenberg', 'WooCommerce'],
  visibleFields: ['role', 'interests'],
};

// Twitter is asked as a bare handle (CC1 wording), not a full URL —
// resolved to a real link only when actually needed (QR/visible tags).
function resolveLink(field, value) {
  if (!value) return null;
  if (field === 'twitter') {
    const handle = value.trim().replace(/^@/, '');
    return handle ? `https://x.com/${handle}` : null;
  }
  return value;
}

// Interests used to be saved as a single comma-separated string before
// the tag-input UI existed — split it into tags once, on load, so an
// attendee's existing Camp Card doesn't lose its interests when this
// shipped.
function normalizeInterests(value) {
  if (Array.isArray(value)) return value;
  if (typeof value === 'string' && value.trim()) {
    return value.split(',').map((s) => s.trim()).filter(Boolean);
  }
  return [];
}

export async function renderCampCard() {
  const form = document.getElementById('camp-card-form');
  if (!form) return;

  preloadExporter();

  const card = await kvGet('campCard');
  const visibleFields = new Set(card?.visibleFields ?? []);

  if (card) {
    Object.entries(card).forEach(([key, value]) => {
      const input = form.elements.namedItem(key);
      if (input && typeof value === 'string') input.value = value;
    });
  } else {
    // No card yet: start from what this event's discovery profile and the
    // onboarding answers already say (name, role, WordPress.org), via
    // profile-sync.js. Saved only when they press Save.
    try {
      const eventId = document.getElementById('app')?.dataset.eventId;
      const [discovery, onboarding] = await Promise.all([eventId ? kvGet(`discovery:${eventId}`) : null, kvGet('onboarding')]);
      Object.entries(campCardPrefill(null, discovery, onboarding)).forEach(([key, value]) => {
        const input = form.elements.namedItem(key);
        if (input && !input.value) input.value = value;
      });
    } catch {
      // Storage unavailable: an empty form, as before.
    }
  }

  const getInterests = setupTagInput(normalizeInterests(card?.interests));

  document.querySelectorAll('[data-visible-field]').forEach((chip) => {
    setChipState(chip, visibleFields.has(chip.dataset.visibleField));

    chip.addEventListener('click', () => {
      setChipState(chip, !chip.classList.contains('chip--selected'));
    });
  });

  setupQrTargetPicker(card?.primaryLink ?? DEFAULT_QR_TARGET, form);
  setupLinkFields(form);
  setupRequiredFields(form);
  renderAllPreviews(card ? { ...card, interests: normalizeInterests(card.interests) } : card);

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    // Links are tidied (and checked) first: a malformed one stops the save
    // with a message beside it, rather than becoming a QR that goes nowhere.
    const badLink = tidyLinkFields(form);

    // Then what a card can't be generated without: a name, and a link for
    // its QR code. Whatever is missing is highlighted (not just blocked
    // silently — iOS Safari shows no native "please fill in" bubble at all),
    // and the first problem, top to bottom, gets the focus.
    const missing = validateRequired(form);
    const firstProblem = missing?.name === 'name' ? missing : badLink ?? missing;

    if (firstProblem) {
      firstProblem.focus();
      showToast('Fill in the highlighted fields to generate your Camp Card.');
      return;
    }

    const data = Object.fromEntries(new FormData(form).entries());
    data.interests = getInterests();
    data.visibleFields = [...document.querySelectorAll('[data-visible-field].chip--selected')].map(
      (chip) => chip.dataset.visibleField
    );
    data.primaryLink = document.querySelector('[data-qr-target].chip--selected')?.dataset.qrTarget ?? DEFAULT_QR_TARGET;

    await kvSet('campCard', data);
    // The role and WordPress.org link become the onboarding answers too, so
    // the next discovery profile starts from them (local only).
    try {
      await kvSet('onboarding', onboardingAfterCampCard(await kvGet('onboarding'), data));
    } catch {
      // Only a convenience: the card itself is saved.
    }
    // Camp Card content is local-only (CC5) — only "it was saved" is reported.
    track('camp_card_save');
    renderAllPreviews(data);
    document.getElementById('cc-edit-details').open = false;
    showToast('Camp Card saved.');
  });
  document.querySelectorAll('[data-download-card]').forEach((btn) => {
    btn.addEventListener('click', () => handleExport('download', btn.dataset.downloadCard, btn));
  });

  document.querySelectorAll('[data-share-card]').forEach((btn) => {
    btn.addEventListener('click', () => handleExport('share', btn.dataset.shareCard, btn));
  });
}

function cardElement(layout) {
  return document.querySelector(`[data-layout-card="${layout}"] .camp-card`);
}

// One card as the print PNG (camp-card-paint.js) — shared by Download and
// Share so both produce the exact same file.
function cardPngBlob(layout) {
  return cardPng(cardElement(layout), shown.content, shown.qr);
}

// ---- Share / Download -----------------------------------------------------
//
// Making a 900 DPI image takes a few seconds on a phone (more the first
// time, while html2canvas downloads). Browsers only allow a download or the
// share sheet for a short while after a tap, so an image that finishes too
// late was silently refused — which is why these used to work only on a
// second tap. Now:
//   - html2canvas is fetched in the background as soon as the page is idle;
//   - a tap shows "Preparing…" until the image exists, and the image is kept
//     (per layout, until the card changes);
//   - if the tap still counts when it's ready, it downloads/shares at once;
//     otherwise the button turns into "Ready — tap to …", and that tap works
//     instantly because the image is already made.

// layout → Promise<Blob>, for what's currently shown. Cleared on any change.
const exports = new Map();

function exportFor(layout) {
  if (!exports.has(layout)) {
    const made = cardPngBlob(layout);
    made.catch(() => exports.delete(layout));
    exports.set(layout, made);
  }
  return exports.get(layout);
}

function forgetExports() {
  exports.clear();
  document.querySelectorAll('[data-share-card], [data-download-card]').forEach((btn) => resetButton(btn));
}

const LABELS = {
  share: { idle: 'Share', ready: 'Ready, tap to share' },
  download: { idle: 'Download', ready: 'Ready, tap to download' },
};

// Share / Download keep their icon: only the words beside it change.
function setLabel(btn, text) {
  const label = btn.querySelector('[data-export-label]');
  if (label) label.textContent = text;
  else btn.textContent = text;
}

function resetButton(btn) {
  const kind = btn.dataset.shareCard !== undefined ? 'share' : 'download';
  setLabel(btn, LABELS[kind].idle);
  btn.removeAttribute('aria-busy');
  btn.classList.remove('btn--primary', 'btn--busy');
  btn.classList.add('btn--outline');
  delete btn.dataset.state;
}

async function handleExport(kind, layout, btn) {
  if (btn.dataset.state === 'preparing') return;

  const act = kind === 'share' ? shareBlob : downloadBlob;

  // Already made (or a "Ready" tap): straight away, inside this tap.
  if (btn.dataset.state === 'ready') {
    const blob = await exportFor(layout);
    resetButton(btn);
    await act(blob, layout);
    return;
  }

  btn.dataset.state = 'preparing';
  btn.setAttribute('aria-busy', 'true');
  btn.classList.add('btn--busy');
  setLabel(btn, 'Preparing…');

  let blob;
  try {
    blob = await exportFor(layout);
  } catch {
    resetButton(btn);
    track('camp_card_export_error', { action: kind, layout });
    showToast("Couldn't create the image. Please try again.");
    return;
  }

  if (tapStillCounts()) {
    resetButton(btn);
    await act(blob, layout);
    return;
  }

  btn.dataset.state = 'ready';
  btn.removeAttribute('aria-busy');
  btn.classList.remove('btn--outline', 'btn--busy');
  btn.classList.add('btn--primary');
  setLabel(btn, LABELS[kind].ready);
}

function filenameFor(layout) {
  return `campbuddy-camp-card-${layout}.png`;
}

async function downloadBlob(blob, layout) {
  saveBlob(blob, filenameFor(layout));
  track('camp_card_download', { layout });
  showToast('Saved at 900 DPI, ready to print.');
}

async function shareBlob(blob, layout) {
  const file = new File([blob], filenameFor(layout), { type: 'image/png' });

  try {
    if (navigator.canShare?.({ files: [file] })) {
      await navigator.share({ files: [file], title: 'My Camp Card' });
      track('share', { method: 'web_share_file', content_type: 'camp_card', item_id: layout });
    } else if (navigator.share) {
      // Some browsers support navigator.share but not file sharing —
      // share a link instead of failing silently.
      await navigator.share({ title: 'My Camp Card', url: location.href });
      track('share', { method: 'web_share_link', content_type: 'camp_card', item_id: layout });
    } else {
      // No Web Share support at all (most desktop browsers) — fall back
      // to a download so the button still does something useful.
      saveBlob(blob, filenameFor(layout));
      track('share', { method: 'download_fallback', content_type: 'camp_card', item_id: layout });
      showToast('Sharing isn\'t available here, so the image was downloaded instead.');
    }
  } catch (err) {
    if (err?.name !== 'AbortError') {
      track('camp_card_export_error', { action: 'share', layout });
      showToast("Couldn't open sharing. Try Download instead.");
    }
  }
}

// Fetch the image library while the attendee is still reading their card,
// so the first tap doesn't wait for a download too.
function preloadExporter() {
  const load = () => import('html2canvas').catch(() => {});
  if ('requestIdleCallback' in window) {
    requestIdleCallback(load, { timeout: 3000 });
  } else {
    setTimeout(load, 1500);
  }
}

// ---- The edit form -------------------------------------------------------

const MAX_INTERESTS = 8;
const MAX_INTEREST_LENGTH = 30;

// Tap-to-add ideas under the interests field — WordPress topics people
// actually start conversations about. Ones already chosen are hidden.
const SUGGESTED_INTERESTS = [
  'Gutenberg', 'Block themes', 'WooCommerce', 'Performance', 'Accessibility',
  'Design', 'Security', 'SEO', 'Plugins', 'Community',
];

const LINK_MESSAGE = "That doesn't look like a link. Try something like yoursite.com.";
const HANDLE_MESSAGE = 'Use just your handle: letters, numbers or underscores, up to 15.';

// A toggle chip's on/off state, told to both the eye (class) and assistive tech (aria-pressed).
function setChipState(chip, on) {
  chip.classList.toggle('chip--selected', on);
  chip.setAttribute('aria-pressed', String(on));
}

// The interests field: tags live inside the input box, added with Enter or
// a comma (or the Add button, since a phone keyboard's comma is a
// long-press away), removed with their ×. Returns a getter so the form's
// submit handler can read the current list at save time. No library —
// matches the app's "vanilla JS by default" posture (§4.2/§5.5).
function setupTagInput(initialTags) {
  const box = document.getElementById('interests-input');
  const textInput = document.getElementById('interests-text');
  const tagsContainer = document.getElementById('interests-tags');
  const addButton = document.getElementById('interests-add');
  const countEl = document.getElementById('interests-count');
  const suggestEl = document.getElementById('interests-suggest');
  const tags = [];

  const has = (tag) => tags.some((t) => t.toLowerCase() === tag.toLowerCase());

  function add(value) {
    const tag = value.trim().replace(/\s+/g, ' ').slice(0, MAX_INTEREST_LENGTH);
    if (!tag || has(tag)) return;

    if (tags.length >= MAX_INTERESTS) {
      showToast(`That's ${MAX_INTERESTS} interests. Remove one to add another.`);
      return;
    }

    tags.push(tag);
  }

  // Commits whatever's typed; "a, b, c" pasted in one go becomes three tags.
  function commit() {
    textInput.value.split(',').forEach(add);
    textInput.value = '';
    draw();
  }

  function draw() {
    tagsContainer.replaceChildren(
      ...tags.map((tag, i) =>
        render('tpl-tag-input-tag', {
          text: tag,
          remove: { attrs: { 'data-remove-tag': i, 'aria-label': `Remove ${tag}` } },
        })
      )
    );

    tagsContainer.querySelectorAll('[data-remove-tag]').forEach((btn) => {
      btn.addEventListener('click', () => {
        tags.splice(Number(btn.dataset.removeTag), 1);
        draw();
        textInput.focus();
      });
    });

    countEl.textContent = String(tags.length);
    addButton.hidden = textInput.value.trim() === '';

    suggestEl.replaceChildren(
      ...SUGGESTED_INTERESTS.filter((name) => !has(name)).map((name) => {
        const chip = render('tpl-interest-suggestion', {
          chip: { text: name, attrs: { 'aria-label': `Add ${name}`, disabled: tags.length >= MAX_INTERESTS } },
        });

        chip.addEventListener('click', () => {
          add(name);
          draw();
        });

        return chip;
      })
    );
  }

  textInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ',') {
      e.preventDefault();
      commit();
    } else if (e.key === 'Backspace' && textInput.value === '' && tags.length > 0) {
      tags.pop();
      draw();
    }
  });

  // A comma typed or pasted mid-text ends a tag; what follows it stays in the box.
  textInput.addEventListener('input', () => {
    if (textInput.value.includes(',')) {
      const parts = textInput.value.split(',');
      textInput.value = parts.pop();
      parts.forEach(add);
      draw();
    } else {
      addButton.hidden = textInput.value.trim() === '';
    }
  });

  textInput.addEventListener('blur', () => {
    if (textInput.value.trim()) commit();
  });

  addButton.addEventListener('click', () => {
    commit();
    textInput.focus();
  });

  // Clicking the empty part of the box should still put the cursor in it.
  box.addEventListener('click', (e) => {
    if (e.target === box) textInput.focus();
  });

  initialTags.forEach(add);
  draw();

  return () => {
    commit();
    return [...tags];
  };
}

// "QR code links to": one chip is always chosen. A chip whose link isn't
// filled in yet is faded (still choosable — the QR falls back to the first
// link that exists — but visibly not ready).
function setupQrTargetPicker(activeTarget, form) {
  const chips = document.querySelectorAll('[data-qr-target]');

  const syncEmpty = () => {
    chips.forEach((chip) => {
      const empty = !form.elements.namedItem(chip.dataset.qrTarget)?.value.trim();
      chip.classList.toggle('chip--empty', empty);

      if (empty) {
        chip.title = 'Add this link above first';
      } else {
        chip.removeAttribute('title');
      }
    });
  };

  chips.forEach((chip) => {
    setChipState(chip, chip.dataset.qrTarget === activeTarget);

    chip.addEventListener('click', () => {
      chips.forEach((c) => setChipState(c, c === chip));
    });
  });

  form.addEventListener('input', syncEmpty);
  syncEmpty();
}

// ---- Link fields ---------------------------------------------------------

// "linkedin.com/in/me" → "https://linkedin.com/in/me". Returns '' for an
// empty field and null for something that can't be a link.
function normalizeUrl(raw) {
  const value = raw.trim();
  if (!value) return '';

  const withScheme = /^[a-z][a-z0-9+.-]*:\/\//i.test(value) ? value : `https://${value.replace(/^\/+/, '')}`;

  try {
    const url = new URL(withScheme);

    if (!['http:', 'https:'].includes(url.protocol) || !url.hostname.includes('.')) return null;

    // "https://yoursite.com/" → "https://yoursite.com" (a shorter QR, too).
    return url.pathname === '/' && !url.search && !url.hash ? url.origin : url.href;
  } catch {
    return null;
  }
}

// "@me", "me" or a pasted x.com / twitter.com profile link → "me".
function normalizeHandle(raw) {
  let value = raw.trim();
  if (!value) return '';

  const fromUrl = value.match(/^(?:https?:\/\/)?(?:www\.)?(?:x|twitter)\.com\/@?([A-Za-z0-9_]{1,15})\/?(?:[?#].*)?$/i);
  if (fromUrl) value = fromUrl[1];

  value = value.replace(/^@/, '');

  return /^[A-Za-z0-9_]{1,15}$/.test(value) ? value : null;
}

function showFieldError(input, message) {
  const el = document.getElementById(`${input.id}-error`);

  if (message) {
    input.setAttribute('aria-invalid', 'true');
  } else {
    input.removeAttribute('aria-invalid');
  }

  if (el) {
    el.textContent = message ?? '';
    el.hidden = !message;
  }
}

// Rewrites the field to its tidy form; false (with a message beside it) if it can't be.
function tidyField(input) {
  const isHandle = input.hasAttribute('data-handle-field');
  const tidy = isHandle ? normalizeHandle(input.value) : normalizeUrl(input.value);

  if (tidy === null) {
    showFieldError(input, isHandle ? HANDLE_MESSAGE : LINK_MESSAGE);
    return false;
  }

  input.value = tidy;
  showFieldError(input, null);
  return true;
}

function setupLinkFields(form) {
  form.querySelectorAll('[data-link-field], [data-handle-field]').forEach((input) => {
    input.addEventListener('blur', () => {
      tidyField(input);
      form.dispatchEvent(new Event('input')); // refresh the QR chips' "empty" fade
    });

    input.addEventListener('input', () => showFieldError(input, null));
  });
}

// All of them, so every bad one gets its message. Returns the first bad
// field (for the caller to focus), or null when every link is fine.
function tidyLinkFields(form) {
  let firstBad = null;

  form.querySelectorAll('[data-link-field], [data-handle-field]').forEach((input) => {
    if (!tidyField(input) && !firstBad) firstBad = input;
  });

  return firstBad;
}

// ---- Required fields -----------------------------------------------------

const NAME_MESSAGE = 'Enter your name. It goes on your card.';

function setLinksMissing(missing) {
  document.getElementById('cc-links-group').classList.toggle('form-group--invalid', missing);
  document.getElementById('cc-links-error').hidden = !missing;
}

// What a card can't be generated without (see renderAllPreviews, which shows
// the sample card until both exist): a name, and at least one link for the QR
// code to point at. Highlights whichever is missing and returns the field to
// focus (the name, else the first link), or null when nothing is missing.
function validateRequired(form) {
  const nameInput = form.elements.namedItem('name');
  const nameMissing = nameInput.value.trim() === '';
  const linksMissing = !LINK_FIELDS.some((field) => form.elements.namedItem(field)?.value.trim());

  showFieldError(nameInput, nameMissing ? NAME_MESSAGE : null);
  setLinksMissing(linksMissing);

  if (nameMissing) return nameInput;

  return linksMissing ? form.elements.namedItem(LINK_FIELDS[0]) : null;
}

// The highlight goes away as soon as the person starts fixing it.
function setupRequiredFields(form) {
  const nameInput = form.elements.namedItem('name');
  nameInput.addEventListener('input', () => showFieldError(nameInput, null));

  LINK_FIELDS.forEach((field) => {
    form.elements.namedItem(field)?.addEventListener('input', () => setLinksMissing(false));
  });
}

function renderAllPreviews(card) {
  // Images made from the previous content are out of date now.
  forgetExports();

  const sampleNoteEl = document.getElementById('camp-card-sample-note');
  const hasPrimaryLink = card && LINK_FIELDS.some((f) => resolveLink(f, card[f]));
  const isSample = !card?.name || !hasPrimaryLink;
  sampleNoteEl.hidden = !isSample;

  // The link the QR opens: the chosen one, else the first that's filled in.
  const qrField = !isSample
    ? [card.primaryLink, ...LINK_FIELDS].find((f) => f && resolveLink(f, card[f]))
    : null;
  const primaryUrl = qrField ? resolveLink(qrField, card[qrField]) : null;

  // Same QR (same primary link) shared across every layout's canvas — no
  // need to regenerate the module grid per card, just redraw it per layout.
  const qr = primaryUrl ? QRCode(0, 'H') : null;
  if (qr) {
    qr.addData(primaryUrl);
    qr.make();
  }

  shown = { content: { ...cardContent(isSample ? SAMPLE_CARD : card), scan: SCAN_LABELS[qrField] ?? '' }, qr };
  repaintPreviews();

  // The tag trimming below measures text, so it has to be redone once the
  // web fonts (Inter, Playfair Display…) have swapped in and changed how
  // wide everything is.
  document.fonts?.ready.then(repaintPreviews);
}

function repaintPreviews() {
  LAYOUTS.forEach((layout) => {
    const card = cardElement(layout);
    if (card) paintCard(card, shown.content, shown.qr);
  });
}
