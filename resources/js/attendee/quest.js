// Quest: two sections. "Things to do" is the curated default set (§C3),
// matched by title against THINGS_TO_DO_META below for an icon + an
// optional contextual action button — seeded server-side in
// database/seeders/DefaultQuestSeeder.php, so the two must stay in sync.
// "Checklist" is admin-authored per-event quests, plain binary
// done/not-done rows, unchanged from before. Both share the same
// local-only completion store (C2, C4) — no points, currency, or
// leaderboard, deliberately.

import { track } from './analytics.js';
import { getQuestProgress, setQuestComplete } from './db.js';
import { render } from './template.js';

const THINGS_TO_DO_META = {
  'First Hello': { icon: '👋' },
  'Beyond My City': { icon: '🌍' },
  'Speaker Hello': { icon: '🎤' },
  'Contribution Curious': { icon: '🛠️', nav: 'contribute', navLabel: 'Go to Contribute' },
  'Sponsor Explore': { icon: '🏷️', nav: 'explore-sponsors', navLabel: 'View sponsors' },
  'Asked Something': { icon: '🙋' },
  'Keep The Connection': { icon: '🤝', nav: 'explore-people', navLabel: 'Find people' },
  'Share Camp Card': { icon: '📇', nav: 'camp-card', navLabel: 'Open Camp Card' },
  'Career Chat': { icon: '💼', nav: 'explore-sponsors', navLabel: 'See who\'s here' },
};

export async function renderQuest(root) {
  const dataEl = document.getElementById('quest-data');
  if (!dataEl) return;

  const quests = JSON.parse(dataEl.textContent);
  const eventId = Number(root.dataset.eventId);
  const eventSlug = root.dataset.eventSlug;
  const emptyEl = document.getElementById('quest-empty');

  if (quests.length === 0) {
    emptyEl.hidden = false;
    return;
  }

  const thingsToDo = quests.filter((q) => THINGS_TO_DO_META[q.title]);
  const checklist = quests.filter((q) => !THINGS_TO_DO_META[q.title]);

  const thingsSection = document.getElementById('things-to-do-section');
  const thingsList = document.getElementById('things-to-do-list');
  const checklistSection = document.getElementById('checklist-section');
  const checklistList = document.getElementById('quest-list');

  let completed = new Set((await getQuestProgress(eventId)).map((q) => q.questId));

  async function toggle(id) {
    const isDone = completed.has(id);
    if (isDone) {
      completed.delete(id);
    } else {
      completed.add(id);
    }
    await setQuestComplete(eventId, id, !isDone);

    // Which quest and whether it was ticked or un-ticked — completion state
    // itself is never sent, only this event (no points/leaderboard, by design).
    const quest = quests.find((q) => q.id === id);
    track(isDone ? 'quest_undo' : 'quest_complete', {
      quest_id: id,
      quest_title: quest?.title,
      quest_group: THINGS_TO_DO_META[quest?.title] ? 'things_to_do' : 'checklist',
    });

    renderProgress();
    renderThings();
    renderChecklist();
  }

  function renderThings() {
    if (thingsToDo.length === 0) {
      thingsSection.hidden = true;
      return;
    }

    thingsSection.hidden = false;
    thingsList.replaceChildren(...thingsToDo.map((q) => thingCard(q, completed.has(q.id), eventSlug)));

    thingsList.querySelectorAll('[data-toggle-id]').forEach((btn) => {
      btn.addEventListener('click', () => toggle(Number(btn.dataset.toggleId)));
    });
  }

  function renderChecklist() {
    if (checklist.length === 0) {
      checklistSection.hidden = true;
      return;
    }

    checklistSection.hidden = false;
    checklistList.replaceChildren(
      ...checklist.map((q) => {
        const done = completed.has(q.id);
        return render('tpl-quest-checklist-item', {
          item: { class: { 'checklist__item--done': done } },
          input: { attrs: { 'data-quest-id': q.id, checked: done } },
          label: q.title,
          desc: q.description || null,
        });
      })
    );

    checklistList.querySelectorAll('input[type="checkbox"]').forEach((input) => {
      input.addEventListener('change', () => toggle(Number(input.dataset.questId)));
    });
  }

  function renderProgress() {
    const bar = document.getElementById('quest-progress-bar');
    const summary = document.getElementById('quest-progress-summary');
    const pct = Math.round((completed.size / quests.length) * 100);
    bar.style.width = `${pct}%`;
    summary.textContent = `${completed.size}/${quests.length} complete`;
  }

  renderThings();
  renderChecklist();
  renderProgress();
}

function thingCard(quest, done, eventSlug) {
  const meta = THINGS_TO_DO_META[quest.title] ?? {};

  return render('tpl-quest-thing', {
    card: { class: { 'quest-card--done': done } },
    icon: meta.icon ?? '✨',
    title: quest.title,
    desc: quest.description || null,
    nav: meta.nav
      ? {
          attrs: {
            href: navUrl(eventSlug, meta.nav),
            'data-track': 'quest_nav_click',
            'data-track-quest-title': quest.title,
            'data-track-destination': meta.nav,
          },
        }
      : null,
    'nav-label': meta.navLabel ?? 'Open',
    toggle: {
      text: done ? 'Done ✓' : 'Mark done',
      attrs: { 'data-toggle-id': quest.id },
      class: { 'btn--outline': done, 'btn--primary': !done },
    },
  });
}

function navUrl(eventSlug, target) {
  const base = `/event/${eventSlug}`;
  switch (target) {
    case 'camp-card':
      return `${base}/camp-card`;
    case 'contribute':
      return `${base}/contribute`;
    case 'explore-sponsors':
      return `${base}/explore?tab=sponsors`;
    case 'explore-people':
      return `${base}/explore?tab=people`;
    default:
      return `${base}/explore`;
  }
}
