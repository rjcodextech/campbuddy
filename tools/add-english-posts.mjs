import fs from 'node:fs';

const file = '../marketing/usp-campaign/reels/reels.json';
const data = JSON.parse(fs.readFileSync(file, 'utf8'));

const englishPosts = {
  r01: {
    kicker: 'First WordCamp?',
    title: 'Never alone with *CampBuddy*',
    sub: 'Instant live schedule, a friendly first-timer guide, and fun quests to break the ice.',
    scan: 'Scan to explore WordCamp Bengaluru',
  },
  r02: {
    kicker: 'My Day Schedule',
    title: 'Never miss your *favourite talks*',
    sub: 'Star sessions, curate your custom day, and get offline reminders before talks begin.',
    scan: 'Scan to build your custom schedule',
  },
  r03: {
    kicker: 'Attendee Discovery',
    title: 'Find *your people* in the crowd',
    sub: 'Select your interests, send a wave, and see names only when they wave back. Zero spam.',
    scan: 'Scan to connect at WordCamp Asia',
  },
  r04: {
    kicker: 'Digital Camp Card',
    title: 'Replace paper cards with *one QR*',
    sub: 'Share your name, role, and socials instantly via camera. 7 themes with high-res PNG download.',
    scan: 'Scan to generate your free Camp Card',
  },
  r05: {
    kicker: 'Contributor Day · 12 Dec',
    title: "Non-coder? *There's a table for you.*",
    sub: 'Answer two quick questions to find the ideal WordPress contributor team for your skills.',
    scan: 'Scan to find your contributor team',
  },
  r06: {
    kicker: '100% Offline Ready',
    title: 'Venue Wi-Fi down? *Still works.*',
    sub: 'No sign-up, no login. Open once and your entire schedule and notes stay cached on your device.',
    scan: 'Scan to access offline companion',
  },
  r07: {
    kicker: 'Post-Event Companion',
    title: 'WordCamp ends, *connections stay.*',
    sub: 'Export your personalized day as a PDF, access official photos, and carry connections forward.',
    scan: 'Scan to view WordCamp memories',
  },
  r08: {
    kicker: 'For WordCamp Organizers',
    title: 'A turn-key *companion app*',
    sub: 'Syncs schedules, speakers, and sponsors automatically from WordCamp.org. 100% free & open.',
    scan: 'For your WordCamp, email: hello@wpsimplified.in',
  },
  r09: {
    kicker: 'Live Schedule Control',
    title: 'One setting updates *every schedule*',
    sub: 'When sessions run late, organizers push a time shift and every attendee gets revised reminders.',
    scan: 'For your WordCamp, email: hello@wpsimplified.in',
  },
  r10: {
    kicker: 'Exclusive Community Deals',
    title: 'Curated sponsor perks *in one place*',
    sub: 'Exclusive deals on premium WordPress hosting, plugins, and free dev tools right in the app.',
    scan: 'Scan to unlock community perks',
  },
};

for (const r of data.reels) {
  if (englishPosts[r.id]) {
    // Preserve Hinglish as post_hi
    r.post_hi = { ...r.post };
    r.post_en = {
      ...r.post,
      ...englishPosts[r.id],
    };
  }
}

fs.writeFileSync(file, JSON.stringify(data, null, '\t') + '\n');
console.log('Successfully added English posts (post_en) to all 10 reels in reels.json');
