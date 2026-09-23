// Header "Install app" button — wraps the beforeinstallprompt flow
// (Android/desktop Chrome) with an iOS Safari fallback that walks
// through the manual "Add to Home Screen" steps instead, since iOS
// never fires beforeinstallprompt at all.

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
  const dialog = document.createElement('dialog');
  dialog.innerHTML = `
    <div class="dialog-card">
      <p style="font-weight:700;margin:0 0 8px">Install CampBuddy</p>
      <p class="footer-note" style="text-align:left">Add CampBuddy to your home screen for the full app experience:</p>
      <ol class="ios-install-steps">
        <li>Tap the Share button in Safari</li>
        <li>Choose "Add to Home Screen"</li>
        <li>Open CampBuddy from your home screen</li>
      </ol>
      <button type="button" class="btn btn--primary btn--full" data-action="close">Got it</button>
    </div>
  `;
  dialog.querySelector('[data-action="close"]').addEventListener('click', () => {
    dialog.close();
    dialog.remove();
  });
  document.body.appendChild(dialog);
  dialog.showModal();
}
