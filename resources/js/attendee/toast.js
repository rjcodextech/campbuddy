// Transient bottom-of-screen message. Markup: <template id="tpl-toast">
// (attendee/templates/shared.blade.php).

import { render } from './template.js';

export function showToast(message) {
  const toast = render('tpl-toast', { toast: message });
  document.body.appendChild(toast);
  setTimeout(() => toast.remove(), 4000);
}
