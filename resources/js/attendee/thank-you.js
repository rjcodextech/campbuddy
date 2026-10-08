// The after-event thank-you card (partials/thank-you.blade.php): opens once
// per phone per WordCamp, from day THANK_YOU_AFTER_DAYS after its last day —
// or at once when the day-3 push was tapped (?thanks=1) — and only on a phone
// that holds something for that event. On the picker it's the first such
// "Completed" WordCamp. A rating goes to the server without any name; the
// PDF is made on the phone (export-pdf.js).

import { apiMutate } from './api.js';
import { track } from './analytics.js';
import { exportAll, kvGet, kvSet } from './db.js';
import { getDeviceId } from './device.js';
import { exportPdf, hasEventData } from './export-pdf.js';
import { showToast } from './toast.js';

const seenKey = (eventId) => `thankYou:${eventId}`;

/** Whether this dialog's card should open now. Pure, for tests. */
export function shouldOpen({ daysSince, fromDay, forced, always = false, seen, hasData }) {
  // The "save your data" push (campbuddy:export-push, ?export=1) opens it
  // even when it was already seen: the PDF button is what that push is for.
  if (always) return true;
  if (seen) return false;
  if (forced) return true;
  return hasData && Number.isFinite(daysSince) && daysSince >= fromDay;
}

export async function initThankYou({ surface }) {
  const dialogs = [...document.querySelectorAll('dialog[data-thank-you]')];
  if (dialogs.length === 0) return;

  let dump = null;
  try {
    dump = await exportAll();
  } catch {
    return; // No storage: nothing of theirs to thank them for or export.
  }

  const params = new URLSearchParams(location.search);
  const forced = params.get('thanks') === '1';
  const always = params.get('export') === '1';

  for (const dialog of dialogs) {
    const eventId = dialog.dataset.eventId;
    wire(dialog, surface);

    let seen = null;
    try {
      seen = await kvGet(seenKey(eventId));
    } catch {
      // Treated as not seen.
    }

    const open = shouldOpen({
      daysSince: Number(dialog.dataset.daysSince),
      fromDay: Number(dialog.dataset.fromDay),
      forced: forced && surface === 'event',
      always: always && surface === 'event',
      seen: Boolean(seen),
      hasData: hasEventData(dump, eventId),
    });

    if (!open) continue;

    showWhenFree(dialog);
    kvSet(seenKey(eventId), { seenAt: Date.now() }).catch(() => {});
    track('thank_you_view', { surface });
    break; // One card at a time.
  }
}

// Another modal (the desktop notice) may be up first: wait for it.
function showWhenFree(dialog) {
  const other = [...document.querySelectorAll('dialog[open]')].find((d) => d !== dialog);
  if (other) {
    other.addEventListener('close', () => showWhenFree(dialog), { once: true });
    return;
  }
  if (typeof dialog.showModal === 'function') dialog.showModal();
}

function wire(dialog, surface) {
  if (dialog.dataset.wired) return;
  dialog.dataset.wired = '1';

  const event = {
    id: dialog.dataset.eventId,
    name: dialog.dataset.eventName,
    questTitles: readJson(dialog.querySelector('[data-thank-you-quests]')),
  };
  const form = dialog.querySelector('[data-thank-you-form]');
  const more = dialog.querySelector('[data-thank-you-more]');
  const sent = dialog.querySelector('[data-thank-you-sent]');
  const errorEl = dialog.querySelector('[data-thank-you-error]');

  dialog.querySelectorAll('[data-thank-you-close]').forEach((btn) => btn.addEventListener('click', () => dialog.close()));
  dialog.addEventListener('click', (e) => {
    if (e.target === dialog) dialog.close();
  });

  form.querySelectorAll('input[name="rating"]').forEach((input) => {
    input.addEventListener('change', () => {
      more.hidden = false;
      form.querySelectorAll('.thank-you__star').forEach((star, i) => star.classList.toggle('is-on', i < Number(input.value)));
    });
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const data = new FormData(form);
    const rating = Number(data.get('rating'));
    if (!rating) return;

    const button = form.querySelector('button[type="submit"]');
    button.disabled = true;
    errorEl.hidden = true;

    try {
      await apiMutate(dialog.dataset.eventSlug, '/feedback', 'POST', {
        device_id: getDeviceId(),
        rating,
        comment: String(data.get('comment') ?? '').trim() || null,
        website: String(data.get('website') ?? '') || null,
      });
      // The rating is a number GA can average; the comment never leaves for GA.
      track('event_feedback', { surface, rating });
      more.hidden = true;
      form.querySelector('.thank-you__stars').disabled = true;
      sent.hidden = false;
      kvSet(seenKey(event.id), { seenAt: Date.now(), rated: rating }).catch(() => {});
    } catch (error) {
      button.disabled = false;
      errorEl.textContent = error.userMessage ?? "Couldn't send. Check your connection and try again.";
      errorEl.hidden = false;
    }
  });

  dialog.querySelector('[data-thank-you-pdf]').addEventListener('click', async (e) => {
    const btn = e.currentTarget;
    btn.disabled = true;
    try {
      const made = await exportPdf(event);
      if (made) {
        track('data_export', { method: 'pdf' });
        showToast('Saved. Look for it in your downloads.');
      } else {
        showToast('Nothing saved for this WordCamp on this phone.');
      }
    } catch {
      showToast("Couldn't make the PDF. Try again.");
    } finally {
      btn.disabled = false;
    }
  });

  dialog.querySelector('[data-thank-you-next]')?.addEventListener('click', () => track('thank_you_next_click', { surface }));
}

function readJson(el) {
  try {
    return JSON.parse(el?.textContent ?? '{}');
  } catch {
    return {};
  }
}

// The picker's "Save your WordCamp Data" under each Completed card: the PDF,
// made right there. The event's name and quest titles come from its thank-you
// card on the same page.
export function initCompletedExports() {
  document.querySelectorAll('[data-export-event]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const dialog = document.getElementById(`thank-you-${btn.dataset.exportEvent}`);
      if (!dialog) return;

      btn.disabled = true;
      try {
        const made = await exportPdf({
          id: dialog.dataset.eventId,
          name: dialog.dataset.eventName,
          questTitles: readJson(dialog.querySelector('[data-thank-you-quests]')),
        });
        if (made) {
          track('data_export', { method: 'pdf' });
          showToast('Saved. Look for it in your downloads.');
        } else {
          showToast('Nothing from this WordCamp is saved on this phone.');
        }
      } catch {
        showToast("Couldn't make the PDF. Try again.");
      } finally {
        btn.disabled = false;
      }
    });
  });
}
