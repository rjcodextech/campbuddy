import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    server: {
        host: '127.0.0.1',
        port: 5173,
        cors: true,
        strictPort: true,
    },
    plugins: [
        laravel({
            // Two entry points: the admin panel keeps Breeze's
            // Tailwind + Alpine stack (app.css/app.js), scoped to /admin;
            // the attendee app is a separate bundle — hand-written
            // BEM/SCSS + vanilla JS, no framework — so neither design
            // system bleeds into the other.
            input: [
                // The brand fonts' @font-face rules, shared by both bundles.
                'resources/css/fonts.css',
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/scss/main.scss',
                'resources/js/attendee/app.js',
            ],
            refresh: true,
        }),
        // Tailwind 4: only the admin stylesheet (resources/css/app.css) imports it.
        tailwindcss(),
    ],
});
