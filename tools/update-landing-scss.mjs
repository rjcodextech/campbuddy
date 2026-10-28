import fs from 'node:fs';

const p = 'E:/Sites/InHouse/campbuddy/V2/resources/scss/components/_landing.scss';
let content = fs.readFileSync(p, 'utf8');

// Replace desktop media query with tablet + desktop
const oldMq = `@media (min-width: v.$bp-desktop) {
    .landing-hero {
        display: grid;
        grid-template-columns: 1.15fr 0.85fr;
        gap: v.$space-8;
        align-items: center;
        padding: v.$space-8;
        border-radius: 24px;
        background:
            radial-gradient(circle at 100% 0%, rgba(244, 166, 41, 0.12) 0%, transparent 50%),
            radial-gradient(circle at 0% 100%, rgba(195, 58, 25, 0.08) 0%, transparent 40%),
            linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(255, 246, 237, 0.85));
        border: 1px solid rgba(195, 58, 25, 0.12);
        box-shadow: v.$shadow-card;
    }

    .hero-orbit {
        display: none;
    }

    .hero-stage {
        display: flex;
        flex-direction: column;
        gap: v.$space-3;
        background: var(--paper);
        border: 1px solid var(--line);
        border-radius: 20px;
        padding: v.$space-5;
        box-shadow: 0 16px 36px -12px rgba(35, 31, 32, 0.12);
        position: relative;
        overflow: hidden;

        &::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 120px;
            height: 120px;
            background: radial-gradient(circle, rgba(4, 120, 87, 0.1) 0%, transparent 70%);
            pointer-events: none;
        }
    }`;

const newMq = `@media (min-width: v.$bp-tablet) {
    .landing-hero {
        display: grid;
        grid-template-columns: 1.12fr 0.88fr;
        gap: v.$space-6;
        align-items: center;
        padding: v.$space-6;
        border-radius: 24px;
        background:
            radial-gradient(circle at 100% 0%, rgba(244, 166, 41, 0.12) 0%, transparent 50%),
            radial-gradient(circle at 0% 100%, rgba(255, 77, 45, 0.08) 0%, transparent 40%),
            linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(255, 246, 237, 0.85));
        border: 1px solid rgba(255, 77, 45, 0.12);
        box-shadow: v.$shadow-card;

        @media (min-width: v.$bp-desktop) {
            gap: v.$space-8;
            padding: v.$space-8;
        }
    }

    .hero-orbit {
        display: none;
    }

    .hero-stage {
        display: flex;
        flex-direction: column;
        gap: v.$space-3;
        background: var(--paper);
        border: 1px solid var(--line);
        border-radius: 20px;
        padding: v.$space-4;
        box-shadow: 0 16px 36px -12px rgba(35, 31, 32, 0.12);
        position: relative;
        overflow: hidden;

        @media (min-width: v.$bp-desktop) {
            padding: v.$space-5;
        }

        &::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 120px;
            height: 120px;
            background: radial-gradient(circle, rgba(4, 120, 87, 0.1) 0%, transparent 70%);
            pointer-events: none;
        }
    }`;

// Normalize line endings
content = content.replace(/\r\n/g, '\n');
const normOld = oldMq.replace(/\r\n/g, '\n');
const normNew = newMq.replace(/\r\n/g, '\n');

if (content.includes(normOld)) {
  content = content.replace(normOld, normNew);
} else {
  console.log('oldMq pattern did not match directly, checking lines...');
}

// Update star badge
content = content.replace(
  `    .star-badge {\n        margin-left: auto;\n        color: #d98a1c;\n        font-size: 1.1rem;\n    }`,
  `    .star-badge {\n        margin-left: auto;\n        color: #d97706;\n        font-size: 0.75rem;\n        font-weight: 600;\n        background: rgba(245, 158, 11, 0.12);\n        padding: 2px 8px;\n        border-radius: v.$radius-full;\n    }`
);

fs.writeFileSync(p, content, 'utf8');
console.log('_landing.scss updated!');
