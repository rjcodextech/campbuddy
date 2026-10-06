// "Save my day as PDF": everything this phone holds for one WordCamp (saved
// sessions, people to meet, quests, the Camp Card), as a tidy PDF the attendee
// keeps after the event is gone. Built here, on the phone, from IndexedDB —
// nothing is sent anywhere. jsPDF is loaded only when someone asks for it.

import { exportAll } from './db.js';
import { formatInEventZone } from './eventtime.js';

const STATUS = { attended: 'Attended', missed: "Couldn't make it", met: 'Met' };

// Camp Card fields that aren't for reading (layout choices, pictures).
const CARD_SKIP = new Set(['visibleFields', 'primaryLink', 'interests', 'layout', 'photo', 'avatar', 'avatarUrl']);

// jsPDF's built-in fonts only have Latin letters (WinAnsi): anything else
// would print as garbage, so it's dropped (accents are kept, emoji go).
export const pdfSafe = (text) => String(text)
  .replace(/[\u2018\u2019]/g, "'").replace(/[\u201C\u201D]/g, '"').replace(/[\u2013\u2014]/g, '-').replace(/\u2026/g, '...')
  .replace(/[^\x20-\x7E\xA0-\xFF]/g, '')
  .replace(/\s{2,}/g, ' ')
  .trim();

const label = (key) => key.replace(/_/g, ' ').replace(/([a-z])([A-Z])/g, '$1 $2').replace(/^./, (c) => c.toUpperCase());

/**
 * What goes in the PDF, from an exportAll() dump. Pure, so it can be tested.
 *
 * @param {object} dump        exportAll()
 * @param {object} event       { id, name, questTitles: { [questId]: title } }
 * @param {(ms:number)=>string} formatWhen
 * @returns {{ title: string, sections: Array<{ heading: string, rows: string[] }> }}
 */
export function pdfModel(dump, event, formatWhen) {
  const id = Number(event.id);
  const mine = (rows) => (rows ?? []).filter((r) => Number(r.eventId) === id);
  const sections = [];

  const sessions = mine(dump.bookmarks)
    .filter((b) => b.title)
    .sort((a, b) => (a.startMs ?? Infinity) - (b.startMs ?? Infinity))
    .map((b) => [b.startMs ? formatWhen(b.startMs) : 'Time TBA', b.title, STATUS[b.status] ?? null].filter(Boolean).join('  ·  '));
  if (sessions.length) sections.push({ heading: `Sessions I saved (${sessions.length})`, rows: sessions });

  const people = mine(dump.meetings)
    .filter((m) => m.name && !m.mergedInto && m.status !== 'skipped')
    .map((m) => [m.name, m.sub, STATUS[m.status] ?? null].filter(Boolean).join('  ·  '));
  if (people.length) sections.push({ heading: `People I planned to meet (${people.length})`, rows: people });

  const quests = mine(dump.questProgress).map((q) => event.questTitles?.[q.questId] ?? `Quest #${q.questId}`);
  if (quests.length) sections.push({ heading: `Quests I completed (${quests.length})`, rows: quests });

  const card = dump.kv?.campCard;
  if (card && typeof card === 'object') {
    const rows = Object.entries(card)
      .filter(([k, v]) => !CARD_SKIP.has(k) && typeof v === 'string' && v.trim() && !v.startsWith('data:'))
      .map(([k, v]) => `${label(k)}: ${v.trim()}`);
    if (Array.isArray(card.interests) && card.interests.length) rows.push(`Interests: ${card.interests.join(', ')}`);
    if (rows.length) sections.push({ heading: 'My Camp Card', rows });
  }

  return { title: `My ${event.name}`, sections };
}

/** Builds and downloads the PDF. Resolves to false when there was nothing to put in it. */
export async function exportPdf(event) {
  const dump = await exportAll();
  const model = pdfModel(dump, event, (ms) => formatInEventZone(ms, { weekday: 'short', day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' }));

  if (model.sections.length === 0) return false;

  const { jsPDF } = await import('jspdf');
  const doc = new jsPDF({ unit: 'pt', format: 'a4' });
  const page = { w: doc.internal.pageSize.getWidth(), h: doc.internal.pageSize.getHeight(), m: 48 };
  const width = page.w - page.m * 2;
  let y = page.m;

  const ensure = (needed) => {
    if (y + needed <= page.h - page.m) return;
    doc.addPage();
    y = page.m;
  };

  // Header band in the app's maroon.
  doc.setFillColor(195, 58, 25);
  doc.rect(0, 0, page.w, 8, 'F');

  doc.setFont('helvetica', 'bold');
  doc.setFontSize(22);
  doc.setTextColor(35, 31, 32);
  for (const line of doc.splitTextToSize(pdfSafe(model.title), width)) {
    doc.text(line, page.m, (y += 24));
  }

  doc.setFont('helvetica', 'normal');
  doc.setFontSize(10);
  doc.setTextColor(107, 98, 94);
  doc.text(`Saved from CampBuddy on ${new Date().toLocaleDateString([], { day: 'numeric', month: 'long', year: 'numeric' })}`, page.m, (y += 18));
  y += 12;

  for (const section of model.sections) {
    ensure(48);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(13);
    doc.setTextColor(195, 58, 25);
    doc.text(pdfSafe(section.heading), page.m, (y += 26));
    doc.setDrawColor(234, 223, 214);
    doc.line(page.m, y + 6, page.m + width, y + 6);
    y += 10;

    doc.setFont('helvetica', 'normal');
    doc.setFontSize(10.5);
    doc.setTextColor(35, 31, 32);
    for (const row of section.rows) {
      const lines = doc.splitTextToSize(pdfSafe(row) || '-', width - 12);
      ensure(lines.length * 14 + 4);
      doc.text('•', page.m, y + 14);
      lines.forEach((line, i) => doc.text(line, page.m + 12, y + 14 + i * 14));
      y += lines.length * 14 + 4;
    }
  }

  const pages = doc.getNumberOfPages();
  for (let i = 1; i <= pages; i++) {
    doc.setPage(i);
    doc.setFontSize(8.5);
    doc.setTextColor(107, 98, 94);
    doc.text(`campbuddy.club  ·  ${i} / ${pages}`, page.m, page.h - 24);
  }

  const slug = event.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
  doc.save(`campbuddy-${slug || 'wordcamp'}.pdf`);
  return true;
}

/** Whether this phone holds anything for the event — only they get the thank-you card. */
export function hasEventData(dump, eventId) {
  const id = Number(eventId);
  const any = (rows) => (rows ?? []).some((r) => Number(r.eventId) === id);
  return any(dump.bookmarks) || any(dump.questProgress) || any(dump.meetings) || any(dump.metHistory) || Boolean(dump.kv?.[`discovery:${id}`]);
}
