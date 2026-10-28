import fs from 'fs';

let content = fs.readFileSync('resources/views/attendee/about.blade.php', 'utf8');

content = content.replace(
`<section id="three-pillars" class="guide-section" aria-labelledby="pillars-heading">
                <div class="section-head">
                    <h2 id="pillars-heading" class="section-head__title">The 3 Zero-Friction Promises</h2>`,
`<section id="three-pillars" class="guide-section" aria-labelledby="pillars-heading">
                <div class="section-head">
                    <span class="u-eyebrow"><x-attendee.line-icon name="shield" /> Architectural Philosophy</span>
                    <h2 id="pillars-heading" class="section-head__title">The 3 Zero-Friction Promises</h2>`
);

content = content.replace(
`<section id="attendee-features" class="guide-section" aria-labelledby="features-heading">
                <div class="section-head">
                    <h2 id="features-heading" class="section-head__title">What CampBuddy Does For Attendees</h2>`,
`<section id="attendee-features" class="guide-section" aria-labelledby="features-heading">
                <div class="section-head">
                    <span class="u-eyebrow"><x-attendee.line-icon name="sparkles" /> Attendee Toolset</span>
                    <h2 id="features-heading" class="section-head__title">What CampBuddy Does For Attendees</h2>`
);

content = content.replace(
`<section id="organizer-features" class="guide-section" aria-labelledby="organizer-heading">
                <div class="section-head">
                    <h2 id="organizer-heading" class="section-head__title">Features For WordCamp Organizers</h2>`,
`<section id="organizer-features" class="guide-section" aria-labelledby="organizer-heading">
                <div class="section-head">
                    <span class="u-eyebrow"><x-attendee.line-icon name="tool" /> Event Technology</span>
                    <h2 id="organizer-heading" class="section-head__title">Features For WordCamp Organizers</h2>`
);

content = content.replace(
`<section id="comparison-matrix" class="guide-section" aria-labelledby="comparison-heading">
                <div class="section-head">
                    <h2 id="comparison-heading" class="section-head__title">CampBuddy vs Other Solutions</h2>`,
`<section id="comparison-matrix" class="guide-section" aria-labelledby="comparison-heading">
                <div class="section-head">
                    <span class="u-eyebrow"><x-attendee.line-icon name="layers" /> Direct Benchmark</span>
                    <h2 id="comparison-heading" class="section-head__title">CampBuddy vs Other Solutions</h2>`
);

content = content.replace(
`<section id="architecture" class="guide-section" aria-labelledby="arch-heading">
                <div class="section-head">
                    <h2 id="arch-heading" class="section-head__title">Technical Architecture &amp; Privacy</h2>`,
`<section id="architecture" class="guide-section" aria-labelledby="arch-heading">
                <div class="section-head">
                    <span class="u-eyebrow"><x-attendee.line-icon name="cpu" /> Technical Foundation</span>
                    <h2 id="arch-heading" class="section-head__title">Technical Architecture &amp; Privacy</h2>`
);

content = content.replace(
`<section id="faq" class="guide-section" aria-labelledby="faq-heading">
                <div class="section-head">
                    <h2 id="faq-heading" class="section-head__title">Frequently Asked Questions</h2>`,
`<section id="faq" class="guide-section" aria-labelledby="faq-heading">
                <div class="section-head">
                    <span class="u-eyebrow"><x-attendee.line-icon name="help-circle" /> Common Inquiries</span>
                    <h2 id="faq-heading" class="section-head__title">Frequently Asked Questions</h2>`
);

fs.writeFileSync('resources/views/attendee/about.blade.php', content, 'utf8');
console.log('Restructured about.blade.php');
