// A person on the attendee list (Explore → People), opened in a sheet: their
// photo, roles, talks and links, "+ Meet", and — for an organizer, speaker,
// volunteer or microsponsor — a card to download or share.
//
// That card is the Camp Card's Ticket design (camp-card-paint.js, same 900
// DPI PNG), built only from what the WordCamp site already shows publicly:
// the attendee-list name and links, the roles and talks (RosterRoles). It
// says so on the card ("Made from public WordCamp info") — it isn't a card
// the person made. Nothing about them is sent anywhere or stored.

import QRCode from 'qrcode-generator';
import { track } from './analytics.js';
import { cardPng, paintCard, saveBlob, tapStillCounts } from './camp-card-paint.js';
import { ROLE_LABELS, rolesOf } from './roster-roles.js';
import { render } from './template.js';
import { showToast } from './toast.js';

/** Which of their links the card's QR opens, in order of preference. */
export const QR_LINK_ORDER = ['linkedin', 'website', 'twitter'];

const SCAN_LABELS = {
  linkedin: 'Scan to connect on LinkedIn',
  website: 'Scan to visit their website',
  twitter: 'Scan to follow them on X',
};

/** The link the card's QR opens: LinkedIn, else a website, else X — only real web addresses. */
export function qrLinkFor(entry) {
  const links = (entry?.links ?? []).filter((l) => /^https?:\/\//i.test(l?.url ?? ''));
  for (const type of QR_LINK_ORDER) {
    const link = links.find((l) => (l.type === type) || (type === 'website' && !QR_LINK_ORDER.includes(l.type)));
    if (link) return { type, url: link.url };
  }

  return null;
}

/** Only people with a role get a card made from public data. */
export function hasPublicCard(entry) {
  return rolesOf(entry).length > 0;
}

/**
 * What the card shows (the shape camp-card-paint.js paints): the name, the
 * roles as the role line ("Speaker · Organizer"), and a speaker's first talk
 * in Ticket's "Ask me about" bubble.
 */
export function personCardContent(entry) {
  const roles = rolesOf(entry).map((role) => ROLE_LABELS[role]);
  const talk = (entry?.talks ?? []).find((t) => typeof t === 'string' && t.trim() !== '') ?? '';
  const qr = qrLinkFor(entry);

  return {
    name: entry?.name ?? '',
    roleLines: roles.length ? [roles.join(' · ')] : [],
    roleLine: roles.join(' · '),
    tags: [],
    tagsBesideAsk: [],
    askMe: talk,
    scan: qr ? SCAN_LABELS[qr.type] : '',
  };
}

export function cardFilename(name) {
  const slug = String(name ?? '').toLowerCase().normalize('NFKD').replace(/\p{M}/gu, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 40);

  return `campbuddy-card-${slug || 'person'}.png`;
}

function makeQr(entry) {
  const link = qrLinkFor(entry);
  if (!link) return null;

  const qr = QRCode(0, 'H');
  qr.addData(link.url);
  qr.make();

  return qr;
}

/**
 * Opens the sheet for one attendee-list entry. `wireMeet(btn)` hooks the
 * sheet's "+ Meet" button up the same way as the row's (people.js).
 */
export function openPersonSheet(entry, { links = [], wireMeet = null } = {}) {
  const roles = rolesOf(entry);
  const withCard = hasPublicCard(entry);

  const dialog = render('tpl-person-sheet', {
    avatar: { attrs: { src: entry.gravatar_url || '/media/illustrations/avatar.svg' } },
    name: entry.name ?? '',
    roles: roles.length ? roles.map((role) => render('tpl-role-badge', { badge: { text: ROLE_LABELS[role], class: { [`role-badge--${role}`]: true } } })) : null,
    talks: (entry.talks ?? []).length ? entry.talks.map((t) => render('tpl-person-talk', { talk: t })) : null,
    links: links.length ? links : null,
    'card-part': withCard,
  });

  wireMeet?.(dialog.querySelector('[data-slot="meet"]'));

  const close = () => dialog.close();
  dialog.querySelector('[data-person-close]').addEventListener('click', close);
  dialog.addEventListener('click', (e) => {
    if (e.target === dialog) close();
  });
  dialog.addEventListener('close', () => dialog.remove());

  document.body.appendChild(dialog);
  dialog.showModal();
  track('person_card', { result: 'open' });

  if (withCard) setUpCard(dialog, entry);
}

function setUpCard(dialog, entry) {
  const content = personCardContent(entry);
  const qr = makeQr(entry);
  const preview = render('tpl-person-card');
  dialog.querySelector('[data-slot="preview"]').appendChild(preview);
  const face = preview.classList.contains('camp-card') ? preview : preview.querySelector('.camp-card');

  // The on-screen preview: no "Nothing chosen to show yet" pill — that hint is
  // for someone editing their own card, and this one has nothing to choose.
  paintCard(face, content, qr).then(() => face.querySelectorAll('.camp-card__tag--placeholder').forEach((el) => el.remove()));

  let made = null;
  const png = () => {
    if (!made) {
      made = cardPng(face, content, qr);
      made.catch(() => { made = null; });
    }
    return made;
  };

  dialog.querySelectorAll('[data-person-export]').forEach((btn) => {
    btn.addEventListener('click', () => exportCard(btn, btn.dataset.personExport, png, entry));
  });
}

const BUTTON_TEXT = {
  download: { idle: 'Download card', ready: 'Ready, tap to download' },
  share: { idle: 'Share', ready: 'Ready, tap to share' },
};

function setLabel(btn, text) {
  const label = btn.querySelector('[data-export-label]');
  if (label) label.textContent = text;
}

// Making the 900 DPI image takes a few seconds on a phone, and a browser only
// allows a download / the share sheet shortly after a tap — the same
// "Preparing… → Ready, tap to …" flow as the Camp Card page.
async function exportCard(btn, kind, png, entry) {
  if (btn.dataset.state === 'preparing') return;

  const act = () => (kind === 'share' ? shareCard : downloadCard);

  if (btn.dataset.state !== 'ready') {
    btn.dataset.state = 'preparing';
    btn.setAttribute('aria-busy', 'true');
    setLabel(btn, 'Preparing…');
  }

  let blob;
  try {
    blob = await png();
  } catch {
    delete btn.dataset.state;
    btn.removeAttribute('aria-busy');
    setLabel(btn, BUTTON_TEXT[kind].idle);
    showToast("Couldn't create the image. Please try again.");
    return;
  }

  if (btn.dataset.state === 'ready' || tapStillCounts()) {
    delete btn.dataset.state;
    btn.removeAttribute('aria-busy');
    btn.classList.remove('btn--primary');
    setLabel(btn, BUTTON_TEXT[kind].idle);
    await act()(blob, entry);
    return;
  }

  btn.dataset.state = 'ready';
  btn.removeAttribute('aria-busy');
  btn.classList.add('btn--primary');
  setLabel(btn, BUTTON_TEXT[kind].ready);
}

function downloadCard(blob, entry) {
  saveBlob(blob, cardFilename(entry.name));
  track('person_card', { result: 'download' });
  showToast('Card saved at 900 DPI.');
}

async function shareCard(blob, entry) {
  const file = new File([blob], cardFilename(entry.name), { type: 'image/png' });

  try {
    if (navigator.canShare?.({ files: [file] })) {
      await navigator.share({ files: [file], title: entry.name });
      track('person_card', { result: 'share' });
    } else {
      saveBlob(blob, cardFilename(entry.name));
      track('person_card', { result: 'download' });
      showToast('Sharing isn\'t available here, so the card was downloaded instead.');
    }
  } catch (err) {
    if (err?.name !== 'AbortError') showToast("Couldn't open sharing. Try Download instead.");
  }
}
