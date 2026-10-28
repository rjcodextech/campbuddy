import fs from 'node:fs';
import path from 'node:path';

const reelsPath = 'E:/Sites/InHouse/campbuddy/marketing/usp-campaign/reels/reels.json';
const data = JSON.parse(fs.readFileSync(reelsPath, 'utf8'));

const topicScreens = {
  r01: { card: 'bengaluru/home-now.png', phone: 'bengaluru/guide.png', img: 'bengaluru/guide.png' },
  r02: { card: 'delhi/myday-starred.png', phone: 'delhi/myday-mine.png', img: 'delhi/myday-mine.png' },
  r03: { card: 'asia/join-picked.png', phone: 'asia/explore.png', img: 'asia/explore.png' },
  r04: { card: 'bengaluru/camp-card-full.png', phone: 'bengaluru/camp-card.png', img: 'bengaluru/camp-card-full.png' },
  r05: { card: 'delhi/contribute-matches.png', phone: 'delhi/contribute.png', img: 'delhi/contribute-matches.png' },
  r06: { card: 'asia/offline-home.png', phone: 'asia/offline-guide.png', img: 'asia/offline-home.png' },
  r07: { card: 'rajasthan/memories.png', phone: 'rajasthan/thanks.png', img: 'rajasthan/memories.png' },
  r08: { card: 'delhi-late/admin-delay.png', phone: 'delhi-late/home.png', img: 'delhi-late/admin-delay.png' },
  r09: { card: 'delhi-late/banner.png', phone: 'delhi-late/next.png', img: 'delhi-late/banner.png' },
  r10: { card: 'bengaluru/deals.png', phone: 'bengaluru/steals.png', img: 'bengaluru/deals.png' }
};

for (const r of data.reels) {
  const ts = topicScreens[r.id];
  if (ts) {
    if (!r.post) r.post = {};
    r.post.card = ts.card;
    r.post.phone = ts.phone;
    if (r.post_en) { r.post_en.card = ts.card; r.post_en.phone = ts.phone; }
    if (r.post_hi) { r.post_hi.card = ts.card; r.post_hi.phone = ts.phone; }
    if (r.cover) { r.cover.img = ts.img; r.cover.phone = ts.phone; r.cover.card = ts.card; }
  }
}

fs.writeFileSync(reelsPath, JSON.stringify(data, null, 2));
console.log('Successfully updated reels.json with topic-specific screen captures');
