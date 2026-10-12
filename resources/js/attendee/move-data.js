// "Take it with you" (partials/move-data.blade.php): after a WordCamp, bring
// the profile and the people met to the next one. Opens from the day-15 push
// (?move=1) and from the thank-you card's link, only when this phone holds a
// profile or people met for the event (nothing to bring: no sheet, no link).
// The PDF is made here too (export-pdf.js), with the event's quest titles
// from its thank-you card.

import { track } from './analytics.js';
import { holdsBringable } from './bring-data.js';
import { exportPdf } from './export-pdf.js';
import { showToast } from './toast.js';

/** Whether the sheet should open on this page load. Pure, for tests. */
export function shouldOpenMove(search) {
  return new URLSearchParams(search).get('move') === '1';
}

export async function initMoveData() {
  const dialog = document.querySelector('dialog[data-move-data]');
  if (!dialog) return;
  if (!(await holdsBringable(Number(dialog.dataset.eventId)))) return;

  const open = (surface) => {
    document.querySelectorAll('dialog[open]').forEach((d) => { if (d !== dialog) d.close(); });
    if (typeof dialog.showModal === 'function' && !dialog.open) dialog.showModal();
    track('move_sheet_view', { surface });
  };

  dialog.querySelector('[data-move-close]')?.addEventListener('click', () => dialog.close());
  dialog.addEventListener('click', (e) => { if (e.target === dialog) dialog.close(); });
  dialog.querySelectorAll('[data-move-action]').forEach((a) => a.addEventListener('click', () => track('move_sheet_action', { method: a.dataset.moveAction })));

  dialog.querySelector('[data-move-pdf]')?.addEventListener('click', async (e) => {
    const btn = e.currentTarget;
    const thanks = document.getElementById(`thank-you-${dialog.dataset.eventId}`);
    btn.disabled = true;
    track('move_sheet_action', { method: 'pdf' });
    try {
      let questTitles = {};
      try { questTitles = JSON.parse(thanks?.querySelector('[data-thank-you-quests]')?.textContent ?? '{}'); } catch { /* none */ }
      const made = await exportPdf({ id: dialog.dataset.eventId, name: thanks?.dataset.eventName ?? document.title, questTitles });
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

  // The thank-you card's link (hidden until now) opens it in place.
  document.querySelectorAll('[data-open-move]').forEach((link) => {
    link.hidden = false;
    link.addEventListener('click', (e) => {
      e.preventDefault();
      open('thank_you');
    });
  });

  if (shouldOpenMove(location.search)) {
    // After the thank-you card (if it opened too): one sheet at a time, this one on top.
    setTimeout(() => open('push'), 300);
  }
}
