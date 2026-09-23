// Deals with Offer::capture_leads enabled (admin-configured per deal,
// §7) ask for Name/Email/Mobile before the deal opens. This never feeds
// any analytics/tracking call with the submitted name/email/mobile — if
// a track() wrapper is ever added to this app, that must stay true here.

import { apiMutate } from './api.js';
import { openInAppBrowser } from './in-app-browser.js';

export function initDealLeadCapture(eventSlug) {
  document.querySelectorAll('[data-lead-offer-id]').forEach((btn) => {
    btn.addEventListener('click', () => openLeadForm(eventSlug, btn.dataset));
  });
}

function openLeadForm(eventSlug, { leadOfferId, leadOfferUrl, leadOfferTitle }) {
  const dialog = document.createElement('dialog');
  dialog.innerHTML = `
    <div class="dialog-card">
      <p style="font-weight:700;margin:0 0 4px">${escapeHtml(leadOfferTitle)}</p>
      <p class="footer-note" style="text-align:left;margin:0 0 14px">Share a few details and this deal will open right after — shared with the sponsor to process this deal.</p>
      <form data-lead-form>
        <label class="field"><span>Name</span><input type="text" name="name" placeholder="e.g. Priya Sharma" required maxlength="191"></label>
        <label class="field"><span>Email</span><input type="email" name="email" placeholder="you@example.com" required maxlength="191"></label>
        <label class="field"><span>Mobile (optional)</span><input type="tel" name="mobile" placeholder="e.g. 98765 43210" maxlength="32"></label>
        <p class="footer-note" style="text-align:left;color:var(--danger)" data-lead-error hidden></p>
        <div style="display:flex;gap:8px;margin-top:4px">
          <button type="button" class="btn btn--outline" data-action="close" style="flex:1">Cancel</button>
          <button type="submit" class="btn btn--primary" style="flex:1">Continue</button>
        </div>
      </form>
    </div>
  `;

  const close = () => {
    dialog.close();
    dialog.remove();
  };

  dialog.querySelector('[data-action="close"]').addEventListener('click', close);

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

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str ?? '';
  return div.innerHTML;
}
