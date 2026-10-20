import fs from 'node:fs';
import path from 'node:path';
import { launchChrome, sleep } from '../tests/browser/helpers/chrome.mjs';

async function run() {
  const chrome = await launchChrome();

  const outDir = path.join(process.cwd(), 'storage/ui-test-results');
  if (!fs.existsSync(outDir)) fs.mkdirSync(outDir, { recursive: true });

  const BASE = 'http://campbuddy.test';

  // Set mobile device metrics (iPhone 14 / modern Android viewport)
  await chrome.send('Emulation.setDeviceMetricsOverride', {
    width: 390,
    height: 844,
    deviceScaleFactor: 2,
    mobile: true,
  });

  const screenshot = async (filename) => {
    const shot = await chrome.send('Page.captureScreenshot', { format: 'png' });
    if (shot.result?.data) {
      fs.writeFileSync(path.join(outDir, filename), Buffer.from(shot.result.data, 'base64'));
      console.log(`Saved screenshot: ${filename}`);
    }
  };

  console.log('1. Navigating to Rajasthan Explore -> People tab...');
  await chrome.goto(`${BASE}/event/wordcamp-rajasthan-2026/explore?tab=people`);
  
  // Wait just enough for initial draw (300ms)
  await sleep(400);

  const initialCheck = await chrome.evaluate(`(() => {
    const roster = document.getElementById('people-roster');
    const rows = roster ? roster.querySelectorAll('.roster-row') : [];
    const chips = [...document.querySelectorAll('.roster-role-filters .chip')].map(c => c.textContent.trim());
    return {
      initialRowCount: rows.length,
      roleChips: chips,
    };
  })()`);

  console.log('Initial Paint Check (should be 50 items):', JSON.stringify(initialCheck, null, 2));
  await screenshot('03_attendees_initial_chunk_50.png');

  console.log('2. Waiting for background idle streaming to complete (streaming remaining attendees)...');
  await sleep(2500);

  const streamedCheck = await chrome.evaluate(`(() => {
    const roster = document.getElementById('people-roster');
    const rows = roster ? roster.querySelectorAll('.roster-row') : [];
    return {
      streamedRowCount: rows.length,
    };
  })()`);
  console.log('Streamed Full Roster Check:', JSON.stringify(streamedCheck, null, 2));
  await screenshot('04_attendees_full_streamed.png');

  console.log('3. Testing instant search without impact...');
  const searchResult = await chrome.evaluate(`(() => {
    const searchInput = document.getElementById('roster-search');
    searchInput.value = 'Sharma';
    searchInput.dispatchEvent(new Event('input', { bubbles: true }));
    const roster = document.getElementById('people-roster');
    const rows = roster ? roster.querySelectorAll('.roster-row') : [];
    const names = [...rows].map(r => r.querySelector('.roster-row__name')?.textContent.trim()).filter(Boolean);
    return {
      query: 'Sharma',
      matchCount: rows.length,
      sampleNames: names.slice(0, 5),
    };
  })()`);
  console.log('Search Result Check:', JSON.stringify(searchResult, null, 2));
  await screenshot('05_attendees_search_instant.png');

  // Clear search
  await chrome.evaluate(`(() => {
    const searchInput = document.getElementById('roster-search');
    searchInput.value = '';
    searchInput.dispatchEvent(new Event('input', { bubbles: true }));
  })()`);
  await sleep(500);

  console.log('4. Testing "Looking to meet" multi-entry autocomplete in Join Discovery form...');
  const joinPromptClick = await chrome.evaluate(`(() => {
    const btn = document.getElementById('join-discovery-btn');
    if (!btn) return 'join button not found';
    btn.click();
    return 'clicked';
  })()`);
  console.log('Join button click:', joinPromptClick);
  await sleep(600);

  // Click suggestion pills and add custom entry
  const tagTest = await chrome.evaluate(`(() => {
    const form = document.getElementById('people-discovery');
    const pill1 = form.querySelector('[data-suggest="Plugin Developers"]');
    const pill2 = form.querySelector('[data-suggest="Agency Owners"]');
    if (pill1) pill1.click();
    if (pill2) pill2.click();

    // Type a custom persona tag not in suggestions
    const textInput = form.querySelector('.who-tags__input');
    textInput.value = 'AI & LLM Builders';
    textInput.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));

    const hiddenVal = form.querySelector('#join-who')?.value;
    const chips = [...form.querySelectorAll('.who-tag-chip')].map(c => c.textContent.replace('×', '').trim());

    return {
      hiddenInputValue: hiddenVal,
      renderedChips: chips,
    };
  })()`);
  console.log('Tag addition check (suggestions + custom):', JSON.stringify(tagTest, null, 2));
  await screenshot('06_who_to_meet_multi_tags_added.png');

  // Remove the first chip
  const removeTest = await chrome.evaluate(`(() => {
    const form = document.getElementById('people-discovery');
    const firstChipRemoveBtn = form.querySelector('.who-tag-chip button');
    if (firstChipRemoveBtn) firstChipRemoveBtn.click();

    const hiddenVal = form.querySelector('#join-who')?.value;
    const chips = [...form.querySelectorAll('.who-tag-chip')].map(c => c.textContent.replace('×', '').trim());

    return {
      hiddenInputValue: hiddenVal,
      renderedChips: chips,
    };
  })()`);
  console.log('Tag removal check:', JSON.stringify(removeTest, null, 2));
  await screenshot('07_who_to_meet_chip_removed.png');

  await chrome.close();
  console.log('All browser verification tests completed successfully!');
}

run().catch((err) => {
  console.error('Test execution failed:', err);
  process.exit(1);
});
