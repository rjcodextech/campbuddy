// Export/Clear, reached contextually (not a nav tab). DP3 is the
// one non-obvious rule — clearing never silently drops an active
// discovery profile without a separate warning and offer to leave first.

import { apiMutate } from './api.js';
import { track } from './analytics.js';
import { clearAll, exportAll, kvGet, kvSet } from './db.js';
import { showToast } from './toast.js';

// The card itself is attendee/partials/data-controls.blade.php, included
// inside #data-controls on the pages that offer it — this only wires it.
export function mountDataControls(root, containerId) {
  const container = document.getElementById(containerId);
  if (!container) return;

  document.getElementById('dc-export').addEventListener('click', (e) => exportPdfFor(root, e.currentTarget));
  document.getElementById('dc-export-json')?.addEventListener('click', () => exportData(root));
  document.getElementById('dc-clear').addEventListener('click', () => clearData(root));
}

// "Save as PDF": this event's sessions, people, quests and the Camp Card.
async function exportPdfFor(root, btn) {
  btn.disabled = true;
  try {
    let questTitles = {};
    try {
      questTitles = JSON.parse(document.getElementById('dc-quests')?.textContent ?? '{}');
    } catch {
      // Quests then show by number.
    }

    const { exportPdf } = await import('./export-pdf.js');
    const made = await exportPdf({ id: root.dataset.eventId, name: root.dataset.eventName, questTitles });
    if (made) {
      track('data_export', { method: 'pdf' });
      showToast('Saved. Look for it in your downloads.');
    } else {
      showToast('Nothing saved for this WordCamp on this phone yet.');
    }
  } catch {
    showToast("Couldn't make the PDF. Try again.");
  } finally {
    btn.disabled = false;
  }
}

async function exportData(root) {
  const dump = await exportAll();
  const blob = new Blob([JSON.stringify(dump, null, 2)], { type: 'application/json' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `campbuddy-export-${new Date().toISOString().slice(0, 10)}.json`;
  a.click();
  // Revoking straight away cancels the download on iOS Safari and some Firefox versions.
  setTimeout(() => URL.revokeObjectURL(url), 10000);
  track('data_export', { method: 'json' });
}

async function clearData(root) {
  const eventId = Number(root.dataset.eventId);
  const eventSlug = root.dataset.eventSlug;
  const discoveryKey = `discovery:${eventId}`;
  const discovery = await kvGet(discoveryKey);

  // DP3: an active discovery profile is a server-side thing — clearing
  // the device doesn't touch it, so this is warned about separately and
  // offered as a distinct step, not silently skipped.
  if (discovery) {
    const leaveFirst = confirm(
      "You're still discoverable to other attendees. Leave attendee discovery first? " +
      '(Choosing Cancel keeps your discovery profile active even after clearing local data.)'
    );

    if (leaveFirst) {
      try {
        await apiMutate(eventSlug, `/discovery/${discovery.discoveryId}`, 'DELETE', null, discovery.ownerToken);
      } catch {
        // Proceed with the local clear regardless — the profile will
        // still expire with the event if this call failed.
      }
    }
  }

  const confirmed = confirm(
    'This permanently deletes your onboarding answers, Camp Card, Quest progress, and saved sessions from this device. This cannot be undone. Continue?'
  );

  if (!confirmed) return;

  // A Camp Card shown on the attendee list (card-share.js) is taken off too:
  // without the token kept here nobody could remove it any more.
  const shared = await kvGet(`campCardShare:${eventId}`).catch(() => null);
  if (shared) {
    try {
      await apiMutate(eventSlug, `/camp-card-share/${shared.shareId}`, 'DELETE', null, shared.ownerToken);
    } catch {
      // Clear locally regardless; it stays hidden after the event's retention window.
    }
  }

  await clearAll();
  track('data_clear');
  alert('Your local CampBuddy data has been cleared.');
  window.location.reload();
}
