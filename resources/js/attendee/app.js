// Attendee app entry point — vanilla JS, no framework. Each page
// is server-rendered; this just wires up the interactive parts:
// whichever screen's own module the page needs. The onboarding section
// is Home-specific and wired up inside home.js, not blocking every page.

if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js').catch(() => {
      // Offline-first degrades gracefully without it — a failed
      // registration just means no offline caching or push this visit.
    });
  });
}

async function init() {
  const root = document.getElementById('app');
  if (!root) return;

  if (document.getElementById('home-data')) {
    const { renderHome } = await import('./home.js');
    renderHome(root);
  }

  if (document.getElementById('my-day-data')) {
    const { renderMyDay } = await import('./my-day.js');
    renderMyDay(root);
  }

  if (document.getElementById('quest-data')) {
    const { renderQuest } = await import('./quest.js');
    renderQuest(root);
  }

  if (document.getElementById('camp-card-form')) {
    const { renderCampCard } = await import('./camp-card.js');
    renderCampCard();
  }

  if (document.getElementById('contribute-data')) {
    const { renderContribute } = await import('./contribute.js');
    renderContribute(root);
  }

  if (document.querySelector('[data-explore-tab]')) {
    const { renderExplore } = await import('./explore.js');
    renderExplore();
  }

  if (document.getElementById('people-root')) {
    const { renderPeople } = await import('./people.js');
    renderPeople(root);
  }

  if (document.getElementById('data-controls')) {
    const { mountDataControls } = await import('./data-controls.js');
    mountDataControls(root, 'data-controls');
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
