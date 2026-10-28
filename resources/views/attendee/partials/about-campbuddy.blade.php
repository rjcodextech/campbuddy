{{--
    "What is CampBuddy?" for the WordCamp picker ("/")
      $part = 'steps'  — 9 Superpowers CSS Slider (1 item per slide)
      $part = 'more'   — Pathways ("Who it's for"), WPS Banner, FAQ
--}}
@if ($part === 'steps')
    <section class="landing-usp hide-in-pwa" aria-labelledby="usp-heading">
        <div class="section-head">
            <span class="u-eyebrow"><x-attendee.line-icon name="sparkles" /> WordCamp Day Ka Best Saathi</span>
            <h2 id="usp-heading" class="section-head__title">How CampBuddy helps you at WordCamp</h2>
            <span class="section-head__desc">Conference confusion, visiting card ki jhanjhat aur akelepan ko door karne ke liye 9 simple community superpowers &mdash; 100% free, bina kisi login aur offline-ready.</span>
        </div>

        <div class="usp-slider" id="usp-slider-root">
            <div class="usp-track" id="usp-slider-track" role="region" aria-label="CampBuddy superpowers slider" tabindex="0">
                {{-- USP Slide 1: First-Timer Guide (r01) --}}
                <div class="usp-slide" id="usp-slide-1" data-index="0">
                    <div class="usp-card usp-card--hero">
                        <div class="usp-card__media">
                            <span class="usp-card__hook">Pehla WordCamp Hai? Tension Mat Lo</span>
                            <div class="usp-card__mockup">
                                <img src="/media/usps/guide.png" alt="WordCamp Guide preview" loading="lazy" width="280" height="360">
                            </div>
                        </div>
                        <div class="usp-card__content">
                            <div class="usp-card__badge-row">
                                <span class="usp-card__category">
                                    <x-attendee.line-icon name="book-open" />
                                    <span>5-Min Newcomer Primer</span>
                                </span>
                                <span class="usp-card__num">01 / 09</span>
                            </div>
                            <h3 class="usp-card__title">1. Pehla WordCamp? &mdash; Akele Feel Nahi Hoga</h3>
                            <p class="usp-card__desc">
                                Pehli baar kisi bade conference mein jana thoda overwhelming lag sakta hai. Hamari 5-minute ki simple guide samjhati hai ki din kaise chalta hai, aam bol-chal ke words (Contributor Day, Hallway Track, Wapuu) ka kya matlab hai, aur 15-second ke simple icebreaker scripts se anjaan logon se bina kisi jhijhak baat kaise shuru karein.
                            </p>
                            <div class="usp-card__chips">
                                <span class="usp-chip"><x-attendee.line-icon name="help-circle" /> Jargon Dictionary (Wapuu, BoF)</span>
                                <span class="usp-chip"><x-attendee.line-icon name="graduation-cap" /> College Fresher Tips</span>
                                <span class="usp-chip"><x-attendee.line-icon name="coffee" /> Hallway Networking Icebreakers</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- USP Slide 2: Schedule & My Day (r02) --}}
                <div class="usp-slide" id="usp-slide-2" data-index="1">
                    <div class="usp-card usp-card--hero">
                        <div class="usp-card__media">
                            <span class="usp-card__hook">Favourite Talk Ab Miss Nahi Hogi</span>
                            <div class="usp-card__mockup">
                                <img src="/media/usps/myday.png" alt="Multi-Track Schedule preview" loading="lazy" width="280" height="360">
                            </div>
                        </div>
                        <div class="usp-card__content">
                            <div class="usp-card__badge-row">
                                <span class="usp-card__category">
                                    <x-attendee.line-icon name="calendar" />
                                    <span>Multi-Hall Timetable</span>
                                </span>
                                <span class="usp-card__num">02 / 09</span>
                            </div>
                            <h3 class="usp-card__title">2. Schedule &amp; "My Day" &mdash; Favourite Talk Kabhi Miss Nahi Hogi</h3>
                            <p class="usp-card__desc">
                                Lamba schedule, 2&ndash;3 parallel halls aur chhoti si bhool mein sabse achhi talk nikal jaati hai? CampBuddy ke My Day mein jo talk pasand aaye, bas ek tap mein star karo aur apna custom timetable banao. Talk shuru hone se 10 minute pehle CampBuddy yaad dilata hai ki kaunsi talk kis hall mein hai. Aur agar organizer schedule shift karein, to naya time automatically update ho jaata hai.
                            </p>
                            <div class="usp-card__chips">
                                <span class="usp-chip"><x-attendee.line-icon name="star" /> 1-Tap Star Karo</span>
                                <span class="usp-chip"><x-attendee.line-icon name="clock" /> 10-Min Hall Alert</span>
                                <span class="usp-chip"><x-attendee.line-icon name="calendar" /> Phone Calendar Sync</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- USP Slide 3: Attendee Discovery & Networking (r03) --}}
                <div class="usp-slide" id="usp-slide-3" data-index="2">
                    <div class="usp-card usp-card--hero">
                        <div class="usp-card__media">
                            <span class="usp-card__hook">Bheed Mein Apne Jaise Log Dhoondho</span>
                            <div class="usp-card__mockup">
                                <img src="/media/usps/people-discovery.png" alt="Attendee Discovery preview" loading="lazy" width="280" height="360">
                            </div>
                        </div>
                        <div class="usp-card__content">
                            <div class="usp-card__badge-row">
                                <span class="usp-card__category">
                                    <x-attendee.line-icon name="users" />
                                    <span>Attendee Discovery</span>
                                </span>
                                <span class="usp-card__num">03 / 09</span>
                            </div>
                            <h3 class="usp-card__title">3. Attendee Discovery &mdash; Apne Jaise Log Dhoondho (Zero Spam)</h3>
                            <p class="usp-card__desc">
                                2,000 logon ke bade hall mein jinse aap milna chahte hain, wo aksar usi bheed mein hote hain par aap unhe pehchan nahi paate. CampBuddy ke Explore &rarr; People mein apni interests chuno aur dekho kaun-kaun milna chahta hai. Ek-doosre ko private wave bhejo; jab dono wave karenge, tabhi ek-doosre ka naam dikhega &mdash; zero spam, zero sign-up.
                            </p>
                            <div class="usp-card__chips">
                                <span class="usp-chip"><x-attendee.line-icon name="sparkles" /> Mutual Private Wave</span>
                                <span class="usp-chip"><x-attendee.line-icon name="users" /> Interests Matchmaking</span>
                                <span class="usp-chip"><x-attendee.line-icon name="lock" /> 100% Spam-Free Privacy</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- USP Slide 4: Digital Camp Card (r04) --}}
                <div class="usp-slide" id="usp-slide-4" data-index="3">
                    <div class="usp-card usp-card--hero">
                        <div class="usp-card__media">
                            <span class="usp-card__hook">Visiting Card Ki Jagah Ab Ek QR</span>
                            <div class="usp-card__mockup">
                                <img src="/media/usps/camp-card.png" alt="Digital Camp Card preview" loading="lazy" width="280" height="360">
                            </div>
                        </div>
                        <div class="usp-card__content">
                            <div class="usp-card__badge-row">
                                <span class="usp-card__category">
                                    <x-attendee.line-icon name="camera" />
                                    <span>Paperless Networking</span>
                                </span>
                                <span class="usp-card__num">04 / 09</span>
                            </div>
                            <h3 class="usp-card__title">4. Digital Camp Card &mdash; Visiting Card Ki Jagah Bas Ek QR</h3>
                            <p class="usp-card__desc">
                                Kagaz ke visiting cards sambhalna aur LinkedIn handles type karne ka jhanjhat band karo. CampBuddy ka Camp Card aapka digital ID card hai: apna naam, role aur interests daalo. Saamne wala bas phone camera se aapka QR scan karega aur seedha aapke LinkedIn ya website par pahunch jayega &mdash; na unhe koi app download karni hai, na account banana hai.
                            </p>
                            <div class="usp-card__chips">
                                <span class="usp-chip"><x-attendee.line-icon name="camera" /> Phone Camera Se Scan</span>
                                <span class="usp-chip"><x-attendee.line-icon name="link" /> Direct LinkedIn Profile</span>
                                <span class="usp-chip"><x-attendee.line-icon name="sparkles" /> 7 Ready Card Designs</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- USP Slide 5: Contributor Day (r05) --}}
                <div class="usp-slide" id="usp-slide-5" data-index="4">
                    <div class="usp-card usp-card--hero">
                        <div class="usp-card__media">
                            <span class="usp-card__hook">Contributor Day Jaana Hai Par Code Nahi Aata?</span>
                            <div class="usp-card__mockup">
                                <img src="/media/usps/contribute.png" alt="Contributor Day Matchmaker preview" loading="lazy" width="280" height="360">
                            </div>
                        </div>
                        <div class="usp-card__content">
                            <div class="usp-card__badge-row">
                                <span class="usp-card__category">
                                    <x-attendee.line-icon name="wrench" />
                                    <span>Open Source Contribution</span>
                                </span>
                                <span class="usp-card__num">05 / 09</span>
                            </div>
                            <h3 class="usp-card__title">5. Contributor Day &mdash; Code Nahi Aata? Fir Bhi You Are Welcome</h3>
                            <p class="usp-card__desc">
                                WordCamp Contributor Day sirf coders ke liye nahi hota &mdash; yahan writers, designers, translators aur curious beginners sabke liye dedicated tables hoti hain. Bas do aasaan sawaalon ke jawab do aur CampBuddy batayega ki kaunsi WordPress team aapke liye perfect hai, shuruat kahan se karni hai aur table pe pahunch kar kya bolna hai.
                            </p>
                            <div class="usp-card__chips">
                                <span class="usp-chip"><x-attendee.line-icon name="compass" /> 2 Sawaal Ka Quick Quiz</span>
                                <span class="usp-chip"><x-attendee.line-icon name="users" /> Sahi Table Ki Pehchan</span>
                                <span class="usp-chip"><x-attendee.line-icon name="sprout" /> 100% Non-Tech Friendly</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- USP Slide 6: 100% Offline & PWA (r06) --}}
                <div class="usp-slide" id="usp-slide-6" data-index="5">
                    <div class="usp-card usp-card--hero">
                        <div class="usp-card__media">
                            <span class="usp-card__hook">Venue Wi-Fi Down? Tension Mat Lo</span>
                            <div class="usp-card__mockup">
                                <img src="/media/usps/offline-ready.png" alt="100% Offline and PWA preview" loading="lazy" width="280" height="360">
                            </div>
                        </div>
                        <div class="usp-card__content">
                            <div class="usp-card__badge-row">
                                <span class="usp-card__category">
                                    <x-attendee.line-icon name="zap" />
                                    <span>100% Offline Reliability</span>
                                </span>
                                <span class="usp-card__num">06 / 09</span>
                            </div>
                            <h3 class="usp-card__title">6. 100% Offline &amp; PWA &mdash; Venue Wi-Fi Down? Tension Mat Lo</h3>
                            <p class="usp-card__desc">
                                Bade conference mein venue Wi-Fi slow ya band ho jana aam baat hai. CampBuddy mein na koi account chahiye, na password: ek baar link kholo aur phone ke Home screen pe add kar lo. Poora multi-track schedule, first-timer guide aur digital Camp Card bina internet ke bhi 100% smooth chalta hai.
                            </p>
                            <div class="usp-card__chips">
                                <span class="usp-chip"><x-attendee.line-icon name="lock" /> No Login / No Password</span>
                                <span class="usp-chip"><x-attendee.line-icon name="zap" /> Zero Wi-Fi Dependency</span>
                                <span class="usp-chip"><x-attendee.line-icon name="download" /> Instant Home Screen App</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- USP Slide 7: Post-Event PDF Memories (r07) --}}
                <div class="usp-slide" id="usp-slide-7" data-index="6">
                    <div class="usp-card usp-card--hero">
                        <div class="usp-card__media">
                            <span class="usp-card__hook">WordCamp Khatam, Yaadein Hamesha Saath</span>
                            <div class="usp-card__mockup">
                                <img src="/media/usps/thanks.png" alt="Post-Event Memories and PDF Export preview" loading="lazy" width="280" height="360">
                            </div>
                        </div>
                        <div class="usp-card__content">
                            <div class="usp-card__badge-row">
                                <span class="usp-card__category">
                                    <x-attendee.line-icon name="download" />
                                    <span>Event Memories Journal</span>
                                </span>
                                <span class="usp-card__num">07 / 09</span>
                            </div>
                            <h3 class="usp-card__title">7. Post-Event PDF &mdash; WordCamp Khatam, Yaadein Hamesha Saath</h3>
                            <p class="usp-card__desc">
                                Event khatam hone ke baad sab bhool mat jao. Ek tap mein official photo album dekho aur 'Save My Day as PDF' se apne saare attended sessions, jin doston se mile unke contact links aur personal notes ko ek clean PDF journal mein download kar lo. Agle WordCamp mein yahi profile saath le ja sakte ho.
                            </p>
                            <div class="usp-card__chips">
                                <span class="usp-chip"><x-attendee.line-icon name="download" /> 1-Click PDF Summary</span>
                                <span class="usp-chip"><x-attendee.line-icon name="camera" /> Official Photo Albums</span>
                                <span class="usp-chip"><x-attendee.line-icon name="play" /> WordPress.tv Session Links</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- USP Slide 8: For Organizers (r08) --}}
                <div class="usp-slide" id="usp-slide-8" data-index="7">
                    <div class="usp-card usp-card--hero">
                        <div class="usp-card__media">
                            <span class="usp-card__hook">Organizers: Alag Se App Banane Ka Jhanjhat Khatam</span>
                            <div class="usp-card__mockup">
                                <img src="/media/usps/for-organizers.png" alt="For Organizers preview" loading="lazy" width="280" height="360">
                            </div>
                        </div>
                        <div class="usp-card__content">
                            <div class="usp-card__badge-row">
                                <span class="usp-card__category">
                                    <x-attendee.line-icon name="heart" />
                                    <span>Community Organizers Tool</span>
                                </span>
                                <span class="usp-card__num">08 / 09</span>
                            </div>
                            <h3 class="usp-card__title">8. For Organizers &mdash; Aapke WordCamp Ka Ready Free App</h3>
                            <p class="usp-card__desc">
                                WordCamp organizers ke paas already bohot kaam hota hai; attendees ke liye naya app banana ek aur bojh nahi banna chahiye. CampBuddy WordCamp Central se naye events khud le leta hai, aapki site se schedule aur speakers sync karta hai aur aapka official logo dikhata hai &mdash; bina kisi technical setup ke, 100% free.
                            </p>
                            <div class="usp-card__chips">
                                <span class="usp-chip"><x-attendee.line-icon name="compass" /> WordCamp Central Auto-Sync</span>
                                <span class="usp-chip"><x-attendee.line-icon name="clock" /> Har 15-Min Live Fresh</span>
                                <span class="usp-chip"><x-attendee.line-icon name="sparkles" /> Zero Setup Cost</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- USP Slide 9: Running Late & Live Updates (r09) --}}
                <div class="usp-slide" id="usp-slide-9" data-index="8">
                    <div class="usp-card usp-card--hero">
                        <div class="usp-card__media">
                            <span class="usp-card__hook">Talk 20 Min Late? Mic Pe Chillana Band</span>
                            <div class="usp-card__mockup">
                                <img src="/media/usps/running-late.png" alt="Running Late Schedule Delay preview" loading="lazy" width="280" height="360">
                            </div>
                        </div>
                        <div class="usp-card__content">
                            <div class="usp-card__badge-row">
                                <span class="usp-card__category">
                                    <x-attendee.line-icon name="clock" />
                                    <span>Real-Time Schedule Delay</span>
                                </span>
                                <span class="usp-card__num">09 / 09</span>
                            </div>
                            <h3 class="usp-card__title">9. Running Late &mdash; Talk Late Hai? Ek Tap Mein Schedule Shift</h3>
                            <p class="usp-card__desc">
                                Pehli talk 20 minute late ho gayi? Mic pe chillane ki zarurat nahi. Organizer panel mein bas Running late set karein aur har attendee ke phone par My Day timetable aur reminders automatically naye time pe shift ho jaate hain. Saath mein 'Lunch is ready' jaise live broadcast updates seedha phone pe pahunchte hain.
                            </p>
                            <div class="usp-card__chips">
                                <span class="usp-chip"><x-attendee.line-icon name="clock" /> 1-Tap Timetable Shift</span>
                                <span class="usp-chip"><x-attendee.line-icon name="zap" /> Auto Reminder Adjustment</span>
                                <span class="usp-chip"><x-attendee.line-icon name="sparkles" /> Mic-Free Live Broadcast</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Slider Navigation Controls --}}
            <div class="usp-slider__controls">
                <button type="button" class="usp-slider__arrow usp-slider__arrow--prev" id="usp-slider-prev" aria-label="Previous superpower">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M15 18l-6-6 6-6"/>
                    </svg>
                </button>

                <div class="usp-slider__dots" id="usp-slider-dots" role="tablist" aria-label="Superpower slides">
                    <button type="button" class="usp-slider__dot is-active" data-index="0" aria-label="Slide 1: First-Timer Guide" role="tab" aria-selected="true"></button>
                    <button type="button" class="usp-slider__dot" data-index="1" aria-label="Slide 2: Multi-Track Schedule" role="tab" aria-selected="false"></button>
                    <button type="button" class="usp-slider__dot" data-index="2" aria-label="Slide 3: Attendee Discovery" role="tab" aria-selected="false"></button>
                    <button type="button" class="usp-slider__dot" data-index="3" aria-label="Slide 4: Digital Camp Card" role="tab" aria-selected="false"></button>
                    <button type="button" class="usp-slider__dot" data-index="4" aria-label="Slide 5: Contributor Day Matchmaker" role="tab" aria-selected="false"></button>
                    <button type="button" class="usp-slider__dot" data-index="5" aria-label="Slide 6: 100% Offline & PWA" role="tab" aria-selected="false"></button>
                    <button type="button" class="usp-slider__dot" data-index="6" aria-label="Slide 7: PDF Memories" role="tab" aria-selected="false"></button>
                    <button type="button" class="usp-slider__dot" data-index="7" aria-label="Slide 8: For Organizers" role="tab" aria-selected="false"></button>
                    <button type="button" class="usp-slider__dot" data-index="8" aria-label="Slide 9: Running Late Alerts" role="tab" aria-selected="false"></button>
                </div>

                <button type="button" class="usp-slider__arrow usp-slider__arrow--next" id="usp-slider-next" aria-label="Next superpower">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M9 18l6-6-6-6"/>
                    </svg>
                </button>
            </div>
        </div>
    </section>
@else
    {{-- Section: Co-Branded WPSimplified Banner (Keep this as it is) --}}
    <section class="about-wps-banner" aria-labelledby="wps-partnership-title">
        <div class="about-wps-banner__inner">
            <div class="about-wps-banner__badge">
                <span class="live-dot live-dot--green"></span>
                <span>Open Source Community Initiative</span>
            </div>
            <h3 id="wps-partnership-title" class="about-wps-banner__title">
                CampBuddy &times; <a href="https://wpsimplified.in" target="_blank" rel="noopener">WPSimplified</a>
            </h3>
            <p class="about-wps-banner__desc">
                Built and maintained by WPSimplified as a free, open-source gift for the global WordPress community. Zero sign-up, zero ads, and 100% offline-ready for every WordCamp worldwide.
            </p>
            <div class="about-wps-banner__actions">
                <a href="{{ route('about') }}" class="btn btn--primary">
                    <span>Read Full Story</span>
                    <span aria-hidden="true">&rarr;</span>
                </a>
            </div>
        </div>
    </section>

    {{-- Section: Quick Questions FAQ --}}
    <section class="about-faq" aria-labelledby="about-faq-heading">
        <div class="section-head">
            <span class="u-eyebrow"><x-attendee.line-icon name="help-circle" /> Common Inquiries</span>
            <h2 id="about-faq-heading" class="section-head__title">Quick questions</h2>
            <span class="section-head__desc">Everything you need to know about privacy, offline reliability, and schedule syncing</span>
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
