// Swaps an <img data-fallback="…"> to its fallback when its own source fails
// to load, so a missing or unreachable event logo shows CampBuddy's icon
// instead of a broken-image glyph (BR3: never a broken state).
//
// An image can fail before this script has run (the HTML is parsed and the
// request fired long before the bundle executes), so besides listening for
// future errors it sweeps the images that have *already* failed.

function applyFallback(img) {
  const fallback = img.dataset.fallback;

  // Once only: if the fallback itself is missing, don't loop forever.
  if (!fallback || img.dataset.fallbackApplied) return;

  img.dataset.fallbackApplied = 'true';
  img.src = fallback;
}

export function initImageFallbacks() {
  // 'error' doesn't bubble, so listen in the capture phase on the document.
  document.addEventListener(
    'error',
    (event) => {
      if (event.target instanceof HTMLImageElement) applyFallback(event.target);
    },
    true
  );

  document.querySelectorAll('img[data-fallback]').forEach((img) => {
    // complete + no pixels = finished loading and failed.
    if (img.complete && img.naturalWidth === 0) applyFallback(img);
  });
}
