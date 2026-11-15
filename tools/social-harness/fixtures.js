// The social harness's sample events (creative brief §3 "Sample set"): made-up WordCamps shaped exactly like
// App\Support\SocialKit::data(), each with its own pictures drawn here (portraits with a known face box,
// bright / dark / busy place photos, event and sponsor logos, a skyline) — no real person's photo, no network.
//
//   typical  — a usual event: 12 speakers, sponsors, tables, a two-day schedule; a busy place photo.
//   short    — the shortest words (1–2 word headlines, a one-word name); a very bright place photo.
//   long     — the longest real-world words: a 60-character title, "Venkata Subrahmanya Lakshmi Narasimha Rao",
//              Devanagari, Hinglish, a long venue and a light (white) logo; a very dark place photo.
//   little   — an event with almost no data: no logo, dates, people, sponsors or schedule; no photo.
//   contrast — event colours that fail contrast (yellow main, grey text), as an admin may save them.
//
// Every picture is a blob: URL (same origin, so canvases stay readable) and each portrait's face box is
// registered in window.__faceBoxes (canvas-probe.mjs maps it onto the card to catch words over a face).

const FACES = (window.__faceBoxes ??= {});

/** A small seeded random, so every run draws the same pictures. */
function rng(seed) {
  let a = seed >>> 0 || 1;
  return () => {
    a = (a + 0x6d2b79f5) >>> 0;
    let t = a;
    t = Math.imul(t ^ (t >>> 15), t | 1);
    t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

/** The same CRC-32 PHP's crc32() gives, so a sample picks the same wording the server would. */
export function crc32(text) {
  let c;
  let crc = 0xffffffff;
  for (const byte of new TextEncoder().encode(text)) {
    c = (crc ^ byte) & 0xff;
    for (let k = 0; k < 8; k++) c = c & 1 ? (c >>> 1) ^ 0xedb88320 : c >>> 1;
    crc = (crc >>> 8) ^ c;
  }
  return (crc ^ 0xffffffff) >>> 0;
}

async function toUrl(canvas, faces = null) {
  const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
  const url = URL.createObjectURL(blob);
  if (faces) FACES[url] = faces;
  return url;
}

const make = (w, h) => {
  const c = document.createElement('canvas');
  c.width = w;
  c.height = h;
  return [c, c.getContext('2d')];
};

/**
 * A portrait (600 × 600): a background of the given kind, shoulders, a head with eyes. `dx`, `dy` move the
 * head off centre (a photo framed badly, to test the crop). Returns its URL; the face box is registered.
 */
async function portrait(kind, seed, { dx = 0, dy = 0, skin = '#c68863', hair = '#2b1d16', shirt = '#2f6f8f' } = {}) {
  const r = rng(seed);
  const [c, x] = make(600, 600);
  if (kind === 'bright') {
    const g = x.createLinearGradient(0, 0, 0, 600);
    g.addColorStop(0, '#ffffff');
    g.addColorStop(1, '#eef2f6');
    x.fillStyle = g;
    x.fillRect(0, 0, 600, 600);
  } else if (kind === 'dark') {
    x.fillStyle = '#0b0d12';
    x.fillRect(0, 0, 600, 600);
  } else if (kind === 'busy') {
    for (let i = 0; i < 140; i++) {
      x.fillStyle = `hsl(${Math.floor(r() * 360)} ${50 + r() * 40}% ${25 + r() * 55}%)`;
      x.fillRect(r() * 600, r() * 600, 20 + r() * 120, 10 + r() * 90);
    }
  } else {
    const g = x.createLinearGradient(0, 0, 600, 600);
    g.addColorStop(0, '#9fb4c7');
    g.addColorStop(1, '#5d7488');
    x.fillStyle = g;
    x.fillRect(0, 0, 600, 600);
  }
  const cx = 300 + dx;
  const cy = 240 + dy;
  x.fillStyle = shirt;
  x.beginPath(); x.ellipse(cx, cy + 300, 230, 170, 0, 0, Math.PI * 2); x.fill();
  x.fillStyle = skin;
  x.fillRect(cx - 40, cy + 70, 80, 70);
  x.fillStyle = hair;
  x.beginPath(); x.ellipse(cx, cy - 30, 108, 100, 0, 0, Math.PI * 2); x.fill();
  x.fillStyle = skin;
  x.beginPath(); x.ellipse(cx, cy + 10, 92, 118, 0, 0, Math.PI * 2); x.fill();
  x.fillStyle = '#1b1b1b';
  for (const ex of [-34, 34]) { x.beginPath(); x.arc(cx + ex, cy, 9, 0, Math.PI * 2); x.fill(); }
  x.strokeStyle = '#7a3b2e';
  x.lineWidth = 6;
  x.lineCap = 'round';
  x.beginPath(); x.arc(cx, cy + 46, 30, 0.2 * Math.PI, 0.8 * Math.PI); x.stroke();
  // The face box: the head, hair to chin.
  return toUrl(c, [[(cx - 108) / 600, (cy - 130) / 600, (cx + 108) / 600, (cy + 128) / 600]]);
}

/** A place photo (1600 × 1000): a bright atrium, a dark hall with stage lights, or a busy crowd. */
async function place(kind, seed) {
  const r = rng(seed);
  const [c, x] = make(1600, 1000);
  if (kind === 'bright') {
    const g = x.createLinearGradient(0, 0, 0, 1000);
    g.addColorStop(0, '#ffffff');
    g.addColorStop(1, '#f1f4f8');
    x.fillStyle = g;
    x.fillRect(0, 0, 1600, 1000);
    for (let i = 0; i < 9; i++) {
      x.fillStyle = `rgba(200,210,222,${0.25 + r() * 0.3})`;
      x.fillRect(80 + i * 170, 380 - r() * 160, 130, 700);
    }
  } else if (kind === 'dark') {
    x.fillStyle = '#06070b';
    x.fillRect(0, 0, 1600, 1000);
    for (let i = 0; i < 7; i++) {
      const g = x.createRadialGradient(150 + i * 220, 120, 0, 150 + i * 220, 120, 180);
      g.addColorStop(0, 'rgba(255,190,120,0.35)');
      g.addColorStop(1, 'rgba(255,190,120,0)');
      x.fillStyle = g;
      x.fillRect(0, 0, 1600, 1000);
    }
    x.fillStyle = '#121620';
    for (let i = 0; i < 40; i++) { x.beginPath(); x.arc(r() * 1600, 760 + r() * 240, 30 + r() * 20, 0, Math.PI * 2); x.fill(); }
  } else {
    x.fillStyle = '#3b3b48';
    x.fillRect(0, 0, 1600, 1000);
    for (let i = 0; i < 12; i++) {
      x.fillStyle = `hsl(${Math.floor(r() * 360)} 70% 55%)`;
      x.fillRect(i * 140, 0, 70, 360);
    }
    for (let i = 0; i < 420; i++) {
      x.fillStyle = `hsl(${Math.floor(r() * 360)} ${40 + r() * 50}% ${20 + r() * 65}%)`;
      x.beginPath(); x.arc(r() * 1600, 300 + r() * 700, 14 + r() * 26, 0, Math.PI * 2); x.fill();
    }
  }
  return toUrl(c);
}

/** An event logo (transparent): a round mark and the event's words — `light` = white lettering (needs a dark tile). */
async function eventLogo(words, color, light = false) {
  const [c, x] = make(900, 360);
  x.fillStyle = light ? '#ffffff' : color;
  x.beginPath(); x.arc(170, 180, 140, 0, Math.PI * 2); x.fill();
  x.fillStyle = light ? color : '#ffffff';
  x.font = '800 150px "Baloo 2", sans-serif';
  x.textAlign = 'center';
  x.fillText('W', 170, 232);
  x.textAlign = 'left';
  // The words shrink to fit the logo, as a real logo's lettering would.
  const fit = (font, size, text) => { let s = size; x.font = font.replace('$', s); while (s > 20 && x.measureText(text).width > 530) x.font = font.replace('$', --s); };
  x.fillStyle = light ? '#ffffff' : '#1f2937';
  fit('800 $px "Baloo 2", sans-serif', 96, words[0]);
  x.fillText(words[0], 340, 160);
  x.fillStyle = light ? '#ffffff' : color;
  fit('700 $px "Poppins", sans-serif', 72, words[1] ?? '');
  x.fillText(words[1] ?? '', 344, 262);
  return toUrl(c);
}

/** A sponsor's logo: a coloured mark and its name, in a shape of its own (wide, square or tall). */
async function sponsorLogo(name, hue, shape, light = false) {
  const [w, h] = { wide: [900, 260], square: [500, 500], tall: [360, 560] }[shape];
  const [c, x] = make(w, h);
  x.fillStyle = light ? '#ffffff' : `hsl(${hue} 65% 42%)`;
  if (shape === 'tall') {
    x.beginPath(); x.arc(w / 2, 170, 130, 0, Math.PI * 2); x.fill();
  } else {
    x.beginPath(); x.roundRect?.(20, h / 2 - 90, 180, 180, 40); x.fill();
  }
  x.fillStyle = light ? '#ffffff' : '#111827';
  x.font = `800 ${shape === 'wide' ? 96 : 76}px "Poppins", sans-serif`;
  x.textAlign = shape === 'wide' ? 'left' : 'center';
  if (shape === 'wide') x.fillText(name, 230, h / 2 + 34);
  else x.fillText(name.split(' ')[0], w / 2, shape === 'tall' ? 420 : 440);
  return toUrl(c);
}

/** A one-colour skyline (transparent, wide): the kind of artwork WordCamp sites have. */
async function skyline(seed) {
  const r = rng(seed);
  const [c, x] = make(2400, 600);
  x.fillStyle = '#ffffff';
  let px = 0;
  while (px < 2400) {
    const w = 80 + r() * 160;
    const h = 120 + r() * 420;
    x.fillRect(px, 600 - h, w, h);
    if (r() < 0.3) { x.beginPath(); x.arc(px + w / 2, 600 - h, w / 2, Math.PI, 0); x.fill(); }
    px += w + 6;
  }
  return toUrl(c);
}

const LABELS = { organizer: 'Organizer', speaker: 'Speaker', volunteer: 'Volunteer', microsponsor: 'Microsponsor', sponsor: 'Sponsor', media_partner: 'Media Partner', table_lead: 'Table Lead' };

/** The fifteen posts, worded as App\Support\SocialKit::posts() words them (same picks by the slug's CRC-32). */
function postsFor(s) {
  const { name, short, dates, place, hashtag, slug } = s;
  const pick = (key, options) => options[crc32(`${slug}|${key}`) % options.length];
  const when = [dates, place].filter(Boolean).join(' · ');
  const placeShort = place ? place.split(',')[0].trim() : short;
  const sessions = s.sessions.length;
  const speakers = s.speakerList.length;
  const dayCount = new Set(s.sessions.map((x) => x.day)).size;
  const days = s.daysToGo;
  const posts = [
    { key: 'save-the-date', label: 'Coming up', kicker: s.tagline ?? pick('std-kicker', ['Coming up', 'Save the date', 'Mark your calendar']), headline: pick('std-head', [`${short} is coming`, `See you at ${short}`, `${short}, here we come`]), line: when, body: 'none' },
    { key: 'countdown', label: 'Countdown', kicker: 'Countdown', headline: days === null ? `${short} is here` : (days === 1 ? pick('cd-one', ['Tomorrow!', 'See you tomorrow']) : pick('cd-head', [`${days} days to go`, `${days} days until ${short}`, `Only ${days} days left`])), line: pick('cd-line', ['Get ready with CampBuddy', 'Plan your days in CampBuddy', 'Your schedule is ready in CampBuddy']), body: 'countdown', days },
    { key: 'schedule', label: 'Schedule in CampBuddy', kicker: 'Now in CampBuddy', headline: pick('sch-head', ['The full schedule, in your pocket', 'Every talk, one tap away', `Plan your ${short} in minutes`]), line: [sessions ? `${sessions} sessions` : '', speakers ? `${speakers} speakers` : ''].filter(Boolean).join(' · ') || when, body: 'points', points: [sessions ? `${sessions} sessions${dayCount > 1 ? ` over ${dayCount} days` : ''}` : null, speakers ? `${speakers} ${speakers === 1 ? 'speaker' : 'speakers'} on stage` : null, 'Save a talk, get a reminder before it', 'Your own day, in one tap'].filter(Boolean) },
    { key: 'app', label: 'Get CampBuddy', kicker: pick('app-kicker', ['Get CampBuddy', 'Your WordCamp app', 'One link, the whole day']), headline: pick('app-head', ['Your WordCamp companion', `Your ${short} companion`, 'One app for your whole WordCamp']), line: pick('app-line', [`For everyone at ${short}`, 'Schedule, people and reminders', `Everything for ${short}, in one place`]), body: 'features' },
    { key: 'speakers', label: 'Speakers', kicker: 'Speakers', headline: pick('spk-head', [`Meet the ${short} speakers`, `The voices of ${short}`, `On stage at ${short}`]), line: pick('spk-line', ['Their talks are in CampBuddy', 'Save their talks in CampBuddy', 'Get a reminder before each talk']), body: 'photos' },
    { key: 'sponsors', label: 'Sponsors', kicker: pick('spo-kicker', ['Say hi at their booths', 'Sponsors', 'Thank you, sponsors']), headline: pick('spo-head', ['The sponsors who make it happen', `The sponsors behind ${short}`, 'Thanks to these sponsors']), line: 'Their deals are in CampBuddy', body: 'logos' },
    { key: 'today', label: 'Day schedule', picker: 'day', kicker: 'On the schedule', headline: pick('day-head', [`Today at ${short}`, `Your day at ${short}`, `What's on at ${short}`]), line: 'Save your picks in CampBuddy', body: 'list' },
    { key: 'spotlight', label: 'Session spotlight', picker: 'session', kicker: 'Up next', headline: '', line: '', body: 'session' },
    { key: 'contributor', label: 'Contributor Day', kicker: 'Contributor Day', headline: pick('con-head', ['Find your Contributor Day table', 'Give back to WordPress', 'Pick your Contributor Day team']), line: pick('con-line', ['CampBuddy matches you with a team', 'No coding needed, every table welcomes you', 'Three questions, one team for you']), body: 'tables' },
    { key: 'meet', label: 'Find your people', kicker: "Don't go alone", headline: pick('meet-head', ['Find people to meet', 'Meet your people', 'Come for the talks, stay for the people']), line: 'CampBuddy shows who shares your interests', body: 'points', points: ['See who shares your interests', 'Say hi with one tap', 'Swap Camp Cards with a QR', 'Your Contributor Day table'] },
    { key: 'thank-you', label: 'Thank you', kicker: "That's a wrap", headline: pick('ty-head', [`Thank you, ${short}!`, `That's a wrap, ${short}!`, `Thanks for a great ${short}`]), line: 'Your day is saved in CampBuddy', body: 'points', points: ['Your talks and notes stay in the app', 'The people you met, all in one place', 'Save your whole day as a PDF', 'See you at the next WordCamp'] },
    { key: 'venue', label: 'Venue', kicker: pick('ven-kicker', ['Getting there', 'The venue', 'Where it happens']), headline: place ? pick('ven-head', [`See you at ${placeShort}`, `${short} happens here`, `Your way to ${short}`]) : `Where ${short} happens`, line: when, body: 'points', points: [place || null, dates || null, 'Directions, Wi-Fi and food in CampBuddy'].filter(Boolean) },
    { key: 'first-timer', label: 'First WordCamp?', kicker: pick('ft-kicker', ['First time?', 'New to WordCamp?', 'Pehla WordCamp?']), headline: pick('ft-head', ['Your first WordCamp? *Start here*', `First time at ${short}? *You'll love it*`, 'New to WordCamp? *Read this first*']), line: 'A 5-minute guide in CampBuddy', body: 'points', points: ['What happens on the day', "Words you'll hear", 'What to bring', 'How to meet people'] },
    { key: 'wpsimplified', label: 'Learn with WPSimplified', kicker: pick('wps-kicker', ['Get WordCamp-ready', 'Before you go', 'Learn first']), headline: pick('wps-head', [`Brush up on WordPress before *${short}*`, 'Learn WordPress, then *meet the people* behind it', `Come to ${short} *ready to learn*`]), line: 'Free tutorials by WPSimplified, in Hindi and English', body: 'points', points: ['Step-by-step video tutorials', 'Podcasts with the community', 'Shorts: one tip in a minute'] },
    { key: 'moments', label: 'Share your moments', kicker: hashtag || 'Share it', headline: pick('mom-head', [`Share your *${short}* moments`, 'Your photos make the day *last*', 'Post it, tag it, *keep it*']), line: `${hashtag ? `Tag ${hashtag} · ` : ''}Your day stays in CampBuddy Memories`, body: 'points', points: [hashtag ? `Post with ${hashtag}` : 'Post your photos', 'Add #CampBuddy and #WPSimplified', 'Save your day in CampBuddy Memories'] },
  ];
  // The sample's own words where it tests them (the shortest, the longest, Devanagari, Hinglish).
  for (const post of posts) Object.assign(post, s.override?.[post.key] ?? {});
  const order = ['save-the-date', 'venue', 'speakers', 'sponsors', 'schedule', 'first-timer', 'contributor', 'wpsimplified', 'app', 'meet', 'countdown', 'today', 'spotlight', 'moments', 'thank-you'];
  const timed = s.sessions.length > 0;
  return order.map((k) => posts.find((p) => p.key === k)).filter((p) => timed || !['today', 'spotlight'].includes(p.key)).map((p) => ({ ...p, captions: { instagram: '', linkedin: '', x: '' }, caption: '' }));
}

function daysUntil(start) {
  if (!start) return null;
  const today = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Kolkata', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
  const d = Math.round((Date.parse(`${start}T00:00:00Z`) - Date.parse(`${today}T00:00:00Z`)) / 86400000);
  return d > 0 ? d : null;
}

/** A kit (SocialKit::data()) from a sample's facts. */
function kitOf(s) {
  const slug = s.slug;
  const qr = `https://campbuddy.club/event/${slug}?utm_source=campbuddy&utm_medium=social&utm_campaign=${slug}`;
  const sessions = s.sessions.map((x, i) => ({ id: i + 1, title: x.title, at: `${x.day}T${x.at}:00+05:30`, day: x.day, time: x.time, track: x.track ?? null, type: 'session', speakers: x.speakers.map((n) => n.name), photos: x.speakers.map((n) => n.photo).filter(Boolean) }));
  const dayKeys = [...new Set(sessions.map((x) => x.day))];
  const label = (d) => new Intl.DateTimeFormat('en-GB', { weekday: 'short', day: 'numeric', month: 'short', timeZone: 'UTC' }).format(new Date(`${d}T00:00:00Z`)).replace(',', '');
  s.daysToGo = daysUntil(s.starts);
  s.speakerList = s.people.filter((p) => p.roles.includes('speaker'));
  const kit = {
    event: { name: s.name, dates: s.dates, place: s.place, site: `https://${slug}.wordcamp.org/2026`, app: qr, hashtags: s.hashtags, logo: s.logo, mark: s.logo, slug, starts: s.starts, timezone: 'Asia/Kolkata', qr, seed: crc32(slug) },
    colors: s.colors,
    colorsSaved: true,
    posts: postsFor(s),
    people: s.people.map((p, i) => ({ id: i + 1, name: p.name, photo: p.photo ?? null, roles: p.roles, role_labels: p.roles.map((r) => LABELS[r]), talk: p.talk ?? null, card: p.card ?? null, link: `${qr}&utm_content=people-${(p.roles[0] ?? 'camp-card').replace('_', '-')}`, captions: { instagram: '', linkedin: '', x: '' }, caption: '' })),
    speakers: s.speakerList.map((p) => ({ name: p.name, photo: p.photo ?? null })),
    sponsors: s.sponsors,
    schedule: { days: dayKeys.map((d) => ({ key: d, label: label(d) })), sessions, next: sessions.find((x) => x.speakers.length)?.id ?? sessions[0]?.id ?? null },
    tables: s.tables,
    short: s.short,
    site: { colors: [], fonts: {}, art: [] },
  };
  return kit;
}

/** Every sample: its kit, its site artwork (photo, illustration), the names the checks expect whole. */
export async function makeSamples() {
  const f = {
    bright: await portrait('bright', 1, { skin: '#f1c7a5', hair: '#6b4b2a', shirt: '#e8eef5' }),
    dark: await portrait('dark', 2, { skin: '#8d5a3c', hair: '#0d0d0d', shirt: '#1d1f24' }),
    busy: await portrait('busy', 3, { skin: '#c68863', shirt: '#b0303a' }),
    plain: await portrait('plain', 4, { skin: '#a8714c', shirt: '#335c3b' }),
    low: await portrait('plain', 5, { dy: 120, skin: '#d29a76', shirt: '#4a3b8f' }),
    left: await portrait('bright', 6, { dx: -150, skin: '#b67b55', shirt: '#7a5a1f' }),
    busy2: await portrait('busy', 7, { skin: '#e0ac8a', hair: '#3a2a1a', shirt: '#0f766e' }),
    dark2: await portrait('dark', 8, { skin: '#6f4630', hair: '#202020', shirt: '#5b1f2b' }),
  };
  const places = { bright: await place('bright', 11), dark: await place('dark', 12), busy: await place('busy', 13) };
  const sky = await skyline(21);

  const talk = (day, at, time, title, speakers, track = 'Track 1') => ({ day, at, time, title, speakers, track });
  const sp = (name, hue, shape, light = false) => sponsorLogo(name, hue, shape, light).then((logo) => ({ name, logo, tier: 'Gold' }));

  // ---- typical ----
  const typicalSpeakers = [
    ['Amit Singh', f.plain, 'Blocks for people who hate blocks'],
    ['Riya Sharma', f.bright, 'Ship faster with the Interactivity API'],
    ['Karthik Iyer', f.dark, 'Making WooCommerce stores load in a second'],
    ['Neha Gupta', f.busy, 'Accessibility checks you can run today'],
    ['Arjun Mehta', null, 'From freelancer to agency in two years'],
    ['Sana Qureshi', f.low, 'Writing docs people actually read'],
    ['Vikram Rao', f.left, 'Headless WordPress without the headache'],
    ['Pooja Nair', f.busy2, 'Theme.json in twenty minutes'],
    ['Rahul Verma', f.dark2, 'Security basics for small sites'],
    ['Anjali Das', f.plain, 'Translating WordPress into your language'],
    ['Farhan Ali', null, 'Testing plugins before your users do'],
    ['Meera Pillai', f.bright, 'Design systems with the Site Editor'],
  ].map(([name, photo, t]) => ({ name, photo, roles: ['speaker'], talk: t }));
  const typical = {
    id: 'typical', slug: 'wordcamp-delhi-2026', name: 'WordCamp Delhi 2026', short: 'WordCamp Delhi', dates: '12–13 Dec 2026', starts: '2026-12-12', place: 'India Habitat Centre',
    hashtags: '#WordCamp #WordPress #WCDelhi #WCDelhi2026', hashtag: '#WCDelhi', tagline: 'Dil se Dilli, code se community',
    colors: { primary: '#1d4e89', secondary: '#f2a541', ink: '#14213d', paper: '#fbf8f1' },
    logo: await eventLogo(['WordCamp', 'Delhi 2026'], '#1d4e89'),
    people: [
      { name: 'Anup Kumar', photo: f.plain, roles: ['organizer'] },
      { name: 'Devyanshi Wadhwa', photo: f.bright, roles: ['organizer'] },
      { name: 'Harsh Vardhan', photo: f.dark, roles: ['organizer', 'speaker'], talk: 'What organizing a WordCamp taught me' },
      ...typicalSpeakers,
      { name: 'Ishita Bose', photo: f.busy, roles: ['volunteer'] },
      { name: 'Rohan Kapoor', photo: null, roles: ['volunteer'] },
      { name: 'Nidhi Jain', photo: f.low, roles: ['table_lead'] },
      { name: 'Hosting Co', photo: null, roles: ['sponsor'] },
      { name: 'Delhi Tech Daily', photo: f.left, roles: ['media_partner'] },
      { name: 'Kabir Malhotra', photo: f.busy2, roles: ['microsponsor'] },
      { name: 'Tara Menon', photo: f.dark2, roles: [], card: { role: 'Product designer', askMeAbout: 'Block themes' } },
    ],
    sponsors: await Promise.all([sp('Acme Hosting', 210, 'wide'), sp('Pixel Labs', 340, 'square'), sp('Bharat Cloud', 120, 'wide'), sp('Chai Code', 30, 'tall'), sp('Snow Studio', 0, 'wide', true), sp('Lotus WP', 280, 'square'), sp('Kite Commerce', 190, 'wide'), sp('Indus Themes', 60, 'square'), { name: 'Small Biz Without A Logo', logo: null, tier: 'Silver' }]),
    tables: [
      { name: 'Polyglots', place: 'Room 2', leads: ['Nidhi Jain'] }, { name: 'Core', place: 'Hall A', leads: ['Amit Singh'] }, { name: 'Training', place: 'Room 3', leads: [] },
      { name: 'Accessibility', place: 'Room 4', leads: ['Neha Gupta'] }, { name: 'Photos', place: 'Lobby', leads: [] }, { name: 'Marketing', place: 'Room 5', leads: ['Riya Sharma'] },
    ],
    art: { photo: places.busy, illustration: sky },
  };
  const ts = typicalSpeakers;
  typical.sessions = [
    talk('2026-12-12', '09:00', '9:00 AM', 'Registration and breakfast', []),
    talk('2026-12-12', '10:00', '10:00 AM', ts[0].talk, [ts[0]]),
    talk('2026-12-12', '11:00', '11:00 AM', ts[1].talk, [ts[1]], 'Track 2'),
    talk('2026-12-12', '12:00', '12:00 PM', ts[2].talk, [ts[2], ts[3]]),
    talk('2026-12-12', '14:00', '2:00 PM', ts[4].talk, [ts[4]]),
    talk('2026-12-12', '15:00', '3:00 PM', ts[5].talk, [ts[5]], 'Track 2'),
    talk('2026-12-13', '10:00', '10:00 AM', ts[6].talk, [ts[6]]),
    talk('2026-12-13', '11:00', '11:00 AM', ts[7].talk, [ts[7]]),
    talk('2026-12-13', '12:00', '12:00 PM', ts[8].talk, [ts[8], ts[9]], 'Track 2'),
    talk('2026-12-13', '14:00', '2:00 PM', ts[10].talk, [ts[10], ts[11]]),
  ];

  // ---- short ----
  const short = {
    id: 'short', slug: 'wordcamp-goa-2027', name: 'WordCamp Goa 2027', short: 'WordCamp Goa', dates: '6 Feb 2027', starts: '2027-02-06', place: 'Panaji',
    hashtags: '#WordCamp #WordPress #WCGoa #WCGoa2027', hashtag: '#WCGoa', tagline: null,
    colors: { primary: '#7a1f2b', secondary: '#e0a11b', ink: '#231f20', paper: '#fff8ee' },
    logo: await eventLogo(['WordCamp', 'Goa'], '#7a1f2b'),
    people: [
      { name: 'Ana Li', photo: f.bright, roles: ['speaker'], talk: 'Blocks' },
      { name: 'Bo', photo: null, roles: ['organizer'] },
      { name: 'Jo Dsouza', photo: f.dark, roles: ['volunteer'] },
    ],
    sponsors: await Promise.all([sp('Sun', 40, 'square')]),
    tables: [{ name: 'Core', place: null, leads: [] }],
    override: {
      'save-the-date': { kicker: 'Hi', headline: 'Goa!' },
      venue: { headline: 'Panaji' },
      speakers: { headline: 'Speakers' },
      app: { headline: 'CampBuddy', line: '' },
      meet: { headline: 'Say hi' },
      'thank-you': { headline: 'Thanks!' },
    },
    art: { photo: places.bright, illustration: null },
  };
  short.sessions = [talk('2027-02-06', '10:00', '10:00 AM', 'Blocks', [short.people[0]])];

  // ---- long ----
  const venkata = { name: 'Venkata Subrahmanya Lakshmi Narasimha Rao', photo: f.busy, roles: ['speaker', 'organizer'], talk: 'Everything you need for two days of WordPress in one pocket' };
  const longPeople = [
    venkata,
    { name: 'Priyadarshini Ramachandran-Iyer', photo: f.dark, roles: ['speaker'], talk: 'वर्डप्रेस सीखें: ब्लॉक थीम से अपनी पहली वेबसाइट बनाइए' },
    { name: 'Mohammed Abdul Rahman Siddiqui', photo: null, roles: ['speaker'], talk: 'Pehla plugin banao: chhote steps mein, bina dar ke, apne haath se' },
    { name: 'Chandrasekharan Unnikrishnan Nair', photo: f.bright, roles: ['volunteer'] },
    { name: 'Thiruvananthapuram Digital Media Collective', photo: f.left, roles: ['media_partner'] },
    { name: 'Anantha Padmanabhan Krishnamoorthy', photo: f.low, roles: ['table_lead'] },
    { name: 'Lakshmi', photo: f.plain, roles: ['speaker'], talk: 'Faster sites with smaller images' },
  ];
  const long = {
    id: 'long', slug: 'wordcamp-thiruvananthapuram-2026', name: 'WordCamp Thiruvananthapuram 2026', short: 'WordCamp Thiruvananthapuram', dates: '28 Nov – 1 Dec 2026', starts: '2026-11-28',
    place: 'Jawaharlal Nehru Auditorium, Kerala University Senate House Campus',
    hashtags: '#WordCamp #WordPress #WCThiruvananthapuram #WCThiruvananthapuram2026', hashtag: '#WCThiruvananthapuram', tagline: 'Pehla WordCamp? Chalo, saath mein seekhte hain',
    colors: { primary: '#14532d', secondary: '#d4a017', ink: '#0b1f14', paper: '#f6faf3' },
    logo: await eventLogo(['WordCamp', 'Thiruvananthapuram'], '#14532d', true),
    people: longPeople,
    sponsors: await Promise.all([sp('Malabar Coast Hosting and Cloud Services', 20, 'wide'), sp('Travancore Web Studio', 150, 'tall'), { name: 'Kerala Startup Mission Digital Commerce Programme', logo: null, tier: 'Gold' }, sp('Backwaters Dev', 200, 'square')]),
    tables: [{ name: 'Polyglots (Malayalam, Hindi and Tamil translations)', place: 'Seminar Hall 2, first floor', leads: ['Anantha Padmanabhan Krishnamoorthy', 'Venkata Subrahmanya Lakshmi Narasimha Rao'] }, { name: 'Core', place: 'Main Hall', leads: [] }],
    override: {
      'save-the-date': { headline: 'Everything you need for two days of WordPress in one pocket' },
      'first-timer': { headline: 'Pehla WordCamp? *Chalo saath chalein*', kicker: 'Pehla WordCamp?' },
      wpsimplified: { headline: 'वर्डप्रेस सीखें, *साथ मिलकर*', line: 'WPSimplified के मुफ़्त ट्यूटोरियल, हिंदी और अंग्रेज़ी में' },
      meet: { headline: 'Akele mat aao: *apne log dhoondo*' },
      app: { line: 'Schedule, people, reminders and Contributor Day tables, all in one place' },
    },
    art: { photo: places.dark, illustration: sky },
  };
  long.sessions = [
    talk('2026-11-28', '09:30', '9:30 AM', venkata.talk, [venkata, longPeople[2]], 'Main Hall (Ground Floor)'),
    talk('2026-11-28', '11:00', '11:00 AM', longPeople[1].talk, [longPeople[1]], 'Seminar Hall 2'),
    talk('2026-11-28', '12:30', '12:30 PM', longPeople[2].talk, [longPeople[2], longPeople[1], venkata, longPeople[6]], 'Main Hall (Ground Floor)'),
    talk('2026-11-29', '10:00', '10:00 AM', longPeople[6].talk, [longPeople[6]], 'Seminar Hall 2'),
  ];

  // ---- little ----
  const little = {
    id: 'little', slug: 'wordcamp-nagpur-2027', name: 'WordCamp Nagpur 2027', short: 'WordCamp Nagpur', dates: '', starts: null, place: '',
    hashtags: '#WordCamp #WordPress #WCNagpur', hashtag: '#WCNagpur', tagline: null,
    colors: { primary: '#7a1f2b', secondary: '#e0a11b', ink: '#231f20', paper: '#fff8ee' },
    logo: null, people: [], sponsors: [], tables: [], sessions: [], art: { photo: null, illustration: null },
  };

  // ---- contrast: colours an admin saved that fail (light main, grey text) ----
  const contrast = {
    id: 'contrast', slug: 'wordcamp-kochi-2026', name: 'WordCamp Kochi 2026', short: 'WordCamp Kochi', dates: '19–20 Dec 2026', starts: '2026-12-19', place: 'Bolgatty Palace',
    hashtags: '#WordCamp #WordPress #WCKochi #WCKochi2026', hashtag: '#WCKochi', tagline: null,
    colors: { primary: '#ffd23f', secondary: '#7ae7c7', ink: '#9aa0a6', paper: '#ffffff' },
    logo: await eventLogo(['WordCamp', 'Kochi'], '#ffd23f'),
    people: [typical.people[0], typical.people[2], typicalSpeakers[1], typicalSpeakers[3], typical.people[16]],
    sponsors: typical.sponsors.slice(0, 4),
    tables: typical.tables.slice(0, 3),
    art: { photo: places.bright, illustration: null },
  };
  contrast.sessions = typical.sessions.slice(0, 5).map((x) => ({ ...x, day: '2026-12-19' }));

  const all = [typical, short, long, little, contrast];
  return Object.fromEntries(all.map((s) => [s.id, {
    id: s.id,
    kit: kitOf(s),
    art: s.art,
    names: s.people.map((p) => p.name),
  }]));
}
