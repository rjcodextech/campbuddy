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
export function shouldOpen({ daysSince, fromDay, forced, always = false, seen, hasData, hasChoice }) {
  // The "save your data" push (campbuddy:export-push, ?export=1) opens it
  // even when it was already seen: the PDF button is what that push is for.
  if (always) return true;
  if (seen) return false;
  if (forced) return true;
  if (hasChoice === false && hasData && Number.isFinite(daysSince) && daysSince >= 1) return true;
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

    let choice = null;
    try {
      choice = await kvGet(`dataChoice:${eventId}`);
    } catch {
      // Treated as not chosen.
    }

    const open = shouldOpen({
      daysSince: Number(dialog.dataset.daysSince),
      fromDay: Number(dialog.dataset.fromDay),
      forced: forced && surface === 'event',
      always: always && surface === 'event',
      seen: Boolean(seen),
      hasData: hasEventData(dump, eventId),
      hasChoice: Boolean(choice),
    });

    if (!open) continue;

    showWhenFree(dialog);
    if (choice) {
      kvSet(seenKey(eventId), { seenAt: Date.now() }).catch(() => {});
    }
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

  // DPDPA 2023 after-event data retention choice
  const retentionConfirmBtn = dialog.querySelector('[data-retention-confirm]');
  const retentionStatus = dialog.querySelector('[data-retention-status]');
  const retentionRadios = dialog.querySelectorAll('input[type="radio"][name^="retention_choice_"]');

  kvGet(`dataChoice:${event.id}`).then((choice) => {
    if (!choice) return;
    if (choice.deletedNow) {
      if (retentionStatus) {
        retentionStatus.textContent = 'All data for this event was deleted from this device.';
        retentionStatus.hidden = false;
      }
      retentionRadios.forEach((r) => (r.disabled = true));
      if (retentionConfirmBtn) retentionConfirmBtn.disabled = true;
      return;
    }

    const val = choice.keep ? 'keep' : 'auto_delete';
    const targetRadio = dialog.querySelector(`input[type="radio"][name^="retention_choice_"][value="${val}"]`);
    if (targetRadio) targetRadio.checked = true;

    if (retentionStatus) {
      if (choice.keep) {
        retentionStatus.textContent = 'Active preference: Kept on this device.';
      } else if (choice.deleteAt) {
        const d = new Date(choice.deleteAt).toLocaleDateString();
        retentionStatus.textContent = `Active preference: Auto-deletes around ${d}.`;
      }
      retentionStatus.hidden = false;
    }
  }).catch(() => {});

  retentionRadios.forEach((radio) => {
    radio.addEventListener('change', () => {
      if (retentionConfirmBtn) retentionConfirmBtn.disabled = false;
    });
  });

  retentionConfirmBtn?.addEventListener('click', async () => {
    const chosen = dialog.querySelector('input[type="radio"][name^="retention_choice_"]:checked')?.value;
    if (!chosen) return;

    retentionConfirmBtn.disabled = true;

    if (chosen === 'delete_now') {
      const ok = typeof window !== 'undefined' && typeof window.confirm === 'function'
        ? window.confirm('Are you sure you want to delete all saved sessions, notes and quests for this event from this device now?')
        : true;
      if (!ok) {
        retentionConfirmBtn.disabled = false;
        return;
      }

      try {
        const disc = await kvGet(`discovery:${event.id}`);
        if (disc?.discoveryId && disc?.ownerToken) {
          await apiMutate(dialog.dataset.eventSlug, `/discovery/${disc.discoveryId}`, 'DELETE', null, disc.ownerToken);
        }
      } catch {}

      const { eraseEventData } = await import('./db.js');
      await eraseEventData(event.id);
      await kvSet(`dataChoice:${event.id}`, { keep: false, deleteAt: Date.now(), chosenAt: Date.now(), deletedNow: true });
      await kvSet(seenKey(event.id), { seenAt: Date.now() });
      track('after_event_retention_choice', { surface, choice: 'delete_now' });

      showToast('Saved data for this event has been erased.');
      if (retentionStatus) {
        retentionStatus.textContent = 'All data for this event was deleted from this device.';
        retentionStatus.hidden = false;
      }
      retentionRadios.forEach((r) => (r.disabled = true));
      return;
    }

    if (chosen === 'keep') {
      await kvSet(`dataChoice:${event.id}`, { keep: true, chosenAt: Date.now() });
      await kvSet(seenKey(event.id), { seenAt: Date.now() });
      track('after_event_retention_choice', { surface, choice: 'keep' });
      showToast('Preference saved: Data will stay on this device.');
      if (retentionStatus) {
        retentionStatus.textContent = 'Active preference: Kept on this device.';
        retentionStatus.hidden = false;
      }
    } else if (chosen === 'auto_delete') {
      const RETENTION_MS = 30 * 24 * 60 * 60 * 1000;
      const deleteAt = Date.now() + RETENTION_MS;
      await kvSet(`dataChoice:${event.id}`, { keep: false, deleteAt, chosenAt: Date.now() });
      await kvSet(seenKey(event.id), { seenAt: Date.now() });
      track('after_event_retention_choice', { surface, choice: 'auto_delete' });
      showToast('Preference saved: Will auto-delete after 30 days.');
      if (retentionStatus) {
        const d = new Date(deleteAt).toLocaleDateString();
        retentionStatus.textContent = `Active preference: Auto-deletes around ${d}.`;
        retentionStatus.hidden = false;
      }
    }
  });
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
