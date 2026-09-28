// Deals with Offer::capture_leads enabled (admin-configured per deal,
// §7) ask for Name/Email/Mobile before the deal opens. This never feeds
// any analytics/tracking call with the submitted name/email/mobile. Only
// the offer's own id/title (admin-authored, public) and the fact that a
// lead was submitted are reported — never `body` or anything read from
// the form. analytics.js's allowlist has no param that could carry them.

import { apiMutate } from './api.js';
import { linkDomain, track } from './analytics.js';
import { openInAppBrowser } from './in-app-browser.js';
import { render } from './template.js';

export function initDealLeadCapture(eventSlug) {
  document.querySelectorAll('[data-lead-offer-id]').forEach((btn) => {
    btn.addEventListener('click', () => openLeadForm(eventSlug, btn.dataset));
  });
}

function openLeadForm(eventSlug, { leadOfferId, leadOfferUrl, leadOfferTitle }) {
  const dialog = render('tpl-deal-lead-dialog', { title: leadOfferTitle });

  const close = () => {
    dialog.close();
    dialog.remove();
  };

  track('deal_lead_form_open', { offer_id: leadOfferId, offer_title: leadOfferTitle });

  dialog.querySelector('[data-action="close"]').addEventListener('click', () => {
    track('deal_lead_form_cancel', { offer_id: leadOfferId });
    close();
  });

  dialog.querySelector('[data-lead-form]').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    const errorEl = dialog.querySelector('[data-lead-error]');
    const submitBtn = form.querySelector('button[type="submit"]');
    const body = {
      name: form.name.value.trim(),
      email: form.email.value.trim(),
      mobile: form.mobile.value.trim() || null,
    };

    submitBtn.disabled = true;
    errorEl.hidden = true;

    try {
      await apiMutate(eventSlug, `/offers/${leadOfferId}/leads`, 'POST', body);
      track('generate_lead', { offer_id: leadOfferId, offer_title: leadOfferTitle });
      track('deal_open', { offer_title: leadOfferTitle, link_domain: linkDomain(leadOfferUrl), lead_capture: true });
      close();
      openInAppBrowser(leadOfferUrl, leadOfferTitle);
    } catch {
      errorEl.textContent = "Couldn't submit that — check your details and try again.";
      errorEl.hidden = false;
      submitBtn.disabled = false;
    }
  });

  document.body.appendChild(dialog);
  dialog.showModal();
}
