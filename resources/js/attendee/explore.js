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
        track('deal_open', { offer_title: btn.dataset.inappTitle, link_domain: domain, lead_capture: false, target: btn.dataset.target || 'in_app_cta' });
      } else {
        track('sponsor_open', { sponsor_name: btn.dataset.inappTitle, link_domain: domain, target: btn.dataset.target || 'sponsor_chip' });
      }

      const { openInAppBrowser } = await import('./in-app-browser.js');
      openInAppBrowser(btn.dataset.inappUrl, btn.dataset.inappTitle);
    });
  });

  document.querySelectorAll('[data-sponsor-chip]').forEach((chip) => {
    chip.addEventListener('click', async (e) => {
      const name = chip.dataset.inappTitle || chip.dataset.trackSponsorName;
      if (!name) return;
      const url = chip.dataset.inappUrl;
      const domain = url ? linkDomain(url) : (chip.dataset.trackLinkDomain || 'booth');
      track('sponsor_open', {
        sponsor_name: name,
        link_domain: domain,
        target: 'sponsor_chip',
      });
      if (url && !chip.dataset.inappUrlAttached) {
        chip.dataset.inappUrlAttached = 'true';
        e.preventDefault();
        const { openInAppBrowser } = await import('./in-app-browser.js');
        openInAppBrowser(url, name);
      }
    });
  });

  // A deal that opens in a new tab is a plain link (so a referral or
  // affiliate link keeps its credit); only report it.
  document.querySelectorAll('[data-deal-link]').forEach((link) => {
    link.addEventListener('click', () => {
      track('deal_open', { offer_title: link.dataset.dealTitle, link_domain: linkDomain(link.href), lead_capture: false, target: link.dataset.target || 'external_cta' });
    });
  });

  // A deal's coupon code (or Info's wifi details): one tap copies it.
  document.querySelectorAll('[data-copy-code]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(btn.dataset.copyCode);
        const deal = btn.closest('[data-promo-id]');
        if (deal) track('deal_code_copy', { offer_id: deal.dataset.promoId, offer_title: deal.dataset.promoName, target: btn.dataset.target || 'copy_code' });
        else track('useful_link_click', { link_type: 'wifi_copy' });
        btn.textContent = 'Copied';
      } catch {
        btn.textContent = 'Copy failed';
      }
      setTimeout(() => { btn.textContent = 'Copy'; }, 2000);
    });
  });

  setUpCardDetails();
  setUpStealSheets();
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

// A deal's or Free Steal's "Details" (phones only, by CSS): the description
// and small print open and close under it.
export function setUpCardDetails(root = document) {
  root.querySelectorAll('[data-card-details]').forEach((btn) => {
    const card = btn.closest('.deal-card');
    card.classList.add('is-collapsible');
    btn.addEventListener('click', () => {
      const open = btn.getAttribute('aria-expanded') !== 'true';
      btn.setAttribute('aria-expanded', String(open));
      card.classList.toggle('is-open', open);
      if (open && card.dataset) {
        const title = card.dataset.promoName || card.dataset.promoCreative || '';
        const link = card.querySelector?.('.deal-card__cta')?.href || card.querySelector?.('[data-inapp-url]')?.dataset?.inappUrl || '';
        track('deal_open', {
          offer_title: title,
          link_domain: link ? linkDomain(link) : 'details',
          lead_capture: false,
          target: 'details_toggle',
        });
      }
    });
  });
}

// A Free Steal's row opens its sheet (a <dialog> inside the row, so the
// card's GA reporting still finds its button). The backdrop, Esc and the
// close button shut it; so does opening the link, which leaves for a new tab.
export function setUpStealSheets(root = document) {
  root.querySelectorAll('[data-steal-open]').forEach((btn) => {
    const dialog = btn.closest('.steal-row')?.querySelector('dialog');
    if (!dialog || typeof dialog.showModal !== 'function') return;

    const close = () => dialog.close();
    btn.addEventListener('click', () => {
      const card = btn.closest('.steal-row');
      if (card && card.dataset?.stealName) {
        const cta = dialog.querySelector?.('.deal-card__cta');
        const domain = cta?.dataset?.trackLinkDomain || (cta?.href ? linkDomain(cta.href) : '');
        track('free_steal_open', {
          offer_title: card.dataset.stealName,
          link_domain: domain || 'sheet',
          target: btn.dataset?.target || 'steal_row',
        });
        track('select_item', {
          ...STEAL_LIST,
          items: [stealItem(card)],
          target: btn.dataset?.target || 'steal_row',
        });
      }
      dialog.showModal();
    });
    dialog.addEventListener('click', (e) => {
      if (e.target === dialog) close();
    });
    dialog.querySelector('[data-steal-close]')?.addEventListener('click', close);
    dialog.querySelector('.deal-card__cta')?.addEventListener('click', () => setTimeout(close, 0));
  });
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

  cards.forEach((card) => card.querySelector('.deal-card__cta')?.addEventListener('click', () => {
    const cta = card.querySelector('.deal-card__cta');
    const target = cta?.dataset.target || (card.dataset.promoId ? 'deal_cta' : 'sheet_cta');
    return card.dataset.promoId
      ? track('select_promotion', { items: [promoItem(card)], target })
      : track('select_item', { ...STEAL_LIST, items: [stealItem(card)], target });
  }));
}
