// Explore (§3.12, §3.7): Sponsors/Deals/Event Info are fully server-
// rendered — the only client behavior is switching between the three
// sub-sections. People (§3.4) joins this page in a later phase.

export function renderExplore() {
  document.querySelectorAll('[data-explore-tab]').forEach((btn) => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('[data-explore-tab]').forEach((b) => {
        b.setAttribute('aria-selected', String(b === btn));
        b.classList.toggle('btn--ghost', b !== btn);
      });
      document.querySelectorAll('[data-explore-panel]').forEach((panel) => {
        panel.hidden = panel.dataset.explorePanel !== btn.dataset.exploreTab;
      });
    });
  });
}
