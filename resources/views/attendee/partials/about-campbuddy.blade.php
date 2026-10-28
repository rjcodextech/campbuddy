{{--
    "What is CampBuddy?" for the WordCamp picker ("/")
      $part = 'steps'  — 9 Superpowers CSS Slider (1 item per slide)
      $part = 'more'   — WPS Banner, FAQ
--}}
@if ($part === 'steps')
    <section class="landing-usp hide-in-pwa" aria-labelledby="usp-heading">
        <div class="section-head">
            <span class="u-eyebrow"><x-attendee.line-icon name="sparkles" /> Essential WordCamp Companion</span>
            <h2 id="usp-heading" class="section-head__title">How CampBuddy helps you at WordCamp</h2>
            <span class="section-head__desc">Nine focused superpowers engineered to eliminate conference confusion, hallway hesitation, and paper card clutter &mdash; 100% free, zero login, and completely offline-ready.</span>
        </div>

        <div class="usp-slider" id="usp-slider-root">
            <div class="usp-track" id="usp-slider-track" role="region" aria-label="CampBuddy superpowers slider" tabindex="0">
                {{-- USP Slide 1: First-Timer Guide (r01) --}}
                <div class="usp-slide" id="usp-slide-1" data-index="0">
                    <div class="usp-card usp-card--hero">
                        <div class="usp-card__media">
                            <span class="usp-card__hook">First WordCamp? You're in Good Hands</span>
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
                            <h3 class="usp-card__title">1. First WordCamp? &mdash; Never Feel Lost in the Crowd</h3>
                            <p class="usp-card__desc">
                                Stepping into a massive tech conference can feel overwhelming. Our 5-minute newcomer primer breaks down how the day unfolds, explains community jargon (Contributor Day, Hallway Track, Wapuu), and equips you with 15-second natural icebreaker scripts so you can start conversations with confidence.
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
                            <span class="usp-card__hook">Never Miss Your Favorite Session</span>
                            <div class="usp-card__mockup">
                                <img src="/media/usps/myday.png" alt="Multi-Track Schedule preview" loading="lazy" width="280" height="360">
                            </div>
                        </div>
                        <div class="usp-card__content">
                            <div class="usp-card__badge-row">
                                <span class="usp-card__category">
                                    <x-attendee.line-icon name="calendar" />
                                    <span>Multi-Track Schedule</span>
                                </span>
                                <span class="usp-card__num">02 / 09</span>
                            </div>
                            <h3 class="usp-card__title">2. Schedule &amp; &ldquo;My Day&rdquo; &mdash; Curate Your Personal Timetable</h3>
                            <p class="usp-card__desc">
                                With parallel tracks across multiple auditoriums, it is easy to lose track of key sessions. In CampBuddy, star the talks that matter to you with a single tap to build your custom schedule. Get smart reminders 10 minutes before sessions start, with automatic timetable shifts whenever organizers adjust timing.
                            </p>
                            <div class="usp-card__chips">
                                <span class="usp-chip"><x-attendee.line-icon name="star" /> 1-Tap Star Bookmarks</span>
                                <span class="usp-chip"><x-attendee.line-icon name="clock" /> 10-Min Hall Reminders</span>
                                <span class="usp-chip"><x-attendee.line-icon name="calendar" /> Phone Calendar Sync</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- USP Slide 3: Attendee Discovery & Networking (r03) --}}
                <div class="usp-slide" id="usp-slide-3" data-index="2">
                    <div class="usp-card usp-card--hero">
                        <div class="usp-card__media">
                            <span class="usp-card__hook">Find Your Tribe in the Crowd</span>
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
                            <h3 class="usp-card__title">3. Attendee Discovery &mdash; Connect with Zero Spam</h3>
                            <p class="usp-card__desc">
                                In a venue of 2,000+ attendees, finding peers who share your niche or passion can be daunting. CampBuddy lets you filter by shared interests, skills, or roles. Send a lightweight private wave to someone you want to meet: contact details are only revealed when both parties mutually wave &mdash; zero spam, zero sign-up.
                            </p>
                            <div class="usp-card__chips">
                                <span class="usp-chip"><x-attendee.line-icon name="sparkles" /> Mutual Private Wave</span>
                                <span class="usp-chip"><x-attendee.line-icon name="users" /> Interest Matchmaking</span>
                                <span class="usp-chip"><x-attendee.line-icon name="lock" /> 100% Spam-Free Privacy</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- USP Slide 4: Digital Camp Card (r04) --}}
                <div class="usp-slide" id="usp-slide-4" data-index="3">
                    <div class="usp-card usp-card--hero">
                        <div class="usp-card__media">
                            <span class="usp-card__hook">Ditch Paper Cards for One Instant QR</span>
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
                            <h3 class="usp-card__title">4. Digital Camp Card &mdash; Instant Paperless Networking</h3>
                            <p class="usp-card__desc">
                                Forget carrying paper business cards or clumsily typing social handles into phones. Your Camp Card is an instant digital conference badge: set your name, title, and social links. Other attendees simply scan your QR code with their default camera app to open your profile directly &mdash; no app download or sign-up needed on their end.
                            </p>
                            <div class="usp-card__chips">
                                <span class="usp-chip"><x-attendee.line-icon name="camera" /> Native Camera Scan</span>
                                <span class="usp-chip"><x-attendee.line-icon name="link" /> Direct Profile Link</span>
                                <span class="usp-chip"><x-attendee.line-icon name="sparkles" /> 7 Curated Card Themes</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- USP Slide 5: Contributor Day (r05) --}}
                <div class="usp-slide" id="usp-slide-5" data-index="4">
                    <div class="usp-card usp-card--hero">
                        <div class="usp-card__media">
                            <span class="usp-card__hook">No Coding Experience? You Belong Here</span>
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
                            <h3 class="usp-card__title">5. Contributor Day &mdash; Meaningful Contributions Without Coding</h3>
                            <p class="usp-card__desc">
                                WordCamp Contributor Day is for everyone &mdash; not just backend developers. Writers, designers, translators, documenters, and curious first-timers have dedicated team tables. Answer two quick questions to find your ideal team, understand where to sit, and get proven scripts for introducing yourself to table leads.
                            </p>
                            <div class="usp-card__chips">
                                <span class="usp-chip"><x-attendee.line-icon name="compass" /> 2-Question Team Matcher</span>
                                <span class="usp-chip"><x-attendee.line-icon name="users" /> Find the Right Table</span>
                                <span class="usp-chip"><x-attendee.line-icon name="sprout" /> 100% Non-Tech Friendly</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- USP Slide 6: 100% Offline & PWA (r06) --}}
                <div class="usp-slide" id="usp-slide-6" data-index="5">
                    <div class="usp-card usp-card--hero">
                        <div class="usp-card__media">
                            <span class="usp-card__hook">Venue Wi-Fi Down? It Keeps Running</span>
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
                            <h3 class="usp-card__title">6. 100% Offline &amp; Instant PWA &mdash; Built for Crowded Venues</h3>
                            <p class="usp-card__desc">
                                Conference Wi-Fi routinely stutters or drops under heavy auditorium load. CampBuddy requires no account and no login: open the link once and add it to your home screen. Your entire multi-track schedule, first-timer guide, and digital Camp Card function seamlessly completely offline with local storage.
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
                            <span class="usp-card__hook">Event Memories in a Keepsake PDF</span>
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
                            <h3 class="usp-card__title">7. Post-Event Memories &mdash; Your WordCamp Keepsake</h3>
                            <p class="usp-card__desc">
                                Don't let the knowledge and connections fade once the event closes. Access official photo albums and generate a beautiful 'Save My Day' PDF keepsake compiling your attended sessions, speakers heard, new peer contacts, and personal notes. Take your verified badge to your next WordCamp.
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
                            <span class="usp-card__hook">Ready-to-Deploy Companion for Organizers</span>
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
                            <h3 class="usp-card__title">8. For Organizers &mdash; Zero Setup Conference Companion</h3>
                            <p class="usp-card__desc">
                                Organizing teams are already stretched thin; building and maintaining custom event apps shouldn't add to the burden. CampBuddy automatically syncs schedules, speakers, and event details directly from WordCamp Central every 15 minutes, styled with your official branding &mdash; completely free for any WordCamp community.
                            </p>
                            <div class="usp-card__chips">
                                <span class="usp-chip"><x-attendee.line-icon name="compass" /> WordCamp Central Auto-Sync</span>
                                <span class="usp-chip"><x-attendee.line-icon name="clock" /> 15-Min Live Freshness</span>
                                <span class="usp-chip"><x-attendee.line-icon name="sparkles" /> Zero Setup Cost</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- USP Slide 9: Running Late & Live Updates (r09) --}}
                <div class="usp-slide" id="usp-slide-9" data-index="8">
                    <div class="usp-card usp-card--hero">
                        <div class="usp-card__media">
                            <span class="usp-card__hook">Sessions Running 20 Mins Late? Update in One Tap</span>
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
                            <h3 class="usp-card__title">9. Running Late Alerts &mdash; Instant Timetable Shift</h3>
                            <p class="usp-card__desc">
                                When keynote sessions or hallway breaks run overtime, organizers can shift the entire conference timetable by 10, 15, or 20 minutes in a single tap. Every attendee's personal schedule and upcoming notifications automatically adjust on their phones, along with urgent broadcast updates like 'Lunch is now served'.
                            </p>
                            <div class="usp-card__chips">
                                <span class="usp-chip"><x-attendee.line-icon name="clock" /> 1-Tap Timetable Shift</span>
                                <span class="usp-chip"><x-attendee.line-icon name="zap" /> Auto Reminder Adjustment</span>
                                <span class="usp-chip"><x-attendee.line-icon name="sparkles" /> Instant Live Broadcasts</span>
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
