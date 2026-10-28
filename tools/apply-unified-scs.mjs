import fs from 'fs';

// =========================================================================
// 1. UPDATE _wide-screens.scss for UNIFIED CONTAINER
// =========================================================================
let wideScreens = fs.readFileSync('resources/scss/layout/_wide-screens.scss', 'utf8');

const newWideScreens = `@use '../abstracts/variables' as v;
@use '../abstracts/mixins' as m;

// ==========================================================================
// UNIFIED CONTAINER ARCHITECTURE
// Enforces that Header (.topbar__inner), Body (#main-content, .landing-main,
// .legal-main), and Footer (.site-footer__inner) share the EXACT same 1120px
// container width, margins, and padding across all viewports.
// ==========================================================================

$shell-max: 1120px;
$sidebar-width: 220px;
$content-max: 868px;
$reading-max: 760px;
$shell-gutter: max(#{v.$space-6}, calc((100vw - #{$shell-max}) / 2));

// Standalone pages (Home, About, Guide, Brand Guidelines, Legal):
// EXACT identical container on all viewports!
.app-frame:not(.app-frame--with-nav) {
    .topbar {
        padding: 0;
    }

    .topbar__inner,
    #main-content,
    .site-main,
    .landing-main,
    .legal-main,
    .legal-shell,
    .bg-page,
    .site-footer__inner {
        width: 100%;
        max-width: $shell-max;
        margin-left: auto;
        margin-right: auto;
        box-sizing: border-box;
        padding-left: 16px;
        padding-right: 16px;

        @media (min-width: v.$bp-tablet) {
            padding-left: 24px;
            padding-right: 24px;
        }
    }

    .landing-footer,
    .legal-footer {
        text-align: center;
    }
}

// In-event pages (with sidebar on desktop)
@media (min-width: v.$bp-tablet) {
    .app-frame--with-nav {
        .topbar {
            padding-right: v.$space-6;
            padding-left: v.$space-6;
        }
        #main-content {
            width: 100%;
            max-width: $reading-max;
            margin-right: auto;
            margin-left: auto;
            padding-right: v.$space-6;
            padding-left: v.$space-6;
        }
    }

    .bottom-nav {
        justify-content: center;
    }

    .bottom-nav__item {
        flex: 0 1 110px;
    }
}

@media (min-width: v.$bp-desktop) {
    .brand__logo--wordmark {
        height: 38px;
    }

    .app-frame--with-nav {
        padding-bottom: 0;

        #main-content {
            max-width: calc(#{$content-max} + #{$sidebar-width});
            margin-right: auto;
            margin-left: calc(#{$shell-gutter} + #{$sidebar-width} + #{v.$space-4});
            padding-top: v.$space-6;
            padding-left: v.$space-4;
        }

        .bottom-nav {
            position: fixed;
            top: 0;
            bottom: 0;
            left: $shell-gutter;
            z-index: 20;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            gap: v.$space-1;
            width: $sidebar-width;
            height: 100vh;
            padding: calc(#{v.$topbar-height} + #{v.$space-4}) v.$space-2 v.$space-4;
            background: var(--paper-soft);
            border-top: 0;
            border-right: 1px solid var(--line);
        }

        .bottom-nav__item {
            flex: 0 0 auto;
            flex-direction: row;
            justify-content: flex-start;
            gap: v.$space-3;
            width: 100%;
            min-height: 44px;
            padding: v.$space-2 v.$space-3;
            border-radius: 12px;
            font-size: v.$font-size-sm;

            &::before {
                top: 50%;
                bottom: auto;
                left: 0;
                width: 3px;
                height: 20px;
                transform: translateY(-50%);
                border-radius: 0 v.$radius-full v.$radius-full 0;
            }

            svg {
                width: 20px;
                height: 20px;
            }
        }
    }
}
`;

fs.writeFileSync('resources/scss/layout/_wide-screens.scss', newWideScreens, 'utf8');
console.log('Updated _wide-screens.scss');

// =========================================================================
// 2. UPDATE _topbar.scss
// =========================================================================
let topbar = fs.readFileSync('resources/scss/layout/_topbar.scss', 'utf8');

const oldTopbarRule = `.topbar {
    position: sticky;
    top: 0;
    z-index: 15;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: v.$space-2 v.$space-3;
    min-height: v.$topbar-height;
    padding: v.$space-2 v.$space-4;
    // Content stays clear of a notch/status bar when installed
    // standalone — added on top of the existing vertical padding, not
    // replacing it.
    padding-top: max(#{v.$space-2}, env(safe-area-inset-top, 0px));
    background: var(--paper-soft);
    border-bottom: 1px solid var(--line);
}`;

const newTopbarRule = `.topbar {
    position: sticky;
    top: 0;
    z-index: 25;
    width: 100%;
    min-height: v.$topbar-height;
    background: rgba(250, 250, 253, 0.94);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border-bottom: 1px solid var(--line);
    padding: 0;

    // Fallback for views without .topbar__inner
    &:not(:has(.topbar__inner)) {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: v.$space-2 v.$space-3;
        padding: v.$space-2 v.$space-4;
        padding-top: max(#{v.$space-2}, env(safe-area-inset-top, 0px));
    }
}

.topbar__inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: v.$space-2 v.$space-3;
    min-height: v.$topbar-height;
    width: 100%;
    max-width: 1120px;
    margin: 0 auto;
    padding: v.$space-2 v.$space-4;
    padding-top: max(#{v.$space-2}, env(safe-area-inset-top, 0px));
    box-sizing: border-box;

    @media (min-width: v.$bp-tablet) {
        padding-left: v.$space-6;
        padding-right: v.$space-6;
        min-height: 64px;
    }
}`;

topbar = topbar.replace(oldTopbarRule, newTopbarRule);
fs.writeFileSync('resources/scss/layout/_topbar.scss', topbar, 'utf8');
console.log('Updated _topbar.scss');

// =========================================================================
// 3. UPDATE _landing.scss (Append styles for hero-trust-bar, bento-header, site-footer)
// =========================================================================
let landing = fs.readFileSync('resources/scss/components/_landing.scss', 'utf8');

// Ensure landing-main doesn't overwrite padding
landing = landing.replace(
`.landing-main {
    flex: 1;
    padding: v.$space-5 v.$space-4 v.$space-8;
}`,
`.landing-main {
    flex: 1;
    padding-top: v.$space-5;
    padding-bottom: v.$space-8;
}`
);

const landingAdditions = `
// --- New Billboard & Bento Components --------------------------------------
.hero-trust-bar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px 12px;
    margin-top: v.$space-4;
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--muted);

    span {
        display: inline-flex;
        align-items: center;
        gap: 5px;

        svg {
            width: 14px;
            height: 14px;
            color: var(--brand-primary);
        }
    }

    &__sep {
        opacity: 0.4;
    }
}

.bento-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    margin-bottom: v.$space-1;
}

// --- Unified Site Footer Component ------------------------------------------
.site-footer {
    width: 100%;
    background: #ffffff;
    border-top: 1px solid var(--line);
    margin-top: v.$space-10;

    &__inner {
        width: 100%;
        max-width: 1120px;
        margin: 0 auto;
        padding: v.$space-8 v.$space-4 v.$space-10;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: v.$space-4;
        text-align: center;
        box-sizing: border-box;

        @media (min-width: v.$bp-tablet) {
            padding: v.$space-10 v.$space-6 v.$space-12;
            gap: v.$space-5;
        }
    }
}

.footer-nav-links {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 8px 18px;
    font-size: 0.88rem;

    a {
        color: var(--muted);
        text-decoration: none;
        font-weight: 500;
        transition: color 0.15s ease;

        &:hover {
            color: var(--brand-primary);
            text-decoration: underline;
        }
    }
}

.footer-social-wrap {
    padding: 4px 0;
}

.footer-copy {
    margin: 0;
    font-size: 0.82rem;
    color: var(--muted);

    a {
        color: inherit;
        font-weight: 600;
        text-decoration: underline;

        &:hover {
            color: var(--brand-primary);
        }
    }
}
`;

if (!landing.includes('.hero-trust-bar')) {
    landing += landingAdditions;
    fs.writeFileSync('resources/scss/components/_landing.scss', landing, 'utf8');
    console.log('Appended components to _landing.scss');
}

// =========================================================================
// 4. UPDATE _about.scss (Add .about-who__badge and .about-who__points)
// =========================================================================
let aboutScss = fs.readFileSync('resources/scss/components/_about.scss', 'utf8');

const aboutWhoAdditions = `
.about-who__badge {
    align-self: flex-start;
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 3px 10px;
    border-radius: v.$radius-full;
    background: var(--paper-soft);
    border: 1px solid var(--line);
    color: var(--muted);
    margin-bottom: 2px;
}

.about-who__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 52px;
    height: 52px;
    border-radius: 16px;
    margin-bottom: v.$space-1;

    svg {
        width: 26px;
        height: 26px;
    }

    &--first {
        background: linear-gradient(135deg, rgba(255, 77, 45, 0.15), rgba(217, 119, 6, 0.12));
        color: var(--brand-primary);
    }

    &--student {
        background: linear-gradient(135deg, rgba(217, 119, 6, 0.18), rgba(245, 158, 11, 0.12));
        color: #b45309;
    }

    &--regular {
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.15), rgba(16, 185, 129, 0.12));
        color: #2563eb;
    }
}

.about-who__points {
    list-style: none;
    padding: 0;
    margin: v.$space-2 0 v.$space-3;
    display: flex;
    flex-direction: column;
    gap: 6px;

    li {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.84rem;
        color: var(--ink);

        &::before {
            content: '✓';
            color: #10b981;
            font-weight: 900;
            font-size: 0.85rem;
        }
    }
}
`;

if (!aboutScss.includes('.about-who__badge')) {
    aboutScss += aboutWhoAdditions;
    fs.writeFileSync('resources/scss/components/_about.scss', aboutScss, 'utf8');
    console.log('Appended who badges to _about.scss');
}

// =========================================================================
// 5. UPDATE _guide.scss (Add .guide-what-card and .guide-what-highlights)
// =========================================================================
let guideScss = fs.readFileSync('resources/scss/components/_guide.scss', 'utf8');

const guideWhatAdditions = `
.guide-what-card {
    display: flex;
    flex-direction: column;
    gap: v.$space-4;
    background: #ffffff;
    border: 1.5px solid rgba(15, 23, 42, 0.08);
    border-radius: 20px;
    padding: v.$space-6;
    box-shadow: 0 4px 16px -2px rgba(15, 23, 42, 0.04);
}

.guide-what-highlights {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 12px;
    padding-top: v.$space-3;
    border-top: 1px solid var(--line);

    .highlight-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: v.$radius-full;
        background: var(--paper-soft);
        border: 1px solid var(--line);
        font-size: 0.82rem;
        font-weight: 700;
        color: var(--ink);

        svg {
            width: 14px;
            height: 14px;
            color: var(--brand-primary);
        }
    }
}
`;

if (!guideScss.includes('.guide-what-card')) {
    guideScss += guideWhatAdditions;
    fs.writeFileSync('resources/scss/components/_guide.scss', guideScss, 'utf8');
    console.log('Appended what highlights to _guide.scss');
}

// =========================================================================
// 6. UPDATE _legal.scss (Ensure .legal-shell is 100% max-width 1120px)
// =========================================================================
let legalScss = fs.readFileSync('resources/scss/components/_legal.scss', 'utf8');
legalScss = legalScss.replace('max-width: 960px;', 'max-width: 1120px;');
fs.writeFileSync('resources/scss/components/_legal.scss', legalScss, 'utf8');
console.log('Updated _legal.scss max-width to 1120px');
