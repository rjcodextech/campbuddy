// "Meet this person": pick anyone from the attendee list (or a discovery
// match), write yourself a note, optionally set a time — and they show up in
// My Day → My schedule, where you can tick them off as met.
//
// Everything is stored on this device only (db.js 'meetings'); the person
// isn't told. The same sheet edits an entry from My schedule.

import { track } from './analytics.js';
import { buildIcs, defaultMeetingDay, deliverIcs, eventFacts, googleCalendarUrl } from './calendar.js';
import { saveMeeting } from './db.js';
import { LABELS } from './people-state.js';
import { peopleStatus } from './people-status.js';
import { eventDayKey, fromEventInput, toEventInput } from './eventtime.js';
import { render } from './template.js';
import { showToast } from './toast.js';

/**
 * @param {object} opts
 * @param {number} opts.eventId
 * @param {object} opts.person   { personKey, name, avatarUrl, sub, source, links }
 * @param {object} [opts.existing] the saved meeting, when editing
 * @param {Function} [opts.onChange] called after save/remove with the row (or null)
 */
export function openMeetSheet({ eventId, person, existing = null, onChange = () => {} }) {
  const facts = eventFacts();
  // The time box shows and takes the venue's clock time (eventtime.js).
  const atValue = existing?.at ? toEventInput(new Date(existing.at).getTime()) : '';

  const dialog = render('tpl-meet-sheet', {
    avatar: { attrs: { src: person.avatarUrl || '/media/illustrations/avatar.svg' } },
    name: person.name || 'Anonymous attendee',
    sub: person.sub || null,
    note: { text: existing?.note ?? '' },
    'when-any': { attrs: { checked: !existing?.at } },
    'when-time': { attrs: { checked: Boolean(existing?.at) } },
    at: {
      attrs: {
        value: atValue,
        hidden: !existing?.at,
        min: facts.start ? `${facts.start}T00:00` : null,
        max: facts.end ? `${facts.end}T23:59` : null,
      },
    },
    calendar: true,
    remove: Boolean(existing),
    save: existing ? 'Save changes' : 'Save to My schedule',
  });
  dialog.querySelector('.meet-sheet__eyebrow').textContent = existing ? 'Person to meet' : 'Add to people to meet';

  document.body.appendChild(dialog);

  const noteEl = dialog.querySelector('#meet-note');
  const atEl = dialog.querySelector('#meet-at');
  const radios = dialog.querySelectorAll('input[name="meet-when"]');

  radios.forEach((r) =>
    r.addEventListener('change', () => {
      const timed = dialog.querySelector('input[name="meet-when"]:checked')?.value === 'time';
      atEl.hidden = !timed;
      if (timed && !atEl.value) atEl.value = suggestedTime(facts);
      if (timed) atEl.focus();
    })
  );

  const close = () => {
    dialog.close();
    dialog.remove();
  };

  dialog.querySelector('[data-meet-close]').addEventListener('click', close);
  dialog.addEventListener('cancel', (e) => {
    e.preventDefault();
    close();
  });
  // Tapping the dimmed backdrop closes it, like any bottom sheet.
  dialog.addEventListener('click', (e) => {
    if (e.target === dialog) close();
  });

  const current = () => {
    const timed = dialog.querySelector('input[name="meet-when"]:checked')?.value === 'time' && atEl.value;
    const atMs = timed ? fromEventInput(atEl.value) : NaN;

    return {
      name: person.name || null,
      avatarUrl: person.avatarUrl || null,
      sub: person.sub || null,
      source: person.source,
      links: person.links ?? [],
      note: noteEl.value.trim().slice(0, 280),
      at: Number.isFinite(atMs) ? new Date(atMs).toISOString() : null,
    };
  };

  dialog.querySelector('[data-meet-save]').addEventListener('click', async () => {
    // Saving here is planning them: someone made by "I met them" is on the plan from now on.
    const row = await saveMeeting(eventId, person.personKey, { ...current(), ...(person.discoveryId ? { discoveryId: person.discoveryId } : {}), unplanned: false });
    track(existing ? 'meet_update' : 'meet_add', { source: person.source, timed: Boolean(row.at) });
    showToast(existing ? 'Saved.' : 'Added to My schedule — see My Day.');
    close();
    onChange(row);
  });

  // Nothing is deleted: "Hide" only sets the person aside, note and time kept,
  // and "Show again" (My Day → Hidden, or Explore → Hidden) brings them back.
  const hideBtn = dialog.querySelector('[data-meet-remove]');
  if (hideBtn) {
    hideBtn.textContent = LABELS.hide;
    hideBtn.addEventListener('click', async () => {
      const row = await peopleStatus.hide(eventId, person);
      track('meet_hide', { source: person.source });
      showToast(LABELS.hiddenToast);
      close();
      onChange(row);
    });
  }

  dialog.querySelector('[data-meet-ics]')?.addEventListener('click', () => {
    const item = meetingCalendarItem({ ...existing, ...current(), personKey: person.personKey }, facts);
    deliverIcs(`meet-${person.name || 'attendee'}`, buildIcs([item]));
    track('calendar_export', { scope: 'meeting', method: 'ics' });
  });

  const google = dialog.querySelector('[data-meet-google]');
  if (google) {
    const refreshGoogle = () => {
      google.href = googleCalendarUrl(meetingCalendarItem({ ...existing, ...current(), personKey: person.personKey }, facts));
    };
    refreshGoogle();
    [noteEl, atEl, ...radios].forEach((el) => el.addEventListener('input', refreshGoogle));
    radios.forEach((r) => r.addEventListener('change', refreshGoogle));
    google.addEventListener('click', () => track('calendar_export', { scope: 'meeting', method: 'google' }));
  }

  dialog.showModal();
  if (!existing) noteEl.focus();
}

/** One meeting as a calendar entry: timed if it has a time, otherwise all day. */
export function meetingCalendarItem(meeting, facts = eventFacts()) {
  const who = meeting.name || 'someone from the attendee list';
  const description = [
    meeting.note ? `Note: ${meeting.note}` : null,
    meeting.sub || null,
    ...(meeting.links ?? []).map((l) => l.url).filter((u) => /^https?:\/\//i.test(u ?? '')),
    `Your plan: ${facts.myDayUrl}`,
  ].filter(Boolean).join('\n');

  const base = {
    uid: `meet-${facts.slug}-${String(meeting.personKey ?? who).replace(/[^a-zA-Z0-9]/g, '')}`,
    title: `Meet ${who} · ${facts.name}`,
    description,
    location: facts.venue || undefined,
    alarmMinutes: 10,
  };

  if (meeting.at) {
    const startMs = new Date(meeting.at).getTime();
    return { ...base, startMs, endMs: startMs + 15 * 60 * 1000 };
  }

  return { ...base, date: defaultMeetingDay(facts) };
}

// The next quarter hour today during the event, otherwise 11:00 on day one.
function suggestedTime(facts) {
  const nowMs = Date.now();
  const today = eventDayKey(nowMs);
  if (facts.start && (today < facts.start || today > (facts.end ?? facts.start))) {
    return `${facts.start}T11:00`;
  }
  return toEventInput(Math.ceil(nowMs / (15 * 60 * 1000)) * 15 * 60 * 1000);
}
