// "Add to calendar" for the day planner: one .ics builder, delivered the way
// each platform actually handles it, plus a Google Calendar link.
//
//   iPhone / iPad   Safari only offers "Add to Calendar" for a calendar file
//                   the *server* sends, so (when online) the .ics built here is
//                   posted to /calendar.ics, which hands it straight back with
//                   the right headers. Nothing is stored or logged there.
//   Android, desktop  A downloaded .ics opens in Google Calendar, Samsung
//                   Calendar, Outlook or Apple Calendar.
//   Offline         Always the local download.
//
// The note someone writes about a person stays theirs: it only ever goes into
// the file on their own device (and, on iOS, through that echo endpoint).

import { eventDayKey } from './eventtime.js';

const PRODID = '-//CampBuddy//Day planner//EN';

/** Event facts the layout puts on #app. */
export function eventFacts() {
  const app = document.getElementById('app');
  const d = app?.dataset ?? {};

  return {
    name: d.eventName || 'WordCamp',
    slug: d.eventSlug || 'wordcamp',
    start: d.eventStart || null, // YYYY-MM-DD
    end: d.eventEnd || d.eventStart || null,
    venue: d.eventVenue || '',
    myDayUrl: d.myDayUrl ? new URL(d.myDayUrl, location.origin).href : location.href,
  };
}

/**
 * The day a meeting with no set time belongs on: today while the event is
 * on, otherwise its first day (or today if the event has no dates).
 */
export function defaultMeetingDay(facts = eventFacts()) {
  const today = eventDayKey(Date.now());
  if (!facts.start) return today;
  if (today >= facts.start && today <= (facts.end ?? facts.start)) return today;
  return facts.start;
}

/**
 * @typedef {object} CalendarItem
 * @property {string} uid
 * @property {string} title
 * @property {number} [startMs]      timed item
 * @property {number} [endMs]
 * @property {string} [date]         all-day item, YYYY-MM-DD
 * @property {string} [description]
 * @property {string} [location]
 * @property {string} [url]
 * @property {number} [alarmMinutes] timed items: minutes before; all-day: a morning nudge
 */

/** @param {CalendarItem[]} items */
export function buildIcs(items) {
  const stamp = utcStamp(Date.now());
  const lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', `PRODID:${PRODID}`, 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH'];

  for (const item of items) {
    lines.push('BEGIN:VEVENT', `UID:${item.uid}@campbuddy`, `DTSTAMP:${stamp}`);

    if (item.date) {
      lines.push(`DTSTART;VALUE=DATE:${item.date.replaceAll('-', '')}`, `DTEND;VALUE=DATE:${nextDay(item.date).replaceAll('-', '')}`);
    } else {
      const end = item.endMs && item.endMs > item.startMs ? item.endMs : item.startMs + 15 * 60 * 1000;
      lines.push(`DTSTART:${utcStamp(item.startMs)}`, `DTEND:${utcStamp(end)}`);
    }

    lines.push(`SUMMARY:${escapeText(item.title)}`);
    if (item.description) lines.push(`DESCRIPTION:${escapeText(item.description)}`);
    if (item.location) lines.push(`LOCATION:${escapeText(item.location)}`);
    if (item.url) lines.push(`URL:${item.url}`);

    if (item.alarmMinutes !== undefined) {
      lines.push(
        'BEGIN:VALARM',
        'ACTION:DISPLAY',
        `DESCRIPTION:${escapeText(item.title)}`,
        // All-day: 9 in the morning of that day. Timed: N minutes before.
        item.date ? 'TRIGGER:PT9H' : `TRIGGER:-PT${Math.max(0, item.alarmMinutes)}M`,
        'END:VALARM'
      );
    }

    lines.push('END:VEVENT');
  }

  lines.push('END:VCALENDAR');
  return lines.map(fold).join('\r\n') + '\r\n';
}

/** A Google Calendar "add event" link for one item (works on any device). */
export function googleCalendarUrl(item) {
  const dates = item.date
    ? `${item.date.replaceAll('-', '')}/${nextDay(item.date).replaceAll('-', '')}`
    : `${utcStamp(item.startMs)}/${utcStamp(item.endMs && item.endMs > item.startMs ? item.endMs : item.startMs + 15 * 60 * 1000)}`;

  const params = new URLSearchParams({ action: 'TEMPLATE', text: item.title, dates });
  if (item.description) params.set('details', item.description);
  if (item.location) params.set('location', item.location);

  return `https://calendar.google.com/calendar/render?${params}`;
}

/** Hands the file to the device's calendar the way that device supports. */
export function deliverIcs(filename, ics) {
  const safeName = `${String(filename).toLowerCase().replace(/[^a-z0-9-]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 60) || 'campbuddy'}.ics`;

  if (isAppleMobile() && navigator.onLine !== false) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/calendar.ics';
    form.hidden = true;

    for (const [name, value] of Object.entries({ ics, filename: safeName })) {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = name;
      input.value = value;
      form.appendChild(input);
    }

    document.body.appendChild(form);
    form.submit();
    setTimeout(() => form.remove(), 1000);
    return;
  }

  const url = URL.createObjectURL(new Blob([ics], { type: 'text/calendar;charset=utf-8' }));
  const a = document.createElement('a');
  a.href = url;
  a.download = safeName;
  document.body.appendChild(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(url), 10_000);
}

export function isAppleMobile() {
  return /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
}

// --- helpers -------------------------------------------------------------

function utcStamp(ms) {
  return new Date(ms).toISOString().replace(/[-:]/g, '').replace(/\.\d{3}/, '');
}

export function localDate(d) {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function nextDay(ymd) {
  const [y, m, d] = ymd.split('-').map(Number);
  return localDate(new Date(y, m - 1, d + 1));
}

function escapeText(value) {
  return String(value ?? '')
    .replace(/\\/g, '\\\\')
    .replace(/;/g, '\\;')
    .replace(/,/g, '\\,')
    .replace(/\r?\n/g, '\\n');
}

// RFC 5545: lines longer than 75 octets continue on the next line after a space.
function fold(line) {
  const encoder = new TextEncoder();
  if (encoder.encode(line).length <= 75) return line;

  const parts = [];
  let current = '';
  for (const char of line) {
    const limit = parts.length === 0 ? 75 : 74;
    if (encoder.encode(current + char).length > limit) {
      parts.push(current);
      current = char;
    } else {
      current += char;
    }
  }
  parts.push(current);
  return parts.join('\r\n ');
}
