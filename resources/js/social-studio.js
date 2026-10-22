// The Social media studio (partials/social-media.blade.php): a full-screen
// dialog with three tabs — posts, people cards, reels — each with its list on
// the left, the big preview in the middle and its edits on the right, so a
// post can be seen and changed without scrolling a long page. The posts list
// is static markup (picked here); the people and reels lists are built by
// social-kit.js and social-reels.js. #studio-posts / -people / -reels open it.

export function initStudio() {
  const dialog = document.querySelector('[data-studio]');
  if (!dialog || typeof dialog.showModal !== 'function') return;
  const tabs = [...dialog.querySelectorAll('[data-studio-tab]')];
  const panels = [...dialog.querySelectorAll('[data-studio-panel]')];

  const show = (tab) => {
    tabs.forEach((b) => b.setAttribute('aria-selected', String(b.dataset.studioTab === tab)));
    panels.forEach((p) => { p.hidden = p.dataset.studioPanel !== tab; });
    dialog.dataset.tab = tab;
    try { history.replaceState(null, '', `#studio-${tab}`); } catch { /* not allowed here */ }
    document.dispatchEvent(new CustomEvent('studio:tab', { detail: tab }));
  };
  const open = (tab = 'posts') => {
    if (!dialog.open) dialog.showModal();
    document.documentElement.classList.add('cb-studio-open');
    show(tab);
  };

  dialog.addEventListener('close', () => {
    document.documentElement.classList.remove('cb-studio-open');
    try { history.replaceState(null, '', window.location.pathname + window.location.search); } catch { /* not allowed here */ }
    document.dispatchEvent(new CustomEvent('studio:close'));
  });

  dialog.querySelector('[data-studio-close]')?.addEventListener('click', () => dialog.close());
  tabs.forEach((b) => b.addEventListener('click', () => show(b.dataset.studioTab)));
  // Arrow keys move between the tabs, like any tab list.
  dialog.querySelector('[role="tablist"]')?.addEventListener('keydown', (e) => {
    if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
    const i = tabs.findIndex((b) => b.getAttribute('aria-selected') === 'true');
    const next = tabs[(i + (e.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length];
    next.focus();
    show(next.dataset.studioTab);
  });

  // Posts: one at a time.
  const items = [...dialog.querySelectorAll('[data-studio-item]')];
  const posts = [...dialog.querySelectorAll('[data-social-post]')];
  const pick = (key) => {
    items.forEach((b) => b.setAttribute('aria-current', String(b.dataset.studioItem === key)));
    posts.forEach((p) => { p.hidden = p.dataset.socialPost !== key; });
    items.find((b) => b.dataset.studioItem === key)?.scrollIntoView({ block: 'nearest' });
  };
  items.forEach((b) => b.addEventListener('click', () => pick(b.dataset.studioItem)));
  if (items[0]) pick(items[0].dataset.studioItem);

  // The launch buttons (and the posting plan's posts, which open their own post).
  document.querySelectorAll('[data-studio-open]').forEach((b) => b.addEventListener('click', () => {
    open(b.dataset.studioOpen);
    if (b.dataset.studioPost) pick(b.dataset.studioPost);
  }));

  const hash = window.location.hash.match(/^#studio-(posts|people|reels)$/);
  if (hash) open(hash[1]);
}
