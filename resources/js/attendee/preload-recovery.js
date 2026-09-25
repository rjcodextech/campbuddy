// After a deploy, a page that was already open can ask for a script chunk the
// new build no longer has (chunk names change with their content). Vite
// reports that as a `vite:preloadError` event; without this the feature just
// fails. The fix is what a fresh visit would do anyway: load the page again,
// which brings the new HTML and the new chunk names.
//
// Guarded so it can never loop: one reload, then a minute's grace. If the
// chunk is still missing after that it is a real problem, not a stale page,
// and the original error is left to show. Where session storage is blocked
// the guard can't be kept, so nothing is reloaded.

export const RELOAD_GUARD_KEY = 'campbuddy:preload-reload';
export const RELOAD_GUARD_MS = 60 * 1000;

export function installPreloadRecovery({ win = window, reload = () => win.location.reload(), now = () => Date.now() } = {}) {
  win.addEventListener('vite:preloadError', (event) => {
    let storage;
    let last = 0;

    try {
      storage = win.sessionStorage;
      last = Number(storage.getItem(RELOAD_GUARD_KEY)) || 0;
    } catch {
      return;
    }

    if (now() - last < RELOAD_GUARD_MS) return;

    try {
      storage.setItem(RELOAD_GUARD_KEY, String(now()));
    } catch {
      return;
    }

    event.preventDefault();
    reload();
  });
}
