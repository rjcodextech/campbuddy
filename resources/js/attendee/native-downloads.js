// Downloads inside the Android / iOS app. A browser saves a file when a page
// clicks <a download>; the app WebView does not, so those clicks are turned
// into the phone share sheet (save to Files, send on WhatsApp, open in
// Calendar...). Covers the attendee pages and the admin / manager panel:
// QR codes, social images, deal-lead and roster exports, calendar files.
//
// Loaded only in the app (native-bridge.js, organizer-native.js).

const EXPORT_PATH = /\/export(\/|\?|$)/;

/** The file name a response asks for, else a name made from the link. */
export function filenameFrom(disposition, url, fallback = 'campbuddy') {
  const star = /filename\*\s*=\s*(?:UTF-8'')?([^;]+)/i.exec(disposition ?? '');
  const plain = /filename\s*=\s*"?([^";]+)"?/i.exec(disposition ?? '');
  const raw = star?.[1] ?? plain?.[1];

  if (raw) {
    try {
      return decodeURIComponent(raw.trim());
    } catch {
      return raw.trim();
    }
  }

  const last = new URL(url, 'https://campbuddy.club').pathname.split('/').filter(Boolean).pop();

  return last && /\.[a-z0-9]{2,5}$/i.test(last) ? last : fallback;
}

/** Whether a link is a file to save, not a page to open. */
export function isDownloadLink(anchor, origin) {
  if (!anchor?.href) return false;
  if (anchor.hasAttribute('download')) return true;

  try {
    const url = new URL(anchor.href, origin);

    return url.origin === origin && EXPORT_PATH.test(url.pathname);
  } catch {
    return false;
  }
}

async function save(anchor) {
  const response = await fetch(anchor.href, { credentials: 'same-origin' });
  if (!response.ok) throw new Error(`download ${response.status}`);

  const blob = await response.blob();
  const name = anchor.getAttribute('download') || filenameFrom(response.headers.get('content-disposition'), anchor.href);
  const { saveFile } = await import('./native-bridge.js');

  await saveFile(blob, name, name);
}

function handle(anchor) {
  save(anchor).catch(() => {
    // Offline or refused: nothing to save, and nothing worth a crash.
  });
}

let installed = false;

export function installDownloads() {
  if (installed) return;
  installed = true;

  // A script building a link and clicking it (QR codes, social images, ...).
  const nativeClick = HTMLAnchorElement.prototype.click;
  HTMLAnchorElement.prototype.click = function click() {
    if (this.hasAttribute('download') && this.href) {
      handle(this);
      return;
    }

    nativeClick.call(this);
  };

  // A person tapping a link that is in the page (exports, download buttons).
  document.addEventListener(
    'click',
    (event) => {
      const anchor = event.target instanceof Element ? event.target.closest('a') : null;
      if (!isDownloadLink(anchor, location.origin)) return;

      event.preventDefault();
      handle(anchor);
    },
    true
  );
}
