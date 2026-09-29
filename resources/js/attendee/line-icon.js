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
