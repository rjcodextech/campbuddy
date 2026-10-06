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
    // First line: who and how it went; then, each on its own line, what they wrote down.
    .map((m) => [
      [m.name, m.sub, STATUS[m.status] ?? null].filter(Boolean).join('  ·  '),
      m.note ? `Note: ${m.note}` : null,
      m.at && Number.isFinite(Date.parse(m.at)) ? `Planned: ${formatWhen(Date.parse(m.at))}` : null,
      personLinks(m.links),
    ].filter(Boolean).join('\n'));
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

// A person's saved links ({ url, type } or plain strings), short: "linkedin.com/in/asha".
function personLinks(links) {
  const list = (Array.isArray(links) ? links : [])
    .map((l) => (typeof l === 'string' ? l : l?.url))
    .filter((u) => typeof u === 'string' && /^https?:\/\//i.test(u))
    .map((u) => u.replace(/^https?:\/\/(www\.)?/i, '').replace(/\/$/, ''));

  return list.length ? `Links: ${list.join(', ')}` : null;
}

/** Builds and downloads the PDF. Resolves to false when there was nothing to put in it. */
export async function exportPdf(event) {
  const dump = await exportAll();
  const model = pdfModel(dump, event, (ms) => formatInEventZone(ms, { weekday: 'short', day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' }));

  if (model.sections.length === 0) return false;

  const { jsPDF } = await import('jspdf');
  const doc = new jsPDF({ unit: 'pt', format: 'a4' });

  // Anything beyond Latin (Hindi, Bangla, Urdu, Tamil, Chinese, emoji…): the
  // PDF library can't shape those scripts, so the phone lays the pages out
  // itself and they go in as pictures — every language right, text not selectable.
  if (needsPictures(model)) {
    await drawAsPictures(doc, model);
    doc.save(fileName(event));
    return true;
  }

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
      // A row's first line is the item; any further lines (a person's note,
      // time, links) sit under it in grey.
      const [head, ...details] = String(row).split('\n');
      const lines = doc.splitTextToSize(pdfSafe(head) || '-', width - 12);
      const extra = details.flatMap((d) => doc.splitTextToSize(pdfSafe(d), width - 12));
      ensure((lines.length + extra.length) * 14 + 4);
      doc.text('•', page.m, y + 14);
      lines.forEach((line, i) => doc.text(line, page.m + 12, y + 14 + i * 14));
      y += lines.length * 14;
      if (extra.length) {
        doc.setFontSize(9.5);
        doc.setTextColor(107, 98, 94);
        extra.forEach((line, i) => doc.text(line, page.m + 12, y + 13 + i * 13));
        y += extra.length * 13 + 2;
        doc.setFontSize(10.5);
        doc.setTextColor(35, 31, 32);
      }
      y += 4;
    }
  }

  const pages = doc.getNumberOfPages();
  for (let i = 1; i <= pages; i++) {
    doc.setPage(i);
    doc.setFontSize(8.5);
    doc.setTextColor(107, 98, 94);
    doc.text(`campbuddy.club  ·  ${i} / ${pages}`, page.m, page.h - 24);
  }

  doc.save(fileName(event));
  return true;
}

function fileName(event) {
  const slug = String(event.name).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
  return `campbuddy-${slug || 'wordcamp'}.pdf`;
}

// Text the built-in PDF font can draw: Latin-1 plus the typographic quotes,
// dashes and ellipsis pdfSafe turns into plain ones.
const LATIN = /^[\x00-\xFF–—‘’“”…]*$/;

/** Whether any text in the PDF is outside what the built-in font can draw. */
export function needsPictures(model) {
  const all = [model.title, ...model.sections.flatMap((s) => [s.heading, ...s.rows])];
  return all.some((text) => !LATIN.test(String(text)));
}

// A4 at 96 dpi, the way the browser lays it out; drawn at 2× for print.
const SHEET = { w: 794, h: 1123, pad: 64, scale: 2 };

/**
 * The same layout as the text PDF, as real HTML pages the browser renders
 * (its own fonts and shaping, any script, right-to-left too), then each page
 * captured with html2canvas and placed on an A4 page.
 */
async function drawAsPictures(doc, model) {
  const { default: html2canvas } = await import('html2canvas');

  const host = document.createElement('div');
  host.setAttribute('aria-hidden', 'true');
  host.style.cssText = 'position:fixed;left:-10000px;top:0;';
  document.body.appendChild(host);

  const font = 'system-ui, -apple-system, "Segoe UI", Roboto, "Noto Sans", "Noto Sans Devanagari", "Noto Sans Bengali", "Noto Sans Arabic", sans-serif';
  const el = (tag, css, text) => {
    const node = document.createElement(tag);
    node.style.cssText = css;
    if (text !== undefined) {
      node.textContent = text;
      node.dir = 'auto';
    }
    return node;
  };
  const newSheet = () => {
    const sheet = el('div', `box-sizing:border-box;width:${SHEET.w}px;height:${SHEET.h}px;padding:${SHEET.pad}px;background:#fff;color:#231f20;font-family:${font};position:relative;overflow:hidden;`);
    sheet.appendChild(el('div', 'position:absolute;left:0;top:0;right:0;height:10px;background:#c33a19;'));
    // Blocks go in here; its own height says when the page is full.
    sheet.content = el('div', '');
    sheet.appendChild(sheet.content);
    host.appendChild(sheet);
    return sheet;
  };
  // Room for text: the page less its margins and the footer line.
  const full = (sheet) => sheet.content.offsetHeight > SHEET.h - SHEET.pad * 2 - 24;

  // Blocks in reading order; each goes on the current sheet, or starts a new one if it doesn't fit.
  const blocks = [
    el('h1', 'margin:0 0 6px;font-size:30px;line-height:1.2;font-weight:800;', model.title),
    el('p', 'margin:0 0 18px;font-size:13px;color:#6b625e;', `Saved from CampBuddy on ${new Date().toLocaleDateString([], { day: 'numeric', month: 'long', year: 'numeric' })}`),
  ];
  for (const section of model.sections) {
    blocks.push(el('h2', 'margin:22px 0 10px;padding-bottom:6px;border-bottom:1px solid #eadfd6;font-size:18px;font-weight:700;color:#c33a19;', section.heading));
    for (const row of section.rows) {
      const [head, ...details] = String(row).split('\n');
      const item = el('div', 'display:flex;gap:10px;margin:0 0 8px;font-size:14px;line-height:1.45;');
      item.appendChild(el('span', 'flex:none;', '•'));
      const body = el('div', 'min-width:0;overflow-wrap:anywhere;');
      body.appendChild(el('div', '', head));
      details.forEach((d) => body.appendChild(el('div', 'font-size:12.5px;color:#6b625e;', d)));
      item.appendChild(body);
      blocks.push(item);
    }
  }

  const sheets = [newSheet()];
  for (const block of blocks) {
    let sheet = sheets[sheets.length - 1];
    sheet.content.appendChild(block);
    // Doesn't fit: it starts the next page (unless it's alone — then it stays, cut at the bottom).
    if (full(sheet) && sheet.content.childElementCount > 1) {
      // A section heading never stays alone at the bottom: it moves with its first item.
      const heading = block.previousElementSibling?.tagName === 'H2' && sheet.content.childElementCount > 2 ? block.previousElementSibling : null;
      sheet = newSheet();
      sheets.push(sheet);
      if (heading) sheet.content.appendChild(heading);
      sheet.content.appendChild(block);
    }
  }

  try {
    const w = doc.internal.pageSize.getWidth();
    const h = doc.internal.pageSize.getHeight();

    for (let i = 0; i < sheets.length; i++) {
      sheets[i].appendChild(el('div', `position:absolute;left:${SHEET.pad}px;bottom:28px;font-size:11px;color:#6b625e;`, `campbuddy.club  ·  ${i + 1} / ${sheets.length}`));
      const canvas = await html2canvas(sheets[i], { backgroundColor: '#ffffff', scale: SHEET.scale, logging: false, width: SHEET.w, height: SHEET.h, windowWidth: SHEET.w });
      if (i > 0) doc.addPage();
      doc.addImage(canvas.toDataURL('image/jpeg', 0.92), 'JPEG', 0, 0, w, h);
    }
  } finally {
    host.remove();
  }
}

/** Whether this phone holds anything for the event — only they get the thank-you card. */
export function hasEventData(dump, eventId) {
  const id = Number(eventId);
  const any = (rows) => (rows ?? []).some((r) => Number(r.eventId) === id);
  return any(dump.bookmarks) || any(dump.questProgress) || any(dump.meetings) || any(dump.metHistory) || Boolean(dump.kv?.[`discovery:${id}`]);
}
