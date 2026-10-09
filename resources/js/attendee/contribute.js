// Contribute: a short, skippable question flow (CD1) that
// recommends curated WordPress Contributor Teams (CD2) with the full
// plain-language explanation each one needs (CD3), plus the Quest tie-in
// (CD4).

import { track } from './analytics.js';
import { CONTRIB_TEAMS, CONTRIB_QUESTION_TAGS } from './contrib-teams.js';
import { kvGet, kvSet, setQuestComplete } from './db.js';
import { lineIcon } from './line-icon.js';
import { contributePrefill, onboardingWithContributorDay } from './profile-sync.js';
import { fill, render } from './template.js';

export async function renderContribute(root) {
  const tagsEl = document.getElementById('contrib-tags');
  if (!tagsEl) return;

  const eventId = Number(root.dataset.eventId);
  const { contributorDayQuestId, tables = [] } = JSON.parse(document.getElementById('contribute-data').textContent);
  tablesByTeam = groupTables(tables);
  renderTables(tables);
  const selected = new Set();

  tagsEl.replaceChildren(
    ...CONTRIB_QUESTION_TAGS.map((t) =>
      render('tpl-contribute-chip', { chip: { text: t.label, attrs: { 'data-tag': t.key } } })
    )
  );

  const setChip = (chip, on) => {
    chip.classList.toggle('chip--selected', on);
    chip.setAttribute('aria-pressed', String(on));
  };

  tagsEl.querySelectorAll('[data-tag]').forEach((chip) => {
    chip.addEventListener('click', () => {
      const key = chip.dataset.tag;
      selected.has(key) ? selected.delete(key) : selected.add(key);
      setChip(chip, selected.has(key));
    });
  });

  // What they told the picker's onboarding card: "What describes you?"
  // answers the questions in advance (they can still change them), and the
  // Contributor Day answer is shown here and can be changed (profile-sync.js).
  let onboarding = null;
  try {
    onboarding = await kvGet('onboarding');
  } catch {
    // Storage unavailable: nothing pre-selected, as before.
  }

  contributePrefill(onboarding, CONTRIB_QUESTION_TAGS.map((t) => t.key)).forEach((key) => {
    selected.add(key);
    const chip = tagsEl.querySelector(`[data-tag="${key}"]`);
    if (chip) setChip(chip, true);
  });

  setupContributorDay(onboarding?.attendingContributorDay ?? null, setChip);

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

// What the note under "Going to Contributor Day?" says for each answer.
const CONTRIBUTOR_DAY_NOTES = {
  yes: 'Nice. Pick what you enjoy below to see which tables to head for. Many WordCamps ask you to register for Contributor Day separately, so check that you have.',
  no: 'No problem. Have a look anyway: most teams also work online, any time you like.',
  null: "It's a hands-on day improving WordPress. You don't need to code, and every table welcomes beginners.",
};

function setupContributorDay(answer, setChip) {
  const box = document.getElementById('contrib-day');
  if (!box) return;

  const note = box.querySelector('[data-contrib-day-note]');
  const show = (value) => {
    box.querySelectorAll('[data-contrib-day]').forEach((chip) => setChip(chip, (chip.dataset.contribDay || null) === value));
    note.textContent = CONTRIBUTOR_DAY_NOTES[value] ?? CONTRIBUTOR_DAY_NOTES.null;
  };

  show(answer);

  box.querySelectorAll('[data-contrib-day]').forEach((chip) => {
    chip.addEventListener('click', async () => {
      const value = chip.dataset.contribDay || null;
      show(value);
      try {
        await kvSet('onboarding', onboardingWithContributorDay(await kvGet('onboarding'), value));
      } catch {
        // Not remembered this time; the page still shows the choice.
      }
    });
  });
}

// Contributor Day tables the organizers entered (ContributorTable), by team id.
let tablesByTeam = new Map();

/** team id → its tables; "other" tables aren't tied to a curated team. */
export function groupTables(tables) {
  const map = new Map();
  (tables ?? []).forEach((t) => {
    if (!t || t.team === 'other') return;
    map.set(t.team, [...(map.get(t.team) ?? []), t]);
  });
  return map;
}

/** "Floor 2 · Hall B · Table 5 · Leads: Asha, Ravi" for one team's tables. */
export function whereText(tables) {
  return (tables ?? [])
    .map((t) => [t.place, t.leads?.length ? `Leads: ${t.leads.join(', ')}` : ''].filter(Boolean).join(' · '))
    .filter(Boolean)
    .join(' | ');
}

function renderTables(tables) {
  const section = document.getElementById('contrib-tables');
  if (!section || !tables.length) return;

  document.getElementById('contrib-tables-list').replaceChildren(...tables.map((t) => render('tpl-contribute-table', {
    team: t.name,
    place: Boolean(t.place),
    'place-text': t.place,
    leads: Boolean(t.leads?.length),
    'leads-text': t.leads?.length ? `Table ${t.leads.length === 1 ? 'lead' : 'leads'}: ${t.leads.join(', ')}` : '',
    note: t.note || null,
  })));
  section.hidden = false;
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
  const where = whereText(tablesByTeam.get(team.id));

  return render('tpl-contribute-team-card', {
    card: { attrs: { 'data-team-id': team.id } },
    emoji: lineIcon(team.icon),
    name: team.name,
    desc: team.description,
    table: Boolean(where),
    'table-text': where,
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
    emoji: lineIcon(team.icon),
    name: team.name,
    description: team.description,
    'who-it-suits': team.whoItSuits,
    'beginner-task': team.beginnerTask,
    'at-the-table': team.atTheTable,
    where: { attrs: { hidden: !whereText(tablesByTeam.get(team.id)) } },
    'where-text': whereText(tablesByTeam.get(team.id)),
  });

  dialog.showModal();

  // CD4: opening a team's detail is "learning what one team does" —
  // mark the linked Quest complete directly by its real ID (from the
  // server-embedded contribute-data, the Quest editor governs its text).
  if (questId) {
    await setQuestComplete(eventId, questId, true);
  }
}
