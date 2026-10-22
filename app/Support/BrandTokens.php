<?php

namespace App\Support;

/**
 * CampBuddy's brand tokens as data, for the Brand guidelines page
 * (/brand-guidelines, resources/views/brand/guidelines.blade.php). The source
 * of truth stays resources/scss/abstracts/_variables.scss
 * (CAMPBUDDY-BRAND-GUIDELINES.md §0); `npm run brand` fails when a hex here
 * drifts from it, so the page always shows the colours the app really uses.
 */
class BrandTokens
{
    /** Approximate share of a screen (§3). */
    public const SHARE = [
        ['Paper', 56, '#FFFAF4', '#231F20'],
        ['Ink', 20, '#231F20', '#FFFFFF'],
        ['Maroon', 12, '#C33A19', '#FFFFFF'],
        ['Wine', 5, '#721313', '#FFFFFF'],
        ['Ochre', 4, '#E39D1C', '#231F20'],
        ['Roles', 3, '#047A7C', '#FFFFFF'],
    ];

    /** group => [token, hex, use] */
    public const COLOURS = [
        'Grounds and text' => [
            ['--paper', '#FFFAF4', 'Page ground, cards in the app'],
            ['--paper-soft', '#FFF0DF', 'App background, soft bands, selected rows'],
            ['--line', '#EADFD6', 'Borders and dividers'],
            ['--ink', '#231F20', 'Text, deep bands'],
            ['--muted', '#6B625E', 'Secondary copy'],
            ['--subtle', '#7A706B', 'Meta and captions'],
        ],
        'Brand' => [
            ['--brand-primary', '#C33A19', 'Maroon — the one brand voice: links, primary buttons'],
            ['--brand-accent', '#A12F14', 'Hover and pressed maroon'],
            ['--wine', '#721313', 'The logo’s wine — gradient partner'],
            ['--gold', '#E39D1C', 'Ochre — light only: glows, waves, highlights on ink. Never text on paper.'],
            ['--peach', '#FFE4C4', 'Secondary buttons, soft chips'],
        ],
        'Roles and status' => [
            ['--teal', '#047A7C', 'Sessions, “now”, together'],
            ['--navy', '#0D2343', 'Events, travel; the organizer console band'],
            ['--success', '#1F7A4D', 'Done, saved, checked in'],
            ['--danger', '#B3261E', 'Errors, deletes, LIVE'],
        ],
    ];

    /** [label, foreground, background] pairs every screen relies on (§3). */
    public const CONTRAST = [
        ['Ink on paper', '#231F20', '#FFFAF4'],
        ['Maroon on paper', '#C33A19', '#FFFAF4'],
        ['White on maroon', '#FFFFFF', '#C33A19'],
        ['Muted on paper', '#6B625E', '#FFFAF4'],
        ['Ochre on ink', '#E39D1C', '#231F20'],
        ['Ochre text on paper', '#E39D1C', '#FFFAF4'],
    ];

    /** [role, family, weight, size px, sample] (§4). */
    public const TYPE_SCALE = [
        ['Display', 'display', 800, 56, '12–13 Dec'],
        ['H1', 'display', 800, 32, 'Your WordCamp companion'],
        ['H2', 'display', 800, 24, 'Happening now'],
        ['H3', 'display', 700, 20, 'Registration + Breakfast'],
        ['H4', 'display', 700, 18, 'Find your people'],
        ['Lead', 'sans', 400, 18, 'Plan your day, meet people who share your interests.'],
        ['Body', 'sans', 400, 16, 'Every text colour reaches 4.5:1 on paper and on cards.'],
        ['Small', 'sans', 400, 14, 'Next on the schedule, starts Sat 8:00 AM.'],
        ['Kicker', 'sans', 700, 12, 'NEW UPDATE'],
        ['Badge', 'sans', 700, 12, 'SPEAKER'],
    ];

    /** [name, px] (§5). */
    public const SPACE = [['1', 4], ['2', 8], ['3', 12], ['4', 16], ['6', 24], ['8', 32], ['10', 40], ['12', 48], ['16', 64], ['20', 80], ['24', 96]];

    /** [surface, px] (§5). */
    public const RADII = [['Chip', 6], ['Input', 10], ['Row', 14], ['Card', 20], ['Sheet', 28], ['Stage', 40], ['Pill', 999]];

    /** [name, CSS box-shadow] (§5). */
    public const SHADOWS = [
        ['sm', '0 1px 2px rgba(35, 31, 32, 0.05), 0 1px 4px rgba(35, 31, 32, 0.06)'],
        ['md', '0 2px 6px -2px rgba(35, 31, 32, 0.06), 0 12px 28px -12px rgba(35, 31, 32, 0.14)'],
        ['lg', '0 4px 12px -4px rgba(35, 31, 32, 0.07), 0 24px 48px -20px rgba(35, 31, 32, 0.2)'],
        ['xl', '0 8px 16px -8px rgba(35, 31, 32, 0.08), 0 36px 72px -32px rgba(35, 31, 32, 0.26)'],
        ['glow-lift', '0 12px 28px -12px rgba(161, 47, 20, 0.45)'],
    ];

    /** [say, avoid] (§6). */
    public const VOICE = [
        ['Plain', 'Tap a session to add it to My Day.', 'Leverage our scheduling capabilities.'],
        ['Friendly — one person, “you”', 'Your first WordCamp? Start here.', 'Users can access onboarding.'],
        ['Calm', 'A free app for WordCamp attendees.', 'A revolutionary, game-changing experience!!!'],
        ['Correct', 'WordPress · WordCamp · CampBuddy', 'Wordpress · Wordcamp · Campbuddy'],
    ];

    public const BANNED = ['revolutionary', 'game-changer', 'seamless', 'unlock', 'elevate', 'supercharge'];

    /** [size, use] (§8). */
    public const SOCIAL_SIZES = [
        ['1080 × 1080', 'Square post'],
        ['1080 × 1350', 'Portrait post (default)'],
        ['1080 × 1920', 'Story / reel cover'],
        ['1200 × 630', 'Open Graph (link previews)'],
    ];

    /** [part, seconds, what, colour] (§9). */
    public const REEL_BEATS = [
        ['Hook', '0–2 s', 'The event or the result, big. No logo.', '#E39D1C'],
        ['Context', '2–6 s', 'Why it matters to this person.', '#047A7C'],
        ['Value', '6–25 s', 'One idea per cut, a cut every 2–3 s; the progress bar runs under the header.', '#C33A19'],
        ['Payoff + CTA', '25–30 s', 'One call: scan, or open campbuddy.club. Brand bar + WPSimplified credit.', '#721313'],
    ];

    /** WCAG contrast ratio of two #RRGGBB colours. */
    public static function contrast(string $a, string $b): float
    {
        return MobileAppConfig::contrast($a, $b);
    }
}
