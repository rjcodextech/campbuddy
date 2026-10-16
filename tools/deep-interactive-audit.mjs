import fs from 'node:fs';
import path from 'node:path';
import { launchChrome, sleep } from '../tests/browser/helpers/chrome.mjs';

const BASE = 'http://campbuddy.test';
const OUT = path.join(process.cwd(), 'storage/ui-audit-interactive');
fs.mkdirSync(OUT, { recursive: true });

const chrome = await launchChrome();
await chrome.send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true });
await chrome.send('Emulation.setTouchEmulationEnabled', { enabled: true });
await chrome.send('Page.addScriptToEvaluateOnNewDocument', {
  source: `
    window.__pageErrors = [];
    window.__clickedButtons = [];
    addEventListener('error', e => window.__pageErrors.push(String(e.message)));
    addEventListener('unhandledrejection', e => window.__pageErrors.push('promise: ' + String(e.reason)));
  `,
});

const auditResults = [];

async function testScreen(url, screenName, interactionsFn) {
  console.log(`\n--- Testing ${screenName} (${url}) ---`);
  await chrome.goto(BASE + url);
  await sleep(1500);

  // Initial probe of interactive elements
  const initialProbe = await chrome.evaluate(`(() => {
    const buttons = [...document.querySelectorAll('button, a[role=button], .btn, [role=tab]')].map(b => ({
      text: (b.innerText || b.getAttribute('aria-label') || '').trim().replace(/\\s+/g, ' ').slice(0, 50),
      tag: b.tagName.toLowerCase(),
      id: b.id || null,
      cls: b.className || '',
      visible: b.offsetWidth > 0 && b.offsetHeight > 0,
      width: Math.round(b.getBoundingClientRect().width),
      height: Math.round(b.getBoundingClientRect().height),
      disabled: b.disabled || b.getAttribute('aria-disabled') === 'true',
    }));

    const inputs = [...document.querySelectorAll('input:not([type=hidden]), textarea, select')].map(i => ({
      name: i.name || null,
      id: i.id || null,
      type: i.type || 'text',
      placeholder: i.placeholder || '',
      value: i.value || '',
      visible: i.offsetWidth > 0 && i.offsetHeight > 0,
      width: Math.round(i.getBoundingClientRect().width),
      height: Math.round(i.getBoundingClientRect().height),
    }));

    return { buttons, inputs };
  })()`);

  let interactionLogs = [];
  if (interactionsFn) {
    try {
      interactionLogs = await interactionsFn(chrome);
    } catch (err) {
      interactionLogs.push({ action: 'error', message: err.message });
    }
  }

  // Final check for errors
  const finalCheck = await chrome.evaluate(`(() => {
    return {
      errors: window.__pageErrors || [],
      scrollWidth: document.documentElement.scrollWidth,
      innerWidth: window.innerWidth,
    };
  })()`);

  const entry = {
    screen: screenName,
    url,
    totalButtons: initialProbe.buttons.length,
    visibleButtons: initialProbe.buttons.filter(b => b.visible).length,
    totalInputs: initialProbe.inputs.length,
    visibleInputs: initialProbe.inputs.filter(i => i.visible).length,
    buttons: initialProbe.buttons,
    inputs: initialProbe.inputs,
    interactions: interactionLogs,
    overflow: finalCheck.scrollWidth > finalCheck.innerWidth,
    errors: finalCheck.errors,
  };

  auditResults.push(entry);
  console.log(`  Buttons: ${entry.visibleButtons} visible, Inputs: ${entry.visibleInputs} visible, Errors: ${entry.errors.length}, Overflow: ${entry.overflow ? 'YES' : 'NO'}`);
}

try {
  // 1. EVENT PICKER
  await testScreen('/', 'Event Picker', async (ch) => {
    const logs = [];
    // Test country filter dropdown
    const filterClicked = await ch.evaluate(`(() => {
      const btn = document.querySelector('[data-picker-country-btn], .picker-filter, [aria-haspopup="listbox"]');
      if (btn) { btn.click(); return true; }
      return false;
    })()`);
    logs.push({ action: 'country_filter_toggle', success: filterClicked });
    await sleep(500);

    // Test search / more buttons
    const moreBtn = await ch.evaluate(`(() => {
      const btn = document.querySelector('[data-picker-more]');
      if (btn && !btn.hidden) { btn.click(); return true; }
      return false;
    })()`);
    logs.push({ action: 'more_events_click', success: moreBtn });

    return logs;
  });

  // 2. WORDCAMP RAJASTHAN - HOME
  await testScreen('/event/wordcamp-rajasthan-2026', 'Rajasthan Home (Completed)', async (ch) => {
    const logs = [];
    // Test memories buttons
    const memoriesTest = await ch.evaluate(`(() => {
      const photos = document.querySelector('a[data-track-target="photos"]');
      const videos = document.querySelector('a[data-track-target="videos"]');
      return {
        hasPhotos: !!photos,
        photosHref: photos?.href || null,
        hasVideos: !!videos,
        videosHref: videos?.href || null,
      };
    })()`);
    logs.push({ action: 'memories_check', ...memoriesTest });

    // Test Save your WordCamp Data export button
    const exportBtn = await ch.evaluate(`(() => {
      const btn = document.querySelector('.event-card__export, [data-export-event]');
      return { hasExport: !!btn, text: btn?.innerText?.trim() };
    })()`);
    logs.push({ action: 'export_button_check', ...exportBtn });

    return logs;
  });

  // 3. WORDCAMP RAJASTHAN - MY DAY (SCHEDULE)
  await testScreen('/event/wordcamp-rajasthan-2026/my-day', 'Rajasthan Schedule / My Day', async (ch) => {
    const logs = [];
    // Test search input
    const searchRes = await ch.evaluate(`(() => {
      const input = document.querySelector('input[type=search], input[placeholder*="Search"]');
      if (input) {
        input.value = 'WordPress';
        input.dispatchEvent(new Event('input', { bubbles: true }));
        return { hasInput: true, value: input.value };
      }
      return { hasInput: false };
    })()`);
    logs.push({ action: 'schedule_search', ...searchRes });
    await sleep(500);

    // Test Bookmark / Star button
    const bookmarkRes = await ch.evaluate(`(() => {
      const btn = document.querySelector('[data-session-save], .session-save, [aria-label*="Save"]');
      if (btn) {
        btn.click();
        return { clicked: true, label: btn.getAttribute('aria-label') || btn.innerText };
      }
      return { clicked: false };
    })()`);
    logs.push({ action: 'session_bookmark', ...bookmarkRes });

    return logs;
  });

  // 4. WORDCAMP RAJASTHAN - QUEST
  await testScreen('/event/wordcamp-rajasthan-2026/quest', 'Rajasthan Quests', async (ch) => {
    const logs = [];
    // Test clicking a quest toggle
    const questRes = await ch.evaluate(`(() => {
      const btn = document.querySelector('.quest-item button, [data-quest-toggle], [data-action="toggle-quest"]');
      if (btn) {
        btn.click();
        return { clicked: true, text: btn.innerText.trim() };
      }
      return { clicked: false };
    })()`);
    logs.push({ action: 'quest_toggle', ...questRes });
    return logs;
  });

  // 5. WORDCAMP RAJASTHAN - CONTRIBUTE
  await testScreen('/event/wordcamp-rajasthan-2026/contribute', 'Rajasthan Contribute', async (ch) => {
    const logs = [];
    // Test questionnaire answer buttons
    const answerRes = await ch.evaluate(`(() => {
      const btn = document.querySelector('.contrib-choice, [data-choice="yes"], button');
      if (btn) {
        btn.click();
        return { clicked: true, text: btn.innerText.trim() };
      }
      return { clicked: false };
    })()`);
    logs.push({ action: 'contribute_choice', ...answerRes });
    return logs;
  });

  // 6. WORDCAMP RAJASTHAN - EXPLORE (PEOPLE, SPONSORS, DEALS, FREE STEALS, INFO)
  await testScreen('/event/wordcamp-rajasthan-2026/explore', 'Rajasthan Explore (People)', async (ch) => {
    const logs = [];
    // Test People search
    const searchRes = await ch.evaluate(`(() => {
      const input = document.querySelector('input[type=search], input[placeholder*="Search"]');
      if (input) {
        input.value = 'Sunil';
        input.dispatchEvent(new Event('input', { bubbles: true }));
        return { hasInput: true };
      }
      return { hasInput: false };
    })()`);
    logs.push({ action: 'people_search', ...searchRes });

    // Test Attendee filter chips (All, Organizers, Speakers, Attendees)
    const chipsRes = await ch.evaluate(`(() => {
      const chips = [...document.querySelectorAll('.filter-chip, [data-filter-role], .tab-pill')].map(c => c.innerText.trim());
      return { chips };
    })()`);
    logs.push({ action: 'people_filter_chips', ...chipsRes });

    return logs;
  });

  await testScreen('/event/wordcamp-rajasthan-2026/explore?tab=deals', 'Rajasthan Explore (Deals)', async (ch) => {
    const logs = [];
    // Test Deal details expand / claim
    const dealRes = await ch.evaluate(`(() => {
      const detailsBtn = document.querySelector('.deal-card button, [data-action="expand-deal"], summary');
      if (detailsBtn) {
        detailsBtn.click();
        return { expanded: true, text: detailsBtn.innerText.trim() };
      }
      return { expanded: false };
    })()`);
    logs.push({ action: 'deal_expand', ...dealRes });
    return logs;
  });

  await testScreen('/event/wordcamp-rajasthan-2026/explore?tab=info', 'Rajasthan Explore (Info)', async (ch) => {
    const logs = [];
    const actions = await ch.evaluate(`(() => {
      const buttons = [...document.querySelectorAll('.event-info__action, a[href^="https://www.google.com/maps"], button[data-action="copy"], button[data-action="clear-data"]')].map(b => b.innerText.trim() || b.getAttribute('aria-label'));
      return { actions };
    })()`);
    logs.push({ action: 'info_actions', ...actions });
    return logs;
  });

  // 7. WORDCAMP RAJASTHAN - CAMP CARD
  await testScreen('/event/wordcamp-rajasthan-2026/camp-card', 'Rajasthan Camp Card', async (ch) => {
    const logs = [];
    // Test Theme Selector
    const themeRes = await ch.evaluate(`(() => {
      const themes = [...document.querySelectorAll('[data-theme-choice], .cc-theme-dot, .theme-picker button')];
      if (themes.length > 0) {
        themes[1]?.click();
        return { count: themes.length, switched: true };
      }
      return { count: 0, switched: false };
    })()`);
    logs.push({ action: 'theme_picker', ...themeRes });

    // Test Tag selection
    const tagRes = await ch.evaluate(`(() => {
      const tag = document.querySelector('.cc-tag, [data-tag]');
      if (tag) {
        tag.click();
        return { clickedTag: tag.innerText.trim() };
      }
      return { clickedTag: null };
    })()`);
    logs.push({ action: 'tag_toggle', ...tagRes });

    // Test Share / Download buttons
    const actionRes = await ch.evaluate(`(() => {
      const share = document.querySelector('[data-action="share-card"], .btn-share');
      const dl = document.querySelector('[data-action="download-card"], .btn-download');
      return { hasShare: !!share, hasDownload: !!dl };
    })()`);
    logs.push({ action: 'card_actions', ...actionRes });

    return logs;
  });

  // 8. WORDCAMP BENGALURU - HOME
  await testScreen('/event/wordcamp-bengaluru-2026', 'Bengaluru Home (Upcoming Live)', async (ch) => {
    const logs = [];
    const guideCard = await ch.evaluate(`(() => {
      const startHere = document.querySelector('#start-here, .start-here');
      const dismiss = document.querySelector('#start-here-dismiss');
      return { hasStartHere: !!startHere, hasDismiss: !!dismiss };
    })()`);
    logs.push({ action: 'guide_card_check', ...guideCard });
    return logs;
  });

  // 9. WORDCAMP BENGALURU - SCHEDULE
  await testScreen('/event/wordcamp-bengaluru-2026/my-day', 'Bengaluru Schedule (Live Sessions)', async (ch) => {
    const logs = [];
    // Test Day switching
    const daysRes = await ch.evaluate(`(() => {
      const days = [...document.querySelectorAll('[data-day-tab], .day-tabs button')].map(d => d.innerText.trim());
      return { days };
    })()`);
    logs.push({ action: 'day_tabs', ...daysRes });

    // Test Session detail opening
    const sessionClick = await ch.evaluate(`(() => {
      const session = document.querySelector('.session-card, [data-session-id]');
      if (session) {
        session.click();
        return { clicked: true, title: session.querySelector('.session-card__title, h3')?.innerText?.trim() };
      }
      return { clicked: false };
    })()`);
    logs.push({ action: 'session_click', ...sessionClick });

    return logs;
  });

  // 10. WORDCAMP BENGALURU - PEOPLE DISCOVERY
  await testScreen('/event/wordcamp-bengaluru-2026/explore', 'Bengaluru People & Attendees', async (ch) => {
    const logs = [];
    const attendeesCount = await ch.evaluate(`(() => {
      const cards = document.querySelectorAll('.attendee-card, [data-attendee-id]');
      return { attendeeCardsCount: cards.length };
    })()`);
    logs.push({ action: 'attendees_rendered', ...attendeesCount });
    return logs;
  });

} finally {
  await chrome.close();
}

fs.writeFileSync(path.join(OUT, 'deep-audit-results.json'), JSON.stringify(auditResults, null, 2));
console.log(`\nDeep interactive audit finished! Results saved to ${path.join(OUT, 'deep-audit-results.json')}`);
