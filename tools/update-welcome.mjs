import fs from 'node:fs';

const p = 'E:/Sites/InHouse/campbuddy/V2/resources/views/welcome.blade.php';
let content = fs.readFileSync(p, 'utf8');

// Normalize line endings
const normContent = content.replace(/\r\n/g, '\n');

const searchFragment = `                <div class="landing-hero__visual" aria-hidden="true">
                    {{-- Mobile line icon orbit --}}
                    <div class="hero-orbit">
                        <span class="hero-orbit__ring"></span>
                        <span class="hero-orbit__ring hero-orbit__ring--inner"></span>
                        <span class="hero-orbit__core"><img src="/media/icons/icon-192.png" alt="" width="72" height="72"></span>
                        <span class="hero-orbit__item hero-orbit__item--1"><x-attendee.line-icon name="calendar" /><b>Talks</b></span>
                        <span class="hero-orbit__item hero-orbit__item--2"><x-attendee.line-icon name="users" /><b>People</b></span>
                        <span class="hero-orbit__item hero-orbit__item--3"><x-attendee.line-icon name="sparkles" /><b>My Day</b></span>
                        <span class="hero-orbit__item hero-orbit__item--4"><x-attendee.line-icon name="qr-code" /><b>Camp Card</b></span>
                    </div>

                    </div>`;

const newVisual = `                <div class="landing-hero__visual" aria-hidden="true">
                    {{-- Mobile line icon orbit --}}
                    <div class="hero-orbit">
                        <span class="hero-orbit__ring"></span>
                        <span class="hero-orbit__ring hero-orbit__ring--inner"></span>
                        <span class="hero-orbit__core"><img src="/media/icons/icon-192.png" alt="" width="72" height="72"></span>
                        <span class="hero-orbit__item hero-orbit__item--1"><x-attendee.line-icon name="calendar" /><b>Talks</b></span>
                        <span class="hero-orbit__item hero-orbit__item--2"><x-attendee.line-icon name="users" /><b>People</b></span>
                        <span class="hero-orbit__item hero-orbit__item--3"><x-attendee.line-icon name="sparkles" /><b>My Day</b></span>
                        <span class="hero-orbit__item hero-orbit__item--4"><x-attendee.line-icon name="qr-code" /><b>Camp Card</b></span>
                    </div>

                    {{-- Live WordCamp Companion Stage on Desktop/Tablet --}}
                    <div class="hero-stage">
                        <div class="hero-stage__header">
                            <div class="hero-stage__brand">
                                <img src="/media/icons/icon-192.png" alt="" width="26" height="26">
                                <span>WordCamp Companion</span>
                            </div>
                            <span class="hero-stage__live-badge">
                                <span class="live-dot live-dot--green"></span>
                                <span>100% Offline</span>
                            </span>
                        </div>

                        {{-- Card 1: Live Now Session --}}
                        <div class="hero-stage__card">
                            <div class="hero-stage__meta">
                                <span class="track-pill">Track 1 &middot; Hall A</span>
                                <span class="time-pill">11:30 AM &middot; Happening Now</span>
                            </div>
                            <div class="hero-stage__talk-title">Building Modern WordPress with Headless &amp; Blocks</div>
                            <div class="hero-stage__speaker">
                                <span class="speaker-avatar">AS</span>
                                <span>Aarav Sharma &middot; Core Contributor</span>
                                <span class="star-badge">&#9733; Saved in My Day</span>
                            </div>
                        </div>

                        {{-- Card 2: Digital Camp Card Swap --}}
                        <div class="hero-stage__card hero-stage__card--card-swap">
                            <div class="swap-row">
                                <div class="qr-mini">
                                    <x-attendee.line-icon name="qr-code" />
                                </div>
                                <div class="swap-info">
                                    <strong>Instant Digital Camp Card (P2P Swap)</strong>
                                    <span>Swap LinkedIn, website &amp; contacts via camera &mdash; zero paper cards</span>
                                </div>
                                <span class="offline-tag">Zero Sign-Up</span>
                            </div>
                        </div>

                        {{-- Quick Feature Chips --}}
                        <div class="hero-stage__chips">
                            <span class="stage-chip"><x-attendee.line-icon name="calendar" /> Multi-Track</span>
                            <span class="stage-chip"><x-attendee.line-icon name="qr-code" /> Camp Card</span>
                            <span class="stage-chip"><x-attendee.line-icon name="wrench" /> Contributor Day</span>
                            <span class="stage-chip"><x-attendee.line-icon name="lock" /> DPDPA Privacy</span>
                        </div>
                    </div>
                </div>`;

if (!normContent.includes(searchFragment)) {
  console.error('searchFragment not found in welcome.blade.php');
  process.exit(1);
}

let updated = normContent.replace(searchFragment, newVisual);

// Also clean up promises:
const oldPromises = `                    <ul class="landing-hero__promises" aria-label="Key promises">
                        <li><span>&#10003;</span> 100% Free</li>
                        <li><span>&#10003;</span> Zero Sign-Up (DPDPA 2023)</li>
                        <li><span>&#10003;</span> Works Offline</li>
                        <li><span>&#10003;</span> Instant PWA (No App Store)</li>
                    </ul>`;

const newPromises = `                    <ul class="landing-hero__promises" aria-label="Key promises">
                        <li>100% Free &amp; Open</li>
                        <li>Zero Sign-Up (DPDPA 2023)</li>
                        <li>100% Offline Ready</li>
                        <li>Instant Browser PWA</li>
                    </ul>`;

if (updated.includes(oldPromises)) {
  updated = updated.replace(oldPromises, newPromises);
}

// Also refine Bento Grid title to clearly answer "Why CampBuddy?":
updated = updated.replace(
  `<h2 id="bento-heading" class="section-head__title">How CampBuddy Powers Your WordCamp</h2>\n                    <span class="section-head__desc">Four zero-friction features built directly into your browser</span>`,
  `<h2 id="bento-heading" class="section-head__title">Why Use CampBuddy?</h2>\n                    <span class="section-head__desc">Four zero-friction pillars designed specifically for WordCamp attendees</span>`
);

// Remove redundant steps section after onboarding so content is not repeated:
updated = updated.replace(`            @include('attendee.partials.about-campbuddy', ['part' => 'steps'])\n\n\n`, '');

fs.writeFileSync(p, updated, 'utf8');
console.log('welcome.blade.php successfully updated!');
