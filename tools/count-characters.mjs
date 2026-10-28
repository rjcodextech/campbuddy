// Instagram strictly <= 150 characters
// Note: In Instagram, the link is usually put in the dedicated "Link" field, so we can save space or include a clean short CTA.

const igEn1 = `Your WordCamp companion 🏕️
⚡ Live schedule & offline sync
🤝 Digital Camp Card & QR
🎯 Quests, deals & badges
👇 Explore WordCamp with us:
campbuddy.club`;

const igEn2 = `Your WordCamp companion 🏕️
⚡ Live schedule & offline mode
🤝 Digital Camp Card & 1-tap QR
🎯 Fun quests & attendee discovery
🔗 campbuddy.club`;

const igHi1 = `WordCamp companion 🏕️
📅 Live schedule & offline mode
🪪 Visiting card ki jagah 1 QR
🤝 Meet attendees & win badges
👇 Abhi explore karo:
campbuddy.club`;

const igHi2 = `Aapka WordCamp companion 🏕️
⚡ Offline schedule & reminders
🪪 1-tap QR Camp Card networking
🎯 Attendee discovery & quests
🔗 campbuddy.club`;

// YouTube strictly <= 1000 characters
const ytDesc = `Welcome to CampBuddy — Your WordCamp Companion 🏕️

Whether it's your first WordCamp or your tenth, CampBuddy ensures you never feel lost. From offline schedule tracking to effortless networking, keep the entire event in your pocket.

🌟 Key Features:
📅 My Day: Star sessions & get live reminders even with zero Wi-Fi.
🪪 Camp Card: Share your QR badge with your bio & LinkedIn in 1 tap.
👥 People Discovery: Find attendees with shared interests & break the ice.
🧭 Quests: Complete fun missions, collect badges & make memories.
🤝 Contributor Day: Find the right table and mentor for your skills.
🎁 Deals & Steals: WordCamp perks, coupons & community swags.
⚡ 100% Offline Ready: Fast, private, no app store install needed.

Crafted with care by WPSimplified.

🔗 Links:
🌐 Website: https://campbuddy.club
🛠️ WPSimplified: https://wpsimplified.in
🐦 X: @CampBuddyClub
📸 Instagram: @CampBuddyClub

#WordCamp #WordPress #CampBuddy #WPSimplified`;

console.log('IG EN 1:', igEn1.length, 'chars -> Valid:', igEn1.length <= 150);
console.log('IG EN 2:', igEn2.length, 'chars -> Valid:', igEn2.length <= 150);
console.log('IG HI 1:', igHi1.length, 'chars -> Valid:', igHi1.length <= 150);
console.log('IG HI 2:', igHi2.length, 'chars -> Valid:', igHi2.length <= 150);
console.log('YouTube:', ytDesc.length, 'chars -> Valid:', ytDesc.length <= 1000);
