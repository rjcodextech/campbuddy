# CampBuddy Development Skill

## Purpose

CampBuddy is a lightweight, mobile-first companion for WordCamp attendees, especially first-time attendees.

Its job is to reduce confusion and social friction before and during a WordCamp by helping attendees:

- understand what to do before the event,
- know what is happening and what they can do next,
- plan a simple personal day,
- meet people without awkwardness,
- discover Contributor Day opportunities,
- create a lightweight Camp Card for introductions,
- find essential event links quickly,
- continue useful connections after the event.

CampBuddy does **not** replace the official WordCamp website, schedule, ticketing system, attendee directory, or organizer communications. It is a friendly companion layer on top of official event information.

---

## Core Product Principles

### 1. First-timer first

Always design for someone attending their first WordCamp.

Assume they may not know what Contributor Day is, what Make WordPress teams are, what hallway tracks are, how WordCamp schedules work, whether they are allowed to talk to speakers, whether Contributor Day requires coding, where to go, or what people normally do between sessions.

Do not assume WordPress community vocabulary is already understood. When community-specific terminology is necessary, explain it in plain language.

### 2. Make WordCamp feel smaller

A WordCamp can feel overwhelming. CampBuddy should reduce this feeling by turning the event into small, manageable actions.

Prefer prompts such as:

- "Say hello to one person."
- "Pick two sessions you really want to attend."
- "Visit one contributor table."
- "Take a 15-minute break."
- "Ask someone what they are working on."

Avoid complicated task systems, competitive scoring, public leaderboards, or mechanics that pressure attendees.

### 3. Useful before clever

Every feature must solve a real attendee problem.

Before adding a feature, ask:

1. Does this help someone understand the event?
2. Does this make attending easier?
3. Does this help them meet people?
4. Does this reduce anxiety or confusion?
5. Can this work without an account or backend?

If the answer is no to most of these, do not add the feature.

### 4. Do not replace official information

Official WordCamp websites remain the source of truth.

CampBuddy may summarize official information, but it must clearly link back to the organizer's page where appropriate.

Never invent session times, speaker information, venue instructions, accessibility information, organizer policies, Contributor Day details, or ticket information.

If official information is unavailable, omit it or clearly label the content as a CampBuddy suggestion.

---

## Visual Style

### General direction

CampBuddy should feel:

- warm,
- modern,
- welcoming,
- community-oriented,
- lightweight,
- slightly playful,
- professional enough to be trusted.

It should **not** look like an enterprise dashboard, generic SaaS admin panel, banking application, corporate conference app, or over-designed social network.

The experience should feel like a friendly event guide in your pocket.

### Mobile first

Always design for mobile first.

Primary expected usage:

- phones at the venue,
- one-handed use,
- quick interactions while walking,
- poor or inconsistent venue Wi-Fi,
- bright indoor/outdoor conditions.

Desktop and tablet layouts should enhance the mobile layout rather than introduce an entirely different interface.

Recommended application content width:

```css
max-width: 760px;
```

### Layout

Use generous spacing.

Recommended defaults:

```text
Page padding:     14px–24px
Card padding:     16px–22px
Card radius:      18px–28px
Section spacing:  24px–32px
```

Avoid dense dashboards. Break information into clear sections. One screen should generally have one obvious primary purpose.

### Color system

The Rajasthan prototype uses a warm palette inspired by Rajasthan.

```css
--ink: #231f20;
--paper: #fffaf4;
--card: #ffffff;
--muted: #6b625e;
--line: #eadfd6;
--primary: #7c2d12;
--primary-alt: #9a3412;
--accent: #f5b942;
--soft-accent: #fff0df;
--success: #166534;
```

For multi-event support, colors may be configurable per event, but event branding must never reduce accessibility or readability. Text/background contrast always takes priority.

### Typography

Prefer system fonts and do not require a remote font for the app to function.

```css
font-family:
  Inter,
  ui-sans-serif,
  system-ui,
  -apple-system,
  BlinkMacSystemFont,
  "Segoe UI",
  sans-serif;
```

Recommended hierarchy:

```text
Hero heading:      28–34px
Page heading:      26–32px
Section heading:   20–24px
Card heading:      15–18px
Body:              14–16px
Secondary text:    12–14px
Labels:            10–12px
```

Avoid text smaller than 10px.

### Cards

Cards are a major UI primitive.

```css
background: white;
border: 1px solid var(--line);
border-radius: 20px–26px;
```

Use shadows sparingly. Cards should feel lightweight rather than heavily elevated.

### Buttons

Primary actions should be obvious.

Use primary buttons for the main action, neutral/outlined buttons for secondary actions, and destructive styling only for destructive actions such as resetting local data.

Minimum recommended touch target height:

```text
44px
```

### Icons

Prefer simple Unicode symbols, small SVG icons, or a bundled lightweight icon system.

Do not introduce a large external icon dependency for a handful of icons. Icons must support text, not replace important labels.

---

## Voice and Content Style

CampBuddy should sound like a helpful member of the WordPress community.

Use simple language, short sentences, friendly prompts, encouraging wording, and occasional light humor.

Examples:

```text
Your WordCamp. Minus the awkward bits.

I have 10 minutes.

Make the room feel smaller.

This is my first Contributor Day. Can you help me get started?

Take a proper break. Seriously.
```

Avoid corporate jargon, marketing hype, fake urgency, childish gamification, or patronizing language.

Do not assume everyone is an extrovert. Networking suggestions should be optional and low-pressure.

---

## Navigation

Keep primary mobile navigation small.

Recommended sections:

```text
Home
My Day
Camp Quest
Contribute
Camp Card
```

Do not add permanent navigation items unless a feature is important enough to justify occupying one of these limited slots.

Secondary information belongs under a screen such as `Useful Stuff` or inside contextual screens.

---

## Home Screen

The Home screen should answer:

```text
What should I know right now?
```

Recommended order:

1. event identity,
2. countdown or current event state,
3. Right Now guidance,
4. quick actions,
5. preparation checklist,
6. useful links.

During the event, Right Now becomes more important than the countdown.

The app should adapt its guidance depending on whether the attendee is before WordCamp, on Contributor Day, on Conference Day, or after WordCamp.

---

## Right Now Rules

This is one of CampBuddy's defining features.

The panel should provide immediate, useful context.

Before event:

```text
Do one small thing today.
Finish your checklist or create your Camp Card.
```

Contributor Day:

```text
New to Contributor Day?

Walk up to a table and say:
"Hi, this is my first Contributor Day. Can you help me get started?"
```

Conference Day:

```text
You have 20 minutes before your next saved session.

Grab water, visit one sponsor, or meet someone new.
```

After event:

```text
Don't lose the good conversations.

Connect with the people you met and send the links you promised.
```

Never fabricate live event activity. Only claim a session is currently happening when the application has verified schedule data.

---

## Camp Quest

Camp Quest provides small networking prompts.

It must remain optional, private, non-competitive, and lightweight.

Good examples:

```text
Introduce yourself to someone you haven't met.
Meet someone attending from another city.
Say hello to a speaker.
Visit a sponsor you haven't heard of.
Ask one useful question.
Exchange contact details with one person you'd like to stay in touch with.
```

Do not add public scores, leaderboards, attendee rankings, social pressure, streak mechanics, or rewards requiring organizer participation.

Progress should remain local to the user's device unless the user explicitly chooses otherwise.

---

## Free-Time Missions

The application may offer tiny tasks based on available time.

Recommended buckets:

```text
5 minutes
10 minutes
15 minutes
20 minutes
```

Suggestions must be realistic for a conference environment.

Examples:

```text
5 min
Say hello to the person nearest you.

10 min
Visit one sponsor and ask what they build.

15 min
Find someone working in a different WordPress role.

20 min
Visit a Contributor table and ask how newcomers can help.
```

Do not require precise indoor positioning and do not track attendee location.

---

## Contributor Day Finder

The Contributor Day finder exists to answer:

```text
Where could I help?
```

Do not frame teams as requiring expert knowledge. Match interests rather than qualifications.

Possible inputs:

```text
I like code.
I like helping people.
I like writing or teaching.
I like testing things.
I just want to try.
```

Possible teams include Core, Test, Documentation, Polyglots, Community, Training, and Design.

Recommendations are starting points, not authoritative assignments. Make it clear attendees can explore any team.

---

## Camp Card

Camp Card is a lightweight personal introduction card.

It may contain:

```text
Name
Role / what I do
Ask me about
I'd love to meet
One optional link
```

Example:

```text
Prathamesh

Technical Support / WordPress Multisite

ASK ME ABOUT
Multisite
Scaling WordPress
Support

I'D LOVE TO MEET
WordPress engineers
Agency owners
Contributors
```

The Camp Card must be easy to show directly from a phone. QR sharing may be supported.

### Camp Card privacy

Do not send Camp Card data to CampBuddy servers by default.

Preferred implementation:

```text
localStorage / IndexedDB
+
client-side QR generation
```

Avoid third-party QR APIs in production. Do not automatically publish Camp Cards into a public directory. Sharing must be an explicit attendee action.

---

## Schedule / My Day

CampBuddy should not replicate an entire conference schedule unless necessary.

`My Day` is intended to answer:

```text
What do I personally care about today?
```

Users should be able to save sessions or useful event anchors.

Important distinction:

```text
Official schedule item
CampBuddy suggestion
```

These must never be visually ambiguous.

---

## Event Data Architecture

Event-specific information must be kept outside core application logic.

Preferred structure:

```text
/events/
  rajasthan-2026.json
  pune-2027.json
  mumbai-2027.json
```

Example:

```json
{
  "id": "rajasthan-2026",
  "name": "WordCamp Rajasthan 2026",
  "timezone": "Asia/Kolkata",
  "dates": {
    "starts": "2026-10-03T09:00:00+05:30",
    "conference": "2026-10-04T09:00:00+05:30"
  },
  "venue": {
    "name": "Rajasthan International Centre",
    "address": "Jhalana Doongri, Jaipur, Rajasthan"
  },
  "links": {
    "official": "https://rajasthan.wordcamp.org/2026/",
    "schedule": "https://rajasthan.wordcamp.org/2026/schedule/"
  }
}
```

The application should be able to load a new event without modifying core UI code.

---

## Data Sources

Preferred source order:

1. official WordCamp website,
2. official WordCamp / WordPress APIs,
3. organizer-provided data,
4. manually maintained event configuration.

Never scrape or copy information from unofficial sites when an official source exists.

Importer jobs must retain enough metadata to identify where information came from.

---

## Offline and PWA Requirements

CampBuddy should remain a Progressive Web App.

Minimum expectations:

- installable where supported,
- service worker,
- offline application shell,
- cached event information,
- local attendee preferences,
- graceful behavior on poor networks.

Venue Wi-Fi must be treated as unreliable.

Core functionality should not depend on a network connection after initial load.

Core offline features should include:

```text
Home
saved schedule
checklist
Camp Quest
Contributor Finder
Camp Card
venue/event information already cached
```

External official links may naturally require connectivity.

### Service Worker

Keep the service worker simple.

Cache only application assets and safe event data.

Use explicit cache versions:

```js
const CACHE = "campbuddy-v2";
```

When deployment changes application assets materially, update the cache version — and bump the matching `?v=` query string on `styles.css`/`app.js` in `index.html` (and in the SW's own `ASSETS` list) to the same number. `index.html` itself isn't cached at the CDN, but `styles.css`/`app.js` are; without a version bump, the CDN can keep serving a stale build's CSS/JS against a freshly deployed HTML/JS pair, breaking layout for real visitors even though the origin has the right files.

Do not create complicated service-worker logic unless necessary. Avoid caching external pages aggressively.

---

## Storage

Use `localStorage` for small preference data.

Use `IndexedDB` only when data size or structure justifies it.

Do not add a database merely to store checklist state, saved sessions, interests, quest completion, or Camp Card information.

---

## Backend Policy

CampBuddy should remain static-first.

Do not introduce a backend unless a feature genuinely requires one.

Features that do **not** require a backend:

```text
checklists
saved sessions
event configuration
networking missions
Contributor Finder
Camp Card
QR generation
offline support
event countdown
PWA installation
```

Possible future features that may justify a backend:

```text
organizer-managed event configuration
optional cross-device sync
anonymous aggregate event usage
community-submitted tips
push notifications
```

Even then, prefer the smallest possible backend.

---

## Dependencies

Keep dependencies minimal.

Before installing a package, ask:

```text
Can this reasonably be done with browser APIs or a tiny local module?
```

Avoid large UI frameworks, massive utility libraries, heavy date libraries, large icon packages, or unnecessary state-management frameworks.

CampBuddy should remain easy for community contributors to understand.

---

## Framework Policy

The application may use vanilla JavaScript, Preact, React, or Vue if there is a clear benefit.

For the current size of the application, vanilla JavaScript or Preact is preferred.

Do not rewrite the app into a framework solely because a framework is available.

---

## Code Style

Keep code straightforward.

Prefer:

```js
function openMission(minutes) {
  // ...
}
```

over unnecessary abstraction.

Use descriptive variable names, small functions, clear data structures, and comments for non-obvious decisions.

Avoid excessive class hierarchies, clever metaprogramming, premature abstractions, deeply nested callbacks, and unnecessary build complexity.

---

## File Organization

As CampBuddy grows, prefer:

```text
/
  index.html
  manifest.webmanifest
  sw.js

  /assets/
    icons/
    styles/

  /src/
    app.js
    router.js
    storage.js
    schedule.js
    quests.js
    contributor.js
    camp-card.js

  /events/
    rajasthan-2026.json
```

Do not split a tiny feature across many files purely for architectural purity. File organization should improve understanding.

---

## Accessibility

Accessibility is mandatory.

Minimum requirements:

- semantic HTML,
- keyboard navigation,
- visible focus styles,
- sufficiently large touch targets,
- meaningful button labels,
- good contrast,
- forms with labels,
- no interaction dependent only on color,
- respect `prefers-reduced-motion`,
- no important information conveyed only by icons.

Use native HTML controls whenever practical.

Examples:

```html
<button>
<label>
<input>
<dialog>
<nav>
```

Avoid rebuilding native controls unnecessarily.

---

## Motion

Use subtle motion only.

Good:

```text
small button feedback
gentle card transitions
progress updates
light install confirmation
```

Avoid large parallax effects, constant animations, confetti for routine actions, or motion that distracts during sessions.

Respect:

```css
@media (prefers-reduced-motion: reduce)
```

---

## Performance

CampBuddy should load quickly on mobile connections.

Targets:

```text
Initial app shell: as small as practical
JavaScript: minimal
Images: optional and optimized
No blocking third-party scripts
No mandatory external font
```

Do not add analytics, chat widgets, marketing pixels, or external scripts without a strong reason.

---

## Privacy

CampBuddy should be privacy-friendly by default.

Principles:

```text
No account required.
No unnecessary personal data collection.
No attendee tracking.
No precise location tracking.
No public profile by default.
No selling or sharing attendee data.
```

If analytics are ever added, prefer anonymous, aggregate, privacy-preserving analytics.

Explain clearly what data is stored and where.

---

## Security

Because CampBuddy may eventually import external event data:

- escape untrusted text before rendering,
- sanitize imported HTML,
- avoid `innerHTML` with uncontrolled content,
- validate URLs,
- restrict externally loaded content,
- do not trust WordCamp page markup automatically.

Do not store secrets in client-side code. There should be no API keys in the repository unless the service explicitly supports public client-side keys.

---

## External Links

Use:

```html
target="_blank"
rel="noopener"
```

for links opening new tabs.

Official event links should be preferred over copied information for anything likely to change.

---

## Hosting

CampBuddy should remain deployable to static hosting.

Supported targets should include:

```text
Apache shared hosting
GitHub Pages
Cloudflare Pages
Netlify
Vercel
Nginx
Docker
```

A normal deployment should not require PHP, MySQL, a Node.js runtime, Docker, cron, or server-side sessions.

Build tooling may be used during development, but the production output should ideally remain static.

### Apache compatibility

CampBuddy must work when uploaded to ordinary shared Apache hosting.

Avoid requiring Apache modules beyond normal static hosting.

Prefer hash-based routing or generated static routes where practical.

Do not require complex rewrite rules for core functionality.

---

## Multi-WordCamp Direction

Every major feature should be evaluated against:

```text
Could another WordCamp use this without modifying application code?
```

Event-specific things belong in event configuration.

Core application behavior should remain generic.

Examples of configurable event details:

```text
name
dates
timezone
venue
branding
schedule URL
Contributor Day URL
contact URL
tracks
sessions
speakers
useful links
event-specific preparation items
```

---

## Things CampBuddy Should Not Become

Do not turn CampBuddy into:

- a ticketing platform,
- a replacement WordCamp website,
- another Meetup product,
- a LinkedIn clone,
- an attendee surveillance system,
- a public attendee-ranking system,
- a chat platform,
- a sponsor advertising platform,
- a complicated CMS,
- an event organizer ERP.

If a feature pushes the project strongly toward one of these categories, reconsider it.

---

## Development Workflow

When making changes:

1. Identify the attendee problem being solved.
2. Confirm whether official event information is required.
3. Keep event-specific content in event data.
4. Implement mobile-first.
5. Test offline behavior where relevant.
6. Test local persistence.
7. Test keyboard and touch interaction.
8. Test narrow mobile widths.
9. Avoid touching unrelated files.
10. Keep the implementation as small as reasonably possible.

---

## Important Rule for Coding Agents

When working on an existing CampBuddy codebase:

**Do not modify unrelated files.**

Before changing architecture, inspect the existing implementation.

Prefer targeted changes.

Do not:

- rewrite working code without a reason,
- replace the styling system during an unrelated task,
- change event data while fixing UI,
- add packages for trivial functionality,
- remove existing behavior unless explicitly requested.

Preserve backward compatibility with saved local data whenever reasonably possible.

---

## Testing Checklist

### General

- App loads without console errors.
- Navigation works.
- Mobile layout works around 320px–430px widths.
- Tablet layout remains usable.
- Desktop layout does not become overly wide.

### Storage

- Checklist survives refresh.
- Quest completion survives refresh.
- Saved schedule survives refresh.
- Contributor preferences survive refresh.
- Camp Card survives refresh.
- Reset actually removes data.

### Offline

- App shell opens after losing connection.
- Saved content remains accessible.
- Core navigation works offline.
- App does not get stuck because an external service is unavailable.

### Camp Card

- Long names do not destroy layout.
- Long role text wraps correctly.
- Tags wrap correctly.
- Invalid links do not cause code errors.
- QR sharing is optional and graceful.

### Schedule

- Timezone is correct.
- Official and suggested items are distinguishable.
- Missing session information does not generate fake data.

### Accessibility

- Entire application can be navigated by keyboard.
- Focus state is visible.
- Buttons have understandable labels.
- Text remains readable at browser zoom.
- Screen-reader structure remains logical.

---

## Definition of Done

A CampBuddy feature is done when:

- it solves the intended attendee problem,
- it works well on mobile,
- it does not depend unnecessarily on connectivity,
- it does not collect unnecessary data,
- it is understandable by a first-time attendee,
- it does not contradict official WordCamp information,
- it does not introduce unnecessary architectural complexity,
- it has been tested alongside existing functionality.

---

## Product North Star

When unsure about a design or engineering decision, use this question:

> Does this make a first WordCamp easier, friendlier, and less confusing?

If yes, it probably belongs in CampBuddy.

If it mostly makes the application look more sophisticated, it probably does not.
