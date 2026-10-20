import fs from 'node:fs';
import path from 'node:path';
import { launchChrome, sleep } from '../tests/browser/helpers/chrome.mjs';

async function run() {
  const chrome = await launchChrome();

  const outDir = path.join(process.cwd(), 'storage/ui-test-results');
  if (!fs.existsSync(outDir)) fs.mkdirSync(outDir, { recursive: true });

  const BASE = 'http://campbuddy.test';

  // Set mobile device metrics
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

  console.log('1. Checking Rajasthan Contribute page for Table Leads...');
  await chrome.goto(`${BASE}/event/wordcamp-rajasthan-2026/contribute`);
  await sleep(1500);

  const tableSummary = await chrome.evaluate(`(() => {
    const sec = document.getElementById('contrib-tables');
    const list = document.getElementById('contrib-tables-list');
    const items = list ? [...list.children].map(li => li.innerText.replace(/\\n+/g, ' · ')) : [];
    return {
      sectionHidden: sec ? sec.hidden : true,
      count: items.length,
      items
    };
  })()`);
  console.log('Table summary:', JSON.stringify(tableSummary, null, 2));
  await screenshot('01_rajasthan_contribute_tables.png');

  console.log('2. Checking Explore Deals - Claim the plan popup form...');
  await chrome.goto(`${BASE}/event/wordcamp-rajasthan-2026/explore?tab=deals`);
  await sleep(1500);

  // Click on the Claim CTA button
  const clickClaimResult = await chrome.evaluate(`(() => {
    const btn = document.querySelector('[data-lead-offer-id]');
    if (!btn) return 'button not found';
    btn.click();
    return 'clicked';
  })()`);
  console.log('Claim button click status:', clickClaimResult);
  await sleep(600);

  const claimDialogInfo = await chrome.evaluate(`(() => {
    const d = document.querySelector('dialog.deal-lead-dialog, dialog[open]');
    if (!d) return null;
    const r = d.getBoundingClientRect();
    const title = d.querySelector('[data-slot="title"]')?.textContent;
    const closeBtn = d.querySelector('[data-action="close"]');
    const closeRect = closeBtn ? closeBtn.getBoundingClientRect() : null;
    return {
      top: r.top,
      bottom: r.bottom,
      height: r.height,
      width: r.width,
      title,
      closeBtnTop: closeRect ? closeRect.top : null,
      isTopClipped: r.top < 0,
      className: d.className
    };
  })()`);
  console.log('Claim Dialog measurement:', JSON.stringify(claimDialogInfo, null, 2));
  await screenshot('02_claim_the_plan_popup.png');

  // Close the deal claim dialog
  await chrome.evaluate(`(() => {
    const closeBtn = document.querySelector('dialog.deal-lead-dialog [data-action="close"], dialog[open] [data-action="close"]');
    if (closeBtn) closeBtn.click();
  })()`);
  await sleep(400);

  console.log('3. Checking People tab - Meet Button and Meet Sheet...');
  await chrome.goto(`${BASE}/event/wordcamp-rajasthan-2026/explore?tab=people`);
  await sleep(1500);

  const meetClickResult = await chrome.evaluate(`(() => {
    const meetBtn = document.querySelector('.meet-btn');
    if (!meetBtn) return 'meet button not found';
    meetBtn.click();
    return 'clicked meet';
  })()`);
  console.log('Meet button status:', meetClickResult);
  await sleep(600);

  const meetSheetInfo = await chrome.evaluate(`(() => {
    const d = document.querySelector('dialog.meet-sheet[open]');
    if (!d) return null;
    const r = d.getBoundingClientRect();
    const name = d.querySelector('[data-slot="name"]')?.textContent;
    return {
      top: r.top,
      height: r.height,
      name,
      hasCalendar: Boolean(d.querySelector('[data-meet-ics]')),
      hasNote: Boolean(d.querySelector('#meet-note'))
    };
  })()`);
  console.log('Meet Sheet measurement:', JSON.stringify(meetSheetInfo, null, 2));
  await screenshot('03_meet_sheet.png');

  // Close meet sheet
  await chrome.evaluate(`(() => {
    const closeBtn = document.querySelector('[data-meet-close]');
    if (closeBtn) closeBtn.click();
  })()`);
  await sleep(400);

  console.log('4. Checking Join Discovery Button and Form...');
  const joinClickResult = await chrome.evaluate(`(() => {
    const joinBtn = document.getElementById('join-discovery-btn');
    if (!joinBtn) return 'join button not found';
    joinBtn.click();
    return 'clicked join';
  })()`);
  console.log('Join button status:', joinClickResult);
  await sleep(600);

  const discoveryFormInfo = await chrome.evaluate(`(() => {
    const form = document.querySelector('.card .identity-choice');
    const submitBtn = document.getElementById('join-submit');
    const tags = document.querySelectorAll('#join-tags .chip').length;
    return {
      hasIdentityChoice: Boolean(form),
      hasSubmitBtn: Boolean(submitBtn),
      tagsCount: tags
    };
  })()`);
  console.log('Discovery form info:', JSON.stringify(discoveryFormInfo, null, 2));
  await screenshot('04_join_discovery_form.png');

  await chrome.close();
  console.log('Completed successfully!');
}

run().catch((err) => {
  console.error('Fatal error:', err);
  process.exit(1);
});
