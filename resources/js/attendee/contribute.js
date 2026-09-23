// Contribute: a short, skippable question flow (CD1) that
// recommends curated WordPress Contributor Teams (CD2) with the full
// plain-language explanation each one needs (CD3), plus the Quest tie-in
// (CD4).

import { track } from './analytics.js';
import { CONTRIB_TEAMS, CONTRIB_QUESTION_TAGS } from './contrib-teams.js';
import { setQuestComplete } from './db.js';
import { fill, render } from './template.js';

export async function renderContribute(root) {
  const tagsEl = document.getElementById('contrib-tags');
  if (!tagsEl) return;

  const eventId = Number(root.dataset.eventId);
  const { contributorDayQuestId } = JSON.parse(document.getElementById('contribute-data').textContent);
  const selected = new Set();

  tagsEl.replaceChildren(
    ...CONTRIB_QUESTION_TAGS.map((t) =>
      render('tpl-contribute-chip', { chip: { text: t.label, attrs: { 'data-tag': t.key } } })
    )
  );

  tagsEl.querySelectorAll('[data-tag]').forEach((chip) => {
    chip.addEventListener('click', () => {
      const key = chip.dataset.tag;
      selected.has(key) ? selected.delete(key) : selected.add(key);

      const on = selected.has(key);
      chip.classList.toggle('chip--selected', on);
      chip.setAttribute('aria-pressed', String(on));
    });
  });

  document.getElementById('contrib-see-teams').addEventListener('click', () => {
    showMatches([...selected], eventId, contributorDayQuestId);
  });

  document.getElementById('contrib-retry').addEventListener('click', () => {
    track('contribute_retry');
    document.getElementById('contrib-results').hidden = true;
    document.getElementById('contrib-questions').hidden = false;
  });

  const dialog = document.getElementById('contrib-team-detail');
  dialog.querySelector('[data-action="close"]').addEventListener('click', () => dialog.close());

  renderAllTeams(eventId, contributorDayQuestId);
}

function scoreTeam(team, answers) {
  if (answers.length === 0) return 0;
  return team.matches.filter((m) => answers.includes(m)).length;
}

function showMatches(answers, eventId, questId) {
  // The answers are picks from a fixed, curated list and aren't stored
  // anywhere — a signal for which contribution areas attendees lean toward.
  track('contribute_matches_view', { answer_count: answers.length, answers: [...answers].sort().join(',') });

  document.getElementById('contrib-questions').hidden = true;
  const resultsEl = document.getElementById('contrib-results');
  resultsEl.hidden = false;

  const ranked = [...CONTRIB_TEAMS].sort((a, b) => scoreTeam(b, answers) - scoreTeam(a, answers));
  const top = answers.length > 0 ? ranked.slice(0, 3) : CONTRIB_TEAMS.slice(0, 3);

  const listEl = document.getElementById('contrib-team-list');
  listEl.replaceChildren(...top.map(teamCard));
  wireTeamCards(listEl, eventId, questId, 'matches');
}

function renderAllTeams(eventId, questId) {
  const el = document.getElementById('contrib-all-list');
  el.replaceChildren(...CONTRIB_TEAMS.map(teamCard));
  wireTeamCards(el, eventId, questId, 'all');
}

function teamCard(team) {
  return render('tpl-contribute-team-card', {
    card: { attrs: { 'data-team-id': team.id } },
    emoji: team.emoji,
    name: team.name,
    desc: team.description,
  });
}

function wireTeamCards(container, eventId, questId, source) {
  container.querySelectorAll('[data-team-id]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const team = CONTRIB_TEAMS.find((t) => t.id === btn.dataset.teamId);
      track('contribute_team_view', { team_id: team.id, team_name: team.name, source });
      showTeamDetail(team, eventId, questId);
    });
  });
}

async function showTeamDetail(team, eventId, questId) {
  const dialog = document.getElementById('contrib-team-detail');

  fill(dialog, {
    'badge-technical': { attrs: { hidden: !team.technical } },
    'badge-plain': { attrs: { hidden: team.technical } },
    emoji: team.emoji,
    name: team.name,
    description: team.description,
    'who-it-suits': team.whoItSuits,
    'beginner-task': team.beginnerTask,
    'at-the-table': team.atTheTable,
  });

  dialog.showModal();

  // CD4: opening a team's detail is "learning what one team does" —
  // mark the linked Quest complete directly by its real ID (from the
  // server-embedded contribute-data, the Quest editor governs its text).
  if (questId) {
    await setQuestComplete(eventId, questId, true);
  }
}
