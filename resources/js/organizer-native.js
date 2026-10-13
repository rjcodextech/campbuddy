// Admin and event manager panel inside the Android / iOS app. Loaded only
// there (app.js, behind the CampBuddyApp user agent) and only on /admin and
// /manager pages.
//
//   - phone fit: the page is drawn under the status bar, so it keeps clear of it
//   - alerts: once per phone the person is asked, then this phone's push token is
//     registered for them (e.g. "a profile was reported")
//   - sign-out removes that token first, so a signed-out phone gets nothing
//   - lock: after the app has been away a while, Face ID / fingerprint is needed
//
// The attendee side never loads this file.

import { appVersion, nativePlatform } from './attendee/platform.js';
import { pushSupported } from './attendee/native-env.js';

const ALERTS_KEY = 'campbuddy:organizer-alerts'; // 'on' | 'declined'
const TOKEN_KEY = 'campbuddy:organizer-token';
export const LOCK_AFTER_MS = 30 * 1000;

// --- pure helpers (unit tested) --------------------------------------------

/** 'admin' | 'manager' | null for a path of the panel. */
export function roleFromPath(pathname) {
  if (/^\/admin(\/|$)/.test(pathname)) return 'admin';
  if (/^\/manager(\/|$)/.test(pathname)) return 'manager';

  return null;
}

/**
 * Whether the lock screen is due: every cold start of the app, and after it
 * has been in the background for LOCK_AFTER_MS.
 */
export function shouldLock({ unlockedThisLaunch, hiddenAt, now }) {
  if (!unlockedThisLaunch) return true;

  return hiddenAt !== null && now - hiddenAt >= LOCK_AFTER_MS;
}

/**
 * The bottom tabs for a panel page: the panel's main places, or - inside one
 * event - that event's own sections. Manager pages outside an event have a
 * single place, so no bar. Each tab: { key, label, href, active }.
 */
export function tabsFor(pathname) {
  const path = pathname.replace(/\/+$/, '') || '/';
  const tab = (key, label, href, active) => ({ key, label, href, active });
  const admin = path.match(/^\/admin\/events\/(\d+)(\/|$)/);
  const manager = path.match(/^\/manager\/events\/(\d+)(\/|$)/);

  if (admin) {
    const base = `/admin/events/${admin[1]}`;

    return [
      tab('dashboard', 'Dashboard', '/admin', false),
      tab('events', 'Events', '/admin/events', false),
      tab('details', 'Details', `${base}/edit`, path === `${base}/edit`),
      tab('reports', 'Reports', `${base}/reports`, path.startsWith(`${base}/reports`)),
      tab('notifications', 'Alerts', `${base}/notifications`, path.startsWith(`${base}/notifications`)),
    ];
  }

  if (/^\/admin(\/|$)/.test(path)) {
    return [
      tab('dashboard', 'Dashboard', '/admin', path === '/admin'),
      tab('events', 'Events', '/admin/events', path === '/admin/events' || path.startsWith('/admin/events/')),
      tab('analytics', 'Analytics', '/admin/analytics', path.startsWith('/admin/analytics')),
      tab('errors', 'Errors', '/admin/errors', path.startsWith('/admin/errors')),
      tab('feedback', 'Feedback', '/admin/feedback', path.startsWith('/admin/feedback')),
    ];
  }

  if (manager) {
    const base = `/manager/events/${manager[1]}`;

    return [
      tab('events', 'My events', '/manager', false),
      tab('details', 'Details', base, path === base),
      tab('attendees', 'People', `${base}/attendees`, path.startsWith(`${base}/attendees`)),
      tab('reports', 'Reports', `${base}/reports`, path.startsWith(`${base}/reports`)),
      tab('notifications', 'Alerts', `${base}/notifications`, path.startsWith(`${base}/notifications`)),
    ];
  }

  return [];
}

// --- plugins ----------------------------------------------------------------

function plugin(name) {
  const cap = window.Capacitor;

  return cap?.Plugins?.[name] ?? cap?.registerPlugin?.(name) ?? null;
}

function csrf() {
  return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function remember(key, value) {
  try {
    localStorage.setItem(key, value);
  } catch {
    // Storage blocked: asked again next time.
  }
}

function recall(key) {
  try {
    return localStorage.getItem(key);
  } catch {
    return null;
  }
}

// --- phone fit --------------------------------------------------------------

function fitToPhone() {
  document.documentElement.classList.add('is-native-app');

  const viewport = document.querySelector('meta[name="viewport"]');
  if (viewport && !viewport.content.includes('viewport-fit')) viewport.content += ', viewport-fit=cover';
}

// --- bottom tabs --------------------------------------------------------------

const ICONS = {
  dashboard: 'M3 11l9-8 9 8v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z',
  events: 'M7 3v3M17 3v3M4 8h16M5 5h14a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1z',
  details: 'M5 4h14v16H5zM8 9h8M8 13h8M8 17h5',
  analytics: 'M4 20V10M10 20V4M16 20v-7M22 20H2',
  errors: 'M12 3l10 18H2zM12 10v5M12 18v.5',
  feedback: 'M4 5h16v11H9l-5 4z',
  reports: 'M5 21V4M5 4h11l-2 4 2 4H5',
  notifications: 'M6 17V11a6 6 0 0 1 12 0v6l2 2H4zM10 21h4',
  attendees: 'M9 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM3 20a6 6 0 0 1 12 0M16 11a2.5 2.5 0 1 0 0-5M17 14a5 5 0 0 1 4 6',
};

function showTabs() {
  const tabs = tabsFor(location.pathname);
  if (tabs.length < 2 || document.getElementById('app-tabbar')) return;

  const NS = 'http://www.w3.org/2000/svg';
  const bar = document.createElement('nav');
  bar.id = 'app-tabbar';
  bar.className = 'app-tabbar';
  bar.setAttribute('aria-label', 'Panel');

  for (const t of tabs) {
    const link = document.createElement('a');
    link.href = t.href;
    link.className = 'app-tabbar__item' + (t.active ? ' app-tabbar__item--active' : '');
    if (t.active) link.setAttribute('aria-current', 'page');

    const svg = document.createElementNS(NS, 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('width', '22');
    svg.setAttribute('height', '22');
    svg.setAttribute('fill', 'none');
    svg.setAttribute('stroke', 'currentColor');
    svg.setAttribute('stroke-width', '1.8');
    svg.setAttribute('stroke-linecap', 'round');
    svg.setAttribute('stroke-linejoin', 'round');
    svg.setAttribute('aria-hidden', 'true');
    const path = document.createElementNS(NS, 'path');
    path.setAttribute('d', ICONS[t.key]);
    svg.append(path);

    const label = document.createElement('span');
    label.textContent = t.label;
    link.append(svg, label);
    bar.append(link);
  }

  document.body.append(bar);
  document.documentElement.classList.add('has-app-tabbar');
}

// --- alerts -----------------------------------------------------------------

async function send(role, method, body) {
  const response = await fetch(`/${role}/app-push`, {
    method,
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
    credentials: 'same-origin',
    keepalive: true,
    body: JSON.stringify(body),
  });
  if (!response.ok) throw new Error(`app-push ${method} ${response.status}`);
}

function pushToken(push) {
  return new Promise((resolve, reject) => {
    const timer = setTimeout(() => reject(new Error('push token timeout')), 15000);
    push.addListener('registration', (result) => {
      clearTimeout(timer);
      resolve(result.value);
    });
    push.addListener('registrationError', (error) => {
      clearTimeout(timer);
      reject(new Error(error?.error ?? 'push registration failed'));
    });
    push.register();
  });
}

async function registerAlerts(role) {
  const push = plugin('PushNotifications');
  const choice = recall(ALERTS_KEY);
  if (!push || choice === 'declined' || !(await pushSupported())) return;

  try {
    if (choice !== 'on') {
      if (!confirm('Get an alert on this phone when someone reports a profile at your events?')) {
        remember(ALERTS_KEY, 'declined');
        return;
      }
      const asked = await push.requestPermissions();
      if (asked.receive !== 'granted') {
        remember(ALERTS_KEY, 'declined');
        return;
      }
    } else if ((await push.checkPermissions()).receive !== 'granted') {
      return;
    }

    await push.createChannel?.({ id: 'announcements', name: 'Organizer alerts', description: 'Reports and event alerts', importance: 4 });
    const token = await pushToken(push);
    await send(role, 'POST', { platform: nativePlatform(), token, app_version: appVersion() });
    remember(TOKEN_KEY, token);
    remember(ALERTS_KEY, 'on');
  } catch {
    // No Firebase yet, or offline: the panel works the same without alerts.
  }
}

/** Signing out takes this phone off the alert list first. */
function wireSignOut(role) {
  document.querySelectorAll('form[action$="/logout"]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
      const token = recall(TOKEN_KEY);
      if (!token || form.dataset.tokenDropped) return;

      event.preventDefault();
      form.dataset.tokenDropped = '1';
      try {
        await send(role, 'DELETE', { token });
        localStorage.removeItem(TOKEN_KEY);
      } catch {
        // Offline: sign-out still goes ahead.
      }
      form.submit();
    });
  });
}

// --- lock -------------------------------------------------------------------

function showLock(biometric, onUnlock) {
  if (document.getElementById('organizer-lock')) return;

  const screen = document.createElement('div');
  screen.id = 'organizer-lock';
  screen.setAttribute('role', 'alertdialog');
  screen.setAttribute('aria-modal', 'true');
  screen.setAttribute('aria-label', 'Locked');
  screen.style.cssText = 'position:fixed;inset:0;z-index:2147483647;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:16px;padding:24px;text-align:center;background:#fcf1db;color:#2b1014;font:16px Inter,system-ui,sans-serif';

  const heading = document.createElement('h1');
  heading.textContent = 'CampBuddy is locked';
  heading.style.cssText = 'margin:0;font-size:1.375rem;font-weight:700';
  const button = document.createElement('button');
  button.type = 'button';
  button.textContent = 'Unlock';
  button.style.cssText = 'min-height:48px;min-width:176px;border:0;border-radius:12px;background:#60121c;color:#fff;font:inherit;font-weight:600';
  screen.append(heading, button);
  document.body.append(screen);

  const ask = async () => {
    try {
      await biometric.verifyIdentity({ reason: 'Unlock the CampBuddy organizer panel', title: 'Unlock CampBuddy' });
      screen.remove();
      onUnlock();
    } catch {
      // Cancelled or failed: the button stays for another try.
    }
  };
  button.addEventListener('click', ask);
  ask();
}

async function wireLock() {
  const biometric = plugin('NativeBiometric');
  if (!biometric) return;

  let available = false;
  try {
    available = Boolean((await biometric.isAvailable())?.isAvailable);
  } catch {
    available = false;
  }
  if (!available) return;

  let unlocked = false;
  let hiddenAt = null;
  const check = () => {
    if (shouldLock({ unlockedThisLaunch: unlocked, hiddenAt, now: Date.now() })) {
      showLock(biometric, () => {
        unlocked = true;
        hiddenAt = null;
      });
    }
  };

  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden') hiddenAt = Date.now();
    else check();
  });
  check();
}

// --- entry ------------------------------------------------------------------

export function initOrganizerNative() {
  const role = roleFromPath(location.pathname);
  if (!role) return;

  fitToPhone();

  // Only a signed-in page has a sign-out form; the sign-in page needs nothing more.
  if (!document.querySelector('form[action$="/logout"]')) return;

  showTabs();
  wireSignOut(role);
  wireLock();
  registerAlerts(role);
}
