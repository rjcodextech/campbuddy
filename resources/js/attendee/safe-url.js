// Addresses that come from data (an organizer's form, a scraped WordCamp page,
// an older copy saved on the phone) and end up as a link or a frame in the
// app. A `javascript:` or `data:` address there would run script in
// CampBuddy's own origin, so only plain web addresses get through — the same
// rule as the server's App\Support\SafeUrl::web(). Everything else is null,
// and the caller leaves the link out.

/** The address when it is an http(s) web address, otherwise null. */
export function webUrl(value) {
  if (typeof value !== 'string') return null;

  const url = value.trim();

  // No spaces, quotes or angle brackets anywhere, and no more than a long address needs.
  if (url.length > 2000 || !/^https?:\/\/[^\s<>"'`]+$/i.test(url)) return null;

  try {
    return ['http:', 'https:'].includes(new URL(url).protocol) ? url : null;
  } catch {
    return null;
  }
}

