// Contribute (§3.9): a short, skippable question flow (CD1) that
// recommends curated WordPress Contributor Teams (CD2) with the full
// plain-language explanation each one needs (CD3), plus the Quest tie-in
// (CD4, §3.6 C3).

import { CONTRIB_TEAMS, CONTRIB_QUESTION_TAGS } from './contrib-teams.js';
import { setQuestComplete } from './db.js';

export async function renderContribute(root) {
  const tagsEl = document.getElementById('contrib-tags');
  if (!tagsEl) return;

  const eventId = Number(root.dataset.eventId);
  const { contributorDayQuestId } = JSON.parse(document.getElementById('contribute-data').textContent);
  const selected = new Set();

  tagsEl.innerHTML = CONTRIB_QUESTION_TAGS.map(
    (t) => `<button type="button" class="chip" data-tag="${t.key}">${escapeHtml(t.label)}</button>`
  ).join('');

  tagsEl.querySelectorAll('[data-tag]').forEach((chip) => {
    chip.addEventListener('click', () => {
      chip.classList.toggle('chip--selected');
      const key = chip.dataset.tag;
      selected.has(key) ? selected.delete(key) : selected.add(key);
    });
  });

  document.getElementById('contrib-see-teams').addEventListener('click', () => {
    showMatches([...selected], eventId, contributorDayQuestId);
  });

  document.getElementById('contrib-retry').addEventListener('click', () => {
    document.getElementById('contrib-results').hidden = true;
    document.getElementById('contrib-questions').hidden = false;
  });

  renderAllTeams(eventId, contributorDayQuestId);
}

function scoreTeam(team, answers) {
  if (answers.length === 0) return 0;
  return team.matches.filter((m) => answers.includes(m)).length;
}

function showMatches(answers, eventId, questId) {
  document.getElementById('contrib-questions').hidden = true;
  const resultsEl = document.getElementById('contrib-results');
  resultsEl.hidden = false;

  const ranked = [...CONTRIB_TEAMS].sort((a, b) => scoreTeam(b, answers) - scoreTeam(a, answers));
  const top = answers.length > 0 ? ranked.slice(0, 3) : CONTRIB_TEAMS.slice(0, 3);

  document.getElementById('contrib-team-list').innerHTML = top.map((team) => teamCardHtml(team)).join('');
  wireTeamCards(document.getElementById('contrib-team-list'), eventId, questId);
}

function renderAllTeams(eventId, questId) {
  const el = document.getElementById('contrib-all-list');
  el.innerHTML = CONTRIB_TEAMS.map((team) => teamCardHtml(team)).join('');
  wireTeamCards(el, eventId, questId);
}

function teamCardHtml(team) {
  return `
    <button type="button" class="action-card action-card--wide" data-team-id="${team.id}" style="width:100%;margin-bottom:10px">
      <span class="action-card__icon" aria-hidden="true">${team.emoji}</span>
      <span>
        <span class="action-card__title">${escapeHtml(team.name)}</span>
        <span class="action-card__desc">${escapeHtml(team.description)}</span>
      </span>
    </button>
  `;
}

function wireTeamCards(container, eventId, questId) {
  container.querySelectorAll('[data-team-id]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const team = CONTRIB_TEAMS.find((t) => t.id === btn.dataset.teamId);
      showTeamDetail(team, eventId, questId);
    });
  });
}

async function showTeamDetail(team, eventId, questId) {
  const dialog = document.getElementById('contrib-team-detail');
  dialog.innerHTML = `
    <div class="dialog-card">
      <p class="badge">${team.technical ? 'Technical background helps' : 'No technical background needed'}</p>
      <h2 style="margin:10px 0 4px">${team.emoji} ${escapeHtml(team.name)}</h2>
      <p>${escapeHtml(team.description)}</p>

      <p style="font-weight:700;margin-top:14px">Who it suits</p>
      <p class="footer-note" style="text-align:left">${escapeHtml(team.whoItSuits)}</p>

      <p style="font-weight:700;margin-top:14px">A beginner-friendly task</p>
      <p class="footer-note" style="text-align:left">${escapeHtml(team.beginnerTask)}</p>

      <p style="font-weight:700;margin-top:14px">At their table</p>
      <p class="footer-note" style="text-align:left">${escapeHtml(team.atTheTable)}</p>

      <div style="margin-top:18px;display:flex;justify-content:flex-end">
        <button type="button" class="btn btn--primary" data-action="close">Got it</button>
      </div>
    </div>
  `;

  dialog.querySelector('[data-action="close"]').addEventListener('click', () => dialog.close());
  dialog.showModal();

  // CD4: opening a team's detail is "learning what one team does" —
  // mark the linked Quest complete directly by its real ID (from the
  // server-embedded contribute-data, §9's Quest editor governs its text).
  if (questId) {
    await setQuestComplete(eventId, questId, true);
  }
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str ?? '';
  return div.innerHTML;
}
