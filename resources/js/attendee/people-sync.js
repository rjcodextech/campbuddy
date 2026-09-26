// Tells the app's other open windows — a second browser tab, or the installed app
// next to a tab — that someone's record changed, so what they show follows at
// once instead of when you next come back to them.
//
// It only carries "something changed for this event"; each window then reads the
// records itself. Nothing personal is sent, and nothing leaves the device (a
// BroadcastChannel is same-browser, same-site only). Where the browser has no
// BroadcastChannel this does nothing and the windows catch up when they are
// next opened, as before.

const NAME = 'campbuddy-people';

let shared = null;

function channel() {
  if (typeof BroadcastChannel === 'undefined') return null;

  if (!shared) {
    try {
      shared = new BroadcastChannel(NAME);
      shared.unref?.(); // Node only (the tests): an open channel must not keep the process alive.
    } catch {
      return null;
    }
  }

  return shared;
}

/** Call after a person's record changed on this window. Other windows hear it, this one does not. */
export function notifyPeopleChanged(eventId) {
  try {
    channel()?.postMessage({ type: 'people', eventId });
  } catch {
    // A closed or blocked channel: the other windows catch up when they are opened.
  }
}

/**
 * @param {(eventId: number) => void} handler  called when another window changed someone
 * @returns {() => void} stop listening
 */
export function onPeopleChanged(handler) {
  const ch = channel();
  if (!ch) return () => {};

  const listener = (event) => {
    if (event.data?.type === 'people') handler(event.data.eventId);
  };
  ch.addEventListener('message', listener);

  return () => ch.removeEventListener('message', listener);
}

/** Closes this window's channel (for tests). */
export function closePeopleChannel() {
  shared?.close();
  shared = null;
}
