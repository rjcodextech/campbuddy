// In-app browser: an iframe-based dialog for deal/sponsor links, so
// tapping one doesn't fully leave CampBuddy. Many third-party sites
// block framing (X-Frame-Options/CSP frame-ancestors) — there's no
// reliable way to detect that from JS before attempting the embed (a
// blocked cross-origin frame never fires a catchable error, it just
// silently never loads), so this uses a load-timeout heuristic and
// always keeps a visible "open in browser" escape hatch rather than
// pretending the detection is perfect.

import { render } from './template.js';

export function openInAppBrowser(url, title) {
  const label = title || url;
  const dialog = render('tpl-in-app-browser', {
    title: label,
    'open-link': { attrs: { href: url } },
    frame: { attrs: { src: url, title: label } },
    'fallback-link': { attrs: { href: url } },
  });

  const close = () => dialog.close();
  dialog.querySelector('[data-action="close"]').addEventListener('click', close);
  dialog.addEventListener('close', () => dialog.remove());
  dialog.addEventListener('cancel', close);

  const iframe = dialog.querySelector('iframe');
  const fallback = dialog.querySelector('.in-app-browser__fallback');
  let loaded = false;
  iframe.addEventListener('load', () => { loaded = true; });

  document.body.appendChild(dialog);
  dialog.showModal();

  setTimeout(() => {
    if (!loaded) fallback.hidden = false;
  }, 3000);
}
