// A line icon from the page's sprite (App\Support\LineIcons, drawn by
// attendee/partials/line-icons.blade.php), for screens JS fills in. Cloned
// from #tpl-line-icon like every other piece of runtime UI (template.js).

import { render } from './template.js';

export function lineIcon(name) {
  return render('tpl-line-icon', { use: { attrs: { href: `#li-${name || 'sparkles'}` } } });
}

/** A line icon, then text: for a template slot or replaceChildren(). */
export function iconText(name, text) {
  return [lineIcon(name), ` ${text}`];
}

const SVG = 'http://www.w3.org/2000/svg';

/**
 * The same icon built as nodes, for the few places that run on pages without
 * the templates (the picker's spotlight). Only a sprite id ("x", "map-pin")
 * is accepted as a name; anything else draws the default icon.
 */
export function spriteIcon(name) {
  const id = typeof name === 'string' && /^[a-z0-9-]{1,40}$/.test(name) ? name : 'sparkles';
  const svg = document.createElementNS(SVG, 'svg');
  svg.setAttribute('class', `line-icon line-icon--${id}`);
  svg.setAttribute('aria-hidden', 'true');
  svg.setAttribute('focusable', 'false');
  const use = document.createElementNS(SVG, 'use');
  use.setAttribute('href', `#li-${id}`);
  svg.append(use);

  return svg;
}
