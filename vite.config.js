import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            // Two entry points (§5.5): the admin panel keeps Breeze's
            // Tailwind + Alpine stack (app.css/app.js), scoped to /admin;
            // the attendee app is a separate bundle — hand-written
            // BEM/SCSS + vanilla JS, no framework — so neither design
            // system bleeds into the other (§4.5).
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/scss/main.scss',
                'resources/js/attendee/app.js',
            ],
            refresh: true,
        }),
    ],
});
