// The WordCamp picker ("/"): a country filter and "Load more".
//
// The server lists every upcoming WordCamp (HomeController), the same HTML for
// every visitor. Here, in the browser only:
//   - the visitor's country is found from their time zone (the approach of
//     timezone-country.js: Intl time zone → country), and if any WordCamp is
//     there the list starts filtered to it;
//   - only the first FIRST matching cards show, "Load N more" opens STEP more;
//   - "Show all countries" removes the filter, "Show only <country>" puts it
//     back, and the dropdown picks any listed country.
// Cards are only shown or hidden (`hidden`), never built, moved or copied, so
// a WordCamp can't appear twice and every count is a count of real cards. The
// choice is remembered on this device and kept the same across open tabs.

/** Cards shown before anyone taps "Load more", and after each filter change. */
export const FIRST = 5;

/** Cards each "Load more" opens. */
export const STEP = 5;

/** The filter value meaning "every country". */
export const ALL = 'all';

export const STORAGE_KEY = 'campbuddy.pickerCountry';

// Time zones browsers still report under older names (Chrome says
// "Asia/Calcutta"), taken from timezone-country.js. The server sends every
// current zone of each listed country; this only catches the old names.
const ALIASES = {
  IN: ['Asia/Kolkata', 'Asia/Calcutta'],
  US: ['America/New_York', 'America/Chicago', 'America/Denver', 'America/Los_Angeles', 'America/Phoenix', 'America/Anchorage', 'Pacific/Honolulu', 'America/Detroit', 'America/Indiana', 'US/Eastern', 'US/Central', 'US/Mountain', 'US/Pacific'],
  GB: ['Europe/London', 'Europe/Belfast', 'GB'],
  DE: ['Europe/Berlin'],
  FR: ['Europe/Paris'],
  AE: ['Asia/Dubai'],
  SA: ['Asia/Riyadh'],
  SG: ['Asia/Singapore', 'Singapore'],
  JP: ['Asia/Tokyo', 'Japan'],
  CN: ['Asia/Shanghai', 'Asia/Chongqing', 'Asia/Urumqi', 'PRC'],
  HK: ['Asia/Hong_Kong', 'Hongkong'],
  AU: ['Australia/Sydney', 'Australia/Melbourne', 'Australia/Perth', 'Australia/Brisbane', 'Australia/Adelaide', 'Australia/Hobart', 'Australia/Canberra', 'Australia/ACT', 'Australia/NSW'],
  CA: ['America/Toronto', 'America/Vancouver', 'America/Edmonton', 'America/Winnipeg', 'America/Halifax', 'America/Montreal'],
  MY: ['Asia/Kuala_Lumpur'],
  TH: ['Asia/Bangkok'],
  ZA: ['Africa/Johannesburg'],
  CH: ['Europe/Zurich'],
  NL: ['Europe/Amsterdam'],
  IT: ['Europe/Rome'],
  ES: ['Europe/Madrid'],
  KR: ['Asia/Seoul', 'ROK'],
  ID: ['Asia/Jakarta'],
  PH: ['Asia/Manila'],
  NZ: ['Pacific/Auckland', 'NZ'],
  TR: ['Europe/Istanbul', 'Asia/Istanbul', 'Turkey'],
  BR: ['America/Sao_Paulo'],
  MX: ['America/Mexico_City'],
  RU: ['Europe/Moscow'],
  QA: ['Asia/Qatar'],
  LK: ['Asia/Colombo'],
  NP: ['Asia/Kathmandu', 'Asia/Katmandu'],
  BD: ['Asia/Dhaka', 'Asia/Dacca'],
  PK: ['Asia/Karachi'],
  KE: ['Africa/Nairobi'],
  NG: ['Africa/Lagos'],
  EG: ['Africa/Cairo', 'Egypt'],
  UA: ['Europe/Kyiv', 'Europe/Kiev'],
  VN: ['Asia/Ho_Chi_Minh', 'Asia/Saigon'],
  MM: ['Asia/Yangon', 'Asia/Rangoon'],
};

const ALIAS_ZONES = Object.fromEntries(
  Object.entries(ALIASES).flatMap(([code, zones]) => zones.map((zone) => [zone, code])),
);

/** The visitor's country code from their time zone, or null. */
export function detectCountry(timeZone, zones = {}) {
  if (!timeZone) return null;

  return zones[timeZone] ?? ALIAS_ZONES[timeZone] ?? null;
}

/** How many cards each country has: { IN: 3, GB: 1 }. Cards with no country count only under ALL. */
export function countByCountry(countries) {
  const counts = {};
  for (const code of countries) {
    if (code) counts[code] = (counts[code] ?? 0) + 1;
  }

  return counts;
}

/**
 * Where the list starts: the choice saved on this device if it still lists
 * something, else the visitor's own country if it has a WordCamp, else ALL.
 */
export function initialChoice({ saved, detected, counts }) {
  if (saved === ALL) return ALL;
  if (saved && counts[saved]) return saved;
  if (detected && counts[detected]) return detected;

  return ALL;
}

/**
 * Which cards are on screen: the first `shown` of those matching `choice`, in
 * page order.
 *
 * @param {Array<string|null>} countries  each card's country, in page order
 * @returns {{ visible: boolean[], total: number, shown: number, next: number }}
 */
export function layout(countries, choice, shown) {
  const total = countries.filter((code) => choice === ALL || code === choice).length;
  const upTo = Math.max(0, Math.min(total, shown));

  let seen = 0;
  const visible = countries.map((code) => {
    if (choice !== ALL && code !== choice) return false;
    seen++;

    return seen <= upTo;
  });

  return { visible, total, shown: upTo, next: Math.min(STEP, total - upTo) };
}

const plural = (n) => (n === 1 ? 'WordCamp' : 'WordCamps');

export function statusText({ shown, total }, countryName) {
  const where = countryName ? ` in ${countryName}` : '';

  if (total === 1) return `Showing 1 WordCamp${where}`;

  return shown >= total
    ? `Showing all ${total} ${plural(total)}${where}`
    : `Showing ${shown} of ${total} ${plural(total)}${where}`;
}

function readStorage(storage) {
  try {
    return storage?.getItem(STORAGE_KEY) ?? null;
  } catch {
    return null;
  }
}

function writeStorage(storage, value) {
  try {
    storage?.setItem(STORAGE_KEY, value);
  } catch {
    // Private window / blocked storage: the filter still works for this visit.
  }
}

function regionName(code, names) {
  const given = names[code];
  if (given && given !== code) return given;
  try {
    return new Intl.DisplayNames(['en'], { type: 'region' }).of(code) ?? code;
  } catch {
    return code;
  }
}

/**
 * Wires the picker's filter and "Load more". Does nothing on a page without
 * the event list (e.g. the empty state).
 */
export function initPickerFilter({
  doc = globalThis.document,
  win = globalThis.window,
  storage = globalThis.localStorage,
  timeZone,
} = {}) {
  const list = doc.querySelector('[data-picker-list]');
  if (!list) return null;

  const cards = [...list.querySelectorAll('[data-picker-card]')];
  const bar = doc.querySelector('[data-picker-filter]');
  const select = doc.querySelector('[data-picker-country]');
  const status = doc.querySelector('[data-picker-status]');
  const toggle = doc.querySelector('[data-picker-toggle]');
  const more = doc.querySelector('[data-picker-more]');

  let data = { names: {}, zones: {} };
  try {
    data = { ...data, ...JSON.parse(doc.getElementById('picker-countries')?.textContent || '{}') };
  } catch {
    // Unreadable data: no filter, "Load more" still works.
  }

  const countries = cards.map((card) => card.dataset.country || null);
  const counts = countByCountry(countries);
  const codes = Object.keys(counts);
  const name = (code) => regionName(code, data.names ?? {});

  let zone = timeZone;
  if (zone === undefined) {
    try {
      zone = Intl.DateTimeFormat().resolvedOptions().timeZone;
    } catch {
      zone = null;
    }
  }
  const detected = detectCountry(zone, data.zones ?? {});

  // Filtering only helps when some country has fewer WordCamps than the whole list.
  const filterable = codes.some((code) => counts[code] < cards.length);

  let choice = filterable ? initialChoice({ saved: readStorage(storage), detected, counts }) : ALL;
  let shown = FIRST;

  if (filterable && select && bar) {
    const sorted = codes.sort((a, b) => name(a).localeCompare(name(b)));
    const option = (value, label) => {
      const el = doc.createElement('option');
      el.value = value;
      el.textContent = label;

      return el;
    };
    select.replaceChildren(
      option(ALL, `All countries (${cards.length})`),
      ...sorted.map((code) => option(code, `${name(code)} (${counts[code]})`)),
    );
    bar.hidden = false;
  }

  function render({ focusFrom } = {}) {
    const view = layout(countries, choice, shown);
    shown = view.shown;
    cards.forEach((card, i) => {
      card.hidden = !view.visible[i];
    });

    if (select) select.value = choice;
    if (status) status.textContent = statusText(view, choice === ALL ? null : name(choice));

    if (toggle) {
      if (choice !== ALL) {
        toggle.textContent = 'Show all countries';
        toggle.dataset.target = ALL;
        toggle.hidden = false;
      } else if (detected && counts[detected] && filterable) {
        toggle.textContent = `Show only ${name(detected)} (${counts[detected]})`;
        toggle.dataset.target = detected;
        toggle.hidden = false;
      } else {
        toggle.hidden = true;
      }
    }

    if (more) {
      more.hidden = view.next <= 0;
      more.textContent = `Load ${view.next} more`;
    }

    // After "Load more", keyboard and screen-reader users land on the first new card.
    if (focusFrom !== undefined) {
      const first = cards.filter((_, i) => view.visible[i])[focusFrom];
      first?.focus?.();
    }
  }

  function choose(value, { save = true } = {}) {
    const next = value === ALL || counts[value] ? value : ALL;
    if (save) writeStorage(storage, next);
    if (next === choice) return;
    choice = next;
    shown = FIRST;
    render();
  }

  select?.addEventListener('change', () => choose(select.value));
  toggle?.addEventListener('click', () => choose(toggle.dataset.target || ALL));
  more?.addEventListener('click', () => {
    const from = shown;
    shown += STEP;
    render({ focusFrom: from });
  });

  // The same choice in every open tab of the picker.
  win?.addEventListener?.('storage', (event) => {
    if (event.key === STORAGE_KEY && filterable) choose(event.newValue || ALL, { save: false });
  });

  render();

  return {
    get choice() { return choice; },
    get shown() { return shown; },
    detected,
    choose,
  };
}
