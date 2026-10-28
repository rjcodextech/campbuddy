// Hero stage dynamic spotlight engine:
// Shuffles real WordCamp sessions, speakers, organizers, and sponsors
// from active database events, respecting the visitor's selected country.

export function initHeroSpotlight() {
  const card = document.getElementById('hero-spotlight-card');
  const dataScript = document.getElementById('hero-spotlights-data');
  if (!card || !dataScript) return;

  let spotlights = [];
  try {
    spotlights = JSON.parse(dataScript.textContent || '[]');
  } catch {
    return;
  }

  if (!Array.isArray(spotlights) || spotlights.length === 0) return;

  const headerLogoEl = document.getElementById('hero-stage-event-logo');
  const headerNameEl = document.getElementById('hero-stage-event-name');
  const eventEl = document.getElementById('hero-spotlight-event');
  const badgeEl = document.getElementById('hero-spotlight-badge');
  const titleEl = document.getElementById('hero-spotlight-title');
  const metaEl = document.getElementById('hero-spotlight-meta');
  const avatarEl = document.getElementById('hero-spotlight-avatar');
  const linkEl = document.getElementById('hero-spotlight-link');
  const shuffleBtn = document.getElementById('hero-shuffle-btn');

  function getCountry() {
    let saved = null;
    try {
      saved = localStorage.getItem('campbuddy.pickerCountry');
    } catch {}
    if (saved && saved !== 'all') return saved;

    // Detect from timezone if not explicitly set
    try {
      const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
      if (tz && (tz.includes('Kolkata') || tz.includes('Calcutta'))) return 'IN';
      if (tz && (tz.includes('Toronto') || tz.includes('Vancouver') || tz.includes('Montreal'))) return 'CA';
    } catch {}

    return 'all';
  }

  let currentCountry = getCountry();
  let lastIndex = -1;

  function getFilteredSpotlights() {
    if (currentCountry && currentCountry !== 'all') {
      const matched = spotlights.filter((s) => s.country === currentCountry);
      if (matched.length > 0) return matched;
    }
    return spotlights;
  }

  function pickRandomSpotlight() {
    const list = getFilteredSpotlights();
    if (list.length === 0) return null;
    if (list.length === 1) return list[0];

    let nextIndex;
    let attempts = 0;
    do {
      nextIndex = Math.floor(Math.random() * list.length);
      attempts++;
    } while (nextIndex === lastIndex && attempts < 5);

    lastIndex = nextIndex;
    return list[nextIndex];
  }

  function renderSpotlight(item, animate = true) {
    if (!item) return;

    if (animate) {
      card.classList.add('is-refreshing');
      setTimeout(() => applyData(item), 120);
    } else {
      applyData(item);
    }
  }

  function applyData(item) {
    if (headerLogoEl && item.event_logo) {
      headerLogoEl.src = item.event_logo;
    }
    if (headerNameEl && item.event_name) {
      headerNameEl.textContent = item.event_name;
      headerNameEl.title = item.event_name;
    }
    if (eventEl) eventEl.textContent = item.event_name || 'WordCamp';
    if (badgeEl) badgeEl.textContent = item.badge || 'WordCamp Spotlight';
    if (titleEl) titleEl.textContent = item.title || '';
    if (metaEl) {
      metaEl.textContent = item.meta || '';
      metaEl.hidden = !item.meta;
    }
    if (avatarEl) {
      const initials = (item.title || item.event_name || 'CB')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((w) => w[0].toUpperCase())
        .join('');
      avatarEl.textContent = initials || '★';
    }
    if (linkEl && item.url) {
      linkEl.href = item.url;
      linkEl.setAttribute('aria-label', `Explore ${item.event_name}`);
    }

    card.classList.remove('is-refreshing');
  }

  // Initial random spotlight on page load
  const initial = pickRandomSpotlight();
  if (initial) renderSpotlight(initial, false);

  // Manual shuffle button
  shuffleBtn?.addEventListener('click', () => {
    shuffleBtn.classList.add('is-spinning');
    setTimeout(() => shuffleBtn.classList.remove('is-spinning'), 400);
    const next = pickRandomSpotlight();
    if (next) renderSpotlight(next, true);
  });

  // Country changed event from picker-filter.js or select dropdown
  const onCountryChange = (country) => {
    currentCountry = country || 'all';
    lastIndex = -1;
    const next = pickRandomSpotlight();
    if (next) renderSpotlight(next, true);
  };

  window.addEventListener('campbuddy:country-changed', (e) => {
    if (e.detail?.country) onCountryChange(e.detail.country);
  });

  const select = document.querySelector('[data-picker-country]');
  select?.addEventListener('change', () => {
    onCountryChange(select.value);
  });
}
