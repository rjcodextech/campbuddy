# Ek WordPress, Hazaar Sites — WordPress Multisite talk

Interactive deck for WordCamp Rajasthan 2026 by Prathamesh Palve (@prathameshp).

- `Multisite-WordCamp-Rajasthan-2026.pptx`: the deck (15 slides, with speaker notes).
- `build-deck.js`: the generator script. It uses the brand assets in `assets/`, which come from `/wcr`.

Interactive bits, which work in Slide Show mode:
- Slide 3 is a clickable menu. Each tile jumps to its section.
- Every slide has a "☰ Menu" button that jumps back to the menu.
- The slide 7 quiz has "Jawab dekho" buttons that open the answers. The answers slide links back to the quiz.

Rebuild:

    npm install pptxgenjs react react-dom react-icons sharp
    node build-deck.js
