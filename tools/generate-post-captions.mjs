import fs from 'node:fs';
import path from 'node:path';

const REELS_FILE = '../marketing/usp-campaign/reels/reels.json';
const POSTS_DIR = '../marketing/final-assets/02-social-posts';

const data = JSON.parse(fs.readFileSync(REELS_FILE, 'utf8'));

const copyDeck = {
  r01: {
    en: {
      headline: 'First time at a WordCamp? You are never alone with CampBuddy.',
      hook: 'Stepping into a WordCamp with 500+ attendees can feel overwhelming. Where do you go first? Who do you talk to?',
      body: `Here is the secret to getting the absolute most out of your first WordCamp:

🌟 1. Live "Now" Guidance: Instantly see what is happening right this second across all tracks—no lost wandering.
🗺️ 2. First-Timer 5-Minute Guide: The unwritten rules of WordCamp, hallway tracks, and networking made simple.
🎯 3. Fun Icebreaker Quests: Friendly micro-challenges designed to help you start conversations naturally.
🔒 4. 100% Free & Zero Sign-Up: Open it in any mobile browser on venue Wi-Fi. No password, no personal data harvested.

Meet your community, discover talks that matter, and leave with lasting connections.`,
      cta: '👉 Try CampBuddy now for your WordCamp: https://campbuddy.club',
      hashtags: '#WordCamp #WordPress #Community #CampBuddy #WordPressIndia #Networking #OpenSource',
    },
    hi: {
      headline: 'Pehla WordCamp? Akele nahi, CampBuddy saath hai!',
      hook: 'WordCamp ke bheed-bhaad wale hall mein enter karte hi sabse pehla sawaal: kahan se shuru karun aur kis se baat karun?',
      body: `CampBuddy ke saath aapka pehla WordCamp experience banega 100% aasaan:

🌟 1. Live Home Screen: Abhi kaunsa talk chal raha hai aur agla kya hai, turant screen par.
🗺️ 2. 5-Minute First-Timer Guide: Hallway track aur networking ke friendly tips.
🎯 3. Fun Icebreaker Quests: Chhote fun tasks jo logon se baat shuru karna aasaan banate hain.
🔒 4. Zero Sign-Up & 100% Free: Koi account ya password ki zaroorat nahi. Venue Wi-Fi par bhi super-fast.`,
      cta: '👉 Abhi kholo bina kisi sign-up ke: https://campbuddy.club',
      hashtags: '#WordCamp #WordPress #CampBuddy #Networking #WordCampBengaluru #TechCommunity',
    },
  },
  r02: {
    en: {
      headline: 'Never miss your favourite WordCamp talks again with "My Day".',
      hook: 'Two tracks, lightning talks, workshops happening simultaneously. How do you keep track without checking messy paper pamphlets?',
      body: `Meet "My Day"—your personal WordCamp itinerary planner in CampBuddy:

⭐ 1. One-Tap Star: Tap any talk across any track to pin it to your personal schedule.
⏰ 2. Smart Venue Reminders: Receive a gentle reminder before your chosen session kicks off.
📶 3. Works 100% Offline: Venue Wi-Fi dropped? Your starred talks and timings stay saved in your phone.
📄 4. Export & Keep: When the event ends, download your complete day as a clean PDF summary.`,
      cta: '👉 Plan your schedule today: https://campbuddy.club',
      hashtags: '#WordCamp #WordPress #Schedule #Productivity #MyDay #CampBuddy #WordCampDelhi',
    },
    hi: {
      headline: 'Multi-track WordCamp mein favourite talk ab miss nahi hogi!',
      hook: 'Track 1 mein workshop aur Track 2 mein keynote—hath mein paper pamphlet leke bhatakne ki zaroorat nahi!',
      body: `CampBuddy ka "My Day" feature aapke din ko banata hai super-organized:

⭐ 1. One-Tap Star: Jo talk pasand aaye, use star karo aur apni personal list banao.
⏰ 2. Timely Reminders: Talk shuru hone se pehle gentle alert pao.
📶 3. 100% Offline Ready: Hall mein Wi-Fi na chale toh bhi aapka schedule phone mein saved rahega.
📄 4. Instant PDF Export: Din khatam hone par poore schedule ka clean PDF download karo.`,
      cta: '👉 Apni talks abhi save karo: https://campbuddy.club',
      hashtags: '#WordCamp #WordPress #MyDay #Schedule #CampBuddy #WordCampDelhi',
    },
  },
  r03: {
    en: {
      headline: 'Find your people in the crowd with zero spam & total privacy.',
      hook: 'Want to meet developers, designers, or agency founders who share your niche, without shouting in awkward networking circles?',
      body: `CampBuddy brings respectful, consent-first community discovery:

🤝 1. Pick Your Interests: Select tags like Core, WooCommerce, Blocks, SEO, or Design.
👋 2. The Consent Wave: Send a subtle wave to an anonymous profile matching your vibe.
✨ 3. Mutual Reveal Only: You only see each other's names when they wave back! Zero spam.
🔐 4. DPDPA 2023 Compliant: No public scraping, no phone number sharing, end-to-end encrypted.`,
      cta: '👉 Connect respectfully at your next WordCamp: https://campbuddy.club',
      hashtags: '#WordCamp #WordPress #Networking #PrivacyFirst #WordCampAsia #CampBuddy',
    },
    hi: {
      headline: 'Bheed mein apne matlab ke log dhoondo—bina kisi awkward networking ke!',
      hook: 'Core dev, designer, ya agency founder se milna hai par samajh nahi aa raha kaise shuru karein?',
      body: `CampBuddy ka Attendee Discovery banata hai networking ko super simple:

🤝 1. Apne Interests Chuno: Plugins, WooCommerce, Theme Design, ya Freelancing.
👋 2. Send a Wave: Matching profile ko bina naam dikhaye respectful wave bhejo.
✨ 3. Mutual Reveal: Jab samne wala bhi wave back karega, tabhi dono ko naam dikhega!
🔐 4. 100% Private & DPDPA Compliant: Koi phone number ya email public nahi hota.`,
      cta: '👉 Apne log abhi dhoondo: https://campbuddy.club',
      hashtags: '#WordCamp #WordPress #Networking #Community #CampBuddy',
    },
  },
  r04: {
    en: {
      headline: 'Replace paper visiting cards with a high-resolution Digital Camp Card.',
      hook: 'Still carrying paper business cards that end up lost in bags or trash bins by Monday morning?',
      body: `Upgrade to your personalized Digital Camp Card with CampBuddy:

📱 1. Instant Camera Swap: Show your QR code. Other attendees scan it with their phone camera to instantly save your LinkedIn, website, and portfolio.
🎨 2. 7 Gorgeous Designer Themes: Choose from Midnight, Sunset, Emerald, Daylight, and more.
🖼️ 3. 900 DPI High-Res Download: Export as a crisp graphic to save as your phone lock screen or share on WhatsApp.
⚡ 4. Zero App Install Needed: Scans directly through native phone cameras—works for everyone.`,
      cta: '👉 Create your free Digital Camp Card in 30 seconds: https://campbuddy.club',
      hashtags: '#DigitalBusinessCard #CampCard #WordCamp #WordPress #Paperless #Networking',
    },
    hi: {
      headline: 'Paper visiting card bhool jao—banao apna Instant Digital Camp Card!',
      hook: 'Visiting card kho gaya ya khatam ho gaya? Ab paper cards ka zamana gaya!',
      body: `CampBuddy Camp Card se networking hoti hai lightning fast:

📱 1. Scan & Swap: Apna QR dikhao, samne wala camera se scan karega aur direct LinkedIn/Website open!
🎨 2. 7 Beautiful Designer Styles: Apne mood aur brand ke hisaab se theme pick karo.
🖼️ 3. Print-Quality Download: High-resolution PNG download karke phone ka lock screen bana lo.
⚡ 4. Zero App Download: Kisi ko koi app install karne ki zaroorat nahi, seedha camera se kaam karta hai.`,
      cta: '👉 Apna free Camp Card abhi generate karo: https://campbuddy.club',
      hashtags: '#CampCard #VisitingCard #WordCamp #WordPress #Paperless #CampBuddy',
    },
  },
  r05: {
    en: {
      headline: "Don't write code? There's still a table waiting for you at Contributor Day.",
      hook: 'Think WordPress Contributor Day is only for hardcore PHP/React engineers? Think again!',
      body: `Over 60% of WordPress contributions have nothing to do with code:

✍️ 1. Polyglots: Translate WordPress into regional Indian and world languages.
🎨 2. Design & UX: Test mockups, refine block components, and improve user flows.
📚 3. Documentation: Write guides, review tutorials, and help beginners learn.
🤝 4. Community & Support: Answer questions on forums and organize local meetups.

CampBuddy’s 2-question team matchmaker tells you exactly which table matches your superpowers!`,
      cta: '👉 Find your Contributor Day team: https://campbuddy.club',
      hashtags: '#ContributorDay #WordPress #OpenSource #GivingBack #WordCampDelhi #CampBuddy',
    },
    hi: {
      headline: 'Coding nahi aati? Contributor Day mein aapke liye bhi table hai!',
      hook: 'Agar aapko lagta hai ki Contributor Day sirf programmers ke liye hai, toh aap bilkul galat hain!',
      body: `WordPress open source mein 60% se zyada contribution bina coding ke hota hai:

✍️ 1. Translation (Polyglots): WordPress ko apni bhasha (Hindi, Bengali, Tamil, etc.) mein translate karo.
🎨 2. Design & Photo: WordPress Photo Directory mein royalty-free photos submit karo.
📚 3. Documentation: New users ke liye tutorials aur helpful guides review karo.
🤝 4. Community Support: Forums par logon ke sawalon ke jawab do.

CampBuddy ke 2 simple questions ka answer do aur apni perfect team table dhoondo!`,
      cta: '👉 Apni table abhi dhoondo: https://campbuddy.club',
      hashtags: '#ContributorDay #WordPress #OpenSource #WordCampDelhi #CampBuddy',
    },
  },
  r06: {
    en: {
      headline: 'Crowded venue Wi-Fi gave up? CampBuddy works 100% offline.',
      hook: 'Auditorium packed with 800 people and venue Wi-Fi crawls to a halt? Your schedule shouldn’t crash when you need it most.',
      body: `CampBuddy is engineered offline-first from the ground up:

⚡ 1. Instant Cache: Load it once in your browser, and every schedule, track, talk, and room map stays cached locally.
🔒 2. Zero Sign-Up (DPDPA 2023): No account creation, no SMS OTP, no waiting for validation emails.
🔋 3. Ultra-Lightweight PWA: Loads in milliseconds, consumes minimal battery, and never lags.
🔄 4. Background Sync: Whenever connectivity flickers back, updates sync quietly without blocking your view.`,
      cta: '👉 Experience 100% offline companion: https://campbuddy.club',
      hashtags: '#OfflineFirst #PWA #WordCamp #WordPress #TechEvents #CampBuddy #Performance',
    },
    hi: {
      headline: 'Venue Wi-Fi down ho gaya? CampBuddy fir bhi 100% chalega!',
      hook: '800 logon ke beech venue Wi-Fi band pad jaye toh schedule kaise dekhoge? Tension mat lo!',
      body: `CampBuddy poori tarah Offline-First design kiya gaya hai:

⚡ 1. One-Time Cache: Ek baar kholo, poora schedule aur room details aapke phone mein save.
🔒 2. Zero Sign-Up (DPDPA 2023): Na koi login, na OTP, na password ka jhanjhat.
🔋 3. Super Fast & Light: Battery bachata hai aur bina internet ke bina ruke chalta hai.
🔄 4. Auto Sync: Jab bhi halka internet milega, updates silently sync ho jayenge.`,
      cta: '👉 Bina internet chalne wala app dekho: https://campbuddy.club',
      hashtags: '#OfflineReady #WordCamp #WordPress #CampBuddy #TechHack',
    },
  },
  r07: {
    en: {
      headline: 'WordCamp is over, but your connections and memories stay forever.',
      hook: 'Event ended, luggage packed, back at your desk. How do you keep the momentum and connections alive?',
      body: `CampBuddy stays valuable even after the closing remarks:

📄 1. Export Your Day as PDF: Download a polished, permanent record of every talk you attended, speakers met, and key notes.
📸 2. Official Photo Gallery: High-resolution event moments curated directly in the companion.
💼 3. Camp Card Archive: Review everyone you exchanged digital cards with and connect on LinkedIn with one tap.
🎟️ 4. Carry Forward to Next Camp: Your profile and settings easily transfer to the next WordCamp on your calendar!`,
      cta: '👉 Download your WordCamp summary: https://campbuddy.club',
      hashtags: '#WordCamp #WordPress #Networking #PostEvent #Memories #CampBuddy',
    },
    hi: {
      headline: 'WordCamp khatam, par yaadein aur connections hamesha ke liye!',
      hook: 'Event ke baad aksar hum bhool jaate hain ki kis se mile the aur kya seekha tha. Ab aisa nahi hoga!',
      body: `CampBuddy event ke baad bhi aapka best dost hai:

📄 1. "My Day" PDF Export: Jo talks dekhi aur jin logon se mile, unka complete PDF summary download karo.
📸 2. Official Event Photos: WordCamp ki high-quality photos seedha app ke andar browse karo.
💼 3. Saved Camp Cards: Jin logon se QR swap kiya tha, unhe ek click mein LinkedIn par connect karo.
🎟️ 4. Next Camp Ready: Aapka data agle WordCamp mein bina dobara setup kiye transfer ho jata hai!`,
      cta: '👉 Apni WordCamp yaadein save karo: https://campbuddy.club',
      hashtags: '#WordCamp #WordPress #Memories #CampBuddy #WordCampRajasthan',
    },
  },
  r08: {
    en: {
      headline: 'Organizing a WordCamp? Get a custom turn-key companion app in minutes.',
      hook: 'Spending weeks building custom event apps or answering repetitive attendee questions at the help desk?',
      body: `Give your attendees a world-class companion app without writing a single line of code:

🔄 1. Auto-Sync from WordCamp.org: Schedules, speakers, and sponsors sync automatically every 15 minutes.
🎨 2. Your Event Brand: Styled with your official WordCamp logo, dates, colors, and venue details.
📊 3. Free & Open Source: No fees, no commercial lock-in, built with love for the global WordPress community.
🛡️ 4. DPDPA 2023 & GDPR Compliant: Zero attendee data collected, zero server maintenance overhead.`,
      cta: '👉 Get CampBuddy for your WordCamp: email hello@wpsimplified.in or visit https://campbuddy.club',
      hashtags: '#WordCampOrganizers #WordPress #EventTech #Community #OpenSource #CampBuddy',
    },
    hi: {
      headline: 'WordCamp Organize kar rahe hain? Pao ready-made companion app bilkul FREE!',
      hook: 'App banane mein weeks kharch karne ki zaroorat nahi—CampBuddy aapke event ke liye ready hai!',
      body: `Apne WordCamp attendees ko do ek seamless, high-tech experience:

🔄 1. Automatic Sync: Schedule, speakers aur sponsors WordCamp.org se har 15 minute mein auto-sync hote hain.
🎨 2. Aapka Brand & Logo: Aapke WordCamp ka official logo, colors aur venue details automatically lag jaate hain.
📊 3. 100% Free & Open Source: WordPress community ke liye totally free, koi hidden charge nahi.
🛡️ 4. Zero Data Hassle: Zero sign-up, zero database liability—100% DPDPA 2023 compliant.`,
      cta: '👉 Apne WordCamp ke liye connect karein: hello@wpsimplified.in ya https://campbuddy.club',
      hashtags: '#WordCampOrganizers #WordPress #EventApp #CampBuddy #OpenSource',
    },
  },
  r09: {
    en: {
      headline: 'Sessions running 15 minutes late? Update every attendee’s phone with one toggle.',
      hook: 'A speaker arrived late or Q&A went over time? How do you inform 500 attendees across multiple halls?',
      body: `CampBuddy gives WordCamp organizers real-time schedule control:

⏱️ 1. Instant Time-Shift: Dial in a 10, 15, or 20-minute delay from the admin controls.
🔔 2. Auto-Adjusted Reminders: Every attendee’s starred sessions immediately recalculate and push updated alerts.
📢 3. Zero Microphone Shouting: No need for frantic stage announcements—attendees look at their phone and stay on track.
🛠️ 4. Works Seamlessly with Scraped Schedules: Doesn’t touch WordCamp.org, updates locally and reliably.`,
      cta: '👉 Experience organizer live controls: https://campbuddy.club',
      hashtags: '#EventManagement #WordCamp #WordPress #Organizers #ScheduleControl #CampBuddy',
    },
    hi: {
      headline: 'Session 15 minute late ho gaya? Ek click mein sabka schedule update!',
      hook: 'Speaker traffic mein phans gaya ya Q&A lamba khinch gaya? 500 logon ko hall-hall jaake kaise bataoge?',
      body: `CampBuddy se schedule delay manage karna hai chutzpah-free:

⏱️ 1. Live Time-Shift: Organizer panel se bas 10 ya 15 minute ka delay set karo.
🔔 2. Auto Alerts: Har attendee ke phone par schedule naye time ke hisaab se update ho jata hai.
📢 3. No Chaos: Mic par chillane ki zaroorat nahi, har attendee ko phone par exact updated time dikhta hai.
🛠️ 4. Reliable & Instant: Seconds mein sync hota hai bina kisi manual re-entry ke.`,
      cta: '👉 Organizers ke liye smart controls dekhein: https://campbuddy.club',
      hashtags: '#EventTech #WordCamp #WordPress #Organizers #CampBuddy',
    },
  },
  r10: {
    en: {
      headline: 'Unlock curated WordPress sponsor deals and free dev tools in one hub.',
      hook: 'Tired of hunting through flyer bags for coupon codes that expire before you even get home?',
      body: `CampBuddy organizes exclusive event sponsor perks in one streamlined drawer:

🎁 1. Curated Sponsor Deals: Exclusive discounts on WordPress managed hosting, premium plugins, and themes.
🛠️ 2. Free Steals for Creators: Hand-picked open-source tools, icon libraries, and productivity utilities.
🔖 3. One-Tap Save: Bookmark offers to your phone so you can claim them easily after the event.
🤝 4. Supporting the Ecosystem: Connecting generous WordPress sponsors directly with active community builders.`,
      cta: '👉 Browse active community deals: https://campbuddy.club',
      hashtags: '#WordPressDeals #Sponsors #WebDev #Hosting #Plugins #WordCamp #CampBuddy',
    },
    hi: {
      headline: 'Sponsor deals aur free tools—sab kuch ek jagah!',
      hook: 'Paper pamphlets aur promo coupons kho gaye? Saari offers ab aapke phone ke andar hain!',
      body: `CampBuddy ka Deals & Free Steals hub aapke liye lata hai exclusive faayde:

🎁 1. Curated Sponsor Deals: Best WordPress hosting, premium plugins aur themes par exclusive event discounts.
🛠️ 2. Free Steals: Developers aur creators ke liye chune hue free tools aur libraries.
🔖 3. Save for Later: Jo offer pasand aaye use save kar lo, event ke baad aaraam se redeem karo.
🤝 4. Sponsor Support: WordPress ecosystem ko aage badhane wale sponsors ko support karo.`,
      cta: '👉 Community deals abhi explore karo: https://campbuddy.club',
      hashtags: '#WordPressDeals #SponsorPerks #WordCamp #WordPress #CampBuddy',
    },
  },
};

let totalWritten = 0;
for (const r of data.reels) {
  const dir = path.join(POSTS_DIR, r.file);
  fs.mkdirSync(dir, { recursive: true });

  const postCopy = copyDeck[r.id];
  if (!postCopy) continue;

  // 1. Write English copy
  const enContent = `================================================================================
CAMPBUDDY SOCIAL MEDIA POST COPY · ENGLISH
Asset ID: ${r.id} | Slug: ${r.file}
================================================================================

[HEADLINE / TITLE]
${postCopy.en.headline}

[HOOK LINE]
${postCopy.en.hook}

[POST BODY / CAPTION]
${postCopy.en.body}

[CALL TO ACTION]
${postCopy.en.cta}

[RECOMMENDED HASHTAGS]
${postCopy.en.hashtags}

================================================================================
RECOMMENDED PUBLISHING CHANNELS:
- LinkedIn: Use Headline + Hook + Full Body + CTA + Hashtags
- Twitter / X: Use Headline + Hook + CTA + Hashtags (Thread full body if needed)
- Instagram: Use Feed Post Graphic + Full Caption in description
- Facebook: Use Link Card Graphic + Full Body + Link
================================================================================
`;

  // 2. Write Hinglish copy
  const hiContent = `================================================================================
CAMPBUDDY SOCIAL MEDIA POST COPY · HINGLISH
Asset ID: ${r.id} | Slug: ${r.file}
================================================================================

[HEADLINE / TITLE]
${postCopy.hi.headline}

[HOOK LINE]
${postCopy.hi.hook}

[POST BODY / CAPTION]
${postCopy.hi.body}

[CALL TO ACTION]
${postCopy.hi.cta}

[RECOMMENDED HASHTAGS]
${postCopy.hi.hashtags}

================================================================================
RECOMMENDED PUBLISHING CHANNELS:
- LinkedIn / Facebook / Instagram / Twitter
================================================================================
`;

  fs.writeFileSync(path.join(dir, 'post-copy-en.txt'), enContent);
  fs.writeFileSync(path.join(dir, 'post-copy-hi.txt'), hiContent);
  totalWritten += 2;
}

// 3. Write Master Playbook
let playbook = `# CampBuddy Social Media Campaign Playbook
**Dual-Language Post Deck (English & Hinglish) with Captions, Headlines, Descriptions & Hashtags**

All 10 social campaigns are tailored for maximum engagement on LinkedIn, X/Twitter, Instagram, and Facebook. Each campaign includes high-resolution graphics stamped at **900 DPI** in two formats:
- **Portrait Feed Post (1080×1350)**: Perfect for Instagram, Facebook, and LinkedIn single-image posts.
- **Landscape Link Card (1200×627)**: Optimized for X (Twitter) cards, LinkedIn link previews, and web shares.

---

`;

for (const r of data.reels) {
  const c = copyDeck[r.id];
  if (!c) continue;
  playbook += `## Post ${r.id.toUpperCase()}: ${c.en.headline}\n\n`;
  playbook += `**Event**: ${r.event.toUpperCase()} | **Audience**: ${r.audience}\n\n`;
  playbook += `### English Version\n\`\`\`text\n${c.en.headline}\n\n${c.en.hook}\n\n${c.en.body}\n\n${c.en.cta}\n\n${c.en.hashtags}\n\`\`\`\n\n`;
  playbook += `### Hinglish Version\n\`\`\`text\n${c.hi.headline}\n\n${c.hi.hook}\n\n${c.hi.body}\n\n${c.hi.cta}\n\n${c.hi.hashtags}\n\`\`\`\n\n---\n\n`;
}

fs.writeFileSync(path.join(POSTS_DIR, 'SOCIAL-MEDIA-PLAYBOOK.md'), playbook);
console.log(`Generated ${totalWritten} caption files and master SOCIAL-MEDIA-PLAYBOOK.md successfully!`);
