// What kind of device the app is running on — shared by the install button
// (install.js) and the reminder prompt (push.js), so they can't disagree.

// Every iPhone and iPad browser (Safari, Chrome, Edge, Firefox…) is WebKit
// underneath and works the same way for install and push. iPadOS 13+ Safari
// sends a desktop-Mac user agent; its touch screen is the giveaway.
export function isIos() {
  return /iP(hone|ad|od)/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
}

// Running as an installed app (home-screen icon, standalone window), not in a browser tab.
export function isStandalone() {
  return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
}

// Running inside the CampBuddy Android / iOS app (the Capacitor shell adds
// "CampBuddyApp/<version> (android|ios)" to the user agent — mobile-app/app).
const APP_UA = /CampBuddyApp\/(\d+\.\d+\.\d+)(?: \((android|ios)\))?/;

export function isNativeApp() {
  return APP_UA.test(navigator.userAgent);
}

/** 'android' | 'ios' | null (not the app). Falls back to the OS when the shell does not say. */
export function nativePlatform() {
  const match = navigator.userAgent.match(APP_UA);
  if (!match) return null;

  return match[2] ?? (isIos() ? 'ios' : 'android');
}

/** The app build, "1.0.0", or null outside the app. */
export function appVersion() {
  return navigator.userAgent.match(APP_UA)?.[1] ?? null;
}
