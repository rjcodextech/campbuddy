// "Suggest a Free Steal" (Explore → Free Steals): a short form sent to the
// server for the CampBuddy team to review — nothing is published from it.
// Analytics hears only that a suggestion was sent, never what was typed.

import { apiMutate } from './api.js';
import { track } from './analytics.js';
import { render } from './template.js';

export function initFreeStealSuggest(eventSlug) {
  document.querySelectorAll('[data-suggest-steal]').forEach((btn) => {
    btn.addEventListener('click', () => openSuggestForm(eventSlug));
  });
}

/** "github.com/x" → "https://github.com/x"; anything that still isn't a web address → null. */
export function suggestionUrl(value) {
  const typed = value.trim();
  if (!typed) return null;

  const withScheme = /^[a-z][a-z0-9+.-]*:\/\//i.test(typed) ? typed : `https://${typed}`;
  try {
    const url = new URL(withScheme);
    return ['http:', 'https:'].includes(url.protocol) && url.hostname.includes('.') ? url.href : null;
  } catch {
    return null;
  }
}

function openSuggestForm(eventSlug) {
  const dialog = render('tpl-free-steal-suggest');
  const formEl = dialog.querySelector('[data-suggest-form]');
  const errorEl = dialog.querySelector('[data-suggest-error]');

  let sent = false;
  const close = () => {
    if (!sent) track('free_steal_suggest_cancel');
    dialog.close();
    dialog.remove();
  };
  dialog.querySelectorAll('[data-action="close"]').forEach((btn) => btn.addEventListener('click', close));
  track('free_steal_suggest_open');

  const fail = (message, field) => {
    errorEl.textContent = message;
    errorEl.hidden = false;
    field?.focus();
  };

  formEl.addEventListener('submit', async (e) => {
    e.preventDefault();
    const { name, url, maker, why, email, website } = formEl.elements;
    const submitBtn = formEl.querySelector('button[type="submit"]');
    const link = suggestionUrl(url.value);

    errorEl.hidden = true;
    if (!name.value.trim()) return fail('Please add the name of the tool.', name);
    if (!link) return fail('Please add a link to it, like https://github.com/…', url);

    submitBtn.disabled = true;
    try {
      await apiMutate(eventSlug, '/free-steal-suggestions', 'POST', {
        name: name.value.trim(),
        url: link,
        maker: maker.value.trim() || null,
        why: why.value.trim() || null,
        email: email.value.trim() || null,
        website: website.value || null,
      });
      sent = true;
      track('free_steal_suggest', {});
      formEl.hidden = true;
      const done = dialog.querySelector('[data-suggest-done]');
      done.hidden = false;
      done.querySelector('button').focus();
    } catch (error) {
      fail(error.userMessage || "Couldn't send that. Check your connection and try again.");
      submitBtn.disabled = false;
    }
  });

  document.body.appendChild(dialog);
  dialog.showModal();
}
