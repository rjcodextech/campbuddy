import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

// Only the "QR codes" pages have [data-qr-kit] cards; the QR library is loaded just there.
if (document.querySelector('[data-qr-kit]')) import('./qr-kit').then((kit) => kit.initQrKit());

// The Social media page's posts and people cards (drawn in the browser).
if (document.getElementById('social-kit-data')) import('./social-kit').then((kit) => kit.initSocialKit());
