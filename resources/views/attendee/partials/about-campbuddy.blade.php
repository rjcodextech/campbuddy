{{--
    "What is CampBuddy?" for the WordCamp picker ("/") — the answer to
    feedback that people didn't get the use case. Two parts, included in
    different places by welcome.blade.php:
      $part = 'steps'  — the four-step infographic, right under the intro;
      $part = 'more'   — the moving tour of real screens, who it's for, FAQ.
--}}
@if ($part === 'steps')
    <section class="about-steps" aria-labelledby="about-steps-heading">
        <h2 id="about-steps-heading" class="about-steps__title">How CampBuddy helps you at WordCamp</h2>
        <p class="about-steps__lead">Four things, one app — open it any time during the event.</p>
        <ol class="about-steps__list">
            @foreach ([
                ['home', 'Know what\'s happening', 'See what\'s on now and next — with a tip on what to do.'],
                ['my-day', 'Plan your day', 'Star the talks you like. We\'ll remind you before they start.'],
                ['explore', 'Meet your people', 'Find attendees who share your interests — with names and profiles.'],
                ['camp-card', 'Stay in touch', 'Share your Camp Card\'s QR code. No paper cards to lose.'],
            ] as $i => [$icon, $title, $text])
                <li class="about-step">
                    <span class="about-step__icon" aria-hidden="true">
                        <img src="/media/{{ $icon }}.svg" alt="" width="28" height="28">
                        <span class="about-step__num">{{ $i + 1 }}</span>
                    </span>
                    <span class="about-step__title">{{ $title }}</span>
                    <span class="about-step__text">{{ $text }}</span>
                </li>
            @endforeach
        </ol>
    </section>
@else
    <section class="about-tour" aria-labelledby="about-tour-heading">
        <div class="section-head">
            <h2 id="about-tour-heading" class="section-head__title">See it in action</h2>
            <span class="section-head__desc">Real screens from the app — swipe or tap the phone</span>
        </div>

        {{-- A short, silent "video" made of the app's real screens (tour.js):
        plays on its own, pauses when touched, and never moves for anyone who
        asked their device for reduced motion. --}}
        <div class="tour" data-tour>
            <div class="tour__phone">
                <div class="tour__screen" aria-live="off">
                    @foreach ([
                        ['1-home', 'Home tells you what\'s on now, what\'s next — and what to actually do about it.'],
                        ['2-schedule', 'Browse the schedule and star the talks you want. Beginner-friendly ones are marked.'],
                        ['3-people', 'Join attendee discovery to find people who share your interests — then go say hi.'],
                        ['4-guide', 'First WordCamp? A 5-minute guide explains the day, the jargon and the etiquette.'],
                        ['5-camp-card', 'Your Camp Card: show the QR code, and new contacts land straight on your LinkedIn.'],
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
            <h2 id="about-who-heading" class="section-head__title">Made for you, whoever you are</h2>
        </div>
        <div class="about-who__grid">
            <a class="about-who__card" href="{{ route('guide') }}" data-track="guide_open" data-track-surface="picker_who_first">
                <span class="about-who__emoji" aria-hidden="true">🌱</span>
                <span class="about-who__title">Your first WordCamp</span>
                <span class="about-who__text">Not sure what a "track" is or who to talk to? A 5-minute guide and gentle tips walk you through the day.</span>
                <span class="about-who__cta">Read the first-timer guide →</span>
            </a>
            <a class="about-who__card" href="{{ route('guide') }}#guide-students" data-track="guide_open" data-track-surface="picker_who_student">
                <span class="about-who__emoji" aria-hidden="true">🎓</span>
                <span class="about-who__title">College students</span>
                <span class="about-who__text">Learn what the industry really uses, earn open-source experience for your résumé, and meet people who hire.</span>
                <span class="about-who__cta">What's in it for students →</span>
            </a>
            <a class="about-who__card" href="#find-your-camp">
                <span class="about-who__emoji" aria-hidden="true">🤝</span>
                <span class="about-who__title">WordCamp regulars</span>
                <span class="about-who__text">Plan your talks in a minute, get reminders, and meet new people who share your interests.</span>
                <span class="about-who__cta">Choose your WordCamp ↑</span>
            </a>
        </div>
    </section>

    <section class="about-faq" aria-labelledby="about-faq-heading">
        <div class="section-head">
            <h2 id="about-faq-heading" class="section-head__title">Quick questions</h2>
        </div>
        <div class="guide-accordion">
            @foreach ([
                ['Is it free?', 'Yes, completely — no ads, no paid features. It\'s made for WordCamp attendees.'],
                ['Do I need to sign up or download an app?', 'No account, ever, and nothing to download from a store. It opens in your phone\'s browser — tap "Install app" if you\'d like it on your home screen.'],
                ['Where does the schedule come from?', 'From each WordCamp\'s own website: sessions, speakers and sponsors are read from there and refreshed through the day. If anything differs, the WordCamp website and the organizers have the final word.'],
                ['I\'m a student — is this for me?', 'Very much. WordCamp is an affordable way to learn real-world skills and meet people who hire. Pick "Student" when you set up, and read the student section of the first-timer guide.'],
                ['What happens to what I type in?', 'Your answers, saved talks, Quest progress and Camp Card stay on your phone. Only what you choose to share in attendee discovery is shared with other attendees — and you can leave any time.'],
                ['Does it work with bad wifi?', 'Yes. Pages you\'ve opened keep working offline — handy in a crowded venue. Open your event once at home and you\'re set.'],
            ] as [$q, $a])
                <details class="guide-accordion__item" data-track-open="faq_open" data-track-question="{{ $q }}">
                    <summary>{{ $q }}</summary>
                    <p>{{ $a }}</p>
                </details>
            @endforeach
        </div>
    </section>
@endif
