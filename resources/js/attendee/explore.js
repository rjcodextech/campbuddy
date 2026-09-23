// Explore: Sponsors/Deals/Event Info are fully server-
// rendered — the client behavior is switching between the sub-sections,
// plus opening sponsor/deal links in the in-app browser instead of
// fully leaving CampBuddy.

export function renderExplore() {
  document.querySelectorAll('[data-explore-tab]').forEach((btn) => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('[data-explore-tab]').forEach((b) => {
        b.setAttribute('aria-selected', String(b === btn));
        b.classList.toggle('btn--outline', b !== btn);
      });
      document.querySelectorAll('[data-explore-panel]').forEach((panel) => {
        panel.hidden = panel.dataset.explorePanel !== btn.dataset.exploreTab;
      });
    });
  });

  document.querySelectorAll('[data-inapp-url]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const { openInAppBrowser } = await import('./in-app-browser.js');
      openInAppBrowser(btn.dataset.inappUrl, btn.dataset.inappTitle);
    });
  });

  // Deep-link support (e.g. Quest's "View sponsors"/"Find people"
  // actions linking to /explore?tab=sponsors) — pre-selects the matching
  // sub-tab instead of always landing on People.
  const requestedTab = new URLSearchParams(location.search).get('tab');
  const requestedBtn = requestedTab && document.querySelector(`[data-explore-tab="${requestedTab}"]`);
  if (requestedBtn) requestedBtn.click();
}
