// In-app browser: an iframe-based dialog for deal/sponsor links, so
// tapping one doesn't fully leave CampBuddy. Many third-party sites
// block framing (X-Frame-Options/CSP frame-ancestors) — there's no
// reliable way to detect that from JS before attempting the embed (a
// blocked cross-origin frame never fires a catchable error, it just
// silently never loads), so this uses a load-timeout heuristic and
// always keeps a visible "open in browser" escape hatch rather than
// pretending the detection is perfect.

export function openInAppBrowser(url, title) {
  const dialog = document.createElement('dialog');
  dialog.className = 'in-app-browser';
  dialog.innerHTML = `
    <div class="in-app-browser__bar">
      <span class="in-app-browser__title">${escapeHtml(title || url)}</span>
      <div class="in-app-browser__actions">
        <a href="${escapeAttr(url)}" target="_blank" rel="noopener" class="topbar__icon-btn" aria-label="Open in browser">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6"/><path d="M10 14 21 3"/></svg>
        </a>
        <button type="button" class="topbar__icon-btn" data-action="close" aria-label="Close">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
      </div>
    </div>
    <div class="in-app-browser__body">
      <iframe src="${escapeAttr(url)}" title="${escapeAttr(title || url)}" referrerpolicy="no-referrer"></iframe>
      <div class="in-app-browser__fallback" hidden>
        <p class="footer-note">This site couldn't be shown here.</p>
        <a href="${escapeAttr(url)}" target="_blank" rel="noopener" class="btn btn--primary">Open in your browser →</a>
      </div>
    </div>
  `;

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

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str ?? '';
  return div.innerHTML;
}

function escapeAttr(str) {
  return escapeHtml(str);
}
