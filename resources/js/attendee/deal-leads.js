// Deals with Offer::capture_leads enabled (admin-configured per deal,
// §7) ask for contact details before the deal opens — the fields that
// deal's own form uses (DealForm: name / company / email / phone, each
// off, optional or required, plus products to tick). This never feeds
// any analytics/tracking call with what was typed or ticked. Only the
// offer's own id/title (admin-authored, public) and the fact that a lead
// was submitted are reported — never `body` or anything read from the
// form. analytics.js's allowlist has no param that could carry them.

import { apiMutate } from './api.js';
import { linkDomain, track } from './analytics.js';
import { openInAppBrowser } from './in-app-browser.js';
import { render } from './template.js';
import { DEFAULT_FORM, TEXT_FIELDS, leadBody, readForm } from './deal-form.js';

export function initDealLeadCapture(eventSlug) {
  document.querySelectorAll('[data-lead-offer-id]').forEach((btn) => {
    btn.addEventListener('click', () => openLeadForm(eventSlug, btn.dataset));
  });
}

function openLeadForm(eventSlug, { leadOfferId, leadOfferUrl, leadOfferTitle, leadNewTab, leadForm }) {
  const form = readForm(leadForm);
  const newTab = leadNewTab === '1';
  const dialog = render('tpl-deal-lead-dialog', {
    title: leadOfferTitle,
    intro: form.intro || undefined,
    'done-link': { attrs: { href: leadOfferUrl } },
  });
  const formEl = dialog.querySelector('[data-lead-form]');
  const picked = new Set();

  setUpFields(dialog, form);
  setUpChoices(dialog, form, picked);

  const close = () => {
    dialog.close();
    dialog.remove();
  };

  track('deal_lead_form_open', { offer_id: leadOfferId, offer_title: leadOfferTitle });

  dialog.querySelectorAll('[data-action="close"]').forEach((btn) => btn.addEventListener('click', () => {
    if (!formEl.hidden) track('deal_lead_form_cancel', { offer_id: leadOfferId });
    close();
  }));

  formEl.addEventListener('submit', async (e) => {
    e.preventDefault();
    const errorEl = dialog.querySelector('[data-lead-error]');
    const submitBtn = formEl.querySelector('button[type="submit"]');

    if (form.choices?.mode === 'required' && picked.size === 0) {
      errorEl.textContent = `Please choose at least one: ${form.choices.label}.`;
      errorEl.hidden = false;
      return;
    }

    const values = Object.fromEntries(TEXT_FIELDS.map((f) => [f, formEl.elements[f]?.value ?? '']));
    const body = leadBody(form, values, picked);

    submitBtn.disabled = true;
    errorEl.hidden = true;

    try {
      await apiMutate(eventSlug, `/offers/${leadOfferId}/leads`, 'POST', body);
      track('generate_lead', { offer_id: leadOfferId, offer_title: leadOfferTitle });
      track('deal_open', { offer_title: leadOfferTitle, link_domain: linkDomain(leadOfferUrl), lead_capture: true });

      if (newTab) {
        // A real link the attendee taps: opening a tab by script after the
        // network wait would be caught by pop-up blockers.
        formEl.hidden = true;
        const done = dialog.querySelector('[data-lead-done]');
        done.hidden = false;
        done.querySelector('a').addEventListener('click', () => setTimeout(close, 0));
        done.querySelector('a').focus();
      } else {
        close();
        openInAppBrowser(leadOfferUrl, leadOfferTitle);
      }
    } catch {
      errorEl.textContent = "Couldn't send that. Check your details and try again.";
      errorEl.hidden = false;
      submitBtn.disabled = false;
    }
  });

  document.body.appendChild(dialog);
  dialog.showModal();
}

/** Keeps the fields this deal asks for, with its labels, hints and required marks. */
function setUpFields(dialog, form) {
  for (const field of TEXT_FIELDS) {
    const wrap = dialog.querySelector(`[data-lead-field="${field}"]`);
    const config = form.fields[field] ?? DEFAULT_FORM.fields[field];

    if (!wrap) continue;
    if (config.mode === 'off') {
      wrap.remove();
      continue;
    }

    const required = config.mode === 'required';
    wrap.querySelector('[data-lead-label]').textContent = config.label;
    wrap.querySelector('input').required = required;
    if (!required) wrap.querySelector('[data-lead-req]').remove();

    const hint = config.hint || (required ? '' : 'Optional.');
    const hintEl = wrap.querySelector('[data-lead-hint]');
    if (hint) {
      hintEl.textContent = hint;
    } else {
      hintEl.remove();
      wrap.querySelector('input').removeAttribute('aria-describedby');
    }
  }
}

/** One chip per product; one pick only unless the deal allows more. */
function setUpChoices(dialog, form, picked) {
  const wrap = dialog.querySelector('[data-lead-field="choices"]');
  const choices = form.choices;

  if (!choices || choices.mode === 'off' || !choices.options?.length) {
    wrap.remove();
    return;
  }

  wrap.querySelector('[data-lead-label]').textContent = choices.label;
  if (choices.mode !== 'required') wrap.querySelector('[data-lead-req]').remove();
  const hintEl = wrap.querySelector('[data-lead-hint]');
  hintEl.textContent = choices.multiple ? 'Tap all that apply.' : 'Tap one.';

  const group = wrap.querySelector('[data-lead-choices]');
  for (const option of choices.options) {
    const chip = render('tpl-deal-lead-choice', { label: option });
    chip.addEventListener('click', () => {
      const on = !picked.has(option);
      if (!choices.multiple) {
        picked.clear();
        group.querySelectorAll('.chip').forEach((c) => setChip(c, false));
      }
      if (on) picked.add(option); else picked.delete(option);
      setChip(chip, on);
    });
    group.appendChild(chip);
  }
}

function setChip(chip, on) {
  chip.setAttribute('aria-pressed', String(on));
  chip.classList.toggle('chip--selected', on);
}
