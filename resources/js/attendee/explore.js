// Explore: Sponsors/Deals/Event Info are fully server-
// rendered — the client behavior is switching between the sub-sections,
// plus opening sponsor/deal links in the in-app browser instead of
// fully leaving CampBuddy.

import { linkDomain, track } from './analytics.js';
import { setSectionTitle } from './page-title.js';

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
      setSectionTitle(btn.textContent.trim());
      track('explore_tab_view', { tab: btn.dataset.exploreTab });
    });
  });

  document.querySelectorAll('[data-inapp-url]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      // The panel the button sits in says whether it's a sponsor or a deal
      // (a lead-capturing deal is reported by deal-leads.js instead).
      const panel = btn.closest('[data-explore-panel]')?.dataset.explorePanel;
      const domain = linkDomain(btn.dataset.inappUrl);

      if (panel === 'deals') {
        track('deal_open', { offer_title: btn.dataset.inappTitle, link_domain: domain, lead_capture: false });
      } else {
        track('sponsor_open', { sponsor_name: btn.dataset.inappTitle, link_domain: domain });
      }

      const { openInAppBrowser } = await import('./in-app-browser.js');
      openInAppBrowser(btn.dataset.inappUrl, btn.dataset.inappTitle);
    });
  });

  // A deal that opens in a new tab is a plain link (so a referral or
  // affiliate link keeps its credit); only report it.
  document.querySelectorAll('[data-deal-link]').forEach((link) => {
    link.addEventListener('click', () => {
      track('deal_open', { offer_title: link.dataset.dealTitle, link_domain: linkDomain(link.href), lead_capture: false });
    });
  });

  // A deal's coupon code (or Info's wifi details): one tap copies it.
  document.querySelectorAll('[data-copy-code]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(btn.dataset.copyCode);
        const deal = btn.closest('[data-promo-id]');
        if (deal) track('deal_code_copy', { offer_id: deal.dataset.promoId, offer_title: deal.dataset.promoName });
        else track('useful_link_click', { link_type: 'wifi_copy' });
        btn.textContent = 'Copied';
      } catch {
        btn.textContent = 'Copy failed';
      }
      setTimeout(() => { btn.textContent = 'Copy'; }, 2000);
    });
  });

  reportCards();

  // Deep-link support (e.g. Quest's "View sponsors"/"Find people"
  // actions linking to /explore?tab=sponsors) — pre-selects the matching
  // sub-tab instead of always landing on People.
  const requestedTab = new URLSearchParams(location.search).get('tab');
  const requestedBtn = requestedTab && document.querySelector(`[data-explore-tab="${requestedTab}"]`);
  if (requestedBtn) requestedBtn.click();
}

// Deals as GA4 promotions, Free Steals as a GA4 item list (analytics.js):
// each card counts as viewed once per page load when half of it is on
// screen (a card on a hidden tab isn't), and as selected when its button is
// tapped. All of it is the card's own public content and its position.
const STEAL_LIST = { item_list_id: 'free_steals', item_list_name: 'Free Steals' };

export function promoItem(card) {
  return {
    promotion_id: `deal-${card.dataset.promoId}`,
    promotion_name: card.dataset.promoName,
    creative_name: card.dataset.promoCreative,
    creative_slot: `deals_${card.dataset.position}`,
    item_id: `deal-${card.dataset.promoId}`,
    item_name: card.dataset.promoCreative,
    index: Number(card.dataset.position),
  };
}

export function stealItem(card) {
  return {
    ...STEAL_LIST,
    item_id: `steal-${card.dataset.stealId}`,
    item_name: card.dataset.stealName,
    item_brand: card.dataset.stealMaker,
    item_category: card.dataset.stealCategory,
    index: Number(card.dataset.position),
  };
}

function reportCards() {
  const cards = [...document.querySelectorAll('[data-promo-id], [data-steal-id]')];
  const viewed = (card) => (card.dataset.promoId
    ? track('view_promotion', { items: [promoItem(card)] })
    : track('view_item_list', { ...STEAL_LIST, items: [stealItem(card)] }));

  if (typeof IntersectionObserver === 'function') {
    const seen = new IntersectionObserver((entries) => entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      seen.unobserve(entry.target);
      viewed(entry.target);
    }), { threshold: 0.5 });
    cards.forEach((card) => seen.observe(card));
  }

  cards.forEach((card) => card.querySelector('.deal-card__cta')?.addEventListener('click', () => (card.dataset.promoId
    ? track('select_promotion', { items: [promoItem(card)] })
    : track('select_item', { ...STEAL_LIST, items: [stealItem(card)] }))));
}
