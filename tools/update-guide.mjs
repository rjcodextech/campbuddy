import fs from 'node:fs';

// 1. Update guide-body.blade.php
const guidePath = 'E:/Sites/InHouse/campbuddy/V2/resources/views/attendee/partials/guide-body.blade.php';
let guideContent = fs.readFileSync(guidePath, 'utf8').replace(/\r\n/g, '\n');

const superpowersBlock = `<section class="guide-section" aria-labelledby="guide-why-heading">
    <div class="card guide-why-card">
        <div class="section-head" style="margin-bottom:12px">
            <span class="u-eyebrow"><x-attendee.line-icon name="sparkles" /> Why Use CampBuddy?</span>
            <h2 id="guide-why-heading" class="guide-section__title" style="margin:4px 0 6px">Your 4 WordCamp Superpowers</h2>
            <p class="guide-section__desc" style="margin:0">Open CampBuddy on your phone once, and everything stays ready &mdash; even without internet.</p>
        </div>
        <div class="guide-superpowers-grid">
            <div class="superpower-col">
                <div class="superpower-icon"><x-attendee.line-icon name="calendar" /></div>
                <strong>1. Never Miss a Talk</strong>
                <span>Track multiple halls live with real-time "Now &amp; Next" and 1-tap bookmarks in "My Day".</span>
            </div>
            <div class="superpower-col">
                <div class="superpower-icon"><x-attendee.line-icon name="qr-code" /></div>
                <strong>2. Swap Camp Cards in 2s</strong>
                <span>Exchange LinkedIn and website via your phone camera. No paper cards, zero sign-up required.</span>
            </div>
            <div class="superpower-col">
                <div class="superpower-icon"><x-attendee.line-icon name="wrench" /></div>
                <strong>3. Find Contributor Tables</strong>
                <span>A 30-second skill quiz points you to the exact table (Core, Docs, Design, Polyglots) for your skills.</span>
            </div>
            <div class="superpower-col">
                <div class="superpower-icon"><x-attendee.line-icon name="lock" /></div>
                <strong>4. 100% Offline &amp; Private</strong>
                <span>Zero login, no passwords, no trackers. All data is saved on your device (DPDPA 2023 compliant).</span>
            </div>
        </div>
    </div>
</section>

`;

const navTarget = `</nav>\n\n`;
if (guideContent.includes(navTarget) && !guideContent.includes('guide-why-card')) {
  guideContent = guideContent.replace(navTarget, navTarget + superpowersBlock);
  fs.writeFileSync(guidePath, guideContent, 'utf8');
  console.log('guide-body.blade.php updated with superpower card!');
}

// 2. Add styles to _guide.scss
const scssPath = 'E:/Sites/InHouse/campbuddy/V2/resources/scss/components/_guide.scss';
let scssContent = fs.readFileSync(scssPath, 'utf8').replace(/\r\n/g, '\n');

const scssStyles = `
.guide-why-card {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(255, 246, 237, 0.9));
    border: 1px solid rgba(255, 77, 45, 0.15);
    border-radius: 18px;
    padding: v.$space-5;
    margin-bottom: v.$space-6;
}

.guide-superpowers-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: v.$space-3;
    margin-top: v.$space-4;

    @media (min-width: v.$bp-tablet) {
        grid-template-columns: repeat(2, 1fr);
        gap: v.$space-4;
    }

    .superpower-col {
        display: flex;
        flex-direction: column;
        gap: 4px;
        padding: v.$space-3 v.$space-4;
        background: var(--paper);
        border: 1px solid var(--line);
        border-radius: 12px;

        strong {
            font-size: 0.9rem;
            color: var(--ink);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        span {
            font-size: 0.8125rem;
            color: var(--muted);
            line-height: 1.4;
        }

        .superpower-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: rgba(255, 77, 45, 0.1);
            color: var(--brand-primary);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 4px;

            svg {
                width: 18px;
                height: 18px;
            }
        }
    }
}
`;

if (!scssContent.includes('.guide-why-card')) {
  scssContent += scssStyles;
  fs.writeFileSync(scssPath, scssContent, 'utf8');
  console.log('_guide.scss updated with superpower card styles!');
}
