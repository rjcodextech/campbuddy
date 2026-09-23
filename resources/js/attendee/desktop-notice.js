// "Best on your phone" notice for laptop/desktop visitors. CampBuddy is
// designed one-handed, for a phone, and the app is full width at every
// screen size — so on a big, mouse-driven screen it reads better on a
// phone, and this says so (with a QR code of the current page to make the
// switch one scan). Markup: attendee/partials/desktop-notice.blade.php.
//
// "Desktop-class" = a wide viewport AND a precise, hovering pointer, so a
// tablet (touch) or a narrow browser window never gets it. Keep the
// min-width in step with $bp-desktop in scss/abstracts/_variables.scss.
// Dismissal is remembered per browser; if storage is unavailable it just
// shows again next visit.

import { track } from './analytics.js';

const DESKTOP = '(min-width: 1024px) and (hover: hover) and (pointer: fine)';
const DISMISSED_KEY = 'campbuddy-desktop-notice-dismissed';

function wasDismissed() {
  try {
    return localStorage.getItem(DISMISSED_KEY) === '1';
  } catch {
    return false;
  }
}

function rememberDismissed() {
  try {
    localStorage.setItem(DISMISSED_KEY, '1');
  } catch {
    // Private mode / blocked storage — fine, it just reappears next visit.
  }
}

// The QR library is only pulled in for the visitors who actually see the
// notice — never on a phone.
async function drawQr(notice) {
  const img = notice.querySelector('.desktop-notice__qr');

  try {
    const { default: QRCode } = await import('qrcode-generator');
    const qr = QRCode(0, 'M');
    qr.addData(location.href);
    qr.make();
    img.src = qr.createDataURL(4, 8);
    img.hidden = false;
  } catch {
    // The text on its own still says what to do.
  }
}

export function initDesktopNotice() {
  const notice = document.getElementById('desktop-notice');
  if (!notice || wasDismissed()) return;

  const query = window.matchMedia(DESKTOP);
  let qrDrawn = false;

  const sync = () => {
    notice.hidden = !query.matches;

    if (query.matches && !qrDrawn) {
      qrDrawn = true;
      drawQr(notice);
    }
  };

  sync();
  query.addEventListener('change', sync);

  notice.querySelector('[data-action="dismiss"]').addEventListener('click', () => {
    query.removeEventListener('change', sync);
    notice.hidden = true;
    rememberDismissed();
    track('desktop_notice_dismiss');
  });
}
