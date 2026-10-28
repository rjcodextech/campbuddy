// Script to generate platform-specific copy (X, Instagram, YouTube, LinkedIn from WPSimplified)
// for all 10 posts in final-assets/02-social-posts/
import fs from 'node:fs';
import path from 'node:path';

const POSTS_DIR = 'E:/Sites/InHouse/campbuddy/marketing/final-assets/02-social-posts';

const postsData = [
  {
    folder: '01-pehla-wordcamp-wordcamp-bengaluru',
    topic: 'First WordCamp Experience & Orientation',
    x: `Attending your very first WordCamp? You don't have to navigate 500+ attendees alone 🏕️✨

CampBuddy gives you:
⚡ Live "What's happening now" schedule
🗺️ 5-minute first-timer survival guide
🎯 Fun icebreaker quests to meet people
🪪 1-tap QR Camp Card networking

Open in your browser: https://campbuddy.club
Crafted by @WPSimplified 🧡 #WordCamp #WordPress #CampBuddy`,
    insta: `Attending your very first WordCamp? You don't have to navigate 500+ attendees alone 🏕️✨

Walking into a conference hall with multiple tracks, crowded hallways, and unfamiliar faces can feel overwhelming. Where do you start? What is the "hallway track"? Who do you talk to?

CampBuddy was designed specifically to make your first WordCamp unforgettable:
⚡ Live "Now" Guidance: Instant view of active talks across every room.
🗺️ 5-Minute First-Timer Guide: The unwritten rules of WordCamp simplified.
🎯 Fun Icebreaker Quests: Low-pressure micro-challenges to spark natural conversations.
🪪 Instant Digital Camp Card: Swap paper cards for a clean, professional QR code in 1 tap.

No app store download. No login required. 100% free and open.

🔗 Tap the link in bio to try it: campbuddy.club
Crafted with care by @wpsimplified 🧡

#WordCamp #WordPress #CampBuddy #WPSimplified #FirstWordCamp #TechCommunity #WordPressDeveloper #WebDevelopment`,
    yt: `Heading to your first WordCamp? Here is your ultimate survival guide 🏕️

Stepping into a WordCamp with 500+ attendees can feel overwhelming. CampBuddy is a free companion app crafted by WPSimplified to help first-timers find talks, navigate tracks, and break the ice with fellow WordPressers without feeling lost.

🔥 What's inside:
• Live "What's happening right now" timeline
• 5-minute first-timer guide
• Friendly networking quests and badges
• Works 100% offline when conference Wi-Fi is spotty

Try it free on your phone: https://campbuddy.club`,
    linkedin: `At WPSimplified, we remember the excitement—and slight overwhelm—of stepping into our very first WordCamp. Hundreds of attendees, parallel speaker tracks, and unfamiliar corridors.

That's why we built CampBuddy: an open-source, mobile-first companion app designed to welcome newcomers and make every attendee feel at home 🏕️

Key highlights for first-timers:
🔹 Live Multi-Track Clarity: Instant visibility on active sessions and upcoming talks.
🔹 The 5-Minute First-Timer Playbook: Breaking down the hallway track, contributor culture, and networking.
🔹 Micro-Icebreakers: Friendly community quests that make starting conversations natural.
🔹 Zero App Store Barriers: Runs seamlessly in any mobile browser with offline caching.

Check out CampBuddy for your next WordCamp:
👉 https://campbuddy.club

#WordPress #WordCamp #WPSimplified #CampBuddy #CommunityFirst #ProductDesign #OpenSource #TechConferences`
  },
  {
    folder: '02-talk-miss-nahi-hogi-wordcamp-delhi',
    topic: 'Starred Schedule & Talk Reminders',
    x: `Two amazing talks in the same time slot? Never miss a session at WordCamp again 🏕️⏰

With CampBuddy:
⭐ Star your must-watch talks
🔔 Smart reminders before sessions start
📍 Track, hall & speaker details in 1 tap
⚡ Works 100% offline without Wi-Fi

Build your plan: https://campbuddy.club
Crafted by @WPSimplified #WordCamp #WordPress #CampBuddy`,
    insta: `Two amazing talks in the same time slot? Never miss a session at WordCamp again 🏕️⏰

WordCamp schedules are packed with incredible sessions across multiple tracks. It's easy to lose track of time in hallway conversations and miss the one talk you travelled to see.

With CampBuddy's "My Day":
⭐ Star your favorite talks in advance.
🔔 Get clean talk reminders before sessions kick off.
📍 Pinpoint exactly which hall, room, and speaker you need to head to.
⚡ 100% offline sync: works perfectly even inside basement auditoriums with zero network.

Plan your day and get the most out of every track.

🔗 Link in bio: campbuddy.club
Crafted with care by @wpsimplified 🧡

#WordCamp #WordPress #CampBuddy #WPSimplified #ConferenceSchedule #WordPressCommunity #WebDev #TechEvents`,
    yt: `How to plan your day at WordCamp and never miss a talk 🏕️⏰

Multi-track conferences can get chaotic. CampBuddy lets you star your favorite talks, build your personalized agenda, and receive session reminders—all working 100% offline when conference Wi-Fi drops.

👉 Plan your schedule free: https://campbuddy.club
Crafted with pride by WPSimplified.`,
    linkedin: `One of the most common challenges at multi-track technical conferences is agenda management: overlapping sessions, last-minute track changes, and spotty auditorium connectivity.

At WPSimplified, we built the "My Day" engine inside CampBuddy to solve this friction 🏕️

Key capabilities:
🔹 Personalized Session Curating: Attendees can star must-see talks across parallel tracks.
🔹 Offline-First Persistence: Schedules stay synchronized and cached locally on device storage.
🔹 Venue Hall Mapping: Clear room indicators and countdown timers prevent session overlap.
🔹 Lightweight PWA: Zero installation friction, instant browser performance.

Explore CampBuddy live:
👉 https://campbuddy.club

#WordPress #WordCamp #WPSimplified #SoftwareEngineering #PWA #Productivity #TechConferences`
  },
  {
    folder: '03-find-your-people-wordcamp-asia-2027',
    topic: 'Attendee Discovery & Shared Interests',
    x: `Find your people in a crowd of 1,000+ WordCamp attendees 🏕️🤝

CampBuddy helps you:
🎯 Filter attendees by shared passions (Dev, SEO, Design, WooCommerce)
👋 Send friendly waves to break the ice
🔒 Mutual match privacy: details reveal only when both wave back

Connect now: https://campbuddy.club
Crafted by @WPSimplified #WordCamp #WordPress #CampBuddy`,
    insta: `Find your people in a crowd of 1,000+ WordCamp attendees 🏕️🤝

The true magic of WordCamp happens in the hallways—meeting fellow creators, collaborating on open-source, and finding future co-founders. But how do you find the right people in a crowd of hundreds?

CampBuddy makes attendee discovery effortless and respectful:
🎯 Pick your interests (Frontend, Gutenberg, WooCommerce, Security, Podcasting).
👋 Send a subtle "wave" to fellow attendees who match your topics.
🔒 Privacy First: Full profile details only unlock when both attendees wave back.
✨ Zero spam, zero cold pitches—just genuine community connections.

Connect with fellow WordPressers at your next event!

🔗 Link in bio: campbuddy.club
Crafted with care by @wpsimplified 🧡

#WordCamp #WordPress #CampBuddy #WPSimplified #Networking #WordPressCommunity #OpenSource #TechNetworking`,
    yt: `Networking at WordCamp without the awkwardness 🏕️🤝

The hallway track is where lifelong connections are made. CampBuddy helps WordCamp attendees connect based on shared technical interests and mutual waves—with zero spam and total privacy.

Discover attendees at your next WordCamp:
👉 https://campbuddy.club
Crafted by WPSimplified.`,
    linkedin: `Community conferences are defined by the "Hallway Track"—the serendipitous interactions that lead to mentorships, client partnerships, and open-source collaborations.

To help attendees find meaningful connections without awkward cold introductions, WPSimplified developed "People Discovery" within CampBuddy 🏕️

How it works:
🔹 Interest-Based Clustering: Attendees select topic affinities (Core, Headless WP, Agency Scaling, WooCommerce).
🔹 Double-Opt-In Waves: Names and direct contacts are only shared when both individuals express mutual interest.
🔹 Privacy-Preserving Architecture: No personal data is indexed or publicly exposed.

See how CampBuddy fosters community connection:
👉 https://campbuddy.club

#WordPress #WordCamp #Networking #CommunityBuilding #WPSimplified #CampBuddy #OpenSource`
  },
  {
    folder: '04-camp-card-wordcamp-bengaluru',
    topic: 'Digital Camp Card & QR Networking',
    x: `Still printing paper visiting cards for WordCamp? Swap them for 1 sleek QR code 🏕️🪪

CampBuddy Camp Card:
⚡ Instant scan & save to phone contacts
🔗 Links your bio, GitHub & LinkedIn in 1 tap
🎨 7 customizable brand themes
🌱 100% paperless & eco-friendly

Get your card: https://campbuddy.club
Crafted by @WPSimplified #WordCamp #WordPress`,
    insta: `Still printing paper visiting cards for WordCamp? Swap them for 1 sleek QR code 🏕️🪪

Paper business cards get lost, bent, or thrown into the conference bag never to be seen again. 

CampBuddy introduces the Digital Camp Card:
⚡ 1-Tap QR Exchange: Let anyone scan your badge directly from your phone screen.
📱 Save to Contacts: Seamlessly saves your name, phone, email, and social profiles.
🎨 Custom Designs: Choose from 7 curated design templates that represent your style.
🌱 Sustainable: 100% paper-free networking that never runs out of stock.

Create and customize your Camp Card today!

🔗 Link in bio: campbuddy.club
Crafted with care by @wpsimplified 🧡

#WordCamp #WordPress #CampBuddy #WPSimplified #DigitalBusinessCard #EcoFriendly #Networking #TechEvent`,
    yt: `The modern way to network at WordCamp: Digital Camp Card 🏕️🪪

Ditch paper business cards forever. CampBuddy lets you generate an instant QR Camp Card that links your bio, portfolio, and LinkedIn directly into phone contacts with one quick camera scan.

Create your free Camp Card:
👉 https://campbuddy.club
Crafted with pride by WPSimplified.`,
    linkedin: `In an era of digital-first workflows, handing out paper business cards at technical conferences feels increasingly outdated and wasteful.

At WPSimplified, we designed the "Digital Camp Card" inside CampBuddy to modernize conference networking 🏕️

Features:
🔹 Instant QR Contact Exchange: Compatible with native phone cameras without requiring third-party scanners.
🔹 Comprehensive Social Lockups: Aggregates LinkedIn, GitHub, portfolio URLs, and bios into a single verified card.
🔹 Offline Availability: Stored locally in mobile cache for instant access during connectivity drops.
🔹 Environmental Impact: Eliminates paper printing while ensuring contact details are saved directly to address books.

Generate your digital badge:
👉 https://campbuddy.club

#WordPress #WordCamp #DigitalIdentity #WPSimplified #CampBuddy #Paperless #Sustainability #Networking`
  },
  {
    folder: '05-contributor-day-wordcamp-delhi',
    topic: 'Contributor Day Table & Mentorship Guide',
    x: `Want to contribute to WordPress but don't write code? There's a table waiting for you 🏕️🤝

CampBuddy Contributor Guide:
🧭 2 quick questions match your skills
📝 Polyglots, Docs, Design, Marketing & Core
👋 Meet your table leads and mentors

Find your table: https://campbuddy.club
Crafted by @WPSimplified #WordCamp #WordPress #OpenSource`,
    insta: `Want to contribute to WordPress but don't write code? There's a table waiting for you 🏕️🤝

Contributor Day is the beating heart of WordPress. But if you are not a PHP or React developer, it's easy to assume you don't belong. That couldn't be further from the truth!

WordPress needs writers, translators, designers, testers, event organizers, and community cheerleaders.

CampBuddy's Contributor Day Guide:
🧭 Answer 2 quick questions about what you enjoy doing.
🎯 Get instantly matched with the exact Contributor Table that fits your skills.
👋 See table leads, mentors, and onboarding steps before you even sit down.
✨ Start contributing on day one and earn your first contributor badge!

Every contribution counts. Find your table with CampBuddy.

🔗 Link in bio: campbuddy.club
Crafted with care by @wpsimplified 🧡

#WordCamp #WordPress #ContributorDay #CampBuddy #WPSimplified #OpenSource #Community #LearnWordPress`,
    yt: `First time at WordPress Contributor Day? Here's how to find your table 🏕️🤝

You don't need to be a developer to contribute to WordPress. CampBuddy helps newcomers discover the right team—from Translation and Documentation to Community and Design—with simple 2-question matching.

Find your contributor team:
👉 https://campbuddy.club
Crafted by WPSimplified.`,
    linkedin: `Contributor Day is the engine that drives WordPress, powering 43%+ of the web through open-source collaboration. Yet for first-timers and non-engineers, selecting a team among 20+ tables can be daunting.

To support community onboarding, WPSimplified introduced the Contributor Day Table Matcher in CampBuddy 🏕️

Highlights:
🔹 Skills-Based Routing: Matches designers, copywriters, translators, and educators to respective contribution teams.
🔹 Mentor & Table Discovery: Identifies team leads and starter issues for frictionless onboarding.
🔹 Demystifying Open Source: Demonstrates that contributing to WordPress goes far beyond writing code.

Support WordPress contribution:
👉 https://campbuddy.club

#WordPress #ContributorDay #OpenSource #Community #WPSimplified #CampBuddy #Volunteering #TechEducation`
  },
  {
    folder: '06-works-offline-wordcamp-asia-2027',
    topic: 'Zero Connectivity & 100% Offline Resilience',
    x: `Conference hall Wi-Fi down? CampBuddy doesn't care 🏕️⚡

Built with an offline-first architecture:
📱 All schedules & talk locations cached
🪪 Camp Card QR works without internet
🔒 Zero battery drain, zero tracking
⚡ Opens instantly in any browser

Try it: https://campbuddy.club
Crafted by @WPSimplified #WordCamp #WordPress #CampBuddy`,
    insta: `Conference hall Wi-Fi down? CampBuddy doesn't care 🏕️⚡

We have all been there: you step into a basement auditorium with 800 other attendees, cell reception drops to zero, and the conference Wi-Fi crawls to a halt. You can't even check what talk is next.

CampBuddy is engineered offline-first:
⚡ Opens Instantly: Loads from your phone's browser cache in under 100 milliseconds.
📱 Full Schedule Cached: Room tracks, speaker bios, and starred talks remain accessible without a single bar of signal.
🪪 QR Always Active: Your digital Camp Card generates and renders 100% locally.
🔒 Privacy First: Your data stays on your device—never harvested, never sold.

Reliability when you need it most.

🔗 Link in bio: campbuddy.club
Crafted with care by @wpsimplified 🧡

#WordCamp #WordPress #CampBuddy #WPSimplified #OfflineFirst #PWA #WebPerformance #TechConferences`,
    yt: `How CampBuddy works 100% offline at crowded WordCamps 🏕️⚡

When 1,000 phones hit the same Wi-Fi router, connectivity fails. CampBuddy caches schedules, speaker tracks, and QR badges directly on your phone so you never miss a beat—even with zero signal.

Experience offline-first performance:
👉 https://campbuddy.club
Crafted by WPSimplified.`,
    linkedin: `At massive developer conferences, network degradation is inevitable when thousands of mobile devices saturate local access points. Building an app that depends on active network requests is a recipe for attendee frustration.

That's why WPSimplified built CampBuddy with an Offline-First, PWA-native architecture 🏕️

Technical highlights:
🔹 Aggressive Service Worker Caching: Critical assets, talk timelines, and speaker profiles cache locally on first visit.
🔹 Local-Only QR Generation: Digital badges render deterministically using client-side SVG algorithms without external API calls.
🔹 Resilient Data Synchronization: Background reconciliation occurs gracefully whenever intermittent connectivity restores.

Learn more about reliable web engineering:
👉 https://campbuddy.club

#WebDevelopment #PWA #OfflineFirst #Performance #WPSimplified #CampBuddy #WordPress #Architecture`
  },
  {
    folder: '07-after-the-wordcamp-wordcamp-rajasthan',
    topic: 'Post-Event Connections, PDF Export & Memories',
    x: `WordCamp is over, but your connections don't have to fade 🏕️📸

CampBuddy post-event perks:
📄 1-tap PDF summary of talks you attended
👥 Export connections & LinkedIn profiles
📸 Community photo memories in 1 place

Keep the momentum going: https://campbuddy.club
Crafted by @WPSimplified #WordCamp #WordPress`,
    insta: `WordCamp is over, but your connections don't have to fade 🏕️📸

The after-party is wrapped up, luggage is packed, and you are heading home with a head full of ideas. But what happens to all those great conversations and contacts?

CampBuddy helps you keep the conference momentum alive:
📄 1-Tap PDF Export: Download a clean summary of every session you starred and attended.
👥 Preserved Contacts: Revisit the people you waved to and connect on LinkedIn or Twitter.
📸 Memory Hub: Access official event photos and recap highlights.
🌱 Next WordCamp: Seamlessly port your Camp Card and profile to the next event.

WordCamp ends. Community stays.

🔗 Link in bio: campbuddy.club
Crafted with care by @wpsimplified 🧡

#WordCamp #WordPress #CampBuddy #WPSimplified #WordCampRecap #Community #TechNetwork #WordPressDeveloper`,
    yt: `What to do after WordCamp: Export your talks & contacts 🏕️📄

Don't let post-event momentum slip away. CampBuddy allows attendees to export an official PDF summary of their schedule and connect with fellow attendees long after the event wraps up.

Keep your WordCamp memories alive:
👉 https://campbuddy.club
Crafted by WPSimplified.`,
    linkedin: `The value of attending a WordCamp extends far beyond the conference weekend—it lies in the follow-through, post-event collaborations, and relationships built.

To ensure attendees maximize post-conference ROI, WPSimplified integrated post-event archiving tools into CampBuddy 🏕️

Key features:
🔹 Single-Click Session Export: Generates a structured PDF dossier of starred sessions and speaker resources.
🔹 Relationship Retention: Preserves mutual attendee connections for streamlined LinkedIn and GitHub follow-ups.
🔹 Cross-Event Portability: User profiles and digital badges seamlessly carry over to upcoming regional WordCamps.

Explore CampBuddy:
👉 https://campbuddy.club

#WordPress #WordCamp #CommunityROI #Networking #WPSimplified #CampBuddy #ProfessionalGrowth #TechEvents`
  },
  {
    folder: '08-for-organizers-wordcamp-rajasthan',
    topic: 'Turn-Key Companion App for WordCamp Organizers',
    x: `Organizing a WordCamp? Give your attendees a world-class companion app in zero minutes 🏕️🚀

CampBuddy for Organizers:
⚡ Syncs schedule directly from your official site
📢 Broadcast live delay & track announcements
🎨 Custom event branding & colors
🆓 100% Free & Open Source

Get it for your event: https://campbuddy.club
Crafted by @WPSimplified #WordCamp #WordPress`,
    insta: `Organizing a WordCamp? Give your attendees a world-class companion app in zero minutes 🏕️🚀

WordCamp organizing teams already have their hands full with venue logistics, speaker coordination, swag orders, and sponsorships. Building a dedicated mobile app from scratch shouldn't be another headache.

CampBuddy is a ready-to-deploy, turn-key companion app:
⚡ Auto-Sync Schedule: Pulls speakers, sessions, and sponsors straight from your official WordCamp site.
📢 Live Admin Announcements: Broadcast room changes or delay alerts instantly to every attendee's phone.
🎨 Event Customization: Features your event's official logo, brand colors, and schedule tracks.
🆓 100% Free & Open-Source: Built by the community, for the community.

Bring CampBuddy to your local WordCamp organizing team!

🔗 Link in bio: campbuddy.club
Crafted with care by @wpsimplified 🧡

#WordCamp #WordPress #CampBuddy #WPSimplified #WordCampOrganizers #EventTech #OpenSource #CommunityOrganizers`,
    yt: `Why WordCamp organizers love CampBuddy 🏕️🚀

Building and maintaining mobile apps for WordCamps takes months of dev time. CampBuddy offers a turn-key, automated companion app that syncs schedules and broadcasts live updates in real time.

Bring CampBuddy to your event:
👉 https://campbuddy.club
Crafted with pride by WPSimplified.`,
    linkedin: `Organizing a WordCamp is an immense volunteer undertaking—coordinating speaker tracks, managing sponsorships, and delivering a smooth attendee experience. Often, building a custom conference app is simply not feasible within budget and timeline constraints.

WPSimplified built CampBuddy to provide WordCamp organizing teams with a production-ready, turn-key companion platform at zero cost 🏕️

Benefits for Organizers:
🔹 Automated Feed Ingestion: Automatically pulls schedule and speaker metadata from WordCamp.org REST APIs every 15 minutes.
🔹 Real-Time Broadcast Console: Allows organizers to broadcast session delays, room shifts, or announcements directly to attendee screens.
🔹 Brand Fidelity: Respects official event marks, palette tokens, and local community identity.
🔹 Zero Infrastructure Overhead: Hosted, optimized, and maintained by WPSimplified as an open community service.

Empower your organizing team:
👉 https://campbuddy.club

#WordPress #WordCamp #EventManagement #EventTech #WPSimplified #CampBuddy #CommunityOrganizers #OpenSource`
  },
  {
    folder: '09-running-late-wordcamp-delhi',
    topic: 'Schedule Delays & Real-Time Sync',
    x: `Speaker flight delayed? Keynote running 15 minutes late? 🏕️⏰

One click from organizers updates every attendee's phone in real time:
📢 Prominent live delay banner
🔄 Schedule shifts automatically
🔔 Reminders adapt to the new time

Stay in sync: https://campbuddy.club
Crafted by @WPSimplified #WordCamp #WordPress #CampBuddy`,
    insta: `Speaker flight delayed? Keynote running 15 minutes late? 🏕️⏰

Live events rarely run 100% on schedule. A speaker gets caught in traffic, a Q&A session runs over, or a technical glitch shifts the timeline. At traditional conferences, attendees wander into halls at the wrong time.

With CampBuddy's Live Sync:
📢 Organizers set a 10 or 15-minute delay in one click from the admin console.
🔄 Every attendee's schedule instantly recalibrates with updated start times.
🔔 Push alerts and reminders automatically shift to the new timeline.
✨ Zero confusion, zero frantic hallway announcements.

Smart tech for smooth conference operations.

🔗 Link in bio: campbuddy.club
Crafted with care by @wpsimplified 🧡

#WordCamp #WordPress #CampBuddy #WPSimplified #EventTech #ConferenceManagement #SmartEvents #TechConferences`,
    yt: `How CampBuddy handles real-time conference schedule delays 🏕️⏰

When sessions run late, CampBuddy keeps attendees informed with instant schedule recalculation and real-time announcement banners.

See it in action:
👉 https://campbuddy.club
Crafted by WPSimplified.`,
    linkedin: `In live event management, schedule slippage is inevitable—keynotes run long, panel discussions spark extended Q&As, and room transitions take extra time. Managing attendee flow during delays is critical to maintaining a professional event atmosphere.

CampBuddy features a real-time Schedule Shift engine designed specifically for conference agility 🏕️

Operational benefits:
🔹 Single-Point Schedule Adjustment: Organizers can apply global or track-specific time shifts with a single setting.
🔹 Dynamic Timeline Recalculation: All attendee schedules, countdowns, and upcoming talk alerts automatically adjust in real time.
🔹 Ambient Alert Banners: Prominently informs attendees of schedule shifts without intrusive modal interruptions.

Experience intelligent event scheduling:
👉 https://campbuddy.club

#EventTech #LiveEvents #ConferenceOperations #WPSimplified #CampBuddy #WordPress #ProductDesign #UX`
  },
  {
    folder: '10-deals-free-steals-wordcamp-bengaluru',
    topic: 'Curated Sponsor Deals, Coupons & Swag',
    x: `Don't leave WordCamp without your sponsor perks & swag deals 🏕️🎁

CampBuddy Deals & Steals:
🏷️ Exclusive hosting, plugin & theme discounts
👕 Swag booth locations & free steals
⚡ Curated for developers, agencies & creators

Claim your perks: https://campbuddy.club
Crafted by @WPSimplified #WordCamp #WordPress`,
    insta: `Don't leave WordCamp without your sponsor perks & swag deals 🏕️🎁

Sponsors invest generously to support the WordPress community, offering attendee-exclusive discounts on hosting, premium plugins, themes, SaaS tools, and cool booth swag. But navigating crowded expo halls can mean missing out on great deals.

CampBuddy's Deals & Steals Hub:
🏷️ Curated Discounts: Browse exclusive coupons on top WordPress hosting, tools, and dev services.
👕 Swag Tracker: Find out which sponsor booths are giving away free t-shirts, stickers, and swag.
⚡ 1-Tap Redemption: Copy coupon codes or open claim links directly from your mobile browser.
🎁 Maximum Value: Make the most of your WordCamp ticket!

Check out active deals at your WordCamp!

🔗 Link in bio: campbuddy.club
Crafted with care by @wpsimplified 🧡

#WordCamp #WordPress #CampBuddy #WPSimplified #WordPressDeals #TechSwag #WebHosting #WordPressPlugins`,
    yt: `Best sponsor deals and free swag at WordCamp 🏕️🎁

WordCamp sponsors offer massive discounts and free perks for attendees. CampBuddy collects all coupons, hosting deals, and swag booth locations into one clean, searchable tab.

Explore deals for your next WordCamp:
👉 https://campbuddy.club
Crafted with pride by WPSimplified.`,
    linkedin: `Sponsors are foundational to the sustainability of WordCamps, underwriting event costs while offering attendees substantial commercial perks—from enterprise hosting credits to developer tooling discounts.

To maximize value exchange between sponsors and attendees, WPSimplified built the "Deals & Steals" portal within CampBuddy 🏕️

Highlights:
🔹 Curated Sponsor Showcase: Direct visibility for event sponsors with actionable coupon codes and redemption links.
🔹 Category-Specific Filtering: Easily discover deals on hosting, agency tooling, security, and plugins.
🔹 Enhanced Sponsor ROI: Drives measurable, qualified attendee engagement for event partners.

Discover CampBuddy:
👉 https://campbuddy.club

#WordPress #WordCamp #Sponsorships #ConferenceROI #WPSimplified #CampBuddy #TechTools #SaaS`
  }
];

// Write individual files for each of the 10 post folders
for (const p of postsData) {
  const dir = path.join(POSTS_DIR, p.folder);
  if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });

  const fileContent = `================================================================================
CAMPBUDDY PLATFORM-SPECIFIC SOCIAL MEDIA COPY
Asset: ${p.folder}
Topic: ${p.topic}
================================================================================

--------------------------------------------------------------------------------
1. 🆇 X (TWITTER) POST COPY
--------------------------------------------------------------------------------
${p.x}

--------------------------------------------------------------------------------
2. 📸 INSTAGRAM POST / REEL CAPTION
--------------------------------------------------------------------------------
${p.insta}

--------------------------------------------------------------------------------
3. ▶️ YOUTUBE COMMUNITY TAB / VIDEO DESCRIPTION
--------------------------------------------------------------------------------
${p.yt}

--------------------------------------------------------------------------------
4. 💼 LINKEDIN POST COPY (TO BE POSTED FROM WPSIMPLIFIED CHANNEL)
--------------------------------------------------------------------------------
${p.linkedin}

================================================================================
`;

  fs.writeFileSync(path.join(dir, 'platform-copy.txt'), fileContent);
  console.log(`Wrote platform-copy.txt in ${p.folder}`);
}

// Generate updated Master Social Media Playbook
let playbook = `# CampBuddy — Master Social Media Publishing Playbook
All 10 Campaign Posts with Tailored Copy for **X (Twitter)**, **Instagram**, **YouTube**, and **LinkedIn** (to be published via **WPSimplified**).

---
`;

for (const [idx, p] of postsData.entries()) {
  playbook += `
## Post ${idx + 1}: ${p.folder}
**Topic:** ${p.topic}

### 🆇 X (Twitter)
\`\`\`text
${p.x}
\`\`\`

### 📸 Instagram
\`\`\`text
${p.insta}
\`\`\`

### ▶️ YouTube (Community Post)
\`\`\`text
${p.yt}
\`\`\`

### 💼 LinkedIn (WPSimplified Channel)
\`\`\`text
${p.linkedin}
\`\`\`

---
`;
}

fs.writeFileSync(path.join(POSTS_DIR, 'SOCIAL-MEDIA-PLAYBOOK.md'), playbook);
console.log('Updated SOCIAL-MEDIA-PLAYBOOK.md with all 4 platforms successfully!');
