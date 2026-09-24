// "Best on your phone" popup for laptop/desktop visitors. CampBuddy is
// designed one-handed, for a phone; on a big screen every section still
// works (layout/_wide-screens.scss), and this popup suggests the phone,
// with a QR code of the current page to make the switch one scan.
// Markup: attendee/partials/desktop-notice.blade.php.
//
// "Desktop-class" = a wide viewport AND a precise, hovering pointer, so a
// tablet (touch) or a narrow browser window never gets it. Keep the
// min-width in step with $bp-desktop in scss/abstracts/_variables.scss.
//
// Shown by itself once, then not again for a few days after it's closed —
// a popup on every page would stop anyone using the app here at all. The
// topbar's "Open on phone" button reopens it any time.

import { track } from './analytics.js';

const DESKTOP = '(min-width: 1024px) and (hover: hover) and (pointer: fine)';
const DISMISSED_KEY = 'campbuddy-desktop-notice-dismissed';
const QUIET_DAYS = 7;

function recentlyDismissed() {
  try {
    const at = Number(localStorage.getItem(DISMISSED_KEY));
    return at > 1 && Date.now() - at < QUIET_DAYS * 86400000;
  } catch {
    return false;
  }
}

function rememberDismissed() {
  try {
    localStorage.setItem(DISMISSED_KEY, String(Date.now()));
  } catch {
    // Private mode / blocked storage — fine, it just reappears next visit.
  }
}

// The QR library is only pulled in for the visitors who actually see the
// popup — never on a phone.
async function drawQr(dialog) {
  const img = dialog.querySelector('.desktop-notice__qr');
  if (!img.hidden) return;

  try {
    const { default: QRCode } = await import('qrcode-generator');
    const qr = QRCode(0, 'M');
    qr.addData(location.href);
    qr.make();
    img.src = qr.createDataURL(5, 2);
    img.hidden = false;
  } catch {
    // The text on its own still says what to do.
  }
}

export function initDesktopNotice() {
  const dialog = document.getElementById('desktop-notice');
  if (!dialog || typeof dialog.showModal !== 'function') return;

  const query = window.matchMedia(DESKTOP);
  const reopen = document.getElementById('open-on-phone-btn');

  const open = (via) => {
    if (dialog.open) return;
    drawQr(dialog);
    dialog.showModal();
    track('desktop_notice_view', { via });
  };

  const syncButton = () => {
    if (reopen) reopen.hidden = !query.matches;
  };

  syncButton();
  query.addEventListener('change', syncButton);
  reopen?.addEventListener('click', () => open('button'));

  dialog.querySelectorAll('[data-action="dismiss"]').forEach((btn) => {
    btn.addEventListener('click', () => dialog.close());
  });
  // Clicking the dimmed backdrop closes it too.
  dialog.addEventListener('click', (e) => {
    if (e.target === dialog) dialog.close();
  });
  dialog.addEventListener('close', () => {
    rememberDismissed();
    track('desktop_notice_dismiss');
  });

  if (query.matches && !recentlyDismissed()) {
    open('auto');
  }
}
