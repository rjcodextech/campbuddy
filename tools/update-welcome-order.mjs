import fs from 'node:fs';

// 1. Update about-campbuddy.blade.php to render the 4 pillars infographic for $part === 'steps'
const aboutPartialsPath = 'E:/Sites/InHouse/campbuddy/V2/resources/views/attendee/partials/about-campbuddy.blade.php';
let aboutContent = fs.readFileSync(aboutPartialsPath, 'utf8').replace(/\r\n/g, '\n');

const newStepsSection = `@if ($part === 'steps')
    <section class="landing-bento hide-in-pwa" aria-labelledby="bento-heading">
        <div class="section-head section-head--center">
            <span class="u-eyebrow"><x-attendee.line-icon name="sparkles" /> Why Use CampBuddy?</span>
            <h2 id="bento-heading" class="section-head__title">Four Superpowers For Your WordCamp</h2>
            <span class="section-head__desc">Zero sign-up, zero paper cards, and 100% offline reliability</span>
        </div>

        <div class="bento-grid">
            <div class="bento-card bento-card--schedule">
                <div class="bento-card__icon"><x-attendee.line-icon name="calendar" /></div>
                <span class="bento-card__badge">Works 100% Offline</span>
                <h3 class="bento-card__title">1. Multi-Track Schedule &amp; "My Day"</h3>
                <p class="bento-card__desc">Know what's happening now across multiple halls. Star talks into your "My Day" agenda with instant conflict alerts &mdash; cached offline.</p>
                <div class="bento-card__footer">
                    <span class="bento-pill">Conflict Warnings</span>
                    <span class="bento-pill">Talk Bookmarks</span>
                </div>
            </div>

            <div class="bento-card bento-card--networking">
                <div class="bento-card__icon"><x-attendee.line-icon name="qr-code" /></div>
                <span class="bento-card__badge">Zero Paper Cards</span>
                <h3 class="bento-card__title">2. Digital Camp Card &amp; QR Swap</h3>
                <p class="bento-card__desc">Exchange social profiles and contact info in 2 seconds via phone camera. Zero apps or sign-up needed by the scanner.</p>
                <div class="bento-card__footer">
                    <span class="bento-pill">Camera QR Swap</span>
                    <span class="bento-pill">Save to Phone</span>
                </div>
            </div>

            <div class="bento-card bento-card--contribute">
                <div class="bento-card__icon"><x-attendee.line-icon name="wrench" /></div>
                <span class="bento-card__badge">30-Second Quiz</span>
                <h3 class="bento-card__title">3. Contributor Day Matchmaker</h3>
                <p class="bento-card__desc">Not sure which table to join? A friendly 30-second skill quiz matches you to Core, Polyglots, Design, Docs, or Community tables.</p>
                <div class="bento-card__footer">
                    <span class="bento-pill">Skill Quiz</span>
                    <span class="bento-pill">Table Routing</span>
                </div>
            </div>

            <div class="bento-card bento-card--privacy">
                <div class="bento-card__icon"><x-attendee.line-icon name="lock" /></div>
                <span class="bento-card__badge">DPDPA 2023 Compliant</span>
                <h3 class="bento-card__title">4. Zero Sign-Up &amp; Absolute Privacy</h3>
                <p class="bento-card__desc">No password, no email harvesting, no tracking cookies. All data lives strictly in your phone's browser (IndexedDB).</p>
                <div class="bento-card__footer">
                    <span class="bento-pill">Client-Side Only</span>
                    <span class="bento-pill">Instant Erasure</span>
                </div>
            </div>
        </div>
    </section>`;

// Replace lines 8-28 in about-campbuddy.blade.php
const stepsIdx = aboutContent.indexOf("@if ($part === 'steps')");
const elseIdx = aboutContent.indexOf('@else');
aboutContent = aboutContent.substring(0, stepsIdx) + newStepsSection + '\n' + aboutContent.substring(elseIdx);
fs.writeFileSync(aboutPartialsPath, aboutContent, 'utf8');
console.log('about-campbuddy.blade.php updated with bento infographic!');

// 2. Update welcome.blade.php:
// Remove bento from between hero and find-your-camp, and keep steps & more after onboarding!
const welcomePath = 'E:/Sites/InHouse/campbuddy/V2/resources/views/welcome.blade.php';
let welcome = fs.readFileSync(welcomePath, 'utf8').replace(/\r\n/g, '\n');

// Find landing-bento block before find-your-camp
const bentoStart = welcome.indexOf('{{-- 4 Pillars Bento Grid');
const findCamp = welcome.indexOf('{{-- Choosing the event is the one job');

if (bentoStart !== -1 && findCamp !== -1 && bentoStart < findCamp) {
  welcome = welcome.substring(0, bentoStart) + welcome.substring(findCamp);
}

// Ensure steps and more are present after onboarding
const stepsCall = `@include('attendee.partials.about-campbuddy', ['part' => 'steps'])`;
const moreCall = `@include('attendee.partials.about-campbuddy', ['part' => 'more'])`;

if (!welcome.includes(stepsCall)) {
  const moreIdx = welcome.indexOf(moreCall);
  if (moreIdx !== -1) {
    welcome = welcome.substring(0, moreIdx) + stepsCall + '\n\n            ' + welcome.substring(moreIdx);
  }
}

fs.writeFileSync(welcomePath, welcome, 'utf8');
console.log('welcome.blade.php updated with clean ordering!');
