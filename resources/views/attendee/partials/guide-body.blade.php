{{--
    WordCamp 101 — the body of both Guide pages: the general one (/guide,
    $event null) and each event's own (/event/{slug}/guide), which also gets
    that event's practical details and live times (guide.js). Glossary and
    FAQ are native <details>, so they work offline and without JavaScript.
--}}
@php
    use App\Support\FirstTimerGuide;

    $event ??= null;
    $info = $event?->info ?? [];
    $webUrl = function (?string $v): ?string {
        $v = trim((string) $v);
        return preg_match('#^https?://\S+$#i', $v) === 1 ? $v : null;
    };
    $contactHref = function (?string $v): ?string {
        if (blank($v)) return null;
        if (filter_var($v, FILTER_VALIDATE_EMAIL)) return "mailto:{$v}";
        $digits = preg_replace('/[^\d+]/', '', $v);
        return strlen(preg_replace('/\D/', '', $digits)) >= 7 ? "tel:{$digits}" : null;
    };
    $practical = array_filter([
        ['map-pin', 'Venue', $info['venue'] ?? null, null],
        ['ticket', 'Registration', $info['registration_info'] ?? null, null],
        ['wifi', 'Wifi', $info['wifi'] ?? null, null],
        ['wrench', 'Contributor Day', $info['contributor_day_location'] ?? null, null],
        ['party', 'Social event', $info['social_event_info'] ?? null, null],
        ['siren', 'Need help?', $info['emergency_contact'] ?? null, $contactHref($info['emergency_contact'] ?? null)],
    ], fn ($row) => filled($row[2]));
    $cocUrl = $webUrl($info['code_of_conduct_url'] ?? null);
@endphp

<section class="hero-creative" aria-labelledby="guide-title">
    <nav class="hero-breadcrumb" aria-label="Breadcrumb">
        <a href="{{ route('home') }}">Home</a>
        <span class="hero-breadcrumb__sep" aria-hidden="true">/</span>
        <span class="hero-breadcrumb__current" aria-current="page">WordCamp Guide</span>
    </nav>



    <div class="hero-kicker">
        <x-attendee.line-icon name="sprout" />
        <span>5-Minute First-Timer Handbook</span>
    </div>

    <h1 id="guide-title" class="hero-creative__title">
        @if ($event)
            New to {{ $event->short_name ?: 'WordCamp' }}? <span class="hero-creative__highlight">Start here.</span>
        @else
            Your first WordCamp? <span class="hero-creative__highlight">Start here.</span>
        @endif
    </h1>

    <p class="hero-creative__lead">
        How the day runs, the words people throw around, what to bring, and how to start talking to people you don't know yet in the hallway track.
    </p>

    <div class="hero-creative__pills">
        <span class="hero-pill"><x-attendee.line-icon name="clock" /> 5-minute read</span>
        @if ($event && filled($event->venue))
            <span class="hero-pill"><x-attendee.line-icon name="map-pin" /> {{ $event->venue }}</span>
        @else
            <span class="hero-pill"><x-attendee.line-icon name="ticket" /> Free community guide</span>
        @endif
        <span class="hero-pill"><x-attendee.line-icon name="wifi" /> Works offline</span>
        <span class="hero-pill"><x-attendee.line-icon name="users" /> Hallway tips</span>
    </div>

    <div class="hero-creative__actions">
        <a href="#guide-what" class="btn btn--primary">
            <span>Read WordCamp 101</span>
            <span aria-hidden="true">&darr;</span>
        </a>
        <a href="{{ route('home') }}#find-your-camp" class="btn btn--outline">
            <span>Select WordCamp</span>
            <span aria-hidden="true">&rarr;</span>
        </a>
    </div>

    <div class="hero-creative__waves" aria-hidden="true">
        <svg viewBox="0 0 1200 120" preserveAspectRatio="none">
            <path d="M0,0 C150,90 350,-40 500,40 C650,120 900,10 1200,40 L1200,120 L0,120 Z"></path>
        </svg>
    </div>
</section>

<nav class="guide-jump" aria-label="Guide sections">
    @if ($event && ($practical || $cocUrl))
        <a class="chip" href="#guide-here" data-track="guide_section_jump" data-track-section="here">
            <x-attendee.line-icon name="map-pin" />
            <span>At this event</span>
        </a>
    @endif
    <a class="chip" href="#guide-why" data-track="guide_section_jump" data-track-section="why">
        <x-attendee.line-icon name="sparkles" />
        <span>Superpowers</span>
    </a>
    <a class="chip" href="#guide-what" data-track="guide_section_jump" data-track-section="what">
        <x-attendee.line-icon name="help-circle" />
        <span>What is it?</span>
    </a>
    <a class="chip" href="#guide-day" data-track="guide_section_jump" data-track-section="day">
        <x-attendee.line-icon name="calendar" />
        <span>Your day</span>
    </a>
    <a class="chip" href="#guide-words" data-track="guide_section_jump" data-track-section="words">
        <x-attendee.line-icon name="message-circle" />
        <span>Words you'll hear</span>
    </a>
    <a class="chip" href="#guide-tips" data-track="guide_section_jump" data-track-section="tips">
        <x-attendee.line-icon name="lightbulb" />
        <span>Tips</span>
    </a>
    <a class="chip" href="#guide-students" data-track="guide_section_jump" data-track-section="students">
        <x-attendee.line-icon name="graduation-cap" />
        <span>Students</span>
    </a>
    <a class="chip" href="#guide-bring" data-track="guide_section_jump" data-track-section="bring">
        <x-attendee.line-icon name="briefcase" />
        <span>What to bring</span>
    </a>
    <a class="chip" href="#guide-faq" data-track="guide_section_jump" data-track-section="faq">
        <x-attendee.line-icon name="help-circle" />
        <span>FAQ</span>
    </a>
</nav>


<section id="guide-why" class="guide-section" aria-labelledby="guide-why-heading">
    <div class="card guide-why-card">
        <div class="section-head" style="margin-bottom:16px">
            <span class="u-eyebrow"><x-attendee.line-icon name="sparkles" /> Why Use CampBuddy?</span>
            <h2 id="guide-why-heading" class="guide-section__title" style="margin:4px 0 6px">Your 4 WordCamp Superpowers</h2>
            <p class="guide-section__desc" style="margin:0">Open CampBuddy on your phone once, and everything stays ready &mdash; even without internet.</p>
        </div>
        <div class="guide-superpowers-grid">
            <div class="superpower-col">
                <div class="superpower-icon"><x-attendee.line-icon name="calendar" /></div>
                <strong>1. Never Miss a Talk</strong>
                <span>Track multiple halls live with real-time "Now &amp; Next" and 1-tap bookmarks in "My Day".</span>
            </div>
            <div class="superpower-col">
                <div class="superpower-icon"><x-attendee.line-icon name="qr-code" /></div>
                <strong>2. Swap Camp Cards in 2s</strong>
                <span>Exchange LinkedIn and website via your phone camera. No paper cards, zero sign-up required.</span>
            </div>
            <div class="superpower-col">
                <div class="superpower-icon"><x-attendee.line-icon name="wrench" /></div>
                <strong>3. Find Contributor Tables</strong>
                <span>A 30-second skill quiz points you to the exact table (Core, Docs, Design, Polyglots) for your skills.</span>
            </div>
            <div class="superpower-col">
                <div class="superpower-icon"><x-attendee.line-icon name="lock" /></div>
                <strong>4. 100% Offline &amp; Private</strong>
                <span>Zero login, no passwords, no trackers. All data is saved on your device (DPDPA 2023 compliant).</span>
            </div>
        </div>
    </div>
</section>

@if ($event && ($practical || $cocUrl))
    <section id="guide-here" class="guide-section" data-track-section-view="here" aria-labelledby="guide-here-heading">
        <h2 id="guide-here-heading" class="guide-section__title">At {{ $event->display_name }}</h2>
        <div class="card guide-practical">
            @foreach ($practical as [$icon, $label, $value, $href])
                @if ($href)
                    <a class="useful-link" href="{{ $href }}" data-track="useful_link_click" data-track-link-type="emergency">
                        <span class="useful-link__icon" aria-hidden="true"><x-attendee.line-icon :name="$icon" /></span>
                        <span><span class="useful-link__title">{{ $label }}</span><span class="useful-link__desc">{{ $value }}</span></span>
                    </a>
                @else
                    <div class="useful-link">
                        <span class="useful-link__icon" aria-hidden="true"><x-attendee.line-icon :name="$icon" /></span>
                        <span><span class="useful-link__title">{{ $label }}</span><span class="useful-link__desc">{{ $value }}</span></span>
                    </div>
                @endif
            @endforeach
            @if ($cocUrl)
                <a class="useful-link" href="{{ $cocUrl }}" target="_blank" rel="noopener" data-track="useful_link_click" data-track-link-type="code_of_conduct">
                    <span class="useful-link__icon" aria-hidden="true"><x-attendee.line-icon name="clipboard" /></span>
                    <span><span class="useful-link__title">Code of Conduct</span><span class="useful-link__desc">The rules that keep this event welcoming for everyone.</span></span>
                </a>
            @endif
        </div>
    </section>
@endif

<section id="guide-what" class="guide-section" data-track-section-view="what" aria-labelledby="guide-what-heading">
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
</section>

<section id="guide-day" class="guide-section" data-track-section-view="day" aria-labelledby="guide-day-heading">
    <div class="section-head">
        <span class="u-eyebrow"><x-attendee.line-icon name="clock" /> Conference Timeline</span>
        <h2 id="guide-day-heading" class="guide-section__title">How the day usually goes</h2>
        <span class="section-head__desc">
            Every WordCamp is a little different, but most follow this shape.
            @if ($event)
                Where we could find it in this event's schedule, you'll see the real time.
            @endif
        </span>
    </div>
    <ol class="guide-timeline">
        @foreach (FirstTimerGuide::day() as $step)
            <li class="guide-step" data-guide-match="{{ implode('|', $step['match']) }}" data-guide-talk="{{ ($step['talk'] ?? false) ? '1' : '0' }}">
                <span class="guide-step__icon" aria-hidden="true"><x-attendee.line-icon :name="$step['icon']" /></span>
                <div class="guide-step__body">
                    <h3 class="guide-step__title">{{ $step['title'] }}</h3>
                    <p class="guide-step__when" data-guide-when hidden></p>
                    <p class="guide-step__what">{{ $step['what'] }}</p>
                    <p class="guide-step__tip"><strong>Tip:</strong> {{ $step['tip'] }}</p>
                </div>
            </li>
        @endforeach
    </ol>
</section>

<section id="guide-words" class="guide-section" data-track-section-view="words" aria-labelledby="guide-words-heading">
    <div class="section-head">
        <span class="u-eyebrow"><x-attendee.line-icon name="book-open" /> Community Glossary</span>
        <h2 id="guide-words-heading" class="guide-section__title">Words you'll hear</h2>
        <span class="section-head__desc">Tap any word to see what it means in plain English</span>
    </div>
    <div class="guide-accordion">
        @foreach (FirstTimerGuide::glossary() as $entry)
            <details class="guide-accordion__item" data-track-open="glossary_open" data-track-term="{{ $entry['term'] }}">
                <summary>{{ $entry['term'] }}</summary>
                <p>{{ $entry['meaning'] }}</p>
            </details>
        @endforeach
    </div>
</section>

<section id="guide-tips" class="guide-section" data-track-section-view="tips" aria-labelledby="guide-tips-heading">
    <div class="section-head">
        <span class="u-eyebrow"><x-attendee.line-icon name="sparkles" /> Hallway Wisdom</span>
        <h2 id="guide-tips-heading" class="guide-section__title">Tips from WordCamp regulars</h2>
        <span class="section-head__desc">Small habits that make the day twice as fun and relaxing</span>
    </div>
    <div class="guide-tips">
        @foreach (FirstTimerGuide::tips() as $tip)
            <div class="guide-tip">
                <span class="guide-tip__icon" aria-hidden="true"><x-attendee.line-icon :name="$tip['icon']" /></span>
                <div>
                    <h3 class="guide-tip__title">{{ $tip['title'] }}</h3>
                    <p class="guide-tip__text">{{ $tip['text'] }}</p>
                </div>
            </div>
        @endforeach
    </div>
</section>

<section id="guide-students" class="guide-section" data-track-section-view="students" aria-labelledby="guide-students-heading">
    <h2 id="guide-students-heading" class="guide-section__title"><x-attendee.line-icon name="graduation-cap" class="guide-section__icon" /> For college students</h2>
    <p class="guide-section__desc">For the price of a ticket you get a day of practical talks, a chance to work on real open-source code, and a room full of people who hire.</p>
    <div class="guide-tips">
        @foreach (FirstTimerGuide::students() as $tip)
            <div class="guide-tip">
                <span class="guide-tip__icon" aria-hidden="true"><x-attendee.line-icon :name="$tip['icon']" /></span>
                <div>
                    <h3 class="guide-tip__title">{{ $tip['title'] }}</h3>
                    <p class="guide-tip__text">{{ $tip['text'] }}</p>
                </div>
            </div>
        @endforeach
    </div>
    <div class="card guide-student-intro">
        <p class="u-eyebrow">Your 15-second intro</p>
        <p class="guide-student-intro__line">{{ FirstTimerGuide::studentIntro() }}</p>
        <p class="footer-note" style="text-align:left;margin:0">End with a question. Then it's their turn to talk, and the conversation keeps going.</p>
    </div>
</section>

<section id="guide-bring" class="guide-section" data-track-section-view="bring" aria-labelledby="guide-bring-heading">
    <div class="section-head">
        <span class="u-eyebrow"><x-attendee.line-icon name="check-square" /> Packing Checklist</span>
        <h2 id="guide-bring-heading" class="guide-section__title">What to bring</h2>
        <span class="section-head__desc">Essentials to keep in your conference day bag</span>
    </div>
    <ul class="card guide-bring">
        @foreach (FirstTimerGuide::bring() as $item)
            <li>{{ $item }}</li>
        @endforeach
    </ul>
</section>

<section id="guide-faq" class="guide-section" data-track-section-view="faq" aria-labelledby="guide-faq-heading">
    <div class="section-head">
        <span class="u-eyebrow"><x-attendee.line-icon name="help-circle" /> Common Concerns</span>
        <h2 id="guide-faq-heading" class="guide-section__title">Questions newcomers ask</h2>
        <span class="section-head__desc">Real answers to what first-timers worry about</span>
    </div>
    <div class="guide-accordion">
        @foreach (FirstTimerGuide::faq() as $entry)
            <details class="guide-accordion__item" data-track-open="faq_open" data-track-question="{{ $entry['q'] }}">
                <summary>{{ $entry['q'] }}</summary>
                <p>{{ $entry['a'] }}</p>
            </details>
        @endforeach
    </div>
</section>

<section class="guide-section guide-next" aria-labelledby="guide-next-heading">
    <h2 id="guide-next-heading" class="guide-section__title">Ready? Three small steps</h2>
    @if ($event)
        <div class="guide-next__list">
            <a class="action-card action-card--wide" href="{{ route('event.my-day', $event) }}" data-track="guide_next_click" data-track-target="my_day">
                <span class="action-card__icon" aria-hidden="true"><x-attendee.line-icon name="star" /></span>
                <span><span class="action-card__title">Save 3 sessions you'd enjoy</span><span class="action-card__desc">Tap the star on a session in My Day. Look for "Beginner friendly".</span></span>
            </a>
            <a class="action-card action-card--wide" href="{{ route('event.quest', $event) }}" data-track="guide_next_click" data-track-target="quest">
                <span class="action-card__icon" aria-hidden="true"><x-attendee.line-icon name="compass" /></span>
                <span><span class="action-card__title">Start your Quest</span><span class="action-card__desc">Small, friendly challenges that make meeting people easy.</span></span>
            </a>
            <a class="action-card action-card--wide" href="{{ route('event.camp-card', $event) }}" data-track="guide_next_click" data-track-target="camp_card">
                <span class="action-card__icon" aria-hidden="true"><x-attendee.line-icon name="id-card" /></span>
                <span><span class="action-card__title">Make your Camp Card</span><span class="action-card__desc">A digital badge people can scan to stay in touch.</span></span>
            </a>
        </div>
    @else
        <a class="btn btn--primary btn--full" href="{{ route('home') }}#find-your-camp" data-track="home_link_click" data-track-target="find_your_camp">Choose your WordCamp →</a>
    @endif
</section>
