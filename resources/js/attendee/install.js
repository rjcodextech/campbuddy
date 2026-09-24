// Header "Install app" button. Browsers split three ways on installing a
// web app, and the button has to work on all of them:
//
//   - Chrome, Edge, Samsung Internet, Opera… (Android and desktop) hand the
//     page a `beforeinstallprompt` event; the button shows once it arrives
//     and opens the browser's own install dialog.
//   - iPhone/iPad (Safari, Chrome, Edge, Firefox — all WebKit underneath) and
//     Firefox on Android never fire that event, but can install by hand, so
//     the button is always there and walks through the steps.
//   - In-app browsers (Instagram, Facebook, LinkedIn… and Android WebViews)
//     can't install anything at all; the button says to open the page in the
//     real browser first, rather than hiding and leaving people wondering.
//
// Nothing is shown once the app is running installed (standalone), and on a
// desktop browser that can't install web apps (Firefox, Safari) the button
// simply never appears.

import { track } from './analytics.js';
import { isIos, isStandalone } from './platform.js';
import { render } from './template.js';

const IN_APP_BROWSER = /FBAN|FBAV|FBIOS|FB_IAB|Instagram|LinkedInApp|Snapchat|MicroMessenger|\bLine\/|Twitter|Pinterest|TikTok|musical_ly|; wv\)/;

function detectPlatform() {
  const ua = navigator.userAgent;

  if (IN_APP_BROWSER.test(ua)) return 'in_app_browser';

  if (isIos()) return 'ios';

  if (/Android/.test(ua) && /Firefox\//.test(ua)) return 'android_firefox';

  return 'native';
}

// Which list of steps the dialog shows for a platform.
const STEPS = { ios: 'ios', in_app_browser: 'in-app', android_firefox: 'menu', native: 'menu' };

export function initInstallPrompt() {
  const btn = document.getElementById('install-app-btn');
  if (!btn || isStandalone()) return;

  const platform = detectPlatform();
  let deferredPrompt = null;

  // No install prompt to wait for on these — the button is always shown.
  if (platform !== 'native') btn.hidden = false;

  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    btn.hidden = false;
  });

  btn.addEventListener('click', async () => {
    if (!deferredPrompt) {
      // Also reached on a native browser after its prompt was dismissed: an
      // install prompt can only be used once, so this points at the browser
      // menu instead of leaving a button that does nothing.
      track('install_prompt_open', { platform });
      showInstallSteps(STEPS[platform]);
      return;
    }

    const prompt = deferredPrompt;
    deferredPrompt = null;
    track('install_prompt_open', { platform: 'native' });
    prompt.prompt();
    const { outcome } = await prompt.userChoice;
    track('install_prompt_result', { outcome });

    if (outcome === 'accepted') btn.hidden = true;
  });

  window.addEventListener('appinstalled', () => {
    track('install_complete');
    btn.hidden = true;
    deferredPrompt = null;
  });
}

function showInstallSteps(steps) {
  const dialog = render('tpl-install-dialog');

  // One dialog holds every list of steps; only this platform's is kept.
  dialog.querySelectorAll('[data-install-steps]').forEach((list) => {
    if (list.dataset.installSteps !== steps) list.remove();
  });

  dialog.querySelector('[data-action="close"]').addEventListener('click', () => dialog.close());
  dialog.addEventListener('close', () => dialog.remove());
  document.body.appendChild(dialog);
  dialog.showModal();
}
