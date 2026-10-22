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
            // Brand fonts (CAMPBUDDY-BRAND-GUIDELINES.md §4), self-hosted in
            // resources/css/fonts.css: Poppins for UI, Baloo 2 for headings/numbers.
            fontFamily: {
                sans: ['Poppins', ...defaultTheme.fontFamily.sans],
                display: ['"Baloo 2"', 'Poppins', ...defaultTheme.fontFamily.sans],
            },
            // Radius grows with the surface (guideline §5): chips 6 · inputs 10 ·
            // rows 14 · cards 20 · sheets 28 · stages 40.
            borderRadius: {
                sm: '6px',
                DEFAULT: '6px',
                md: '10px',
                lg: '14px',
                xl: '20px',
                '2xl': '28px',
                '3xl': '40px',
            },
            boxShadow: {
                focus: '0 0 0 3px rgba(195, 58, 25, 0.32)',
            },
            // CampBuddy's own brand tokens — mirror resources/scss/abstracts/_variables.scss
            // (the single source; `npm run brand` checks they match) so the admin
            // panel reads as the same product as the attendee app, even though its
            // component library (Tailwind + Alpine) is deliberately different.
            colors: {
                ink: '#231f20',
                paper: '#fffaf4',
                'paper-soft': '#fff0df',
                muted: '#6b625e',
                subtle: '#7a706b',
                line: '#eadfd6',
                maroon: {
                    DEFAULT: '#c33a19',
                    dark: '#a12f14',
                },
                navy: '#0d2343',
                // Light only: highlights on ink, glows. Never text on paper (2.2:1).
                gold: '#e39d1c',
                wine: '#721313',
                teal: '#047a7c',
                // Destructive actions get their own red so "Delete" never
                // reads as the same thing as the maroon primary "Save".
                danger: {
                    DEFAULT: '#b42318',
                    dark: '#912018',
                    soft: '#fef3f2',
                },
            },
        },
    },

    plugins: [forms],
};
