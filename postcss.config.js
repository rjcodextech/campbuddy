// Tailwind 4 runs as a Vite plugin (vite.config.js) on the admin stylesheet only;
// PostCSS keeps adding vendor prefixes to every stylesheet, the attendee app's included.
export default {
    plugins: {
        autoprefixer: {},
    },
};
