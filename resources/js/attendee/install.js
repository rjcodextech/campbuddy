// Header "Install app" button — wraps the beforeinstallprompt flow
// (Android/desktop Chrome) with an iOS Safari fallback that walks
// through the manual "Add to Home Screen" steps instead, since iOS
// never fires beforeinstallprompt at all.

import { render } from './template.js';

function isIosSafari() {
  const ua = navigator.userAgent;
  return /iP(hone|ad|od)/.test(ua) && /WebKit/.test(ua) && !/CriOS|FxiOS/.test(ua);
}

function isStandalone() {
  return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
}

export function initInstallPrompt() {
  const btn = document.getElementById('install-app-btn');
  if (!btn || isStandalone()) return;

  let deferredPrompt = null;

  if (isIosSafari()) {
    btn.hidden = false;
    btn.addEventListener('click', showIosInstallSteps);
    return;
  }

  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    btn.hidden = false;
  });

  btn.addEventListener('click', async () => {
    if (!deferredPrompt) return;
    btn.hidden = true;
    deferredPrompt.prompt();
    await deferredPrompt.userChoice;
    deferredPrompt = null;
  });

  window.addEventListener('appinstalled', () => {
    btn.hidden = true;
    deferredPrompt = null;
  });
}

function showIosInstallSteps() {
  const dialog = render('tpl-install-ios-dialog');
  dialog.querySelector('[data-action="close"]').addEventListener('click', () => {
    dialog.close();
    dialog.remove();
  });
  document.body.appendChild(dialog);
  dialog.showModal();
}
