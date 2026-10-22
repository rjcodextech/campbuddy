import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

// Inside the Android / iOS app only: phone fit, organizer alerts and the lock on the panel.
if (/^\/(admin|manager)(\/|$)/.test(location.pathname) && /CampBuddyApp\//.test(navigator.userAgent)) {
    import('./organizer-native').then((m) => m.initOrganizerNative()).catch(() => {});
}

// Only the "QR codes" pages have [data-qr-kit] cards; the QR library is loaded just there.
if (document.querySelector('[data-qr-kit]')) import('./qr-kit').then((kit) => kit.initQrKit());

// Analytics → Usage post (drawn in the browser from the WordCamp's numbers).
if (document.querySelector('[data-usage-post]')) import('./usage-post').then((m) => m.initUsagePost());

// The Social media page's posts and people cards (drawn in the browser).
if (document.getElementById('social-kit-data')) {
    import('./social-kit').then((kit) => {
        kit.initSocialKit();
        // Its reels (video) use the same live state, so they start after it; the studio opens once both are wired.
        const reels = document.querySelector('[data-social-reels]') ? import('./social-reels').then((m) => m.initSocialReels()) : Promise.resolve();
        reels.finally(() => import('./social-studio').then((m) => m.initStudio()));
    });
}
