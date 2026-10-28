// CampBuddy's brand tokens for canvas work — social posts (social-kit.js),
// reels (social-reels.js) and the usage post (usage-post.js) draw on a canvas,
// where CSS custom properties can't reach. Mirrors
// resources/scss/abstracts/_variables.scss (the single source, see
// CAMPBUDDY-BRAND-GUIDELINES.md §0); `npm run brand` fails if they drift.

export const BRAND = {
  maroon: '#ff4d2d',
  maroonDark: '#e03616',
  wine: '#ea580c',
  gold: '#d97706',
  ink: '#0f172a',
  paper: '#fafafd',
  paperSoft: '#fff7ed',
  line: '#e2e8f0',
  muted: '#475569',
  teal: '#2563eb',
  navy: '#1e293b',
};

// Poppins for every line of text, Baloo 2 for headlines and big numbers (§4).
export const FONT_TEXT = '"Poppins", "Segoe UI", system-ui, -apple-system, sans-serif';
export const FONT_DISPLAY = '"Baloo 2", "Poppins", "Segoe UI", system-ui, sans-serif';

/**
 * A canvas never asks for a font, so the browser only downloads the brand
 * fonts' @font-face files (resources/css/fonts.css) once something on the
 * page uses them. Load the weights the canvases draw with — Latin and
 * Devanagari — before the first frame; a font that fails leaves the fallback.
 */
export async function loadBrandFonts() {
  if (!document.fonts?.load) return;
  const sample = 'Aa अ';
  await Promise.allSettled([
    ...['400', '500', '600', '700'].map((w) => document.fonts.load(`${w} 32px "Poppins"`, sample)),
    ...['600', '700', '800'].map((w) => document.fonts.load(`${w} 32px "Baloo 2"`, sample)),
  ]);
}
