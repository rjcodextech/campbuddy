import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            // CampBuddy's own brand tokens (resources/scss/abstracts/_variables.scss,
            // carried from V1) — reused here so the admin panel reads as the same
            // product as the attendee app, even though its component library
            // (Tailwind + Alpine) is deliberately different.
            colors: {
                ink: '#231f20',
                paper: '#fffaf4',
                'paper-soft': '#fff0df',
                muted: '#6b625e',
                line: '#eadfd6',
                maroon: {
                    DEFAULT: '#c33a19',
                    dark: '#a12f14',
                },
                navy: '#0d2343',
                gold: '#e39d1c',
                teal: '#049395',
            },
        },
    },

    plugins: [forms],
};
