// Export/Clear, reached contextually (not a nav tab). DP3 is the
// one non-obvious rule — clearing never silently drops an active
// discovery profile without a separate warning and offer to leave first.

import { apiMutate } from './api.js';
import { clearAll, exportAll, kvGet, kvSet } from './db.js';

export function mountDataControls(root, containerId) {
  const container = document.getElementById(containerId);
  if (!container) return;

  container.innerHTML = `
    <div class="card">
      <p style="font-weight:700;margin:0 0 4px">Your data</p>
      <p class="footer-note" style="text-align:left;margin:0 0 12px">Everything you've entered stays on this device. Export it or clear it any time.</p>
      <div style="display:flex;gap:8px">
        <button type="button" class="btn btn--outline btn--compact" id="dc-export">Export my data</button>
        <button type="button" class="btn btn--outline btn--danger btn--compact" id="dc-clear">Clear my data</button>
      </div>
    </div>
  `;

  document.getElementById('dc-export').addEventListener('click', () => exportData(root));
  document.getElementById('dc-clear').addEventListener('click', () => clearData(root));
}

async function exportData(root) {
  const dump = await exportAll();
  const blob = new Blob([JSON.stringify(dump, null, 2)], { type: 'application/json' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `campbuddy-export-${new Date().toISOString().slice(0, 10)}.json`;
  a.click();
  URL.revokeObjectURL(url);
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

  await clearAll();
  alert('Your local CampBuddy data has been cleared.');
  window.location.reload();
}
