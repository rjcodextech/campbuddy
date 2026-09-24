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
