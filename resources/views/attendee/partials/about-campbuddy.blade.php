{{--
    "What is CampBuddy?" for the WordCamp picker ("/"), the answer to
    feedback that people didn't get the use case. Two parts, included in
    different places by welcome.blade.php:
      $part = 'steps'  the four things it does, under the event list;
      $part = 'more'   the moving tour of real screens, who it's for, FAQ.
--}}
@if ($part === 'steps')
    <section class="about-steps" aria-labelledby="about-steps-heading">
        <h2 id="about-steps-heading" class="about-steps__title">How it works</h2>
        <ol class="about-steps__list">
            @foreach ([
                ['home', 'See what\'s on', 'What\'s on now and next, with a short tip for each part of the day.'],
                ['my-day', 'Plan your day', 'Star the talks you want and add people you\'d like to meet.'],
                ['explore', 'Find your people', 'See who shares your interests. Wave, and if they wave back you both see names.'],
                ['camp-card', 'Swap contacts', 'Show the QR code on your Camp Card instead of handing out paper cards.'],
            ] as $i => [$icon, $title, $text])
                <li class="about-step">
                    <span class="about-step__icon" aria-hidden="true">
                        <img src="/media/{{ $icon }}.svg" alt="" width="28" height="28">
                    </span>
                    <span class="about-step__title"><span class="about-step__num">{{ $i + 1 }}.</span> {{ $title }}</span>
                    <span class="about-step__text">{{ $text }}</span>
                </li>
            @endforeach
        </ol>
    </section>
@else
    <section class="about-tour" aria-labelledby="about-tour-heading">
        <div class="section-head">
            <h2 id="about-tour-heading" class="section-head__title">What it looks like</h2>
            <span class="section-head__desc">Swipe or tap the phone</span>
        </div>

        {{-- A short, silent "video" made of the app's real screens (tour.js):
        plays on its own, pauses when touched, and never moves for anyone who
        asked their device for reduced motion. --}}
        <div class="tour" data-tour>
            <div class="tour__phone">
                <div class="tour__screen" aria-live="off">
                    @foreach ([
                        ['1-home', 'Home shows what\'s on now and what\'s next, with a tip for that part of the day.'],
                        ['2-schedule', 'Go through the schedule and star the talks you want. Beginner-friendly ones are marked.'],
                        ['3-people', 'Join attendee discovery to see who shares your interests, then go and say hi.'],
                        ['4-guide', 'First WordCamp? The guide explains how the day runs and the words people use.'],
                        ['5-camp-card', 'Your Camp Card. Someone scans the QR code and lands on your LinkedIn.'],
                    ] as $i => [$image, $caption])
                        <figure class="tour__slide" data-tour-slide @if ($i > 0) hidden @endif>
                            <img src="/media/tour/{{ $image }}.jpg" alt="{{ $caption }}" width="360" height="720" @if ($i > 0) loading="lazy" @endif decoding="async">
                        </figure>
                    @endforeach
                </div>
            </div>

            <div class="tour__side">
                <p class="tour__caption" data-tour-caption aria-live="polite"></p>
                <div class="tour__controls">
                    <button type="button" class="tour__btn" data-tour-prev aria-label="Previous screen">‹</button>
                    <div class="tour__dots" role="tablist" aria-label="Screens" data-tour-dots></div>
                    <button type="button" class="tour__btn" data-tour-next aria-label="Next screen">›</button>
                    <button type="button" class="tour__btn tour__btn--play" data-tour-toggle aria-label="Pause">❚❚</button>
                </div>
            </div>
        </div>
    </section>

    <section class="about-who" aria-labelledby="about-who-heading">
        <div class="section-head">
            <h2 id="about-who-heading" class="section-head__title">Who it's for</h2>
        </div>
        <div class="about-who__grid">
            <a class="about-who__card" href="{{ route('guide') }}" data-track="guide_open" data-track-surface="picker_who_first">
                <span class="about-who__title">First WordCamp</span>
                <span class="about-who__text">Read the 5-minute guide, then follow the tips on Home during the day.</span>
                <span class="about-who__cta">Read the guide →</span>
            </a>
            <a class="about-who__card" href="{{ route('guide') }}#guide-students" data-track="guide_open" data-track-surface="picker_who_student">
                <span class="about-who__title">Students</span>
                <span class="about-who__text">Learn from people who do this for a living, work on open source, and meet companies that hire.</span>
                <span class="about-who__cta">Tips for students →</span>
            </a>
            <a class="about-who__card" href="#find-your-camp">
                <span class="about-who__title">Been before</span>
                <span class="about-who__text">Plan your talks quickly and find people who are into the same things.</span>
                <span class="about-who__cta">Choose your WordCamp ↑</span>
            </a>
        </div>
    </section>

    <section class="about-faq" aria-labelledby="about-faq-heading">
        <div class="section-head">
            <h2 id="about-faq-heading" class="section-head__title">Questions</h2>
        </div>
        <div class="guide-accordion">
            @foreach (\App\Support\FirstTimerGuide::quickQuestions() as ['q' => $q, 'a' => $a])
                <details class="guide-accordion__item" data-track-open="faq_open" data-track-question="{{ $q }}">
                    <summary>{{ $q }}</summary>
                    <p>{{ $a }}</p>
                </details>
            @endforeach
        </div>
    </section>
@endif
