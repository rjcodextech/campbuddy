// Prints the bottom tabs the app shows on a few panel pages, as JSON, so the PHP
// sync test can check every tab link is a real route (tests/Feature/AppSyncTest.php).
globalThis.window = {};
Object.defineProperty(globalThis, 'navigator', { value: { userAgent: 'node', platform: 'Linux', maxTouchPoints: 0 }, configurable: true });

const { tabsFor } = await import('../resources/js/organizer-native.js');

const pages = ['/admin', '/admin/events', '/admin/analytics', '/admin/events/7/edit', '/admin/events/7/reports', '/manager/events/7', '/manager/events/7/reports'];
console.log(JSON.stringify(Object.fromEntries(pages.map((p) => [p, tabsFor(p).map((t) => t.href)]))));
