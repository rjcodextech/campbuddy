// node --test tests/js  — the body of a discovery report (report.js).
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

globalThis.window = {};
globalThis.document = { querySelector: () => null };
globalThis.localStorage = { getItem: () => null, setItem() {} };

const { reportBody, REASONS } = await import('../../resources/js/attendee/report.js');

test('an unknown reason becomes "other" and a long note is cut', () => {
  assert.equal(reportBody('spam', ' hi ', 'd1').note, 'hi');
  assert.equal(reportBody('nonsense', '', 'd1').reason, 'other');
  assert.equal(reportBody('abusive', 'x'.repeat(500), 'd1').note.length, 300);
  assert.equal(reportBody('abusive', '   ', 'd1').note, null);
});

test('the reasons match the ones the server accepts', () => {
  const php = readFileSync(new URL('../../app/Models/DiscoveryReport.php', import.meta.url), 'utf8');
  for (const reason of REASONS) assert.match(php, new RegExp(`'${reason}' =>`));
});

test('the sheet has every reason and the buttons report.js wires', () => {
  const blade = readFileSync(new URL('../../resources/views/attendee/templates/discovery.blade.php', import.meta.url), 'utf8');
  const tpl = blade.match(/<template id="tpl-report-sheet">([\s\S]*?)<\/template>/)?.[1] ?? '';
  for (const reason of REASONS) assert.match(tpl, new RegExp(`value="${reason}"`));
  for (const hook of ['data-report-close', 'data-report-send', 'id="report-note"']) assert.ok(tpl.includes(hook), hook);
});
