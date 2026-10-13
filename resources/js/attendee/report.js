// "Report" on a discovery match: the sheet that asks what is wrong, and the
// call that tells the organizers (Admin → Event → Reports). The person is
// hidden on this phone by people.js right after. Nothing here reaches GA.

import { apiMutate } from './api.js';
import { getDeviceId } from './device.js';
import { render } from './template.js';

export const REASONS = ['spam', 'abusive', 'impersonation', 'other'];

/** What the server is sent: a known reason, a short trimmed note. */
export function reportBody(reason, note, deviceId) {
  return {
    device_id: deviceId,
    reason: REASONS.includes(reason) ? reason : 'other',
    note: (note ?? '').trim().slice(0, 300) || null,
  };
}

/** Opens the sheet; resolves to { reason, note }, or null when closed without sending. */
export function openReportSheet(name) {
  return new Promise((resolve) => {
    const dialog = render('tpl-report-sheet', { title: name || 'this person' });
    document.body.appendChild(dialog);

    let answer = null;
    const close = () => {
      dialog.close();
      dialog.remove();
      resolve(answer);
    };

    dialog.querySelector('[data-report-close]').addEventListener('click', close);
    dialog.addEventListener('cancel', (e) => {
      e.preventDefault();
      close();
    });
    dialog.addEventListener('click', (e) => {
      if (e.target === dialog) close();
    });
    dialog.querySelector('[data-report-send]').addEventListener('click', () => {
      answer = {
        reason: dialog.querySelector('input[name="report-reason"]:checked')?.value ?? 'other',
        note: dialog.querySelector('#report-note').value,
      };
      close();
    });

    dialog.showModal();
  });
}

export function sendReport(eventSlug, discoveryId, { reason, note }) {
  return apiMutate(eventSlug, `/discovery/${discoveryId}/report`, 'POST', reportBody(reason, note, getDeviceId()));
}
