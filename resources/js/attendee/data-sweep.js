// Automatic data sweep for finished WordCamps on attendee devices.
// Mirrors Native App's DataSweep: when an attendee chose to delete their
// event data after the retention period, and that time has arrived, this
// cleans up their local IndexedDB records (bookmarks, quest progress, meetings,
// met history, discovery/share tokens) and leaves discovery on the server.

import { apiMutate } from './api.js';
import { eraseEventData, exportAll, kvGet, kvSet } from './db.js';

export function isDueForSweep(choice, now = Date.now()) {
  if (!choice || typeof choice !== 'object') return false;
  if (choice.keep === true || choice.erasedAt) return false;
  return Number.isFinite(choice.deleteAt) && choice.deleteAt > 0 && choice.deleteAt <= now;
}

export async function runDataSweep(now = Date.now()) {
  let dump = null;
  try {
    dump = await exportAll();
  } catch {
    return [];
  }

  const kv = dump.kv ?? {};
  const swept = [];

  for (const [key, value] of Object.entries(kv)) {
    if (!key.startsWith('dataChoice:')) continue;
    const eventId = key.slice('dataChoice:'.length);
    if (!eventId) continue;

    if (!isDueForSweep(value, now)) continue;

    // Best-effort: leave server discovery before wiping local token
    const discovery = kv[`discovery:${eventId}`];
    if (discovery?.discoveryId && discovery?.ownerToken) {
      try {
        const slug = discovery.eventSlug || eventId;
        await apiMutate(slug, `/discovery/${discovery.discoveryId}`, 'DELETE', null, discovery.ownerToken);
      } catch {
        // Continue wiping locally even if server call fails
      }
    }

    try {
      await eraseEventData(eventId);
      await kvSet(key, { ...value, erasedAt: now });
      swept.push(eventId);
    } catch {
      // Retried on next launch
    }
  }

  return swept;
}
