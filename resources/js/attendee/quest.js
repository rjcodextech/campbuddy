// Quest: binary done/not-done, local-only progress (C2, C4) — no
// points, currency, or leaderboard, deliberately.

import { getQuestProgress, setQuestComplete } from './db.js';

export async function renderQuest(root) {
  const dataEl = document.getElementById('quest-data');
  if (!dataEl) return;

  const quests = JSON.parse(dataEl.textContent);
  const eventId = Number(root.dataset.eventId);
  const listEl = document.getElementById('quest-list');
  const emptyEl = document.getElementById('quest-empty');

  if (quests.length === 0) {
    emptyEl.hidden = false;
    return;
  }

  let completed = new Set((await getQuestProgress(eventId)).map((q) => q.questId));

  function render() {
    listEl.innerHTML = quests
      .map((q) => {
        const done = completed.has(q.id);
        return `
          <label class="checklist__item ${done ? 'checklist__item--done' : ''}">
            <input type="checkbox" class="checklist__input" data-quest-id="${q.id}" ${done ? 'checked' : ''}>
            <span>
              <span class="checklist__label">${escapeHtml(q.title)}</span>
              ${q.description ? `<span class="footer-note" style="display:block;text-align:left;margin-top:2px">${escapeHtml(q.description)}</span>` : ''}
            </span>
          </label>
        `;
      })
      .join('');

    listEl.querySelectorAll('input[type="checkbox"]').forEach((input) => {
      input.addEventListener('change', async () => {
        const id = Number(input.dataset.questId);
        if (input.checked) {
          completed.add(id);
        } else {
          completed.delete(id);
        }
        await setQuestComplete(eventId, id, input.checked);
        renderProgress();
        render();
      });
    });
  }

  function renderProgress() {
    const bar = document.getElementById('quest-progress-bar');
    const summary = document.getElementById('quest-progress-summary');
    const pct = Math.round((completed.size / quests.length) * 100);
    bar.style.width = `${pct}%`;
    summary.textContent = `${completed.size}/${quests.length} complete`;
  }

  render();
  renderProgress();
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str ?? '';
  return div.innerHTML;
}
