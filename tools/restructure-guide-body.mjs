import fs from 'fs';

let content = fs.readFileSync('resources/views/attendee/partials/guide-body.blade.php', 'utf8');

// Replace #guide-what with restructured layout
const oldWhat = `<section id="guide-what" class="guide-section" data-track-section-view="what" aria-labelledby="guide-what-heading">
    <h2 id="guide-what-heading" class="guide-section__title">What is a WordCamp?</h2>
    <div class="card guide-prose">
        <p>A <strong>WordCamp</strong> is a conference about WordPress, the free software that runs a large part of the web. Each one is organized by <strong>local volunteers</strong> from the WordPress community, and there are WordCamps in cities all over the world.</p>
        <p>It's for <strong>everyone who uses WordPress</strong> or is curious about it: bloggers, small-business owners, designers, developers, marketers, students and writers. You don't need to be an expert, and nobody will quiz you.</p>
        <p><strong>Sponsors</strong> pay for much of it, so tickets stay cheap. Speakers and organizers are volunteers, not paid staff, which is a big part of why people are so easy to talk to.</p>
    </div>
</section>`;

const newWhat = `<section id="guide-what" class="guide-section" data-track-section-view="what" aria-labelledby="guide-what-heading">
    <div class="section-head">
        <span class="u-eyebrow"><x-attendee.line-icon name="compass" /> Conference Orientation</span>
        <h2 id="guide-what-heading" class="guide-section__title">What is a WordCamp?</h2>
        <span class="section-head__desc">The global community conference that anyone can attend</span>
    </div>
    <div class="card guide-prose guide-what-card">
        <div class="guide-what-body">
            <p>A <strong>WordCamp</strong> is a conference about WordPress, the free software that runs a large part of the web. Each one is organized by <strong>local volunteers</strong> from the WordPress community, and there are WordCamps in cities all over the world.</p>
            <p>It's for <strong>everyone who uses WordPress</strong> or is curious about it: bloggers, small-business owners, designers, developers, marketers, students and writers. You don't need to be an expert, and nobody will quiz you.</p>
            <p><strong>Sponsors</strong> pay for much of it, so tickets stay cheap. Speakers and organizers are volunteers, not paid staff, which is a big part of why people are so easy to talk to.</p>
        </div>
        <div class="guide-what-highlights">
            <div class="highlight-chip"><x-attendee.line-icon name="heart" /> 100% Volunteer-Organized</div>
            <div class="highlight-chip"><x-attendee.line-icon name="ticket" /> Heavily Subsidized Tickets</div>
            <div class="highlight-chip"><x-attendee.line-icon name="users" /> Open to All Experience Levels</div>
        </div>
    </div>
</section>`;

content = content.replace(oldWhat, newWhat);

// Replace section head for #guide-day
const oldDayHead = `<section id="guide-day" class="guide-section" data-track-section-view="day" aria-labelledby="guide-day-heading">
    <h2 id="guide-day-heading" class="guide-section__title">How the day usually goes</h2>
    <p class="guide-section__desc">
        Every WordCamp is a little different, but most follow this shape.
        @if ($event)
            Where we could find it in this event's schedule, you'll see the real time.
        @endif
    </p>`;

const newDayHead = `<section id="guide-day" class="guide-section" data-track-section-view="day" aria-labelledby="guide-day-heading">
    <div class="section-head">
        <span class="u-eyebrow"><x-attendee.line-icon name="clock" /> Conference Timeline</span>
        <h2 id="guide-day-heading" class="guide-section__title">How the day usually goes</h2>
        <span class="section-head__desc">
            Every WordCamp is a little different, but most follow this shape.
            @if ($event)
                Where we could find it in this event's schedule, you'll see the real time.
            @endif
        </span>
    </div>`;

content = content.replace(oldDayHead, newDayHead);

// Replace section head for #guide-words
const oldWordsHead = `<section id="guide-words" class="guide-section" data-track-section-view="words" aria-labelledby="guide-words-heading">
    <h2 id="guide-words-heading" class="guide-section__title">Words you'll hear</h2>
    <p class="guide-section__desc">Tap a word to see what it means.</p>`;

const newWordsHead = `<section id="guide-words" class="guide-section" data-track-section-view="words" aria-labelledby="guide-words-heading">
    <div class="section-head">
        <span class="u-eyebrow"><x-attendee.line-icon name="book-open" /> Community Glossary</span>
        <h2 id="guide-words-heading" class="guide-section__title">Words you'll hear</h2>
        <span class="section-head__desc">Tap any word to see what it means in plain English</span>
    </div>`;

content = content.replace(oldWordsHead, newWordsHead);

// Replace section head for #guide-tips
const oldTipsHead = `<section id="guide-tips" class="guide-section" data-track-section-view="tips" aria-labelledby="guide-tips-heading">
    <h2 id="guide-tips-heading" class="guide-section__title">Tips from WordCamp regulars</h2>`;

const newTipsHead = `<section id="guide-tips" class="guide-section" data-track-section-view="tips" aria-labelledby="guide-tips-heading">
    <div class="section-head">
        <span class="u-eyebrow"><x-attendee.line-icon name="sparkles" /> Hallway Wisdom</span>
        <h2 id="guide-tips-heading" class="guide-section__title">Tips from WordCamp regulars</h2>
        <span class="section-head__desc">Small habits that make the day twice as fun and relaxing</span>
    </div>`;

content = content.replace(oldTipsHead, newTipsHead);

// Replace section head for #guide-bring
const oldBringHead = `<section id="guide-bring" class="guide-section" data-track-section-view="bring" aria-labelledby="guide-bring-heading">
    <h2 id="guide-bring-heading" class="guide-section__title">What to bring</h2>`;

const newBringHead = `<section id="guide-bring" class="guide-section" data-track-section-view="bring" aria-labelledby="guide-bring-heading">
    <div class="section-head">
        <span class="u-eyebrow"><x-attendee.line-icon name="check-square" /> Packing Checklist</span>
        <h2 id="guide-bring-heading" class="guide-section__title">What to bring</h2>
        <span class="section-head__desc">Essentials to keep in your conference day bag</span>
    </div>`;

content = content.replace(oldBringHead, newBringHead);

// Replace section head for #guide-faq
const oldFaqHead = `<section id="guide-faq" class="guide-section" data-track-section-view="faq" aria-labelledby="guide-faq-heading">
    <h2 id="guide-faq-heading" class="guide-section__title">Questions newcomers ask</h2>`;

const newFaqHead = `<section id="guide-faq" class="guide-section" data-track-section-view="faq" aria-labelledby="guide-faq-heading">
    <div class="section-head">
        <span class="u-eyebrow"><x-attendee.line-icon name="help-circle" /> Common Concerns</span>
        <h2 id="guide-faq-heading" class="guide-section__title">Questions newcomers ask</h2>
        <span class="section-head__desc">Real answers to what first-timers worry about</span>
    </div>`;

content = content.replace(oldFaqHead, newFaqHead);

fs.writeFileSync('resources/views/attendee/partials/guide-body.blade.php', content, 'utf8');
console.log('Restructured guide-body.blade.php');
