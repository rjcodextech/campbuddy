// Transient bottom-of-screen message. Markup: <template id="tpl-toast">
// (attendee/templates/shared.blade.php).

import { render } from './template.js';

let activeToast = null;

export function showToast(message) {
  if (activeToast) {
    activeToast.remove();
    activeToast = null;
  }
  const toast = render('tpl-toast', { toast: message });
  document.body.appendChild(toast);
  activeToast = toast;
  setTimeout(() => {
    if (activeToast === toast) {
      toast.remove();
      activeToast = null;
    }
  }, 4000);
}
