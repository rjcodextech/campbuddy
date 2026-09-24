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
        ['📍', 'Venue', $info['venue'] ?? null, null],
        ['🎫', 'Registration', $info['registration_info'] ?? null, null],
        ['📶', 'Wifi', $info['wifi'] ?? null, null],
        ['🛠️', 'Contributor Day', $info['contributor_day_location'] ?? null, null],
        ['🎉', 'Social event', $info['social_event_info'] ?? null, null],
        ['🚨', 'Need help?', $info['emergency_contact'] ?? null, $contactHref($info['emergency_contact'] ?? null)],
    ], fn ($row) => filled($row[2]));
    $cocUrl = $webUrl($info['code_of_conduct_url'] ?? null);
@endphp

<section class="guide-hero" aria-labelledby="guide-title">
    <div class="guide-hero__text">
        <p class="guide-hero__eyebrow">
            <img src="/media/icons/icon-192.png" alt="" width="22" height="22">
            WordCamp 101
        </p>
        <h1 id="guide-title" class="guide-hero__title">
            @if ($event)
                New to {{ $event->short_name ?: 'WordCamp' }}?<br>Start here.
            @else
                Your first WordCamp?<br>Start here.
            @endif
        </h1>
        <p class="guide-hero__lead">
            What happens when, the words people use, and how to make friends — everything regulars wish they'd known on day one.
        </p>
        <ul class="guide-hero__meta" aria-label="About this guide">
            <li>⏱ 5-minute read</li>
            <li>📶 Works offline</li>
        </ul>
    </div>
    <img class="guide-hero__art" src="/media/illustrations/first-badge.svg" alt="" width="260" height="300">
</section>

<nav class="guide-jump" aria-label="Guide sections">
    @if ($event && ($practical || $cocUrl))
        <a class="chip" href="#guide-here" data-track="guide_section_jump" data-track-section="here">At this event</a>
    @endif
    <a class="chip" href="#guide-what" data-track="guide_section_jump" data-track-section="what">What is it?</a>
    <a class="chip" href="#guide-day" data-track="guide_section_jump" data-track-section="day">Your day</a>
    <a class="chip" href="#guide-words" data-track="guide_section_jump" data-track-section="words">Words you'll hear</a>
    <a class="chip" href="#guide-tips" data-track="guide_section_jump" data-track-section="tips">Tips</a>
    <a class="chip" href="#guide-bring" data-track="guide_section_jump" data-track-section="bring">What to bring</a>
    <a class="chip" href="#guide-faq" data-track="guide_section_jump" data-track-section="faq">FAQ</a>
</nav>

@if ($event && ($practical || $cocUrl))
    <section id="guide-here" class="guide-section" data-track-section-view="here" aria-labelledby="guide-here-heading">
        <h2 id="guide-here-heading" class="guide-section__title">At {{ $event->display_name }}</h2>
        <div class="card guide-practical">
            @foreach ($practical as [$icon, $label, $value, $href])
                @if ($href)
                    <a class="useful-link" href="{{ $href }}" data-track="useful_link_click" data-track-link-type="emergency">
                        <span class="useful-link__icon" aria-hidden="true">{{ $icon }}</span>
                        <span><span class="useful-link__title">{{ $label }}</span><span class="useful-link__desc">{{ $value }}</span></span>
                    </a>
                @else
                    <div class="useful-link">
                        <span class="useful-link__icon" aria-hidden="true">{{ $icon }}</span>
                        <span><span class="useful-link__title">{{ $label }}</span><span class="useful-link__desc">{{ $value }}</span></span>
                    </div>
                @endif
            @endforeach
            @if ($cocUrl)
                <a class="useful-link" href="{{ $cocUrl }}" target="_blank" rel="noopener" data-track="useful_link_click" data-track-link-type="code_of_conduct">
                    <span class="useful-link__icon" aria-hidden="true">📋</span>
                    <span><span class="useful-link__title">Code of Conduct</span><span class="useful-link__desc">The rules that keep this event welcoming for everyone.</span></span>
                </a>
            @endif
        </div>
    </section>
@endif

<section id="guide-what" class="guide-section" data-track-section-view="what" aria-labelledby="guide-what-heading">
    <h2 id="guide-what-heading" class="guide-section__title">What is a WordCamp?</h2>
    <div class="card guide-prose">
        <p>A <strong>WordCamp</strong> is a conference about WordPress — the free software behind a huge share of the world's websites. Each one is organized by <strong>local volunteers</strong> from the WordPress community, and there are WordCamps in cities all over the world.</p>
        <p>It's for <strong>everyone who uses WordPress</strong> or is curious about it: bloggers, small-business owners, designers, developers, marketers, students and writers. You don't need to be an expert, and nobody will quiz you.</p>
        <p>Tickets are kept affordable thanks to <strong>sponsors</strong>, and everyone — speakers and organizers included — is there because they love the community. That's why the atmosphere is famously friendly.</p>
    </div>
</section>

<section id="guide-day" class="guide-section" data-track-section-view="day" aria-labelledby="guide-day-heading">
    <h2 id="guide-day-heading" class="guide-section__title">How the day usually goes</h2>
    <p class="guide-section__desc">
        Every WordCamp is a little different, but most follow this shape.
        @if ($event)
            Where we could find it in this event's schedule, you'll see the real time.
        @endif
    </p>
    <ol class="guide-timeline">
        @foreach (FirstTimerGuide::day() as $step)
            <li class="guide-step" data-guide-match="{{ implode('|', $step['match']) }}" data-guide-talk="{{ ($step['talk'] ?? false) ? '1' : '0' }}">
                <span class="guide-step__icon" aria-hidden="true">{{ $step['icon'] }}</span>
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
    <h2 id="guide-words-heading" class="guide-section__title">Words you'll hear</h2>
    <p class="guide-section__desc">Tap a word to see what it means.</p>
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
    <h2 id="guide-tips-heading" class="guide-section__title">Tips from WordCamp regulars</h2>
    <div class="guide-tips">
        @foreach (FirstTimerGuide::tips() as $tip)
            <div class="guide-tip">
                <span class="guide-tip__icon" aria-hidden="true">{{ $tip['icon'] }}</span>
                <div>
                    <h3 class="guide-tip__title">{{ $tip['title'] }}</h3>
                    <p class="guide-tip__text">{{ $tip['text'] }}</p>
                </div>
            </div>
        @endforeach
    </div>
</section>

<section id="guide-bring" class="guide-section" data-track-section-view="bring" aria-labelledby="guide-bring-heading">
    <h2 id="guide-bring-heading" class="guide-section__title">What to bring</h2>
    <ul class="card guide-bring">
        @foreach (FirstTimerGuide::bring() as $item)
            <li>{{ $item }}</li>
        @endforeach
    </ul>
</section>

<section id="guide-faq" class="guide-section" data-track-section-view="faq" aria-labelledby="guide-faq-heading">
    <h2 id="guide-faq-heading" class="guide-section__title">Questions newcomers ask</h2>
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
                <span class="action-card__icon" aria-hidden="true">⭐</span>
                <span><span class="action-card__title">Save 3 sessions you'd enjoy</span><span class="action-card__desc">Tap the star on a session in My Day. Look for "Beginner friendly".</span></span>
            </a>
            <a class="action-card action-card--wide" href="{{ route('event.quest', $event) }}" data-track="guide_next_click" data-track-target="quest">
                <span class="action-card__icon" aria-hidden="true">🧭</span>
                <span><span class="action-card__title">Start your Quest</span><span class="action-card__desc">Small, friendly challenges that make meeting people easy.</span></span>
            </a>
            <a class="action-card action-card--wide" href="{{ route('event.camp-card', $event) }}" data-track="guide_next_click" data-track-target="camp_card">
                <span class="action-card__icon" aria-hidden="true">📇</span>
                <span><span class="action-card__title">Make your Camp Card</span><span class="action-card__desc">A digital badge people can scan to stay in touch.</span></span>
            </a>
        </div>
    @else
        <a class="btn btn--primary btn--full" href="{{ route('home') }}#find-your-camp">Choose your WordCamp →</a>
    @endif
</section>
